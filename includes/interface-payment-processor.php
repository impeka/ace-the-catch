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
	 * Execute the payment transaction.
	 *
	 * @param array $payload Payment data (amount, metadata, customer details).
	 * @return array Result data (status, reference, error, etc.).
	 */
	public function process_payment( array $payload ): array;
}
