<?php
if (!defined('ABSPATH')) { exit; }

class WTIP_Ajax {
  public static function init() {
    add_action('wp_ajax_wtip_submit_initial', [__CLASS__, 'submit_initial']);
    add_action('wp_ajax_nopriv_wtip_submit_initial', [__CLASS__, 'submit_initial']);

    add_action('wp_ajax_wtip_send_code', [__CLASS__, 'send_code']);
    add_action('wp_ajax_nopriv_wtip_send_code', [__CLASS__, 'send_code']);

    add_action('wp_ajax_wtip_verify_code', [__CLASS__, 'verify_code']);
    add_action('wp_ajax_nopriv_wtip_verify_code', [__CLASS__, 'verify_code']);

    add_action('wp_ajax_wtip_confirm_request', [__CLASS__, 'confirm_request']);
    add_action('wp_ajax_nopriv_wtip_confirm_request', [__CLASS__, 'confirm_request']);
  }

  protected static function check_nonce() {
    $nonce = isset($_POST['nonce']) && is_string($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'wtip_nonce')) {
      wp_send_json_error(['message' => __('Security check failed.', 'woocommerce-trade-in-pro')], 403);
    }
  }

  public static function submit_initial() {
    WTIP_Helpers::ensure_json();
    self::check_nonce();

    $opts = WTIP_Helpers::get_options();

    // Strict type checks on inputs for PHP 8.0+
    $new_name_raw = isset($_POST['new_product_name']) && is_string($_POST['new_product_name']) ? $_POST['new_product_name'] : '';
    $new_product_name = sanitize_text_field(wp_unslash($new_name_raw));

    $new_link_raw = isset($_POST['new_product_link']) && is_string($_POST['new_product_link']) ? $_POST['new_product_link'] : '';
    $new_product_link = esc_url_raw($new_link_raw);

    $product_id = isset($_POST['product_id']) && is_scalar($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

    $old_name_raw = isset($_POST['old_product_name']) && is_string($_POST['old_product_name']) ? $_POST['old_product_name'] : '';
    $old_product_name = sanitize_text_field(wp_unslash($old_name_raw));

    $cond_raw = isset($_POST['condition']) && is_string($_POST['condition']) ? $_POST['condition'] : '';
    $condition = sanitize_text_field(wp_unslash($cond_raw));

    $val_raw = isset($_POST['expected_value']) && is_scalar($_POST['expected_value']) ? $_POST['expected_value'] : '';
    $expected_value = WTIP_Helpers::sanitize_value($val_raw);

    $curr_raw = isset($_POST['currency']) && is_string($_POST['currency']) ? $_POST['currency'] : 'USD';
    $currency = strtoupper(sanitize_text_field(wp_unslash($curr_raw)));

    $allowed_currencies = ['USD','SAR','GBP','EUR'];
    if (!in_array($currency, $allowed_currencies, true)) {
      $currency = 'USD';
    }

    if (empty($new_product_name) || empty($new_product_link) || empty($old_product_name) || empty($condition) || $expected_value < 0) {
      wp_send_json_error(['message' => __('Please complete all required fields.', 'woocommerce-trade-in-pro')], 400);
    }

    $images = WTIP_Helpers::attach_media_from_uploads('images', (int)$opts['max_images'], (int)$opts['max_image_size_mb']);

    $draft = [
      'new_product_name' => $new_product_name,
      'new_product_link' => $new_product_link,
      'product_id' => $product_id,
      'old_product_name' => $old_product_name,
      'condition' => $condition,
      'expected_value' => $expected_value,
      'currency' => $currency,
      'image_ids' => $images,
      'timestamp' => time(),
    ];

    $draft_key = WTIP_Helpers::set_draft_data($draft);
    $resp = [
      'draft_key' => $draft_key,
      'is_logged_in' => is_user_logged_in(),
      'images' => WTIP_Helpers::map_images($images),
      'message' => __('Draft saved.', 'woocommerce-trade-in-pro'),
    ];
    wp_send_json_success($resp);
  }

  public static function send_code() {
    WTIP_Helpers::ensure_json();
    self::check_nonce();

    $opts = WTIP_Helpers::get_options();
    if (empty($opts['enable_magic_login'])) {
      wp_send_json_error(['message' => __('Magic login is disabled by admin.', 'woocommerce-trade-in-pro')], 400);
    }

    $email_raw = isset($_POST['email']) && is_string($_POST['email']) ? $_POST['email'] : '';
    $email = sanitize_email($email_raw);
    $res = WTIP_Auth::send_code($email);
    if (is_wp_error($res)) {
      wp_send_json_error(['message' => $res->get_error_message()], 400);
    }
    wp_send_json_success(['message' => __('Verification code sent. Check your email.', 'woocommerce-trade-in-pro')]);
  }

  public static function verify_code() {
    WTIP_Helpers::ensure_json();
    self::check_nonce();

    $email_raw = isset($_POST['email']) && is_string($_POST['email']) ? $_POST['email'] : '';
    $email = sanitize_email($email_raw);

    $code_raw = isset($_POST['code']) && is_string($_POST['code']) ? $_POST['code'] : '';
    $code = sanitize_text_field($code_raw);

    $user = WTIP_Auth::verify_code_and_login($email, $code);
    if (is_wp_error($user)) {
      wp_send_json_error(['message' => $user->get_error_message()], 400);
    }

    $draft = WTIP_Helpers::get_draft_data();
    if ($draft) {
      update_user_meta($user->ID, '_wtip_last_draft', $draft);
    }

    $new_nonce = wp_create_nonce('wtip_nonce');

    wp_send_json_success([
      'message' => __('Logged in and draft saved to your profile.', 'woocommerce-trade-in-pro'),
      'user_email' => $user->user_email,
      'nonce' => $new_nonce,
    ]);
  }

  public static function confirm_request() {
    WTIP_Helpers::ensure_json();
    self::check_nonce();

    $consent = !empty($_POST['consent']);
    if (!$consent) {
      wp_send_json_error(['message' => __('You must agree to the terms to continue.', 'woocommerce-trade-in-pro')], 400);
    }

    // Resolve draft from (1) cookie transient, (2) provided draft_key, (3) user meta _wtip_last_draft
    $draft = WTIP_Helpers::get_draft_data();
    $key_raw = isset($_POST['draft_key']) && is_string($_POST['draft_key']) ? $_POST['draft_key'] : '';
    $posted_draft_key = sanitize_text_field(wp_unslash($key_raw));

    if (!$draft && $posted_draft_key) {
      $maybe = get_transient('wtip_draft_' . $posted_draft_key);
      if ($maybe && is_array($maybe)) {
        $draft = $maybe;
      }
    }

    $user_id = get_current_user_id();
    if (!$draft && $user_id) {
      $maybe_user = get_user_meta($user_id, '_wtip_last_draft', true);
      if ($maybe_user && is_array($maybe_user)) {
        $draft = $maybe_user;
      }
    }

    if (!$draft || !is_array($draft)) {
      wp_send_json_error(['message' => __('Draft not found. Please restart the process.', 'woocommerce-trade-in-pro')], 400);
    }

    $user = $user_id ? get_user_by('id', $user_id) : null;
    $user_email = $user ? $user->user_email : '';

    $image_ids = isset($draft['image_ids']) ? (array)$draft['image_ids'] : [];

    $data = [
      'new_product_name' => (string)$draft['new_product_name'],
      'new_product_link' => (string)$draft['new_product_link'],
      'product_id' => (int)$draft['product_id'],
      'old_product_name' => (string)$draft['old_product_name'],
      'condition' => (string)$draft['condition'],
      'expected_value' => (float)$draft['expected_value'],
      'currency' => isset($draft['currency']) ? (string)$draft['currency'] : 'USD',
      'images' => WTIP_Helpers::map_images($image_ids),
      'user_email' => $user_email,
      'user_id' => $user_id,
      'consent' => (bool)$consent,
    ];

    do_action('wtip_before_request_save', $data);

    $title = sprintf(__('Trade-In: %s → %s', 'woocommerce-trade-in-pro'), $data['old_product_name'], $data['new_product_name']);
    $post_id = wp_insert_post([
      'post_type' => 'wtip_request',
      'post_title' => $title,
      'post_status' => 'wtip_pending',
      'post_author' => $user_id ?: 0,
    ]);

    if (is_wp_error($post_id)) {
      wp_send_json_error(['message' => $post_id->get_error_message()], 500);
    }

    update_post_meta($post_id, '_wtip_new_product_name', $data['new_product_name']);
    update_post_meta($post_id, '_wtip_new_product_link', $data['new_product_link']);
    update_post_meta($post_id, '_wtip_product_id', $data['product_id']);
    update_post_meta($post_id, '_wtip_old_product_name', $data['old_product_name']);
    update_post_meta($post_id, '_wtip_condition', $data['condition']);
    update_post_meta($post_id, '_wtip_expected_value', $data['expected_value']);
    update_post_meta($post_id, '_wtip_currency', $data['currency']);
    update_post_meta($post_id, '_wtip_image_ids', array_map('intval', $image_ids));
    update_post_meta($post_id, '_wtip_consent', $data['consent'] ? 'yes' : 'no');
    if ($user_email) {
      update_post_meta($post_id, '_wtip_user_email', $user_email);
    }

    $data['post_id'] = $post_id;

    $email_sent = WTIP_Email::send_admin_notification($post_id, $data);

    $wa_url = WTIP_WhatsApp::build_url($data);

    do_action('wtip_after_request_save', $post_id, $data);

    // Cleanup: clear cookie-based draft, delete posted draft transient, and user meta copy
    WTIP_Helpers::clear_draft();
    if (!empty($posted_draft_key)) {
      delete_transient('wtip_draft_' . $posted_draft_key);
    }
    if ($user_id) {
      delete_user_meta($user_id, '_wtip_last_draft');
    }

    wp_send_json_success([
      'message' => __('Trade-in request submitted!', 'woocommerce-trade-in-pro'),
      'admin_email_sent' => $email_sent,
      'whatsapp_url' => $wa_url,
      'dashboard_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
    ]);
  }
}
