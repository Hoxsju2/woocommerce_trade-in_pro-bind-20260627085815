=== WooCommerce Trade-In Pro ===
Contributors: bindai
Tags: woocommerce, trade-in, whatsapp, verification, modal, kyc, buyback
Requires at least: 6.5
Tested up to: 7.0
Stable tag: 1.1.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Trade-in workflow for WooCommerce with a multi-step modal, email code login/signup, admin notifications, and WhatsApp chat initiation.

== Description ==

WooCommerce Trade-In Pro adds a guided, multi-step trade-in flow to your WooCommerce product pages:
- Step 1: Trade-in form (auto-detected current product name/link, old product info, condition, images, expected value).
- Step 2 (if not logged-in): Two-step email verification (send code → verify) to login or create an account.
- Step 3: Review, consent, confirm. Sends admin email, logs a Request (CPT), and opens WhatsApp chat with details.

Features:
- Shortcode [tradein_button] to render a “Trade-In Your Old Device” button and modal.
- Shortcode [tradein_requests] to render a table of the logged-in user's trade-in requests (place anywhere, e.g., My Account > Account details).
- Image uploads (up to 5, 5MB each, JPEG/PNG) to Media Library.
- Custom Post Type “Trade-In Request” with statuses: Pending, Approved, Rejected.
- Admin settings for: admin email, WhatsApp number, condition labels, toggles, button text and colors, and placeholders.
- Secure: nonces, strict sanitization, strict file-type validation, GDPR consent.
- Internationalization ready.
- Extensible via actions/filters.

Note: Magic-code login uses time-limited codes and auto-creates users by email if not existing.

== Installation ==

1. Upload the `woocommerce-trade-in-pro` folder to `/wp-content/plugins/`.
2. Activate the plugin through the ‘Plugins’ screen in WordPress.
3. Go to WooCommerce → Trade-In Pro settings:
   - Set Admin Email
   - Set WhatsApp number in international format (e.g. 15551234567)
   - Adjust condition options and input placeholders if desired
   - Optionally customize the Trade-In button text and colors
4. Add the shortcode `[tradein_button]` to your WooCommerce product template or product description to display the button.
5. To let users see their requests, place `[tradein_requests]` on a page (or hook into My Account endpoints).

== Usage ==

- On the product page, click “Trade-In Your Old Device” to launch the modal.
- Complete Step 1; if not logged in you’ll be prompted to verify your email (Step 2).
- Review and accept Terms, then confirm (Step 3).
- Admin receives an email and a Trade-In Request is created.
- WhatsApp chat opens with pre-filled message.

== Shortcodes ==

- `[tradein_button]` — Renders the trade-in trigger button and modal on product pages.
- `[tradein_requests]` — Displays a table of the currently logged-in user's trade-in requests (status, values, links).

== Compatibility ==

- WordPress 6.5+ (Ready for WordPress 7.0)
- WooCommerce 8.0+
- PHP 8.0+

== Extensibility ==

Filters:
- `wtip_condition_options` (array) — customize condition dropdown.
- `wtip_email_subject` (string, $data)
- `wtip_email_body` (string HTML, $data)
- `wtip_whatsapp_message` (string, $data)
- `wtip_whatsapp_url` (string, $data)
- `wtip_button_text` (string, $atts, $options)

Actions:
- `wtip_before_request_save` ($data)
- `wtip_after_request_save` ($post_id, $data)
- `wtip_after_admin_notified` ($post_id, $email_sent)
- `wtip_after_whatsapp_url_built` ($post_id, $wa_url)

== Notes ==

This plugin uses a code-based email verification to log in or create a user by email. Ensure your site can send emails (SMTP recommended).

== Changelog ==

= 1.1.0 =
- Added strict type checking for modern PHP 8.0+ and WordPress 7.0 compatibility.
- Enhanced file upload security with `wp_check_filetype_and_ext`.
- Added placeholder customization support in settings.
- Increased robustness of superglobal handling.

= 1.0.0 =
- Initial release.
