<?php
/**
 * Clients screens in WP admin: list with stages and tasks, client card, report by source.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=bk_booking', __( 'Clients', 'shootbook' ), __( 'Clients', 'shootbook' ), 'manage_options', 'bkb-clients', 'bkb_crm_page' );
		add_submenu_page( 'edit.php?post_type=bk_booking', __( 'Requests report', 'shootbook' ), __( 'Report', 'shootbook' ), 'manage_options', 'bkb-report', 'bkb_crm_report_page' );
	},
	5
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( in_array( $hook, array( 'bk_booking_page_bkb-clients', 'bk_booking_page_bkb-report' ), true ) ) {
			wp_enqueue_style( 'bkb-admin', BKB_URL . 'assets/admin.css', array(), BKB_VER );
		}
	}
);

function bkb_crm_stage_badge( $s ) {
	return '<span class="bkb-stage bkb-stage-' . esc_attr( $s ) . '">' . esc_html( bkb_crm_stage_label( $s ) ) . '</span>';
}

function bkb_crm_action_url( $action, $args = array() ) {
	return wp_nonce_url( add_query_arg( array_merge( array( 'action' => $action ), $args ), admin_url( 'admin-post.php' ) ), $action );
}

/* ---------- list ---------- */

function bkb_crm_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_GET['new'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		bkb_crm_new_form( (int) ( $_GET['client'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( ! empty( $_GET['client'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		bkb_crm_card( (int) $_GET['client'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	global $wpdb;
	$ct     = bkb_crm_table( 'clients' );
	$et     = bkb_crm_table( 'events' );
	$stage  = isset( $_GET['stage'] ) ? sanitize_key( $_GET['stage'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$paged  = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$per    = 50;
	$where  = '1=1';
	$args   = array();
	if ( $stage && isset( bkb_crm_stages()[ $stage ] ) ) {
		$where .= ' AND c.stage = %s';
		$args[] = $stage;
	}
	if ( '' !== $search ) {
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$where .= ' AND (c.name LIKE %s OR c.email LIKE %s OR c.instagram LIKE %s OR c.phone LIKE %s OR c.notes LIKE %s)';
		array_push( $args, $like, $like, $like, $like, $like );
	}
	$sql_where = $args ? $wpdb->prepare( $where, $args ) : $where; // phpcs:ignore WordPress.DB
	$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $ct c WHERE $sql_where" ); // phpcs:ignore WordPress.DB
	$rows      = $wpdb->get_results( "SELECT c.*, (SELECT COUNT(*) FROM $et e WHERE e.client_id = c.id AND e.type = 'request') AS requests FROM $ct c WHERE $sql_where ORDER BY c.last_contact DESC LIMIT " . (int) $per . ' OFFSET ' . (int) ( ( $paged - 1 ) * $per ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$counts    = $wpdb->get_results( "SELECT stage, COUNT(*) n FROM $ct GROUP BY stage", OBJECT_K ); // phpcs:ignore WordPress.DB
	$all       = array_sum( array_map( fn( $x ) => (int) $x->n, $counts ) );
	$base      = admin_url( 'edit.php?post_type=bk_booking&page=bkb-clients' );
	$tz_end    = gmdate( 'Y-m-d H:i:s', ( new DateTime( 'tomorrow', wp_timezone() ) )->getTimestamp() );
	$tasks     = $wpdb->get_results( $wpdb->prepare( "SELECT e.*, c.name FROM $et e LEFT JOIN $ct c ON c.id = e.client_id WHERE e.type = 'task' AND e.done = 0 AND (e.due IS NULL OR e.due < %s) ORDER BY e.due IS NULL, e.due LIMIT 30", $tz_end ), ARRAY_A ); // phpcs:ignore WordPress.DB
	?>
	<div class="wrap bkb-crm">
		<h1 style="display:flex;gap:12px;align-items:center"><?php esc_html_e( 'Clients', 'shootbook' ); ?> <a class="page-title-action" href="<?php echo esc_url( admin_url( 'edit.php?post_type=bk_booking&page=bkb-clients&new=1' ) ); ?>"><?php esc_html_e( 'Add a request from Instagram or by hand', 'shootbook' ); ?></a></h1>
		<?php bkb_crm_notice(); ?>
		<?php if ( $tasks ) : ?>
			<div class="bkb-crm-box bkb-crm-tasks">
				<h2><?php esc_html_e( 'Tasks for today', 'shootbook' ); ?></h2>
				<ul>
					<?php foreach ( $tasks as $t ) : ?>
						<?php $late = $t['due'] && strtotime( $t['due'] . ' UTC' ) < ( new DateTime( 'today', wp_timezone() ) )->getTimestamp(); ?>
						<li>
							<span class="<?php echo $late ? 'bkb-late' : ''; ?>"><?php echo esc_html( $t['due'] ? bkb_crm_time( $t['due'], 'j.m' ) : __( 'no due date', 'shootbook' ) ); ?></span>
							<?php echo esc_html( $t['body'] ); ?>
							<?php if ( $t['client_id'] ) : ?>
								— <a href="<?php echo esc_url( bkb_crm_client_url( $t['client_id'] ) ); ?>"><?php echo esc_html( $t['name'] ); ?></a>
							<?php endif; ?>
							<a class="bkb-done" href="<?php echo esc_url( bkb_crm_action_url( 'bkb_crm_task_done', array( 'event' => $t['id'], 'back' => 'list' ) ) ); ?>"><?php esc_html_e( 'Done', 'shootbook' ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<ul class="subsubsub">
			<li><a href="<?php echo esc_url( $base ); ?>" class="<?php echo '' === $stage ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'shootbook' ); ?> <span class="count">(<?php echo (int) $all; ?>)</span></a></li>
			<?php foreach ( bkb_crm_stages() as $k => $label ) : ?>
				<li>| <a href="<?php echo esc_url( add_query_arg( 'stage', $k, $base ) ); ?>" class="<?php echo $stage === $k ? 'current' : ''; ?>"><?php echo esc_html( $label ); ?> <span class="count">(<?php echo (int) ( $counts[ $k ]->n ?? 0 ); ?>)</span></a></li>
			<?php endforeach; ?>
		</ul>
		<form method="get" class="search-box" style="margin:6px 0 10px">
			<input type="hidden" name="post_type" value="bk_booking">
			<input type="hidden" name="page" value="bkb-clients">
			<?php if ( $stage ) : ?>
				<input type="hidden" name="stage" value="<?php echo esc_attr( $stage ); ?>">
			<?php endif; ?>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Name, e-mail, Instagram, phone, notes', 'shootbook' ); ?>">
			<button class="button"><?php esc_html_e( 'Search', 'shootbook' ); ?></button>
		</form>
		<br class="clear">
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th style="width:24%"><?php esc_html_e( 'Client', 'shootbook' ); ?></th><th><?php esc_html_e( 'Contacts', 'shootbook' ); ?></th><th style="width:15%"><?php esc_html_e( 'Stage', 'shootbook' ); ?></th><th style="width:15%"><?php esc_html_e( 'Source', 'shootbook' ); ?></th><th style="width:8%"><?php echo esc_html_x( 'Requests', 'number of requests', 'shootbook' ); ?></th><th style="width:13%"><?php esc_html_e( 'Last contact', 'shootbook' ); ?></th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No clients yet. They appear by themselves when a request comes from the site.', 'shootbook' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $c ) : ?>
				<tr>
					<td><strong><a href="<?php echo esc_url( bkb_crm_client_url( $c['id'] ) ); ?>"><?php echo esc_html( $c['name'] ? $c['name'] : ( $c['email'] ? $c['email'] : '@' . $c['instagram'] ) ); ?></a></strong></td>
					<td><?php echo bkb_crm_contacts_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td><?php echo bkb_crm_stage_badge( $c['stage'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td><?php echo esc_html( $c['source'] ? $c['source'] : '—' ); ?></td>
					<td><?php echo (int) $c['requests']; ?></td>
					<td><?php echo esc_html( bkb_crm_time( $c['last_contact'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $paged,
					'total'   => $pages,
				)
			) . '</div></div>';
		}
		?>
	</div>
	<?php
}

function bkb_crm_notice() {
	$m = isset( $_GET['bkb_msg'] ) ? sanitize_key( $_GET['bkb_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$t = array(
		'saved'   => __( 'Saved.', 'shootbook' ),
		'added'   => __( 'Added to the history.', 'shootbook' ),
		'done'    => __( 'Task marked as done.', 'shootbook' ),
		'deleted' => __( 'Client removed from the CRM.', 'shootbook' ),
	);
	if ( isset( $t[ $m ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $t[ $m ] ) . '</p></div>';
	}
}

/* ---------- client card ---------- */

function bkb_crm_event_label( $type ) {
	$m = array(
		'request' => __( 'Request', 'shootbook' ),
		'status'  => __( 'Answer to the request', 'shootbook' ),
		'stage'   => __( 'Stage', 'shootbook' ),
		'note'    => __( 'Note', 'shootbook' ),
		'task'    => __( 'Task', 'shootbook' ),
		'mail'    => __( 'E-mail', 'shootbook' ),
		'message' => __( 'Message', 'shootbook' ),
	);
	return $m[ $type ] ?? $type;
}

function bkb_crm_card( $id ) {
	$c = bkb_crm_client( $id );
	if ( ! $c ) {
		echo '<div class="wrap"><h1>' . esc_html__( 'Client not found', 'shootbook' ) . '</h1></div>';
		return;
	}
	$events = bkb_crm_events( $id );
	$tz_today = ( new DateTime( 'today', wp_timezone() ) )->format( 'Y-m-d' );
	?>
	<div class="wrap bkb-crm">
		<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bk_booking&page=bkb-clients' ) ); ?>"><?php esc_html_e( '← All clients', 'shootbook' ); ?></a></p>
		<h1 style="display:flex;gap:12px;align-items:center;flex-wrap:wrap"><?php echo esc_html( $c['name'] ? $c['name'] : ( $c['email'] ? $c['email'] : '@' . $c['instagram'] ) ); ?> <?php echo bkb_crm_stage_badge( $c['stage'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a class="page-title-action" href="<?php echo esc_url( admin_url( 'edit.php?post_type=bk_booking&page=bkb-clients&new=1&client=' . (int) $id ) ); ?>"><?php esc_html_e( 'New request from this client', 'shootbook' ); ?></a></h1>
		<p><?php echo bkb_crm_contacts_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php bkb_crm_notice(); ?>
		<div class="bkb-crm-cols">
			<div class="bkb-crm-box">
				<h2><?php esc_html_e( 'Card', 'shootbook' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="bkb_crm_save">
					<input type="hidden" name="client" value="<?php echo (int) $id; ?>">
					<?php wp_nonce_field( 'bkb_crm_save' ); ?>
					<p><label><?php esc_html_e( 'Name', 'shootbook' ); ?><br><input type="text" class="widefat" name="name" value="<?php echo esc_attr( $c['name'] ); ?>"></label></p>
					<p><label>E-mail<br><input type="email" class="widefat" name="email" value="<?php echo esc_attr( $c['email'] ); ?>"></label></p>
					<p><label>Instagram<br><input type="text" class="widefat" name="instagram" value="<?php echo esc_attr( $c['instagram'] ? '@' . $c['instagram'] : '' ); ?>" placeholder="@name"></label></p>
					<p><label><?php esc_html_e( 'Phone or WhatsApp', 'shootbook' ); ?><br><input type="text" class="widefat" name="phone" value="<?php echo esc_attr( $c['phone'] ); ?>"></label></p>
					<p><label><?php esc_html_e( 'Language to talk in', 'shootbook' ); ?><br><input type="text" class="widefat" name="lang" value="<?php echo esc_attr( $c['lang'] ); ?>" placeholder="en, ru, uk…"></label></p>
					<p><label><?php esc_html_e( 'Source', 'shootbook' ); ?><br><input type="text" class="widefat" name="source" value="<?php echo esc_attr( $c['source'] ); ?>"></label></p>
					<p><label><?php esc_html_e( 'Stage', 'shootbook' ); ?><br><select name="stage" class="widefat">
						<?php foreach ( bkb_crm_stages() as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $c['stage'], $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select></label></p>
					<p><label><?php esc_html_e( 'Why it was lost', 'shootbook' ); ?><br><input type="text" class="widefat" name="lost_reason" value="<?php echo esc_attr( $c['lost_reason'] ); ?>" placeholder="<?php esc_attr_e( 'too expensive, date taken, stopped answering…', 'shootbook' ); ?>" list="bkb-reasons"></label></p>
					<datalist id="bkb-reasons"><option value="<?php esc_attr_e( 'Too expensive', 'shootbook' ); ?>"><option value="<?php esc_attr_e( 'Date taken', 'shootbook' ); ?>"><option value="<?php esc_attr_e( 'Stopped answering', 'shootbook' ); ?>"><option value="<?php esc_attr_e( 'Changed their mind', 'shootbook' ); ?>"><option value="<?php esc_attr_e( 'Declined', 'shootbook' ); ?>"></datalist>
					<p><label><?php esc_html_e( 'Notes', 'shootbook' ); ?><br><textarea class="widefat" rows="6" name="notes"><?php echo esc_textarea( $c['notes'] ); ?></textarea></label></p>
					<p><button class="button button-primary"><?php esc_html_e( 'Save', 'shootbook' ); ?></button></p>
				</form>
				<p><a class="bkb-del" href="<?php echo esc_url( bkb_crm_action_url( 'bkb_crm_delete', array( 'client' => $id ) ) ); ?>" onclick="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Remove the client and their whole history from the CRM? This cannot be undone. The requests stay in the list of requests.', 'shootbook' ) ) ); ?>);"><?php esc_html_e( 'Remove the client from the CRM', 'shootbook' ); ?></a></p>
				<p class="description">
				<?php
				/* translators: 1: date the client first came, 2: date and time of the last contact */
				echo esc_html( sprintf( __( 'Client since %1$s, last contact %2$s.', 'shootbook' ), bkb_crm_time( $c['created'], 'j.m.Y' ), bkb_crm_time( $c['last_contact'] ) ) );
				?>
				</p>
			</div>
			<div class="bkb-crm-box bkb-crm-history">
				<h2><?php esc_html_e( 'History', 'shootbook' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bkb-crm-add">
					<input type="hidden" name="action" value="bkb_crm_add">
					<input type="hidden" name="client" value="<?php echo (int) $id; ?>">
					<?php wp_nonce_field( 'bkb_crm_add' ); ?>
					<textarea name="body" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'What happened or what to do: “Wrote on Instagram, waiting for an answer”, “Remind on Thursday”…', 'shootbook' ); ?>" required></textarea>
					<p>
						<select name="type">
							<option value="note"><?php esc_html_e( 'Note', 'shootbook' ); ?></option>
							<option value="message"><?php esc_html_e( 'Message to or from the client', 'shootbook' ); ?></option>
							<option value="task"><?php esc_html_e( 'Task', 'shootbook' ); ?></option>
						</select>
						<label><?php esc_html_e( 'due', 'shootbook' ); ?> <input type="date" name="due" min="<?php echo esc_attr( $tz_today ); ?>"></label>
						<label><input type="checkbox" name="touch" value="1" checked> <?php esc_html_e( 'this is a contact with the client', 'shootbook' ); ?></label>
						<button class="button"><?php esc_html_e( 'Add', 'shootbook' ); ?></button>
					</p>
				</form>
				<?php if ( ! $events ) : ?>
					<p><?php esc_html_e( 'Nothing yet.', 'shootbook' ); ?></p>
				<?php endif; ?>
				<ul class="bkb-timeline">
					<?php foreach ( $events as $e ) : ?>
						<li class="bkb-ev bkb-ev-<?php echo esc_attr( $e['type'] ); ?><?php echo ( 'task' === $e['type'] && $e['done'] ) ? ' bkb-ev-done' : ''; ?>">
							<div class="bkb-ev-head">
								<b><?php echo esc_html( bkb_crm_event_label( $e['type'] ) ); ?></b>
								<span><?php echo esc_html( bkb_crm_time( $e['created'] ) ); ?></span>
								<?php if ( $e['source'] ) : ?>
									<span>· <?php echo esc_html( $e['source'] ); ?></span>
								<?php endif; ?>
								<?php if ( 'task' === $e['type'] ) : ?>
									<span>· <?php esc_html_e( 'due', 'shootbook' ); ?> <?php echo esc_html( $e['due'] ? bkb_crm_time( $e['due'], 'j.m.Y' ) : __( 'not set', 'shootbook' ) ); ?></span>
									<?php if ( ! $e['done'] ) : ?>
										<a href="<?php echo esc_url( bkb_crm_action_url( 'bkb_crm_task_done', array( 'event' => $e['id'] ) ) ); ?>"><?php esc_html_e( 'Done', 'shootbook' ); ?></a>
									<?php else : ?>
										<span>· <?php esc_html_e( 'done', 'shootbook' ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
								<?php if ( $e['booking_id'] && get_post( $e['booking_id'] ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $e['booking_id'] . '&action=edit' ) ); ?>"><?php esc_html_e( 'open the request', 'shootbook' ); ?></a>
								<?php endif; ?>
							</div>
							<div class="bkb-ev-body"><?php echo nl2br( esc_html( $e['body'] ) ); ?></div>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<div class="bkb-crm-box">
			<h2><?php esc_html_e( 'Ready-made replies', 'shootbook' ); ?></h2>
			<?php bkb_replies_box( bkb_replies_latest_booking( $id ), $c ); ?>
		</div>
	</div>
	<?php
}

/* ---------- actions ---------- */

function bkb_crm_back( $id, $msg ) {
	wp_safe_redirect( add_query_arg( 'bkb_msg', $msg, $id ? bkb_crm_client_url( $id ) : admin_url( 'edit.php?post_type=bk_booking&page=bkb-clients' ) ) );
	exit;
}

add_action(
	'admin_post_bkb_crm_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'bkb_crm_save' );
		$id = (int) ( $_POST['client'] ?? 0 );
		$c  = bkb_crm_client( $id );
		if ( ! $c ) {
			wp_die( 'Not found' );
		}
		$p     = wp_unslash( $_POST );
		$email = sanitize_email( (string) ( $p['email'] ?? '' ) );
		bkb_crm_update_client(
			$id,
			array(
				'name'   => sanitize_text_field( (string) ( $p['name'] ?? '' ) ),
				'email'     => $email ? strtolower( $email ) : ( '' === trim( (string) ( $p['email'] ?? '' ) ) ? '' : $c['email'] ),
				'instagram' => bkb_crm_ig_handle( $p['instagram'] ?? '' ),
				'phone'  => sanitize_text_field( (string) ( $p['phone'] ?? '' ) ),
				'lang'   => sanitize_key( (string) ( $p['lang'] ?? '' ) ),
				'source' => sanitize_text_field( (string) ( $p['source'] ?? '' ) ),
				'notes'  => sanitize_textarea_field( (string) ( $p['notes'] ?? '' ) ),
			)
		);
		$stage = sanitize_key( (string) ( $p['stage'] ?? '' ) );
		bkb_crm_set_stage( $id, $stage, 'lost' === $stage ? sanitize_text_field( (string) ( $p['lost_reason'] ?? '' ) ) : '' );
		bkb_crm_back( $id, 'saved' );
	}
);

add_action(
	'admin_post_bkb_crm_add',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'bkb_crm_add' );
		$id   = (int) ( $_POST['client'] ?? 0 );
		$p    = wp_unslash( $_POST );
		$type = in_array( $p['type'] ?? '', array( 'note', 'message', 'task' ), true ) ? $p['type'] : 'note';
		$body = sanitize_textarea_field( (string) ( $p['body'] ?? '' ) );
		if ( ! bkb_crm_client( $id ) || '' === trim( $body ) ) {
			bkb_crm_back( $id, '' );
		}
		$extra = array();
		$due   = (string) ( $p['due'] ?? '' );
		if ( 'task' === $type && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $due ) ) {
			$d            = DateTime::createFromFormat( '!Y-m-d', $due, wp_timezone() )->setTime( 9, 0 );
			$extra['due'] = gmdate( 'Y-m-d H:i:s', $d->getTimestamp() );
		}
		bkb_crm_add_event( $id, $type, $body, $extra );
		if ( ! empty( $p['touch'] ) && 'task' !== $type ) {
			bkb_crm_update_client( $id, array( 'last_contact' => bkb_crm_now() ) );
		}
		bkb_crm_back( $id, 'added' );
	}
);

add_action(
	'admin_post_bkb_crm_task_done',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'bkb_crm_task_done' );
		global $wpdb;
		$eid = (int) ( $_GET['event'] ?? 0 );
		$cid = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT client_id FROM ' . bkb_crm_table( 'events' ) . ' WHERE id = %d', $eid ) ); // phpcs:ignore WordPress.DB
		$wpdb->update( bkb_crm_table( 'events' ), array( 'done' => 1 ), array( 'id' => $eid ) ); // phpcs:ignore WordPress.DB
		bkb_crm_back( 'list' === ( $_GET['back'] ?? '' ) ? 0 : $cid, 'done' );
	}
);

/* ---------- links from the requests ---------- */

add_filter(
	'manage_bk_booking_posts_columns',
	function ( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'bkb_who' === $k ) {
				$out['bkb_client'] = __( 'Client', 'shootbook' );
			}
		}
		return $out;
	},
	20
);

add_action(
	'manage_bk_booking_posts_custom_column',
	function ( $col, $id ) {
		if ( 'bkb_client' !== $col ) {
			return;
		}
		$cid = (int) get_post_meta( $id, '_bkb_client', true );
		$c   = $cid ? bkb_crm_client( $cid ) : null;
		if ( $c ) {
			echo '<a href="' . esc_url( bkb_crm_client_url( $cid ) ) . '">' . esc_html__( 'card', 'shootbook' ) . '</a><br>' . bkb_crm_stage_badge( $c['stage'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	},
	10,
	2
);

add_action(
	'add_meta_boxes_bk_booking',
	function ( $post ) {
		$cid = (int) get_post_meta( $post->ID, '_bkb_client', true );
		if ( ! $cid || ! bkb_crm_client( $cid ) ) {
			return;
		}
		add_meta_box(
			'bkb_client',
			__( 'Client', 'shootbook' ),
			function () use ( $cid ) {
				$c = bkb_crm_client( $cid );
				echo '<p><b>' . esc_html( $c['name'] ) . '</b><br>' . bkb_crm_stage_badge( $c['stage'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<p>' . esc_html__( 'Source:', 'shootbook' ) . ' ' . esc_html( $c['source'] ? $c['source'] : '—' ) . '</p>';
				echo '<p><a class="button" href="' . esc_url( bkb_crm_client_url( $cid ) ) . '">' . esc_html__( 'Open the card', 'shootbook' ) . '</a></p>';
			},
			'bk_booking',
			'side'
		);
	}
);

/* ---------- report ---------- */

function bkb_crm_report_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$et     = bkb_crm_table( 'events' );
	$ct     = bkb_crm_table( 'clients' );
	$days   = (int) ( $_GET['days'] ?? 90 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$days   = in_array( $days, array( 30, 90, 365, 0 ), true ) ? $days : 90;
	$since  = $days ? gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) : '1970-01-01 00:00:00';
	$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB
		$wpdb->prepare(
			"SELECT IF(r.source = '', %s, r.source) AS src, COUNT(*) AS requests,
				SUM(EXISTS(SELECT 1 FROM $et s WHERE s.booking_id = r.booking_id AND s.booking_id > 0 AND s.type = 'status' AND (s.body LIKE %s OR s.body LIKE %s OR s.body LIKE %s)) OR EXISTS(SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = r.booking_id AND r.booking_id > 0 AND pm.meta_key = '_bkb_status' AND pm.meta_value = 'confirmed')) AS confirmed,
				COUNT(DISTINCT r.client_id) AS clients
			FROM $et r WHERE r.type = 'request' AND r.created >= %s GROUP BY src ORDER BY requests DESC", // phpcs:ignore WordPress.DB
			__( 'Unknown', 'shootbook' ),
			'%подтвержд%', // Written by versions before the translation.
			'%Request confirmed%',
			'%' . $wpdb->esc_like( __( 'Request confirmed', 'shootbook' ) ) . '%',
			$since
		),
		ARRAY_A
	);
	$stages = $wpdb->get_results( $wpdb->prepare( "SELECT stage, COUNT(*) n FROM $ct WHERE last_contact >= %s GROUP BY stage", $since ), OBJECT_K ); // phpcs:ignore WordPress.DB
	$lost   = $wpdb->get_results( $wpdb->prepare( "SELECT IF(lost_reason = '', %s, lost_reason) r, COUNT(*) n FROM $ct WHERE stage = 'lost' AND last_contact >= %s GROUP BY r ORDER BY n DESC", __( 'No reason', 'shootbook' ), $since ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$base   = admin_url( 'edit.php?post_type=bk_booking&page=bkb-report' );
	?>
	<div class="wrap bkb-crm">
		<h1><?php esc_html_e( 'Requests report', 'shootbook' ); ?></h1>
		<ul class="subsubsub">
			<?php foreach ( array( 30 => __( '30 days', 'shootbook' ), 90 => __( '90 days', 'shootbook' ), 365 => __( 'Year', 'shootbook' ), 0 => __( 'All time', 'shootbook' ) ) as $k => $label ) : ?>
				<li><a href="<?php echo esc_url( add_query_arg( 'days', $k, $base ) ); ?>" class="<?php echo $days === $k ? 'current' : ''; ?>"><?php echo esc_html( $label ); ?></a> <?php echo 0 === $k ? '' : '|'; ?></li>
			<?php endforeach; ?>
		</ul>
		<br class="clear">
		<h2><?php esc_html_e( 'Where requests come from', 'shootbook' ); ?></h2>
		<table class="wp-list-table widefat striped" style="max-width:760px">
			<thead><tr><th><?php esc_html_e( 'Source', 'shootbook' ); ?></th><th><?php echo esc_html_x( 'Requests', 'number of requests', 'shootbook' ); ?></th><th><?php echo esc_html_x( 'Clients', 'number of clients', 'shootbook' ); ?></th><th><?php esc_html_e( 'Confirmed', 'shootbook' ); ?></th><th><?php esc_html_e( 'Share confirmed', 'shootbook' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No requests in this period.', 'shootbook' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><?php echo esc_html( $r['src'] ); ?></td>
						<td><?php echo (int) $r['requests']; ?></td>
						<td><?php echo (int) $r['clients']; ?></td>
						<td><?php echo (int) $r['confirmed']; ?></td>
						<td><?php echo (int) $r['requests'] ? (int) round( 100 * $r['confirmed'] / $r['requests'] ) . '%' : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Source is where the person first came to the site from: UTM tags in the link or the site they followed a link on. “Direct visit” means they opened the site themselves or the browser hid the source. Requests that came before tracking was on have no source.', 'shootbook' ); ?></p>
		<h2><?php esc_html_e( 'Clients by stage', 'shootbook' ); ?></h2>
		<table class="wp-list-table widefat striped" style="max-width:460px">
			<tbody>
				<?php foreach ( bkb_crm_stages() as $k => $label ) : ?>
					<tr><td><?php echo bkb_crm_stage_badge( $k ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo (int) ( $stages[ $k ]->n ?? 0 ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $lost ) : ?>
			<h2><?php esc_html_e( 'Why requests are lost', 'shootbook' ); ?></h2>
			<table class="wp-list-table widefat striped" style="max-width:460px">
				<tbody>
					<?php foreach ( $lost as $l ) : ?>
						<tr><td><?php echo esc_html( $l['r'] ); ?></td><td><?php echo (int) $l['n']; ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/* ---------- contacts and manual requests (Instagram, phone, in person) ---------- */

function bkb_crm_contacts_html( $c ) {
	$out = array();
	if ( $c['instagram'] ) {
		$out[] = '<a href="' . esc_url( 'https://instagram.com/' . $c['instagram'] ) . '" target="_blank" rel="noopener">@' . esc_html( $c['instagram'] ) . '</a>';
	}
	if ( $c['email'] ) {
		$out[] = '<a href="mailto:' . esc_attr( $c['email'] ) . '">' . esc_html( $c['email'] ) . '</a>';
	}
	if ( $c['phone'] ) {
		$out[] = esc_html( $c['phone'] );
	}
	return implode( '<br>', $out );
}

/** Sources to pick from when a client is added by hand: the standard ones, then the studio's own from the settings. */
function bkb_crm_manual_sources() {
	$list   = array( 'Instagram', 'Facebook', 'WhatsApp', 'Telegram', __( 'Phone', 'shootbook' ), 'E-mail', __( 'In person', 'shootbook' ), __( 'Recommendation', 'shootbook' ) );
	$crm    = (array) bkb_cfg( 'crm' );
	$own    = preg_split( '/\R/', (string) ( $crm['sources'] ?? '' ) );
	$list   = array_merge( $list, array_filter( array_map( 'trim', $own ), 'strlen' ) );
	$list[] = __( 'Other', 'shootbook' );
	return array_values( array_unique( $list ) );
}

function bkb_crm_new_form( $client_id ) {
	$c     = $client_id ? bkb_crm_client( $client_id ) : null;
	$cfg   = bkb_cfg();
	$hours = bkb_durations();
	$def   = bkb_valid_duration( $cfg['schedule']['default_duration'] ) ?? $hours[0];
	$err   = isset( $_GET['bkb_err'] ) ? sanitize_key( $_GET['bkb_err'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$v     = fn( $k ) => $c ? (string) $c[ $k ] : '';
	?>
	<div class="wrap bkb-crm">
		<p><a href="<?php echo esc_url( $c ? bkb_crm_client_url( $client_id ) : admin_url( 'edit.php?post_type=bk_booking&page=bkb-clients' ) ); ?>">← <?php echo esc_html( $c ? __( 'Client card', 'shootbook' ) : __( 'All clients', 'shootbook' ) ); ?></a></p>
		<h1><?php echo esc_html( $c ? __( 'New request:', 'shootbook' ) . ' ' . $c['name'] : __( 'New request by hand', 'shootbook' ) ); ?></h1>
		<?php if ( 'contact' === $err ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Enter a name and at least one contact: Instagram, e-mail or phone.', 'shootbook' ); ?></p></div>
		<?php elseif ( 'date' === $err ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Could not read the date or time.', 'shootbook' ); ?></p></div>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'For requests that did not come through the site: from Instagram, by phone, in person. If the client is already known (same Instagram or e-mail), the request goes to their card. No e-mails are sent to the client.', 'shootbook' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bkb-crm-box bkb-crm-new">
			<input type="hidden" name="action" value="bkb_crm_new">
			<input type="hidden" name="client" value="<?php echo (int) $client_id; ?>">
			<?php wp_nonce_field( 'bkb_crm_new' ); ?>
			<h2><?php esc_html_e( 'Client', 'shootbook' ); ?></h2>
			<div class="bkb-grid">
				<label><?php esc_html_e( 'Name', 'shootbook' ); ?> *<input type="text" name="name" required value="<?php echo esc_attr( $v( 'name' ) ); ?>"></label>
				<label>Instagram<input type="text" name="instagram" placeholder="<?php esc_attr_e( '@name or a link to the profile', 'shootbook' ); ?>" value="<?php echo esc_attr( $c && $c['instagram'] ? '@' . $c['instagram'] : '' ); ?>"></label>
				<label>E-mail<input type="email" name="email" value="<?php echo esc_attr( $v( 'email' ) ); ?>"></label>
				<label><?php esc_html_e( 'Phone or WhatsApp', 'shootbook' ); ?><input type="text" name="phone" value="<?php echo esc_attr( $v( 'phone' ) ); ?>"></label>
				<label><?php esc_html_e( 'Where they came from', 'shootbook' ); ?>
					<select name="source">
						<?php foreach ( bkb_crm_manual_sources() as $s ) : ?>
							<option <?php selected( $c ? $c['source'] : 'Instagram', $s ); ?>><?php echo esc_html( $s ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php $slangs = (array) ( $cfg['fields']['slang']['choices'] ?? array() ); ?>
				<label><?php esc_html_e( 'Language to talk in', 'shootbook' ); ?>
					<select name="lang">
						<option value="">—</option>
						<?php foreach ( $slangs ? $slangs : array_map( fn( $l ) => array( 'id' => $l, 'label' => array() ), bkb_langs() ) as $l ) : ?>
							<option value="<?php echo esc_attr( $l['id'] ); ?>" <?php selected( $v( 'lang' ), $l['id'] ); ?>><?php echo esc_html( bkb_l( $l['label'], bkb_admin_lang(), $l['id'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<h2><?php esc_html_e( 'What they want', 'shootbook' ); ?></h2>
			<div class="bkb-grid">
				<?php if ( $cfg['services'] ) : ?>
					<label><?php esc_html_e( 'Service', 'shootbook' ); ?>
						<select name="service">
							<option value=""><?php esc_html_e( 'not decided', 'shootbook' ); ?></option>
							<?php foreach ( $cfg['services'] as $s ) : ?>
								<option value="<?php echo esc_attr( $s['id'] ); ?>"><?php echo esc_html( bkb_service_label( $s['id'], bkb_admin_lang() ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
				<?php if ( $cfg['locations'] ) : ?>
					<label><?php esc_html_e( 'Location', 'shootbook' ); ?>
						<select name="location">
							<?php foreach ( $cfg['locations'] as $l ) : ?>
								<option value="<?php echo esc_attr( $l['id'] ); ?>"><?php echo esc_html( bkb_location_label( $l['id'], bkb_admin_lang() ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
				<label><?php esc_html_e( 'Date', 'shootbook' ); ?><input type="date" name="date"></label>
				<label><?php esc_html_e( 'Start', 'shootbook' ); ?><input type="time" name="time" step="900"></label>
				<label><?php esc_html_e( 'Duration', 'shootbook' ); ?>
					<select name="hours">
						<?php foreach ( $hours as $h ) : ?>
							<option value="<?php echo esc_attr( $h ); ?>" <?php selected( (string) $def, (string) $h ); ?>><?php echo esc_html( sprintf( /* translators: %s: number of hours */ __( '%s h', 'shootbook' ), $h ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Price', 'shootbook' ); ?><input type="text" name="price" placeholder="<?php esc_attr_e( 'for example 630 €', 'shootbook' ); ?>"></label>
			</div>
			<p><label><input type="checkbox" name="block" value="1" checked> <?php esc_html_e( 'If a date and time are given, hold this time in the calendar on the site', 'shootbook' ); ?></label></p>
			<p><label><?php esc_html_e( 'What the client wrote or what you agreed', 'shootbook' ); ?><textarea name="msg" rows="5" class="widefat" placeholder="<?php esc_attr_e( 'You can simply paste the Instagram chat', 'shootbook' ); ?>"></textarea></label></p>
			<div class="bkb-grid">
				<label><?php esc_html_e( 'Stage', 'shootbook' ); ?>
					<select name="stage">
						<?php foreach ( bkb_crm_stages() as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( 'new', $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Task for later', 'shootbook' ); ?><input type="text" name="task" placeholder="<?php esc_attr_e( 'for example: answer when they name the date', 'shootbook' ); ?>"></label>
				<label><?php esc_html_e( 'Task due', 'shootbook' ); ?><input type="date" name="task_due"></label>
			</div>
			<p><button class="button button-primary button-large"><?php esc_html_e( 'Save the request', 'shootbook' ); ?></button></p>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_bkb_crm_new',
	function () {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'bkb_crm_new' );
		$p     = wp_unslash( $_POST );
		$cid   = (int) ( $p['client'] ?? 0 );
		$back  = admin_url( 'edit.php?post_type=bk_booking&page=bkb-clients&new=1' . ( $cid ? '&client=' . $cid : '' ) );
		$name  = sanitize_text_field( (string) ( $p['name'] ?? '' ) );
		$ig    = bkb_crm_ig_handle( $p['instagram'] ?? '' );
		$email = strtolower( sanitize_email( (string) ( $p['email'] ?? '' ) ) );
		$phone = sanitize_text_field( (string) ( $p['phone'] ?? '' ) );
		$src   = in_array( $p['source'] ?? '', bkb_crm_manual_sources(), true ) ? $p['source'] : __( 'Other', 'shootbook' );
		$lang  = sanitize_key( (string) ( $p['lang'] ?? '' ) );
		$stage = isset( bkb_crm_stages()[ $p['stage'] ?? '' ] ) ? $p['stage'] : 'new';
		if ( '' === $name || ( '' === $ig && '' === $email && '' === $phone ) ) {
			wp_safe_redirect( add_query_arg( 'bkb_err', 'contact', $back ) );
			exit;
		}
		$svc   = bkb_service( (string) ( $p['service'] ?? '' ) ) ? (string) $p['service'] : '';
		$loc   = bkb_location( (string) ( $p['location'] ?? '' ) ) ? (string) $p['location'] : '';
		$hours = bkb_valid_duration( $p['hours'] ?? 0 ) ?? bkb_durations()[0];
		$date  = (string) ( $p['date'] ?? '' );
		$time  = (string) ( $p['time'] ?? '' );
		$start = null;
		if ( '' !== $date || '' !== $time ) {
			$start = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' ' . ( '' !== $time ? substr( $time, 0, 5 ) : '10:00' ), wp_timezone() );
			if ( ! $start || '' === $date ) {
				wp_safe_redirect( add_query_arg( 'bkb_err', 'date', $back ) );
				exit;
			}
		}

		// The client: the one we came from, or the same Instagram / e-mail, or a new one.
		$c = $cid ? bkb_crm_client( $cid ) : null;
		if ( ! $c && $ig ) {
			$c = bkb_crm_client_by_instagram( $ig );
		}
		if ( ! $c && $email ) {
			$c = bkb_crm_client_by_email( $email );
		}
		$now = bkb_crm_now();
		if ( $c ) {
			$cid  = (int) $c['id'];
			$data = array(
				'name'         => $name,
				'last_contact' => $now,
			);
			foreach ( array( 'instagram' => $ig, 'email' => $email, 'phone' => $phone, 'lang' => $lang ) as $k => $val ) {
				if ( '' !== $val ) {
					$data[ $k ] = $val;
				}
			}
			if ( '' === $c['source'] ) {
				$data['source'] = $src;
			}
			bkb_crm_update_client( $cid, $data );
		} else {
			$wpdb->insert( // phpcs:ignore WordPress.DB
				bkb_crm_table( 'clients' ),
				array(
					'email'        => $email,
					'instagram'    => $ig,
					'name'         => $name,
					'phone'        => $phone,
					'lang'         => $lang,
					'source'       => $src,
					'stage'        => 'new',
					'lost_reason'  => '',
					'notes'        => '',
					'created'      => $now,
					'last_contact' => $now,
				)
			);
			$cid = (int) $wpdb->insert_id;
		}

		// A request with a date also goes to the requests list and can hold the time in the calendar.
		$bid = 0;
		$msg = sanitize_textarea_field( (string) ( $p['msg'] ?? '' ) );
		if ( $start && ! empty( $p['block'] ) ) {
			$status = in_array( $stage, array( 'confirmed', 'prepaid', 'done', 'photos', 'review' ), true ) ? 'confirmed' : ( 'lost' === $stage ? 'declined' : 'pending' );
			$bid    = wp_insert_post(
				array(
					'post_type'   => 'bk_booking',
					'post_status' => 'publish',
					'post_title'  => trim( sprintf( '%s — %s — %s (%s)', $start->format( 'Y-m-d H:i' ), $svc ? bkb_service_label( $svc, 'en' ) : bkb_site_name(), $name, $src ) ),
				)
			);
			if ( $bid && ! is_wp_error( $bid ) ) {
				$meta = array(
					'date'     => $start->format( 'Y-m-d' ),
					'time'     => $start->format( 'H:i' ),
					'service'  => $svc,
					'location' => $loc,
					'hours'    => $hours,
					'opts'     => array(),
					'price'    => sanitize_text_field( (string) ( $p['price'] ?? '' ) ),
					'name'     => $name,
					'email'    => $email,
					'phone'    => trim( ( $ig ? '@' . $ig . ' ' : '' ) . $phone ),
					'details'  => '',
					'slang'    => $lang,
					'msg'      => $msg,
					'ui'       => in_array( $lang, bkb_langs(), true ) ? $lang : bkb_langs()[0],
					'page'     => '',
					'start'    => $start->getTimestamp(),
					'end'      => $start->getTimestamp() + bkb_block_seconds( $hours ),
					'status'   => $status,
					'manual'   => $src,
					'client'   => $cid,
				);
				foreach ( $meta as $k => $val ) {
					update_post_meta( $bid, '_bkb_' . $k, $val );
				}
			} else {
				$bid = 0;
			}
		}
		if ( $bid ) {
			$body = bkb_summary( bkb_get( $bid ), bkb_admin_lang(), false );
		} else {
			$lines = array();
			if ( $start ) {
				/* translators: %s: date and time */
				$lines[] = sprintf( __( 'Date: %s (the time is not held in the calendar)', 'shootbook' ), $start->format( 'd.m.Y H:i' ) );
			}
			if ( $svc ) {
				$lines[] = __( 'Service:', 'shootbook' ) . ' ' . bkb_service_label( $svc, bkb_admin_lang() );
			}
			if ( $loc && ( $start || $svc ) ) {
				$lines[] = __( 'Location:', 'shootbook' ) . ' ' . bkb_location_label( $loc, bkb_admin_lang() );
			}
			if ( $start || $svc ) {
				/* translators: %s: number of hours */
				$lines[] = sprintf( __( 'Duration: %s h', 'shootbook' ), $hours );
			}
			if ( '' !== (string) ( $p['price'] ?? '' ) ) {
				$lines[] = __( 'Price:', 'shootbook' ) . ' ' . sanitize_text_field( (string) $p['price'] );
			}
			$body = implode( "\n", $lines );
		}
		$body = trim( $body . ( $msg ? "\n\n" . $msg : '' ) );
		$body = '' === $body ? __( 'Request without details', 'shootbook' ) : $body;
		bkb_crm_add_event( $cid, 'request', $body, array( 'booking_id' => (int) $bid, 'source' => $src ) );
		bkb_crm_set_stage( $cid, $stage );
		$task = sanitize_text_field( (string) ( $p['task'] ?? '' ) );
		if ( '' !== $task ) {
			$extra = array();
			$due   = (string) ( $p['task_due'] ?? '' );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $due ) ) {
				$extra['due'] = gmdate( 'Y-m-d H:i:s', DateTime::createFromFormat( '!Y-m-d', $due, wp_timezone() )->setTime( 9, 0 )->getTimestamp() );
			}
			bkb_crm_add_event( $cid, 'task', $task, $extra );
		}
		bkb_crm_back( $cid, 'added' );
	}
);

/* Remove a client and the history (for test entries or when a client asks to delete their data). */
add_action(
	'admin_post_bkb_crm_delete',
	function () {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'bkb_crm_delete' );
		$id = (int) ( $_GET['client'] ?? 0 );
		if ( $id ) {
			$wpdb->delete( bkb_crm_table( 'events' ), array( 'client_id' => $id ) ); // phpcs:ignore WordPress.DB
			$wpdb->delete( bkb_crm_table( 'clients' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
			$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_bkb_client', 'meta_value' => (string) $id ) ); // phpcs:ignore WordPress.DB
		}
		wp_safe_redirect( add_query_arg( 'bkb_msg', 'deleted', admin_url( 'edit.php?post_type=bk_booking&page=bkb-clients' ) ) );
		exit;
	}
);
