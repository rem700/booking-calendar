<?php
/**
 * Plugin Name: Booking Calendar
 * Description: Booking requests with a live calendar: free start times from your working hours and a Google Calendar (iCal) feed. Services, locations, options, hours, texts and colours are set in Settings, no code needed. Requests are confirmed or declined in WP admin, the client gets emails in their language.
 * Version: 2.0.0
 * Plugin URI: https://github.com/rem700/booking-calendar
 * Author: rem700
 * Author URI: https://github.com/rem700
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: bk-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BKB_VER', '2.0.0' );
define( 'BKB_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKB_URL', plugin_dir_url( __FILE__ ) );

require_once BKB_DIR . 'includes/ics.php';
require_once BKB_DIR . 'includes/texts.php';
require_once BKB_DIR . 'includes/config.php';
require_once BKB_DIR . 'includes/core.php';
require_once BKB_DIR . 'includes/admin.php';
