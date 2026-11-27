<?php
/**
 * The booking form on sites made in Framer, Webflow, Tilda or Wix.
 *
 * The other site gets a short code: a block and assets/embed.js. The script puts this site's
 * form page (?bkb_embed=1) into an iframe and makes it as tall as the form. Requests, e-mails
 * and clients stay here, and the form talks only to this site, so no CORS is needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Settings of the widget: sites allowed to show the form, one address per line. */
function bkb_embed_get() {
	$o = get_option( 'bkb_embed', array() );
	return array( 'sites' => (string) ( is_array( $o ) ? ( $o['sites'] ?? '' ) : '' ) );
}

/** "https://iparstudio.com/page" → "https://iparstudio.com"; '' when it is not a site address. */
function bkb_embed_origin( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		$url = 'https://' . $url;
	}
	$p = wp_parse_url( $url );
	if ( empty( $p['host'] ) || ! preg_match( '/^[a-z0-9.-]+$/i', $p['host'] ) || false === strpos( $p['host'], '.' ) && 'localhost' !== $p['host'] ) {
		return '';
	}
	return strtolower( $p['scheme'] . '://' . $p['host'] ) . ( ! empty( $p['port'] ) ? ':' . (int) $p['port'] : '' );
}

/**
 * Sites that may show the form. Each one is allowed with and without www.
 * An empty list means any site.
 */
function bkb_embed_origins() {
	$out = array();
	foreach ( preg_split( '/[\s,]+/', bkb_embed_get()['sites'] ) as $line ) {
		$o = bkb_embed_origin( $line );
		if ( '' === $o ) {
			continue;
		}
		$out[] = $o;
		$out[] = false !== strpos( $o, '://www.' ) ? str_replace( '://www.', '://', $o ) : preg_replace( '#://#', '://www.', $o, 1 );
	}
	return array_values( array_unique( $out ) );
}

function bkb_embed_save( $sites ) {
	$list = array();
	foreach ( preg_split( '/[\s,]+/', (string) $sites ) as $line ) {
		$o = bkb_embed_origin( $line );
		if ( '' !== $o ) {
			$list[] = $o;
		}
	}
	update_option( 'bkb_embed', array( 'sites' => implode( "\n", array_unique( $list ) ) ), false );
}

/** The address of the form page that the widget opens. */
function bkb_embed_url() {
	return add_query_arg( 'bkb_embed', '1', home_url( '/' ) );
}

function bkb_embed_script_url() {
	return BKB_URL . 'assets/embed.js?ver=' . BKB_VER;
}

/*
 * The address of the page with the form, sent with a request. It must be this site or one of
 * the allowed sites: it goes into the client's e-mails as the link to book again.
 */
add_filter(
	'bkb_request_data',
	function ( $d, $p ) {
		$page = esc_url_raw( (string) ( $p['page'] ?? '' ) );
		$o    = bkb_embed_origin( $page );
		if ( '' !== $o && in_array( $o, bkb_embed_origins(), true ) ) {
			$d['page'] = $page;
		}
		return $d;
	},
	1,
	2
);

/** A colour from the widget code: #abc, #aabbcc or #aabbccdd. */
function bkb_embed_color( $v ) {
	$v = ltrim( trim( (string) $v ), '#' );
	return preg_match( '/^([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $v ) ? '#' . strtolower( $v ) : '';
}

/** A font list from the widget code, without anything that could end the CSS value. */
function bkb_embed_font( $v ) {
	return mb_substr( trim( preg_replace( '/[^\p{L}\p{N}\s,\'"\-]/u', '', (string) $v ) ), 0, 200 );
}

/** Google Fonts stylesheet for the first font of each list, when the widget code asks for it. */
function bkb_embed_google_fonts( $fonts ) {
	$fam = array();
	foreach ( $fonts as $f ) {
		$first = trim( explode( ',', (string) $f )[0], " \t'\"" );
		if ( '' !== $first && ! in_array( strtolower( $first ), array( 'serif', 'sans-serif', 'monospace', 'system-ui', 'inherit', 'inter var' ), true ) ) {
			$fam[ $first ] = 'family=' . str_replace( '%20', '+', rawurlencode( $first ) ) . ':wght@400;600;700;800';
		}
	}
	return $fam ? 'https://fonts.googleapis.com/css2?' . implode( '&', $fam ) . '&display=swap' : '';
}

/* The form page inside the iframe: only the form, without the theme. */
add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['bkb_embed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		bkb_embed_render();
		exit;
	},
	0
);

function bkb_embed_render() {
	// phpcs:disable WordPress.Security.NonceVerification
	$get  = fn( $k ) => sanitize_text_field( wp_unslash( (string) ( $_GET[ $k ] ?? '' ) ) );
	$lang = strtolower( substr( $get( 'lang' ), 0, 2 ) );
	$lang = in_array( $lang, bkb_langs(), true ) ? $lang : bkb_langs()[0];
	$cols = array(
		'ink'    => bkb_embed_color( $get( 'ink' ) ),
		'paper'  => bkb_embed_color( $get( 'paper' ) ),
		'accent' => bkb_embed_color( $get( 'accent' ) ),
	);
	$font = bkb_embed_font( $get( 'font' ) );
	$head = bkb_embed_font( $get( 'head' ) );
	$gf   = '1' === $get( 'gf' ) ? bkb_embed_google_fonts( array( $font, $head ) ) : '';
	// phpcs:enable

	$sites = bkb_embed_origins();
	header_remove( 'X-Frame-Options' );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Content-Security-Policy: frame-ancestors ' . ( $sites ? "'self' " . implode( ' ', $sites ) : '*' ) );
	header( 'X-Robots-Tag: noindex' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );

	add_filter(
		'bkb_style',
		function ( $s ) use ( $cols, $font, $head ) {
			foreach ( $cols as $k => $v ) {
				if ( '' !== $v ) {
					$s[ $k ] = $v;
				}
			}
			if ( '' !== $font ) {
				$s['body_font'] = $font;
			}
			if ( '' !== $head || '' !== $font ) {
				$s['heading_font'] = '' !== $head ? $head : $font;
			}
			return $s;
		}
	);
	add_filter( 'bkb_front_config', fn( $c ) => $c + array( 'embed' => true ) );
	$form = bkb_shortcode( array( 'lang' => $lang ) );
	wp_enqueue_script( 'bkb-embed-frame', BKB_URL . 'assets/embed-frame.js', array( 'bk-booking' ), BKB_VER, true );
	?>
<!doctype html>
<html lang="<?php echo esc_attr( $lang ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( bkb_site_name() ); ?></title>
	<?php if ( $gf ) : ?>
<link rel="stylesheet" href="<?php echo esc_url( $gf ); ?>">
	<?php endif; ?>
	<?php wp_dequeue_style( 'wp-emoji-styles' ); ?>
	<?php wp_print_styles(); ?>
<style>html,body{margin:0;padding:0;background:transparent;height:auto}body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}html.bkb-sized,html.bkb-sized body{overflow:hidden}.bkb{max-width:none;margin:0}</style>
</head>
<body class="bkb-embed">
	<?php echo $form; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php wp_print_scripts(); ?>
</body>
</html>
	<?php
}
