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
		add_submenu_page( 'edit.php?post_type=bk_booking', 'Клиенты', 'Клиенты', 'manage_options', 'bkb-clients', 'bkb_crm_page' );
		add_submenu_page( 'edit.php?post_type=bk_booking', 'Отчёт по заявкам', 'Отчёт', 'manage_options', 'bkb-report', 'bkb_crm_report_page' );
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
		$where .= ' AND (c.name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s OR c.notes LIKE %s)';
		array_push( $args, $like, $like, $like, $like );
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
		<h1>Клиенты</h1>
		<?php bkb_crm_notice(); ?>
		<?php if ( $tasks ) : ?>
			<div class="bkb-crm-box bkb-crm-tasks">
				<h2>Задачи на сегодня</h2>
				<ul>
					<?php foreach ( $tasks as $t ) : ?>
						<?php $late = $t['due'] && strtotime( $t['due'] . ' UTC' ) < ( new DateTime( 'today', wp_timezone() ) )->getTimestamp(); ?>
						<li>
							<span class="<?php echo $late ? 'bkb-late' : ''; ?>"><?php echo esc_html( $t['due'] ? bkb_crm_time( $t['due'], 'j.m' ) : 'без срока' ); ?></span>
							<?php echo esc_html( $t['body'] ); ?>
							<?php if ( $t['client_id'] ) : ?>
								— <a href="<?php echo esc_url( bkb_crm_client_url( $t['client_id'] ) ); ?>"><?php echo esc_html( $t['name'] ); ?></a>
							<?php endif; ?>
							<a class="bkb-done" href="<?php echo esc_url( bkb_crm_action_url( 'bkb_crm_task_done', array( 'event' => $t['id'], 'back' => 'list' ) ) ); ?>">Готово</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<ul class="subsubsub">
			<li><a href="<?php echo esc_url( $base ); ?>" class="<?php echo '' === $stage ? 'current' : ''; ?>">Все <span class="count">(<?php echo (int) $all; ?>)</span></a></li>
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
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Имя, e-mail, телефон, заметки">
			<button class="button">Найти</button>
		</form>
		<br class="clear">
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th style="width:24%">Клиент</th><th>Контакты</th><th style="width:15%">Этап</th><th style="width:15%">Источник</th><th style="width:8%">Заявок</th><th style="width:13%">Последний контакт</th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="6">Клиентов пока нет. Они появляются сами, когда приходит заявка с сайта.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $c ) : ?>
				<tr>
					<td><strong><a href="<?php echo esc_url( bkb_crm_client_url( $c['id'] ) ); ?>"><?php echo esc_html( $c['name'] ? $c['name'] : $c['email'] ); ?></a></strong></td>
					<td><a href="mailto:<?php echo esc_attr( $c['email'] ); ?>"><?php echo esc_html( $c['email'] ); ?></a><?php echo $c['phone'] ? '<br>' . esc_html( $c['phone'] ) : ''; ?></td>
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
		'saved' => 'Сохранено.',
		'added' => 'Добавлено в историю.',
		'done'  => 'Задача отмечена выполненной.',
	);
	if ( isset( $t[ $m ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $t[ $m ] ) . '</p></div>';
	}
}

/* ---------- client card ---------- */

function bkb_crm_event_label( $type ) {
	$m = array(
		'request' => 'Заявка с сайта',
		'status'  => 'Ответ по заявке',
		'stage'   => 'Этап',
		'note'    => 'Заметка',
		'task'    => 'Задача',
		'mail'    => 'Письмо',
		'message' => 'Сообщение',
	);
	return $m[ $type ] ?? $type;
}

function bkb_crm_card( $id ) {
	$c = bkb_crm_client( $id );
	if ( ! $c ) {
		echo '<div class="wrap"><h1>Клиент не найден</h1></div>';
		return;
	}
	$events = bkb_crm_events( $id );
	$tz_today = ( new DateTime( 'today', wp_timezone() ) )->format( 'Y-m-d' );
	?>
	<div class="wrap bkb-crm">
		<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bk_booking&page=bkb-clients' ) ); ?>">← Все клиенты</a></p>
		<h1><?php echo esc_html( $c['name'] ? $c['name'] : $c['email'] ); ?> <?php echo bkb_crm_stage_badge( $c['stage'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1>
		<?php bkb_crm_notice(); ?>
		<div class="bkb-crm-cols">
			<div class="bkb-crm-box">
				<h2>Карточка</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="bkb_crm_save">
					<input type="hidden" name="client" value="<?php echo (int) $id; ?>">
					<?php wp_nonce_field( 'bkb_crm_save' ); ?>
					<p><label>Имя<br><input type="text" class="widefat" name="name" value="<?php echo esc_attr( $c['name'] ); ?>"></label></p>
					<p><label>E-mail<br><input type="email" class="widefat" name="email" value="<?php echo esc_attr( $c['email'] ); ?>"></label></p>
					<p><label>Телефон или Instagram<br><input type="text" class="widefat" name="phone" value="<?php echo esc_attr( $c['phone'] ); ?>"></label></p>
					<p><label>Язык общения<br><input type="text" class="widefat" name="lang" value="<?php echo esc_attr( $c['lang'] ); ?>" placeholder="en, ru, uk…"></label></p>
					<p><label>Источник<br><input type="text" class="widefat" name="source" value="<?php echo esc_attr( $c['source'] ); ?>"></label></p>
					<p><label>Этап<br><select name="stage" class="widefat">
						<?php foreach ( bkb_crm_stages() as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $c['stage'], $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select></label></p>
					<p><label>Почему потеряна<br><input type="text" class="widefat" name="lost_reason" value="<?php echo esc_attr( $c['lost_reason'] ); ?>" placeholder="дорого, дата занята, пропал…" list="bkb-reasons"></label></p>
					<datalist id="bkb-reasons"><option value="Дорого"><option value="Дата занята"><option value="Пропал, не отвечает"><option value="Передумал"><option value="Отклонена"></datalist>
					<p><label>Заметки<br><textarea class="widefat" rows="6" name="notes"><?php echo esc_textarea( $c['notes'] ); ?></textarea></label></p>
					<p><button class="button button-primary">Сохранить</button></p>
				</form>
				<p class="description">Клиент с нами с <?php echo esc_html( bkb_crm_time( $c['created'], 'j.m.Y' ) ); ?>, последний контакт <?php echo esc_html( bkb_crm_time( $c['last_contact'] ) ); ?>.</p>
			</div>
			<div class="bkb-crm-box bkb-crm-history">
				<h2>История</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bkb-crm-add">
					<input type="hidden" name="action" value="bkb_crm_add">
					<input type="hidden" name="client" value="<?php echo (int) $id; ?>">
					<?php wp_nonce_field( 'bkb_crm_add' ); ?>
					<textarea name="body" rows="3" class="widefat" placeholder="Что было или что нужно сделать: «Написала в Instagram, ждёт ответа от мужа», «Напомнить в четверг»…" required></textarea>
					<p>
						<select name="type">
							<option value="note">Заметка</option>
							<option value="message">Сообщение клиенту или от клиента</option>
							<option value="task">Задача</option>
						</select>
						<label>срок <input type="date" name="due" min="<?php echo esc_attr( $tz_today ); ?>"></label>
						<label><input type="checkbox" name="touch" value="1" checked> это контакт с клиентом</label>
						<button class="button">Добавить</button>
					</p>
				</form>
				<?php if ( ! $events ) : ?>
					<p>Пока пусто.</p>
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
									<span>· срок <?php echo esc_html( $e['due'] ? bkb_crm_time( $e['due'], 'j.m.Y' ) : 'не указан' ); ?></span>
									<?php if ( ! $e['done'] ) : ?>
										<a href="<?php echo esc_url( bkb_crm_action_url( 'bkb_crm_task_done', array( 'event' => $e['id'] ) ) ); ?>">Готово</a>
									<?php else : ?>
										<span>· выполнено</span>
									<?php endif; ?>
								<?php endif; ?>
								<?php if ( $e['booking_id'] && get_post( $e['booking_id'] ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $e['booking_id'] . '&action=edit' ) ); ?>">открыть заявку</a>
								<?php endif; ?>
							</div>
							<div class="bkb-ev-body"><?php echo nl2br( esc_html( $e['body'] ) ); ?></div>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
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
				'email'  => $email ? strtolower( $email ) : $c['email'],
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
				$out['bkb_client'] = 'Клиент';
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
			echo '<a href="' . esc_url( bkb_crm_client_url( $cid ) ) . '">карточка</a><br>' . bkb_crm_stage_badge( $c['stage'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
			'Клиент',
			function () use ( $cid ) {
				$c = bkb_crm_client( $cid );
				echo '<p><b>' . esc_html( $c['name'] ) . '</b><br>' . bkb_crm_stage_badge( $c['stage'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<p>Источник: ' . esc_html( $c['source'] ? $c['source'] : '—' ) . '</p>';
				echo '<p><a class="button" href="' . esc_url( bkb_crm_client_url( $cid ) ) . '">Открыть карточку</a></p>';
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
			"SELECT IF(r.source = '', 'Неизвестно', r.source) AS src, COUNT(*) AS requests,
				SUM(EXISTS(SELECT 1 FROM $et s WHERE s.booking_id = r.booking_id AND s.booking_id > 0 AND s.type = 'status' AND s.body LIKE %s) OR EXISTS(SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = r.booking_id AND r.booking_id > 0 AND pm.meta_key = '_bkb_status' AND pm.meta_value = 'confirmed')) AS confirmed,
				COUNT(DISTINCT r.client_id) AS clients
			FROM $et r WHERE r.type = 'request' AND r.created >= %s GROUP BY src ORDER BY requests DESC", // phpcs:ignore WordPress.DB
			'%подтвержд%',
			$since
		),
		ARRAY_A
	);
	$stages = $wpdb->get_results( $wpdb->prepare( "SELECT stage, COUNT(*) n FROM $ct WHERE last_contact >= %s GROUP BY stage", $since ), OBJECT_K ); // phpcs:ignore WordPress.DB
	$lost   = $wpdb->get_results( $wpdb->prepare( "SELECT IF(lost_reason = '', 'Без причины', lost_reason) r, COUNT(*) n FROM $ct WHERE stage = 'lost' AND last_contact >= %s GROUP BY r ORDER BY n DESC", $since ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$base   = admin_url( 'edit.php?post_type=bk_booking&page=bkb-report' );
	?>
	<div class="wrap bkb-crm">
		<h1>Отчёт по заявкам</h1>
		<ul class="subsubsub">
			<?php foreach ( array( 30 => '30 дней', 90 => '90 дней', 365 => 'Год', 0 => 'Всё время' ) as $k => $label ) : ?>
				<li><a href="<?php echo esc_url( add_query_arg( 'days', $k, $base ) ); ?>" class="<?php echo $days === $k ? 'current' : ''; ?>"><?php echo esc_html( $label ); ?></a> <?php echo 0 === $k ? '' : '|'; ?></li>
			<?php endforeach; ?>
		</ul>
		<br class="clear">
		<h2>Откуда приходят заявки</h2>
		<table class="wp-list-table widefat striped" style="max-width:760px">
			<thead><tr><th>Источник</th><th>Заявок</th><th>Клиентов</th><th>Подтверждено</th><th>Доля подтверждённых</th></tr></thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5">За этот период заявок нет.</td></tr>
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
		<p class="description">Источник — откуда человек впервые пришёл на сайт: метки UTM в ссылке или сайт, с которого он перешёл. «Прямой заход» — открыл сайт сам или источник скрыт браузером. У заявок, пришедших до включения учёта, источник неизвестен.</p>
		<h2>Клиенты по этапам</h2>
		<table class="wp-list-table widefat striped" style="max-width:460px">
			<tbody>
				<?php foreach ( bkb_crm_stages() as $k => $label ) : ?>
					<tr><td><?php echo bkb_crm_stage_badge( $k ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo (int) ( $stages[ $k ]->n ?? 0 ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $lost ) : ?>
			<h2>Почему теряем</h2>
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
