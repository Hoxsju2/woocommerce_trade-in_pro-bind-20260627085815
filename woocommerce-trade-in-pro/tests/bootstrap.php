<?php
// Minimal bootstrap for WP PHPUnit integration.
// Adjust paths for your local setup, e.g., WP core tests directory.

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) $_tests_dir = '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', function () {
  // Load WooCommerce if needed here (path depends on your setup)
  // Load plugin
  require dirname(__DIR__) . '/woocommerce-trade-in-pro.php';
});

require $_tests_dir . '/includes/bootstrap.php';
