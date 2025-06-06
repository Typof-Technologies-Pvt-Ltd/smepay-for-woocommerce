=== SMEPay for WooCommerce ===
Contributors: smepay, upnrunn, kishores
Donate link: https://smepay.io/
Tags: UPI, WooCommerce, Payment Gateway, SMEPay, QR Code, UPI Payments, Instant Payment
Requires at least: 4.7
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.0
Requires Plugins: woocommerce
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SMEPay for WooCommerce is a WordPress plugin that enables WooCommerce stores to accept UPI payments via SMEPay.

== Description ==

This plugin allows WooCommerce stores to accept payments through **SMEPay** using a UPI QR code. Customers can scan the QR code for instant payments. The plugin is easy to install, configure, and works with the latest version of WooCommerce.

### Features:
- **UPI Payments Integration**: Accept UPI payments via QR code.
- **Works with WooCommerce**: Seamless integration with WooCommerce.
- **Customizable Settings**: Configure title, description, and more directly from the WooCommerce settings page.
- **Instant Payment**: Customers can complete transactions with instant UPI payment processing.

== Installation ==

1. Upload the plugin to your WordPress site (Plugins > Add New > Upload Plugin).
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to WooCommerce > Settings > Payments to enable and configure SMEPay as a payment method.
4. Enter your **SMEPay API credentials** (provided when you sign up for SMEPay) in the settings to enable payment processing.

== Requirements ==

- **SSL Certificate Required**: For secure payment processing, **SSL** (Secure Socket Layer) is mandatory. Your website must have an SSL certificate installed and configured to use the SMEPay payment gateway.
  - You can verify if your website has SSL by checking for the green padlock icon in your browser's address bar.
  - If you don't have SSL, consider installing one from your hosting provider.


== Frequently Asked Questions ==

= What is SMEPay? =
**SMEPay** is a payment gateway that enables WooCommerce store owners to accept payments through UPI (Unified Payments Interface) in the form of a QR code. Customers can easily scan the QR code with any UPI-enabled mobile app (like Google Pay, PhonePe, etc.) to make instant payments.

= How do I set up SMEPay for WooCommerce? =
After installing the plugin, go to **WooCommerce > Settings > Payments**, and enable **SMEPay**. You'll need to enter your **SMEPay API credentials**. Once set up, customers will be able to pay via UPI when checking out on your store.

= How do UPI payments work with SMEPay? =
SMEPay generates a unique QR code for each transaction. Customers can scan the code using their UPI mobile app to make instant payments. Once the payment is successful, the order status will automatically update in WooCommerce.

= Is SMEPay compatible with all WooCommerce themes? =
Yes, SMEPay works with all WooCommerce-compatible themes. However, it's recommended to test on your site to ensure full compatibility.

= What UPI apps are supported? =
SMEPay supports all UPI-enabled apps, including Google Pay, PhonePe, Paytm, and others.

= Can I use SMEPay for recurring payments? =
Currently, SMEPay supports one-time payments via UPI. For recurring payments, you would need to explore other payment gateways.

== Changelog ==

= 1.0.0 =
* Initial release of SMEPay for WooCommerce plugin.
* Added UPI QR code payment functionality.
* Integrated with WooCommerce payment system.
* Customizable settings for store owners.

== Upgrade Notice ==

= 1.0.0 =
* First version released. No previous version to upgrade from.