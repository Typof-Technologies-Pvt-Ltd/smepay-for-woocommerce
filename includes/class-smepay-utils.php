<?php

trait SMEPay_Utils {
    /**
     * Detects the checkout layout and theme type.
     *
     * @return array {
     *     @type string $theme  'block' or 'classic'
     *     @type string $layout 'block', 'classic', 'block+shortcode', or 'unknown'
     * }
     */
    function smepay_detect_checkout_layout_backend() {
	    $is_block_theme = wp_is_block_theme();
	    $theme_type     = $is_block_theme ? 'block' : 'classic';

	    $checkout_page_id = wc_get_page_id('checkout');
	    $layout_type = 'unknown';

	    if ($checkout_page_id && ($page = get_post($checkout_page_id)) instanceof WP_Post) {
	        
	        // Explicitly check for blocks
	        $has_blocks = has_blocks($page->post_content);  // Ensure we're passing post content

	        // Explicitly check for shortcode presence
	        $has_shortcode = has_shortcode($page->post_content, 'woocommerce_checkout');

	        // Determine layout type based on blocks and shortcode
	        if ($has_blocks && $has_shortcode) {
	            $layout_type = 'block+shortcode';
	        } elseif ($has_blocks) {
	            $layout_type = 'block';
	        } elseif ($has_shortcode) {
	            $layout_type = 'classic';
	        }
	    }

	    return [
	        'theme'  => $theme_type,
	        'layout' => $layout_type,
	    ];
	}

}
