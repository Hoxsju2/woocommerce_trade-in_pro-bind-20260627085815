<?php
if (!defined('ABSPATH')) { exit; }

class WTIP_Plugin {
  public static function init() {
    add_shortcode('tradein_button', [__CLASS__, 'shortcode_button']);
    add_shortcode('tradein_requests', [__CLASS__, 'shortcode_user_requests']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
  }

  public static function enqueue_assets() {
    // Only enqueue on front end
    if (is_admin()) return;

    wp_register_style('wtip-css', WTIP_URL . 'assets/css/tradein.css', [], WTIP_VERSION);
    wp_register_script('wtip-js', WTIP_URL . 'assets/js/tradein-modal.js', ['jquery'], WTIP_VERSION, true);

    $opts = WTIP_Helpers::get_options();
    $conds = WTIP_Helpers::get_condition_options();
    $ctx = WTIP_Helpers::current_product_context();
    $data = [
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('wtip_nonce'),
      'is_logged_in' => is_user_logged_in(),
      'condition_options' => array_values($conds),
      'max_images' => (int)$opts['max_images'],
      'max_image_size_mb' => (int)$opts['max_image_size_mb'],
      'strings' => [
        'startTitle' => __('Start Your Trade-In', 'woocommerce-trade-in-pro'),
        'startSubtitle' => __('Trade in your old product for credit toward this new one', 'woocommerce-trade-in-pro'),
        'next' => __('Next', 'woocommerce-trade-in-pro'),
        'sendCode' => __('Send Verification Code', 'woocommerce-trade-in-pro'),
        'verify' => __('Verify and Continue', 'woocommerce-trade-in-pro'),
        'resend' => __('Resend', 'woocommerce-trade-in-pro'),
        'reviewTitle' => __('Review Your Trade-In', 'woocommerce-trade-in-pro'),
        'confirm' => __('Confirm Trade-In', 'woocommerce-trade-in-pro'),
        'edit' => __('Edit', 'woocommerce-trade-in-pro'),
        'consent' => __('I agree to the trade-in terms', 'woocommerce-trade-in-pro'),
        'savedProceed' => __('Trade-in saved to your profile. Shall I proceed with notifications?', 'woocommerce-trade-in-pro'),
        'yes' => __('Yes', 'woocommerce-trade-in-pro'),
        'no' => __('No', 'woocommerce-trade-in-pro'),
      ],
      'product' => [
        'title' => $ctx['title'],
        'link' => $ctx['link'],
        'id' => (int)$ctx['product_id'],
      ]
    ];

    wp_localize_script('wtip-js', 'WTIP_DATA', $data);
  }

  public static function shortcode_button($atts) {
    $opts = WTIP_Helpers::get_options();

    $text = isset($opts['button_text']) && $opts['button_text'] !== '' ? $opts['button_text'] : __('Trade-In Your Old Device', 'woocommerce-trade-in-pro');
    $bg = isset($opts['button_bg_color']) ? $opts['button_bg_color'] : '#111827';
    $fg = isset($opts['button_text_color']) ? $opts['button_text_color'] : '#ffffff';

    // Allow theme/plugin overrides
    $text = apply_filters('wtip_button_text', $text, $atts, $opts);
    $style = 'background:' . esc_attr($bg) . ';color:' . esc_attr($fg) . ';';

    wp_enqueue_style('wtip-css');
    wp_enqueue_script('wtip-js');

    ob_start();
    ?>
    <div class="wtip-container">
      <button class="wtip-trigger" style="<?php echo esc_attr($style); ?>"><?php echo esc_html($text); ?></button>
      <?php include WTIP_DIR . 'templates/modal.php'; ?>
    </div>
    <?php
    return ob_get_clean();
  }

  public static function shortcode_user_requests($atts) {
    // Front-end list of current user's trade-in requests
    wp_enqueue_style('wtip-css');

    if (!is_user_logged_in()) {
      $out  = '<div class="wtip-user-requests">';
      $out .= '<p>' . esc_html__('Please log in to view your trade-in requests.', 'woocommerce-trade-in-pro') . '</p>';
      $out .= '</div>';
      return $out;
    }

    $user_id = get_current_user_id();

    $q = new WP_Query([
      'post_type' => 'wtip_request',
      'author' => $user_id,
      'post_status' => ['wtip_pending', 'wtip_approved', 'wtip_rejected'],
      'posts_per_page' => -1,
      'orderby' => 'date',
      'order' => 'DESC',
      'no_found_rows' => true,
      'ignore_sticky_posts' => true,
    ]);

    $rows = '';
    if ($q->have_posts()) {
      while ($q->have_posts()) {
        $q->the_post();
        $post_id = get_the_ID();
        $new_name = get_post_meta($post_id, '_wtip_new_product_name', true);
        $new_link = get_post_meta($post_id, '_wtip_new_product_link', true);
        $old_name = get_post_meta($post_id, '_wtip_old_product_name', true);
        $condition = get_post_meta($post_id, '_wtip_condition', true);
        $value = (float)get_post_meta($post_id, '_wtip_expected_value', true);
        $status = get_post_status($post_id);

        $value_html = function_exists('wc_price') ? wc_price($value) : esc_html(number_format_i18n($value, 2));
        $status_label = self::status_label_front($status);

        $rows .= '<tr>';
        $rows .= '<td>' . esc_html(get_the_date()) . '</td>';
        $rows .= '<td>' . esc_html($old_name) . '</td>';
        $rows .= '<td>' . ($new_name ? '<a href="' . esc_url($new_link) . '" target="_blank" rel="noopener">' . esc_html($new_name) . '</a>' : '&ndash;') . '</td>';
        $rows .= '<td>' . esc_html($condition) . '</td>';
        $rows .= '<td>' . wp_kses_post($value_html) . '</td>';
        $rows .= '<td><span class="wtip-badge wtip-' . esc_attr($status) . '">' . esc_html($status_label) . '</span></td>';
        $rows .= '</tr>';
      }
      wp_reset_postdata();
    } else {
      $rows .= '<tr><td colspan="6">' . esc_html__('No trade-in requests yet.', 'woocommerce-trade-in-pro') . '</td></tr>';
    }

    $out  = '<div class="wtip-user-requests">';
    $out .= '<h3>' . esc_html__('My Trade-In Requests', 'woocommerce-trade-in-pro') . '</h3>';
    $out .= '<div class="wtip-table-wrap">';
    $out .= '<table class="wtip-table">';
    $out .= '<thead><tr>';
    $out .= '<th>' . esc_html__('Date', 'woocommerce-trade-in-pro') . '</th>';
    $out .= '<th>' . esc_html__('Old Product', 'woocommerce-trade-in-pro') . '</th>';
    $out .= '<th>' . esc_html__('New Product', 'woocommerce-trade-in-pro') . '</th>';
    $out .= '<th>' . esc_html__('Condition', 'woocommerce-trade-in-pro') . '</th>';
    $out .= '<th>' . esc_html__('Expected Value', 'woocommerce-trade-in-pro') . '</th>';
    $out .= '<th>' . esc_html__('Status', 'woocommerce-trade-in-pro') . '</th>';
    $out .= '</tr></thead>';
    $out .= '<tbody>' . $rows . '</tbody>';
    $out .= '</table>';
    $out .= '</div>';
    $out .= '</div>';

    return $out;
  }

  protected static function status_label_front($status) {
    switch ($status) {
      case 'wtip_pending': return __('Pending', 'woocommerce-trade-in-pro');
      case 'wtip_approved': return __('Approved', 'woocommerce-trade-in-pro');
      case 'wtip_rejected': return __('Rejected', 'woocommerce-trade-in-pro');
      default: return ucfirst(str_replace(['wc-', 'wtip_'], '', $status));
    }
  }
}
