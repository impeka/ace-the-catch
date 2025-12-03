<?php
/**
 * Payment processor factory.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates payment processor instances and exposes a registry hook.
 */
class PaymentProcessorFactory {

	const FILTER_REGISTRY = 'lotto_payment_processors';

	/**
	 * Register a provider resolver at runtime.
	 *
	 * @param string   $provider Unique provider key.
	 * @param callable $resolver Callable that returns a PaymentProcessor.
	 * @return void
	 */
	public function register( string $provider, callable $resolver ): void {
		if ( empty( $provider ) || ! \is_callable( $resolver ) ) {
			return;
		}

		\add_filter(
			self::FILTER_REGISTRY,
			function( $registry ) use ( $provider, $resolver ) {
				if ( ! \is_array( $registry ) ) {
					$registry = array();
				}

				$registry[ $provider ] = $resolver;

				return $registry;
			}
		);
	}

	/**
	 * Create a processor instance for the requested provider.
	 *
	 * @param string $provider Provider key.
	 * @return PaymentProcessor|null
	 */
	public function create( string $provider ): ?PaymentProcessor {
		$registry = $this->get_registry();

		if ( isset( $registry[ $provider ] ) && \is_callable( $registry[ $provider ] ) ) {
			$instance = \call_user_func( $registry[ $provider ] );

			if ( $instance instanceof PaymentProcessor ) {
				return $instance;
			}
		}

		return null;
	}

	/**
	 * Get all provider keys currently registered.
	 *
	 * @return string[]
	 */
	public function available_providers(): array {
		return \array_keys( $this->get_registry() );
	}

	/**
	 * Return registry after applying external filters.
	 *
	 * @return array
	 */
	private function get_registry(): array {
		$registry = \apply_filters( self::FILTER_REGISTRY, array() );

		return \is_array( $registry ) ? $registry : array();
	}
}
