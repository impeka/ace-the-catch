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
			'ip'         => '203.0.113.10',
			'city'       => 'Ottawa',
			'postal'     => 'K1P 1J1',
			'latitude'   => 45.4215,
			'longitude'  => -75.6972,
			'time_zone'  => 'America/Toronto',
			'accuracy_radius' => 50,
			'source'     => 'dummy',
			'country'    => 'CA',
			'region'     => 'ON',
			'country_name' => 'Canada',
			'region_name'  => 'Ontario',
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
			'ip'         => '198.51.100.23',
			'city'       => 'New York',
			'postal'     => '10001',
			'latitude'   => 40.7128,
			'longitude'  => -74.0060,
			'time_zone'  => 'America/New_York',
			'accuracy_radius' => 50,
			'source'     => 'dummy',
			'country'    => 'US',
			'region'     => 'NY',
			'country_name' => 'United States',
			'region_name'  => 'New York',
			'in_ontario' => false,
			'error'      => '',
		);
	}
}
