<?php

trait SMEPFOWO_Utils {
    /**
     * Detects the checkout layout and theme type.
     *
     * @return array {
     *     @type string $theme  'block' or 'classic'
     *     @type string $layout 'block', 'classic', 'block+shortcode', or 'unknown'
     * }
     */
    function smepfowo_detect_checkout_layout_backend() {
	    $is_block_theme = wp_is_block_theme();
	    $theme_type     = $is_block_theme ? 'block' : 'classic';

	    $checkout_page_id = wc_get_page_id('checkout');
	    $layout_type = 'unknown';

	    if ($checkout_page_id && ($page = get_post($checkout_page_id)) instanceof WP_Post) {
	        $content = $page->post_content;

	        // Check for Gutenberg blocks
	        $has_blocks = has_blocks($content);

	        // Check for classic shortcode
	        $has_shortcode = has_shortcode($content, 'woocommerce_checkout');

	        // Check for the classic-shortcode block wrapper
	        $has_shortcode_block = strpos($content, '<!-- wp:woocommerce/classic-shortcode') !== false
	            && strpos($content, '"shortcode":"checkout"') !== false;

	        if (($has_blocks && $has_shortcode) || ($has_blocks && $has_shortcode_block)) {
	            $layout_type = 'block+shortcode';
	        } elseif ($has_blocks) {
	            $layout_type = 'block';
	        } elseif ($has_shortcode || $has_shortcode_block) {
	            $layout_type = 'classic';
	        }
	    }

	    return [
	        'theme'  => $theme_type,
	        'layout' => $layout_type,
	    ];
	}

	/**
     * Format partial payment email message.
     *
     * @param float $paid_amount Amount already paid.
     * @param float $total_amount Total order amount.
     * @param string $currency_symbol Currency symbol, e.g. '$' or '₹'.
     *
     * @return string
     */
    public function smepfowo_format_partial_payment_message( $paid_amount, $total_amount, $currency_symbol = '₹' ) {
        $remaining = $total_amount - $paid_amount;
        $message = sprintf(
            /* translators: 1: paid amount with currency, 2: total amount with currency, 3: remaining amount with currency */
            __( 'You have paid %1$s out of %2$s. Remaining %3$s is to be paid at COD.', 'smepay-for-woocommerce' ),
            $currency_symbol . number_format_i18n( $paid_amount, 2 ),
            $currency_symbol . number_format_i18n( $total_amount, 2 ),
            $currency_symbol . number_format_i18n( $remaining, 2 )
        );

        // Log the message for debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $logger = wc_get_logger();
            $logger->debug( 'Partial payment message: ' . $message, [ 'source' => 'smepfowo_email' ] );
        }

        return $message;
    }

    /**
	 * AJAX handler to check the SME Pay for WooCommerce order payment status.
	 *
	 * This function responds to AJAX requests from the frontend or backend
	 * to check the current payment status of an order (e.g., for partial or COD payments).
	 *
	 * It validates the nonce, retrieves the order ID from the POST request,
	 * and calls the internal method to get the payment status.
	 *
	 * Sends a JSON response:
	 * - success: with payment status info
	 * - error: with appropriate error message
	 *
	 * @return void Outputs JSON and terminates execution via wp_send_json_*()
	 */
    public function ajax_check_smepfowo_order_status() {

	    check_ajax_referer( 'smepfowo_nonce_action', 'nonce' );

	    $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

	    if ( ! $order_id ) {
	        wp_send_json_error( [ 'message' => 'Invalid order ID.' ] );
	    }

	    $order = wc_get_order( $order_id );
	    if ( ! $order ) {
	        wp_send_json_error( [ 'message' => 'Order not found.' ] );
	    }

	    $result = $this->smepfowo_check_order_status( $order_id );

	    if ( $result['status'] ?? false ) {
	        $status = $result['payment_status'] ?? '';

	        // Determine if payment was successful
	        $is_paid = in_array( $status, [ 'SUCCESS', 'TEST_SUCCESS' ], true );

	        // Load stored meta (if available)
	        $slug         = $order->get_meta( '_smepfowo_slug' );
	        $payment_link = $order->get_meta( '_smepfowo_payment_link' );

	        // Generate base thank you URL
	        $redirect_url = $is_paid ? $order->get_checkout_order_received_url() : '';

	        // Append custom query params
	        $thank_you_url = $is_paid ? add_query_arg(
	            [
	                'smepfowo_slug' => $slug,
	                'payment_link'  => urlencode( $payment_link ),
	            ],
	            $redirect_url
	        ) : '';

	        wp_send_json_success( [
	            'status'       => $status,
	            'is_paid'      => $is_paid,
	            'redirect_url' => $thank_you_url,
	        ] );
	    }

	    // Send API error message if available
	    $error_message = $result['error'] ?? 'Unable to retrieve payment status.';
	    wp_send_json_error( [ 'message' => $error_message ] );
	}
}
