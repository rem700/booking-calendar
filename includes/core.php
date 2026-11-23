<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- requests storage (private post type) ---------- */

add_action(
	'init',
	function () {
		register_post_type(
			'bk_booking',
			array(
				'labels'          => array(
					'name'          => 'Заявки',
					'singular_name' => 'Заявка',
					'menu_name'     => 'Заявки',
					'all_items'     => 'Все заявки',
					'edit_item'     => 'Заявка',
					'search_items'  => 'Найти заявку',
					'not_found'     => 'Заявок пока нет',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_position'   => 3,
				'menu_icon'       => 'dashicons-calendar-alt',
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
				'show_in_rest'    => false,
			)
		);
	}
);

function bkb_requests_in( $from, $to ) {
	$q   = get_posts(
		array(
			'post_type'      => 'bk_booking',
			'post_status'    => 'any',
			'posts_per_page' => 500,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => '_bkb_status', 'value' => array( 'pending', 'confirmed' ), 'compare' => 'IN' ),
				array( 'key' => '_bkb_start', 'value' => array( $from - 86400, $to ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ),
			),
			'fields'         => 'ids',
			'lang'           => '',
		)
	);
	$out = array();
	foreach ( $q as $id ) {
		$out[] = array( (int) get_post_meta( $id, '_bkb_start', true ), (int) get_post_meta( $id, '_bkb_end', true ) );
	}
	return $out;
}

/* ---------- availability ---------- */

function bkb_ics_urls() {
	return array_filter( array_map( 'trim', explode( "\n", (string) bkb_cfg( 'ics' ) ) ) );
}

function bkb_busy( $from, $to ) {
	$busy = array();
	foreach ( bkb_ics_urls() as $url ) {
		$busy = array_merge( $busy, bkb_ics_busy( bkb_ics_fetch( $url ), $from, $to ) );
	}
	return apply_filters( 'bkb_busy', array_merge( $busy, bkb_requests_in( $from, $to ) ), $from, $to );
}

function bkb_is_free( $s, $e, $busy ) {
	foreach ( $busy as $b ) {
		if ( $s < $b[1] && $e > $b[0] ) {
			return false;
		}
	}
	return true;
}

function bkb_at( DateTime $d, $hours ) {
	$m = (int) round( (float) $hours * 60 );
	return ( clone $d )->setTime( intdiv( $m, 60 ), $m % 60 )->getTimestamp();
}

/** Free start times for a month: array( 'Y-m-d' => array( 'H:i', … ) ). */
function bkb_month_slots( $ym, $hours, $loc = '' ) {
	$tz    = wp_timezone();
	$s     = (array) bkb_cfg( 'schedule' );
	$first = DateTime::createFromFormat( '!Y-m-d', $ym . '-01', $tz );
	if ( ! $first ) {
		return array();
	}
	$last = ( clone $first )->modify( 'last day of this month' )->setTime( 23, 59, 59 );
	$min  = ( new DateTime( 'today', $tz ) )->modify( '+' . (int) $s['lead_days'] . ' days' );
	$max  = ( new DateTime( 'today', $tz ) )->modify( '+' . (int) $s['horizon_days'] . ' days' )->setTime( 23, 59, 59 );
	if ( $last < $min || $first > $max ) {
		return array();
	}
	$dur  = bkb_block_seconds( $hours );
	$step = max( 15, (int) $s['step'] ) * 60;
	$now  = time();
	$busy = bkb_busy( $first->getTimestamp(), $last->getTimestamp() + 1 );
	$out  = array();
	for ( $d = clone $first; $d <= $last; $d->modify( '+1 day' ) ) {
		if ( $d < $min || $d > $max ) {
			continue;
		}
		$h = bkb_hours( (int) $d->format( 'N' ), $loc );
		if ( ! $h ) {
			continue;
		}
		$open  = bkb_at( $d, $h[0] );
		$close = bkb_at( $d, $h[1] );
		$times = array();
		for ( $t = $open; $t + $dur <= $close; $t += $step ) {
			if ( $t > $now && bkb_is_free( $t, $t + $dur, $busy ) ) {
				$times[] = wp_date( 'H:i', $t, $tz );
			}
		}
		if ( $times ) {
			$out[ $d->format( 'Y-m-d' ) ] = $times;
		}
	}
	return $out;
}

/* ---------- REST ---------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'bk-booking/v1',
			'/slots',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $r ) {
					$ym = (string) $r->get_param( 'month' );
					if ( ! preg_match( '/^\d{4}-\d{2}$/', $ym ) ) {
						return new WP_Error( 'bad', 'month', array( 'status' => 400 ) );
					}
					$h   = bkb_valid_duration( $r->get_param( 'h' ) );
					$h   = null === $h ? bkb_durations()[0] : $h;
					$loc = (string) $r->get_param( 'loc' );
					$loc = bkb_location( $loc ) ? $loc : '';
					$res = rest_ensure_response( array( 'slots' => (object) bkb_month_slots( $ym, $h, $loc ) ) );
					$res->header( 'Cache-Control', 'no-store' );
					return $res;
				},
			)
		);
		register_rest_route(
			'bk-booking/v1',
			'/request',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => 'bkb_handle_request',
			)
		);
	}
);

function bkb_clean( $v, $max = 200 ) {
	return mb_substr( trim( sanitize_text_field( (string) $v ) ), 0, $max );
}

function bkb_handle_request( WP_REST_Request $r ) {
	$p = $r->get_json_params();
	if ( ! is_array( $p ) ) {
		return new WP_Error( 'bad', 'bad', array( 'status' => 400 ) );
	}
	// Spam traps: the hidden field must stay empty; the form must be open at least 4 seconds.
	if ( ! empty( $p['website'] ) || (int) ( $p['el'] ?? 0 ) < 4000 ) {
		return new WP_Error( 'spam', 'spam', array( 'status' => 400 ) );
	}
	$lang   = in_array( $p['ui'] ?? '', bkb_langs(), true ) ? $p['ui'] : bkb_langs()[0];
	$fields = (array) bkb_cfg( 'fields' );
	$svc    = (string) ( $p['service'] ?? '' );
	$loc    = (string) ( $p['location'] ?? '' );
	$hours  = bkb_valid_duration( $p['hours'] ?? 0 );
	$err    = false;
	if ( bkb_cfg( 'services' ) && ! bkb_service( $svc ) ) {
		$err = true;
	}
	if ( bkb_cfg( 'locations' ) && ! bkb_location( $loc ) ) {
		$err = true;
	}
	if ( ! bkb_cfg( 'services' ) ) {
		$svc = '';
	}
	if ( ! bkb_cfg( 'locations' ) ) {
		$loc = '';
	}
	$opts = array();
	foreach ( bkb_options_for( $loc ) as $o ) {
		$v = (string) ( $p['opts'][ $o['id'] ] ?? '' );
		if ( 'choice' === $o['type'] ) {
			$ids = array_column( (array) $o['choices'], 'id' );
			$v   = in_array( $v, $ids, true ) ? $v : ( in_array( $o['default'], $ids, true ) ? $o['default'] : ( $ids[0] ?? '' ) );
		} else {
			$v = '1' === $v ? '1' : '0';
		}
		$opts[ $o['id'] ] = $v;
	}
	$slangs = array_column( (array) ( $fields['slang']['choices'] ?? array() ), 'id' );
	$d      = array(
		'date'     => (string) ( $p['date'] ?? '' ),
		'time'     => (string) ( $p['time'] ?? '' ),
		'service'  => $svc,
		'location' => $loc,
		'hours'    => $hours,
		'opts'     => $opts,
		'price'    => bkb_clean( $p['price'] ?? '', 60 ),
		'name'     => bkb_clean( $p['name'] ?? '', 100 ),
		'email'    => sanitize_email( (string) ( $p['email'] ?? '' ) ),
		'phone'    => ! empty( $fields['phone']['on'] ) ? bkb_clean( $p['phone'] ?? '', 100 ) : '',
		'details'  => ! empty( $fields['details']['on'] ) ? mb_substr( sanitize_textarea_field( (string) ( $p['details'] ?? '' ) ), 0, 1000 ) : '',
		'slang'    => ( ! empty( $fields['slang']['on'] ) && in_array( $p['slang'] ?? '', $slangs, true ) ) ? $p['slang'] : '',
		'msg'      => ! empty( $fields['message']['on'] ) ? mb_substr( sanitize_textarea_field( (string) ( $p['msg'] ?? '' ) ), 0, 1500 ) : '',
		'ui'       => $lang,
		'page'     => wp_validate_redirect( esc_url_raw( (string) ( $p['page'] ?? '' ) ), home_url( '/' ) ),
		'consent'  => ! empty( $p['consent'] ),
	);
	$need_details = ! empty( $fields['details']['on'] ) && ! empty( $fields['details']['required'] );
	if ( $err || null === $hours || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d['date'] ) || ! preg_match( '/^\d{2}:\d{2}$/', $d['time'] ) || '' === $d['name'] || ! is_email( $d['email'] ) || ( $need_details && '' === trim( $d['details'] ) ) || ! $d['consent'] ) {
		return new WP_Error( 'fields', 'fields', array( 'status' => 422 ) );
	}
	$tz    = wp_timezone();
	$start = DateTime::createFromFormat( 'Y-m-d H:i', $d['date'] . ' ' . $d['time'], $tz );
	if ( ! $start ) {
		return new WP_Error( 'fields', 'fields', array( 'status' => 422 ) );
	}
	$s = $start->getTimestamp();
	// The chosen time must still be offered.
	$month = bkb_month_slots( $start->format( 'Y-m' ), $hours, $loc );
	if ( empty( $month[ $d['date'] ] ) || ! in_array( $d['time'], $month[ $d['date'] ], true ) ) {
		return new WP_Error( 'taken', 'taken', array( 'status' => 409 ) );
	}
	$d = apply_filters( 'bkb_request_data', $d, $p );

	$id = wp_insert_post(
		array(
			'post_type'   => 'bk_booking',
			'post_status' => 'publish',
			'post_title'  => trim( sprintf( '%s %s — %s — %s', $d['date'], $d['time'], $svc ? bkb_service_label( $svc, 'en' ) : bkb_site_name(), $d['name'] ) ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'save', 'save', array( 'status' => 500 ) );
	}
	foreach ( $d as $k => $v ) {
		update_post_meta( $id, '_bkb_' . $k, $v );
	}
	update_post_meta( $id, '_bkb_start', $s );
	update_post_meta( $id, '_bkb_end', $s + bkb_block_seconds( $hours ) );
	update_post_meta( $id, '_bkb_status', 'pending' );

	bkb_mail_team( $id );
	bkb_mail_client( $id, 'ack' );
	do_action( 'bkb_request_saved', $id, bkb_get( $id ) );

	return rest_ensure_response( array( 'ok' => true ) );
}

/* ---------- a request as an array (also reads requests saved by version 1) ---------- */

function bkb_get( $id ) {
	$keys = array( 'date', 'time', 'service', 'location', 'hours', 'opts', 'price', 'name', 'email', 'phone', 'details', 'slang', 'msg', 'ui', 'page', 'start', 'end', 'status', 'pkg', 'loc', 'h', 'ph', 'smk', 'vid', 'guests' );
	$o    = array( 'id' => $id );
	foreach ( $keys as $k ) {
		$o[ $k ] = get_post_meta( $id, '_bkb_' . $k, true );
	}
	if ( '' === $o['service'] && '' !== $o['pkg'] ) {
		$o['service']  = $o['pkg'];
		$o['location'] = $o['loc'];
		$o['hours']    = $o['h'];
		$o['details']  = $o['guests'];
		$o['opts']     = array_filter(
			array(
				'ph'  => $o['ph'],
				'smk' => $o['smk'] ? '1' : '0',
				'vid' => $o['vid'] ? '1' : '0',
			),
			fn( $v ) => '' !== $v
		);
	}
	$o['opts'] = is_array( $o['opts'] ) ? $o['opts'] : array();
	return $o;
}

/** Lines describing the booking, in a language: array( array( label, value ), … ). */
function bkb_summary_lines( $b, $lang, $with_contact = false ) {
	$t     = bkb_t( $lang );
	$tz    = wp_timezone();
	$prep  = (int) bkb_cfg( 'schedule' )['prep'];
	$lines = array();
	$lines[] = array( $t['f_date'], wp_date( 'd.m.Y', (int) $b['start'], $tz ) . ', ' . wp_date( 'H:i', (int) $b['start'], $tz ) . '–' . wp_date( 'H:i', (int) $b['end'], $tz ) );
	if ( '' !== (string) $b['service'] ) {
		$lines[] = array( $t['service'], bkb_service_label( $b['service'], $lang ) );
	}
	if ( '' !== (string) $b['location'] ) {
		$lines[] = array( $t['location'], bkb_location_label( $b['location'], $lang ) );
	}
	$dur = bkb_fmt_hours( $b['hours'], $t );
	if ( $prep ) {
		$dur .= ' (' . bkb_fill( $t['prep_short'], array( 'prep' => bkb_fmt_minutes( $prep, $t ) ) ) . ')';
	}
	$lines[] = array( $t['duration'], $dur );
	$yes     = array();
	foreach ( (array) bkb_cfg( 'options' ) as $o ) {
		if ( ! isset( $b['opts'][ $o['id'] ] ) ) {
			continue;
		}
		$v = $b['opts'][ $o['id'] ];
		if ( 'choice' === $o['type'] ) {
			$ch = bkb_find( $o['choices'], $v );
			if ( $ch ) {
				$lines[] = array( bkb_l( $o['label'], $lang, $o['id'] ), bkb_l( $ch['label'], $lang, $ch['id'] ) );
			}
		} elseif ( '1' === (string) $v ) {
			$yes[] = bkb_l( $o['label'], $lang, $o['id'] );
		}
	}
	if ( $yes ) {
		$lines[] = array( $t['options'], implode( ', ', $yes ) );
	}
	if ( '' !== (string) $b['price'] ) {
		$lines[] = array( $t['price'], $b['price'] );
	}
	if ( $with_contact ) {
		$lines[] = array( $t['name'], $b['name'] );
		$lines[] = array( $t['email'], $b['email'] );
		if ( $b['phone'] ) {
			$lines[] = array( $t['phone'], $b['phone'] );
		}
		if ( $b['slang'] ) {
			$lines[] = array( $t['session_lang'], bkb_slang_label( $b['slang'], $lang ) );
		}
		if ( $b['details'] ) {
			$lines[] = array( bkb_l( bkb_cfg( 'fields' )['details']['label'] ?? array(), $lang, $t['details'] ), $b['details'] );
		}
	}
	return apply_filters( 'bkb_summary_lines', $lines, $b, $lang, $with_contact );
}

function bkb_slang_label( $id, $lang ) {
	$c = bkb_find( bkb_cfg( 'fields' )['slang']['choices'] ?? array(), $id );
	return $c ? bkb_l( $c['label'], $lang, $id ) : $id;
}

function bkb_summary( $b, $lang, $with_contact = false ) {
	return implode( "\n", array_map( fn( $l ) => $l[0] . ': ' . $l[1], bkb_summary_lines( $b, $lang, $with_contact ) ) );
}

/* ---------- calendar links ---------- */

function bkb_gcal_url( $title, $b, $details ) {
	$fmt = fn( $ts ) => gmdate( 'Ymd\THis\Z', (int) $ts );
	$loc = bkb_location( $b['location'] );
	return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode( $title ) . '&dates=' . $fmt( $b['start'] ) . '/' . $fmt( $b['end'] ) . '&details=' . rawurlencode( $details ) . '&location=' . rawurlencode( $loc['address'] ?? '' );
}

function bkb_gcal_client( $b, $lang ) {
	$svc = $b['service'] ? ' — ' . bkb_service_label( $b['service'], $lang ) : '';
	$c   = trim( bkb_cfg( 'contact' ) . "\n" . bkb_notify_email() );
	return bkb_gcal_url( bkb_site_name() . $svc, $b, bkb_summary( $b, $lang ) . "\n\n" . $c );
}

function bkb_gcal_link( $b ) {
	$svc = $b['service'] ? bkb_service_label( $b['service'], 'ru' ) . ' — ' : '';
	return bkb_gcal_url( bkb_site_name() . ': ' . $svc . $b['name'], $b, bkb_summary( $b, 'ru', true ) );
}

/* ---------- emails ---------- */

function bkb_mail_team( $id ) {
	$b    = bkb_get( $id );
	$tz   = wp_timezone();
	$body = "Новая заявка\n\n"
		. bkb_summary( $b, 'ru', true ) . "\n\n"
		. ( $b['msg'] ? "Сообщение:\n" . $b['msg'] . "\n\n" : '' )
		. 'Подтвердить или отклонить: ' . admin_url( 'post.php?post=' . $id . '&action=edit' ) . "\n"
		. 'Добавить в Google Календарь: ' . bkb_gcal_link( $b ) . "\n\n"
		. "Время помечено как занятое на сайте, пока заявка не отклонена.\n";
	$svc  = $b['service'] ? bkb_service_label( $b['service'], 'ru' ) . ', ' : '';
	return wp_mail(
		bkb_notify_email(),
		'Новая заявка: ' . $svc . wp_date( 'j.m H:i', (int) $b['start'], $tz ) . ' — ' . $b['name'],
		$body,
		array( 'Reply-To: ' . $b['name'] . ' <' . $b['email'] . '>' )
	);
}

function bkb_mail_client( $id, $type ) {
	$b    = bkb_get( $id );
	$lang = in_array( $b['ui'], bkb_langs(), true ) ? $b['ui'] : bkb_langs()[0];
	$t    = bkb_t( $lang );
	$loc  = bkb_location( $b['location'] );
	$vars = array(
		'name'      => $b['name'],
		'summary'   => bkb_summary( $b, $lang ),
		'where'     => ! empty( $loc['address'] ) ? bkb_fill( $t['m_where'], array( 'address' => $loc['address'] ) ) : '',
		'calendar'  => bkb_fill( $t['m_gcal'], array( 'url' => bkb_gcal_client( $b, $lang ) ) ),
		'book_url'  => $b['page'] ? $b['page'] : home_url( '/' ),
		'site'      => bkb_site_name(),
		'signature' => '' !== trim( (string) bkb_cfg( 'signature' ) ) ? (string) bkb_cfg( 'signature' ) : bkb_site_name(),
	);
	$map  = array(
		'ack'       => 'm_ack',
		'confirmed' => 'm_conf',
		'declined'  => 'm_decl',
		'reminder'  => 'm_rem',
	);
	$k    = $map[ $type ] ?? 'm_ack';
	$subj = bkb_fill( $t[ $k . '_subj' ], $vars );
	$body = bkb_fill( $t[ $k ], $vars );
	return wp_mail( $b['email'], $subj, $body, array( 'Reply-To: ' . bkb_site_name() . ' <' . bkb_notify_email() . '>' ) );
}

add_filter(
	'wp_mail_from_name',
	function ( $name ) {
		return 'WordPress' === $name ? bkb_site_name() : $name;
	}
);

/* ---------- front end ---------- */

function bkb_privacy_url( $lang ) {
	$own = (string) ( bkb_cfg( 'privacy' )[ $lang ] ?? '' );
	if ( '' !== $own ) {
		return $own;
	}
	$pid = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( $pid && function_exists( 'pll_get_post' ) && function_exists( 'pll_current_language' ) ) {
		$tr = pll_get_post( $pid, pll_current_language( 'slug' ) );
		if ( $tr ) {
			return get_permalink( $tr );
		}
	}
	return get_privacy_policy_url();
}

/** Everything the form needs, in the page language. */
function bkb_front_config( $lang ) {
	$t   = bkb_t( $lang );
	$c   = bkb_cfg();
	$lab = fn( $x ) => array(
		'id'     => (string) $x['id'],
		'label'  => bkb_l( $x['label'] ?? array(), $lang, (string) $x['id'] ),
		'labels' => (array) ( $x['label'] ?? array() ),
	);
	$opts = array();
	foreach ( (array) $c['options'] as $o ) {
		$opts[] = array_merge(
			$lab( $o ),
			array(
				'type'      => $o['type'],
				'note'      => bkb_l( $o['note'] ?? array(), $lang ),
				'default'   => (string) $o['default'],
				'locations' => array_values( (array) $o['locations'] ),
				'choices'   => array_map( $lab, (array) $o['choices'] ),
			)
		);
	}
	$f      = (array) $c['fields'];
	$slangs = array();
	foreach ( (array) ( $f['slang']['choices'] ?? array() ) as $ch ) {
		if ( ! in_array( $lang, (array) ( $ch['hide'] ?? array() ), true ) ) {
			$slangs[] = $lab( $ch );
		}
	}
	$ids = array_column( $slangs, 'id' );
	return array(
		'lang'      => $lang,
		'api'       => esc_url_raw( rest_url( 'bk-booking/v1/' ) ),
		't'         => $t,
		'privacy'   => bkb_privacy_url( $lang ),
		'services'  => array_map( $lab, (array) $c['services'] ),
		'locations' => array_map( $lab, (array) $c['locations'] ),
		'options'   => $opts,
		'durations' => bkb_durations(),
		'duration'  => bkb_valid_duration( $c['schedule']['default_duration'] ) ?? bkb_durations()[0],
		'prep'      => (int) $c['schedule']['prep'],
		'fields'    => array(
			'phone'   => ! empty( $f['phone']['on'] ),
			'details' => array(
				'on'          => ! empty( $f['details']['on'] ),
				'required'    => ! empty( $f['details']['required'] ),
				'label'       => bkb_l( $f['details']['label'] ?? array(), $lang, $t['details'] ),
				'placeholder' => bkb_l( $f['details']['placeholder'] ?? array(), $lang ),
			),
			'message' => ! empty( $f['message']['on'] ),
			'slang'   => ! empty( $f['slang']['on'] ) && $slangs ? $slangs : array(),
		),
		'slang'     => in_array( $lang, $ids, true ) ? $lang : ( in_array( 'en', $ids, true ) ? 'en' : ( $ids[0] ?? '' ) ),
	);
}

function bkb_style_attr() {
	$s   = (array) bkb_cfg( 'style' );
	$css = '--ink:' . $s['ink'] . ';--cream:' . $s['paper'] . ';--gold:' . $s['accent'] . ';';
	if ( ! empty( $s['heading_font'] ) ) {
		$css .= '--bkb-head:' . $s['heading_font'] . ';';
	}
	if ( ! empty( $s['body_font'] ) ) {
		$css .= '--bkb-body:' . $s['body_font'] . ';';
	}
	return $css;
}

function bkb_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'lang' => '' ), $atts );
	$lang = bkb_page_lang( (string) $atts['lang'] );
	$t    = bkb_t( $lang );
	wp_enqueue_style( 'bk-booking', BKB_URL . 'assets/booking.css', array(), BKB_VER );
	wp_enqueue_script( 'bk-booking', BKB_URL . 'assets/booking.js', array(), BKB_VER, true );
	wp_add_inline_script( 'bk-booking', 'window.BKB=' . wp_json_encode( apply_filters( 'bkb_front_config', bkb_front_config( $lang ), $lang ) ) . ';', 'before' );
	do_action( 'bkb_enqueue', $lang );
	return '<div class="bkb" id="bkb" data-lang="' . esc_attr( $lang ) . '" style="' . esc_attr( bkb_style_attr() ) . '"><p class="bkb-loading">' . esc_html( $t['loading'] ) . '</p></div>';
}
add_shortcode( 'bk_booking', 'bkb_shortcode' );
add_shortcode( 'booking_calendar', 'bkb_shortcode' );

/* ---------- retention ---------- */

add_action(
	'bkb_cleanup',
	function () {
		$ids = get_posts(
			array(
				'post_type'      => 'bk_booking',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_bkb_end',
						'value'   => time() - DAY_IN_SECONDS * (int) bkb_cfg( 'retention_days' ),
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}
);

/* ---------- reminder the day before a confirmed booking ---------- */

add_action(
	'bkb_remind',
	function () {
		if ( ! bkb_cfg( 'reminder' ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'bk_booking',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array( 'key' => '_bkb_status', 'value' => 'confirmed' ),
					array( 'key' => '_bkb_start', 'value' => array( time() + 12 * HOUR_IN_SECONDS, time() + 30 * HOUR_IN_SECONDS ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ),
					array( 'key' => '_bkb_reminded', 'compare' => 'NOT EXISTS' ),
				),
			)
		);
		foreach ( $ids as $id ) {
			update_post_meta( $id, '_bkb_reminded', time() );
			bkb_mail_client( $id, 'reminder' );
		}
	}
);

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'bkb_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'bkb_cleanup' );
		}
		if ( ! wp_next_scheduled( 'bkb_remind' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'bkb_remind' );
		}
	}
);
