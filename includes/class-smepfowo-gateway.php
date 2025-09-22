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

    protected $mode;

    /**
     * Constructor
     */
    public function __construct() {
        $this->icon = apply_filters(
            'smepfowo_gateway_icon',
            trailingslashit( SMEPFOWO_Plugin::plugin_url() ) . 'resources/img/smepfowo.svg'
        );
        $this->has_fields         = false;
        $this->method_title       = _x( 'SMEPay UPI Payment', 'SMEPay payment method', 'smepay-for-woocommerce' );
        $this->method_description = __( 'Pay via UPI apps.', 'smepay-for-woocommerce' );

        $this->mode = strtolower( trim( $this->get_option( 'mode', 'production' ) ) );
        $this->client_id = $this->mode === 'development'
            ? $this->get_option( 'dev_client_id' )
            : $this->get_option( 'client_id' );
        $this->client_secret = $this->mode === 'development'
            ? $this->get_option( 'dev_client_secret' )
            : $this->get_option( 'client_secret' );


        $this->init_form_fields();
        $this->init_settings();

        $this->title                   = $this->get_option( 'title' );
        $this->description             = $this->get_option( 'description' );
        $this->instructions            = $this->get_option( 'instructions', $this->description );
        $this->hide_for_non_admin_users = $this->get_option( 'hide_for_non_admin_users' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'send_validate_order_request' ], 10, 1 );
        add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
        add_action( 'woocommerce_review_order_before_submit', [ $this, 'add_nonce_to_checkout' ] );

        if ( is_admin() && 'development' === $this->mode ) {
            add_action( 'admin_notices', function() {
                $screen = get_current_screen();
                if ( isset( $screen->id ) && strpos( $screen->id, 'woocommerce' ) !== false ) {
                    printf(
                        '<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
                        esc_html__( 'SMEPay:', 'smepay-for-woocommerce' ),
                        esc_html__( 'You are currently in Development Mode. Orders will use test credentials.', 'smepay-for-woocommerce' )
                    );
                }
            } );
        }


        if ( is_admin() && current_user_can( 'manage_woocommerce' ) ) {
            add_action( 'admin_enqueue_scripts', [ $this, 'admin_conditional_fields_script' ] );
        }
    }

    /**
     * Get base API URL depending on mode
     *
     * @return string
     */
    private function get_api_base_url() {
        return ( $this->mode === 'development' )
            ? 'https://staging.smepay.in/api/wiz/'
            : 'https://extranet.smepay.in/api/wiz/';
    }

    /**
     * Get the current mode of the gateway.
     *
     * @return string Returns the mode, e.g. 'production' or 'sandbox'.
     */
    public function get_mode() {
        return $this->mode;
    }

    /**
     * Determine if SSL is required for this gateway.
     *
     * @return bool Returns true if the gateway requires SSL (i.e., in production mode), false otherwise.
     */
    public function requires_ssl() {
        return $this->get_mode() === 'production';
    }

    
    /**
     * Determine whether the gateway is available at checkout.
     *
     * @return bool
     */
    public function is_available() {
        if ( ! is_ssl() && $this->requires_ssl() ) {
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


    public function admin_conditional_fields_script( $hook ) {
        if ( 'woocommerce_page_wc-settings' !== $hook ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe usage for script enqueue on WC settings.
        if ( isset( $_GET['section'] ) && $_GET['section'] === $this->id ) {
            wp_add_inline_script( 'jquery', "
                jQuery(document).ready(function($) {
                    function toggleSMEPayFields() {
                        var mode = $('#woocommerce_{$this->id}_mode').val();
                        $('.smepfowo-prod-field').closest('tr').toggle(mode === 'production');
                        $('.smepfowo-dev-field').closest('tr').toggle(mode === 'development');
                    }

                    toggleSMEPayFields();
                    $('#woocommerce_{$this->id}_mode').on('change input', toggleSMEPayFields);
                });
            " );
        }
    }


    /**
     * Get and cache the checkout layout.
     *
     * @return array
     */
    protected function get_checkout_layout() {
        static $layout = null;

        if ( $layout === null ) {
            $layout = $this->smepfowo_detect_checkout_layout_backend();
        }

        return $layout;
    }



    /**
     * Enqueue frontend scripts
     */
    public function payment_scripts() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended

        $checkout_layout = $this->get_checkout_layout();

        if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_order_received_page() ) {
            return;
        }

        if ( ! is_ssl() && $this->requires_ssl() ) {
            return;
        }

        // Load the remote SMEPay widget
        wp_enqueue_script(
            'smepfowo-checkout',
            'https://typof.co/smepay/checkout.js',
            [],
            SMEPFOWO_Plugin::VERSION,
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
        $script_url  = trailingslashit( SMEPFOWO_Plugin::plugin_url() ) . $script_path;

        wp_register_script(
            'smepfowo-handler',
            $script_url,
            [ 'jquery', 'wp-i18n' ],
            SMEPFOWO_Plugin::VERSION,
            true
        );

        // Translation support
        wp_set_script_translations(
            'smepfowo-handler',
            'smepay-for-woocommerce',
            SMEPFOWO_Plugin::plugin_abspath() . 'languages/'
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
                'default'     => _x( 'UPI Pay', 'SMEPay payment method', 'smepay-for-woocommerce' ),
                'desc_tip'    => true,
            ],
            'description'             => [
                'title'       => __( 'Description', 'smepay-for-woocommerce' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', 'smepay-for-woocommerce' ),
                'default'     => __( 'Secure by SMEPay.', 'smepay-for-woocommerce' ),
                'desc_tip'    => true,
            ],
            'mode' => [
                'title'       => __( 'Mode', 'smepay-for-woocommerce' ),
                'type'        => 'select',
                'description' => __( 'Select environment mode (Production or Development)', 'smepay-for-woocommerce' ),
                'default'     => 'production',
                'desc_tip'    => true,
                'options'     => [
                    'production'  => __( 'Production', 'smepay-for-woocommerce' ),
                    'development' => __( 'Development', 'smepay-for-woocommerce' ),
                ],
            ],
            'client_id' => [
                'title' => __( 'Production Client ID', 'smepay-for-woocommerce' ),
                'type'  => 'text',
                'class' => 'smepfowo-prod-field',
            ],
            'client_secret' => [
                'title' => __( 'Production Client Secret', 'smepay-for-woocommerce' ),
                'type'  => 'password',
                'class' => 'smepfowo-prod-field',
            ],
            'dev_client_id' => [
                'title' => __( 'Development Client ID', 'smepay-for-woocommerce' ),
                'type'  => 'text',
                'class' => 'smepfowo-dev-field',
            ],
            'dev_client_secret' => [
                'title' => __( 'Development Client Secret', 'smepay-for-woocommerce' ),
                'type'  => 'password',
                'class' => 'smepfowo-dev-field',
            ],
            'result'                  => [
                'title'    => __( 'Payment result', 'smepay-for-woocommerce' ),
                'desc'     => __( 'Determine if order payments are successful when using this gateway.', 'smepay-for-woocommerce' ),
                'id'       => 'woo_smepfowo_payment_result',
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
        // Ensure the gateway is still available
        if ( ! $this->is_available() ) {
            wc_add_notice( __( 'Payment method is currently unavailable. Please choose another method.', 'smepay-for-woocommerce' ), 'error' );
            return [ 'result' => 'failure' ];
        }
        
        $checkout_layout = $this->get_checkout_layout();

        // Verify nonce only for classic checkout (non-block)
        if ( 'block' !== $checkout_layout['layout'] ) {
            $nonce = isset( $_POST['smepfowo_nonce'] )
                ? sanitize_text_field( wp_unslash( $_POST['smepfowo_nonce'] ) )
                : '';

            if ( ! $nonce || ! wp_verify_nonce( $nonce, 'smepfowo_nonce_action' ) ) {
                wc_add_notice( __( 'Security check failed. Please refresh the page and try again.', 'smepay-for-woocommerce' ), 'error' );
                return [ 'result' => 'failure' ];
            }
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wc_add_notice( __( 'Invalid order.', 'smepay-for-woocommerce' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        $slug = $this->smepfowo_create_order( $order );
        if ( ! $slug ) {
            wc_add_notice( __( 'Failed to initiate SMEPay session. Please try again.', 'smepay-for-woocommerce' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        $order->update_meta_data( '_smepfowo_slug', $slug );
        $order->save();

        // Prepare redirect URL (only used in block checkout)
        $redirect_url = ( 'block' === $checkout_layout['layout'] )
            ? add_query_arg(
                [
                    'key'           => $order->get_order_key(),
                    'redirect_url'  => $order->get_checkout_order_received_url(),
                    'smepfowo_slug' => $slug,
                ],
                $order->get_checkout_payment_url( false )
            )
            : '';

        // Handle payment result based on settings
        $payment_result = $this->get_option( 'result' );
        if ( $payment_result === 'success' ) {
            return [
                'result'        => 'success',
                'smepfowo_slug' => $slug,
                'order_id'      => $order_id,
                'order_key'     => $order->get_order_key(),
                'redirect_url'  => $order->get_checkout_order_received_url(),
                'redirect'      => $redirect_url,
            ];
        }

        // On failure
        wc_add_notice( __( 'Order payment failed. Please review the gateway settings.', 'smepay-for-woocommerce' ), 'error' );
        $order->update_status( 'failed', __( 'Order payment failed.', 'smepay-for-woocommerce' ) );

        return [
            'result'   => 'failure',
            'redirect' => '',
        ];
    }

    /**
     * Create SMEPay order
     *
     * @param WC_Order $order WooCommerce order object.
     * @return string|null SMEPay order slug or null on failure.
     */
    private function smepfowo_create_order( $order ) {
        $order_id  = (string) $order->get_id();
        $timestamp = time();
        $random    = wp_rand(1000, 9999);
        $new_order_id = "{$order_id}-{$timestamp}-{$random}";
        $existing_order_id = $order->get_meta( '_smepfowo_order_id' );

        // Prevent duplicate creation
        if ( $existing_order_id === $new_order_id ) {
            return null;
        }

        $token = $this->get_access_token();
        if ( ! $token ) {
            return null;
        }

        // Save new order ID and persist immediately
        $order->update_meta_data( '_smepfowo_order_id', $new_order_id );
        $order->save();

        // Prepare payload
        $payload = [
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
            $this->get_api_base_url() . 'external/create-order',
            [
                'body'    => wp_json_encode( $payload ),
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

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $slug = $body['order_slug'] ?? null;

        if ( is_string( $slug ) && $slug !== '' ) {
            $order->update_meta_data( '_smepfowo_slug', $slug );
            $order->save();
            return $slug;
        }

        return null;
    }


    /**
     * Get access token
     */
    private function get_access_token() {
        $response = wp_remote_post(
            $this->get_api_base_url() . 'external/auth',
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

        // 🚫 Abort if this order was not paid using SMEPay
        if ( $order->get_payment_method() !== $this->id ) {
            return;
        }

        // ✅ Check if gateway is currently available
        if ( ! $this->is_available() ) {
            return;
        }


        // 🔐 Only now proceed to validate via SMEPay API
        $token = $this->get_access_token();
        if ( ! $token ) {
            return;
        }

        $data = [
            'client_id' => $this->client_id,
            'amount'    => (float)$order->get_total(),
            'slug'      => $order->get_meta( '_smepfowo_slug' ),
        ];

        $response = wp_remote_post(
            $this->get_api_base_url() . 'external/validate-order',
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

        if ( isset( $decoded_response['status'], $decoded_response['payment_status'] ) && $decoded_response['status'] && 'SUCCESS' === $decoded_response['payment_status'] ) {
            if ( $order->get_status() !== 'completed' ) {
                $order->payment_complete();
                $order->add_order_note( __( 'Payment confirmed via SMEPay.', 'smepay-for-woocommerce' ) );
            }
        } else {
            if ( $order->get_status() !== 'failed' ) {
                $order->update_status( 'failed', __( 'Payment failed via SMEPay.', 'smepay-for-woocommerce' ) );
                $order->add_order_note( __( 'Payment failed via SMEPay.', 'smepay-for-woocommerce' ) );
            }
        }
    }
}