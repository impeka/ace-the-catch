<?php
/**
 * Dummy payment processors for testing.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class DummyPaymentProcessorSuccess implements PaymentProcessor {

	public function get_id(): string {
		return 'dummy_success';
	}

	public function get_label(): string {
		return \__( 'Dummy (Always Succeeds)', 'ace-the-catch' );
	}

	public function supports_currency( string $currency ): bool {
		return true;
	}

	public function attach_metadata( array $payload ): array {
		return $payload;
	}

	public function sync_order_payment( array $payload, array $config = array() ): array {
		$reference = isset( $payload['reference'] ) ? (string) $payload['reference'] : '';

		return array(
			'reference' => $reference,
			'status'    => 'noop',
			'error'     => '',
		);
	}

	public function process_payment( array $payload, array $config = array() ): array {
		return array(
			'status'    => 'succeeded',
			'reference' => 'dummy-' . wp_generate_uuid4(),
			'error'     => '',
		);
	}

	public function refund_payment( array $payload, array $config = array() ): array {
		return array(
			'status'    => 'succeeded',
			'reference' => 'refund-dummy-' . wp_generate_uuid4(),
			'error'     => '',
		);
	}

	public function enqueue_checkout_assets( array $config = array() ): void {
		// No assets needed.
	}

	public function render_checkout_fields( array $config = array() ): string {
		return '';
	}
}

class DummyPaymentProcessorFailure implements PaymentProcessor {

	public function get_id(): string {
		return 'dummy_failure';
	}

	public function get_label(): string {
		return \__( 'Dummy (Always Fails)', 'ace-the-catch' );
	}

	public function supports_currency( string $currency ): bool {
		return true;
	}

	public function attach_metadata( array $payload ): array {
		return $payload;
	}

	public function sync_order_payment( array $payload, array $config = array() ): array {
		$reference = isset( $payload['reference'] ) ? (string) $payload['reference'] : '';

		return array(
			'reference' => $reference,
			'status'    => 'noop',
			'error'     => '',
		);
	}

	public function process_payment( array $payload, array $config = array() ): array {
		return array(
			'status'    => 'failed',
			'reference' => '',
			'error'     => \__( 'Dummy processor configured to always fail.', 'ace-the-catch' ),
		);
	}

	public function refund_payment( array $payload, array $config = array() ): array {
		return array(
			'status'    => 'failed',
			'reference' => '',
			'error'     => \__( 'Dummy processor configured to always fail refunds.', 'ace-the-catch' ),
		);
	}

	public function enqueue_checkout_assets( array $config = array() ): void {
		// No assets needed.
	}

	public function render_checkout_fields( array $config = array() ): string {
		return '';
	}
}
