<?php
/**
 * Instagram in the admin: the connection page, the Direct box in the client card
 * and the "Send to Direct" button under ready-made replies.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- history entries ---------- */

add_filter(
	'bkb_crm_event_label',
	function ( $label, $type ) {
		if ( 'ig_in' === $type ) {
			return __( 'Instagram, from the client', 'shootbook' );
		}
		if ( 'ig_out' === $type ) {
			return __( 'Instagram, to the client', 'shootbook' );
		}
		return $label;
	},
	10,
	2
);

/* ---------- the connection page ---------- */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=bk_booking', 'Instagram', 'Instagram', 'manage_options', 'bkb-ig', 'bkb_ig_page' );
	},
	20
);

function bkb_ig_page() {
	$o   = bkb_ig_get();
	$msg = sanitize_key( wp_unslash( $_GET['bkb_ig'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$err = get_transient( 'bkb_ig_err' );
	delete_transient( 'bkb_ig_err' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Instagram Direct', 'shootbook' ); ?></h1>
		<p><?php esc_html_e( 'Messages to your Instagram account come into Requests → Clients. A person who writes for the first time becomes a new client with the source Instagram, and every message goes to the client’s history. You can answer from the client card within 24 hours after the client’s last message.', 'shootbook' ); ?></p>
		<?php if ( 'saved' === $msg ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Connected.', 'shootbook' ); ?></p></div>
		<?php elseif ( 'off' === $msg ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Disconnected. The messages already saved stay in the client cards.', 'shootbook' ); ?></p></div>
		<?php elseif ( 'synced' === $msg ) : ?>
			<div class="notice notice-success"><p>
			<?php
			/* translators: %d: number of messages */
			echo esc_html( sprintf( __( 'Checked. New messages saved: %d.', 'shootbook' ), (int) ( $_GET['n'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			?>
			</p></div>
		<?php endif; ?>
		<?php if ( $err ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( __( 'Instagram answered:', 'shootbook' ) . ' ' . $err ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Status', 'shootbook' ); ?></h2>
		<?php if ( bkb_ig_connected() ) : ?>
			<p>
			<?php
			/* translators: 1: @name of the account, 2: date */
			echo esc_html( sprintf( __( 'Connected to @%1$s. The access key is renewed automatically, the current one is valid until %2$s.', 'shootbook' ), $o['username'], wp_date( 'j.m.Y', $o['expires'] ) ) );
			?>
			</p>
			<p>
			<?php
			echo esc_html( __( 'Last message from Instagram:', 'shootbook' ) . ' ' . ( $o['last_hook'] ? wp_date( 'j.m.Y H:i', $o['last_hook'] ) : __( 'none yet', 'shootbook' ) ) );
			echo '<br>';
			echo esc_html( __( 'Last hourly check:', 'shootbook' ) . ' ' . ( $o['last_sync'] ? wp_date( 'j.m.Y H:i', $o['last_sync'] ) : __( 'none yet', 'shootbook' ) ) );
			?>
			</p>
			<?php if ( $o['last_error'] ) : ?>
				<p class="description"><?php echo esc_html( __( 'Last error:', 'shootbook' ) . ' ' . $o['last_error'] ); ?></p>
			<?php endif; ?>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bkb_ig_sync' ), 'bkb_ig_sync' ) ); ?>"><?php esc_html_e( 'Check for new messages now', 'shootbook' ); ?></a>
				<a class="button-link-delete" style="margin-left:12px" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bkb_ig_off' ), 'bkb_ig_off' ) ); ?>"><?php esc_html_e( 'Disconnect', 'shootbook' ); ?></a>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'Not connected.', 'shootbook' ); ?></p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'How to connect', 'shootbook' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Your Instagram account must be a professional one: Business or Creator.', 'shootbook' ); ?></li>
			<li><?php esc_html_e( 'In the Instagram app, open Settings → Messages and story replies → Message controls → Connected tools, and turn on “Allow access to messages”.', 'shootbook' ); ?></li>
			<li><?php esc_html_e( 'On developers.facebook.com create an app of the Business type and add the Instagram product. Choose “API setup with Instagram login”.', 'shootbook' ); ?></li>
			<li><?php esc_html_e( 'In step “Generate access tokens”, add your Instagram account and generate a token. Paste it below together with the Instagram app secret from the same page.', 'shootbook' ); ?></li>
			<li><?php esc_html_e( 'In step “Configure webhooks”, paste the address and the verify token below, press “Verify and save”, then turn on the “messages” field.', 'shootbook' ); ?></li>
		</ol>
		<table class="form-table" role="presentation">
			<tr><th><?php esc_html_e( 'Callback URL', 'shootbook' ); ?></th><td><input type="text" class="large-text code" readonly value="<?php echo esc_attr( bkb_ig_webhook_url() ); ?>" onclick="this.select()"></td></tr>
			<tr><th><?php esc_html_e( 'Verify token', 'shootbook' ); ?></th><td><input type="text" class="regular-text code" readonly value="<?php echo esc_attr( $o['verify'] ); ?>" onclick="this.select()"></td></tr>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bkb_ig_save">
			<?php wp_nonce_field( 'bkb_ig_save' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="bkb-ig-secret"><?php esc_html_e( 'Instagram app secret', 'shootbook' ); ?></label></th><td><input type="password" id="bkb-ig-secret" name="secret" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr( $o['secret'] ? __( 'saved, leave empty to keep it', 'shootbook' ) : '' ); ?>"></td></tr>
				<tr><th><label for="bkb-ig-token"><?php esc_html_e( 'Access token', 'shootbook' ); ?></label></th><td><input type="password" id="bkb-ig-token" name="token" class="large-text" autocomplete="off" placeholder="<?php echo esc_attr( $o['token'] ? __( 'saved, leave empty to keep it', 'shootbook' ) : '' ); ?>"></td></tr>
			</table>
			<p><button class="button button-primary"><?php esc_html_e( 'Connect', 'shootbook' ); ?></button></p>
		</form>
	</div>
	<?php
}

function bkb_ig_back( $args ) {
	wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php?post_type=bk_booking&page=bkb-ig' ) ) );
	exit;
}

add_action(
	'admin_post_bkb_ig_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'bkb_ig_save' );
		$o      = bkb_ig_get();
		$secret = trim( sanitize_text_field( wp_unslash( $_POST['secret'] ?? '' ) ) );
		$token  = trim( sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ) );
		$secret = '' === $secret ? $o['secret'] : $secret;
		$token  = '' === $token ? $o['token'] : $token;
		if ( '' === $secret || '' === $token ) {
			set_transient( 'bkb_ig_err', __( 'Fill in both the app secret and the access token.', 'shootbook' ), 60 );
			bkb_ig_back( array() );
		}
		$r = bkb_ig_connect( $token, $secret );
		if ( is_wp_error( $r ) ) {
			set_transient( 'bkb_ig_err', $r->get_error_message(), 60 );
			bkb_ig_back( array() );
		}
		bkb_ig_sync();
		bkb_ig_back( array( 'bkb_ig' => 'saved' ) );
	}
);

add_action(
	'admin_post_bkb_ig_sync',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'bkb_ig_sync' );
		$n   = bkb_ig_sync();
		$err = bkb_ig_get( 'last_error' );
		if ( $err ) {
			set_transient( 'bkb_ig_err', $err, 60 );
		}
		bkb_ig_back(
			array(
				'bkb_ig' => 'synced',
				'n'   => $n,
			)
		);
	}
);

add_action(
	'admin_post_bkb_ig_off',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'bkb_ig_off' );
		bkb_ig_set(
			array(
				'token'      => '',
				'secret'     => '',
				'ig_id'      => '',
				'username'   => '',
				'expires'    => 0,
				'last_error' => '',
			)
		);
		bkb_ig_back( array( 'bkb_ig' => 'off' ) );
	}
);

/* ---------- the Direct box in the client card ---------- */

add_action(
	'bkb_crm_card_history',
	function ( $c ) {
		if ( ! bkb_ig_connected() ) {
			return;
		}
		$p = bkb_ig_person_by_client( (int) $c['id'] );
		if ( ! $p ) {
			return;
		}
		$until = bkb_ig_window( $p );
		echo '<div class="bkb-ig-box" data-client="' . (int) $c['id'] . '" style="margin:12px 0;padding:10px 12px;border:1px solid #dcdcde;border-radius:6px;background:#f6f7f7">';
		echo '<p style="margin-top:0"><b>' . esc_html__( 'Answer in Instagram Direct', 'shootbook' ) . '</b><br>';
		if ( $until ) {
			/* translators: %s: date and time */
			echo esc_html( sprintf( __( 'You can write until %s, 24 hours after the client’s last message.', 'shootbook' ), wp_date( 'j.m.Y H:i', $until ) ) );
		} else {
			esc_html_e( 'More than 24 hours have passed since the client’s last message. Instagram lets you write only after the client writes again.', 'shootbook' );
		}
		echo '</p>';
		if ( $until ) {
			echo '<textarea class="widefat" rows="3" data-ig-text></textarea>';
			echo '<p style="margin-bottom:0"><button type="button" class="button button-primary" data-ig-send>' . esc_html__( 'Send to Direct', 'shootbook' ) . '</button> <span data-ig-result></span></p>';
		}
		echo '</div>';
		bkb_ig_enqueue();
	}
);

/* ---------- the button under ready-made replies ---------- */

add_action(
	'bkb_reply_actions',
	function ( $client ) {
		if ( ! $client || ! bkb_ig_connected() ) {
			return;
		}
		$p = bkb_ig_person_by_client( (int) $client['id'] );
		if ( ! $p || ! bkb_ig_window( $p ) ) {
			return;
		}
		echo '<button type="button" class="button" data-ig-reply="' . (int) $client['id'] . '">' . esc_html__( 'Send to Direct', 'shootbook' ) . '</button> ';
		bkb_ig_enqueue();
	}
);

function bkb_ig_enqueue() {
	wp_enqueue_script( 'bkb-ig', BKB_URL . 'assets/instagram.js', array( 'wp-i18n' ), BKB_VER, true );
	wp_set_script_translations( 'bkb-ig', 'shootbook', BKB_DIR . 'languages' );
	wp_localize_script(
		'bkb-ig',
		'bkbIg',
		array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bkb_ig_send' ),
		)
	);
}

add_action(
	'wp_ajax_bkb_ig_send',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'not allowed', 'shootbook' ) );
		}
		check_ajax_referer( 'bkb_ig_send' );
		$cid  = (int) ( $_POST['client'] ?? 0 );
		$text = trim( sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) ) );
		if ( ! $cid || '' === $text ) {
			wp_send_json_error( __( 'no text', 'shootbook' ) );
		}
		$r = bkb_ig_send( $cid, $text );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		wp_send_json_success();
	}
);
