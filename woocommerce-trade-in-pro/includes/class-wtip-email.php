<?php
if (!defined('ABSPATH')) { exit; }

class WTIP_Email {
  public static function init() {}

  public static function send_admin_notification($post_id, $data) {
    $opts = WTIP_Helpers::get_options();
    $recipients = WTIP_Helpers::parse_admin_emails($opts);
    $subject = apply_filters('wtip_email_subject', sprintf(__('New Trade-In Request: %s', 'woocommerce-trade-in-pro'), $data['new_product_name']), $data);

    $images_html = '';
    if (!empty($data['images'])) {
      foreach ($data['images'] as $img) {
        $url = esc_url($img['url']);
        $images_html .= '<div style="margin:4px 8px;display:inline-block;"><img src="' . $url . '" style="max-width:120px;height:auto;border:1px solid #ddd;padding:2px;border-radius:4px;" /></div>';
      }
    }

    $admin_url = admin_url('post.php?post=' . $post_id . '&action=edit');

    $val_num = isset($data['expected_value']) ? (float)$data['expected_value'] : 0;
    $currency = isset($data['currency']) ? strtoupper($data['currency']) : '';
    $val_text = number_format((float)$val_num, 2, '.', '') . ($currency ? ' ' . $currency : '');

    $body = '<h2>' . esc_html__('New Trade-In Request', 'woocommerce-trade-in-pro') . '</h2>'
      . '<p><strong>' . esc_html__('User', 'woocommerce-trade-in-pro') . ':</strong> ' . esc_html($data['user_email'] ?? '') . '</p>'
      . '<p><strong>' . esc_html__('New Product', 'woocommerce-trade-in-pro') . ':</strong> ' . esc_html($data['new_product_name']) . ' &middot; <a href="' . esc_url($data['new_product_link']) . '">' . esc_html__('View Product', 'woocommerce-trade-in-pro') . '</a></p>'
      . '<p><strong>' . esc_html__('Old Product', 'woocommerce-trade-in-pro') . ':</strong> ' . esc_html($data['old_product_name']) . '</p>'
      . '<p><strong>' . esc_html__('Condition', 'woocommerce-trade-in-pro') . ':</strong> ' . esc_html($data['condition']) . '</p>'
      . '<p><strong>' . esc_html__('Expected Value', 'woocommerce-trade-in-pro') . ':</strong> ' . esc_html($val_text) . '</p>'
      . (!empty($images_html) ? '<p><strong>' . esc_html__('Images', 'woocommerce-trade-in-pro') . ':</strong><br/>' . $images_html . '</p>' : '')
      . '<p><a href="' . esc_url($admin_url) . '">' . esc_html__('Review Request in Dashboard', 'woocommerce-trade-in-pro') . '</a></p>';

    $body = apply_filters('wtip_email_body', $body, $data);

    add_filter('wp_mail_content_type', [__CLASS__, 'mail_content_type']);
    $sent = wp_mail($recipients, $subject, $body);
    remove_filter('wp_mail_content_type', [__CLASS__, 'mail_content_type']);

    do_action('wtip_after_admin_notified', $post_id, $sent);
    return (bool)$sent;
  }

  public static function mail_content_type() {
    return 'text/html';
  }
}
