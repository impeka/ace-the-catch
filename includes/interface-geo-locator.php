<?php
/**
 * Geo locator contract.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a geo location provider.
 */
interface GeoLocator {

	/**
	 * Unique provider key (e.g. maxmind, ipinfo).
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
	 * Resolve location information.
	 *
	 * @param array $payload Input data (IP address, coordinates, etc).
	 * @return array Location result (country, region, lat/long, error).
	 */
	public function locate( array $payload ): array;
}
