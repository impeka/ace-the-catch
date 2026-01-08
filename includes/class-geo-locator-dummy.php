<?php
/**
 * Dummy geo locator implementations.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple geo locator that always returns a fixed Ontario result.
 */
class DummyGeoLocatorOntario implements GeoLocator {

	public function get_id(): string {
		return 'dummy_ontario';
	}

	public function get_label(): string {
		return \__( 'Dummy (Always Ontario)', 'ace-the-catch' );
	}

	public function locate( array $payload ): array {
		return array(
			'country'    => 'CA',
			'region'     => 'ON',
			'in_ontario' => true,
			'error'      => '',
		);
	}
}

/**
 * Simple geo locator that always returns a fixed outside-Ontario result.
 */
class DummyGeoLocatorOutsideOntario implements GeoLocator {

	public function get_id(): string {
		return 'dummy_outside';
	}

	public function get_label(): string {
		return \__( 'Dummy (Always Outside Ontario)', 'ace-the-catch' );
	}

	public function locate( array $payload ): array {
		return array(
			'country'    => 'US',
			'region'     => 'NY',
			'in_ontario' => false,
			'error'      => '',
		);
	}
}
