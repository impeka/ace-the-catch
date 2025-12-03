<?php
/**
 * Geo locator factory.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates geo locator instances and exposes a registry hook.
 */
class GeoLocatorFactory {

	const FILTER_REGISTRY = 'lotto_geo_locators';

	/**
	 * Register a locator resolver at runtime.
	 *
	 * @param string   $provider Unique provider key.
	 * @param callable $resolver Callable that returns a GeoLocator.
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
	 * Create a locator instance for the requested provider.
	 *
	 * @param string $provider Provider key.
	 * @return GeoLocator|null
	 */
	public function create( string $provider ): ?GeoLocator {
		$registry = $this->get_registry();

		if ( isset( $registry[ $provider ] ) && \is_callable( $registry[ $provider ] ) ) {
			$instance = \call_user_func( $registry[ $provider ] );

			if ( $instance instanceof GeoLocator ) {
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
