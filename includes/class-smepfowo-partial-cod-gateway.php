<?php
/**
 * SMEPFOWO_Partial_COD_Gateway class
 * Enables partial online payment with COD using SMEPay
 *
 * @package WooCommerce SMEPay Payments Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SMEPFOWO_Partial_COD_Gateway extends SMEPFOWO_Gateway {

    public function __construct() {
        $this->id = 'smepfowo_partial_cod';

        // Call parent constructor to load base settings
        parent::__construct();

        // Reload settings with new ID to ensure correct values are fetched
        $this->title              = $this->get_option( 'title', __( 'Partial Payment with COD', 'smepay-for-woocommerce' ) );
        $this->description        = $this->get_option( 'description', __( 'Pay part now, rest on delivery.', 'smepay-for-woocommerce' ) );
        $this->method_title       = _x( 'Partial Payment with COD by SMEPay', 'SMEPay payment method', 'smepay-for-woocommerce' );
        $this->method_description = __( 'Collect a small advance payment online via SMEPay, rest paid as Cash on Delivery.', 'smepay-for-woocommerce' );

        // Load credentials from settings manually, using the correct ID
        $this->mode = strtolower( trim( $this->get_option( 'mode', 'production' ) ) );
        $this->client_id = $this->mode === 'development'
            ? $this->get_option( 'dev_client_id' )
            : $this->get_option( 'client_id' );
        $this->client_secret = $this->mode === 'development'
            ? $this->get_option( 'dev_client_secret' )
            : $this->get_option( 'client_secret' );

        // Load settings form
        $this->init_form_fields();
        $this->init_settings();

        add_action( 'wp_ajax_check_smepfowo_order_status', [ $this, 'ajax_check_smepfowo_order_status' ] );
        add_action( 'wp_ajax_nopriv_check_smepfowo_order_status', [ $this, 'ajax_check_smepfowo_order_status' ] );


        add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'send_validate_partial_order_request' ], 10, 1 );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
    }

    public function init_form_fields() {
        parent::init_form_fields();

        $this->form_fields['partial_percentage'] = [
            'title'       => __( 'Advance Percentage', 'smepay-for-woocommerce' ),
            'type'        => 'number',
            'description' => __( 'Percentage of the total order amount to be paid in advance using SMEPay.', 'smepay-for-woocommerce' ),
            'default'     => 30,
            'desc_tip'    => true,
        ];
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( __( 'Invalid order.', 'smepay-for-woocommerce' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        $partial_percent = absint( $this->get_option( 'partial_percentage', 30 ) );
        $partial_amount  = round( $order->get_total() * ( $partial_percent / 100 ), 2 );

        // Save partial payment meta
        $order->update_meta_data( '_smepfowo_partial_cod', 'yes' );
        $order->update_meta_data( '_smepfowo_partial_amount', $partial_amount );
        $order->save();

        // Create partial SMEPay order
        $result = $this->smepfowo_create_order_partial( $order, $partial_amount );

        if ( empty( $result['slug'] ) ) {
            $error_message = $result['error'] ?? __( 'Failed to create partial SMEPay order.', 'smepay-for-woocommerce' );
            wc_add_notice( $error_message, 'error' );
            return [ 'result' => 'failure' ];
        }

        $slug = $result['slug'];

        // Optional inline QR support
        $qr_code      = '';
        $payment_link = '';
        $intents      = [];

        if ( $this->get_option( 'display_mode' ) === 'inline' ) {
            $initiate = $this->smepfowo_initiate_payment( $slug );

            if ( ! empty( $initiate['status'] ) && $initiate['status'] ) {
                $qr_code      = $initiate['qr_code'] ?? '';
                $payment_link = $initiate['payment_link'] ?? '';
                $intents      = $initiate['intents'] ?? [];

                $order->update_meta_data( '_smepfowo_qr_code', $qr_code );
                $order->update_meta_data( '_smepfowo_payment_link', $payment_link );
                $order->update_meta_data( '_smepfowo_intents', $intents );
                $order->save();
            } else {
                $error_message = $initiate['error'] ?? __( 'Failed to generate UPI QR code.', 'smepay-for-woocommerce' );
                wc_add_notice( $error_message, 'error' );
                return [ 'result' => 'failure' ];
            }
        }

        // Determine checkout layout
        $checkout_layout = $this->get_checkout_layout();
        $redirect_url = ( 'block' === $checkout_layout['layout'] )
            ? add_query_arg(
                [
                    'key'                        => $order->get_order_key(),
                    'redirect_url'               => $order->get_checkout_order_received_url(),
                    'smepfowo_partial_cod_slug'  => $slug,
                    'order_id'                   => $order_id,
                ],
                $order->get_checkout_payment_url( false )
            )
            : '';

        return [
            'result'                       => 'success',
            'smepfowo_partial_cod_slug'    => $slug,
            'order_id'                     => $order_id,
            'order_key'                    => $order->get_order_key(),
            'redirect_url'                 => $order->get_checkout_order_received_url(),
            'redirect'                     => $redirect_url,
            'qr_code'                      => $qr_code,
            'payment_link'                 => $payment_link,
            'intents'                      => $intents,
        ];
    }


    /**
     * Create a partial SMEPay order
     *
     * @param WC_Order $order WooCommerce order object
     * @param float $amount Partial payment amount
     * @return array Result with slug or error
     */
    private function smepfowo_create_order_partial( $order, $amount ) {
        // Prevent creating order if amount is less than 1
        if ( $amount < 1 ) {
            $currency = get_woocommerce_currency_symbol();
            /* translators: Message shown when the partial payment amount is too small to process, %s is the currency symbol */
            $error_message = sprintf(
                __( 'Partial payment amount must be at least %s1.', 'smepay-for-woocommerce' ),
                $currency
            );
            wc_add_notice( $error_message, 'error' );
            return ['error' => $error_message];
        }

        $order_id    = (string) $order->get_id();
        $timestamp   = time();
        $random      = wp_rand( 1000, 9999 );
        $new_order_id = "{$order_id}-{$timestamp}-{$random}";

        $token_result = $this->get_access_token();
        if ( empty( $token_result['token'] ) ) {
            $error_message = $token_result['error'] ?? __( 'Unable to retrieve access token.', 'smepay-for-woocommerce' );
            wc_add_notice( $error_message, 'error' );
            return ['error' => $error_message];
        }

        $token = $token_result['token'];

        $payload = [
            'client_id'        => $this->client_id,
            'amount'           => number_format( (float) $amount, 2, '.', '' ),
            'order_id'         => $new_order_id,
            'callback_url'     => home_url( '/wp-json/smepay/v1/webhook' ),
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
            return ['error' => $response->get_error_message()];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return ['error' => $body['error']];
        }

        if ( ! empty( $body['order_id'] ) ) {
            $order->update_meta_data( '_smepfowo_order_id', $body['order_id'] );
        }

        $slug = $body['order_slug'] ?? null;
        if ( ! empty( $slug ) && is_string( $slug ) ) {
            $order->update_meta_data( '_smepfowo_slug', $slug );
        }

        $order->save();

        if ( ! empty( $slug ) ) {
            return ['slug' => $slug];
        }

        return ['error' => __( 'Unknown API error.', 'smepay-for-woocommerce' )];
    }


    /**
     * Validate partial order payment on thank you page.
     *
     * @param int $order_id WooCommerce order ID.
     * @return array|null Returns result array on failure or null on success.
     */
    public function send_validate_partial_order_request( $order_id ) {
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
            $error_message = $token_result['error'] ?? __( 'Failed to get access token.', 'smepay-for-woocommerce' );
            wc_add_notice( $error_message, 'error' );
            // translators: %s is the error message returned by SMEPay API.
            $order->add_order_note( sprintf( __( 'SMEPay error: %s', 'smepay-for-woocommerce' ), $error_message ) );
            return [ 'result' => 'failure' ];
        }

        $token = $token_result['token'];

        $slug           = $order->get_meta( '_smepfowo_slug' );
        $partial_amount = $order->get_meta( '_smepfowo_partial_amount' );

        // Calculate partial amount if missing
        if ( ! $partial_amount ) {
            $partial_percent = absint( $this->get_option( 'partial_percentage', 30 ) );
            $partial_amount  = round( $order->get_total() * ( $partial_percent / 100 ), 2 );
        }

        $total_amount = (float) $order->get_total();
        $amount_left  = max( 0, $total_amount - $partial_amount );

        // Prepare payload
        $data = [
            'client_id' => $this->client_id,
            'amount'    => round( (float) $partial_amount, 2 ),
            'slug'      => $slug,
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

            // translators: %s is the error message returned by SMEPay API.
            wc_add_notice( sprintf( __( 'SMEPay validation failed: %s', 'smepay-for-woocommerce' ), $error_message ), 'error' );

            // translators: %s is the error message returned by SMEPay API.
            $order->add_order_note( sprintf( __( 'SMEPay connection error: %s', 'smepay-for-woocommerce' ), $error_message ) );

            return [ 'result' => 'failure' ];
        }


        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        if (
            isset( $decoded['status'], $decoded['payment_status'] ) &&
            $decoded['status'] &&
            in_array( $decoded['payment_status'], [ 'SUCCESS', 'TEST_SUCCESS' ], true )
        ) {
            // Partial payment successful
            if ( $order->get_status() !== 'processing' && $amount_left > 0 ) {

                $order->update_status(
                    'processing',
                    sprintf(
                        // translators: %1$s is the partial amount paid, %2$s is the remaining amount to be collected on delivery.
                        __( 'Partial payment of %1$s received via SMEPay. %2$s remaining to be collected on delivery.', 'smepay-for-woocommerce' ),
                        wc_price( $partial_amount ),
                        wc_price( $amount_left )
                    )
                );


                $order->add_order_note(
                    sprintf(
                        // translators: %1$s is the partial amount paid, %2$s is the remaining amount for COD.
                        __( 'Partial payment validated: %1$s paid via SMEPay. %2$s remaining for COD.', 'smepay-for-woocommerce' ),
                        wc_price( $partial_amount ),
                        wc_price( $amount_left )
                    )
                );
            }


            // If partial amount equals total, mark order complete
            if ( $amount_left <= 0 && $order->get_status() !== 'completed' ) {
                $order->payment_complete();

                // translators: Note added to the order when full payment is received via SMEPay.
                $order->add_order_note( __( 'Full payment received via SMEPay.', 'smepay-for-woocommerce' ) );
            }

        } else {
            // Partial payment failed
            $api_error = $decoded['error'] ?? __( 'Partial payment failed via SMEPay.', 'smepay-for-woocommerce' );

            if ( $order->get_status() !== 'failed' ) {
                $order->update_status( 'failed', $api_error );

                $order->add_order_note( sprintf(
                    // translators: %s is the API error message returned by SMEPay.
                    __( 'SMEPay partial payment error: %s', 'smepay-for-woocommerce' ),
                    $api_error
                ) );
            }

            wc_add_notice( $api_error, 'error' );
            return [ 'result' => 'failure' ];
        }
    }


    public function is_available() {
        // Use parent logic, or add condition here if needed
        return parent::is_available();
    }
}


add_filter( 'woocommerce_thankyou_order_received_text', 'smepfowo_custom_thankyou_text', 10, 2 );

function smepfowo_custom_thankyou_text( $thank_you_text, $order ) {
    if ( ! $order || $order->get_payment_method() !== 'smepfowo_partial_cod' ) {
        return $thank_you_text;
    }

    $partial_paid = (float) $order->get_meta( '_smepfowo_partial_amount' );
    $total        = (float) $order->get_total();
    $remaining    = $total - $partial_paid;

    if ( $partial_paid <= 0 || $remaining <= 0 ) {
        // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
        return __( 'Thank you. Your order has been received.', 'woocommerce' );
    }

    $custom_text = sprintf(
        // translators: 1: paid amount (formatted), 2: remaining amount (formatted)
        __( 'Thank you! You have paid %1$s in advance via SMEPay. The remaining %2$s is payable on delivery. We will notify you when your order is out for delivery.', 'smepay-for-woocommerce' ),
        wc_price( $partial_paid ),
        wc_price( $remaining )
    );

    return $custom_text;
}

/**
 * Add custom instructions for partial COD in customer emails.
 */
add_action( 'woocommerce_email_after_order_table', 'smepfowo_partial_cod_email_instructions', 10, 3 );

function smepfowo_partial_cod_email_instructions( $order, $sent_to_admin, $plain_text ) {
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    // Only for your custom gateway
    if ( $order->get_payment_method() !== 'smepfowo_partial_cod' ) {
        return;
    }

    $partial_paid = (float) $order->get_meta( '_smepfowo_partial_amount' );
    $total_amount = (float) $order->get_total();
    $remaining    = $total_amount - $partial_paid;

    // Skip if values aren't valid
    if ( $partial_paid <= 0 || $remaining <= 0 ) {
        return;
    }

    $message = sprintf(
        // translators: %1$s is the paid amount, %2$s is the remaining amount
        __( 'You have paid %1$s in advance via SMEPay. The remaining %2$s is payable on delivery.', 'smepay-for-woocommerce' ),
        wc_price( $partial_paid ),
        wc_price( $remaining )
    );

    // Output message
    if ( $plain_text ) {
        echo "\n" . esc_html( wp_strip_all_tags( $message ) ) . "\n";
    } else {
        echo '<p>' . esc_html( $message ) . '</p>';
    }
}

add_filter('woocommerce_default_gateway', 'smepfowo_force_partial_cod_as_default');

function smepfowo_force_partial_cod_as_default($default_gateway) {
    return 'smepfowo_partial_cod';
}
