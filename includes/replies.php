<?php
/**
 * Ready-made replies: message templates in every form language, filled with the
 * details of a request. Shown under a request and in the client card, in the
 * client's language with the admin's language underneath as a translation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The language the admin reads the translation in. */
function bkb_replies_admin_lang() {
	return (string) apply_filters( 'bkb_replies_admin_lang', bkb_admin_lang() );
}

function bkb_default_replies() {
	return array(
		array(
			'id'    => 'free',
			'title' => __( 'The time is free', 'shootbook' ),
			'text'  => array(
				'ru' => "Здравствуйте, {name}!\nСпасибо за заявку. Это время свободно, держим его за вами:\n\n{service}\n{date}, {time}–{end}\n{location}\n{address}\nСтоимость: {price}\n\nЕсли всё верно, напишите «да», и мы подтвердим запись.",
				'en' => "Hello {name}!\nThank you for your request. This time is free and we are holding it for you:\n\n{service}\n{date}, {time}–{end}\n{location}\n{address}\nPrice: {price}\n\nIf everything is correct, just reply \"yes\" and we will confirm the booking.",
				'de' => "Hallo {name},\nvielen Dank für Ihre Anfrage. Der Termin ist frei, wir halten ihn für Sie fest:\n\n{service}\n{date}, {time}–{end}\n{location}\n{address}\nPreis: {price}\n\nWenn alles passt, antworten Sie einfach mit „Ja“, dann bestätigen wir die Buchung.",
				'hu' => "Kedves {name}!\nKöszönjük a foglalási kérést. Ez az időpont szabad, fenntartjuk Önnek:\n\n{service}\n{date}, {time}–{end}\n{location}\n{address}\nÁr: {price}\n\nHa minden rendben van, válaszoljon egy „igen”-nel, és visszaigazoljuk a foglalást.",
				'uk' => "Вітаю, {name}!\nДякуємо за заявку. Цей час вільний, тримаємо його для вас:\n\n{service}\n{date}, {time}–{end}\n{location}\n{address}\nВартість: {price}\n\nЯкщо все правильно, напишіть «так», і ми підтвердимо запис.",
			),
		),
		array(
			'id'    => 'confirmed',
			'title' => __( 'Booking confirmed', 'shootbook' ),
			'text'  => array(
				'ru' => "Здравствуйте, {name}!\nВаша запись подтверждена:\n\n{service}\n{date}, {time}–{end}\n{location}\n{address}\nВремя включает подготовку: {prep}.\n\nПожалуйста, приходите вовремя. Если планы изменятся, напишите нам заранее, и мы перенесём запись.\nДо встречи!",
				'en' => "Hello {name}!\nYour booking is confirmed:\n\n{service}\n{date}, {time}–{end}\n{location}\n{address}\nThe time includes {prep} of preparation.\n\nPlease arrive on time. If your plans change, let us know in advance and we will reschedule.\nSee you soon!",
				'de' => "Hallo {name},\nIhre Buchung ist bestätigt:\n\n{service}\n{date}, {time}–{end}\n{location}\n{address}\nDie Zeit beinhaltet {prep} Vorbereitung.\n\nBitte kommen Sie pünktlich. Falls sich Ihre Pläne ändern, geben Sie uns bitte rechtzeitig Bescheid, dann verschieben wir den Termin.\nBis bald!",
				'hu' => "Kedves {name}!\nA foglalását visszaigazoltuk:\n\n{service}\n{date}, {time}–{end}\n{location}\n{address}\nAz időpont {prep} előkészületet is tartalmaz.\n\nKérjük, érkezzen pontosan. Ha változnak a tervei, jelezze előre, és áttesszük az időpontot.\nHamarosan találkozunk!",
				'uk' => "Вітаю, {name}!\nВаш запис підтверджено:\n\n{service}\n{date}, {time}–{end}\n{location}\n{address}\nЧас включає підготовку: {prep}.\n\nБудь ласка, приходьте вчасно. Якщо плани зміняться, напишіть нам заздалегідь, і ми перенесемо запис.\nДо зустрічі!",
			),
		),
		array(
			'id'    => 'busy',
			'title' => __( 'The time is taken', 'shootbook' ),
			'text'  => array(
				'ru' => "Здравствуйте, {name}!\nСпасибо за заявку. К сожалению, {date} в {time} у нас уже занято.\nСвободное время можно выбрать здесь: {book_url}\nИли напишите, какие дни вам удобны, и мы подберём вариант.",
				'en' => "Hello {name}!\nThank you for your request. Unfortunately, {date} at {time} is already taken.\nYou can pick a free time here: {book_url}\nOr tell us which days suit you and we will find an option.",
				'de' => "Hallo {name},\nvielen Dank für Ihre Anfrage. Leider ist der {date} um {time} bereits vergeben.\nFreie Termine finden Sie hier: {book_url}\nOder schreiben Sie uns, welche Tage Ihnen passen, dann finden wir eine Lösung.",
				'hu' => "Kedves {name}!\nKöszönjük a foglalási kérést. Sajnos {date} {time} már foglalt.\nSzabad időpontot itt választhat: {book_url}\nVagy írja meg, mely napok felelnek meg Önnek, és találunk megoldást.",
				'uk' => "Вітаю, {name}!\nДякуємо за заявку. На жаль, {date} о {time} вже зайнято.\nВільний час можна обрати тут: {book_url}\nАбо напишіть, які дні вам зручні, і ми підберемо варіант.",
			),
		),
		array(
			'id'    => 'details',
			'title' => __( 'Ask for details', 'shootbook' ),
			'text'  => array(
				'ru' => "Здравствуйте, {name}!\nСпасибо за интерес к нам. Чтобы всё подготовить, подскажите, пожалуйста:\n— какая дата и время вам удобны;\n— сколько человек будет участвовать;\n— есть ли у вас пожелания к сессии.",
				'en' => "Hello {name}!\nThank you for your interest. To prepare everything, could you please tell us:\n— which date and time suit you;\n— how many people will take part;\n— any wishes for the session.",
				'de' => "Hallo {name},\nvielen Dank für Ihr Interesse. Damit wir alles vorbereiten können, verraten Sie uns bitte:\n— welches Datum und welche Uhrzeit Ihnen passen;\n— wie viele Personen teilnehmen;\n— ob Sie besondere Wünsche haben.",
				'hu' => "Kedves {name}!\nKöszönjük az érdeklődést. Hogy mindent előkészíthessünk, kérjük, írja meg:\n— melyik nap és időpont felel meg Önnek;\n— hányan vesznek részt;\n— vannak-e külön kívánságai.",
				'uk' => "Вітаю, {name}!\nДякуємо за інтерес до нас. Щоб усе підготувати, підкажіть, будь ласка:\n— яка дата й час вам зручні;\n— скільки людей братиме участь;\n— чи є у вас побажання до сесії.",
			),
		),
		array(
			'id'    => 'followup',
			'title' => __( 'Follow up', 'shootbook' ),
			'text'  => array(
				'ru' => "Здравствуйте, {name}!\nХотим уточнить, актуальна ли ещё ваша заявка.\nМожем держать для вас {date} в {time}.\nЕсли планы изменились, просто дайте знать.",
				'en' => "Hello {name}!\nWe wanted to check whether your request is still on.\nWe can hold {date} at {time} for you.\nIf your plans have changed, just let us know.",
				'de' => "Hallo {name},\nwir wollten nachfragen, ob Ihre Anfrage noch aktuell ist.\nWir können den {date} um {time} für Sie freihalten.\nFalls sich Ihre Pläne geändert haben, sagen Sie uns einfach Bescheid.",
				'hu' => "Kedves {name}!\nSzeretnénk érdeklődni, hogy aktuális-e még a foglalási kérése.\nA(z) {date} {time} időpontot továbbra is fenn tudjuk tartani Önnek.\nHa változtak a tervei, csak jelezze nekünk.",
				'uk' => "Вітаю, {name}!\nХочемо уточнити, чи ваша заявка ще актуальна.\nМожемо тримати для вас {date} о {time}.\nЯкщо плани змінилися, просто дайте знати.",
			),
		),
		array(
			'id'    => 'thanks',
			'title' => __( 'After the session: thanks and a review', 'shootbook' ),
			'text'  => array(
				'ru' => "Здравствуйте, {name}!\nСпасибо, что были у нас! Надеемся, вам понравилось.\nБудем очень благодарны за короткий отзыв: {review_url} Это очень нам помогает.",
				'en' => "Hello {name}!\nThank you for visiting us! We hope you enjoyed it.\nWe would be very grateful for a short review: {review_url} It really helps us.",
				'de' => "Hallo {name},\nvielen Dank für Ihren Besuch! Wir hoffen, es hat Ihnen gefallen.\nÜber eine kurze Bewertung würden wir uns sehr freuen: {review_url} Das hilft uns sehr.",
				'hu' => "Kedves {name}!\nKöszönjük, hogy nálunk járt! Reméljük, jól érezte magát.\nNagyon hálásak lennénk egy rövid értékelésért: {review_url} Ez sokat segít nekünk.",
				'uk' => "Вітаю, {name}!\nДякуємо, що завітали до нас! Сподіваємося, вам сподобалося.\nБудемо дуже вдячні за короткий відгук: {review_url} Це дуже нам допомагає.",
			),
		),
	);
}

function bkb_replies() {
	$r = bkb_cfg( 'replies' );
	return is_array( $r ) ? $r : bkb_default_replies();
}

/** Values for the placeholders, in one language. */
function bkb_reply_vars( $b, $client, $lang ) {
	$t    = bkb_t( in_array( $lang, bkb_known_langs(), true ) ? $lang : 'en' );
	$tz   = wp_timezone();
	$name = $b ? $b['name'] : ( $client['name'] ?? '' );
	$name = trim( preg_split( '/\s+/', trim( (string) $name ) )[0] ?? '' ); // First name reads warmer.
	$v    = array(
		'name'       => $name,
		'date'       => '',
		'time'       => '',
		'end'        => '',
		'service'    => '',
		'location'   => '',
		'address'    => '',
		'hours'      => '',
		'prep'       => '',
		'price'      => '',
		'book_url'   => '',
		'review_url' => (string) bkb_cfg( 'review_url' ),
		'site'       => bkb_site_name(),
	);
	$prep = (int) bkb_cfg( 'schedule' )['prep'];
	if ( $prep ) {
		$v['prep'] = bkb_fmt_minutes( $prep, $t );
	}
	if ( $b ) {
		$fmt = 'hu' === $lang ? 'Y.m.d.' : 'd.m.Y';
		if ( (int) $b['start'] ) {
			$v['date'] = wp_date( $fmt, (int) $b['start'], $tz );
			$v['time'] = wp_date( 'H:i', (int) $b['start'], $tz );
			$v['end']  = wp_date( 'H:i', (int) $b['end'], $tz );
		}
		if ( '' !== (string) $b['service'] ) {
			$v['service'] = bkb_service_label( $b['service'], $lang );
		}
		if ( '' !== (string) $b['location'] ) {
			$loc           = bkb_location( $b['location'] );
			$v['location'] = bkb_location_label( $b['location'], $lang );
			$v['address']  = (string) ( $loc['address'] ?? '' );
		}
		$v['hours']    = $b['hours'] ? bkb_fmt_hours( $b['hours'], $t ) : '';
		$v['price']    = (string) $b['price'];
		$v['book_url'] = (string) $b['page'];
	}
	if ( '' === $v['book_url'] ) {
		$v['book_url'] = (string) bkb_cfg( 'book_url' );
	}
	return apply_filters( 'bkb_reply_vars', $v, $b, $client, $lang );
}

/** Fill a template. A line whose placeholder has no value is left out, so nothing half-empty is sent. */
function bkb_reply_fill( $tpl, $v ) {
	if ( '' === $v['name'] ) {
		$tpl = preg_replace( '/,?\s*\{name\}/u', '', $tpl );
	}
	$out = array();
	foreach ( preg_split( '/\r\n|\n/', (string) $tpl ) as $line ) {
		$skip = false;
		$line = preg_replace_callback(
			'/\{([a-z_]+)\}/',
			function ( $m ) use ( $v, &$skip ) {
				if ( ! array_key_exists( $m[1], $v ) ) {
					return $m[0];
				}
				if ( '' === (string) $v[ $m[1] ] ) {
					$skip = true;
				}
				return (string) $v[ $m[1] ];
			},
			$line
		);
		if ( ! $skip ) {
			$out[] = $line;
		}
	}
	return trim( preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $out ) ) );
}

/** The client's language for messages: the session language, then the client's language, then the form language. */
function bkb_reply_client_lang( $b, $client ) {
	$cands = array( $b['slang'] ?? '', $client['lang'] ?? '', $b['ui'] ?? '' );
	foreach ( $cands as $l ) {
		if ( in_array( $l, bkb_langs(), true ) ) {
			return $l;
		}
	}
	return bkb_langs()[0];
}

/** The box with replies; $b is a request (or null), $client a CRM client row (or null). */
function bkb_replies_box( $b, $client ) {
	$langs = bkb_langs();
	$admin = bkb_replies_admin_lang();
	$cl    = bkb_reply_client_lang( $b, $client );
	$ig    = $client['instagram'] ?? '';
	$email = $b ? $b['email'] : ( $client['email'] ?? '' );
	$items = bkb_replies();
	if ( ! $items ) {
		echo '<p>' . sprintf(
			/* translators: %s: link to the settings */
			esc_html__( 'There are no ready-made replies. Add them in the %s, tab “Ready-made replies”.', 'shootbook' ),
			'<a href="' . esc_url( admin_url( 'edit.php?post_type=bk_booking&page=bkb-settings' ) ) . '">' . esc_html__( 'settings', 'shootbook' ) . '</a>'
		) . '</p>';
		return;
	}
	$names = bkb_lang_names();
	echo '<div class="bkb-replies" data-lang="' . esc_attr( $cl ) . '" data-client="' . (int) ( $client['id'] ?? 0 ) . '" data-booking="' . (int) ( $b['id'] ?? 0 ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'bkb_send_reply' ) ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '">';
	echo '<p class="bkb-rep-langs">' . esc_html__( 'Client language:', 'shootbook' ) . ' ';
	foreach ( $langs as $l ) {
		echo '<button type="button" class="button button-small' . ( $l === $cl ? ' button-primary' : '' ) . '" data-rl="' . esc_attr( $l ) . '">' . esc_html( $names[ $l ] ?? strtoupper( $l ) ) . '</button> ';
	}
	echo '</p>';
	foreach ( $items as $i => $r ) {
		$tr = bkb_reply_fill( $r['text'][ $admin ] ?? '', bkb_reply_vars( $b, $client, $admin ) );
		echo '<details class="bkb-rep"' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $r['title'] ) . '</summary>';
		foreach ( $langs as $l ) {
			$txt = bkb_reply_fill( $r['text'][ $l ] ?? '', bkb_reply_vars( $b, $client, $l ) );
			echo '<div class="bkb-rep-l" data-l="' . esc_attr( $l ) . '"' . ( $l === $cl ? '' : ' hidden' ) . '>';
			if ( '' === $txt ) {
				echo '<p class="description">' . esc_html__( 'There is no text for this language.', 'shootbook' ) . '</p></div>';
				continue;
			}
			echo '<textarea class="widefat" rows="' . (int) min( 14, substr_count( $txt, "\n" ) + 2 ) . '">' . esc_textarea( $txt ) . '</textarea>';
			echo '<p class="bkb-rep-actions"><button type="button" class="button button-primary" data-copy>' . esc_html__( 'Copy', 'shootbook' ) . '</button> ';
			if ( $ig ) {
				echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( 'https://ig.me/m/' . $ig ) . '">' . esc_html__( 'Open the Instagram chat', 'shootbook' ) . '</a> ';
			}
			/** Fires among the buttons under a reply text, to add more ways to send it. */
			do_action( 'bkb_reply_actions', $client, $b, $l );
			if ( is_email( $email ) ) {
				echo '<button type="button" class="button" data-send="' . esc_attr( $email ) . '" data-title="' . esc_attr( $r['title'] ) . '">' . esc_html__( 'Send an e-mail from the site', 'shootbook' ) . '</button> ';
				echo '<a class="button-link" data-mail="' . esc_attr( $email ) . '" data-subj="' . esc_attr( bkb_site_name() ) . '" href="#">' . esc_html__( 'or open in the mail app', 'shootbook' ) . '</a> ';
			}
			echo '<span class="bkb-rep-ok" hidden>' . esc_html__( 'Copied', 'shootbook' ) . '</span><span class="bkb-rep-sent" hidden></span></p>';
			if ( $l !== $admin && '' !== $tr ) {
				echo '<div class="bkb-rep-tr"><b>' . esc_html__( 'Translation:', 'shootbook' ) . '</b><br>' . nl2br( esc_html( $tr ) ) . '</div>';
			}
			echo '</div>';
		}
		echo '</details>';
	}
	echo '</div>';
	wp_enqueue_script( 'bkb-replies' );
}

/* ---------- where the box appears ---------- */

add_action(
	'add_meta_boxes_bk_booking',
	function ( $post ) {
		add_meta_box(
			'bkb_replies',
			__( 'Ready-made replies', 'shootbook' ),
			function ( $post ) {
				$b      = bkb_get( $post->ID );
				$cid    = (int) get_post_meta( $post->ID, '_bkb_client', true );
				$client = $cid && function_exists( 'bkb_crm_client' ) ? bkb_crm_client( $cid ) : null;
				bkb_replies_box( $b, $client );
			},
			'bk_booking',
			'normal',
			'default'
		);
	}
);

/** The client's latest request that still exists, for the client card. */
function bkb_replies_latest_booking( $client_id ) {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT booking_id FROM ' . bkb_crm_table( 'events' ) . " WHERE client_id = %d AND type = 'request' AND booking_id > 0 ORDER BY created DESC, id DESC LIMIT 5", $client_id ) ); // phpcs:ignore WordPress.DB
	foreach ( $ids as $id ) {
		if ( get_post( (int) $id ) && 'trash' !== get_post_status( (int) $id ) ) {
			return bkb_get( (int) $id );
		}
	}
	return null;
}

add_action(
	'admin_enqueue_scripts',
	function () {
		$s = get_current_screen();
		if ( $s && 'bk_booking' === $s->post_type && 'post' === $s->base ) {
			wp_enqueue_style( 'bkb-admin', BKB_URL . 'assets/admin.css', array(), BKB_VER );
		}
		/* Enqueued by bkb_replies_box() where the templates are shown. */
		wp_register_script( 'bkb-replies', BKB_URL . 'assets/replies.js', array( 'wp-i18n' ), BKB_VER, true );
		wp_set_script_translations( 'bkb-replies', 'shootbook', BKB_DIR . 'languages' );
	}
);

/* ---------- sending a reply by e-mail from the site ---------- */

add_action(
	'wp_ajax_bkb_send_reply',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'not allowed', 'shootbook' ) );
		}
		check_ajax_referer( 'bkb_send_reply' );
		$p    = wp_unslash( $_POST );
		$to   = sanitize_email( (string) ( $p['to'] ?? '' ) );
		$text = sanitize_textarea_field( (string) ( $p['text'] ?? '' ) );
		$cid  = (int) ( $p['client'] ?? 0 );
		$bid  = (int) ( $p['booking'] ?? 0 );
		if ( ! is_email( $to ) || '' === trim( $text ) ) {
			wp_send_json_error( __( 'no address or text', 'shootbook' ) );
		}
		// The address must belong to this client or request, so the button cannot be used to write to anyone.
		$known = array();
		if ( $bid ) {
			$known[] = strtolower( (string) get_post_meta( $bid, '_bkb_email', true ) );
		}
		if ( $cid && function_exists( 'bkb_crm_client' ) ) {
			$c       = bkb_crm_client( $cid );
			$known[] = strtolower( (string) ( $c['email'] ?? '' ) );
		}
		if ( ! in_array( strtolower( $to ), $known, true ) ) {
			wp_send_json_error( __( 'the address is not the client’s address', 'shootbook' ) );
		}
		$ok = wp_mail( $to, bkb_site_name(), $text, array( 'Reply-To: ' . bkb_site_name() . ' <' . bkb_reply_to() . '>' ) );
		if ( ! $ok ) {
			wp_send_json_error( __( 'the mail server did not accept the e-mail', 'shootbook' ) );
		}
		if ( ! $cid && $bid ) {
			$cid = (int) get_post_meta( $bid, '_bkb_client', true );
		}
		if ( $cid && function_exists( 'bkb_crm_add_event' ) ) {
			$title = sanitize_text_field( (string) ( $p['title'] ?? '' ) );
			bkb_crm_add_event(
				$cid,
				'mail',
				/* translators: 1: reply title, 2: e-mail address */
				( $title ? sprintf( __( 'E-mail to the client (“%1$s”) to %2$s', 'shootbook' ), $title, $to ) : sprintf( /* translators: %s: e-mail address */ __( 'E-mail to the client to %s', 'shootbook' ), $to ) ) . ":\n\n" . $text,
				array( 'booking_id' => $bid )
			);
			bkb_crm_update_client( $cid, array( 'last_contact' => bkb_crm_now() ) );
			$c = bkb_crm_client( $cid );
			if ( $c && 'new' === $c['stage'] ) {
				bkb_crm_set_stage( $cid, 'replied', '', $bid );
			}
		}
		wp_send_json_success();
	}
);
