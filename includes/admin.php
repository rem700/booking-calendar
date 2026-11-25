<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- settings page ---------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=bk_booking', __( 'Booking settings', 'shootbook' ), __( 'Settings', 'shootbook' ), 'manage_options', 'bkb-settings', 'bkb_settings_page' );
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
		wp_enqueue_script( 'bkb-admin', BKB_URL . 'assets/admin.js', array( 'wp-i18n' ), BKB_VER, true );
		wp_set_script_translations( 'bkb-admin', 'shootbook', BKB_DIR . 'languages' );
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
					'replies'   => bkb_default_replies(),
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
		<h1><?php esc_html_e( 'Booking settings', 'shootbook' ); ?></h1>
		<?php if ( 'ok' === $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'shootbook' ); ?></p></div>
		<?php elseif ( 'bad' === $saved ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Could not read the settings. Check the JSON.', 'shootbook' ); ?></p></div>
		<?php endif; ?>
		<p class="description">
		<?php
		/* translators: 1: [booking_calendar] shortcode, 2: [bk_booking] shortcode */
		printf( esc_html__( 'Add the booking form to a page with the shortcode %1$s (or %2$s). The form follows the page language (Polylang, WPML or the site language).', 'shootbook' ), '<code>[booking_calendar]</code>', '<code>[bk_booking]</code>' );
		?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bkb-settings-form">
			<input type="hidden" name="action" value="bkb_save_config">
			<?php wp_nonce_field( 'bkb_save_config' ); ?>
			<input type="hidden" name="bkb_config_json" id="bkb-config-json" value="">
			<div id="bkb-editor"><p><?php esc_html_e( 'Loading…', 'shootbook' ); ?></p></div>
			<p class="submit"><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save settings', 'shootbook' ); ?></button></p>
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
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'edit.php?post_type=bk_booking&page=bkb-settings' ) ) . '">' . esc_html__( 'Settings', 'shootbook' ) . '</a>' );
		return $links;
	}
);

/* ---------- list of requests ---------- */

add_filter(
	'manage_bk_booking_posts_columns',
	function () {
		return array(
			'cb'        => '<input type="checkbox">',
			'title'     => __( 'Request', 'shootbook' ),
			'bkb_when'  => __( 'When', 'shootbook' ),
			'bkb_who'   => __( 'Contact', 'shootbook' ),
			'bkb_state' => __( 'Status', 'shootbook' ),
			'date'      => __( 'Received', 'shootbook' ),
		);
	}
);

function bkb_status_label( $s ) {
	$m = array(
		'pending'   => '<span style="color:#b26200;font-weight:600">' . esc_html__( 'Waiting for an answer', 'shootbook' ) . '</span>',
		'confirmed' => '<span style="color:#1a7f37;font-weight:600">' . esc_html__( 'Confirmed', 'shootbook' ) . '</span>',
		'declined'  => '<span style="color:#8c8f94">' . esc_html__( 'Declined', 'shootbook' ) . '</span>',
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
			echo esc_html( $b['name'] ) . '<br>' . ( $b['email'] ? '<a href="mailto:' . esc_attr( $b['email'] ) . '">' . esc_html( $b['email'] ) . '</a>' : esc_html( $b['phone'] ) );
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
		add_meta_box( 'bkb_details', __( 'Request details', 'shootbook' ), 'bkb_details_box', 'bk_booking', 'normal', 'high' );
		remove_meta_box( 'submitdiv', 'bk_booking', 'side' );
	}
);

function bkb_details_box( $post ) {
	$b    = bkb_get( $post->ID );
	$tz   = wp_timezone();
	$prep = (int) bkb_cfg( 'schedule' )['prep'];
	echo '<table class="form-table" style="margin-top:0">';
	echo '<tr><th style="width:170px;padding:8px 10px">' . esc_html__( 'Status', 'shootbook' ) . '</th><td style="padding:8px 10px">' . bkb_status_label( $b['status'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<tr><th style="padding:8px 10px">' . esc_html__( 'When', 'shootbook' ) . '</th><td style="padding:8px 10px">' . esc_html( wp_date( 'l, j F Y', (int) $b['start'], $tz ) . ', ' . wp_date( 'H:i', (int) $b['start'], $tz ) . '–' . wp_date( 'H:i', (int) $b['end'], $tz ) ) . ( $prep ? ' <span class="description">' . esc_html__( '(with preparation)', 'shootbook' ) . '</span>' : '' ) . '</td></tr>';
	foreach ( array_slice( bkb_summary_lines( $b, bkb_admin_lang(), true ), 1 ) as $l ) {
		echo '<tr><th style="padding:8px 10px">' . esc_html( $l[0] ) . '</th><td style="padding:8px 10px">' . nl2br( esc_html( $l[1] ) ) . '</td></tr>';
	}
	echo '<tr><th style="padding:8px 10px">' . esc_html__( 'Message', 'shootbook' ) . '</th><td style="padding:8px 10px">' . nl2br( esc_html( $b['msg'] ? $b['msg'] : '—' ) ) . '</td></tr>';
	echo '</table>';
	$act = function ( $s, $label, $class ) use ( $post ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=bkb_status&id=' . $post->ID . '&s=' . $s ), 'bkb_status_' . $post->ID );
		return '<a class="button ' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
	};
	echo '<p style="margin:16px 0 6px">';
	if ( 'confirmed' !== $b['status'] ) {
		echo $act( 'confirmed', $b['email'] ? __( 'Confirm and e-mail the client', 'shootbook' ) : __( 'Confirm', 'shootbook' ), 'button-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	if ( 'declined' !== $b['status'] ) {
		echo $act( 'declined', $b['email'] ? __( 'Decline and e-mail the client', 'shootbook' ) : __( 'Decline', 'shootbook' ), '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( bkb_gcal_link( $b ) ) . '">' . esc_html__( 'Add to Google Calendar', 'shootbook' ) . '</a></p>';
	echo '<p class="description">' . esc_html__( 'The client gets the e-mail in their language. Add a confirmed booking to Google Calendar so the time is busy for others too.', 'shootbook' ) . '</p>';
	if ( isset( $_GET['bkb_mail'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$m = (string) $_GET['bkb_mail']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
		if ( '2' === $m ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Status changed. The client has no e-mail, please contact them yourself.', 'shootbook' ) . '</p></div>';
		} else {
			echo '1' === $m ? '<div class="notice notice-success inline"><p>' . esc_html__( 'The e-mail to the client has been sent.', 'shootbook' ) . '</p></div>' : '<div class="notice notice-error inline"><p>' . esc_html__( 'Status changed, but the e-mail could not be sent. Please write to the client yourself.', 'shootbook' ) . '</p></div>';
		}
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
		$has = is_email( (string) get_post_meta( $id, '_bkb_email', true ) );
		$ok  = $has ? bkb_mail_client( $id, $s ) : false;
		do_action( 'bkb_status_changed', $id, $s );
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit&bkb_mail=' . ( $has ? ( $ok ? '1' : '0' ) : '2' ) ) );
		exit;
	}
);
