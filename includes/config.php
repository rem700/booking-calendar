<?php
/**
 * Settings: one option `bkb_config` holds everything the site owner can change.
 * Labels that differ by language are stored as array( 'en' => '…', 'ru' => '…' ).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bkb_default_config() {
	return array(
		'site_name' => '',
		'notify'    => '',
		'from'      => '',
		'ics'       => '',
		'contact'   => '',
		'signature' => '',
		'privacy'   => array(),
		'languages' => array( 'en' ),
		'schedule'  => array(
			'days'             => array(
				'1' => array( 10, 18 ),
				'2' => array( 10, 18 ),
				'3' => array( 10, 18 ),
				'4' => array( 10, 18 ),
				'5' => array( 10, 18 ),
				'6' => null,
				'7' => null,
			),
			'step'             => 60,
			'lead_days'        => 1,
			'horizon_days'     => 90,
			'prep'             => 0,
			'durations'        => array( 1 ),
			'default_duration' => 1,
			'parallel'         => false,
		),
		'services'  => array(),
		'price'     => array(
			'currency' => '',
			'before'   => false,
		),
		'locations' => array(),
		'options'   => array(),
		'fields'    => array(
			'phone'   => array( 'on' => true ),
			'details' => array( 'on' => false, 'required' => false, 'label' => array(), 'placeholder' => array() ),
			'message' => array( 'on' => true ),
			'slang'   => array( 'on' => false, 'choices' => array() ),
		),
		'texts'     => array(),
		'style'     => array(
			'paper'        => '#F4F1EA',
			'ink'          => '#151517',
			'accent'       => '#C9A24B',
			'heading_font' => '',
			'body_font'    => '',
		),
		'retention_days' => 365,
		'reminder'       => true,
		'admin_lang'     => '',
		'replies'        => null,
		'review_url'     => '',
		'book_url'       => '',
		'crm'            => array(
			'source'      => true,
			'digest'      => false,
			'digest_hour' => 8,
			'sources'     => '',
		),
	);
}

/** The saved settings merged over the defaults. */
function bkb_cfg( $key = null, $reset = false ) {
	static $cfg = null;
	if ( null === $cfg || $reset ) {
		$saved = get_option( 'bkb_config', array() );
		$cfg   = bkb_merge( bkb_default_config(), is_array( $saved ) ? $saved : array() );
	}
	return null === $key ? $cfg : ( $cfg[ $key ] ?? null );
}

/** Read the settings again after they were changed in this request. */
function bkb_cfg_reset() {
	bkb_cfg( null, true );
}

function bkb_merge( $def, $val ) {
	foreach ( $val as $k => $v ) {
		if ( is_array( $v ) && isset( $def[ $k ] ) && is_array( $def[ $k ] ) && ! wp_is_numeric_array( $def[ $k ] ) && ! wp_is_numeric_array( $v ) && ! in_array( $k, array( 'days', 'texts', 'privacy', 'label', 'placeholder' ), true ) ) {
			$def[ $k ] = bkb_merge( $def[ $k ], $v );
		} else {
			$def[ $k ] = $v;
		}
	}
	return $def;
}

function bkb_site_name() {
	$n = (string) bkb_cfg( 'site_name' );
	return '' !== $n ? $n : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
}

/** All addresses that get new requests (the setting may hold several, separated by commas). */
function bkb_notify_emails() {
	$list = array_values( array_filter( array_map( 'trim', preg_split( '/[\s,;]+/', (string) bkb_cfg( 'notify' ) ) ), 'is_email' ) );
	return $list ? $list : array( get_option( 'admin_email' ) );
}

/** The main address: the first one. */
function bkb_notify_email() {
	return bkb_notify_emails()[0];
}

/** Where clients' answers go: the sender address if set, otherwise the first notification address. */
function bkb_reply_to() {
	$f = (string) bkb_cfg( 'from' );
	return is_email( $f ) ? $f : bkb_notify_email();
}

/** Languages the form speaks on this site. */
function bkb_langs() {
	$l = array_values( array_intersect( (array) bkb_cfg( 'languages' ), bkb_known_langs() ) );
	return $l ? $l : array( 'en' );
}

/** Text in the given language from array( lang => text ), falling back to English, then to anything. */
function bkb_l( $labels, $lang, $fallback = '' ) {
	if ( ! is_array( $labels ) ) {
		return '' !== (string) $labels ? (string) $labels : $fallback;
	}
	foreach ( array( $lang, 'en' ) as $l ) {
		if ( isset( $labels[ $l ] ) && '' !== trim( (string) $labels[ $l ] ) ) {
			return (string) $labels[ $l ];
		}
	}
	foreach ( $labels as $v ) {
		if ( '' !== trim( (string) $v ) ) {
			return (string) $v;
		}
	}
	return $fallback;
}

/**
 * Language for the team: the admin's own language in WP admin, otherwise the site language, if the form
 * knows it; English otherwise. Request details for the team (screens, e-mails, calendar links) use it.
 */
function bkb_admin_lang() {
	$l = (string) bkb_cfg( 'admin_lang' );
	if ( '' !== $l ) {
		return (string) apply_filters( 'bkb_admin_lang', $l );
	}
	$l = strtolower( substr( is_admin() ? get_user_locale() : get_locale(), 0, 2 ) );
	$l = 'ua' === $l ? 'uk' : $l;
	return (string) apply_filters( 'bkb_admin_lang', in_array( $l, bkb_known_langs(), true ) ? $l : 'en' );
}

/** Language of the current page: shortcode attribute, Polylang, WPML or the site locale. */
function bkb_page_lang( $forced = '' ) {
	$cands = array();
	if ( $forced ) {
		$cands[] = $forced;
	}
	if ( function_exists( 'pll_current_language' ) ) {
		$cands[] = substr( (string) pll_current_language( 'locale' ), 0, 2 );
		$cands[] = (string) pll_current_language( 'slug' );
	}
	if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
		$cands[] = ICL_LANGUAGE_CODE;
	}
	$cands[] = substr( determine_locale(), 0, 2 );
	foreach ( $cands as $c ) {
		$c = strtolower( $c );
		if ( 'ua' === $c ) {
			$c = 'uk';
		}
		if ( in_array( $c, bkb_langs(), true ) ) {
			return $c;
		}
	}
	return bkb_langs()[0];
}

/* ---------- lookups ---------- */

function bkb_find( $list, $id ) {
	foreach ( (array) $list as $item ) {
		if ( isset( $item['id'] ) && (string) $item['id'] === (string) $id ) {
			return $item;
		}
	}
	return null;
}

function bkb_service( $id ) {
	return bkb_find( bkb_cfg( 'services' ), $id );
}

function bkb_location( $id ) {
	return bkb_find( bkb_cfg( 'locations' ), $id );
}

function bkb_service_label( $id, $lang ) {
	$s = bkb_service( $id );
	return $s ? bkb_l( $s['label'] ?? array(), $lang, (string) $s['id'] ) : (string) $id;
}

function bkb_location_label( $id, $lang ) {
	$s = bkb_location( $id );
	return $s ? bkb_l( $s['label'] ?? array(), $lang, (string) $s['id'] ) : (string) $id;
}

/** Durations offered, in hours. */
function bkb_durations() {
	$d = array_values( array_filter( array_map( 'floatval', (array) bkb_cfg( 'schedule' )['durations'] ), fn( $x ) => $x > 0 && $x <= 24 ) );
	return $d ? $d : array( 1 );
}

function bkb_valid_duration( $h ) {
	$h = (float) $h;
	foreach ( bkb_durations() as $d ) {
		if ( abs( $d - $h ) < 0.01 ) {
			return $d;
		}
	}
	return null;
}

/** Total blocked time in seconds: chosen duration plus preparation. */
function bkb_block_seconds( $h ) {
	return (int) round( (float) $h * 3600 ) + 60 * max( 0, (int) bkb_cfg( 'schedule' )['prep'] );
}

/** Working hours for an ISO weekday (1 = Mon … 7 = Sun) and a location: array( open, close ) in hours, or null when closed. */
function bkb_hours( $wd, $loc = '' ) {
	$days = (array) bkb_cfg( 'schedule' )['days'];
	$h    = $days[ (string) $wd ] ?? null;
	$l    = $loc ? bkb_location( $loc ) : null;
	if ( $l && ! empty( $l['days'] ) && array_key_exists( (string) $wd, (array) $l['days'] ) ) {
		$h = $l['days'][ (string) $wd ];
	}
	if ( ! is_array( $h ) || count( $h ) < 2 || (float) $h[1] <= (float) $h[0] ) {
		return null;
	}
	return array( (float) $h[0], (float) $h[1] );
}

/** Options that apply at a location. */
function bkb_options_for( $loc ) {
	$out = array();
	foreach ( (array) bkb_cfg( 'options' ) as $o ) {
		$only = array_filter( (array) ( $o['locations'] ?? array() ) );
		if ( ! $only || in_array( $loc, $only, true ) ) {
			$out[] = $o;
		}
	}
	return $out;
}

/** "1 h", "30 min", "1 h 30 min" in the page language. */
function bkb_fmt_minutes( $m, $t ) {
	$m = (int) $m;
	$h = intdiv( $m, 60 );
	$r = $m % 60;
	$s = array();
	if ( $h ) {
		$s[] = $h . ' ' . $t['h_unit'];
	}
	if ( $r ) {
		$s[] = $r . ' ' . $t['min_unit'];
	}
	return implode( ' ', $s );
}

function bkb_fmt_hours( $h, $t ) {
	return bkb_fmt_minutes( (int) round( (float) $h * 60 ), $t );
}

/* ---------- import: keep only what the plugin understands ---------- */

function bkb_clean_labels( $v ) {
	$out = array();
	foreach ( (array) $v as $k => $s ) {
		if ( in_array( $k, bkb_known_langs(), true ) ) {
			$out[ $k ] = sanitize_textarea_field( (string) $s );
		}
	}
	return $out;
}

function bkb_clean_id( $v ) {
	return mb_substr( trim( preg_replace( '/[^\p{L}\p{N} _.\-]/u', '', (string) $v ) ), 0, 60 );
}

function bkb_clean_days( $v ) {
	$out = array();
	foreach ( (array) $v as $d => $h ) {
		$d = (string) (int) $d;
		if ( (int) $d < 1 || (int) $d > 7 ) {
			continue;
		}
		$out[ $d ] = ( is_array( $h ) && count( $h ) >= 2 ) ? array( max( 0, min( 24, (float) $h[0] ) ), max( 0, min( 24, (float) $h[1] ) ) ) : null;
	}
	return $out;
}

/** A price from the settings: a number from 0 up, with at most two decimals. */
function bkb_clean_amount( $v ) {
	$v = is_string( $v ) ? str_replace( ',', '.', $v ) : $v;
	return is_numeric( $v ) ? round( max( 0, min( 1000000, (float) $v ) ), 2 ) : 0;
}

function bkb_sanitize_config( $c ) {
	$d   = bkb_default_config();
	$c   = is_array( $c ) ? $c : array();
	$out = array(
		'site_name' => sanitize_text_field( (string) ( $c['site_name'] ?? '' ) ),
		'notify'    => implode( ', ', array_values( array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', (string) ( $c['notify'] ?? '' ) ) ) ) ) ),
		'from'      => sanitize_email( (string) ( $c['from'] ?? '' ) ),
		'ics'       => implode( "\n", array_filter( array_map( 'esc_url_raw', array_map( 'trim', explode( "\n", (string) ( $c['ics'] ?? '' ) ) ) ) ) ),
		'contact'   => sanitize_text_field( (string) ( $c['contact'] ?? '' ) ),
		'signature' => sanitize_textarea_field( (string) ( $c['signature'] ?? '' ) ),
		'privacy'   => array_map( 'esc_url_raw', array_intersect_key( (array) ( $c['privacy'] ?? array() ), array_flip( bkb_known_langs() ) ) ),
		'languages' => array_values( array_intersect( (array) ( $c['languages'] ?? array( 'en' ) ), bkb_known_langs() ) ),
	);
	if ( ! $out['languages'] ) {
		$out['languages'] = array( 'en' );
	}
	$s               = (array) ( $c['schedule'] ?? array() );
	$durs            = array_values( array_filter( array_map( 'floatval', (array) ( $s['durations'] ?? array( 1 ) ) ), fn( $x ) => $x > 0 && $x <= 24 ) );
	$out['schedule'] = array(
		'days'             => bkb_clean_days( $s['days'] ?? $d['schedule']['days'] ),
		'step'             => in_array( (int) ( $s['step'] ?? 60 ), array( 15, 20, 30, 45, 60, 90, 120 ), true ) ? (int) $s['step'] : 60,
		'lead_days'        => max( 0, min( 60, (int) ( $s['lead_days'] ?? 1 ) ) ),
		'horizon_days'     => max( 7, min( 730, (int) ( $s['horizon_days'] ?? 90 ) ) ),
		'prep'             => max( 0, min( 480, (int) ( $s['prep'] ?? 0 ) ) ),
		'durations'        => $durs ? $durs : array( 1 ),
		'default_duration' => (float) ( $s['default_duration'] ?? 1 ),
		'parallel'         => ! empty( $s['parallel'] ),
	);
	$out['services'] = array();
	foreach ( (array) ( $c['services'] ?? array() ) as $x ) {
		$id = bkb_clean_id( $x['id'] ?? '' );
		if ( '' !== $id ) {
			$out['services'][] = array(
				'id'    => $id,
				'label' => bkb_clean_labels( $x['label'] ?? array() ),
				'price' => bkb_clean_amount( $x['price'] ?? 0 ),
			);
		}
	}
	$pr           = (array) ( $c['price'] ?? array() );
	$out['price'] = array(
		'currency' => mb_substr( sanitize_text_field( (string) ( $pr['currency'] ?? '' ) ), 0, 10 ),
		'before'   => ! empty( $pr['before'] ),
	);
	$out['locations'] = array();
	foreach ( (array) ( $c['locations'] ?? array() ) as $x ) {
		$id = bkb_clean_id( $x['id'] ?? '' );
		if ( '' !== $id ) {
			$out['locations'][] = array(
				'id'       => $id,
				'label'    => bkb_clean_labels( $x['label'] ?? array() ),
				'address'  => sanitize_text_field( (string) ( $x['address'] ?? '' ) ),
				'days'     => bkb_clean_days( $x['days'] ?? array() ),
				'capacity' => max( 1, min( 50, (int) ( $x['capacity'] ?? 1 ) ) ),
			);
		}
	}
	$out['options'] = array();
	foreach ( (array) ( $c['options'] ?? array() ) as $x ) {
		$id = bkb_clean_id( $x['id'] ?? '' );
		if ( '' === $id ) {
			continue;
		}
		$o = array(
			'id'        => $id,
			'type'      => 'choice' === ( $x['type'] ?? '' ) ? 'choice' : 'yesno',
			'label'     => bkb_clean_labels( $x['label'] ?? array() ),
			'note'      => bkb_clean_labels( $x['note'] ?? array() ),
			'default'   => bkb_clean_id( $x['default'] ?? '' ),
			'locations' => array_values( array_filter( array_map( 'bkb_clean_id', (array) ( $x['locations'] ?? array() ) ) ) ),
			'price'     => bkb_clean_amount( $x['price'] ?? 0 ),
			'per_hour'  => ! empty( $x['per_hour'] ),
			'choices'   => array(),
		);
		foreach ( (array) ( $x['choices'] ?? array() ) as $ch ) {
			$cid = bkb_clean_id( $ch['id'] ?? '' );
			if ( '' !== $cid ) {
				$o['choices'][] = array(
					'id'    => $cid,
					'label' => bkb_clean_labels( $ch['label'] ?? array() ),
					'price' => bkb_clean_amount( $ch['price'] ?? 0 ),
				);
			}
		}
		$out['options'][] = $o;
	}
	$f             = (array) ( $c['fields'] ?? array() );
	$out['fields'] = array(
		'phone'   => array( 'on' => ! empty( $f['phone']['on'] ) ),
		'details' => array(
			'on'          => ! empty( $f['details']['on'] ),
			'required'    => ! empty( $f['details']['required'] ),
			'label'       => bkb_clean_labels( $f['details']['label'] ?? array() ),
			'placeholder' => bkb_clean_labels( $f['details']['placeholder'] ?? array() ),
		),
		'message' => array( 'on' => ! empty( $f['message']['on'] ) ),
		'slang'   => array( 'on' => ! empty( $f['slang']['on'] ), 'choices' => array() ),
	);
	foreach ( (array) ( $f['slang']['choices'] ?? array() ) as $ch ) {
		$cid = bkb_clean_id( $ch['id'] ?? '' );
		if ( '' !== $cid ) {
			$out['fields']['slang']['choices'][] = array(
				'id'    => $cid,
				'label' => bkb_clean_labels( $ch['label'] ?? array() ),
				'hide'  => array_values( array_intersect( (array) ( $ch['hide'] ?? array() ), bkb_known_langs() ) ),
			);
		}
	}
	$out['texts'] = array();
	$keys         = array_keys( bkb_default_texts( 'en' ) );
	foreach ( (array) ( $c['texts'] ?? array() ) as $lang => $tx ) {
		if ( ! in_array( $lang, bkb_known_langs(), true ) ) {
			continue;
		}
		foreach ( (array) $tx as $k => $v ) {
			if ( in_array( $k, $keys, true ) && ! is_array( $v ) && '' !== trim( (string) $v ) ) {
				$out['texts'][ $lang ][ $k ] = wp_kses( (string) $v, array( 'a' => array( 'href' => true, 'target' => true ) ) );
			}
		}
	}
	$st           = (array) ( $c['style'] ?? array() );
	$color        = fn( $v, $def ) => preg_match( '/^#[0-9a-f]{3,8}$/i', (string) $v ) ? (string) $v : $def;
	$font         = fn( $v ) => preg_replace( '/[^\w\s,\'"\-]/u', '', (string) $v );
	$out['style'] = array(
		'paper'        => $color( $st['paper'] ?? '', $d['style']['paper'] ),
		'ink'          => $color( $st['ink'] ?? '', $d['style']['ink'] ),
		'accent'       => $color( $st['accent'] ?? '', $d['style']['accent'] ),
		'heading_font' => $font( $st['heading_font'] ?? '' ),
		'body_font'    => $font( $st['body_font'] ?? '' ),
	);
	$out['retention_days'] = max( 30, min( 3650, (int) ( $c['retention_days'] ?? 365 ) ) );
	$out['reminder']       = ! empty( $c['reminder'] );
	$out['admin_lang']     = in_array( $c['admin_lang'] ?? '', array( 'en', 'ru' ), true ) ? $c['admin_lang'] : '';
	$out['review_url']     = esc_url_raw( (string) ( $c['review_url'] ?? '' ) );
	$out['book_url']       = esc_url_raw( (string) ( $c['book_url'] ?? '' ) );
	$out['replies']        = null;
	if ( isset( $c['replies'] ) && is_array( $c['replies'] ) ) {
		$out['replies'] = array();
		foreach ( $c['replies'] as $r ) {
			$title = sanitize_text_field( (string) ( $r['title'] ?? '' ) );
			$text  = array();
			foreach ( (array) ( $r['text'] ?? array() ) as $l => $v ) {
				if ( in_array( $l, bkb_known_langs(), true ) && '' !== trim( (string) $v ) ) {
					$text[ $l ] = mb_substr( sanitize_textarea_field( (string) $v ), 0, 3000 );
				}
			}
			if ( '' !== $title || $text ) {
				$out['replies'][] = array(
					'id'    => bkb_clean_id( $r['id'] ?? '' ),
					'title' => '' !== $title ? $title : __( 'Reply', 'shootbook' ),
					'text'  => $text,
				);
			}
		}
	}
	$crm                   = (array) ( $c['crm'] ?? array() );
	$out['crm']            = array(
		'source'      => ! empty( $crm['source'] ),
		'digest'      => ! empty( $crm['digest'] ),
		'digest_hour' => max( 0, min( 23, (int) ( $crm['digest_hour'] ?? 8 ) ) ),
		'sources'     => sanitize_textarea_field( (string) ( $crm['sources'] ?? '' ) ),
	);
	return $out;
}
