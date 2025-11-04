=== SMEPay: UPI Gateway for WooCommerce ===
Contributors: smepay, upnrunn
Donate link: https://smepay.io/
Tags: woocommerce, payment, upi, qr, india
Requires at least: 4.7
Tested up to: 6.8
Stable tag: 1.0.4
Requires PHP: 7.0
Requires Plugins: woocommerce
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SMEPay: UPI Gateway for WooCommerce is a WordPress plugin that enables WooCommerce stores to accept UPI payments via SMEPay.

== Description ==

SMEPay: UPI Gateway for WooCommerce is a WordPress plugin built specifically for Indian WooCommerce stores to accept payments via UPI QR codes. With SMEPay, customers can scan a QR code at checkout to make instant payments using popular UPI apps like Google Pay, PhonePe, or Paytm. The plugin is easy to install, configure, and fully compatible with the latest WooCommerce versions.

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

- **SSL Certificate Required**: For secure payment processing, your website must have an SSL certificate (HTTPS) installed and configured.
  - Verify SSL by checking for the padlock icon in your browser’s address bar.
  - If you don’t have SSL, please obtain one from your hosting provider before enabling SMEPay.

== Frequently Asked Questions ==

= What is SMEPay? =
**SMEPay** is a payment gateway that enables WooCommerce store owners to accept payments through UPI (Unified Payments Interface) via QR codes. Customers scan the QR code with any UPI-enabled mobile app (like Google Pay, PhonePe, etc.) to make instant payments.

= How do I set up SMEPay for WooCommerce? =
After installing the plugin, go to **WooCommerce > Settings > Payments**, and enable **SMEPay**. Enter your **SMEPay API credentials**. Once configured, customers can pay via UPI at checkout.

= How do UPI payments work with SMEPay? =
SMEPay generates a unique QR code for each transaction. Customers scan it with their UPI app to make instant payments. Upon successful payment, the WooCommerce order status updates automatically.

= Is SMEPay compatible with all WooCommerce themes? =
Yes, SMEPay works with all WooCommerce-compatible themes. We recommend testing on your site to ensure full compatibility.

= What UPI apps are supported? =
SMEPay supports all UPI-enabled apps, including Google Pay, PhonePe, Paytm, and others.

= Can I use SMEPay for recurring payments? =
Currently, SMEPay supports only one-time payments via UPI. For recurring payments, consider other payment gateways.

= Is my payment and data secure when using SMEPay? =
Yes. SMEPay uses secure, authenticated API communication. The plugin shares only the transaction data necessary for payment processing after the customer selects SMEPay at checkout. No additional personal data is collected or stored.

== External Services ==

This plugin connects to the SMEPay platform, a third-party UPI payment service provided by Typof Technologies, to enable UPI payments in your WooCommerce store.

### 🔧 API Endpoints (Based on Mode)

The plugin uses different endpoints depending on your selected mode:

- **Development Mode** (`mode = development`):  
  Base URL: `https://staging.smepay.in/api/wiz/…`

- **Production Mode** (`mode = production`):  
  Base URL: `https://extranet.smepay.in/api/wiz/…`

#### Endpoints Used:
- `/external/auth` – Authenticate WooCommerce store with SMEPay  
- `/external/create-order` – Create UPI QR payment request  
- `/external/validate-order` – Validate payment status

#### Frontend Widget Script:
- `https://typof.co/smepay/checkout-v2.js` – This script loads a React-based frontend app that renders the UPI QR code at checkout. It enables customers to scan and pay using their UPI app. It only uses data required to generate and display the QR code for the current WooCommerce order. It does **not** track users, store cookies, or collect personal data outside the transaction context.

---

### 📤 Data Shared with SMEPay

During payment processing, the plugin sends:
- WooCommerce order ID, total amount, currency  
- Customer info (name, email, phone)  
- Callback URL (order confirmation page)  
- SMEPay Client ID & Secret  
- Mode indicator (development or production)

---

### 🛡️ Data Sharing Consent

No data is sent to SMEPay unless the customer explicitly selects the SMEPay payment option at checkout and places an order.

---

### 🔄 When Data Is Sent

1. When the user selects **SMEPay** at checkout and the order is created  
2. After payment, to confirm successful payment via the validate endpoint

---

### ⚙️ Why We Send This Data

- To generate a **transaction-specific UPI QR code**  
- To **confirm payment** and update order status in WooCommerce  
- To ensure secure, authenticated communication linked to your store  
- If the SMEPay service is temporarily unavailable, customers can select an alternative payment method configured in your WooCommerce store.

---

### 🧭 Service Provider Details

This plugin interacts with the following SMEPay-hosted domains:  
- `smepay.in`, `typof.co` – API endpoints  
- `typof.co` – QR widget script (`checkout-v2.js`)

**Service Provider: SMEPay by Typof Technologies**  
- [Terms of Service](https://smepay.io/tnc)  
- [Privacy Policy](https://smepay.io/privacy-policy)

== Changelog ==

= 1.0.4 =
* Fixed an issue where full SMEPay validation was triggered for partial COD orders.
* Improved gateway hook isolation to prevent cross-validation between payment types.
* Enhanced reliability for thank you page validation flow.
* Minor performance and compatibility improvements.

= 1.0.3 =
* Added file existence checks before including required files to prevent fatal errors.
* Improved plugin stability by handling missing files gracefully.
* Minor code cleanup and optimizations for better maintainability.


= 1.0.2 =
* Added new api endpoints.
* Fixed correct status and type casting for new API

= 1.0.1 =
* Added patches for conflict with other payment gateways.
* Fixed issues for block based shortcode    

= 1.0.0 =
* Initial release of SMEPay for WooCommerce plugin.  
* Added UPI QR code payment functionality.  
* Integrated with WooCommerce payment system.  
* Customizable settings for store owners.

== Upgrade Notice ==

= 1.0.4 =
* Fixed issue where full order validation ran for partial COD payments.
* Improved thank-you page validation to correctly handle partial and full payments separately.
* Enhanced overall gateway reliability and internal hook handling.
* Recommended update for all stores using SMEPay Partial COD or inline QR checkout.


= 1.0.3 =
* Fixed issue with loading required include files by adding checks for file existence before requiring them.
* Improved plugin stability to prevent fatal errors when files are missing.
* Recommended update for all users to ensure smoother plugin operation.


= 1.0.2 =
* Critical update: Updated API endpoints and improved order status handling. Recommended for all users to ensure compatibility with latest SMEPay API.

= 1.0.1 =
* Compatibility patch: Resolved conflicts with other payment gateways and fixed issues with block-based checkout. Update if you're using WooCommerce blocks.

= 1.0.0 =
* First version released. No previous version to upgrade from.