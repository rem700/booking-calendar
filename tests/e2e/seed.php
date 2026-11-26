<?php
/**
 * Test data for the end-to-end tests: run as `php tests/e2e/seed.php /path/to/wordpress`.
 * Wipes requests and clients on that site, so use it only on a throwaway test site.
 */

if ( PHP_SAPI !== 'cli' || empty( $argv[1] ) ) {
	exit( "Usage: php seed.php /path/to/wordpress\n" );
}
$_SERVER['HTTP_HOST'] = parse_url( getenv( 'WP_URL' ) ? getenv( 'WP_URL' ) : 'http://127.0.0.1:8080', PHP_URL_HOST );
require rtrim( $argv[1], '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb;

if ( ! is_plugin_active( 'bk-booking/bk-booking.php' ) ) {
	activate_plugin( 'bk-booking/bk-booking.php' );
}

foreach ( get_posts( array( 'post_type' => 'bk_booking', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( $id, true );
}
if ( function_exists( 'bkb_crm_table' ) ) {
	$wpdb->query( 'DELETE FROM ' . bkb_crm_table( 'events' ) ); // phpcs:ignore WordPress.DB
	$wpdb->query( 'DELETE FROM ' . bkb_crm_table( 'clients' ) ); // phpcs:ignore WordPress.DB
}

$all = array();
for ( $d = 1; $d <= 7; $d++ ) {
	$all[ (string) $d ] = array( 10, 18 );
}
update_option(
	'bkb_config',
	bkb_sanitize_config(
		array(
			'site_name' => 'Test Studio',
			'notify'    => 'team@example.test',
			'from'      => 'hello@example.test',
			'languages' => array( 'en', 'ru' ),
			'schedule'  => array(
				'days'             => $all,
				'step'             => 60,
				'lead_days'        => 1,
				'horizon_days'     => 60,
				'prep'             => 0,
				'durations'        => array( 1, 2 ),
				'default_duration' => 1,
			),
			'services'  => array(
				array( 'id' => 'standard', 'label' => array( 'en' => 'Standard', 'ru' => 'Стандарт' ) ),
			),
			'locations' => array(
				array( 'id' => 'hall-a', 'label' => array( 'en' => 'Hall A' ), 'address' => 'Test street 1' ),
				array( 'id' => 'hall-b', 'label' => array( 'en' => 'Hall B' ), 'address' => 'Test street 2' ),
			),
			'fields'    => array(
				'phone'   => array( 'on' => true ),
				'message' => array( 'on' => true ),
			),
			'reminder'  => true,
			'crm'       => array( 'source' => true ),
		)
	),
	false
);

// Google Calendar: not connected, no test answers left from an earlier run.
foreach ( array( 'bkb_gcal', 'bkb_gcal_stale', 'bkb_feed_key', 'bkb_test_gcal_log', 'bkb_test_gcal_events', 'bkb_test_gcal_foreign', 'bkb_test_gcal_down', 'bkb_test_gcal_revoked' ) as $o ) {
	delete_option( $o );
}
delete_transient( 'bkb_gcal_down' );

$page = get_page_by_path( 'book' );
if ( ! $page ) {
	$pid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Book', 'post_name' => 'book', 'post_content' => '[booking_calendar]' ) );
} else {
	$pid = $page->ID;
}

echo wp_json_encode( array( 'page' => get_permalink( $pid ) ) ), "\n";
