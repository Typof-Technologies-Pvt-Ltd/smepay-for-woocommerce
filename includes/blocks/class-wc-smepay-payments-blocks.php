<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Dummy Payments Blocks integration
 *
 * @since 1.0.3
 */
final class WC_Gateway_SMEPay_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_Dummy
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'smepay';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_smepay_settings', [] );
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_path       = '/resources/js/frontend/blocks.js';
		$script_url        = SMEPAY_WC_URL . $script_path;

		wp_register_script(
			'wc-smepay-payments-blocks',
			$script_url,
			[
                'wc-blocks-registry', // Ensure wc-blocks-registry is loaded.
                'wc-settings',
                'wp-element',
                'wp-hooks',
                'wp-html-entities',
                'wp-i18n',
            ],
			SMEPAY_WC_VERSION, // Replace with your desired version
			true
		);

		wp_script_add_data('wc-smepay-payments-blocks', 'type', 'module'); // Set as ES Module.


		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-smepay-payments-blocks', 'smepay-for-woocommerce', SMEPAY_WC_PATH . 'languages/' );
		}

		return [ 'wc-smepay-payments-blocks' ];
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
			'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] )
		];
	}
}
