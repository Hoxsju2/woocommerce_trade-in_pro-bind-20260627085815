<?php
if (!defined('ABSPATH')) { exit; }

class WTIP_CPT {
  public static function init() {
    add_action('init', [__CLASS__, 'register']);
    add_action('init', [__CLASS__, 'register_statuses']);
  }

  public static function register() {
    $labels = [
      'name' => __('Trade-In Requests', 'woocommerce-trade-in-pro'),
      'singular_name' => __('Trade-In Request', 'woocommerce-trade-in-pro'),
      'menu_name' => __('Trade-In Requests', 'woocommerce-trade-in-pro'),
      'add_new' => __('Add New', 'woocommerce-trade-in-pro'),
      'add_new_item' => __('Add New Trade-In Request', 'woocommerce-trade-in-pro'),
      'edit_item' => __('Edit Trade-In Request', 'woocommerce-trade-in-pro'),
      'new_item' => __('New Trade-In Request', 'woocommerce-trade-in-pro'),
      'view_item' => __('View Trade-In Request', 'woocommerce-trade-in-pro'),
      'search_items' => __('Search Trade-In Requests', 'woocommerce-trade-in-pro'),
      'not_found' => __('No requests found', 'woocommerce-trade-in-pro'),
      'not_found_in_trash' => __('No requests found in Trash', 'woocommerce-trade-in-pro'),
    ];

    $args = [
      'labels' => $labels,
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => false,
      'capability_type' => 'post',
      'map_meta_cap' => true,
      'hierarchical' => false,
      'supports' => ['title', 'author', 'custom-fields'],
      'has_archive' => false,
      'rewrite' => false,
    ];

    register_post_type('wtip_request', $args);
  }

  public static function register_statuses() {
    $statuses = [
      'wtip_pending' => __('Pending', 'woocommerce-trade-in-pro'),
      'wtip_approved' => __('Approved', 'woocommerce-trade-in-pro'),
      'wtip_rejected' => __('Rejected', 'woocommerce-trade-in-pro'),
    ];

    foreach ($statuses as $key => $label) {
      register_post_status($key, [
        'label' => $label,
        'public' => false,
        'exclude_from_search' => true,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop("$label <span class='count'>(%s)</span>", "$label <span class='count'>(%s)</span>", 'woocommerce-trade-in-pro'),
      ]);
    }
  }
}
