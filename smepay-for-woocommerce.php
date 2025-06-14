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
 * License:    GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined( 'ABSPATH' ) || exit;

// Define constants
define( 'SMEPFOWO_VERSION', '1.0.0' );
define( 'SMEPFOWO_PATH', plugin_dir_path( __FILE__ ) );
define( 'SMEPFOWO_URL', plugin_dir_url( __FILE__ ) );
define( 'SMEPFOWO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Class SMEPay_For_WooCommerce
 *
 * Main plugin class for SMEPay integration with WooCommerce.
 */
class SMEPay_For_WooCommerce {

    /**
     * @var SMEPay_For_WooCommerce|null Holds the singleton instance.
     */
    private static $instance = null;

    /**
     * Initializes the plugin: hooks, filters, and includes.
     *
     * @return void
     */
    public static function init() {
        // Load necessary files.
        add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 11 );

        // Register the gateway with WooCommerce.
        add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );

        // Register WooCommerce Blocks support.
        add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'woocommerce_gateway_smepay_woocommerce_block_support' ) );

        // Admin SSL warning.
        add_action( 'admin_notices', array( __CLASS__, 'check_ssl_requirement' ) );

        // Add settings link on plugin list.
        add_filter( 'plugin_action_links_' . SMEPFOWO_PLUGIN_BASENAME, array( __CLASS__, 'add_settings_link' ) );

        // Load translation files.
        add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
    }

    /**
     * Get a singleton instance of this class.
     *
     * This ensures only one instance is ever created — useful for managing
     * shared state or avoiding duplicate hooks/execution.
     *
     * @return SMEPay_For_WooCommerce The single plugin instance.
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add SMEPay gateway to WooCommerce's available gateways list.
     *
     * @param array $gateways Existing payment gateways.
     * @return array Modified gateways list with SMEPay added conditionally.
     */
    public static function add_gateway( $gateways ) {
        $options = get_option( 'woocommerce_smepfowo_settings', array() );
        $hide_for_non_admin_users = isset( $options['hide_for_non_admin_users'] ) ? $options['hide_for_non_admin_users'] : 'no';

        if ( ( $hide_for_non_admin_users === 'yes' && current_user_can( 'manage_options' ) ) || $hide_for_non_admin_users === 'no' ) {
            $gateways[] = 'SMEPFOWO_Gateway';
        }

        return $gateways;
    }

    /**
     * Include required class files.
     *
     * @return void
     */
    public static function includes() {
        require_once SMEPFOWO_PATH . 'includes/class-smepfowo-utils.php';

        if ( class_exists( 'WC_Payment_Gateway' ) ) {
            require_once SMEPFOWO_PATH . 'includes/class-smepfowo-gateway.php';
        }
    }

    /**
     * Warn admin if SSL is not enabled (required for secure payment processing).
     *
     * @return void
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
     * Add "Settings" link to the plugin's action links in the Plugins list.
     *
     * @param array $links Existing action links.
     * @return array Modified action links.
     */
    public static function add_settings_link( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=smepfowo' ) ) . '">' .
                         esc_html__( 'Settings', 'smepay-for-woocommerce' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Load plugin translation files.
     *
     * @return void
     */
    public static function load_textdomain() {
        load_textdomain(
            'smepay-for-woocommerce',
            WP_LANG_DIR . '/smepay-for-woocommerce/smepay-for-woocommerce-' . get_locale() . '.mo'
        );

        load_plugin_textdomain(
            'smepay-for-woocommerce',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }

    /**
     * Register WooCommerce Blocks payment gateway integration.
     *
     * @return void
     */
    public static function woocommerce_gateway_smepay_woocommerce_block_support() {
        if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            require_once SMEPFOWO_PATH . 'includes/blocks/class-smepfowo-gateway-blocks-support.php';

            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                    $payment_method_registry->register( new SMEPFOWO_Gateway_Blocks_Support() );
                }
            );
        }
    }

    /**
     * Return plugin base URL.
     *
     * @return string
     */
    public static function plugin_url() {
        return untrailingslashit( plugins_url( '/', __FILE__ ) );
    }

    /**
     * Return plugin absolute path.
     *
     * @return string
     */
    public static function plugin_abspath() {
        return trailingslashit( plugin_dir_path( __FILE__ ) );
    }
}

// Initialize plugin only if WooCommerce is active
if (
    in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ||
    ( is_multisite() && in_array( 'woocommerce/woocommerce.php', get_site_option( 'active_sitewide_plugins', array() ) ) )
) {
    SMEPay_For_WooCommerce::get_instance()->init();
}