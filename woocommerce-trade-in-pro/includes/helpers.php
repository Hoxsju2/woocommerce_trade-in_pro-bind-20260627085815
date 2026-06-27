<?php
if (!defined('ABSPATH')) { exit; }

class WTIP_Helpers {
  public static function get_options() {
    $defaults = [
      'admin_email' => get_option('admin_email'),
      'admin_emails' => '',
      'whatsapp_number' => '',
      'condition_options' => "New\nUsed - Good\nUsed - Fair\nUsed - Poor/Bad",
      'enable_magic_login' => 1,
      'enable_whatsapp' => 1,
      'max_images' => 5,
      'max_image_size_mb' => 5,
      // Button customization defaults
      'button_text' => __('Trade-In Your Old Device', 'woocommerce-trade-in-pro'),
      'button_bg_color' => '#111827',
      'button_text_color' => '#ffffff',
      // Placeholders defaults
      'placeholder_old_product' => __('e.g., iPhone 11 128GB', 'woocommerce-trade-in-pro'),
      'placeholder_email' => __('you@domain.com', 'woocommerce-trade-in-pro'),
      'placeholder_code' => __('123456', 'woocommerce-trade-in-pro'),
      'placeholder_expected_value' => __('0.00', 'woocommerce-trade-in-pro'),
    ];
    $opts = get_option('wtip_options', []);
    return wp_parse_args($opts, $defaults);
  }

  public static function get_condition_options() {
    $opts = self::get_options();
    $lines = array_filter(array_map('trim', explode("\n", (string)$opts['condition_options'])));
    $lines = apply_filters('wtip_condition_options', $lines);
    if (empty($lines)) {
      $lines = ['New', 'Used - Good', 'Used - Fair', 'Used - Poor/Bad'];
    }
    return $lines;
  }

  public static function sanitize_value($val) {
    $val = preg_replace('/[^\d\.\,]/', '', (string)$val);
    $val = str_replace(',', '.', $val);
    return round((float)$val, 2);
  }

  public static function validate_image_file($file, $max_mb = 5) {
    if (empty($file['tmp_name'])) {
      return new WP_Error('wtip_empty_file', __('File is empty or not uploaded correctly.', 'woocommerce-trade-in-pro'));
    }

    // Secure file type checking instead of trusting $_FILES array
    $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], [
      'jpg'  => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'png'  => 'image/png'
    ]);

    if (!$check['ext'] || !$check['type']) {
      return new WP_Error('wtip_invalid_type', __('Invalid file type. Only JPEG/PNG allowed.', 'woocommerce-trade-in-pro'));
    }

    $max_bytes = (int)$max_mb * 1024 * 1024;
    if (!empty($file['size']) && (int)$file['size'] > $max_bytes) {
      return new WP_Error('wtip_too_large', sprintf(__('File too large. Max %dMB.', 'woocommerce-trade-in-pro'), (int)$max_mb));
    }
    return true;
  }

  public static function attach_media_from_uploads($field_name, $max_images, $max_mb) {
    if (empty($_FILES[$field_name])) {
      return [];
    }

    $files = $_FILES[$field_name];
    $attachments = [];
    $count = is_array($files['name']) ? count($files['name']) : 0;
    $count = min($count, (int)$max_images);

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    for ($i = 0; $i < $count; $i++) {
      $file = [
        'name' => $files['name'][$i] ?? '',
        'type' => $files['type'][$i] ?? '',
        'tmp_name' => $files['tmp_name'][$i] ?? '',
        'error' => $files['error'][$i] ?? 0,
        'size' => $files['size'][$i] ?? 0,
      ];
      $valid = self::validate_image_file($file, $max_mb);
      if (is_wp_error($valid)) {
        continue;
      }
      if ($file['error'] !== UPLOAD_ERR_OK) {
        continue;
      }
      $overrides = ['test_form' => false];
      $movefile = wp_handle_upload($file, $overrides);
      if ($movefile && !isset($movefile['error'])) {
        $attachment = [
          'post_mime_type' => $movefile['type'],
          'post_title' => sanitize_file_name($file['name']),
          'post_content' => '',
          'post_status' => 'inherit'
        ];
        $attach_id = wp_insert_attachment($attachment, $movefile['file']);
        if (!is_wp_error($attach_id)) {
          $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
          wp_update_attachment_metadata($attach_id, $attach_data);
          $attachments[] = (int)$attach_id;
        }
      }
    }
    return $attachments;
  }

  public static function current_product_context() {
    global $product, $post;
    $title = '';
    $link = '';
    $product_id = 0;

    if ($product && is_a($product, 'WC_Product')) {
      $title = $product->get_name();
      $product_id = $product->get_id();
      $link = get_permalink($product_id);
    } elseif ($post) {
      $title = get_the_title($post);
      $product_id = (int)$post->ID;
      $link = get_permalink($post);
    }
    return [
      'title' => (string)$title,
      'link' => (string)$link,
      'product_id' => $product_id,
    ];
  }

  public static function ensure_json() {
    nocache_headers();
    header('Content-Type: application/json; charset=' . get_option('blog_charset'));
  }

  public static function create_draft_key() {
    $key = wp_generate_password(20, false, false);
    setcookie('wtip_draft', $key, time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    return $key;
  }

  public static function get_draft_key() {
    return isset($_COOKIE['wtip_draft']) && is_string($_COOKIE['wtip_draft']) ? sanitize_text_field(wp_unslash($_COOKIE['wtip_draft'])) : '';
  }

  public static function set_draft_data($data) {
    $key = self::get_draft_key();
    if (!$key) {
      $key = self::create_draft_key();
    }
    set_transient('wtip_draft_' . $key, $data, HOUR_IN_SECONDS);
    return $key;
  }

  public static function get_draft_data() {
    $key = self::get_draft_key();
    if (!$key) return null;
    return get_transient('wtip_draft_' . $key);
  }

  public static function clear_draft() {
    $key = self::get_draft_key();
    if ($key) {
      delete_transient('wtip_draft_' . $key);
      setcookie('wtip_draft', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    }
  }

  public static function encoded_attachment_url($attachment_id) {
    $url = wp_get_attachment_url($attachment_id);
    if (!$url) return '';

    $p = wp_parse_url($url);
    if (empty($p['scheme']) || empty($p['host'])) {
      return $url;
    }

    $path = '';
    if (!empty($p['path'])) {
      $segments = explode('/', ltrim($p['path'], '/'));
      $segments = array_map(function ($seg) {
        return rawurlencode(rawurldecode($seg));
      }, $segments);
      $path = '/' . implode('/', $segments);
    }

    $host = $p['host'];
    if (!empty($p['port'])) {
      $host .= ':' . $p['port'];
    }

    $rebuilt = $p['scheme'] . '://' . $host . $path;
    if (!empty($p['query'])) {
      $rebuilt .= '?' . $p['query'];
    }
    if (!empty($p['fragment'])) {
      $rebuilt .= '#' . $p['fragment'];
    }
    return $rebuilt;
  }

  public static function map_images($attachment_ids) {
    $out = [];
    foreach ((array)$attachment_ids as $id) {
      $encoded = self::encoded_attachment_url($id);
      if ($encoded) {
        $out[] = ['id' => (int)$id, 'url' => $encoded];
      }
    }
    return $out;
  }

  public static function parse_admin_emails($opts) {
    $all = [];
    $list = '';
    if (!empty($opts['admin_emails'])) {
      $list = (string)$opts['admin_emails'];
    } elseif (!empty($opts['admin_email'])) {
      $list = (string)$opts['admin_email'];
    }
    if ($list !== '') {
      $parts = preg_split('/[\s,]+/', $list);
      foreach ($parts as $p) {
        $e = sanitize_email($p);
        if ($e && is_email($e)) $all[] = $e;
      }
    }
    if (empty($all)) {
      $admin = get_option('admin_email');
      if ($admin) $all[] = $admin;
    }
    return array_values(array_unique($all));
  }
}
