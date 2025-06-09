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
define( 'SMEPAY_WC_VERSION', '1.0.0' );

// Define absolute server path to the plugin directory.
define( 'SMEPAY_WC_PATH', plugin_dir_path( __FILE__ ) );

// Define URL to the plugin directory (for loading assets).
define( 'SMEPAY_WC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class for SMEPay WooCommerce integration.
 *
 * @package Smepay_For_WooCommerce
 */
class Smepay_For_WooCommerce {

    /**
     * Plugin bootstrapping.
     */
    public static function init() {

        // SMEPay gateway class.
        add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );

        // Make the SMEPay Payments gateway available to WC.
        add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );

        // Registers WooCommerce Blocks integration.
        add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'woocommerce_gateway_smepay_woocommerce_block_support' ) );

        // Check for SSL before initializing the plugin
        add_action( 'admin_notices', array( __CLASS__, 'check_ssl_requirement' ) );
    }

    /**
     * Add the SMEPay Payment gateway to the list of available gateways.
     *
     * @param array $gateways Array of existing gateways.
     * @return array Modified array of gateways including SMEPay.
     */
    public static function add_gateway( $gateways ) {

        $options = get_option( 'woocommerce_smepay_settings', array() );

        if ( isset( $options['hide_for_non_admin_users'] ) ) {
            $hide_for_non_admin_users = $options['hide_for_non_admin_users'];
        } else {
            $hide_for_non_admin_users = 'no';
        }

        if ( ( 'yes' === $hide_for_non_admin_users && current_user_can( 'manage_options' ) ) || 'no' === $hide_for_non_admin_users ) {
            $gateways[] = 'WC_Gateway_SMEPay';
        }

        return $gateways;
    }

    /**
     * Plugin includes.
     */
    public static function includes() {

        require_once SMEPAY_WC_PATH . 'includes/class-smepay-utils.php';

        // Make the SMEPay gateway available to WooCommerce.
        if ( class_exists( 'WC_Payment_Gateway' ) ) {
            require_once SMEPAY_WC_PATH . 'includes/class-wc-gateway-smepay.php';
        }
    }

    /**
     * Show admin notice if SSL is not enabled.
     */
    public static function check_ssl_requirement() {
        if ( ! is_ssl() ) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>' . esc_html__( 'SMEPay for WooCommerce:', 'smepay-for-woocommerce' ) . '</strong> ';
            echo esc_html__( 'SSL is required to use this payment gateway. Please enable SSL on your website for secure transactions.', 'smepay-for-woocommerce' );
            echo '</p>';
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
     * Registers WooCommerce Blocks integration.
     */
    public static function woocommerce_gateway_smepay_woocommerce_block_support() {

        if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {

            require_once SMEPAY_WC_PATH . 'includes/blocks/class-wc-smepay-payments-blocks.php';

            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                    $payment_method_registry->register( new WC_Gateway_SMEPay_Blocks_Support() );
                }
            );
        }
    }
}

// Initialize plugin only if WooCommerce is active.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
    Smepay_For_WooCommerce::init();
}
