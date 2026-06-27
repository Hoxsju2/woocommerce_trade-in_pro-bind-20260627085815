<?php
if (!defined('ABSPATH')) { exit; }

class WTIP_Metaboxes {
  public static function init() {
    add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
    add_action('save_post_wtip_request', [__CLASS__, 'save_meta_boxes']);
  }

  public static function add_meta_boxes() {
    add_meta_box(
      'wtip_details',
      __('Trade-In Details', 'woocommerce-trade-in-pro'),
      [__CLASS__, 'render_details'],
      'wtip_request',
      'normal',
      'high'
    );

    add_meta_box(
      'wtip_images',
      __('Trade-In Images', 'woocommerce-trade-in-pro'),
      [__CLASS__, 'render_images'],
      'wtip_request',
      'normal',
      'default'
    );

    add_meta_box(
      'wtip_status',
      __('Trade-In Status', 'woocommerce-trade-in-pro'),
      [__CLASS__, 'render_status'],
      'wtip_request',
      'side',
      'high'
    );
  }

  protected static function get_request_data($post_id) {
    $data = [];
    $data['new_product_name'] = get_post_meta($post_id, '_wtip_new_product_name', true);
    $data['new_product_link'] = get_post_meta($post_id, '_wtip_new_product_link', true);
    $data['product_id'] = (int) get_post_meta($post_id, '_wtip_product_id', true);
    $data['old_product_name'] = get_post_meta($post_id, '_wtip_old_product_name', true);
    $data['condition'] = get_post_meta($post_id, '_wtip_condition', true);
    $data['expected_value'] = (float) get_post_meta($post_id, '_wtip_expected_value', true);
    $data['currency'] = strtoupper((string) get_post_meta($post_id, '_wtip_currency', true));
    $data['image_ids'] = (array) get_post_meta($post_id, '_wtip_image_ids', true);
    $data['consent'] = get_post_meta($post_id, '_wtip_consent', true) === 'yes';
    $data['user_email'] = get_post_meta($post_id, '_wtip_user_email', true);

    $data['images'] = [];
    foreach ($data['image_ids'] as $aid) {
      $url = wp_get_attachment_url($aid);
      if ($url) {
        $data['images'][] = ['id' => (int) $aid, 'url' => $url];
      }
    }

    return $data;
  }

  public static function render_details($post) {
    wp_nonce_field('wtip_meta', 'wtip_meta_nonce');

    $data = self::get_request_data($post->ID);
    $status = get_post_status($post);

    $val_text = number_format((float)$data['expected_value'], 2, '.', '') . ($data['currency'] ? ' ' . $data['currency'] : '');

    $wa_url = '';
    if (class_exists('WTIP_WhatsApp')) {
      $wa_data = [
        'new_product_name' => $data['new_product_name'],
        'new_product_link' => $data['new_product_link'],
        'old_product_name' => $data['old_product_name'],
        'condition' => $data['condition'],
        'expected_value' => $data['expected_value'],
        'currency' => $data['currency'],
        'images' => $data['images'],
        'user_email' => $data['user_email'],
        'post_id' => $post->ID,
      ];
      $wa_url = WTIP_WhatsApp::build_url($wa_data);
    }

    $author_id = (int) get_post_field('post_author', $post);
    $user = $author_id ? get_user_by('id', $author_id) : null;
    $user_label = $data['user_email'] ?: ($user ? $user->user_email : '');

    echo '<table class="widefat striped" style="margin-top:8px">';
    echo '<tbody>';

    echo '<tr><th style="width:200px">' . esc_html__('User Email', 'woocommerce-trade-in-pro') . '</th><td>';
    if ($user_label) {
      if ($user) {
        $edit_link = get_edit_user_link($user->ID);
        echo '<a href="' . esc_url($edit_link) . '">' . esc_html($user_label) . '</a>';
      } else {
        echo esc_html($user_label);
      }
    } else {
      echo '&ndash;';
    }
    echo '</td></tr>';

    echo '<tr><th>' . esc_html__('New Product', 'woocommerce-trade-in-pro') . '</th><td>';
    if (!empty($data['new_product_name'])) {
      $link = $data['new_product_link'] ? esc_url($data['new_product_link']) : '#';
      echo esc_html($data['new_product_name']) . ' &mdash; ';
      if ($link && $link !== '#') {
        echo '<a href="' . $link . '" target="_blank" rel="noopener">' . esc_html__('View Product', 'woocommerce-trade-in-pro') . '</a>';
      } else {
        echo '<span>' . esc_html__('No link', 'woocommerce-trade-in-pro') . '</span>';
      }
    } else {
      echo '&ndash;';
    }
    echo '</td></tr>';

    echo '<tr><th>' . esc_html__('Old Product', 'woocommerce-trade-in-pro') . '</th><td>' . esc_html($data['old_product_name']) . '</td></tr>';
    echo '<tr><th>' . esc_html__('Condition', 'woocommerce-trade-in-pro') . '</th><td>' . esc_html($data['condition']) . '</td></tr>';
    echo '<tr><th>' . esc_html__('Expected Value', 'woocommerce-trade-in-pro') . '</th><td>' . esc_html($val_text) . '</td></tr>';
    echo '<tr><th>' . esc_html__('Woo Product ID', 'woocommerce-trade-in-pro') . '</th><td>' . ($data['product_id'] ? intval($data['product_id']) : '&ndash;') . '</td></tr>';
    echo '<tr><th>' . esc_html__('Consent', 'woocommerce-trade-in-pro') . '</th><td>' . ($data['consent'] ? esc_html__('Yes', 'woocommerce-trade-in-pro') : esc_html__('No', 'woocommerce-trade-in-pro')) . '</td></tr>';
    echo '<tr><th>' . esc_html__('Status', 'woocommerce-trade-in-pro') . '</th><td>' . esc_html(self::status_label($status)) . '</td></tr>';

    if (!empty($wa_url)) {
      echo '<tr><th>' . esc_html__('WhatsApp', 'woocommerce-trade-in-pro') . '</th><td>';
      echo '<a class="button button-secondary" href="' . esc_url($wa_url) . '" target="_blank" rel="noopener">' . esc_html__('Open WhatsApp with details', 'woocommerce-trade-in-pro') . '</a>';
      echo '</td></tr>';
    }

    echo '</tbody>';
    echo '</table>';
  }

  public static function render_images($post) {
    $ids = (array) get_post_meta($post->ID, '_wtip_image_ids', true);
    if (empty($ids)) {
      echo '<p>' . esc_html__('No images uploaded for this request.', 'woocommerce-trade-in-pro') . '</p>';
      return;
    }

    echo '<div style="display:flex; flex-wrap:wrap; gap:10px;">';
    foreach ($ids as $aid) {
      $aid = (int) $aid;
      $thumb = wp_get_attachment_image($aid, 'thumbnail', false, ['style' => 'border:1px solid #e5e7eb;border-radius:6px;']);
      $edit_link = get_edit_post_link($aid);
      $full = wp_get_attachment_url($aid);
      echo '<div style="text-align:center">';
      if ($edit_link) {
        echo '<a href="' . esc_url($edit_link) . '" target="_blank" rel="noopener">' . $thumb . '</a>';
      } else {
        echo $thumb;
      }
      if ($full) {
        echo '<div><a href="' . esc_url($full) . '" target="_blank" rel="noopener">' . esc_html__('View full', 'woocommerce-trade-in-pro') . '</a></div>';
      }
      echo '</div>';
    }
    echo '</div>';
  }

  public static function render_status($post) {
    $current = get_post_status($post);
    wp_nonce_field('wtip_meta', 'wtip_meta_nonce');
    $options = [
      'wtip_pending' => __('Pending', 'woocommerce-trade-in-pro'),
      'wtip_approved' => __('Approved', 'woocommerce-trade-in-pro'),
      'wtip_rejected' => __('Rejected', 'woocommerce-trade-in-pro'),
    ];
    echo '<p>' . esc_html__('Change the trade-in request status and click Update.', 'woocommerce-trade-in-pro') . '</p>';
    echo '<select name="wtip_update_status" class="widefat">';
    foreach ($options as $key => $label) {
      echo '<option value="' . esc_attr($key) . '"' . selected($current, $key, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
  }

  public static function save_meta_boxes($post_id) {
    if (!isset($_POST['wtip_meta_nonce']) || !wp_verify_nonce($_POST['wtip_meta_nonce'], 'wtip_meta')) {
      return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return;
    }
    if (!current_user_can('edit_post', $post_id)) {
      return;
    }

    if (isset($_POST['wtip_update_status']) && is_string($_POST['wtip_update_status'])) {
      $new_status = sanitize_key($_POST['wtip_update_status']);
      $allowed = ['wtip_pending', 'wtip_approved', 'wtip_rejected'];
      if (in_array($new_status, $allowed, true)) {
        $current = get_post_status($post_id);
        if ($current !== $new_status) {
          remove_action('save_post_wtip_request', [__CLASS__, 'save_meta_boxes']);
          wp_update_post([
            'ID' => $post_id,
            'post_status' => $new_status,
          ]);
          add_action('save_post_wtip_request', [__CLASS__, 'save_meta_boxes']);
        }
      }
    }
  }

  protected static function status_label($status) {
    switch ($status) {
      case 'wtip_pending': return __('Pending', 'woocommerce-trade-in-pro');
      case 'wtip_approved': return __('Approved', 'woocommerce-trade-in-pro');
      case 'wtip_rejected': return __('Rejected', 'woocommerce-trade-in-pro');
      default: return ucfirst(str_replace(['wc-', 'wtip_'], '', (string) $status));
    }
  }
}
