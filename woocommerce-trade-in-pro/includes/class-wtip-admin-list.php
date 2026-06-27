<?php
if (!defined('ABSPATH')) { exit; }

class WTIP_Admin_List {
  public static function init() {
    add_filter('manage_wtip_request_posts_columns', [__CLASS__, 'columns']);
    add_action('manage_wtip_request_posts_custom_column', [__CLASS__, 'column_content'], 10, 2);
    add_action('restrict_manage_posts', [__CLASS__, 'filters']);
    add_filter('views_edit-wtip_request', [__CLASS__, 'views']);
    add_action('pre_get_posts', [__CLASS__, 'pre_get_posts']);
    add_action('admin_init', [__CLASS__, 'handle_export']);
  }

  public static function columns($cols) {
    $new = [];
    $new['cb'] = $cols['cb'] ?? '';
    $new['title'] = __('Title', 'woocommerce-trade-in-pro');
    $new['user'] = __('User', 'woocommerce-trade-in-pro');
    $new['product'] = __('New Product', 'woocommerce-trade-in-pro');
    $new['old_product'] = __('Old Product', 'woocommerce-trade-in-pro');
    $new['condition'] = __('Condition', 'woocommerce-trade-in-pro');
    $new['value'] = __('Expected Value', 'woocommerce-trade-in-pro');
    $new['date'] = $cols['date'] ?? __('Date', 'woocommerce-trade-in-pro');
    return $new;
  }

  public static function column_content($col, $post_id) {
    switch ($col) {
      case 'user':
        $author_id = get_post_field('post_author', $post_id);
        $user = $author_id ? get_user_by('id', $author_id) : null;
        echo $user ? esc_html($user->user_email) : '&ndash;';
        break;
      case 'product':
        $name = get_post_meta($post_id, '_wtip_new_product_name', true);
        $link = get_post_meta($post_id, '_wtip_new_product_link', true);
        if ($name) {
          echo '<a href="' . esc_url($link) . '" target="_blank" rel="noopener">' . esc_html($name) . '</a>';
        } else {
          echo '&ndash;';
        }
        break;
      case 'old_product':
        echo esc_html(get_post_meta($post_id, '_wtip_old_product_name', true));
        break;
      case 'condition':
        echo esc_html(get_post_meta($post_id, '_wtip_condition', true));
        break;
      case 'value':
        $val = (float)get_post_meta($post_id, '_wtip_expected_value', true);
        $cur = strtoupper(get_post_meta($post_id, '_wtip_currency', true));
        $cur = $cur ?: '';
        echo esc_html(number_format($val, 2, '.', '') . ($cur ? ' ' . $cur : ''));
        break;
    }
  }

  public static function filters() {
    global $typenow;
    if ($typenow !== 'wtip_request') return;

    $email_raw = isset($_GET['wtip_user_email']) && is_string($_GET['wtip_user_email']) ? sanitize_email(wp_unslash($_GET['wtip_user_email'])) : '';
    echo '<input type="search" name="wtip_user_email" placeholder="' . esc_attr__('Filter by user email', 'woocommerce-trade-in-pro') . '" value="' . esc_attr($email_raw) . '" />';

    $base = admin_url('edit.php');
    $args = [
      'post_type' => 'wtip_request',
      'wtip_export' => '1',
    ];

    $preserve = ['m', 's', 'orderby', 'order', 'post_status', 'wtip_user_email'];
    foreach ($preserve as $key) {
      if (isset($_GET[$key]) && $_GET[$key] !== '') {
        $args[$key] = is_array($_GET[$key]) ? array_map('sanitize_text_field', wp_unslash($_GET[$key])) : sanitize_text_field(wp_unslash($_GET[$key]));
      }
    }

    $url = add_query_arg($args, $base);
    $url = wp_nonce_url($url, 'wtip_export', 'wtip_export_nonce');

    echo ' <a href="' . esc_url($url) . '" class="button button-primary" style="margin-left:8px;">' . esc_html__('Export CSV', 'woocommerce-trade-in-pro') . '</a>';

    add_filter('parse_query', function ($query) use ($email_raw) {
      global $pagenow;
      if ($pagenow === 'edit.php' && $query->is_main_query() && isset($_GET['post_type']) && is_string($_GET['post_type']) && $_GET['post_type'] === 'wtip_request' && !empty($email_raw)) {
        $query->set('meta_query', [
          [
            'key' => '_wtip_user_email',
            'value' => $email_raw,
            'compare' => 'LIKE',
          ]
        ]);
      }
    });
  }

  public static function views($views) {
    global $typenow;
    if ($typenow !== 'wtip_request') return $views;

    $counts = wp_count_posts('wtip_request');
    $current = isset($_GET['post_status']) && is_string($_GET['post_status']) ? sanitize_key($_GET['post_status']) : '';
    $base = admin_url('edit.php?post_type=wtip_request');

    $custom = [];

    $statuses = [
      'wtip_pending' => __('Pending', 'woocommerce-trade-in-pro'),
      'wtip_approved' => __('Approved', 'woocommerce-trade-in-pro'),
      'wtip_rejected' => __('Rejected', 'woocommerce-trade-in-pro'),
    ];

    foreach ($statuses as $st => $label) {
      $count = isset($counts->$st) ? (int)$counts->$st : 0;
      $class = $current === $st ? 'class="current"' : '';
      $url = esc_url(add_query_arg(['post_status' => $st], $base));
      $custom[$st] = sprintf(
        '<a href="%s" %s>%s <span class="count">(%d)</span></a>',
        $url,
        $class,
        esc_html($label),
        $count
      );
    }

    return array_merge($views, $custom);
  }

  public static function pre_get_posts($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    global $pagenow;
    if ($pagenow !== 'edit.php') return;
    $post_type = isset($_GET['post_type']) && is_string($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
    if ($post_type !== 'wtip_request') return;

    if (empty($_GET['post_status'])) {
      $query->set('post_status', ['wtip_pending', 'wtip_approved', 'wtip_rejected']);
    }
  }

  public static function handle_export() {
    if (!is_admin()) return;
    if (empty($_GET['wtip_export'])) return;

    global $pagenow;
    $post_type = isset($_GET['post_type']) && is_string($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
    if ($pagenow !== 'edit.php' || $post_type !== 'wtip_request') return;

    if (!current_user_can('manage_woocommerce')) {
      wp_die(__('You do not have permission to export.', 'woocommerce-trade-in-pro'));
    }
    $nonce = isset($_GET['wtip_export_nonce']) && is_string($_GET['wtip_export_nonce']) ? sanitize_text_field(wp_unslash($_GET['wtip_export_nonce'])) : (isset($_GET['_wpnonce']) && is_string($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '');
    if (!$nonce || !wp_verify_nonce($nonce, 'wtip_export')) {
      wp_die(__('Security check failed.', 'woocommerce-trade-in-pro'));
    }

    $q_args = [
      'post_type' => 'wtip_request',
      'posts_per_page' => -1,
      'fields' => 'ids',
      'orderby' => 'date',
      'order' => 'DESC',
    ];

    if (!empty($_GET['post_status']) && is_string($_GET['post_status'])) {
      $st = sanitize_text_field(wp_unslash($_GET['post_status']));
      $allowed = ['wtip_pending', 'wtip_approved', 'wtip_rejected'];
      if (in_array($st, $allowed, true)) {
        $q_args['post_status'] = [$st];
      }
    } else {
      $q_args['post_status'] = ['wtip_pending', 'wtip_approved', 'wtip_rejected'];
    }

    if (!empty($_GET['m']) && is_scalar($_GET['m'])) {
      $q_args['m'] = (int) $_GET['m'];
    }

    if (!empty($_GET['s']) && is_string($_GET['s'])) {
      $q_args['s'] = sanitize_text_field(wp_unslash($_GET['s']));
    }

    if (!empty($_GET['wtip_user_email']) && is_string($_GET['wtip_user_email'])) {
      $email = sanitize_email(wp_unslash($_GET['wtip_user_email']));
      $q_args['meta_query'] = [
        [
          'key' => '_wtip_user_email',
          'value' => $email,
          'compare' => 'LIKE',
        ]
      ];
    }

    $q = new WP_Query($q_args);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    $filename = 'trade-in-requests-' . date('Ymd-His') . '.csv';
    header('Content-Disposition: attachment; filename=' . $filename);
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, [
      'ID',
      'Date',
      'Status',
      'User Email',
      'New Product Name',
      'New Product Link',
      'Woo Product ID',
      'Old Product',
      'Condition',
      'Expected Value',
      'Currency',
      'Consent',
      'Image URLs',
    ]);

    if ($q->have_posts()) {
      foreach ($q->posts as $post_id) {
        $date = get_the_date('Y-m-d H:i:s', $post_id);
        $status = get_post_status($post_id);

        $user_email = get_post_meta($post_id, '_wtip_user_email', true);
        if (!$user_email) {
          $author_id = get_post_field('post_author', $post_id);
          $user = $author_id ? get_user_by('id', $author_id) : null;
          $user_email = $user ? $user->user_email : '';
        }

        $new_name = (string) get_post_meta($post_id, '_wtip_new_product_name', true);
        $new_link = (string) get_post_meta($post_id, '_wtip_new_product_link', true);
        $woo_product_id = (int) get_post_meta($post_id, '_wtip_product_id', true);
        $old_name = (string) get_post_meta($post_id, '_wtip_old_product_name', true);
        $condition = (string) get_post_meta($post_id, '_wtip_condition', true);
        $expected_value = (float) get_post_meta($post_id, '_wtip_expected_value', true);
        $currency = strtoupper((string) get_post_meta($post_id, '_wtip_currency', true));
        $currency = $currency ?: '';

        $image_ids = (array) get_post_meta($post_id, '_wtip_image_ids', true);
        $urls = [];
        foreach ($image_ids as $aid) {
          $aid = (int) $aid;
          if (method_exists('WTIP_Helpers', 'encoded_attachment_url')) {
            $u = WTIP_Helpers::encoded_attachment_url($aid);
          } else {
            $u = wp_get_attachment_url($aid);
          }
          if ($u) $urls[] = $u;
        }
        $image_urls = implode(' | ', $urls);

        fputcsv($out, [
          $post_id,
          $date,
          ucfirst(str_replace(['wtip_'], '', $status)),
          $user_email,
          $new_name,
          $new_link,
          $woo_product_id,
          $old_name,
          $condition,
          number_format((float)$expected_value, 2, '.', ''),
          $currency,
          get_post_meta($post_id, '_wtip_consent', true) === 'yes' ? 'yes' : 'no',
          $image_urls,
        ]);
      }
    }

    fclose($out);
    exit;
  }
}
