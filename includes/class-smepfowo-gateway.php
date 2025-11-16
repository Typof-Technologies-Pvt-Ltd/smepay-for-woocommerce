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

    public $id = ''; // empty by default, let child classes define it

    protected $client_id;
    protected $client_secret;

    protected $mode;
    protected $display_mode;

    /**
     * Constructor
     */
    public function __construct() {
        if ( empty( $this->id ) ) {
            $this->id = 'smepfowo'; // fallback for base class
        }
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
        $this->display_mode = $this->get_option( 'display_mode', 'wizard' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        if ( $this->id === 'smepfowo' ) {
            add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'send_validate_order_request' ], 10, 1 );
        }
        add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
        add_action( 'woocommerce_review_order_before_submit', [ $this, 'add_nonce_to_checkout' ] );

        add_action( 'wp_ajax_check_smepfowo_order_status', [ $this, 'ajax_check_smepfowo_order_status' ] );
        add_action( 'wp_ajax_nopriv_check_smepfowo_order_status', [ $this, 'ajax_check_smepfowo_order_status' ] );


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
     * Get base API URL depending on mode and display mode.
     *
     * @return string
     */
    protected function get_api_base_url() {
        $base = ( $this->mode === 'development' )
            ? 'https://staging.smepay.in/api/'
            : 'https://extranet.smepay.in/api/';

        // If display mode is wizard, append 'wiz/' to the base URL
        if ( $this->display_mode === 'wizard' ) {
            $base .= 'wiz/';
        }

        return $base;
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

        // Load the remote SMEPay widget ONLY in wizard mode
        if ( $this->display_mode === 'wizard' ) {
            wp_enqueue_script(
                'smepfowo-checkout',
                'https://typof.co/smepay/checkout-v2.js',
                [],
                SMEPFOWO_Plugin::VERSION,
                true
            );
        }

        // Do not proceed if plugin is disabled or missing credentials
        if ( 'no' === $this->enabled || empty( $this->client_id ) || empty( $this->client_secret ) ) {
            return;
        }

        // ✅ Enqueue CSS here
        wp_enqueue_style(
            'smepfowo-frontend',
            trailingslashit( SMEPFOWO_Plugin::plugin_url() ) . 'resources/css/smepfowo-frontend.css',
            [],
            SMEPFOWO_Plugin::VERSION
        );

        // Skip if using block checkout layout (handled separately)
        if ( 'block' === $checkout_layout['layout'] ) {
            return;
        }


        // Enqueue polling script only for inline display mode
        if ( $this->display_mode === 'inline' ) {
            wp_enqueue_script(
                'smepfowo-polling',
                trailingslashit( SMEPFOWO_Plugin::plugin_url() ) . 'resources/js/frontend/smepfowo-polling.js',
                [ 'jquery' ],
                SMEPFOWO_Plugin::VERSION,
                true
            );

            wp_localize_script(
                'smepfowo-polling',
                'smepfowo_polling_data',
                [
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'smepfowo_nonce_action' ),
                    'order_id' => absint( WC()->session->get( 'order_awaiting_payment' ) ),
                ]
            );

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
                'display_mode' => $this->display_mode,
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
            'display_mode' => [
                'title'       => __( 'Display Mode', 'smepay-for-woocommerce' ),
                'type'        => 'select',
                'description' => __( 'Choose how the QR code should be displayed to the customer during checkout.', 'smepay-for-woocommerce' ),
                'default'     => 'wizard',
                'desc_tip'    => true,
                'options'     => [
                    'wizard' => __( 'Popup Wizard (default)', 'smepay-for-woocommerce' ),
                    'inline' => __( 'Inline QR Code', 'smepay-for-woocommerce' ),
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

        $result = $this->smepfowo_create_order( $order );

        if ( empty( $result['success'] ) ) {
            if ( ! empty( $result['error'] ) ) {
                // translators: %s is the API error message returned by SMEPay.
                $error_message = sprintf( __( 'Failed to initiate SMEPay session: %s', 'smepay-for-woocommerce' ), $result['error'] );
            } else {
                $error_message = __( 'Failed to initiate SMEPay session. Please try again.', 'smepay-for-woocommerce' );
            }

            wc_add_notice( $error_message, 'error' );
            return [ 'result' => 'failure' ];
        }


        $slug = $result['slug'] ?? '';
        $qr_code = '';
        $payment_link = '';
        $intents = [];

        // Inline QR code support
        if ( $this->get_option( 'display_mode' ) === 'inline' ) {
            $initiate = $this->smepfowo_initiate_payment( $slug );

            if ( ! empty( $initiate['status'] ) ) {
                $qr_code      = $initiate['qr_code'] ?? '';
                $payment_link = $initiate['payment_link'] ?? '';
                $intents      = $initiate['intents'] ?? [];
            } else {
                if ( ! empty( $initiate['error'] ) ) {
                    // translators: %s is the API error message returned by SMEPay.
                    $error_message = sprintf( __( 'Failed to generate UPI QR code: %s', 'smepay-for-woocommerce' ), $initiate['error'] );
                } else {
                    $error_message = __( 'Failed to generate UPI QR code.', 'smepay-for-woocommerce' );
                }

                wc_add_notice( $error_message, 'error' );
                return [ 'result' => 'failure' ];
            }
        }

        // Update order meta once
        $order->update_meta_data( '_smepfowo_slug', $slug );
        if ( $qr_code ) $order->update_meta_data( '_smepfowo_qr_code', $qr_code );
        if ( $payment_link ) $order->update_meta_data( '_smepfowo_payment_link', $payment_link );
        if ( $intents ) $order->update_meta_data( '_smepfowo_intents', $intents );
        $order->save();

        // Prepare redirect URL (only used in block checkout)
        $redirect_url = ( 'block' === $checkout_layout['layout'] )
            ? add_query_arg(
                [
                    'key'           => $order->get_order_key(),
                    'redirect_url'  => $order->get_checkout_order_received_url(),
                    'smepfowo_slug' => $slug,
                    'order_id'      => $order_id, 
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
                'qr_code'       => $qr_code,
                'payment_link'  => $payment_link,
                'intents'       => $intents,
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
     * @return array Associative array with 'success', 'slug', and 'error' keys.
     */
    protected function smepfowo_create_order( $order ) {
        // Prevent creating order if total is less than 1
        if ( $order->get_total() < 1 ) {
            $currency = get_woocommerce_currency_symbol();
            /* translators: Message shown when the order total is too small to process a payment, %s is the currency symbol */
            $error_message = sprintf(
                __( 'Order total must be at least %s1 to process payment.', 'smepay-for-woocommerce' ),
                $currency
            );
            wc_add_notice( $error_message, 'error' );
            return [
                'success' => false,
                'error'   => $error_message
            ];
        }

        $order_id  = (string) $order->get_id();
        $timestamp = time();
        $random    = wp_rand(1000, 9999);
        $new_order_id = "{$order_id}-{$timestamp}-{$random}";
        $existing_order_id = $order->get_meta( '_smepfowo_order_id' );

        // Prevent duplicate creation
        if ( $existing_order_id === $new_order_id ) {
            return [
                'success' => false,
                'error'   => 'Duplicate order detected.'
            ];
        }

        $token_result = $this->get_access_token();
        if ( empty( $token_result['token'] ) ) {
            $error_message = $token_result['error'] ?? 'Failed to get access token.';
            return [
                'success' => false,
                'error'   => $error_message
            ];
        }

        $token = $token_result['token'];

        $payload = [
            'client_id'        => $this->client_id,
            'amount'           => (string) number_format( (float) $order->get_total(), 2, '.', '' ),
            'order_id'         => $new_order_id,
            'callback_url'     => home_url('/wp-json/smepay/v1/webhook'),
            'customer_details' => [
                'email'  => $order->get_billing_email(),
                'mobile' => $order->get_billing_phone(),
                'name'   => $order->get_formatted_billing_full_name(),
            ],
        ];

        $url = $this->get_api_base_url() . 'external/order/create';
        $response = wp_remote_post(
            $url,
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
            return [
                'success' => false,
                'error'   => $response->get_error_message()
            ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return [
                'success' => false,
                'error'   => $body['error']
            ];
        }

        // Save the new_order_id
        $order->update_meta_data( '_smepfowo_order_id', $new_order_id );

        // Save the order slug if returned
        $slug = $body['order_slug'] ?? null;
        if ( is_string( $slug ) && $slug !== '' ) {
            $order->update_meta_data( '_smepfowo_slug', $slug );
            $order->save();
            return [
                'success' => true,
                'slug'    => $slug
            ];
        }

        $order->save();

        return [
            'success' => false,
            'error'   => 'Unknown API error'
        ];
    }

    /**
     * Initiate SMEPay Payment
     *
     * @param string $slug SMEPay order slug.
     * @return array|null Payment initiation response or null on failure.
     */
    protected function smepfowo_initiate_payment( $slug ) {
        if ( empty( $slug ) || empty( $this->client_id ) ) {
            return [
                'status' => false,
                'error'  => 'Invalid slug or client ID.',
            ];
        }

        $token_result = $this->get_access_token();

        if ( empty( $token_result['token'] ) ) {
            if ( ! empty( $token_result['error'] ) ) {
                $error_message = sprintf(
                    // translators: %s is the error message returned while fetching the access token.
                    __( 'Failed to get access token: %s', 'smepay-for-woocommerce' ),
                    $token_result['error']
                );
            } else {
                $error_message = __( 'Failed to get access token.', 'smepay-for-woocommerce' );
            }

            wc_add_notice( $error_message, 'error' );

            return [
                'status' => false,
                'error'  => $error_message,
            ];
        }

        $token = $token_result['token'];

        $payload = [
            'slug'      => $slug,
            'client_id' => $this->client_id,
        ];

        $response = wp_remote_post(
            $this->get_api_base_url() . 'external/order/initiate',
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
            $error_message = $response->get_error_message();

            wc_add_notice( sprintf(
                // translators: %s is the error message returned by SMEPay.
                __( 'SMEPay request failed: %s', 'smepay-for-woocommerce' ),
                $error_message
            ), 'error' );

            return [
                'status' => false,
                'error'  => $error_message,
            ];
        }


        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['status'] ) && $body['status'] === true ) {
            return $body;
        }

        if ( ! empty( $body['error'] ) ) {
            // translators: %s is the error message returned by SMEPay.
            $error_message = sprintf( __( 'Failed to initiate SMEPay payment: %s', 'smepay-for-woocommerce' ), $body['error'] );
        } else {
            $error_message = __( 'Failed to initiate SMEPay payment.', 'smepay-for-woocommerce' );
        }

        wc_add_notice( $error_message, 'error' );


        return [
            'status' => false,
            'error'  => $error_message,
        ];
    }


    /**
     * Check SMEPay order payment status.
     *
     * @param int $order_id WooCommerce order ID.
     * @return array Status response. Always includes 'status' key (true/false) and optional 'error' or 'payment_status'.
     */
    public function smepfowo_check_order_status( $order_id ) {

        // Validate input
        if ( empty( $order_id ) || empty( $this->client_id ) ) {
            return [
                'status' => false,
                'error'  => 'Invalid order ID or client ID.',
            ];
        }

        // Get WooCommerce order
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return [
                'status' => false,
                'error'  => 'Order not found.',
            ];
        }

        // Get SMEPay order ID from order meta
        $smepfowo_order_id = $order->get_meta( '_smepfowo_order_id' );
        if ( empty( $smepfowo_order_id ) ) {
            return [
                'status' => false,
                'error'  => 'SMEPay order ID not found.',
            ];
        }

        // Get access token
        $token_result = $this->get_access_token();
        if ( empty( $token_result['token'] ) ) {
            $error_message = $token_result['error'] ?? 'Failed to get access token.';
            return [
                'status' => false,
                'error'  => $error_message,
            ];
        }

        $token = $token_result['token'];

        // Prepare payload
        $payload = [
            'order_id'  => $smepfowo_order_id,
            'client_id' => $this->client_id,
        ];

        // Call SMEPay API
        $response = wp_remote_post(
            $this->get_api_base_url() . 'external/order/status',
            [
                'body'    => wp_json_encode( $payload ),
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => self::TIMEOUT,
            ]
        );

        // Handle request errors
        if ( is_wp_error( $response ) ) {
            return [
                'status' => false,
                'error'  => 'API request failed: ' . $response->get_error_message(),
            ];
        }

        // Check HTTP status code
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            return [
                'status' => false,
                'error'  => "SMEPay API returned HTTP $response_code",
            ];
        }

        // Decode JSON response
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) ) {
            return [
                'status' => false,
                'error'  => 'Invalid response from SMEPay API.',
            ];
        }

        // Successful API response
        if ( ! empty( $body['status'] ) ) {
            return [
                'status'         => true,
                'payment_status' => $body['payment_status'] ?? '',
                'raw'            => $body, // optional: raw response for debugging
            ];
        }

        // API returned failure
        return [
            'status' => false,
            'error'  => $body['error'] ?? 'Failed to retrieve order status.',
        ];
    }



    /**
     * Get access token
     *
     * @return array Returns array with 'token' or 'error':
     *               ['token' => '...'] on success
     *               ['error' => '...'] on failure
     */
    protected function get_access_token() {
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

        // Network / request errors
        if ( is_wp_error( $response ) ) {
            return [ 'error' => $response->get_error_message() ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        // API returned an error
        if ( isset( $body['error'] ) ) {
            return [ 'error' => $body['error'] ];
        }

        // Access token available
        if ( ! empty( $body['access_token'] ) ) {
            return [ 'token' => $body['access_token'] ];
        }

        // Fallback unknown error
        return [ 'error' => 'Unable to retrieve access token from SMEPay API.' ];
    }
    
    /**
     * Validate order on thank you page.
     *
     * @param int $order_id WooCommerce order ID.
     */
    public function send_validate_order_request( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        if ( $order->get_payment_method() !== $this->id ) {
            return;
        }

        if ( ! $this->is_available() ) {
            return;
        }

        // Get access token
        $token_result = $this->get_access_token();

        if ( empty( $token_result['token'] ) ) {
            if ( ! empty( $token_result['error'] ) ) {
                // translators: %s is the error message returned while fetching the access token.
                $error_message = sprintf( __( 'Failed to get access token: %s', 'smepay-for-woocommerce' ), $token_result['error'] );
            } else {
                $error_message = __( 'Failed to get access token.', 'smepay-for-woocommerce' );
            }

            // Add WooCommerce notice for the user
            wc_add_notice( $error_message, 'error' );
            // Add order note for admin/debugging
            $order->add_order_note( $error_message );
            return;
        }


        $token = $token_result['token'];

        $data = [
            'client_id' => $this->client_id,
            'amount'    => round( (float) $order->get_total(), 2 ),
            'slug'      => $order->get_meta( '_smepfowo_slug' ),
        ];

        $response = wp_remote_post(
            $this->get_api_base_url() . 'external/order/validate',
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
            $error_message = $response->get_error_message();

            // translators: %s is the error message returned by SMEPay.
            $notice_message = sprintf( __( 'SMEPay validation failed: %s', 'smepay-for-woocommerce' ), $error_message );
            wc_add_notice( $notice_message, 'error' );

            // translators: %s is the error message returned by SMEPay.
            $order_note = sprintf( __( 'SMEPay connection error: %s', 'smepay-for-woocommerce' ), $error_message );
            $order->add_order_note( $order_note );

            return;
        }


        $decoded_response = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $decoded_response['status'], $decoded_response['payment_status'] ) && $decoded_response['status'] ) {
            if ( 'SUCCESS' === $decoded_response['payment_status'] || 'TEST_SUCCESS' === $decoded_response['payment_status'] ) {
                if ( $order->get_status() !== 'completed' ) {
                    $order->payment_complete();
                    $order->add_order_note( __( 'Payment confirmed via SMEPay.', 'smepay-for-woocommerce' ) );
                }
            } else {
                // API returned failure with optional error message
                $api_error = $decoded_response['error'] ?? __( 'Payment failed via SMEPay.', 'smepay-for-woocommerce' );

                if ( $order->get_status() !== 'failed' ) {
                    // translators: %s is the API error message returned by SMEPay.
                    $order->update_status( 'failed', sprintf( __( 'SMEPay error: %s', 'smepay-for-woocommerce' ), $api_error ) );

                    // translators: %s is the API error message returned by SMEPay.
                    $order->add_order_note( sprintf( __( 'SMEPay error: %s', 'smepay-for-woocommerce' ), $api_error ) );
                }
                
                // translators: $api_error contains the API error returned by SMEPay.
                wc_add_notice( $api_error, 'error' );

            }
        } else {
            // Unexpected API response
            $error_message = __( 'Invalid response from SMEPay API.', 'smepay-for-woocommerce' );
            // translators: %s is the error message returned by SMEPay API.
            $order->add_order_note( sprintf( __( 'SMEPay error: %s', 'smepay-for-woocommerce' ), $error_message ) );
            wc_add_notice( $error_message, 'error' );
        }
    }

}