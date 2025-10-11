<?php

class SMEPFOWO_Webhook_Handler {

	public function __construct() {
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	/**
	 * Register the REST route for the webhook.
	 */
	public function register_routes() {
		register_rest_route('smepay/v1', '/webhook', [
			'methods'  => 'POST',
			'callback' => [$this, 'handle_webhook'],
			'permission_callback' => '__return_true', // Public webhook
		]);
	}

	/**
	 * Handle the SMEPay webhook POST request.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_webhook(WP_REST_Request $request) {
		$body = $request->get_json_params();

		if (empty($body['ref_id']) || empty($body['status'])) {
			return new WP_REST_Response(['error' => 'Missing required fields'], 400);
		}

		$ref_id        = sanitize_text_field($body['ref_id']);
		$transaction_id = sanitize_text_field($body['transaction_id'] ?? '');
		$status        = strtoupper(sanitize_text_field($body['status']));

		$ref_id_parts = explode('-', $ref_id);
		$order_id = $ref_id_parts[0] ?? null;

		if (! $order_id || ! is_numeric($order_id)) {
			return new WP_REST_Response(['error' => 'Invalid order reference'], 400);
		}

		$order = wc_get_order($order_id);
		if (! $order) {
			return new WP_REST_Response(['error' => 'Order not found'], 404);
		}

		// Check if already paid
		if ($order->is_paid()) {
			// Already paid — no further action
			return new WP_REST_Response(['message' => 'Order already marked as paid'], 200);
		}

		if (in_array($status, ['SUCCESS', 'TEST_SUCCESS'], true)) {
			$order->payment_complete($transaction_id);
			$order->add_order_note('SMEPay: Payment confirmed via webhook.');

			$partial_cod = $order->get_meta('_smepfowo_partial_cod');

			if ($partial_cod === 'yes') {
				$partial_amount = floatval($order->get_meta('_smepfowo_partial_amount'));
				$total_amount   = floatval($order->get_total());
				$amount_left    = $total_amount - $partial_amount;

				$order->update_status('processing', sprintf(
					__('Partial payment of %s received via SMEPay. %s remaining to be collected on delivery.', 'smepay-for-woocommerce'),
					wc_price($partial_amount),
					wc_price($amount_left)
				));

				$order->add_order_note(sprintf(
					__('Partial payment validated: %s paid via SMEPay. %s remaining for COD.', 'smepay-for-woocommerce'),
					wc_price($partial_amount),
					wc_price($amount_left)
				));

				return new WP_REST_Response(['message' => 'Order marked as partially paid'], 200);
			} else {
				if ($order->get_status() !== 'completed') {
					$order->payment_complete($transaction_id);
					$order->add_order_note(__('SMEPay: Full payment confirmed.', 'smepay-for-woocommerce'));
				}
				return new WP_REST_Response(['message' => 'Order marked as paid'], 200);
			}
		} elseif ($status === 'FAILED') {
			$order->update_status('failed', 'SMEPay: Payment failed.');
			return new WP_REST_Response(['message' => 'Order marked as failed'], 200);
		}

		return new WP_REST_Response(['message' => 'Unhandled status: no action taken'], 200);
	}
}