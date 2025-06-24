<?php
/**
 * SMEPFOWO_Gateway class
 *
 * @author    SMEPay <support@smepay.io>
 * @package   WooCommerce SMEPay Payments Gateway
 * @since     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SMEPay Gateway Class
 */
class SMEPFOWO_Gateway extends WC_Payment_Gateway {

    use SMEPFOWO_Utils;

    const TIMEOUT = 15;

    protected $instructions;
    protected $hide_for_non_admin_users;

    public $id = 'smepfowo';

    protected $client_id;
    protected $client_secret;

    /**
     * Constructor
     */
    public function __construct() {
        $this->icon = apply_filters(
            'smepfowo_gateway_icon',
            trailingslashit( SMEPay_For_WooCommerce::plugin_url() ) . 'resources/img/smepfowo.svg'
        );
        $this->has_fields         = false;
        $this->method_title       = _x( 'SMEPay Payment', 'SMEPay payment method', 'smepay-for-woocommerce' );
        $this->method_description = __( 'Pay via UPI QR code using SMEPay.', 'smepay-for-woocommerce' );

        $this->init_form_fields();
        $this->init_settings();

        $this->title                   = $this->get_option( 'title' );
        $this->description             = $this->get_option( 'description' );
        $this->instructions            = $this->get_option( 'instructions', $this->description );
        $this->hide_for_non_admin_users = $this->get_option( 'hide_for_non_admin_users' );
        $this->client_id               = $this->get_option( 'client_id' );
        $this->client_secret           = $this->get_option( 'client_secret' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'woocommerce_thankyou', [ $this, 'send_validate_order_request' ], 10, 1 );
        add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
        add_action( 'woocommerce_review_order_before_submit', [ $this, 'add_nonce_to_checkout' ] );
    }

    /**
     * Determine whether the gateway is available at checkout.
     *
     * @return bool
     */
    public function is_available() {
        if ( ! is_ssl() ) {
            return false;
        }

        if ( 'yes' === $this->hide_for_non_admin_users && ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        return 'yes' === $this->enabled && ! empty( $this->client_id ) && ! empty( $this->client_secret );
    }

    /**
     * Output a nonce field on the checkout page
     */
    public function add_nonce_to_checkout() {
        if ( is_checkout() && ! is_wc_endpoint_url() ) {
            wp_nonce_field( 'smepfowo_nonce_action', 'smepfowo_nonce' );
        }
    }


    /**
     * Enqueue frontend scripts
     */
    public function payment_scripts() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended

        $checkout_layout = $this->smepfowo_detect_checkout_layout_backend();

        if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_order_received_page() ) {
            return;
        }

        if ( ! is_ssl() ) {
            return;
        }

        // Load the remote SMEPay widget
        wp_enqueue_script(
            'smepfowo-checkout',
            'https://typof.co/smepay/checkout.js',
            [],
            SMEPay_For_WooCommerce::VERSION,
            true
        );

        // Do not proceed if plugin is disabled or missing credentials
        if ( 'no' === $this->enabled || empty( $this->client_id ) || empty( $this->client_secret ) ) {
            return;
        }

        // Skip if using block checkout layout (handled separately)
        if ( 'block' === $checkout_layout['layout'] ) {
            return;
        }

        // Use recommended path to plugin asset
        $script_path = 'resources/js/frontend/smepfowo-classic-checkout.js';
        $script_url  = trailingslashit( SMEPay_For_WooCommerce::plugin_url() ) . $script_path;

        wp_register_script(
            'smepfowo-handler',
            $script_url,
            [ 'jquery', 'wp-i18n' ],
            SMEPay_For_WooCommerce::VERSION,
            true
        );

        // Translation support
        wp_set_script_translations(
            'smepfowo-handler',
            'smepay-for-woocommerce',
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );

        // Localize data
        wp_localize_script(
            'smepfowo-handler',
            'smepfowo_data',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'smepfowo_nonce_action' ),
            ]
        );

        wp_enqueue_script( 'smepfowo-handler' );
    }


    /**
     * Init admin fields
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled'                 => [
                'title'   => __( 'Enable/Disable', 'smepay-for-woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable SMEPay Payments', 'smepay-for-woocommerce' ),
                'default' => 'yes',
            ],
            'hide_for_non_admin_users' => [
                'type'    => 'checkbox',
                'label'   => __( 'Hide at checkout for non-admin users', 'smepay-for-woocommerce' ),
                'default' => 'no',
            ],
            'title'                   => [
                'title'       => __( 'Title', 'smepay-for-woocommerce' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'smepay-for-woocommerce' ),
                'default'     => _x( 'SMEPay Payment', 'SMEPay payment method', 'smepay-for-woocommerce' ),
                'desc_tip'    => true,
            ],
            'description'             => [
                'title'       => __( 'Description', 'smepay-for-woocommerce' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', 'smepay-for-woocommerce' ),
                'default'     => __( 'The goods are yours. Pay using UPI Payment via SMEPay.', 'smepay-for-woocommerce' ),
                'desc_tip'    => true,
            ],
            'client_id'               => [
                'title' => __( 'Client ID', 'smepay-for-woocommerce' ),
                'type'  => 'text',
            ],
            'client_secret'           => [
                'title' => __( 'Client Secret', 'smepay-for-woocommerce' ),
                'type'  => 'password',
            ],
            'result'                  => [
                'title'    => __( 'Payment result', 'smepay-for-woocommerce' ),
                'desc'     => __( 'Determine if order payments are successful when using this gateway.', 'smepay-for-woocommerce' ),
                'id'       => 'woo_smepay_payment_result',
                'type'     => 'select',
                'options'  => [
                    'success' => __( 'Success', 'smepay-for-woocommerce' ),
                    'failure' => __( 'Failure', 'smepay-for-woocommerce' ),
                ],
                'default'  => 'success',
                'desc_tip' => true,
            ],
        ];
    }

    /**
     * Process payment
     */
    public function process_payment( $order_id ) {

        $checkout_layout = $this->smepfowo_detect_checkout_layout_backend();

        // Skip if using block checkout layout
        if ( 'block' !== $checkout_layout['layout'] ) {
            $nonce = isset( $_POST['smepfowo_nonce'] )
                ? sanitize_text_field( wp_unslash( $_POST['smepfowo_nonce'] ) )
                : '';

            if ( ! $nonce || ! wp_verify_nonce( $nonce, 'smepfowo_nonce_action' ) ) {
                wc_add_notice( __( 'Security check failed. Please refresh the page and try again.', 'smepay-for-woocommerce' ), 'error' );
                return [ 'result' => 'failure' ];
            }
        }



        $payment_result = $this->get_option( 'result' );
        $order          = wc_get_order( $order_id );
        $slug           = $this->smepfowo_create_smepay_order( $order );

        if ( ! $slug ) {
            wc_add_notice( __( 'Failed to initiate SMEPay session. Please try again.', 'smepay-for-woocommerce' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        $order->update_meta_data( '_smepay_slug', $slug );
        $order->save();

        $checkout_layout = $this->smepfowo_detect_checkout_layout_backend();
        $redirect        = ( 'block' === $checkout_layout['layout'] )
            ? add_query_arg(
                [
                    'key'          => $order->get_order_key(),
                    'redirect_url' => $order->get_checkout_order_received_url(),
                    'smepay_slug'  => $slug,
                ],
                $order->get_checkout_payment_url( false )
            )
            : '';

        if ( 'success' === $payment_result ) {
            return [
                'result'       => 'success',
                'smepay_slug'  => $slug,
                'order_id'     => $order_id,
                'order_key'    => $order->get_order_key(),
                'redirect_url' => $order->get_checkout_order_received_url(),
                'redirect'     => $redirect,
            ];
        }

        if ( 'success' !== $payment_result ) {
            wc_add_notice( __( 'Order payment failed. Please review the gateway settings.', 'smepay-for-woocommerce' ), 'error' );
            $order->update_status( 'failed', __( 'Order payment failed.', 'smepay-for-woocommerce' ) );
            return [
                'result'   => 'failure',
                'redirect' => '',
            ];
        }
    }

    /**
     * Create SMEPay order
     */
    private function smepfowo_create_smepay_order( $order ) {
        $base_order_id  = (string) $order->get_id();
        $new_order_id   = $base_order_id . '-' . time();
        $existing_order_id = $order->get_meta( '_smepay_order_id' );

        if ( ! empty( $existing_order_id ) ) {
            [ $existing_base_id, $existing_timestamp ] = explode( '-', $existing_order_id );
            [ $new_base_id, $new_timestamp ] = explode( '-', $new_order_id );

            if ( $existing_base_id === $new_base_id && $existing_timestamp === $new_timestamp ) {
                return null;
            }
        }

        $token = $this->get_access_token();
        if ( ! $token ) {
            return null;
        }

        $order->update_meta_data( '_smepay_order_id', $new_order_id );
        $order->save();

        $data = [
            'client_id'        => $this->client_id,
            'amount'           => (string) $order->get_total(),
            'order_id'         => $new_order_id,
            'callback_url'     => $order->get_checkout_order_received_url(),
            'customer_details' => [
                'email'  => $order->get_billing_email(),
                'mobile' => $order->get_billing_phone(),
                'name'   => $order->get_formatted_billing_full_name(),
            ],
        ];

        $response = wp_remote_post(
            'https://apps.typof.in/api/external/create-order',
            [
                'body'    => wp_json_encode( $data ),
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => self::TIMEOUT,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        return $decoded['order_slug'] ?? null;
    }

    /**
     * Get access token
     */
    private function get_access_token() {
        $response = wp_remote_post(
            'https://apps.typof.in/api/external/auth',
            [
                'body'    => wp_json_encode(
                    [
                        'client_id'     => $this->client_id,
                        'client_secret' => $this->client_secret,
                    ]
                ),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'timeout' => self::TIMEOUT,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return $body['access_token'] ?? null;
    }

    /**
     * Validate order on thank you page
     */
    public function send_validate_order_request( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $token = $this->get_access_token();
        if ( ! $token ) {
            return;
        }

        $data = [
            'client_id' => $this->client_id,
            'amount'    => $order->get_total(),
            'slug'      => $order->get_meta( '_smepay_slug' ),
        ];

        $response = wp_remote_post(
            'https://apps.typof.in/api/external/validate-order',
            [
                'method'  => 'POST',
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
                'body'    => wp_json_encode( $data ),
                'timeout' => self::TIMEOUT,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return;
        }

        $decoded_response = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $decoded_response['status'], $decoded_response['payment_status'] ) && $decoded_response['status'] && 'paid' === $decoded_response['payment_status'] ) {
            if ( $order->get_status() !== 'completed' ) {
                $order->payment_complete();
                $order->add_order_note( __( 'Payment confirmed via SMEPay.', 'smepay-for-woocommerce' ) );
            }
        } else {
            if ( $order->get_status() !== 'failed' ) {
                $order->update_status( 'failed', __( 'Payment failed via SMEPay.', 'smepay-for-woocommerce' ) );
                $order->add_order_note( __( 'Payment failed via SMEPay..', 'smepay-for-woocommerce' ) );
            }
        }
    }
}