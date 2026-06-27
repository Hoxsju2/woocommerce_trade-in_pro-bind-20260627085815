<?php
if (!defined('ABSPATH')) { exit; }

class WTIP_WhatsApp {
  public static function init() {}

  public static function build_message($data) {
    $lines = [];
    $lines[] = 'New Trade-In Request';
    $lines[] = '--------------------';
    $lines[] = 'User: ' . ($data['user_email'] ?? '');
    $lines[] = 'New Product: ' . ($data['new_product_name'] ?? '');
    $lines[] = 'Link: ' . ($data['new_product_link'] ?? '');
    $lines[] = 'Old Product: ' . ($data['old_product_name'] ?? '');
    $lines[] = 'Condition: ' . ($data['condition'] ?? '');

    if (isset($data['expected_value'])) {
      $amount = number_format((float)$data['expected_value'], 2, '.', '');
      $cur = isset($data['currency']) ? strtoupper($data['currency']) : '';
      $lines[] = 'Expected Value: ' . $amount . ($cur ? ' ' . $cur : '');
    }

    if (!empty($data['images'])) {
      $lines[] = 'Images:';
      foreach ($data['images'] as $img) {
        $url = !empty($img['id']) ? WTIP_Helpers::encoded_attachment_url((int)$img['id']) : ($img['url'] ?? '');
        if ($url) {
          $lines[] = $url;
        }
      }
    }

    $message = implode("\n", $lines);
    $message = apply_filters('wtip_whatsapp_message', $message, $data);
    return $message;
  }

  public static function build_url($data) {
    $opts = WTIP_Helpers::get_options();
    if (empty($opts['enable_whatsapp']) || empty($opts['whatsapp_number'])) {
      return '';
    }
    $number = preg_replace('/\D+/', '', $opts['whatsapp_number']);
    $text = self::build_message($data);
    $url = 'https://wa.me/' . rawurlencode($number) . '?text=' . rawurlencode($text);
    $url = apply_filters('wtip_whatsapp_url', $url, $data);
    do_action('wtip_after_whatsapp_url_built', $data['post_id'] ?? 0, $url);
    return $url;
  }
}
