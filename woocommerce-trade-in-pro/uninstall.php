<?php
// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
  exit;
}

// Options cleanup
delete_option('wtip_options');

// Transients cleanup
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wtip_%' OR option_name LIKE '_transient_timeout_wtip_%'");
