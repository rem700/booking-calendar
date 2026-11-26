<?php
/**
 * Google Calendar in the admin: the connection page, the calendar address for other apps,
 * and the Google line in a request.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=bk_booking', __( 'Google Calendar', 'shootbook' ), __( 'Google Calendar', 'shootbook' ), 'manage_options', 'bkb-gcal', 'bkb_gcal_page' );
	},
	21
);

function bkb_gcal_post_url( $action ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=' . $action ), $action );
}

function bkb_gcal_page() {
	$o   = bkb_gcal_get();
	$msg = sanitize_key( wp_unslash( $_GET['bkb_gcal'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$err = get_transient( 'bkb_gcal_err' );
	delete_transient( 'bkb_gcal_err' );
	$done = array(
		'connected' => __( 'Connected. Bookings go to Google Calendar.', 'shootbook' ),
		'saved'     => __( 'Saved.', 'shootbook' ),
		'off'       => __( 'Disconnected. Events already in Google stay there.', 'shootbook' ),
		'feed'      => __( 'A new address is made. The old one no longer works.', 'shootbook' ),
	);
	?>
	<div class="wrap bkb-gcal">
		<h1><?php esc_html_e( 'Google Calendar', 'shootbook' ); ?></h1>
		<p><?php esc_html_e( 'A confirmed booking appears in your Google Calendar by itself. When you change its time, the event moves. When you decline or delete the request, the event is removed. The events of the calendar are busy time on the site at once.', 'shootbook' ); ?></p>
		<?php if ( 'sent' === $msg ) : ?>
			<div class="notice notice-success"><p>
			<?php
			/* translators: %d: number of bookings */
			echo esc_html( sprintf( __( 'Sent to Google: %d.', 'shootbook' ), (int) ( $_GET['n'] ?? 0 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			?>
			</p></div>
		<?php elseif ( isset( $done[ $msg ] ) ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $done[ $msg ] ); ?></p></div>
		<?php endif; ?>
		<?php if ( $err ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( __( 'Google answered:', 'shootbook' ) . ' ' . $err ); ?></p></div>
		<?php endif; ?>

		<?php if ( bkb_gcal_connected() ) : ?>
			<?php bkb_gcal_connected_box( $o ); ?>
		<?php else : ?>
			<?php bkb_gcal_setup_box( $o ); ?>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Calendar address for other apps', 'shootbook' ); ?></h2>
		<p><?php esc_html_e( 'Confirmed bookings as a calendar you can subscribe to in Apple Calendar, Outlook or on a phone. Apps check it on their own schedule, from a few minutes to a day. Keep the address private: anyone who has it sees the bookings.', 'shootbook' ); ?></p>
		<p><input type="text" class="large-text code" readonly id="bkb-feed-url" value="<?php echo esc_attr( bkb_feed_url() ); ?>" onclick="this.select()"></p>
		<p><a class="button" href="<?php echo esc_url( bkb_gcal_post_url( 'bkb_feed_renew' ) ); ?>"><?php esc_html_e( 'Make a new address', 'shootbook' ); ?></a></p>
	</div>
	<?php
}

function bkb_gcal_setup_box( $o ) {
	$have = '' !== $o['client_id'] && '' !== $o['client_secret'];
	?>
	<?php if ( $o['last_error'] && $have ) : ?>
		<div class="notice notice-warning inline"><p><?php echo esc_html( __( 'Last error:', 'shootbook' ) . ' ' . $o['last_error'] ); ?></p></div>
	<?php endif; ?>
	<h2><?php esc_html_e( 'How to connect', 'shootbook' ); ?></h2>
	<p><?php esc_html_e( 'It is done once and is free. You make your own access in Google Cloud, so the bookings go only between this site and your Google account.', 'shootbook' ); ?></p>
	<ol class="bkb-steps">
		<li>
		<?php
		/* translators: %s: link to Google Cloud console */
		printf( esc_html__( 'Open %s and create a project, for example “Shootbook”.', 'shootbook' ), '<a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">Google Cloud</a>' );
		?>
		</li>
		<li>
		<?php
		/* translators: %s: link to the Google Calendar API page */
		printf( esc_html__( 'Turn on %s for this project.', 'shootbook' ), '<a href="https://console.cloud.google.com/apis/library/calendar-json.googleapis.com" target="_blank" rel="noopener">Google Calendar API</a>' );
		?>
		</li>
		<li>
		<?php
		/* translators: %s: link to Google Auth Platform */
		printf( esc_html__( 'In %s fill in the app name and your e-mail, and choose the type External.', 'shootbook' ), '<a href="https://console.cloud.google.com/auth/overview" target="_blank" rel="noopener">Google Auth Platform</a>' );
		?>
		</li>
		<li><?php esc_html_e( 'On the Audience tab press Publish app. While the app is in Testing, Google ends the access after 7 days.', 'shootbook' ); ?></li>
		<li>
		<?php
		esc_html_e( 'On the Clients tab create a client of the type Web application. Under Authorized redirect URIs add this address:', 'shootbook' );
		echo '<br><input type="text" class="large-text code" readonly value="' . esc_attr( bkb_gcal_redirect_uri() ) . '" onclick="this.select()">';
		?>
		</li>
		<li><?php esc_html_e( 'Copy the Client ID and the Client secret of the new client into the fields below and press the button.', 'shootbook' ); ?></li>
		<li><?php esc_html_e( 'Google will say the app is not verified. This is normal for your own app: press Advanced and go on.', 'shootbook' ); ?></li>
	</ol>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="bkb_gcal_keys">
		<?php wp_nonce_field( 'bkb_gcal_keys' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="bkb-gcal-id">Client ID</label></th>
				<td><input type="text" class="large-text code" id="bkb-gcal-id" name="client_id" value="<?php echo esc_attr( $o['client_id'] ); ?>" autocomplete="off"></td>
			</tr>
			<tr>
				<th scope="row"><label for="bkb-gcal-secret">Client secret</label></th>
				<td><input type="password" class="regular-text code" id="bkb-gcal-secret" name="client_secret" value="" autocomplete="new-password" placeholder="<?php echo $o['client_secret'] ? esc_attr__( 'saved, leave empty to keep', 'shootbook' ) : ''; ?>"></td>
			</tr>
		</table>
		<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save and sign in with Google', 'shootbook' ); ?></button></p>
	</form>
	<?php
}

function bkb_gcal_connected_box( $o ) {
	$list  = bkb_gcal_calendars();
	$dirty = count( bkb_gcal_dirty_ids( 1000 ) );
	?>
	<h2><?php esc_html_e( 'Status', 'shootbook' ); ?></h2>
	<p>
	<?php
	/* translators: 1: Google account, 2: calendar name */
	echo esc_html( sprintf( __( 'Connected to %1$s. Bookings go to the calendar “%2$s”.', 'shootbook' ), $o['account'] ? $o['account'] : 'Google', $o['calendar_name'] ? $o['calendar_name'] : $o['calendar'] ) );
	echo '<br>';
	echo esc_html( __( 'Last change sent:', 'shootbook' ) . ' ' . ( $o['last_push'] ? wp_date( 'j.m.Y H:i', $o['last_push'] ) : __( 'none yet', 'shootbook' ) ) );
	?>
	</p>
	<?php if ( $dirty ) : ?>
		<div class="notice notice-warning inline"><p>
		<?php
		/* translators: %d: number of bookings */
		echo esc_html( sprintf( __( 'Waiting to be sent: %d. The site tries again every hour.', 'shootbook' ), $dirty ) );
		if ( $o['last_error'] ) {
			echo '<br>' . esc_html( __( 'Last error:', 'shootbook' ) . ' ' . $o['last_error'] );
		}
		?>
		</p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="bkb_gcal_options">
		<?php wp_nonce_field( 'bkb_gcal_options' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="bkb-gcal-cal"><?php esc_html_e( 'Calendar', 'shootbook' ); ?></label></th>
				<td>
					<?php if ( is_wp_error( $list ) ) : ?>
						<p class="description"><?php echo esc_html( __( 'Could not get the list of calendars:', 'shootbook' ) . ' ' . $list->get_error_message() ); ?></p>
						<input type="hidden" name="calendar" value="<?php echo esc_attr( $o['calendar'] ); ?>">
					<?php else : ?>
						<select id="bkb-gcal-cal" name="calendar">
							<?php foreach ( $list as $c ) : ?>
								<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $c['id'], $o['calendar'] ); ?>><?php echo esc_html( $c['summary'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'When you choose another calendar, the bookings move there.', 'shootbook' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Requests', 'shootbook' ); ?></th>
				<td><label><input type="checkbox" name="pending" value="1" <?php checked( $o['pending'] ); ?>> <?php esc_html_e( 'Also send requests that wait for an answer, with “?” before the title', 'shootbook' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Busy time', 'shootbook' ); ?></th>
				<td>
					<label><input type="checkbox" name="busy" value="1" <?php checked( $o['busy'] ); ?>> <?php esc_html_e( 'Other events of this calendar make the time busy on the site', 'shootbook' ); ?></label>
					<p class="description"><?php esc_html_e( 'Then the iCal address of the same calendar in the settings is not needed. Addresses of other calendars keep working.', 'shootbook' ); ?></p>
				</td>
			</tr>
		</table>
		<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'shootbook' ); ?></button></p>
	</form>
	<p>
		<a class="button" href="<?php echo esc_url( bkb_gcal_post_url( 'bkb_gcal_resend' ) ); ?>"><?php esc_html_e( 'Send all current bookings again', 'shootbook' ); ?></a>
		<a class="button" href="<?php echo esc_url( bkb_gcal_post_url( 'bkb_gcal_off' ) ); ?>"><?php esc_html_e( 'Disconnect', 'shootbook' ); ?></a>
	</p>
	<?php
}

/* ---------- actions ---------- */

function bkb_gcal_check( $action ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Forbidden' );
	}
	check_admin_referer( $action );
}

add_action(
	'admin_post_bkb_gcal_keys',
	function () {
		bkb_gcal_check( 'bkb_gcal_keys' );
		$id     = trim( sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) ) );
		$secret = trim( sanitize_text_field( wp_unslash( $_POST['client_secret'] ?? '' ) ) );
		$old    = bkb_gcal_get();
		bkb_gcal_set(
			array(
				'client_id'     => $id,
				'client_secret' => '' !== $secret ? $secret : ( $id === $old['client_id'] ? $old['client_secret'] : '' ),
				'last_error'    => '',
			)
		);
		if ( '' === $id || '' === bkb_gcal_get( 'client_secret' ) ) {
			bkb_gcal_back( 'err', __( 'Fill in both fields.', 'shootbook' ) );
		}
		wp_redirect( bkb_gcal_auth_url() ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}
);

add_action(
	'admin_post_bkb_gcal_options',
	function () {
		bkb_gcal_check( 'bkb_gcal_options' );
		$o   = bkb_gcal_get();
		$cal = sanitize_text_field( wp_unslash( $_POST['calendar'] ?? '' ) );
		bkb_gcal_set(
			array(
				'pending' => ! empty( $_POST['pending'] ),
				'busy'    => ! empty( $_POST['busy'] ),
			)
		);
		bkb_gcal_busy_flush();
		if ( '' !== $cal && $cal !== $o['calendar'] ) {
			$name = $cal;
			$list = bkb_gcal_calendars();
			foreach ( is_wp_error( $list ) ? array() : $list as $c ) {
				if ( $c['id'] === $cal ) {
					$name = $c['summary'];
				}
			}
			bkb_gcal_use_calendar( $cal, $name );
		} elseif ( (bool) $o['pending'] !== ! empty( $_POST['pending'] ) ) {
			bkb_gcal_mark_all();
			bkb_gcal_push_dirty( 30 );
		}
		bkb_gcal_back( 'saved' );
	}
);

add_action(
	'admin_post_bkb_gcal_resend',
	function () {
		bkb_gcal_check( 'bkb_gcal_resend' );
		bkb_gcal_mark_all();
		$n = bkb_gcal_push_dirty( 100 );
		wp_safe_redirect( admin_url( 'edit.php?post_type=bk_booking&page=bkb-gcal&bkb_gcal=sent&n=' . $n ) );
		exit;
	}
);

add_action(
	'admin_post_bkb_gcal_off',
	function () {
		bkb_gcal_check( 'bkb_gcal_off' );
		bkb_gcal_disconnect();
		bkb_gcal_back( 'off' );
	}
);

add_action(
	'admin_post_bkb_feed_renew',
	function () {
		bkb_gcal_check( 'bkb_feed_renew' );
		bkb_feed_key( true );
		bkb_gcal_back( 'feed' );
	}
);

/* Google stopped giving access: say so on the plugin screens, or bookings would quietly stop going there. */
add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		$o      = bkb_gcal_get();
		if ( ! $screen || 'bk_booking' !== $screen->post_type || 'bk_booking_page_bkb-gcal' === $screen->id || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( '' === $o['client_id'] || '' === $o['calendar'] || '' !== $o['refresh'] ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Google Calendar is not connected any more, bookings do not go there.', 'shootbook' ) . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=bk_booking&page=bkb-gcal' ) ) . '">' . esc_html__( 'Sign in again', 'shootbook' ) . '</a></p></div>';
	}
);

/** The Google line in a request. */
function bkb_gcal_request_line( $id ) {
	if ( ! bkb_gcal_connected() ) {
		return '';
	}
	$err = (string) get_post_meta( $id, '_bkb_gcal_error', true );
	if ( get_post_meta( $id, '_bkb_gcal_dirty', true ) ) {
		return '<span style="color:#b32d2e">' . esc_html( __( 'Not sent to Google yet, the site tries again every hour.', 'shootbook' ) . ( $err ? ' ' . $err : '' ) ) . '</span>';
	}
	if ( get_post_meta( $id, '_bkb_gcal_id', true ) ) {
		return esc_html__( 'In Google Calendar.', 'shootbook' );
	}
	return esc_html__( 'Not in Google Calendar.', 'shootbook' );
}
