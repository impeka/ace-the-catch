<?php
/**
 * Payment processor contract.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a payment processor implementation.
 */
interface PaymentProcessor {

	/**
	 * Unique provider key (e.g. stripe).
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Human-friendly provider label.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Whether the processor can handle a currency code.
	 *
	 * @param string $currency ISO currency code.
	 * @return bool
	 */
	public function supports_currency( string $currency ): bool;

	/**
	 * Attach provider-specific metadata to a payment payload.
	 *
	 * Payload should include customer details and items where possible so metadata
	 * can be attached consistently across providers.
	 *
	 * Expected payload keys (optional):
	 * - customer: array{name?:string,first_name?:string,last_name?:string,email?:string}
	 * - items: array<int,int>|array<int,array{envelope:int,quantity:int}>
	 * - metadata: array<string,string> Existing provider metadata to merge with.
	 *
	 * @param array $payload Payment data (amount, customer, items, etc).
	 * @return array Updated payment payload including provider metadata.
	 */
	public function attach_metadata( array $payload ): array;

	/**
	 * Ensure any provider-side payment object exists for this order (e.g., a Stripe PaymentIntent).
	 *
	 * This is intended to be called when an order/cart is created or updated so the
	 * payment provider can track the lifecycle of the order.
	 *
	 * Expected payload keys (optional):
	 * - order_id: int
	 * - order_number: int
	 * - amount: float
	 * - currency: string
	 * - description: string
	 * - customer: array{name?:string,first_name?:string,last_name?:string,email?:string}
	 * - items: array<int,int>|array<int,array{envelope:int,quantity:int}>
	 * - reference: string Existing provider reference to update (e.g., PaymentIntent id).
	 *
	 * Return keys (optional):
	 * - reference: string Provider reference id.
	 * - client_secret: string Provider client secret (when applicable).
	 * - status: string Provider status.
	 * - error: string Error message.
	 *
	 * @param array $payload Order/payment context.
	 * @param array $config Provider configuration (API keys, etc).
	 * @return array Result payload.
	 */
	public function sync_order_payment( array $payload, array $config = array() ): array;

	/**
	 * Execute the payment transaction.
	 *
	 * @param array $payload Payment data (amount, metadata, customer details).
	 * @param array $config  Provider configuration (API keys, etc).
	 * @return array Result data (status, reference, error, etc.).
	 */
	public function process_payment( array $payload, array $config = array() ): array;

	/**
	 * Refund a completed payment transaction.
	 *
	 * Expected payload keys (optional):
	 * - order_id: int
	 * - order_number: int
	 * - amount: float
	 * - currency: string
	 * - reference: string Provider reference from the original payment (e.g., Stripe PaymentIntent id).
	 * - reason: string Reason (optional, provider-specific).
	 *
	 * Return keys (optional):
	 * - status: string (succeeded|failed|pending)
	 * - reference: string Refund reference/id when available.
	 * - error: string Error message.
	 *
	 * @param array $payload Refund context (order, amount, reference, etc).
	 * @param array $config  Provider configuration (API keys, etc).
	 * @return array Result data (status, reference, error, etc.).
	 */
	public function refund_payment( array $payload, array $config = array() ): array;

	/**
	 * Enqueue any assets needed for the checkout form.
	 *
	 * @param array $config Provider configuration.
	 * @return void
	 */
	public function enqueue_checkout_assets( array $config = array() ): void;

	/**
	 * Render any provider-specific checkout fields (e.g., card element).
	 *
	 * @param array $config Provider configuration.
	 * @return string HTML markup.
	 */
	public function render_checkout_fields( array $config = array() ): string;
}
