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
        $this->settings = get_option( 'woocommerce_smepfowo_settings', [] );
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = $gateways[ $this->name ];
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        // Ensure SSL is being used
        if ( ! is_ssl() ) {
            return false;
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
        
        $script_path = '/resources/js/frontend/smepfowo-block-checkout.js';
        $script_url  = SMEPFOWO_URL . $script_path;

        wp_register_script(
            'smepfowo-payments-blocks',
            $script_url,
            [
                'wc-blocks-registry', // Ensure wc-blocks-registry is loaded.
                'wc-settings',
                'wp-element',
                'wp-hooks',
                'wp-html-entities',
                'wp-i18n',
            ],
            SMEPFOWO_VERSION, // Replace with your desired version.
            true
        );

        wp_script_add_data( 'smepfowo-payments-blocks', 'type', 'module' ); // Set as ES Module.

        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations(
                'smepfowo-payments-blocks',
                'smepay-for-woocommerce',
                SMEPFOWO_PATH . 'languages/'
            );
        }

        // Localize orderPaid boolean and image URL for JS
        wp_localize_script(
            'smepfowo-payments-blocks',
            'smepfowoCheckoutData',
            [
                'orderPaid' => $is_paid,
                'imgUrl'    => SMEPFOWO_URL . 'resources/img/smepfowo.svg',
            ]
        );


        return [ 'smepfowo-payments-blocks' ];
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