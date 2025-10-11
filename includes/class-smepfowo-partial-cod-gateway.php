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

        // 🧠 Reload settings with new ID to ensure correct values are fetched
        $this->title              = $this->get_option( 'title', __( 'Partial Payment with COD', 'smepay-for-woocommerce' ) );
        $this->description        = $this->get_option( 'description', __( 'Pay part now, rest on delivery.', 'smepay-for-woocommerce' ) );
        $this->method_title       = _x( 'Partial Payment with COD by SMEPay', 'SMEPay payment method', 'smepay-for-woocommerce' );
        $this->method_description = __( 'Collect a small advance payment online via SMEPay, rest paid as Cash on Delivery.', 'smepay-for-woocommerce' );

        // 🔄 Load credentials from settings manually, using the correct ID
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


        add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'send_validate_order_request' ], 10, 1 );
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

        $order->update_meta_data( '_smepfowo_partial_cod', 'yes' );
        $order->update_meta_data( '_smepfowo_partial_amount', $partial_amount );
        $order->save();

        $slug = $this->smepfowo_create_order_partial( $order, $partial_amount );

        if ( ! $slug ) {
            wc_add_notice( __( 'Failed to create partial SMEPay order. Try again.', 'smepay-for-woocommerce' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        // Optional inline QR support
        $qr_code = '';
        $payment_link = '';

        if ( $this->get_option( 'display_mode' ) === 'inline' ) {
            $initiate = $this->smepfowo_initiate_payment( $slug );

            if ( $initiate && ! empty( $initiate['qr_code'] ) ) {
                $qr_code       = $initiate['qr_code'];
                $payment_link  = $initiate['payment_link'] ?? '';
                $intents = $initiate['intents'] ?? [];
                
                // Store for reference
                $order->update_meta_data( '_smepfowo_qr_code', $qr_code );
                $order->update_meta_data( '_smepfowo_payment_link', $payment_link );
                $order->update_meta_data( '_smepfowo_intents', $intents );
                $order->save();
            } else {
                wc_add_notice( __( 'Failed to generate UPI QR code.', 'smepay-for-woocommerce' ), 'error' );
                return [ 'result' => 'failure' ];
            }
        }



        // 🔍 Detect layout (block vs classic)
        $checkout_layout = $this->get_checkout_layout();

        // 🧠 Only set redirect if using block layout
        $redirect_url = ( 'block' === $checkout_layout['layout'] )
            ? add_query_arg(
                [
                    'key'           => $order->get_order_key(),
                    'redirect_url'  => $order->get_checkout_order_received_url(),
                    'smepfowo_partial_cod_slug' => $slug,
                    'order_id'      => $order_id, 
                ],
                $order->get_checkout_payment_url( false )
            )
            : '';

        return [
            'result'        => 'success',
            'smepfowo_partial_cod_slug' => $slug,
            'order_id'      => $order_id,
            'order_key'     => $order->get_order_key(),
            'redirect_url'  => $order->get_checkout_order_received_url(),
            'redirect'      => $redirect_url, // 🧠 Empty in classic layout, which triggers popup
            'qr_code'       => $qr_code,
            'payment_link'  => $payment_link,
            'intents'       => $intents,
        ];
    }


    private function smepfowo_create_order_partial( $order, $amount ) {
        $order_id  = (string) $order->get_id();
        $timestamp = time();
        $random    = wp_rand(1000, 9999);
        $new_order_id = "{$order_id}-{$timestamp}-{$random}";

        $token = $this->get_access_token();
        if ( ! $token ) {
            wc_add_notice( __( 'Unable to retrieve access token.', 'smepay-for-woocommerce' ), 'error' );
            return null;
        }

        $payload = [
            'client_id'        => $this->client_id,
            'amount'           => (string) $amount,
            'order_id'         => $new_order_id,
            'callback_url'     => home_url('/wp-json/smepay/v1/webhook'),
            'customer_details' => [
                'email'  => $order->get_billing_email(),
                'mobile' => $order->get_billing_phone(),
                'name'   => $order->get_formatted_billing_full_name(),
            ],
        ];

        $response = wp_remote_post(
            $this->get_api_base_url() . 'external/order/create',
            [
                'body'    => wp_json_encode( $payload ),
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => self::TIMEOUT,
            ]
        );

        // Check for errors in the response
        if ( is_wp_error( $response ) ) {
            return null;
        }

        // Log the HTTP response code
        $response_code = wp_remote_retrieve_response_code( $response );

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // ✅ Save the SMEPay order ID (required for polling)
        if ( ! empty( $body['order_id'] ) ) {
            $order->update_meta_data( '_smepfowo_order_id', $body['order_id'] );
            $order->save();
        }


        // Check if order_slug exists in the response
        $slug = $body['order_slug'] ?? null;
        if ( is_string( $slug ) && $slug !== '' ) {
            $order->update_meta_data( '_smepfowo_slug', $slug );
            $order->save();
            return $slug;
        } else {
            return null;
        }
    }


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

        $token = $this->get_access_token();
        if ( ! $token ) {
            return;
        }

        $slug = $order->get_meta( '_smepfowo_slug' );
        $partial_amount = $order->get_meta( '_smepfowo_partial_amount' );

        if ( ! $partial_amount ) {
            $partial_amount = round( $order->get_total() * $this->partial_payment_percent / 100, 2 );
        }

        $total_amount  = (float) $order->get_total();
        $amount_left   = $total_amount - $partial_amount;

        $data = [
            'client_id' => $this->client_id,
            'amount'    => (float) $partial_amount,
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
            return;
        }

        $body    = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body, true );

        if (
            isset( $decoded['status'], $decoded['payment_status'] ) &&
            $decoded['status'] &&
            in_array( $decoded['payment_status'], [ 'SUCCESS', 'TEST_SUCCESS' ], true )
        ) {
            if ( $order->get_status() !== 'processing' ) {
                $order->update_status( 'processing', sprintf(
                    __( 'Partial payment of %s received via SMEPay. %s remaining to be collected on delivery.', 'smepay-for-woocommerce' ),
                    wc_price( $partial_amount ),
                    wc_price( $amount_left )
                ) );

                $order->add_order_note( sprintf(
                    __( 'Partial payment validated: %s paid via SMEPay. %s remaining for COD.', 'smepay-for-woocommerce' ),
                    wc_price( $partial_amount ),
                    wc_price( $amount_left )
                ) );
            }
        } else {
            if ( $order->get_status() !== 'failed' ) {
                $order->update_status( 'failed', __( 'Partial payment failed via SMEPay.', 'smepay-for-woocommerce' ) );
                $order->add_order_note( __( 'Partial payment validation failed.', 'smepay-for-woocommerce' ) );
            }
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
        echo "\n" . strip_tags( $message ) . "\n";
    } else {
        echo '<p>' . esc_html( $message ) . '</p>';
    }
}