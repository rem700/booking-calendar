<?php
/**
 * Runs when the plugin is deleted in Plugins.
 *
 * Removes the scheduled tasks and the cached calendar feeds. Requests, clients and settings stay in the
 * database, so installing the plugin again brings everything back.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

foreach ( array( 'bkb_cleanup', 'bkb_remind', 'bkb_crm_tick', 'bkb_ig_hourly', 'bkb_ig_daily', 'bkb_gcal_hourly' ) as $bkb_hook ) {
	wp_clear_scheduled_hook( $bkb_hook );
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'bkb\\_ics\\_%' OR option_name LIKE '\\_transient\\_bkb\\_ics\\_%' OR option_name LIKE '\\_transient\\_timeout\\_bkb\\_ics\\_%'" );
delete_option( 'bkb_gcal_stale' );
