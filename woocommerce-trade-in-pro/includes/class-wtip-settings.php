<?php
if (!defined('ABSPATH')) { exit; }

class WTIP_Settings {
  public static function init() {
    add_action('admin_menu', [__CLASS__, 'admin_menu']);
    add_action('admin_init', [__CLASS__, 'register_settings']);
  }

  public static function admin_menu() {
    add_submenu_page(
      'woocommerce',
      __('Trade-In Pro', 'woocommerce-trade-in-pro'),
      __('Trade-In Pro', 'woocommerce-trade-in-pro'),
      'manage_woocommerce',
      'wtip-settings',
      [__CLASS__, 'render_settings_page']
    );

    add_submenu_page(
      'woocommerce',
      __('Trade-In Requests', 'woocommerce-trade-in-pro'),
      __('Trade-In Requests', 'woocommerce-trade-in-pro'),
      'manage_woocommerce',
      'edit.php?post_type=wtip_request'
    );
  }

  public static function register_settings() {
    register_setting('wtip_options_group', 'wtip_options', [__CLASS__, 'sanitize']);

    add_settings_section('wtip_section_main', __('General Settings', 'woocommerce-trade-in-pro'), function () {
      echo '<p>' . esc_html__('Configure core options for the trade-in flow.', 'woocommerce-trade-in-pro') . '</p>';
    }, 'wtip-settings');

    add_settings_field('admin_emails', __('Admin Emails', 'woocommerce-trade-in-pro'), [__CLASS__, 'field_admin_emails'], 'wtip-settings', 'wtip_section_main');
    add_settings_field('whatsapp_number', __('WhatsApp Number', 'woocommerce-trade-in-pro'), [__CLASS__, 'field_whatsapp'], 'wtip-settings', 'wtip_section_main');
    add_settings_field('condition_options', __('Condition Options', 'woocommerce-trade-in-pro'), [__CLASS__, 'field_conditions'], 'wtip-settings', 'wtip_section_main');
    add_settings_field('toggles', __('Toggles', 'woocommerce-trade-in-pro'), [__CLASS__, 'field_toggles'], 'wtip-settings', 'wtip_section_main');

    add_settings_section('wtip_section_button', __('Shortcode & Button', 'woocommerce-trade-in-pro'), function () {
      echo '<p>' . esc_html__('Copy the shortcode and customize the trigger button text and colors.', 'woocommerce-trade-in-pro') . '</p>';
    }, 'wtip-settings');

    add_settings_field('shortcode_display', __('Shortcode', 'woocommerce-trade-in-pro'), [__CLASS__, 'field_shortcode_display'], 'wtip-settings', 'wtip_section_button');
    add_settings_field('button_text', __('Button Text', 'woocommerce-trade-in-pro'), [__CLASS__, 'field_button_text'], 'wtip-settings', 'wtip_section_button');
    add_settings_field('button_bg_color', __('Button Background', 'woocommerce-trade-in-pro'), [__CLASS__, 'field_button_bg_color'], 'wtip-settings', 'wtip_section_button');
    add_settings_field('button_text_color', __('Button Text Color', 'woocommerce-trade-in-pro'), [__CLASS__, 'field_button_text_color'], 'wtip-settings', 'wtip_section_button');

    add_settings_section('wtip_section_placeholders', __('Placeholders', 'woocommerce-trade-in-pro'), function () {
      echo '<p>' . esc_html__('Customize placeholder texts shown in the trade-in modal.', 'woocommerce-trade-in-pro') . '</p>';
    }, 'wtip-settings');

    add_settings_field('placeholder_old_product', __('Old Product Placeholder', 'woocommerce-trade-in-pro'), [__CLASS__, 'field_placeholder_old_product'], 'wtip-settings', 'wtip_section_placeholders');
    add_settings_field('placeholder_expected_value', __('Expected Value Placeholder', 'woocommerce-trade-in-pro'), [__CLASS__, 'field_placeholder_expected_value'], 'wtip-settings', 'wtip_section_placeholders');
    add_settings_field('placeholder_email', __('Email Placeholder', 'woocommerce-trade-in-pro'), [__CLASS__, 'field_placeholder_email'], 'wtip-settings', 'wtip_section_placeholders');
    add_settings_field('placeholder_code', __('Verification Code Placeholder', 'woocommerce-trade-in-pro'), [__CLASS__, 'field_placeholder_code'], 'wtip-settings', 'wtip_section_placeholders');
  }

  public static function sanitize($input) {
    $out = [];
    // Admin emails (comma/newline separated)
    if (isset($input['admin_emails']) && is_string($input['admin_emails'])) {
      $raw = $input['admin_emails'];
      $parts = preg_split('/[\s,]+/', $raw);
      $clean = [];
      foreach ($parts as $p) {
        $e = sanitize_email($p);
        if ($e && is_email($e)) $clean[] = $e;
      }
      $clean = array_values(array_unique($clean));
      $out['admin_emails'] = implode(', ', $clean);
      // keep first as legacy admin_email for backward compatibility
      $out['admin_email'] = isset($clean[0]) ? $clean[0] : '';
    } else {
      $out['admin_emails'] = '';
      $out['admin_email'] = '';
    }

    $out['whatsapp_number'] = isset($input['whatsapp_number']) && is_string($input['whatsapp_number']) ? preg_replace('/\D+/', '', $input['whatsapp_number']) : '';
    $cond = isset($input['condition_options']) && is_string($input['condition_options']) ? $input['condition_options'] : '';
    $out['condition_options'] = implode("\n", array_filter(array_map('sanitize_text_field', array_map('trim', explode("\n", $cond)))));
    $out['enable_magic_login'] = !empty($input['enable_magic_login']) ? 1 : 0;
    $out['enable_whatsapp'] = !empty($input['enable_whatsapp']) ? 1 : 0;
    $out['max_images'] = isset($input['max_images']) && is_scalar($input['max_images']) ? max(1, min(5, (int)$input['max_images'])) : 5;
    $out['max_image_size_mb'] = isset($input['max_image_size_mb']) && is_scalar($input['max_image_size_mb']) ? max(1, min(20, (int)$input['max_image_size_mb'])) : 5;

    // Button customization
    $btn_text = isset($input['button_text']) && is_string($input['button_text']) ? $input['button_text'] : '';
    $out['button_text'] = sanitize_text_field($btn_text);
    
    $bg_col = isset($input['button_bg_color']) && is_string($input['button_bg_color']) ? $input['button_bg_color'] : '#111827';
    $out['button_bg_color'] = self::sanitize_hex_color_fallback($bg_col, '#111827');
    
    $txt_col = isset($input['button_text_color']) && is_string($input['button_text_color']) ? $input['button_text_color'] : '#ffffff';
    $out['button_text_color'] = self::sanitize_hex_color_fallback($txt_col, '#ffffff');

    // Placeholders
    $ph_old = isset($input['placeholder_old_product']) && is_string($input['placeholder_old_product']) ? $input['placeholder_old_product'] : '';
    $out['placeholder_old_product'] = sanitize_text_field($ph_old);
    
    $ph_exp = isset($input['placeholder_expected_value']) && is_string($input['placeholder_expected_value']) ? $input['placeholder_expected_value'] : '';
    $out['placeholder_expected_value'] = sanitize_text_field($ph_exp);
    
    $ph_email = isset($input['placeholder_email']) && is_string($input['placeholder_email']) ? $input['placeholder_email'] : '';
    $out['placeholder_email'] = sanitize_text_field($ph_email);
    
    $ph_code = isset($input['placeholder_code']) && is_string($input['placeholder_code']) ? $input['placeholder_code'] : '';
    $out['placeholder_code'] = sanitize_text_field($ph_code);

    return $out;
  }

  protected static function sanitize_hex_color_fallback($color, $fallback = '') {
    $color = (string)$color;
    if (function_exists('sanitize_hex_color')) {
      $res = sanitize_hex_color($color);
      return $res ? $res : $fallback;
    }
    if (preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $color)) {
      return $color;
    }
    return $fallback;
  }

  public static function field_admin_emails() {
    $opts = WTIP_Helpers::get_options();
    $val = $opts['admin_emails'];
    if (!$val && !empty($opts['admin_email'])) {
      $val = $opts['admin_email'];
    }
    echo '<textarea name="wtip_options[admin_emails]" rows="3" class="large-text code" placeholder="one@company.com, two@company.com">' . esc_textarea($val) . '</textarea>';
    echo '<p class="description">' . esc_html__('Add one or more emails (comma or newline separated). Notifications will be sent to all.', 'woocommerce-trade-in-pro') . '</p>';
  }

  public static function field_whatsapp() {
    $opts = WTIP_Helpers::get_options();
    echo '<input type="text" name="wtip_options[whatsapp_number]" value="' . esc_attr($opts['whatsapp_number']) . '" class="regular-text" placeholder="15551234567" />';
    echo '<p class="description">' . esc_html__('International format without + or spaces. Example: 15551234567', 'woocommerce-trade-in-pro') . '</p>';
  }

  public static function field_conditions() {
    $opts = WTIP_Helpers::get_options();
    echo '<textarea name="wtip_options[condition_options]" rows="6" class="large-text code">' . esc_textarea($opts['condition_options']) . '</textarea>';
    echo '<p class="description">' . esc_html__('One label per line. Displayed in condition dropdown.', 'woocommerce-trade-in-pro') . '</p>';
  }

  public static function field_toggles() {
    $opts = WTIP_Helpers::get_options();
    echo '<label><input type="checkbox" name="wtip_options[enable_magic_login]" value="1" ' . checked($opts['enable_magic_login'], 1, false) . ' /> ' . esc_html__('Enable email code login/signup', 'woocommerce-trade-in-pro') . '</label><br/>';
    echo '<label><input type="checkbox" name="wtip_options[enable_whatsapp]" value="1" ' . checked($opts['enable_whatsapp'], 1, false) . ' /> ' . esc_html__('Enable WhatsApp chat launch', 'woocommerce-trade-in-pro') . '</label><br/>';
    echo '<label>' . esc_html__('Max images (1-5): ', 'woocommerce-trade-in-pro') . '<input type="number" name="wtip_options[max_images]" value="' . esc_attr($opts['max_images']) . '" min="1" max="5" /></label><br/>';
    echo '<label>' . esc_html__('Max image size MB (1-20): ', 'woocommerce-trade-in-pro') . '<input type="number" name="wtip_options[max_image_size_mb]" value="' . esc_attr($opts['max_image_size_mb']) . '" min="1" max="20" /></label>';
  }

  public static function field_shortcode_display() {
    $shortcode = '[tradein_button]';
    echo '<div style="display:flex; gap:8px; align-items:center; max-width:520px;">';
    echo '<input type="text" readonly value="' . esc_attr($shortcode) . '" class="regular-text code" id="wtip-shortcode-field" />';
    echo '<button type="button" class="button" id="wtip-copy-shortcode">' . esc_html__('Copy', 'woocommerce-trade-in-pro') . '</button>';
    echo '</div>';
    echo '<p class="description">' . esc_html__('Place this shortcode anywhere (e.g., product description, templates) to render the Trade-In button.', 'woocommerce-trade-in-pro') . '</p>';
    echo '<script>
      (function(){
        var btn = document.getElementById("wtip-copy-shortcode");
        if (!btn) return;
        btn.addEventListener("click", function(){
          var inp = document.getElementById("wtip-shortcode-field");
          inp.focus(); inp.select();
          try {
            document.execCommand("copy");
            btn.textContent = "' . esc_js(__('Copied!', 'woocommerce-trade-in-pro')) . '";
            setTimeout(function(){ btn.textContent = "' . esc_js(__('Copy', 'woocommerce-trade-in-pro')) . '"; }, 1200);
          } catch(e) {}
        });
      })();
    </script>';
  }

  public static function field_button_text() {
    $opts = WTIP_Helpers::get_options();
    echo '<input type="text" name="wtip_options[button_text]" value="' . esc_attr($opts['button_text']) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Text displayed inside the Trade-In button.', 'woocommerce-trade-in-pro') . '</p>';
  }

  public static function field_button_bg_color() {
    $opts = WTIP_Helpers::get_options();
    echo '<input type="color" name="wtip_options[button_bg_color]" value="' . esc_attr($opts['button_bg_color']) . '" />';
    echo '<p class="description">' . esc_html__('Background color of the button.', 'woocommerce-trade-in-pro') . '</p>';
  }

  public static function field_button_text_color() {
    $opts = WTIP_Helpers::get_options();
    echo '<input type="color" name="wtip_options[button_text_color]" value="' . esc_attr($opts['button_text_color']) . '" />';
    echo '<p class="description">' . esc_html__('Text color of the button.', 'woocommerce-trade-in-pro') . '</p>';
  }

  public static function field_placeholder_old_product() {
    $opts = WTIP_Helpers::get_options();
    echo '<input type="text" name="wtip_options[placeholder_old_product]" value="' . esc_attr($opts['placeholder_old_product']) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Shown in the "Old product name" input.', 'woocommerce-trade-in-pro') . '</p>';
  }

  public static function field_placeholder_expected_value() {
    $opts = WTIP_Helpers::get_options();
    echo '<input type="text" name="wtip_options[placeholder_expected_value]" value="' . esc_attr($opts['placeholder_expected_value']) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Shown in the "Expected trade-in value" input.', 'woocommerce-trade-in-pro') . '</p>';
  }

  public static function field_placeholder_email() {
    $opts = WTIP_Helpers::get_options();
    echo '<input type="text" name="wtip_options[placeholder_email]" value="' . esc_attr($opts['placeholder_email']) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Shown in the "Email" input during verification.', 'woocommerce-trade-in-pro') . '</p>';
  }

  public static function field_placeholder_code() {
    $opts = WTIP_Helpers::get_options();
    echo '<input type="text" name="wtip_options[placeholder_code]" value="' . esc_attr($opts['placeholder_code']) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__('Shown in the "Verification Code" input.', 'woocommerce-trade-in-pro') . '</p>';
  }

  public static function render_settings_page() {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('WooCommerce Trade-In Pro', 'woocommerce-trade-in-pro') . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('wtip_options_group');
    do_settings_sections('wtip-settings');
    submit_button();
    echo '</form>';
    echo '</div>';
  }
}
