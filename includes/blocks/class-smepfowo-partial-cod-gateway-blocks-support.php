<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * SMEPay Payments Blocks integration. 
 *
 * @since 1.0.0
 */
final class SMEPFOWO_Partial_COD_Gateway_Blocks_Support extends AbstractPaymentMethodType {

    /**
     * @var WC_Gateway_SMEPFOWO_Partial_COD
     */
    private $gateway;

    /**
     * Payment method slug
     */
    protected $name = 'smepfowo_partial_cod';

    public function initialize() {
        if ( ! did_action( 'woocommerce_loaded' ) ) {
            return;
        }
        $this->settings = get_option( 'woocommerce_smepfowo_settings', [] );
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : null;
    }

    public function is_active() {
        if ( ! $this->gateway ) {
            return false;
        }
        if ( $this->gateway->requires_ssl() && ! is_ssl() ) {
            return false;
        }
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {
        $is_paid = false;
        if ( is_order_received_page() ) {
            $order_id = absint( get_query_var( 'order-received' ) );
            $order    = wc_get_order( $order_id );
            if ( $order instanceof WC_Order && $order->is_paid() ) {
                $is_paid = true;
            }
        }

        $script_handle   = 'smepfowo-partial-cod-blocks';
        $script_rel_path = 'resources/js/frontend/smepfowo-block-checkout.js';
        $script_url      = trailingslashit( SMEPFOWO_Plugin::plugin_url() ) . $script_rel_path;

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
                'imgUrl'    => trailingslashit( SMEPFOWO_Plugin::plugin_url() ) . 'resources/img/smepfowo.svg',
            ]
        );

        return [ $script_handle ];
    }

    public function get_payment_method_data() {
        return [
            'title'       => $this->gateway->title ?? __( 'Partial Payment with COD', 'smepay-for-woocommerce' ),
            'description' => $this->gateway->description ?? __( 'Pay part now, rest on delivery.', 'smepay-for-woocommerce' ),
            'supports'    => array_filter(
                $this->gateway->supports ?? [],
                [ $this->gateway, 'supports' ]
            ),
        ];
    }


}
