<?php
/**
 * Plugin Name:       WooCommerce Trade-In Pro
 * Plugin URI:        https://example.com/woocommerce-trade-in-pro
 * Description:       Trade-in workflow for WooCommerce product pages with multi-step modal, login/verification, admin notifications, and WhatsApp integration.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Bind AI
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woocommerce-trade-in-pro
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
  exit;
}

define('WTIP_VERSION', '1.1.0');
define('WTIP_FILE', __FILE__);
define('WTIP_DIR', plugin_dir_path(__FILE__));
define('WTIP_URL', plugin_dir_url(__FILE__));
define('WTIP_TEXTDOMAIN', 'woocommerce-trade-in-pro');

register_activation_hook(__FILE__, function () {
  // Ensure CPT is registered and flush rewrite rules
  require_once WTIP_DIR . 'includes/class-wtip-cpt.php';
  WTIP_CPT::register();
  flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
  flush_rewrite_rules();
});

add_action('plugins_loaded', function () {
  load_plugin_textdomain(WTIP_TEXTDOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');

  // WooCommerce check
  if (!class_exists('WooCommerce')) {
    add_action('admin_notices', function () {
      echo '<div class="notice notice-error"><p>' . esc_html__('WooCommerce Trade-In Pro requires WooCommerce to be installed and active.', WTIP_TEXTDOMAIN) . '</p></div>';
    });
    return;
  }

  // Includes
  require_once WTIP_DIR . 'includes/helpers.php';
  require_once WTIP_DIR . 'includes/class-wtip-cpt.php';
  require_once WTIP_DIR . 'includes/class-wtip-settings.php';
  require_once WTIP_DIR . 'includes/class-wtip-email.php';
  require_once WTIP_DIR . 'includes/class-wtip-whatsapp.php';
  require_once WTIP_DIR . 'includes/class-wtip-auth.php';
  require_once WTIP_DIR . 'includes/class-wtip-ajax.php';
  require_once WTIP_DIR . 'includes/class-wtip-plugin.php';
  require_once WTIP_DIR . 'includes/class-wtip-admin-list.php';
  require_once WTIP_DIR . 'includes/class-wtip-metaboxes.php';

  // Boot
  WTIP_CPT::init();
  WTIP_Settings::init();
  WTIP_Email::init();
  WTIP_WhatsApp::init();
  WTIP_Auth::init();
  WTIP_Ajax::init();
  WTIP_Plugin::init();
  WTIP_Admin_List::init();
  WTIP_Metaboxes::init();
});
