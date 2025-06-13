<?php
/**
 * Plugin Name: SMEPay for WooCommerce
 * Plugin URI:  https://smepay.io
 * Description: Accept UPI payments via SMEPay with QR code widget on your WooCommerce website.
 * Version:     1.0.0
 * Author:      SMEPay
 * Author URI:  https://profiles.wordpress.org/smepay
 * Text Domain: smepay-for-woocommerce
 * Domain Path: /languages/
 * Requires Plugins: woocommerce
 *
 * Requires at least: 4.7
 * Tested up to:      6.8
 *
 * Copyright:  © Typof.
 * License:    GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Define plugin version constant.
define( 'SMEPFOWO_VERSION', '1.0.0' );

// Define absolute server path to the plugin directory.
define( 'SMEPFOWO_PATH', plugin_dir_path( __FILE__ ) );

// Define URL to the plugin directory (for loading assets).
define( 'SMEPFOWO_URL', plugin_dir_url( __FILE__ ) );

// Plugin base name used for activation hooks and localization
define( 'SMEPFOWO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class for SMEPay WooCommerce integration.
 *
 * @package SMEPay_For_WooCommerce
 */
class SMEPay_For_WooCommerce {

    /**
     * Plugin bootstrapping.
     */
    public static function init() {

        // SMEPay gateway class.
        add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 11 );

        // Make the SMEPay Payments gateway available to WC.
        add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );

        // Registers WooCommerce Blocks integration.
        add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'woocommerce_gateway_smepay_woocommerce_block_support' ) );

        // Check for SSL before initializing the plugin
        add_action( 'admin_notices', array( __CLASS__, 'check_ssl_requirement' ) );

        // Load text domain.
        add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
    }

    /**
     * Add the SMEPay Payment gateway to the list of available gateways.
     *
     * @param array $gateways Array of existing gateways.
     * @return array Modified array of gateways including SMEPay.
     */
    public static function add_gateway( $gateways ) {

        $options = get_option( 'woocommerce_smepfowo_settings', array() );

        if ( isset( $options['hide_for_non_admin_users'] ) ) {
            $hide_for_non_admin_users = $options['hide_for_non_admin_users'];
        } else {
            $hide_for_non_admin_users = 'no';
        }

        if ( ( 'yes' === $hide_for_non_admin_users && current_user_can( 'manage_options' ) ) || 'no' === $hide_for_non_admin_users ) {
            $gateways[] = 'SMEPFOWO_Gateway';
        }

        return $gateways;
    }

    /**
     * Plugin includes.
     */
    public static function includes() {

        require_once SMEPFOWO_PATH . 'includes/class-smepfowo-utils.php';

        // Make the SMEPay gateway available to WooCommerce.
        if ( class_exists( 'WC_Payment_Gateway' ) ) {
            require_once SMEPFOWO_PATH . 'includes/class-smepfowo-gateway.php';
        }
    }

    /**
     * Show admin notice if SSL is not enabled.
     */
    public static function check_ssl_requirement() {
        if ( ! is_ssl() ) {
            echo '<div class="notice notice-error is-dismissible">';
            printf(
                '<p><strong>%s</strong> %s</p>',
                esc_html__( 'SMEPay for WooCommerce:', 'smepay-for-woocommerce' ),
                esc_html__( 'SSL is required to use this payment gateway. Please enable SSL on your website for secure transactions.', 'smepay-for-woocommerce' )
            );
            echo '</div>';
        }
    }

    /**
     * Get plugin URL.
     *
     * @return string Plugin URL.
     */
    public static function plugin_url() {
        return untrailingslashit( plugins_url( '/', __FILE__ ) );
    }

    /**
     * Get plugin absolute path.
     *
     * @return string Plugin absolute path.
     */
    public static function plugin_abspath() {
        return trailingslashit( plugin_dir_path( __FILE__ ) );
    }

    /**
     * Load plugin textdomain for translations.
     *
     * This function makes the plugin ready for localization by loading the `.mo` files
     * from the `/languages` directory. It should be called during the plugin's initialization phase.
     *
     * @return void
     */
    public static function load_textdomain() {
        load_plugin_textdomain(
            'smepay-for-woocommerce',
            false,
            dirname( SMEPFOWO_PLUGIN_BASENAME ) . '/languages'
        );
    }


    /**
     * Registers WooCommerce Blocks integration for SMEPay payment gateway.
     *
     * This method ensures that the SMEPay gateway is available as a payment option
     * in the WooCommerce Block-based checkout. It checks if the required
     * `AbstractPaymentMethodType` class exists before registering the integration.
     *
     * @return void
     */
    public static function woocommerce_gateway_smepay_woocommerce_block_support() {

        if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {

            require_once SMEPFOWO_PATH . 'includes/blocks/class-smepfowo-gateway-blocks-support.php';

            // Register the gateway integration with the WooCommerce Blocks system.
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                /**
                 * Registers SMEPay Gateway with the WooCommerce Blocks Payment Method Registry.
                 *
                 * @param Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry The payment method registry instance.
                 *
                 * @return void
                 */
                function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                    $payment_method_registry->register( new SMEPFOWO_Gateway_Blocks_Support() );
                }
            );
        }
    }

}

// Initialize plugin only if WooCommerce is active.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) || 
     ( is_multisite() && in_array( 'woocommerce/woocommerce.php', get_site_option( 'active_sitewide_plugins', array() ) ) ) ) {
    SMEPay_For_WooCommerce::init();
}