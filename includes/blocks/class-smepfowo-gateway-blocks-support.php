<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * SMEPay Payments Blocks integration.
 *
 * @since 1.0.0
 */
final class SMEPFOWO_Gateway_Blocks_Support extends AbstractPaymentMethodType {

    /**
     * The gateway instance.
     *
     * @var WC_Gateway_SMEPFOWO
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'smepfowo';

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        if ( ! did_action( 'woocommerce_loaded' ) ) {
            return;
        }
        $this->settings = get_option( 'woocommerce_smepfowo_settings', [] );
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : null;
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        if ( ! $this->gateway ) {
            return false;
        }
        if ( $this->gateway->requires_ssl() ) {
            // SSL is required but not used
            if ( ! is_ssl() ) {
                return false;
            }
        }
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        // Check if on order received (thank you) page and order is paid
        $is_paid = false;
        if ( is_order_received_page() ) {
            $order_id = absint( get_query_var( 'order-received' ) );
            $order    = wc_get_order( $order_id );
            if ( $order instanceof WC_Order && $order->is_paid() ) {
                $is_paid = true;
            }
        }

        $script_handle = 'smepfowo-payments-blocks';
        $script_rel_path = 'resources/js/frontend/smepfowo-block-checkout.js';
        $script_url = trailingslashit( SMEPFOWO_Plugin::plugin_url() ) . $script_rel_path;

        wp_register_script(
            $script_handle,
            $script_url,
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-hooks',
                'wp-html-entities',
                'wp-i18n',
            ],
            SMEPFOWO_Plugin::VERSION,
            true
        );

        wp_script_add_data( $script_handle, 'type', 'module' );

        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations(
                $script_handle,
                'smepay-for-woocommerce',
                SMEPFOWO_Plugin::plugin_abspath() . 'languages/'
            );
        }

        wp_localize_script(
            $script_handle,
            'smepfowoCheckoutData',
            [
                'orderPaid' => $is_paid,
                'imgUrl' => trailingslashit( SMEPFOWO_Plugin::plugin_url() ) . 'resources/img/smepfowo.svg',
            ]
        );

        return [ $script_handle ];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting( 'title' ),
            'description' => $this->get_setting( 'description' ),
            'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
        ];
    }
}