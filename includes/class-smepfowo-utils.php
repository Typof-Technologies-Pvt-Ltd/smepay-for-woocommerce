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
}
