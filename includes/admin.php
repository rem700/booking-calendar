<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- settings page ---------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=bk_booking', 'Настройки записи', 'Настройки', 'manage_options', 'bkb-settings', 'bkb_settings_page' );
	}
);

/* Version 1 kept the calendar link and e-mail in `bkb_settings`: carry them over once. */
add_action(
	'admin_init',
	function () {
		if ( false !== get_option( 'bkb_config', false ) ) {
			return;
		}
		$old = get_option( 'bkb_settings', array() );
		$c   = bkb_default_config();
		if ( is_array( $old ) ) {
			$c['ics']    = (string) ( $old['ics'] ?? '' );
			$c['notify'] = (string) ( $old['notify'] ?? '' );
		}
		add_option( 'bkb_config', bkb_sanitize_config( $c ), '', false );
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'bk_booking_page_bkb-settings' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'bkb-admin', BKB_URL . 'assets/admin.css', array(), BKB_VER );
		wp_enqueue_script( 'bkb-admin', BKB_URL . 'assets/admin.js', array(), BKB_VER, true );
		$texts = array();
		foreach ( bkb_known_langs() as $l ) {
			$texts[ $l ] = bkb_default_texts( $l );
		}
		$status = array();
		foreach ( bkb_ics_urls() as $url ) {
			$ics      = bkb_ics_fetch( $url );
			$status[] = false !== strpos( $ics, 'BEGIN:VCALENDAR' ) ? (int) substr_count( $ics, 'BEGIN:VEVENT' ) : -1;
		}
		wp_add_inline_script(
			'bkb-admin',
			'window.BKB_ADMIN=' . wp_json_encode(
				array(
					'config'    => bkb_cfg(),
					'langs'     => bkb_lang_names(),
					'texts'     => $texts,
					'icsStatus' => $status,
					'exportUrl' => wp_nonce_url( admin_url( 'admin-post.php?action=bkb_export' ), 'bkb_export' ),
				)
			) . ';',
			'before'
		);
	}
);

function bkb_settings_page() {
	$saved = isset( $_GET['bkb_saved'] ) ? sanitize_key( $_GET['bkb_saved'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap bkb-admin">
		<h1>Настройки записи</h1>
		<?php if ( 'ok' === $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p>Сохранено.</p></div>
		<?php elseif ( 'bad' === $saved ) : ?>
			<div class="notice notice-error"><p>Не удалось прочитать настройки. Проверьте JSON.</p></div>
		<?php endif; ?>
		<p class="description">Форму записи на страницу добавляет шорткод <code>[booking_calendar]</code> (или <code>[bk_booking]</code>). Язык формы берётся из языка страницы (Polylang, WPML или язык сайта).</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bkb-settings-form">
			<input type="hidden" name="action" value="bkb_save_config">
			<?php wp_nonce_field( 'bkb_save_config' ); ?>
			<input type="hidden" name="bkb_config_json" id="bkb-config-json" value="">
			<div id="bkb-editor"><p>Загрузка…</p></div>
			<p class="submit"><button type="submit" class="button button-primary button-hero">Сохранить настройки</button></p>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_bkb_save_config',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'bkb_save_config' );
		$raw = wp_unslash( $_POST['bkb_config_json'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$c   = json_decode( (string) $raw, true );
		$ok  = is_array( $c );
		if ( $ok ) {
			update_option( 'bkb_config', bkb_sanitize_config( $c ), false );
		}
		wp_safe_redirect( admin_url( 'edit.php?post_type=bk_booking&page=bkb-settings&bkb_saved=' . ( $ok ? 'ok' : 'bad' ) ) );
		exit;
	}
);

add_action(
	'admin_post_bkb_export',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'bkb_export' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="booking-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
		$c = bkb_cfg();
		unset( $c['ics'] ); // The private calendar address stays on this site.
		echo wp_json_encode( $c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( BKB_DIR . 'bk-booking.php' ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'edit.php?post_type=bk_booking&page=bkb-settings' ) ) . '">Настройки</a>' );
		return $links;
	}
);

/* ---------- list of requests ---------- */

add_filter(
	'manage_bk_booking_posts_columns',
	function () {
		return array(
			'cb'        => '<input type="checkbox">',
			'title'     => 'Заявка',
			'bkb_when'  => 'Когда',
			'bkb_who'   => 'Контакт',
			'bkb_state' => 'Статус',
			'date'      => 'Получена',
		);
	}
);

function bkb_status_label( $s ) {
	$m = array(
		'pending'   => '<span style="color:#b26200;font-weight:600">Ждёт ответа</span>',
		'confirmed' => '<span style="color:#1a7f37;font-weight:600">Подтверждена</span>',
		'declined'  => '<span style="color:#8c8f94">Отклонена</span>',
	);
	return $m[ $s ] ?? esc_html( $s );
}

add_action(
	'manage_bk_booking_posts_custom_column',
	function ( $col, $id ) {
		$b = bkb_get( $id );
		if ( 'bkb_when' === $col ) {
			echo esc_html( wp_date( 'j.m.Y H:i', (int) $b['start'], wp_timezone() ) . '–' . wp_date( 'H:i', (int) $b['end'], wp_timezone() ) );
		} elseif ( 'bkb_who' === $col ) {
			echo esc_html( $b['name'] ) . '<br><a href="mailto:' . esc_attr( $b['email'] ) . '">' . esc_html( $b['email'] ) . '</a>';
		} elseif ( 'bkb_state' === $col ) {
			echo bkb_status_label( $b['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	},
	10,
	2
);

/* Pending count badge in the menu. */
add_action(
	'admin_menu',
	function () {
		global $menu;
		$n = count(
			get_posts(
				array(
					'post_type'      => 'bk_booking',
					'posts_per_page' => 50,
					'fields'         => 'ids',
					'meta_key'       => '_bkb_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			)
		);
		if ( ! $n ) {
			return;
		}
		foreach ( $menu as $i => $item ) {
			if ( isset( $item[2] ) && 'edit.php?post_type=bk_booking' === $item[2] ) {
				$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $n . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	},
	99
);

/* ---------- one request ---------- */

add_action(
	'add_meta_boxes_bk_booking',
	function () {
		add_meta_box( 'bkb_details', 'Детали заявки', 'bkb_details_box', 'bk_booking', 'normal', 'high' );
		remove_meta_box( 'submitdiv', 'bk_booking', 'side' );
	}
);

function bkb_details_box( $post ) {
	$b    = bkb_get( $post->ID );
	$tz   = wp_timezone();
	$prep = (int) bkb_cfg( 'schedule' )['prep'];
	echo '<table class="form-table" style="margin-top:0">';
	echo '<tr><th style="width:170px;padding:8px 10px">Статус</th><td style="padding:8px 10px">' . bkb_status_label( $b['status'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<tr><th style="padding:8px 10px">Когда</th><td style="padding:8px 10px">' . esc_html( wp_date( 'l, j F Y', (int) $b['start'], $tz ) . ', ' . wp_date( 'H:i', (int) $b['start'], $tz ) . '–' . wp_date( 'H:i', (int) $b['end'], $tz ) ) . ( $prep ? ' <span class="description">(с подготовкой)</span>' : '' ) . '</td></tr>';
	foreach ( array_slice( bkb_summary_lines( $b, 'ru', true ), 1 ) as $l ) {
		echo '<tr><th style="padding:8px 10px">' . esc_html( $l[0] ) . '</th><td style="padding:8px 10px">' . nl2br( esc_html( $l[1] ) ) . '</td></tr>';
	}
	echo '<tr><th style="padding:8px 10px">Сообщение</th><td style="padding:8px 10px">' . nl2br( esc_html( $b['msg'] ? $b['msg'] : '—' ) ) . '</td></tr>';
	echo '</table>';
	$act = function ( $s, $label, $class ) use ( $post ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=bkb_status&id=' . $post->ID . '&s=' . $s ), 'bkb_status_' . $post->ID );
		return '<a class="button ' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
	};
	echo '<p style="margin:16px 0 6px">';
	if ( 'confirmed' !== $b['status'] ) {
		echo $act( 'confirmed', 'Подтвердить и написать клиенту', 'button-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	if ( 'declined' !== $b['status'] ) {
		echo $act( 'declined', 'Отклонить и написать клиенту', '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( bkb_gcal_link( $b ) ) . '">Добавить в Google Календарь</a></p>';
	echo '<p class="description">Клиент получит письмо на своём языке. Подтверждённую запись добавьте в Google Календарь, чтобы время было занято и для других.</p>';
	if ( isset( $_GET['bkb_mail'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '1' === $_GET['bkb_mail'] ? '<div class="notice notice-success inline"><p>Письмо клиенту отправлено.</p></div>' : '<div class="notice notice-error inline"><p>Статус изменён, но письмо отправить не удалось. Напишите клиенту вручную.</p></div>'; // phpcs:ignore
	}
}

add_action(
	'admin_post_bkb_status',
	function () {
		$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$s  = (string) ( $_GET['s'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! in_array( $s, array( 'confirmed', 'declined' ), true ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'bkb_status_' . $id );
		update_post_meta( $id, '_bkb_status', $s );
		$ok = bkb_mail_client( $id, $s );
		do_action( 'bkb_status_changed', $id, $s );
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit&bkb_mail=' . ( $ok ? '1' : '0' ) ) );
		exit;
	}
);
