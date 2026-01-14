<?php
/**
 * MaxMind Web Services geo locator.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class MaxMindGeoLocator implements GeoLocator {

	public function get_id(): string {
		return 'maxmind';
	}

	public function get_label(): string {
		return \__( 'MaxMind (Web Services)', 'ace-the-catch' );
	}

	/**
	 * Locate user via MaxMind city web service.
	 *
	 * Expected $payload['config'] with 'account_id' and 'license_key'.
	 *
	 * @param array $payload Input data (ip, config).
	 * @return array
	 */
	public function locate( array $payload ): array {
		$ip      = $payload['ip'] ?? '';
		$config  = $payload['config'] ?? array();
		$account = isset( $config['account_id'] ) ? \trim( (string) $config['account_id'] ) : '';
		$license = isset( $config['license_key'] ) ? \trim( (string) $config['license_key'] ) : '';

		if ( empty( $account ) || empty( $license ) ) {
			return array(
				'country'    => '',
				'region'     => '',
				'in_ontario' => false,
				'error'      => 'MaxMind credentials are missing.',
			);
		}

		// Fall back to visitor IP if not provided.
		if ( empty( $ip ) && isset( $_SERVER['REMOTE_ADDR'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$ip = (string) $_SERVER['REMOTE_ADDR']; // phpcs:ignore
		}

		// Cache by IP to reduce token usage.
		$cache_key = $ip ? 'ace_mm_' . md5( $ip ) : '';
		if ( $cache_key ) {
			$cached = \get_transient( $cache_key );
			if ( \is_array( $cached ) && isset( $cached['in_ontario'] ) ) {
				return $cached;
			}
		}

		$endpoint = 'https://geoip.maxmind.com/geoip/v2.1/city/' . rawurlencode( $ip );
		$response = \wp_remote_get(
			$endpoint,
			array(
				'timeout' => 5,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $account . ':' . $license ),
					'Accept'        => 'application/json',
				),
			)
		);

		if ( \is_wp_error( $response ) ) {
			return array(
				'country'    => '',
				'region'     => '',
				'in_ontario' => false,
				'error'      => $response->get_error_message(),
			);
		}

		$code = \wp_remote_retrieve_response_code( $response );
		$body = \wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			$err = isset( $data['error'] ) ? (string) $data['error'] : 'MaxMind request failed.';
			return array(
				'country'    => '',
				'region'     => '',
				'in_ontario' => false,
				'error'      => $err,
			);
		}

		$country = $data['country']['iso_code'] ?? '';
		$region  = '';
		if ( ! empty( $data['subdivisions'][0]['iso_code'] ) ) {
			$region = $data['subdivisions'][0]['iso_code'];
		}

		$in_ontario = ( 'CA' === $country && 'ON' === $region );

		$result = array(
			'country'    => $country,
			'region'     => $region,
			'in_ontario' => $in_ontario,
			'error'      => '',
		);

		if ( $cache_key ) {
			// Cache for 24 hours.
			\set_transient( $cache_key, $result, DAY_IN_SECONDS );
		}

		return $result;
	}
}
