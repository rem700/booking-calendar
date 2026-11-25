<?php
/**
 * Instagram Direct → clients.
 *
 * Instagram sends each new message to the site (webhook). Every hour the site also
 * reads the recent conversations, so a message is not lost if the site was down.
 * A new person becomes a client with the source Instagram, and every message goes
 * to the client's history. You can answer from the card while Instagram allows it:
 * within 24 hours after the client's last message.
 *
 * Uses the Instagram API with Instagram Login. The token and the app secret are
 * kept in their own option, never in the Shootbook settings export.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BKB_IG_DB', '1' );

/** Graph API version. */
function bkb_ig_api() {
	return 'https://graph.instagram.com/' . apply_filters( 'bkb_ig_api_version', 'v24.0' ) . '/';
}

/* ---------- settings ---------- */

function bkb_ig_get( $key = null ) {
	$o = wp_parse_args(
		(array) get_option( 'bkb_instagram', array() ),
		array(
			'secret'     => '',
			'token'      => '',
			'expires'    => 0,
			'ig_id'      => '',
			'username'   => '',
			'verify'     => '',
			'last_sync'  => 0,
			'last_hook'  => 0,
			'last_error' => '',
		)
	);
	if ( '' === $o['verify'] ) {
		$o['verify'] = wp_generate_password( 32, false );
		update_option( 'bkb_instagram', $o, false );
	}
	return null === $key ? $o : $o[ $key ];
}

function bkb_ig_set( $data ) {
	update_option( 'bkb_instagram', array_merge( bkb_ig_get(), $data ), false );
}

function bkb_ig_connected() {
	$o = bkb_ig_get();
	return '' !== $o['token'] && '' !== $o['ig_id'];
}

function bkb_ig_webhook_url() {
	return rest_url( 'bk-booking/v1/instagram' );
}

/* ---------- tables: who is who, and which messages are already saved ---------- */

function bkb_ig_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'bkb_ig_' . $name;
}

function bkb_ig_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$cs = $wpdb->get_charset_collate();
	dbDelta(
		'CREATE TABLE ' . bkb_ig_table( 'people' ) . " (
			igsid varchar(40) NOT NULL,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			username varchar(100) NOT NULL DEFAULT '',
			last_in datetime NULL DEFAULT NULL,
			PRIMARY KEY  (igsid),
			KEY client_id (client_id)
		) $cs;"
	);
	dbDelta(
		'CREATE TABLE ' . bkb_ig_table( 'messages' ) . " (
			mid varchar(190) NOT NULL,
			igsid varchar(40) NOT NULL DEFAULT '',
			event_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NOT NULL,
			PRIMARY KEY  (mid)
		) $cs;"
	);
	update_option( 'bkb_ig_db', BKB_IG_DB, false );
}

add_action(
	'init',
	function () {
		if ( get_option( 'bkb_ig_db' ) !== BKB_IG_DB ) {
			bkb_ig_install();
		}
		// The checks run only while an account is connected.
		$on = bkb_ig_connected();
		foreach ( array( 'bkb_ig_hourly' => 'hourly', 'bkb_ig_daily' => 'daily' ) as $hook => $every ) {
			$next = wp_next_scheduled( $hook );
			if ( $on && ! $next ) {
				wp_schedule_event( time() + 300, $every, $hook );
			} elseif ( ! $on && $next ) {
				wp_clear_scheduled_hook( $hook );
			}
		}
	}
);

function bkb_ig_person( $igsid ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . bkb_ig_table( 'people' ) . ' WHERE igsid = %s', $igsid ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

function bkb_ig_person_by_client( $client_id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . bkb_ig_table( 'people' ) . ' WHERE client_id = %d ORDER BY last_in DESC LIMIT 1', $client_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

/* ---------- talking to Instagram ---------- */

/**
 * A call to the Graph API. Returns the decoded answer, or WP_Error with Instagram's message.
 *
 * @param string $method GET or POST.
 * @param string $path   Path after the version, or a full URL.
 * @param array  $args   Query arguments (GET) or JSON body (POST).
 */
function bkb_ig_call( $method, $path, $args = array() ) {
	$token = bkb_ig_get( 'token' );
	$url   = 0 === strpos( $path, 'https://' ) ? $path : bkb_ig_api() . ltrim( $path, '/' );
	$req   = array(
		'timeout' => 15,
		'headers' => array( 'Authorization' => 'Bearer ' . $token ),
	);
	if ( 'POST' === $method ) {
		$req['headers']['Content-Type'] = 'application/json';
		$req['body']                    = wp_json_encode( $args );
		$res                            = wp_remote_post( $url, $req );
	} else {
		$res = wp_remote_get( add_query_arg( array_map( 'rawurlencode', $args ), $url ), $req );
	}
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $body ) ) {
		return new WP_Error( 'bkb_ig', 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
	}
	if ( isset( $body['error'] ) ) {
		return new WP_Error( 'bkb_ig', (string) ( $body['error']['message'] ?? 'error' ), $body['error'] );
	}
	return $body;
}

/**
 * Check a token and remember whose account it is. Also asks Instagram to send
 * new messages of this account to the site.
 */
function bkb_ig_connect( $token, $secret ) {
	$old = bkb_ig_get();
	bkb_ig_set( array( 'token' => $token ) );
	$me = bkb_ig_call( 'GET', 'me', array( 'fields' => 'user_id,username' ) );
	if ( is_wp_error( $me ) || empty( $me['user_id'] ) ) {
		bkb_ig_set( array( 'token' => $old['token'] ) );
		return is_wp_error( $me ) ? $me : new WP_Error( 'bkb_ig', 'no user_id' );
	}
	bkb_ig_set(
		array(
			'secret'     => $secret,
			'ig_id'      => (string) $me['user_id'],
			'username'   => (string) ( $me['username'] ?? '' ),
			'expires'    => time() + 60 * DAY_IN_SECONDS,
			'last_error' => '',
		)
	);
	$sub = bkb_ig_call( 'POST', 'me/subscribed_apps?subscribed_fields=messages', array() );
	if ( is_wp_error( $sub ) ) {
		bkb_ig_set( array( 'last_error' => $sub->get_error_message() ) );
	}
	return true;
}

/** Tokens live 60 days; a fresh one is asked for when less than 15 days are left. */
function bkb_ig_refresh_token() {
	$o = bkb_ig_get();
	if ( ! bkb_ig_connected() || $o['expires'] - time() > 15 * DAY_IN_SECONDS ) {
		return;
	}
	$r = bkb_ig_call(
		'GET',
		'https://graph.instagram.com/refresh_access_token',
		array(
			'grant_type'   => 'ig_refresh_token',
			'access_token' => $o['token'],
		)
	);
	if ( is_wp_error( $r ) || empty( $r['access_token'] ) ) {
		bkb_ig_set( array( 'last_error' => is_wp_error( $r ) ? $r->get_error_message() : 'refresh failed' ) );
		return;
	}
	bkb_ig_set(
		array(
			'token'   => (string) $r['access_token'],
			'expires' => time() + (int) ( $r['expires_in'] ?? 60 * DAY_IN_SECONDS ),
		)
	);
}
add_action( 'bkb_ig_daily', 'bkb_ig_refresh_token' );

/* ---------- saving a message ---------- */

/** The client for an Instagram user: known already, the same @name in the CRM, or a new client. */
function bkb_ig_client_for( $igsid, &$is_new = false ) {
	global $wpdb;
	$p = bkb_ig_person( $igsid );
	if ( $p && $p['client_id'] && bkb_crm_client( $p['client_id'] ) ) {
		return (int) $p['client_id'];
	}
	$prof     = bkb_ig_call( 'GET', rawurlencode( $igsid ), array( 'fields' => 'name,username' ) );
	$username = is_wp_error( $prof ) ? '' : bkb_crm_ig_handle( $prof['username'] ?? '' );
	$name     = is_wp_error( $prof ) ? '' : sanitize_text_field( (string) ( $prof['name'] ?? '' ) );
	$c        = $username ? bkb_crm_client_by_instagram( $username ) : null;
	if ( $c ) {
		$cid = (int) $c['id'];
	} else {
		$now = bkb_crm_now();
		$wpdb->insert( // phpcs:ignore WordPress.DB
			bkb_crm_table( 'clients' ),
			array(
				'email'        => '',
				'instagram'    => $username,
				'name'         => $name ? $name : ( $username ? '' : 'Instagram ' . substr( $igsid, -6 ) ),
				'phone'        => '',
				'lang'         => '',
				'source'       => 'Instagram',
				'stage'        => 'new',
				'lost_reason'  => '',
				'notes'        => '',
				'created'      => $now,
				'last_contact' => $now,
			)
		);
		$cid    = (int) $wpdb->insert_id;
		$is_new = true;
	}
	$wpdb->replace( // phpcs:ignore WordPress.DB
		bkb_ig_table( 'people' ),
		array(
			'igsid'     => $igsid,
			'client_id' => $cid,
			'username'  => $username,
			'last_in'   => $p['last_in'] ?? null,
		)
	);
	return $cid;
}

/** Text for the history: the message, or what was attached. */
function bkb_ig_text( $msg ) {
	$text = trim( (string) ( $msg['text'] ?? ( $msg['message'] ?? '' ) ) );
	$att  = $msg['attachments']['data'] ?? ( $msg['attachments'] ?? array() );
	foreach ( (array) $att as $a ) {
		$type  = (string) ( $a['type'] ?? ( isset( $a['image_data'] ) ? 'image' : ( isset( $a['video_data'] ) ? 'video' : 'file' ) ) );
		$names = array(
			'image'      => __( 'photo', 'shootbook' ),
			'video'      => __( 'video', 'shootbook' ),
			'audio'      => __( 'voice message', 'shootbook' ),
			'share'      => __( 'shared post', 'shootbook' ),
			'story_mention' => __( 'mention in a story', 'shootbook' ),
			'ig_reel'    => __( 'reel', 'shootbook' ),
		);
		$text .= ( '' === $text ? '' : "\n" ) . '[' . ( $names[ $type ] ?? $type ) . ']';
	}
	if ( ! empty( $msg['reply_to']['story'] ) ) {
		$text = '[' . __( 'reply to a story', 'shootbook' ) . '] ' . $text;
	}
	return '' === $text ? '[' . __( 'message', 'shootbook' ) . ']' : $text;
}

/**
 * Save one message, once. Returns the history entry id, or 0 when it was saved before or is not a message.
 *
 * @param string $mid      Instagram message id.
 * @param string $igsid    The other person (not the studio).
 * @param bool   $incoming From the client (true) or from the studio (false).
 * @param string $text     Text for the history.
 * @param int    $time     Unix time of the message.
 */
function bkb_ig_save( $mid, $igsid, $incoming, $text, $time ) {
	global $wpdb;
	if ( '' === $mid || '' === $igsid || $igsid === bkb_ig_get( 'ig_id' ) ) {
		return 0;
	}
	if ( $wpdb->get_var( $wpdb->prepare( 'SELECT mid FROM ' . bkb_ig_table( 'messages' ) . ' WHERE mid = %s', $mid ) ) ) { // phpcs:ignore WordPress.DB
		return 0;
	}
	// Taken before the work, so the webhook and the hourly check do not save it twice.
	$taken = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . bkb_ig_table( 'messages' ) . ' (mid, igsid, event_id, created) VALUES (%s, %s, 0, %s)', $mid, $igsid, gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB
	if ( ! $taken ) {
		return 0;
	}
	$is_new = false;
	$cid    = bkb_ig_client_for( $igsid, $is_new );
	$when   = gmdate( 'Y-m-d H:i:s', $time ? $time : time() );
	$eid    = bkb_crm_add_event( $cid, $incoming ? 'ig_in' : 'ig_out', $text, array( 'created' => $when ) );
	$wpdb->update( bkb_ig_table( 'messages' ), array( 'event_id' => $eid ), array( 'mid' => $mid ) ); // phpcs:ignore WordPress.DB

	$c = bkb_crm_client( $cid );
	if ( $c && $when > $c['last_contact'] ) {
		bkb_crm_update_client( $cid, array( 'last_contact' => $when ) );
	}
	if ( $incoming ) {
		$p = bkb_ig_person( $igsid );
		if ( $p && ( ! $p['last_in'] || $when > $p['last_in'] ) ) {
			$wpdb->update( bkb_ig_table( 'people' ), array( 'last_in' => $when ), array( 'igsid' => $igsid ) ); // phpcs:ignore WordPress.DB
		}
		if ( $is_new ) {
			bkb_ig_notify( $cid, $text );
		}
	} elseif ( $c && 'new' === $c['stage'] ) {
		bkb_crm_set_stage( $cid, 'replied' );
	}
	do_action( 'bkb_ig_message_saved', $eid, $cid, $incoming );
	return $eid;
}

/** A new person wrote: tell the studio by e-mail, like a new request. */
function bkb_ig_notify( $client_id, $text ) {
	if ( ! apply_filters( 'bkb_ig_notify', true, $client_id ) ) {
		return;
	}
	$c    = bkb_crm_client( $client_id );
	$who  = $c['name'] ? $c['name'] : '@' . $c['instagram'];
	$body = $text . "\n\n" . ( $c['instagram'] ? 'https://instagram.com/' . $c['instagram'] . "\n" : '' ) . bkb_crm_client_url( $client_id );
	/* translators: %s: client's name or @name */
	wp_mail( bkb_notify_emails(), sprintf( __( 'New message on Instagram from %s', 'shootbook' ), $who ), $body );
}

/* ---------- webhook ---------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'bk-booking/v1',
			'/instagram',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => 'bkb_ig_hook_verify',
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => 'bkb_ig_hook',
					'permission_callback' => '__return_true',
				),
			)
		);
	}
);

/** Meta checks the address once: it sends our verify token and expects the challenge back as plain text. */
function bkb_ig_hook_verify( WP_REST_Request $r ) {
	$q = $r->get_query_params();
	// PHP turns "hub.mode" into "hub_mode".
	$mode  = (string) ( $q['hub_mode'] ?? ( $q['hub.mode'] ?? '' ) );
	$token = (string) ( $q['hub_verify_token'] ?? ( $q['hub.verify_token'] ?? '' ) );
	$chal  = (string) ( $q['hub_challenge'] ?? ( $q['hub.challenge'] ?? '' ) );
	if ( 'subscribe' !== $mode || ! hash_equals( bkb_ig_get( 'verify' ), $token ) ) {
		return new WP_REST_Response( 'forbidden', 403 );
	}
	add_filter(
		'rest_pre_serve_request',
		function () use ( $chal ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo preg_replace( '/[^A-Za-z0-9_\-]/', '', $chal ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return true;
		}
	);
	return new WP_REST_Response( null, 200 );
}

function bkb_ig_hook( WP_REST_Request $r ) {
	$secret = bkb_ig_get( 'secret' );
	$raw    = $r->get_body();
	$sig    = (string) $r->get_header( 'x_hub_signature_256' );
	if ( '' === $secret || ! hash_equals( 'sha256=' . hash_hmac( 'sha256', $raw, $secret ), $sig ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 403 );
	}
	bkb_ig_set( array( 'last_hook' => time() ) );
	$j   = json_decode( $raw, true );
	$own = bkb_ig_get( 'ig_id' );
	foreach ( (array) ( $j['entry'] ?? array() ) as $entry ) {
		foreach ( (array) ( $entry['messaging'] ?? array() ) as $m ) {
			$msg = $m['message'] ?? null;
			if ( ! is_array( $msg ) || ! empty( $msg['is_deleted'] ) || empty( $msg['mid'] ) ) {
				continue;
			}
			$from     = (string) ( $m['sender']['id'] ?? '' );
			$to       = (string) ( $m['recipient']['id'] ?? '' );
			$incoming = empty( $msg['is_echo'] ) && $from !== $own;
			bkb_ig_save( (string) $msg['mid'], $incoming ? $from : $to, $incoming, bkb_ig_text( $msg ), (int) floor( ( $m['timestamp'] ?? 0 ) / 1000 ) );
		}
	}
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/* ---------- the hourly check ---------- */

/** Read conversations changed since the last check and save the messages that are missing. */
function bkb_ig_sync() {
	if ( ! bkb_ig_connected() ) {
		return 0;
	}
	$o     = bkb_ig_get();
	$since = $o['last_sync'] ? $o['last_sync'] - HOUR_IN_SECONDS : time() - 3 * DAY_IN_SECONDS;
	$start = time();
	$saved = 0;
	$r     = bkb_ig_call(
		'GET',
		'me/conversations',
		array(
			'platform' => 'instagram',
			'fields'   => 'id,updated_time,messages.limit(25){id,created_time,from,to,message,attachments}',
			'limit'    => '25',
		)
	);
	if ( is_wp_error( $r ) ) {
		bkb_ig_set( array( 'last_error' => $r->get_error_message() ) );
		return 0;
	}
	foreach ( (array) ( $r['data'] ?? array() ) as $conv ) {
		if ( strtotime( (string) ( $conv['updated_time'] ?? '' ) ) < $since ) {
			continue;
		}
		$msgs = array_reverse( (array) ( $conv['messages']['data'] ?? array() ) );
		foreach ( $msgs as $m ) {
			$t = strtotime( (string) ( $m['created_time'] ?? '' ) );
			if ( $t < $since ) {
				continue;
			}
			$from     = (string) ( $m['from']['id'] ?? '' );
			$incoming = $from !== $o['ig_id'];
			$other    = $incoming ? $from : (string) ( $m['to']['data'][0]['id'] ?? '' );
			$saved   += bkb_ig_save( (string) ( $m['id'] ?? '' ), $other, $incoming, bkb_ig_text( $m ), $t ) ? 1 : 0;
		}
	}
	bkb_ig_set(
		array(
			'last_sync'  => $start,
			'last_error' => '',
		)
	);
	return $saved;
}
add_action( 'bkb_ig_hourly', 'bkb_ig_sync' );

/* ---------- answering from the card ---------- */

/** Until when the studio can write to this client (unix time), or 0 if it cannot now. */
function bkb_ig_window( $person ) {
	if ( ! $person || ! $person['last_in'] ) {
		return 0;
	}
	$until = strtotime( $person['last_in'] . ' UTC' ) + DAY_IN_SECONDS;
	return $until > time() ? $until : 0;
}

/** Send a text to the client in Direct and save it in the history. */
function bkb_ig_send( $client_id, $text ) {
	$p = bkb_ig_person_by_client( $client_id );
	if ( ! $p ) {
		return new WP_Error( 'bkb_ig', __( 'This client has not written on Instagram yet.', 'shootbook' ) );
	}
	if ( ! bkb_ig_window( $p ) ) {
		return new WP_Error( 'bkb_ig', __( 'More than 24 hours have passed since the client’s last message. Instagram lets you write only after the client writes again.', 'shootbook' ) );
	}
	$r = bkb_ig_call(
		'POST',
		rawurlencode( bkb_ig_get( 'ig_id' ) ) . '/messages',
		array(
			'recipient' => array( 'id' => $p['igsid'] ),
			'message'   => array( 'text' => $text ),
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$mid = (string) ( $r['message_id'] ?? '' );
	if ( '' === $mid ) {
		return new WP_Error( 'bkb_ig', 'no message_id' );
	}
	bkb_ig_save( $mid, $p['igsid'], false, $text, time() );
	return true;
}
