// Activation, deactivation and removal of the plugin.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { seed, wpEval } from './lib.mjs';

const plugin = "'bk-booking/bk-booking.php'";
const hooks = "['bkb_cleanup','bkb_remind','bkb_crm_tick']";

before(() => { seed(); wpEval('do_action("init");'); });
after(() => { wpEval(`require_once ABSPATH.'wp-admin/includes/plugin.php';activate_plugin(${plugin});`); });

test('deactivating stops all scheduled tasks', () => {
  wpEval(`require_once ABSPATH.'wp-admin/includes/plugin.php';deactivate_plugins(${plugin});`);
  const left = wpEval(`foreach(${hooks} as $h){if(wp_next_scheduled($h))echo $h,' ';}`);
  assert.equal(left.trim(), '');
});

test('activating creates the client tables', () => {
  wpEval("global $wpdb;$wpdb->query(\"DROP TABLE IF EXISTS {$wpdb->prefix}bkb_events, {$wpdb->prefix}bkb_clients\");delete_option('bkb_crm_db');");
  wpEval(`require_once ABSPATH.'wp-admin/includes/plugin.php';activate_plugin(${plugin});`);
  const n = wpEval("global $wpdb;echo count($wpdb->get_col(\"SHOW TABLES LIKE '{$wpdb->prefix}bkb_%'\"));");
  assert.equal(n, '2');
});

test('uninstall removes cached feeds and tasks but keeps settings', () => {
  wpEval("update_option('bkb_ics_test_stale','x',false);set_transient('bkb_ics_test','x',60);wp_schedule_event(time()+60,'hourly','bkb_remind');");
  // Checked in the same run: any later page load schedules the tasks again while the plugin is active.
  const task = wpEval("define('WP_UNINSTALL_PLUGIN','bk-booking/bk-booking.php');include WP_PLUGIN_DIR.'/bk-booking/uninstall.php';var_export(wp_next_scheduled('bkb_remind'));");
  assert.equal(task, 'false');
  assert.equal(wpEval("var_export(get_option('bkb_ics_test_stale'));"), 'false');
  assert.equal(wpEval("var_export(get_transient('bkb_ics_test'));"), 'false');
  assert.notEqual(wpEval("var_export(get_option('bkb_config'));"), 'false');
});
