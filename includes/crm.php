<?php
/**
 * Clients: a small CRM on top of the booking requests.
 *
 * Two tables: clients (one row per e-mail) and events (requests, status changes,
 * notes, tasks). Events keep a text copy of each request, so the history stays
 * after old requests are removed by the retention setting.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BKB_CRM_DB', '1' );

function bkb_crm_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'bkb_' . $name;
}

/** Pipeline stages, in order. */
function bkb_crm_stages() {
	return array(
		'new'       => 'Новая заявка',
		'replied'   => 'Ответили',
		'confirmed' => 'Подтверждена',
		'prepaid'   => 'Предоплата',
		'done'      => 'Проведена',
		'photos'    => 'Фото отданы',
		'review'    => 'Попросили отзыв',
		'lost'      => 'Потеряна',
	);
}

function bkb_crm_stage_label( $s ) {
	$st = bkb_crm_stages();
	return $st[ $s ] ?? $s;
}

/* ---------- tables ---------- */

function bkb_crm_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$cs = $wpdb->get_charset_collate();
	dbDelta(
		'CREATE TABLE ' . bkb_crm_table( 'clients' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			phone varchar(100) NOT NULL DEFAULT '',
			lang varchar(10) NOT NULL DEFAULT '',
			source varchar(190) NOT NULL DEFAULT '',
			stage varchar(20) NOT NULL DEFAULT 'new',
			lost_reason varchar(190) NOT NULL DEFAULT '',
			notes longtext NOT NULL,
			created datetime NOT NULL,
			last_contact datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY stage (stage),
			KEY last_contact (last_contact)
		) $cs;"
	);
	dbDelta(
		'CREATE TABLE ' . bkb_crm_table( 'events' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(20) NOT NULL DEFAULT 'note',
			body text NOT NULL,
			source varchar(190) NOT NULL DEFAULT '',
			due datetime NULL DEFAULT NULL,
			done tinyint(1) NOT NULL DEFAULT 0,
			created datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY booking_id (booking_id),
			KEY type_created (type,created),
			KEY tasks (type,done,due)
		) $cs;"
	);
	update_option( 'bkb_crm_db', BKB_CRM_DB, false );
}

add_action(
	'plugins_loaded',
	function () {
		if ( get_option( 'bkb_crm_db' ) !== BKB_CRM_DB ) {
			bkb_crm_install();
		}
	}
);

/* Clients for the requests that came before the CRM: once, in the admin. */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'bkb_crm_backfilled' ) ) {
			return;
		}
		update_option( 'bkb_crm_backfilled', time(), false );
		$ids = get_posts(
			array(
				'post_type'      => 'bk_booking',
				'post_status'    => 'any',
				'posts_per_page' => 1000,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'lang'           => '',
			)
		);
		foreach ( $ids as $id ) {
			if ( ! get_post_meta( $id, '_bkb_client', true ) ) {
				bkb_crm_from_booking( $id, true );
			}
		}
	}
);

/* ---------- reading and writing ---------- */

function bkb_crm_now() {
	return current_time( 'mysql', true );
}

/** A UTC datetime from the database, shown in the site time zone. */
function bkb_crm_time( $utc, $fmt = 'j.m.Y H:i' ) {
	return $utc ? wp_date( $fmt, strtotime( $utc . ' UTC' ), wp_timezone() ) : '';
}

function bkb_crm_client( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . bkb_crm_table( 'clients' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

function bkb_crm_client_by_email( $email ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . bkb_crm_table( 'clients' ) . ' WHERE email = %s ORDER BY id LIMIT 1', strtolower( trim( $email ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

function bkb_crm_update_client( $id, $data ) {
	global $wpdb;
	$wpdb->update( bkb_crm_table( 'clients' ), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
}

function bkb_crm_add_event( $client_id, $type, $body, $extra = array() ) {
	global $wpdb;
	$row = array_merge(
		array(
			'client_id'  => (int) $client_id,
			'booking_id' => 0,
			'type'       => $type,
			'body'       => (string) $body,
			'source'     => '',
			'created'    => bkb_crm_now(),
		),
		$extra
	);
	$wpdb->insert( bkb_crm_table( 'events' ), $row ); // phpcs:ignore WordPress.DB
	$eid = (int) $wpdb->insert_id;
	do_action( 'bkb_crm_event_added', $eid, $row );
	return $eid;
}

function bkb_crm_events( $client_id ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . bkb_crm_table( 'events' ) . ' WHERE client_id = %d ORDER BY created DESC, id DESC', $client_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

/** Change the stage and note it in the history. */
function bkb_crm_set_stage( $client_id, $stage, $reason = '', $booking_id = 0 ) {
	$c = bkb_crm_client( $client_id );
	if ( ! $c || ! isset( bkb_crm_stages()[ $stage ] ) ) {
		return;
	}
	$data = array( 'stage' => $stage );
	if ( 'lost' === $stage ) {
		$data['lost_reason'] = $reason;
	}
	bkb_crm_update_client( $client_id, $data );
	if ( $c['stage'] !== $stage ) {
		bkb_crm_add_event( $client_id, 'stage', bkb_crm_stage_label( $stage ) . ( $reason ? ': ' . $reason : '' ), array( 'booking_id' => (int) $booking_id ) );
	}
}

/* ---------- where the visitor came from ---------- */

/** The first-visit record saved by the browser, cleaned. */
function bkb_crm_src_clean( $raw ) {
	$j = json_decode( (string) $raw, true );
	if ( ! is_array( $j ) ) {
		return array();
	}
	$out = array();
	foreach ( array( 's', 'm', 'c', 'r', 'l', 'g', 'f' ) as $k ) {
		if ( isset( $j[ $k ] ) && '' !== (string) $j[ $k ] ) {
			$out[ $k ] = mb_substr( sanitize_text_field( (string) $j[ $k ] ), 0, 150 );
		}
	}
	return $out;
}

/** A short name for the source: "Instagram", "Google", "newsletter / email"… */
function bkb_crm_src_label( $src ) {
	if ( ! empty( $src['s'] ) ) {
		return $src['s'] . ( ! empty( $src['m'] ) ? ' / ' . $src['m'] : '' );
	}
	if ( ! empty( $src['g'] ) ) {
		return 'Google Ads';
	}
	$host = strtolower( preg_replace( '/^(www|m|l|lm)\./', '', (string) ( $src['r'] ?? '' ) ) );
	$map  = array(
		'instagram'  => 'Instagram',
		'facebook'   => 'Facebook',
		'fb.'        => 'Facebook',
		'google.'    => 'Google',
		'bing.'      => 'Bing',
		'yandex.'    => 'Yandex',
		'duckduckgo' => 'DuckDuckGo',
		'tiktok'     => 'TikTok',
		'youtube'    => 'YouTube',
		't.me'       => 'Telegram',
		'telegram'   => 'Telegram',
		'chatgpt'    => 'ChatGPT',
		'openai'     => 'ChatGPT',
		'perplexity' => 'Perplexity',
		'claude.ai'  => 'Claude',
		'tripadvisor' => 'Tripadvisor',
	);
	foreach ( $map as $k => $v ) {
		if ( '' !== $host && false !== strpos( $host, $k ) ) {
			return $v;
		}
	}
	if ( '' !== $host ) {
		return $host;
	}
	if ( ! empty( $src['f'] ) ) {
		return 'Facebook / Instagram';
	}
	return 'Прямой заход';
}

/* Save the first visit with the request. */
add_filter(
	'bkb_request_data',
	function ( $d, $p ) {
		$d['src'] = wp_json_encode( bkb_crm_src_clean( $p['src'] ?? '' ) );
		return $d;
	},
	5,
	2
);

/* A tiny script on public pages: remembers the first visit that came from somewhere. */
add_action(
	'wp_footer',
	function () {
		if ( is_admin() || empty( bkb_cfg( 'crm' )['source'] ) ) {
			return;
		}
		echo "<script>(function(){try{var k='bkb_src',o=JSON.parse(localStorage.getItem(k)||'null');if(o&&Date.now()-o.t<7776e6)return;var q=new URLSearchParams(location.search),r='';try{r=document.referrer?new URL(document.referrer).hostname:''}catch(e){}if(r===location.hostname)r='';var v={s:q.get('utm_source')||'',m:q.get('utm_medium')||'',c:q.get('utm_campaign')||'',r:r,g:q.get('gclid')?1:'',f:q.get('fbclid')?1:'',l:location.pathname,t:Date.now()};if(v.s||v.r||v.g||v.f)localStorage.setItem(k,JSON.stringify(v))}catch(e){}})();</script>\n";
	},
	5
);

/* ---------- requests become clients ---------- */

/** Find or create the client of a request and add the request to the history. */
function bkb_crm_from_booking( $id, $backfill = false ) {
	global $wpdb;
	$b     = bkb_get( $id );
	$email = strtolower( trim( (string) $b['email'] ) );
	if ( ! is_email( $email ) ) {
		return 0;
	}
	$post    = get_post( $id );
	$when    = $backfill && $post ? $post->post_date_gmt : bkb_crm_now();
	$src     = bkb_crm_src_clean( get_post_meta( $id, '_bkb_src', true ) );
	$label   = $src ? bkb_crm_src_label( $src ) : ( ! $backfill && ! empty( bkb_cfg( 'crm' )['source'] ) ? 'Прямой заход' : '' );
	$c       = bkb_crm_client_by_email( $email );
	$lang    = $b['slang'] ? $b['slang'] : $b['ui'];
	$stage   = 'new';
	if ( $backfill ) {
		$stage = array(
			'confirmed' => ( (int) $b['end'] < time() ? 'done' : 'confirmed' ),
			'declined'  => 'lost',
		)[ $b['status'] ] ?? 'new';
	}
	if ( ! $c ) {
		$wpdb->insert( // phpcs:ignore WordPress.DB
			bkb_crm_table( 'clients' ),
			array(
				'email'        => $email,
				'name'         => (string) $b['name'],
				'phone'        => (string) $b['phone'],
				'lang'         => (string) $lang,
				'source'       => $label,
				'stage'        => $stage,
				'lost_reason'  => 'lost' === $stage ? 'Отклонена' : '',
				'notes'        => '',
				'created'      => $when,
				'last_contact' => $when,
			)
		);
		$cid = (int) $wpdb->insert_id;
	} else {
		$cid  = (int) $c['id'];
		$data = array(
			'name'         => (string) $b['name'],
			'last_contact' => $when,
			'stage'        => $stage,
			'lost_reason'  => 'lost' === $stage ? 'Отклонена' : '',
		);
		if ( $b['phone'] ) {
			$data['phone'] = (string) $b['phone'];
		}
		if ( $lang ) {
			$data['lang'] = (string) $lang;
		}
		if ( '' === $c['source'] && '' !== $label ) {
			$data['source'] = $label;
		}
		bkb_crm_update_client( $cid, $data );
	}
	if ( ! $cid ) {
		return 0;
	}
	update_post_meta( $id, '_bkb_client', $cid );
	$body = bkb_summary( $b, 'ru', true ) . ( $b['msg'] ? "\n\nСообщение:\n" . $b['msg'] : '' );
	bkb_crm_add_event(
		$cid,
		'request',
		$body,
		array(
			'booking_id' => (int) $id,
			'source'     => $label,
			'created'    => $when,
		)
	);
	return $cid;
}

add_action(
	'bkb_request_saved',
	function ( $id ) {
		bkb_crm_from_booking( $id );
	}
);

add_action(
	'bkb_status_changed',
	function ( $id, $s ) {
		$cid = (int) get_post_meta( $id, '_bkb_client', true );
		if ( ! $cid ) {
			$cid = bkb_crm_from_booking( $id );
		}
		if ( ! $cid ) {
			return;
		}
		bkb_crm_add_event( $cid, 'status', 'confirmed' === $s ? 'Заявка подтверждена, клиенту ушло письмо' : 'Заявка отклонена, клиенту ушло письмо', array( 'booking_id' => (int) $id ) );
		bkb_crm_set_stage( $cid, 'confirmed' === $s ? 'confirmed' : 'lost', 'confirmed' === $s ? '' : 'Отклонена', $id );
		bkb_crm_update_client( $cid, array( 'last_contact' => bkb_crm_now() ) );
	},
	10,
	2
);

/* ---------- morning summary ---------- */

/** What needs attention: used by the e-mail and by the REST endpoint. */
function bkb_crm_digest_data() {
	global $wpdb;
	$tz    = wp_timezone();
	$now   = time();
	$today = new DateTime( 'today', $tz );
	$tom   = ( clone $today )->modify( '+2 days' );
	$sum   = function ( $id ) {
		$b   = bkb_get( $id );
		$cid = (int) get_post_meta( $id, '_bkb_client', true );
		return array(
			'id'      => (int) $id,
			'client'  => $cid,
			'name'    => $b['name'],
			'service' => $b['service'] ? bkb_service_label( $b['service'], 'ru' ) : '',
			'when'    => wp_date( 'j.m H:i', (int) $b['start'], wp_timezone() ),
			'url'     => admin_url( 'post.php?post=' . $id . '&action=edit' ),
		);
	};
	$q = function ( $args ) {
		return get_posts(
			array_merge(
				array(
					'post_type'      => 'bk_booking',
					'post_status'    => 'any',
					'posts_per_page' => 50,
					'fields'         => 'ids',
					'lang'           => '',
				),
				$args
			)
		);
	};
	$new  = $q( array( 'date_query' => array( array( 'after' => gmdate( 'Y-m-d H:i:s', $now - DAY_IN_SECONDS ), 'column' => 'post_date_gmt' ) ) ) );
	$wait = $q(
		array(
			'date_query' => array( array( 'before' => gmdate( 'Y-m-d H:i:s', $now - DAY_IN_SECONDS ), 'column' => 'post_date_gmt' ) ),
			'meta_query' => array( array( 'key' => '_bkb_status', 'value' => 'pending' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);
	$soon = $q(
		array(
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => '_bkb_status', 'value' => 'confirmed' ),
				array( 'key' => '_bkb_start', 'value' => array( $today->getTimestamp(), $tom->getTimestamp() ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ),
			),
			'meta_key'   => '_bkb_start', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'    => 'meta_value_num',
			'order'      => 'ASC',
		)
	);
	$end_today = gmdate( 'Y-m-d H:i:s', ( clone $today )->modify( '+1 day' )->getTimestamp() );
	$tasks     = $wpdb->get_results( $wpdb->prepare( 'SELECT e.*, c.name FROM ' . bkb_crm_table( 'events' ) . ' e LEFT JOIN ' . bkb_crm_table( 'clients' ) . " c ON c.id = e.client_id WHERE e.type = 'task' AND e.done = 0 AND e.due IS NOT NULL AND e.due < %s ORDER BY e.due LIMIT 50", $end_today ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$quiet     = $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, stage, last_contact FROM ' . bkb_crm_table( 'clients' ) . " WHERE stage IN ('new','replied') AND last_contact < %s ORDER BY last_contact LIMIT 30", gmdate( 'Y-m-d H:i:s', $now - 3 * DAY_IN_SECONDS ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
	return array(
		'new'   => array_map( $sum, $new ),
		'wait'  => array_map( $sum, $wait ),
		'soon'  => array_map( $sum, $soon ),
		'tasks' => array_map(
			fn( $t ) => array(
				'client' => (int) $t['client_id'],
				'name'   => (string) $t['name'],
				'task'   => $t['body'],
				'due'    => bkb_crm_time( $t['due'], 'j.m' ),
				'late'   => strtotime( $t['due'] . ' UTC' ) < $today->getTimestamp(),
			),
			$tasks
		),
		'quiet' => array_map(
			fn( $c ) => array(
				'client' => (int) $c['id'],
				'name'   => $c['name'],
				'stage'  => bkb_crm_stage_label( $c['stage'] ),
				'since'  => bkb_crm_time( $c['last_contact'], 'j.m' ),
			),
			$quiet
		),
	);
}

function bkb_crm_client_url( $id ) {
	return admin_url( 'edit.php?post_type=bk_booking&page=bkb-clients&client=' . (int) $id );
}

function bkb_crm_digest_text( $d ) {
	$out  = array();
	$line = fn( $x ) => '• ' . $x['name'] . ( $x['service'] ? ', ' . $x['service'] : '' ) . ' — ' . $x['when'] . "\n  " . $x['url'];
	if ( $d['new'] ) {
		$out[] = "Новые заявки за сутки:\n" . implode( "\n", array_map( $line, $d['new'] ) );
	}
	if ( $d['wait'] ) {
		$out[] = "Ждут ответа больше суток:\n" . implode( "\n", array_map( $line, $d['wait'] ) );
	}
	if ( $d['soon'] ) {
		$out[] = "Записи сегодня и завтра:\n" . implode( "\n", array_map( $line, $d['soon'] ) );
	}
	if ( $d['tasks'] ) {
		$out[] = "Задачи:\n" . implode(
			"\n",
			array_map( fn( $t ) => '• ' . ( $t['late'] ? 'просрочено, ' : '' ) . $t['due'] . ': ' . $t['task'] . ( $t['name'] ? ' (' . $t['name'] . ')' : '' ) . "\n  " . bkb_crm_client_url( $t['client'] ), $d['tasks'] )
		);
	}
	if ( $d['quiet'] ) {
		$out[] = "Давно без контакта, можно напомнить о себе:\n" . implode(
			"\n",
			array_map( fn( $c ) => '• ' . $c['name'] . ' — ' . $c['stage'] . ', последний контакт ' . $c['since'] . "\n  " . bkb_crm_client_url( $c['client'] ), $d['quiet'] )
		);
	}
	return implode( "\n\n", $out );
}

add_action(
	'bkb_crm_tick',
	function () {
		$cfg = (array) bkb_cfg( 'crm' );
		if ( empty( $cfg['digest'] ) ) {
			return;
		}
		$tz    = wp_timezone();
		$today = wp_date( 'Y-m-d', null, $tz );
		if ( get_option( 'bkb_digest_last' ) === $today || (int) wp_date( 'G', null, $tz ) < (int) $cfg['digest_hour'] ) {
			return;
		}
		update_option( 'bkb_digest_last', $today, false );
		$text = bkb_crm_digest_text( bkb_crm_digest_data() );
		if ( '' === $text ) {
			return; // Nothing to report: no e-mail.
		}
		wp_mail( bkb_notify_emails(), 'Сводка ' . wp_date( 'j.m', null, $tz ) . ': ' . bkb_site_name(), $text . "\n\nВсе клиенты: " . admin_url( 'edit.php?post_type=bk_booking&page=bkb-clients' ) . "\n" );
	}
);

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'bkb_crm_tick' ) ) {
			wp_schedule_event( time() + 120, 'hourly', 'bkb_crm_tick' );
		}
	}
);

/* The same summary as JSON, for scripts and assistants (needs an admin login or an application password). */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'bk-booking/v1',
			'/crm/digest',
			array(
				'methods'             => 'GET',
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
				'callback'            => fn() => rest_ensure_response( bkb_crm_digest_data() ),
			)
		);
	}
);
