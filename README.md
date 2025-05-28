# SMEPay for WooCommerce

SMEPay for WooCommerce is a WordPress plugin that enables WooCommerce stores to accept UPI payments via SMEPay. The plugin integrates a UPI QR code widget for easy payments directly from the checkout page.

## Description

This plugin allows WooCommerce stores to accept payments through **SMEPay** using a UPI QR code. Customers can scan the QR code for instant payments. The plugin is easy to install, configure, and works with the latest version of WooCommerce.

- **UPI Payments Integration**: Accept UPI payments with a QR code.
- **Works with WooCommerce**: Seamless integration with WooCommerce.
- **Customizable Settings**: Configure title, description, and more from the WooCommerce settings page.

## Features

- Accept **UPI payments** via SMEPay.
- Displays **UPI QR Code** at checkout.
- Admin controls to show or hide the payment method for non-admin users.
- Works with **WooCommerce Blocks** for modern front-end integration.
- **Customizable**: Change payment method title and description.

## Installation

1. **Upload Plugin:**
   - Download the plugin zip file.
   - Go to `Plugins` → `Add New` → `Upload Plugin` in your WordPress dashboard.
   - Choose the file and click **Install Now**.

2. **Activate the Plugin:**
   - Once installed, click **Activate**.

3. **Configure Settings:**
   - Go to `WooCommerce` → `Settings` → `Payments` tab.
   - Find **SMEPay Payment Gateway** and configure the settings (title, description, UPI details).
   - Make sure to set your **Client ID** and **Client Secret** from your SMEPay dashboard.

4. **Hide Payment Gateway (Optional):**
   - If you'd like to hide the payment method for non-admin users, enable the option under the plugin settings.

## Usage

1. **Select SMEPay on Checkout**: Customers will see the SMEPay payment option during checkout.
2. **Scan UPI QR Code**: Customers can scan the UPI QR code to make the payment.

### Payment Gateway Integration

- The plugin automatically integrates with WooCommerce, adding SMEPay as a payment method during checkout.
- It supports all the necessary WooCommerce hooks for updating order statuses and handling payments.

## Requirements

- **WordPress** 4.2 or higher
- **WooCommerce** installed and activated
- PHP 7.0 or higher (recommended)

## Tested Up to

- WordPress 6.6
- WooCommerce 5.x and higher

## Support

For support, please visit the [SMEPay support page](https://smepay.io/).

## Changelog

### 1.0.0
- Initial release of SMEPay for WooCommerce.
- Integration with WooCommerce to enable UPI payment via QR code.

## License

This plugin is licensed under the **GNU General Public License v3.0**.

See [License URI](http://www.gnu.org/licenses/gpl-3.0.html) for more information.

Copyright (C) Typof. All Rights Reserved.

