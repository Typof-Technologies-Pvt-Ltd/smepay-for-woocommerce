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


}
