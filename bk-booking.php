<?php
/**
 * Plugin Name: Shootbook
 * Description: Booking requests with a live calendar and a simple client list, made for photo studios, photographers and hourly rentals.
 * Version: 2.4.0
 * Plugin URI: https://github.com/rem700/booking-calendar
 * Author: rem700
 * Author URI: https://github.com/rem700
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: shootbook
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BKB_VER', '2.4.0' );
define( 'BKB_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKB_URL', plugin_dir_url( __FILE__ ) );

require_once BKB_DIR . 'includes/ics.php';
require_once BKB_DIR . 'includes/texts.php';
require_once BKB_DIR . 'includes/config.php';
require_once BKB_DIR . 'includes/core.php';
require_once BKB_DIR . 'includes/admin.php';
require_once BKB_DIR . 'includes/crm.php';
require_once BKB_DIR . 'includes/crm-admin.php';
require_once BKB_DIR . 'includes/replies.php';

/* Bundled translations; a newer file in wp-content/languages/plugins wins. */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'shootbook', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	0
);

/*
 * A fixed admin language from the settings (Settings → General), whatever the profile language is.
 * Used, for example, to keep the admin in Russian on sites that had it before 2.4.0.
 */
add_action(
	'init',
	function () {
		$locale = array( 'en' => 'en_US', 'ru' => 'ru_RU' )[ (string) bkb_cfg( 'admin_lang' ) ] ?? '';
		if ( '' === $locale ) {
			return;
		}
		unload_textdomain( 'shootbook' );
		if ( 'en_US' !== $locale ) {
			load_textdomain( 'shootbook', BKB_DIR . 'languages/shootbook-' . $locale . '.mo' );
		}
		add_filter( 'override_load_textdomain', fn( $skip, $domain ) => 'shootbook' === $domain ? true : $skip, 10, 2 );
		add_filter(
			'load_script_translation_file',
			fn( $file, $handle, $domain ) => 'shootbook' === $domain
				? preg_replace( '/shootbook-[A-Za-z_]+-([0-9a-f]{32})\.json$/', 'shootbook-' . $locale . '-$1.json', BKB_DIR . 'languages/' . basename( (string) $file ) )
				: $file,
			10,
			3
		);
	},
	1
);

/*
 * Settings from before 2.4.0 keep working as they did: the admin stays in Russian and
 * the IPAR source, which used to be built in, moves to the site's own sources.
 */
add_action(
	'init',
	function () {
		$was = get_option( 'bkb_version' );
		if ( BKB_VER === $was ) {
			return;
		}
		$cfg = get_option( 'bkb_config' );
		if ( false === $was && is_array( $cfg ) ) {
			if ( ! isset( $cfg['admin_lang'] ) ) {
				$cfg['admin_lang'] = 'ru';
			}
			$cfg['crm'] = (array) ( $cfg['crm'] ?? array() );
			if ( '' === trim( (string) ( $cfg['crm']['sources'] ?? '' ) ) ) {
				$cfg['crm']['sources'] = 'IPAR';
			}
			update_option( 'bkb_config', bkb_sanitize_config( $cfg ) );
			bkb_cfg_reset();
		}
		update_option( 'bkb_version', BKB_VER );
	},
	0
);

/** Scheduled tasks of the plugin: removing old requests, reminders, the morning summary. */
function bkb_cron_hooks() {
	return array( 'bkb_cleanup', 'bkb_remind', 'bkb_crm_tick' );
}

register_activation_hook(
	__FILE__,
	function () {
		bkb_crm_install();
	}
);

/* Stop the scheduled tasks; they are set up again on the next page load after activation. */
register_deactivation_hook(
	__FILE__,
	function () {
		foreach ( bkb_cron_hooks() as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
);
