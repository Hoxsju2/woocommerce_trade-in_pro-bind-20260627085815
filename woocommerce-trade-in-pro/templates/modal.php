<?php
$ctx = WTIP_Helpers::current_product_context();
$conds = WTIP_Helpers::get_condition_options();
$store_currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
$allowed_currencies = ['USD','SAR','GBP','EUR'];
$default_currency = in_array($store_currency, $allowed_currencies, true) ? $store_currency : 'USD';
$opts = WTIP_Helpers::get_options();
?>
<div class="wtip-modal-backdrop"></div>
<div class="wtip-modal" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__('Trade-In', 'woocommerce-trade-in-pro'); ?>">
  <div class="wtip-card">
    <div class="wtip-header">
      <div>
        <h3 class="wtip-title"><?php echo esc_html__('Trade-In', 'woocommerce-trade-in-pro'); ?></h3>
        <div class="wtip-subtitle"><?php echo esc_html__('Guided multi-step trade-in process', 'woocommerce-trade-in-pro'); ?></div>
      </div>
      <button class="wtip-close" aria-label="<?php echo esc_attr__('Close', 'woocommerce-trade-in-pro'); ?>">✕</button>
    </div>

    <div class="wtip-steps">
      <div class="wtip-step active">1. <?php echo esc_html__('Details', 'woocommerce-trade-in-pro'); ?></div>
      <div class="wtip-step">2. <?php echo esc_html__('Login', 'woocommerce-trade-in-pro'); ?></div>
      <div class="wtip-step">3. <?php echo esc_html__('Review', 'woocommerce-trade-in-pro'); ?></div>
    </div>

    <div class="wtip-body">
      <div class="wtip-panel" id="wtip-step1" style="display:block;">
        <h4><?php echo esc_html__('Start Your Trade-In', 'woocommerce-trade-in-pro'); ?></h4>
        <p class="wtip-subtitle"><?php echo esc_html__('Trade in your old product for credit toward this new one', 'woocommerce-trade-in-pro'); ?></p>

        <div class="wtip-field">
          <label><?php echo esc_html__('New product', 'woocommerce-trade-in-pro'); ?></label>
          <input id="wtip-new-name" type="text" class="wtip-input" value="<?php echo esc_attr($ctx['title']); ?>" disabled />
        </div>

        <div class="wtip-field">
          <label><?php echo esc_html__('Old product name', 'woocommerce-trade-in-pro'); ?></label>
          <input id="wtip-old-name" type="text" class="wtip-input" placeholder="<?php echo esc_attr($opts['placeholder_old_product']); ?>" />
        </div>

        <div class="wtip-field wtip-row wtip-row--pair">
          <div>
            <label><?php echo esc_html__('Condition', 'woocommerce-trade-in-pro'); ?></label>
            <select id="wtip-condition" class="wtip-select">
              <?php foreach ($conds as $c): ?>
                <option value="<?php echo esc_attr($c); ?>"><?php echo esc_html($c); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label><?php echo esc_html__('Currency', 'woocommerce-trade-in-pro'); ?></label>
            <select id="wtip-currency" class="wtip-select">
              <?php foreach ($allowed_currencies as $code): ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($code, $default_currency); ?>><?php echo esc_html($code); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="wtip-field">
          <label><?php echo esc_html__('Upload images (up to 5, JPEG/PNG, 5MB each)', 'woocommerce-trade-in-pro'); ?></label>
          <input id="wtip-images" type="file" class="wtip-file" accept="image/jpeg,image/png" multiple />
          <div class="wtip-images" id="wtip-images-preview"></div>
        </div>

        <div class="wtip-field">
          <label><?php echo esc_html__('Expected trade-in value', 'woocommerce-trade-in-pro'); ?></label>
          <input id="wtip-expected" type="number" step="0.01" min="0" class="wtip-number" placeholder="<?php echo esc_attr($opts['placeholder_expected_value']); ?>" />
        </div>
      </div>

      <div class="wtip-panel" id="wtip-step2" style="display:none;">
        <h4><?php echo esc_html__('Login / Sign Up', 'woocommerce-trade-in-pro'); ?></h4>
        <p class="wtip-subtitle"><?php echo esc_html__('Enter your email to receive a verification code.', 'woocommerce-trade-in-pro'); ?></p>

        <div class="wtip-login-substep" id="wtip-substep1" style="display:block;">
          <label><?php echo esc_html__('Email', 'woocommerce-trade-in-pro'); ?></label>
          <input id="wtip-email" type="email" class="wtip-email" placeholder="<?php echo esc_attr($opts['placeholder_email']); ?>" />
          <div class="wtip-field" style="margin-top:12px;">
            <button id="wtip-send-code" class="wtip-btn wtip-btn-primary"><?php echo esc_html__('Send Verification Code', 'woocommerce-trade-in-pro'); ?></button>
          </div>
        </div>

        <div class="wtip-login-substep" id="wtip-substep2" style="display:none;">
          <p class="wtip-subtitle"><?php echo esc_html__('Check your email for the code. Enter it below.', 'woocommerce-trade-in-pro'); ?></p>
          <label><?php echo esc_html__('Verification Code', 'woocommerce-trade-in-pro'); ?></label>
          <input id="wtip-code" type="text" class="wtip-code" placeholder="<?php echo esc_attr($opts['placeholder_code']); ?>" />
          <div style="margin-top:12px; display:flex; gap:8px;">
            <button id="wtip-verify" class="wtip-btn wtip-btn-primary"><?php echo esc_html__('Verify and Continue', 'woocommerce-trade-in-pro'); ?></button>
            <button id="wtip-resend-code" class="wtip-btn wtip-btn-secondary"><?php echo esc_html__('Resend', 'woocommerce-trade-in-pro'); ?></button>
          </div>
          <div id="wtip-proceed" style="display:none; margin-top:12px;">
            <p><?php echo esc_html__('Trade-in saved to your profile. Shall I proceed with notifications?', 'woocommerce-trade-in-pro'); ?></p>
            <button class="wtip-btn wtip-btn-primary wtip-yes"><?php echo esc_html__('Yes', 'woocommerce-trade-in-pro'); ?></button>
            <button class="wtip-btn wtip-btn-secondary wtip-no"><?php echo esc_html__('No', 'woocommerce-trade-in-pro'); ?></button>
          </div>
        </div>
      </div>

      <div class="wtip-panel" id="wtip-step3" style="display:none;">
        <h4><?php echo esc_html__('Review Your Trade-In', 'woocommerce-trade-in-pro'); ?></h4>
        <div class="wtip-field" style="margin-top:8px;">
          <div><strong><?php echo esc_html__('New product:', 'woocommerce-trade-in-pro'); ?></strong> <span id="wtip-review-new-name"></span> — <a id="wtip-review-new-link" href="#" target="_blank" rel="noopener"><?php echo esc_html__('View', 'woocommerce-trade-in-pro'); ?></a></div>
          <div><strong><?php echo esc_html__('Old product:', 'woocommerce-trade-in-pro'); ?></strong> <span id="wtip-review-old"></span></div>
          <div><strong><?php echo esc_html__('Condition:', 'woocommerce-trade-in-pro'); ?></strong> <span id="wtip-review-condition"></span></div>
          <div><strong><?php echo esc_html__('Expected value:', 'woocommerce-trade-in-pro'); ?></strong> <span id="wtip-review-value"></span> <span id="wtip-review-currency"></span></div>
          <div style="margin-top:8px;"><strong><?php echo esc_html__('Images:', 'woocommerce-trade-in-pro'); ?></strong></div>
          <div class="wtip-images" id="wtip-review-images"></div>
          <div class="wtip-field">
            <label><input type="checkbox" id="wtip-consent" /> <?php echo esc_html__('I agree to the trade-in terms', 'woocommerce-trade-in-pro'); ?></label>
          </div>
        </div>
      </div>

      <div class="wtip-error" role="alert"></div>
      <div class="wtip-success" role="status"></div>
    </div>

    <div class="wtip-footer">
      <button class="wtip-btn wtip-btn-secondary wtip-close"><?php echo esc_html__('Close', 'woocommerce-trade-in-pro'); ?></button>
      <button class="wtip-btn wtip-btn-secondary" id="wtip-edit" style="display:none;"><?php echo esc_html__('Edit', 'woocommerce-trade-in-pro'); ?></button>
      <button class="wtip-btn wtip-btn-primary" id="wtip-next"><?php echo esc_html__('Next', 'woocommerce-trade-in-pro'); ?></button>
      <button class="wtip-btn wtip-btn-primary" id="wtip-confirm" style="display:none;"><?php echo esc_html__('Confirm Trade-In', 'woocommerce-trade-in-pro'); ?></button>
    </div>
  </div>
</div>
<script>
(function(){
  function updateFooter(step){
    var next = document.getElementById('wtip-next');
    var confirm = document.getElementById('wtip-confirm');
    var edit = document.getElementById('wtip-edit');
    if(step === 1){
      next.style.display = 'inline-block';
      confirm.style.display = 'none';
      edit.style.display = 'none';
    } else if(step === 2){
      next.style.display = 'none';
      confirm.style.display = 'none';
      edit.style.display = 'none';
    } else if(step === 3){
      next.style.display = 'none';
      confirm.style.display = 'inline-block';
      edit.style.display = 'inline-block';
    }
  }
  var observer = new MutationObserver(function(){
    var step = 1;
    if(document.getElementById('wtip-step3').style.display === 'block') step = 3;
    else if(document.getElementById('wtip-step2').style.display === 'block') step = 2;
    updateFooter(step);
  });
  observer.observe(document.querySelector('.wtip-body'), { attributes:true, childList:true, subtree:true });
})();
</script>
