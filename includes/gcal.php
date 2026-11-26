<?php
/**
 * Google Calendar sync through the Google Calendar API.
 *
 * Bookings → Google: a confirmed booking becomes an event in the chosen calendar; a new time
 * moves it; declining, deleting or moving the request to the bin removes it. Requests that
 * wait for an answer can go there too, marked with "?". A change that Google did not take
 * (no network, an error) is tried again every hour.
 *
 * Google → bookings: the events of the chosen calendar are busy time on the site, read
 * straight from Google. The site's own events are left out, so they do not count twice.
 *
 * Each site uses its own OAuth client from Google Cloud. The keys are kept in their own
 * option, never in the Shootbook settings export.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BKB_GCAL_API', 'https://www.googleapis.com/calendar/v3/' );
define( 'BKB_GCAL_SCOPES', 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.calendarlist.readonly' );

/* ---------- settings ---------- */

function bkb_gcal_get( $key = null ) {
	$o = wp_parse_args(
		(array) get_option( 'bkb_gcal', array() ),
		array(
			'client_id'     => '',
			'client_secret' => '',
			'refresh'       => '',
			'token'         => '',
			'expires'       => 0,
			'account'       => '',
			'calendar'      => '',
			'calendar_name' => '',
			'pending'       => false,
			'busy'          => true,
			'last_error'    => '',
			'last_push'     => 0,
		)
	);
	return null === $key ? $o : $o[ $key ];
}

function bkb_gcal_set( $data ) {
	update_option( 'bkb_gcal', array_merge( bkb_gcal_get(), $data ), false );
}

function bkb_gcal_connected() {
	$o = bkb_gcal_get();
	return '' !== $o['refresh'] && '' !== $o['calendar'];
}

/** The address Google sends the owner back to after signing in. It goes into the OAuth client in Google Cloud. */
function bkb_gcal_redirect_uri() {
	return admin_url( 'admin-post.php' );
}

/* ---------- calls ---------- */

/** A fresh access token, renewed with the refresh token when the old one is about to end. */
function bkb_gcal_token() {
	$o = bkb_gcal_get();
	if ( '' === $o['refresh'] ) {
		return new WP_Error( 'bkb_gcal', 'not connected' );
	}
	if ( '' !== $o['token'] && $o['expires'] > time() + 60 ) {
		return $o['token'];
	}
	$r = bkb_gcal_oauth(
		array(
			'grant_type'    => 'refresh_token',
			'refresh_token' => $o['refresh'],
		)
	);
	if ( is_wp_error( $r ) ) {
		// Access was taken back in the Google account, or the app is in Testing and the 7 days are over.
		if ( 'invalid_grant' === $r->get_error_code() ) {
			$r = new WP_Error( 'bkb_gcal_off', __( 'Google no longer gives access. Sign in again.', 'shootbook' ) );
			bkb_gcal_set(
				array(
					'refresh'    => '',
					'token'      => '',
					'last_error' => $r->get_error_message(),
				)
			);
		}
		return $r;
	}
	bkb_gcal_set(
		array(
			'token'   => (string) $r['access_token'],
			'expires' => time() + (int) ( $r['expires_in'] ?? 3600 ),
		)
	);
	return (string) $r['access_token'];
}

/** A call to Google's token endpoint with the site's client. */
function bkb_gcal_oauth( $args ) {
	$o   = bkb_gcal_get();
	$res = wp_remote_post(
		'https://oauth2.googleapis.com/token',
		array(
			'timeout' => 15,
			'body'    => $args + array(
				'client_id'     => $o['client_id'],
				'client_secret' => $o['client_secret'],
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $body ) || empty( $body['access_token'] ) ) {
		$code = is_array( $body ) && ! empty( $body['error'] ) ? (string) $body['error'] : 'http_' . wp_remote_retrieve_response_code( $res );
		$msg  = is_array( $body ) && ! empty( $body['error_description'] ) ? (string) $body['error_description'] : $code;
		return new WP_Error( $code, $msg );
	}
	return $body;
}

/**
 * A call to the Calendar API.
 *
 * @param string     $method GET, POST, PATCH or DELETE.
 * @param string     $path   Path after /calendar/v3/.
 * @param array      $query  Query arguments.
 * @param array|null $body   JSON body.
 * @return array|WP_Error The answer; the error code is the HTTP status for Google's errors.
 */
function bkb_gcal_call( $method, $path, $query = array(), $body = null ) {
	$token = bkb_gcal_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	$req = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array( 'Authorization' => 'Bearer ' . $token ),
	);
	if ( null !== $body ) {
		$req['headers']['Content-Type'] = 'application/json';
		$req['body']                    = wp_json_encode( $body );
	}
	$res = wp_remote_request( add_query_arg( array_map( 'rawurlencode', $query ), BKB_GCAL_API . $path ), $req );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code >= 300 ) {
		$msg = is_array( $data ) && ! empty( $data['error']['message'] ) ? (string) $data['error']['message'] : 'HTTP ' . $code;
		return new WP_Error( (string) $code, $msg );
	}
	return is_array( $data ) ? $data : array();
}

function bkb_gcal_cal_path( $cal = null ) {
	return 'calendars/' . rawurlencode( null === $cal ? bkb_gcal_get( 'calendar' ) : $cal ) . '/events';
}

/* ---------- signing in ---------- */

/** Where the "Sign in with Google" button leads. */
function bkb_gcal_auth_url() {
	$state = wp_generate_password( 24, false );
	set_transient( 'bkb_gcal_state_' . get_current_user_id(), $state, 30 * MINUTE_IN_SECONDS );
	return add_query_arg(
		array_map(
			'rawurlencode',
			array(
				'client_id'              => bkb_gcal_get( 'client_id' ),
				'redirect_uri'           => bkb_gcal_redirect_uri(),
				'response_type'          => 'code',
				'scope'                  => BKB_GCAL_SCOPES,
				'access_type'            => 'offline',
				'prompt'                 => 'consent',
				'include_granted_scopes' => 'true',
				'state'                  => 'bkb_gcal.' . $state,
			)
		),
		'https://accounts.google.com/o/oauth2/v2/auth'
	);
}

function bkb_gcal_back( $msg, $err = '' ) {
	if ( '' !== $err ) {
		set_transient( 'bkb_gcal_err', $err, 120 );
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=bk_booking&page=bkb-gcal&bkb_gcal=' . $msg ) );
	exit;
}

/*
 * Google sends the owner back to admin-post.php with ?code=…&state=bkb_gcal.…; admin-post.php
 * without an action runs the "admin_post" hook.
 */
add_action(
	'admin_post',
	function () {
		$state = (string) wp_unslash( $_GET['state'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( 0 !== strpos( $state, 'bkb_gcal.' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		$key  = 'bkb_gcal_state_' . get_current_user_id();
		$want = (string) get_transient( $key );
		delete_transient( $key );
		if ( '' === $want || ! hash_equals( 'bkb_gcal.' . $want, $state ) ) {
			bkb_gcal_back( 'err', __( 'The sign-in link is old. Try again.', 'shootbook' ) );
		}
		if ( ! empty( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			bkb_gcal_back( 'err', sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		$r = bkb_gcal_oauth(
			array(
				'grant_type'   => 'authorization_code',
				'code'         => sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification
				'redirect_uri' => bkb_gcal_redirect_uri(),
			)
		);
		if ( is_wp_error( $r ) ) {
			bkb_gcal_back( 'err', $r->get_error_message() );
		}
		if ( empty( $r['refresh_token'] ) ) {
			bkb_gcal_back( 'err', __( 'Google did not give a key for constant access. Remove the access of this app in your Google account and sign in again.', 'shootbook' ) );
		}
		bkb_gcal_set(
			array(
				'refresh'    => (string) $r['refresh_token'],
				'token'      => (string) $r['access_token'],
				'expires'    => time() + (int) ( $r['expires_in'] ?? 3600 ),
				'last_error' => '',
			)
		);
		// The main calendar of the account until another one is chosen.
		$list = bkb_gcal_calendars();
		if ( is_wp_error( $list ) ) {
			bkb_gcal_back( 'err', $list->get_error_message() );
		}
		$main = null;
		foreach ( $list as $c ) {
			if ( ! empty( $c['primary'] ) ) {
				$main = $c;
			}
		}
		$cur = bkb_gcal_get( 'calendar' );
		$ids = array_column( $list, 'id' );
		if ( $main ) {
			bkb_gcal_set( array( 'account' => $main['id'] ) );
		}
		if ( '' === $cur || ! in_array( $cur, $ids, true ) ) {
			$pick = $main ? $main : ( $list[0] ?? null );
			bkb_gcal_use_calendar( $pick ? $pick['id'] : '', $pick ? $pick['summary'] : '' );
		} else {
			bkb_gcal_mark_all();
			bkb_gcal_push_dirty( 30 );
		}
		bkb_gcal_back( 'connected' );
	}
);

/** Calendars the account can write to: array( array( id, summary, primary ), … ). */
function bkb_gcal_calendars() {
	$r = bkb_gcal_call(
		'GET',
		'users/me/calendarList',
		array(
			'minAccessRole' => 'writer',
			'maxResults'    => 250,
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$out = array();
	foreach ( (array) ( $r['items'] ?? array() ) as $c ) {
		$out[] = array(
			'id'      => (string) $c['id'],
			'summary' => (string) ( $c['summaryOverride'] ?? $c['summary'] ?? $c['id'] ),
			'primary' => ! empty( $c['primary'] ),
		);
	}
	return $out;
}

/** Switch to a calendar and send all current bookings there. Events in the old calendar are removed when they are touched. */
function bkb_gcal_use_calendar( $id, $name ) {
	bkb_gcal_set(
		array(
			'calendar'      => (string) $id,
			'calendar_name' => (string) $name,
		)
	);
	bkb_gcal_busy_flush();
	if ( '' !== $id ) {
		bkb_gcal_mark_all();
		bkb_gcal_push_dirty( 30 );
	}
}

function bkb_gcal_disconnect() {
	$o = bkb_gcal_get();
	if ( '' !== $o['refresh'] ) {
		wp_remote_post( 'https://oauth2.googleapis.com/revoke', array( 'timeout' => 10, 'body' => array( 'token' => $o['refresh'] ) ) );
	}
	bkb_gcal_set(
		array(
			'refresh'    => '',
			'token'      => '',
			'expires'    => 0,
			'last_error' => '',
		)
	);
	bkb_gcal_busy_flush();
}

/* ---------- bookings → Google ---------- */

/** Whether a booking should be in Google now. */
function bkb_gcal_wants( $id ) {
	$post = get_post( $id );
	if ( ! $post || 'bk_booking' !== $post->post_type || 'publish' !== $post->post_status ) {
		return false;
	}
	$s = (string) get_post_meta( $id, '_bkb_status', true );
	return 'confirmed' === $s || ( 'pending' === $s && bkb_gcal_get( 'pending' ) );
}

function bkb_gcal_event_body( $id ) {
	$b    = bkb_get( $id );
	$loc  = bkb_location( (string) $b['location'] );
	$wait = 'confirmed' !== $b['status'];
	$tz   = wp_timezone_string();
	$when = function ( $ts ) use ( $tz ) {
		$d = array( 'dateTime' => wp_date( 'c', (int) $ts ) );
		if ( preg_match( '~^[A-Za-z]+/~', $tz ) ) {
			$d['timeZone'] = $tz;
		}
		return $d;
	};
	$body = array(
		'summary'            => ( $wait ? '? ' : '' ) . bkb_gcal_title( $b ),
		'description'        => ( $wait ? __( 'Waiting for an answer.', 'shootbook' ) . "\n\n" : '' ) . bkb_gcal_text( $b ),
		'location'           => (string) ( $loc['address'] ?? '' ),
		'start'              => $when( $b['start'] ),
		'end'                => $when( $b['end'] ),
		'status'             => $wait ? 'tentative' : 'confirmed',
		'transparency'       => 'opaque',
		'extendedProperties' => array(
			'private' => array(
				'shootbook' => (string) $id,
				'site'      => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			),
		),
	);
	return apply_filters( 'bkb_gcal_event', $body, $b );
}

/**
 * Put one booking into Google as it is now: add, change or remove its event.
 *
 * @return true|WP_Error
 */
function bkb_gcal_push( $id ) {
	$eid = (string) get_post_meta( $id, '_bkb_gcal_id', true );
	$cal = (string) get_post_meta( $id, '_bkb_gcal_cal', true );
	if ( ! bkb_gcal_connected() ) {
		return true;
	}
	$cur  = bkb_gcal_get( 'calendar' );
	$want = bkb_gcal_wants( $id );
	$r    = true;
	// An event in another calendar (the calendar was changed) is removed first.
	if ( '' !== $eid && ( ! $want || $cal !== $cur ) ) {
		$r = bkb_gcal_call( 'DELETE', bkb_gcal_cal_path( '' !== $cal ? $cal : $cur ) . '/' . rawurlencode( $eid ) );
		if ( is_wp_error( $r ) && ! in_array( $r->get_error_code(), array( '404', '410' ), true ) ) {
			return bkb_gcal_failed( $id, $r );
		}
		delete_post_meta( $id, '_bkb_gcal_id' );
		delete_post_meta( $id, '_bkb_gcal_cal' );
		delete_post_meta( $id, '_bkb_gcal_uid' );
		$eid = '';
		$r   = true;
	}
	if ( $want ) {
		$body = bkb_gcal_event_body( $id );
		if ( '' !== $eid ) {
			$r = bkb_gcal_call( 'PATCH', bkb_gcal_cal_path() . '/' . rawurlencode( $eid ), array(), $body );
			if ( is_wp_error( $r ) && in_array( $r->get_error_code(), array( '404', '410' ), true ) ) {
				$eid = ''; // Deleted in Google for good: make it again.
			}
		}
		if ( '' === $eid ) {
			$r = bkb_gcal_call( 'POST', bkb_gcal_cal_path(), array(), $body );
		}
		if ( is_wp_error( $r ) ) {
			return bkb_gcal_failed( $id, $r );
		}
		update_post_meta( $id, '_bkb_gcal_id', (string) $r['id'] );
		update_post_meta( $id, '_bkb_gcal_cal', $cur );
		update_post_meta( $id, '_bkb_gcal_uid', (string) ( $r['iCalUID'] ?? '' ) );
	}
	delete_post_meta( $id, '_bkb_gcal_dirty' );
	delete_post_meta( $id, '_bkb_gcal_error' );
	bkb_gcal_set( array( 'last_push' => time() ) );
	bkb_gcal_busy_flush();
	return true;
}

function bkb_gcal_failed( $id, WP_Error $e ) {
	update_post_meta( $id, '_bkb_gcal_dirty', time() );
	update_post_meta( $id, '_bkb_gcal_error', $e->get_error_message() );
	bkb_gcal_set( array( 'last_error' => $e->get_error_message() ) );
	return $e;
}

/** Mark every booking that should be in Google, or still has an event there, to be sent again. */
function bkb_gcal_mark_all() {
	$ids = get_posts(
		array(
			'post_type'      => 'bk_booking',
			'post_status'    => 'any',
			'posts_per_page' => 1000,
			'fields'         => 'ids',
			'lang'           => '',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array( 'key' => '_bkb_gcal_id', 'compare' => 'EXISTS' ),
				array(
					array( 'key' => '_bkb_status', 'value' => array( 'pending', 'confirmed' ), 'compare' => 'IN' ),
					array( 'key' => '_bkb_end', 'value' => time(), 'compare' => '>', 'type' => 'NUMERIC' ),
				),
			),
		)
	);
	foreach ( $ids as $id ) {
		update_post_meta( $id, '_bkb_gcal_dirty', time() );
	}
	return count( $ids );
}

/** Bookings waiting to be sent: IDs. */
function bkb_gcal_dirty_ids( $limit = 50 ) {
	return get_posts(
		array(
			'post_type'      => 'bk_booking',
			'post_status'    => 'any',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'lang'           => '',
			'meta_key'       => '_bkb_gcal_dirty', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
		)
	);
}

/** Send what is waiting; stops at the first network error so a broken connection is not hammered. */
function bkb_gcal_push_dirty( $limit = 50 ) {
	if ( ! bkb_gcal_connected() ) {
		return 0;
	}
	$n = 0;
	foreach ( bkb_gcal_dirty_ids( $limit ) as $id ) {
		$r = bkb_gcal_push( $id );
		if ( is_wp_error( $r ) ) {
			if ( ! is_numeric( $r->get_error_code() ) ) {
				break;
			}
			continue;
		}
		++$n;
	}
	return $n;
}

/*
 * Every change that matters — a status, a time, a location — is collected during the page
 * load and sent once at the end, whichever screen or add-on made it.
 */
function bkb_gcal_queue( $id ) {
	if ( ! bkb_gcal_connected() || 'bk_booking' !== get_post_type( $id ) ) {
		return;
	}
	$GLOBALS['bkb_gcal_queue'][ (int) $id ] = true;
	if ( ! has_action( 'shutdown', 'bkb_gcal_flush_queue' ) ) {
		add_action( 'shutdown', 'bkb_gcal_flush_queue', 5 );
	}
}

function bkb_gcal_flush_queue() {
	$ids                       = array_keys( (array) ( $GLOBALS['bkb_gcal_queue'] ?? array() ) );
	$GLOBALS['bkb_gcal_queue'] = array();
	foreach ( $ids as $id ) {
		bkb_gcal_push( $id );
	}
}

function bkb_gcal_meta_changed( $mid, $id, $key ) {
	if ( in_array( $key, array( '_bkb_status', '_bkb_start', '_bkb_end', '_bkb_location', '_bkb_service', '_bkb_name' ), true ) ) {
		bkb_gcal_queue( $id );
	}
}
add_action( 'added_post_meta', 'bkb_gcal_meta_changed', 10, 3 );
add_action( 'updated_post_meta', 'bkb_gcal_meta_changed', 10, 3 );
add_action( 'trashed_post', 'bkb_gcal_queue' );
add_action( 'untrashed_post', 'bkb_gcal_queue' );

/* A request deleted for good: its event goes right away, while the request is still there to read. */
add_action(
	'before_delete_post',
	function ( $id ) {
		if ( 'bk_booking' !== get_post_type( $id ) || '' === (string) get_post_meta( $id, '_bkb_gcal_id', true ) || ! bkb_gcal_connected() ) {
			return;
		}
		$cal = (string) get_post_meta( $id, '_bkb_gcal_cal', true );
		bkb_gcal_call( 'DELETE', bkb_gcal_cal_path( '' !== $cal ? $cal : null ) . '/' . rawurlencode( (string) get_post_meta( $id, '_bkb_gcal_id', true ) ) );
		unset( $GLOBALS['bkb_gcal_queue'][ (int) $id ] );
	}
);

add_action( 'bkb_gcal_hourly', fn() => bkb_gcal_push_dirty( 50 ) );

add_action(
	'init',
	function () {
		$on   = bkb_gcal_connected();
		$next = wp_next_scheduled( 'bkb_gcal_hourly' );
		if ( $on && ! $next ) {
			wp_schedule_event( time() + 600, 'hourly', 'bkb_gcal_hourly' );
		} elseif ( ! $on && $next ) {
			wp_clear_scheduled_hook( 'bkb_gcal_hourly' );
		}
	}
);

/* ---------- Google → busy time ---------- */

function bkb_gcal_busy_flush() {
	update_option( 'bkb_gcal_busy_ver', (int) get_option( 'bkb_gcal_busy_ver', 0 ) + 1, false );
}

/**
 * Busy intervals from the chosen calendar between two moments, kept for 2 minutes.
 * When Google cannot be reached, the last answer for the same window is used.
 */
function bkb_gcal_busy( $from, $to ) {
	$key    = 'bkb_gcal_busy_' . md5( bkb_gcal_get( 'calendar' ) . '|' . $from . '|' . $to . '|' . get_option( 'bkb_gcal_busy_ver', 0 ) );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$win   = md5( bkb_gcal_get( 'calendar' ) . '|' . $from . '|' . $to );
	$stale = (array) get_option( 'bkb_gcal_stale', array() );
	// After a failed call the form does not wait for Google again for 5 minutes.
	if ( get_transient( 'bkb_gcal_down' ) ) {
		return (array) ( $stale[ $win ] ?? array() );
	}
	$items = array();
	$page      = '';
	do {
		$q = array(
			'timeMin'      => gmdate( 'Y-m-d\TH:i:s\Z', (int) $from ),
			'timeMax'      => gmdate( 'Y-m-d\TH:i:s\Z', (int) $to ),
			'singleEvents' => 'true',
			'maxResults'   => 2500,
			'fields'       => 'items(id,iCalUID,status,transparency,start,end,extendedProperties),nextPageToken',
		);
		if ( '' !== $page ) {
			$q['pageToken'] = $page;
		}
		$r = bkb_gcal_call( 'GET', bkb_gcal_cal_path(), $q );
		if ( is_wp_error( $r ) ) {
			bkb_gcal_set( array( 'last_error' => $r->get_error_message() ) );
			set_transient( 'bkb_gcal_down', 1, 5 * MINUTE_IN_SECONDS );
			return (array) ( $stale[ $win ] ?? array() );
		}
		$items = array_merge( $items, (array) ( $r['items'] ?? array() ) );
		$page  = (string) ( $r['nextPageToken'] ?? '' );
	} while ( '' !== $page && count( $items ) < 10000 );

	$tz   = wp_timezone();
	$busy = array();
	foreach ( $items as $e ) {
		if ( 'cancelled' === ( $e['status'] ?? '' ) || ! empty( $e['extendedProperties']['private']['shootbook'] ) ) {
			continue;
		}
		$allday = isset( $e['start']['date'] );
		// Timed events always block, like in calendar feeds; an all-day event blocks only when marked "busy".
		if ( $allday && 'transparent' === ( $e['transparency'] ?? '' ) ) {
			continue;
		}
		try {
			if ( $allday ) {
				$s = ( new DateTime( $e['start']['date'], $tz ) )->getTimestamp();
				$f = ( new DateTime( $e['end']['date'] ?? $e['start']['date'], $tz ) )->getTimestamp();
			} else {
				$s = ( new DateTime( (string) $e['start']['dateTime'] ) )->getTimestamp();
				$f = ( new DateTime( (string) ( $e['end']['dateTime'] ?? $e['start']['dateTime'] ) ) )->getTimestamp();
			}
		} catch ( Exception $ex ) {
			continue;
		}
		if ( $f > $s ) {
			$busy[] = array( $s, $f, 'uid' => (string) ( $e['iCalUID'] ?? '' ) );
		}
	}
	set_transient( $key, $busy, 2 * MINUTE_IN_SECONDS );
	unset( $stale[ $win ] );
	$stale[ $win ] = $busy;
	update_option( 'bkb_gcal_stale', array_slice( $stale, -24, null, true ), false );
	return $busy;
}

add_filter(
	'bkb_busy',
	function ( $busy, $from, $to ) {
		if ( bkb_gcal_connected() && bkb_gcal_get( 'busy' ) ) {
			$busy = array_merge( $busy, bkb_gcal_busy( $from, $to ) );
		}
		return $busy;
	},
	5,
	3
);

/* Events this site made in Google, when they come back through the calendar's iCal address. */
add_filter(
	'bkb_is_own_event',
	function ( $own, $uid ) {
		global $wpdb;
		if ( $own || ! preg_match( '/@google\.com$/', $uid ) ) {
			return $own;
		}
		static $uids = null;
		if ( null === $uids ) {
			$uids = array_flip( $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_bkb_gcal_uid'" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return isset( $uids[ $uid ] );
	},
	10,
	2
);
