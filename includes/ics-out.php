<?php
/**
 * Bookings as iCal: a private feed with the confirmed bookings for any calendar app,
 * and a .ics file attached to the client's e-mail when a booking is confirmed or cancelled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The same UID in the feed and in the client's file, so a calendar app sees one event. */
function bkb_ics_uid( $id ) {
	return 'shootbook-' . (int) $id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
}

/** Whether a UID belongs to a booking of this site (such events are not busy time from outside). */
function bkb_ics_is_own_uid( $uid ) {
	return 1 === preg_match( '/^shootbook-\d+@' . preg_quote( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '/' ) . '$/', (string) $uid );
}

/** Whether an event of a calendar feed is one of this site's bookings, sent there by the feed or by Google sync. */
function bkb_is_own_event( $uid ) {
	return '' !== (string) $uid && (bool) apply_filters( 'bkb_is_own_event', bkb_ics_is_own_uid( $uid ), (string) $uid );
}

function bkb_ics_escape( $s ) {
	return str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\\;', '\\,', '\\n', '\\n' ), (string) $s );
}

/** Folds lines longer than 75 bytes, as RFC 5545 asks. */
function bkb_ics_fold( $line ) {
	$out = '';
	while ( strlen( $line ) > 75 ) {
		$cut = 75;
		while ( $cut > 1 && ( ord( $line[ $cut ] ) & 0xC0 ) === 0x80 ) {
			--$cut; // Do not split a UTF-8 character.
		}
		$out .= substr( $line, 0, $cut ) . "\r\n ";
		$line  = substr( $line, $cut );
	}
	return $out . $line;
}

/**
 * One VEVENT.
 *
 * @param array  $b      Booking from bkb_get().
 * @param string $title  SUMMARY.
 * @param string $text   DESCRIPTION.
 * @param bool   $cancel Write it as cancelled.
 */
function bkb_ics_event( $b, $title, $text, $cancel = false ) {
	$fmt  = fn( $ts ) => gmdate( 'Ymd\THis\Z', (int) $ts );
	$loc  = bkb_location( (string) $b['location'] );
	$post = get_post( (int) $b['id'] );
	$mod  = $post ? strtotime( $post->post_modified_gmt . ' UTC' ) : time();
	$l    = array(
		'BEGIN:VEVENT',
		'UID:' . bkb_ics_uid( $b['id'] ),
		'DTSTAMP:' . $fmt( time() ),
		'LAST-MODIFIED:' . $fmt( max( $mod, (int) get_post_meta( (int) $b['id'], '_bkb_moved', true ) ) ),
		// A later file must win over an earlier one: the sequence grows with every change of time or status.
		'SEQUENCE:' . (int) get_post_meta( (int) $b['id'], '_bkb_seq', true ),
		'DTSTART:' . $fmt( $b['start'] ),
		'DTEND:' . $fmt( $b['end'] ),
		'SUMMARY:' . bkb_ics_escape( $title ),
		'DESCRIPTION:' . bkb_ics_escape( $text ),
	);
	if ( ! empty( $loc['address'] ) ) {
		$l[] = 'LOCATION:' . bkb_ics_escape( $loc['address'] );
	}
	$l[] = 'STATUS:' . ( $cancel ? 'CANCELLED' : ( 'confirmed' === $b['status'] ? 'CONFIRMED' : 'TENTATIVE' ) );
	$l[] = 'END:VEVENT';
	return $l;
}

function bkb_ics_calendar( $events, $method = 'PUBLISH', $name = '' ) {
	$l = array( 'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Shootbook//' . BKB_VER . '//EN', 'CALSCALE:GREGORIAN', 'METHOD:' . $method );
	if ( '' !== $name ) {
		$l[] = 'X-WR-CALNAME:' . bkb_ics_escape( $name );
		$l[] = 'X-PUBLISHED-TTL:PT15M';
		$l[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT15M';
	}
	foreach ( $events as $e ) {
		$l = array_merge( $l, $e );
	}
	$l[] = 'END:VCALENDAR';
	return implode( "\r\n", array_map( 'bkb_ics_fold', $l ) ) . "\r\n";
}

/* Every change of time or status makes the booking newer for calendar apps. */
add_action(
	'updated_post_meta',
	function ( $mid, $id, $key ) {
		if ( in_array( $key, array( '_bkb_start', '_bkb_status' ), true ) && 'bk_booking' === get_post_type( $id ) ) {
			update_post_meta( $id, '_bkb_seq', (int) get_post_meta( $id, '_bkb_seq', true ) + 1 );
			update_post_meta( $id, '_bkb_moved', time() );
		}
	},
	10,
	3
);

/* ---------- the feed ---------- */

/** The secret part of the feed address; made on first use, a new one makes the old address stop working. */
function bkb_feed_key( $renew = false ) {
	$k = (string) get_option( 'bkb_feed_key', '' );
	if ( '' === $k || $renew ) {
		$k = wp_generate_password( 32, false );
		update_option( 'bkb_feed_key', $k, false );
	}
	return $k;
}

function bkb_feed_url() {
	return add_query_arg( 'key', bkb_feed_key(), rest_url( 'bk-booking/v1/feed' ) );
}

/** Confirmed bookings from 60 days ago on, as one calendar. */
function bkb_feed_ics() {
	$ids    = get_posts(
		array(
			'post_type'      => 'bk_booking',
			'post_status'    => 'publish',
			'posts_per_page' => 1000,
			'fields'         => 'ids',
			'lang'           => '',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => '_bkb_status', 'value' => 'confirmed' ),
				array( 'key' => '_bkb_end', 'value' => time() - 60 * DAY_IN_SECONDS, 'compare' => '>', 'type' => 'NUMERIC' ),
			),
		)
	);
	$events = array();
	foreach ( $ids as $id ) {
		$b        = bkb_get( $id );
		$events[] = bkb_ics_event( $b, bkb_gcal_title( $b ), bkb_gcal_text( $b ) );
	}
	return bkb_ics_calendar( $events, 'PUBLISH', bkb_site_name() );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'bk-booking/v1',
			'/feed',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $r ) {
					if ( ! hash_equals( bkb_feed_key(), (string) $r->get_param( 'key' ) ) ) {
						return new WP_Error( 'forbidden', 'forbidden', array( 'status' => 403 ) );
					}
					$ics = bkb_feed_ics();
					add_filter(
						'rest_pre_serve_request',
						function () use ( $ics ) {
							header( 'Content-Type: text/calendar; charset=utf-8' );
							header( 'Cache-Control: no-store' );
							header( 'Content-Disposition: inline; filename="bookings.ics"' );
							echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							return true;
						}
					);
					return rest_ensure_response( '' );
				},
			)
		);
	}
);

/* ---------- the client's file ---------- */

/**
 * A file for the client's e-mail: the booking when it is confirmed, a cancellation when a
 * booking that was confirmed is declined. Returns a path to attach, or ''.
 */
function bkb_client_ics_file( $id, $type, $lang ) {
	$b = bkb_get( $id );
	if ( 'confirmed' === $type ) {
		$cancel = false;
	} elseif ( 'declined' === $type && get_post_meta( $id, '_bkb_ics_sent', true ) ) {
		$cancel = true;
	} else {
		return '';
	}
	if ( ! apply_filters( 'bkb_client_ics', true, $b, $type ) ) {
		return '';
	}
	$svc  = $b['service'] ? ' — ' . bkb_service_label( $b['service'], $lang ) : '';
	$text = bkb_summary( $b, $lang ) . "\n\n" . trim( bkb_cfg( 'contact' ) . "\n" . bkb_reply_to() );
	$ics  = bkb_ics_calendar( array( bkb_ics_event( $b, bkb_site_name() . $svc, $text, $cancel ) ), $cancel ? 'CANCEL' : 'PUBLISH' );
	$dir  = trailingslashit( get_temp_dir() ) . 'bkb-' . wp_generate_password( 12, false );
	if ( ! wp_mkdir_p( $dir ) ) {
		return '';
	}
	$file = $dir . '/booking.ics';
	if ( false === file_put_contents( $file, $ics ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return '';
	}
	if ( $cancel ) {
		delete_post_meta( $id, '_bkb_ics_sent' );
	} else {
		update_post_meta( $id, '_bkb_ics_sent', 1 );
	}
	return $file;
}

function bkb_client_ics_cleanup( $file ) {
	if ( $file && file_exists( $file ) ) {
		wp_delete_file( $file );
		@rmdir( dirname( $file ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
