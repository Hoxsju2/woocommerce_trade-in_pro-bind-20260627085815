<?php
if (!defined('ABSPATH')) { exit; }

class WTIP_Auth {
  const TRANSIENT_PREFIX = 'wtip_code_';
  const EXPIRE = 15 * MINUTE_IN_SECONDS;

  public static function init() {
    add_action('init', function () {
      // placeholder for potential rewrite endpoints if needed
    });
  }

  protected static function key_for_email($email) {
    // Explicit string cast ensures compatibility with strict types in PHP 8.1+
    return self::TRANSIENT_PREFIX . hash('sha256', strtolower(trim((string)$email)));
  }

  public static function send_code($email) {
    $email = sanitize_email((string)$email);
    if (!is_email($email)) {
      return new WP_Error('wtip_invalid_email', __('Please enter a valid email address.', 'woocommerce-trade-in-pro'));
    }
    $code = wp_rand(100000, 999999);
    set_transient(self::key_for_email($email), (string)$code, self::EXPIRE);

    $subject = __('Your verification code', 'woocommerce-trade-in-pro');
    $body = sprintf(__('Your verification code is: %s', 'woocommerce-trade-in-pro'), $code) . "\n\n" . __('Enter this code to continue your trade-in.', 'woocommerce-trade-in-pro');

    $sent = wp_mail($email, $subject, $body);
    if (!$sent) {
      return new WP_Error('wtip_email_failed', __('Failed to send verification email. Please try again.', 'woocommerce-trade-in-pro'));
    }
    return true;
  }

  public static function verify_code_and_login($email, $code) {
    $email = sanitize_email((string)$email);
    $code = trim((string)$code);
    $stored = get_transient(self::key_for_email($email));
    if (!$stored || $stored !== $code) {
      return new WP_Error('wtip_invalid_code', __('Invalid or expired verification code.', 'woocommerce-trade-in-pro'));
    }

    $user = get_user_by('email', $email);
    if (!$user) {
      // Create user
      $username_base = sanitize_user(current(explode('@', $email)));
      $username = $username_base;
      $i = 1;
      while (username_exists($username)) {
        $username = $username_base . $i;
        $i++;
      }
      $password = wp_generate_password(20, true, true);
      $user_id = wp_create_user($username, $password, $email);
      if (is_wp_error($user_id)) {
        return $user_id;
      }
      $user = get_user_by('id', $user_id);
      // Optionally set role/customer
      if (function_exists('wc_create_new_customer')) {
        // Ensure WooCommerce customer role is honored
        $user->set_role('customer');
      }
    }

    // Log the user in
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    do_action('wp_login', $user->user_login, $user);

    // Clear code
    delete_transient(self::key_for_email($email));
    return $user;
  }
}
