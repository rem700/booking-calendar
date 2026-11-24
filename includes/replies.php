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
	return (string) apply_filters( 'bkb_replies_admin_lang', 'ru' );
}

function bkb_default_replies() {
	return array(
		array(
			'id'    => 'free',
			'title' => 'Время свободно',
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
			'title' => 'Запись подтверждена',
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
			'title' => 'Время уже занято',
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
			'title' => 'Уточнить детали',
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
			'title' => 'Напомнить о себе',
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
			'title' => 'После сессии: спасибо и отзыв',
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
		echo '<p>Готовых ответов нет. Их можно добавить в <a href="' . esc_url( admin_url( 'edit.php?post_type=bk_booking&page=bkb-settings' ) ) . '">настройках</a>, вкладка «Готовые ответы».</p>';
		return;
	}
	$names = bkb_lang_names();
	echo '<div class="bkb-replies" data-lang="' . esc_attr( $cl ) . '" data-client="' . (int) ( $client['id'] ?? 0 ) . '" data-booking="' . (int) ( $b['id'] ?? 0 ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'bkb_send_reply' ) ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '">';
	echo '<p class="bkb-rep-langs">Язык клиента: ';
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
				echo '<p class="description">Для этого языка текста нет.</p></div>';
				continue;
			}
			echo '<textarea class="widefat" rows="' . (int) min( 14, substr_count( $txt, "\n" ) + 2 ) . '">' . esc_textarea( $txt ) . '</textarea>';
			echo '<p class="bkb-rep-actions"><button type="button" class="button button-primary" data-copy>Копировать</button> ';
			if ( $ig ) {
				echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( 'https://ig.me/m/' . $ig ) . '">Открыть чат в Instagram</a> ';
			}
			if ( is_email( $email ) ) {
				echo '<button type="button" class="button" data-send="' . esc_attr( $email ) . '" data-title="' . esc_attr( $r['title'] ) . '">Отправить письмо с сайта</button> ';
				echo '<a class="button-link" data-mail="' . esc_attr( $email ) . '" data-subj="' . esc_attr( bkb_site_name() ) . '" href="#">или открыть в почтовой программе</a> ';
			}
			echo '<span class="bkb-rep-ok" hidden>Скопировано</span><span class="bkb-rep-sent" hidden></span></p>';
			if ( $l !== $admin && '' !== $tr ) {
				echo '<div class="bkb-rep-tr"><b>Перевод:</b><br>' . nl2br( esc_html( $tr ) ) . '</div>';
			}
			echo '</div>';
		}
		echo '</details>';
	}
	echo '</div>';
	static $js = false;
	if ( ! $js ) {
		$js = true;
		?>
		<script>
		(function () {
			document.addEventListener('click', function (e) {
				var t = e.target;
				var box = t.closest ? t.closest('.bkb-replies') : null;
				if (!box) { return; }
				if (t.hasAttribute('data-rl')) {
					var l = t.getAttribute('data-rl');
					box.querySelectorAll('[data-rl]').forEach(function (b) { b.classList.toggle('button-primary', b === t); });
					box.querySelectorAll('.bkb-rep-l').forEach(function (d) { d.hidden = d.getAttribute('data-l') !== l; });
					return;
				}
				var wrap = t.closest('.bkb-rep-l');
				if (!wrap) { return; }
				var ta = wrap.querySelector('textarea');
				if (t.hasAttribute('data-copy')) {
					var ok = wrap.querySelector('.bkb-rep-ok');
					var done = function () { ok.hidden = false; setTimeout(function () { ok.hidden = true; }, 1500); };
					if (navigator.clipboard) { navigator.clipboard.writeText(ta.value).then(done, function () { ta.select(); document.execCommand('copy'); done(); }); }
					else { ta.select(); document.execCommand('copy'); done(); }
				}
				if (t.hasAttribute('data-send')) {
					var to = t.getAttribute('data-send');
					if (!window.confirm('Отправить это письмо на ' + to + '?')) { return; }
					var msg = wrap.querySelector('.bkb-rep-sent');
					t.disabled = true;
					msg.hidden = false;
					msg.textContent = 'Отправляю…';
					var fd = new FormData();
					fd.append('action', 'bkb_send_reply');
					fd.append('_ajax_nonce', box.getAttribute('data-nonce'));
					fd.append('client', box.getAttribute('data-client'));
					fd.append('booking', box.getAttribute('data-booking'));
					fd.append('to', to);
					fd.append('title', t.getAttribute('data-title'));
					fd.append('text', ta.value);
					fetch(box.getAttribute('data-ajax'), { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
						msg.textContent = j.success ? 'Письмо отправлено и сохранено в истории клиента' : ('Не отправлено: ' + (j.data || 'ошибка'));
						msg.style.color = j.success ? '#1a7f37' : '#b32d2e';
						t.disabled = !!j.success;
					}, function () { msg.textContent = 'Не отправлено: нет связи с сайтом'; msg.style.color = '#b32d2e'; t.disabled = false; });
					return;
				}
				if (t.hasAttribute('data-mail')) {
					e.preventDefault();
					location.href = 'mailto:' + t.getAttribute('data-mail') + '?subject=' + encodeURIComponent(t.getAttribute('data-subj')) + '\x26body=' + encodeURIComponent(ta.value);
				}
			});
		})();
		</script>
		<?php
	}
}

/* ---------- where the box appears ---------- */

add_action(
	'add_meta_boxes_bk_booking',
	function ( $post ) {
		add_meta_box(
			'bkb_replies',
			'Готовые ответы',
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
	}
);

/* ---------- sending a reply by e-mail from the site ---------- */

add_action(
	'wp_ajax_bkb_send_reply',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'нет прав' );
		}
		check_ajax_referer( 'bkb_send_reply' );
		$p    = wp_unslash( $_POST );
		$to   = sanitize_email( (string) ( $p['to'] ?? '' ) );
		$text = sanitize_textarea_field( (string) ( $p['text'] ?? '' ) );
		$cid  = (int) ( $p['client'] ?? 0 );
		$bid  = (int) ( $p['booking'] ?? 0 );
		if ( ! is_email( $to ) || '' === trim( $text ) ) {
			wp_send_json_error( 'нет адреса или текста' );
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
			wp_send_json_error( 'адрес не совпадает с адресом клиента' );
		}
		$ok = wp_mail( $to, bkb_site_name(), $text, array( 'Reply-To: ' . bkb_site_name() . ' <' . bkb_reply_to() . '>' ) );
		if ( ! $ok ) {
			wp_send_json_error( 'почтовый сервер не принял письмо' );
		}
		if ( ! $cid && $bid ) {
			$cid = (int) get_post_meta( $bid, '_bkb_client', true );
		}
		if ( $cid && function_exists( 'bkb_crm_add_event' ) ) {
			$title = sanitize_text_field( (string) ( $p['title'] ?? '' ) );
			bkb_crm_add_event( $cid, 'mail', 'Письмо клиенту' . ( $title ? ' («' . $title . '»)' : '' ) . ' на ' . $to . ":\n\n" . $text, array( 'booking_id' => $bid ) );
			bkb_crm_update_client( $cid, array( 'last_contact' => bkb_crm_now() ) );
			$c = bkb_crm_client( $cid );
			if ( $c && 'new' === $c['stage'] ) {
				bkb_crm_set_stage( $cid, 'replied', '', $bid );
			}
		}
		wp_send_json_success();
	}
);
