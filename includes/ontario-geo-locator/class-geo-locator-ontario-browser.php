<?php
/**
 * Ontario boundary geo locator based on browser coordinates.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uses browser-provided lat/long and the bundled Ontario boundary shapefile to
 * determine if a user is within Ontario.
 *
 * This locator relies on client-side geolocation permission and caches the
 * computed result in a signed cookie for 1 hour.
 */
class OntarioBrowserGeoLocator implements GeoLocator {

	public const ID = 'ontario_browser';

	private const COOKIE_NAME    = 'ace_ontario_geo';
	private const COOKIE_VERSION = 2;
	private const COOKIE_TTL     = 3600; // 1 hour.

	/**
	 * Cached geometry.
	 *
	 * @var array{bbox:array{xmin:float,ymin:float,xmax:float,ymax:float}|null,rings:array<int,array<int,array{0:float,1:float}>>,error:string}|null
	 */
	private static ?array $geometry_cache = null;

	public function get_id(): string {
		return self::ID;
	}

	public function get_label(): string {
		return \__( 'Ontario Boundary (Browser Location)', 'ace-the-catch' );
	}

	/**
	 * Locate using either a cached cookie result or passed coordinates.
	 *
	 * @param array $payload Payload (lat/lng or cookie-only).
	 * @return array{country:string,region:string,in_ontario:bool,error:string,needs_location?:bool}
	 */
	public function locate( array $payload ): array {
		$cookie = self::read_cookie_payload();
		if ( $cookie && isset( $cookie['in'] ) ) {
			$in_ontario = (bool) ( (int) $cookie['in'] );
			$lat        = isset( $cookie['lat'] ) ? (float) $cookie['lat'] : null;
			$lng        = isset( $cookie['lng'] ) ? (float) $cookie['lng'] : null;

			return array(
				'source'     => 'cookie',
				'lat'        => $lat,
				'lng'        => $lng,
				'country'    => $in_ontario ? 'CA' : '',
				'region'     => $in_ontario ? 'ON' : '',
				'in_ontario' => $in_ontario,
				'error'      => '',
				'needs_location' => false,
			);
		}

		if ( isset( $payload['lat'], $payload['lng'] ) ) {
			$lat = (float) $payload['lat'];
			$lng = (float) $payload['lng'];
			$in_ontario = $this->is_within_ontario( $lat, $lng );

			return array(
				'source'     => 'browser',
				'lat'        => $lat,
				'lng'        => $lng,
				'country'    => $in_ontario ? 'CA' : '',
				'region'     => $in_ontario ? 'ON' : '',
				'in_ontario' => $in_ontario,
				'error'      => '',
				'needs_location' => false,
			);
		}

		return array(
			'source'         => 'browser',
			'country'        => '',
			'region'         => '',
			'in_ontario'     => false,
			'error'          => '',
			'needs_location' => true,
		);
	}

	/**
	 * Whether a valid cached cookie result exists.
	 *
	 * @return bool
	 */
	public static function has_valid_cookie(): bool {
		return null !== self::read_cookie_payload();
	}

	/**
	 * Persist a signed cookie with the computed result.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @param bool  $in_ontario Whether the point is within Ontario.
	 * @return void
	 */
	public static function persist_cookie( float $lat, float $lng, bool $in_ontario ): void {
		$expires = \time() + self::COOKIE_TTL;

		$payload = array(
			'v'   => self::COOKIE_VERSION,
			'exp' => $expires,
			'in'  => $in_ontario ? 1 : 0,
			'lat' => round( $lat, 6 ),
			'lng' => round( $lng, 6 ),
		);

		$json = \wp_json_encode( $payload );
		if ( ! \is_string( $json ) || '' === $json ) {
			return;
		}

		$b64 = \rtrim( \strtr( \base64_encode( $json ), '+/', '-_' ), '=' );
		$sig = self::sign( $b64 );
		$value = $b64 . '.' . $sig;

		if ( \defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID >= 70300 ) {
			\setcookie(
				self::COOKIE_NAME,
				$value,
				array(
					'expires'  => $expires,
					'path'     => '/',
					'secure'   => \is_ssl(),
					'httponly' => false,
					'samesite' => 'Lax',
				)
			);
		} else {
			\setcookie( self::COOKIE_NAME, $value, $expires, '/', '', \is_ssl(), false );
		}
		$_COOKIE[ self::COOKIE_NAME ] = $value;
	}

	/**
	 * Read and validate the signed cookie payload.
	 *
	 * @return array|null
	 */
	private static function read_cookie_payload(): ?array {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			return null;
		}

		$raw = (string) $_COOKIE[ self::COOKIE_NAME ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$parts = \explode( '.', $raw, 2 );
		if ( 2 !== \count( $parts ) ) {
			return null;
		}

		list( $b64, $sig ) = $parts;
		if ( '' === $b64 || '' === $sig ) {
			return null;
		}

		$expected = self::sign( $b64 );
		if ( ! \hash_equals( $expected, $sig ) ) {
			return null;
		}

		$decoded = \base64_decode( \strtr( $b64, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $b64 ) % 4 ) % 4 ) );
		if ( ! \is_string( $decoded ) || '' === $decoded ) {
			return null;
		}

		$data = \json_decode( $decoded, true );
		if ( ! \is_array( $data ) ) {
			return null;
		}

		$expires = isset( $data['exp'] ) ? (int) $data['exp'] : 0;
		if ( $expires <= 0 || \time() > $expires ) {
			return null;
		}

		if ( isset( $data['v'] ) && (int) $data['v'] !== self::COOKIE_VERSION ) {
			return null;
		}

		return $data;
	}

	/**
	 * Sign a payload string.
	 *
	 * @param string $payload Payload string.
	 * @return string
	 */
	private static function sign( string $payload ): string {
		$key = \function_exists( 'wp_salt' ) ? (string) \wp_salt( 'auth' ) : '';
		if ( '' === $key ) {
			$key = \defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : 'ace-the-catch';
		}

		return \hash_hmac( 'sha256', $payload, $key );
	}

	/**
	 * Determine whether a coordinate pair is inside Ontario.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return bool
	 */
	private function is_within_ontario( float $lat, float $lng ): bool {
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			return false;
		}

		$geometry = self::load_geometry();
		if ( empty( $geometry['rings'] ) || ! empty( $geometry['error'] ) ) {
			return false;
		}

		$bbox = $geometry['bbox'];
		$x = $lng;
		$y = $lat;

		if ( $bbox ) {
			if ( $x < $bbox['xmin'] || $x > $bbox['xmax'] || $y < $bbox['ymin'] || $y > $bbox['ymax'] ) {
				return false;
			}
		}

		$inside = false;
		foreach ( $geometry['rings'] as $ring ) {
			if ( $this->point_in_ring( $x, $y, $ring ) ) {
				$inside = ! $inside;
			}
		}

		return $inside;
	}

	/**
	 * Ray casting point-in-ring test (treats boundary points as inside).
	 *
	 * @param float $x X coordinate (longitude).
	 * @param float $y Y coordinate (latitude).
	 * @param array<int,array{0:float,1:float}> $ring Ring points.
	 * @return bool
	 */
	private function point_in_ring( float $x, float $y, array $ring ): bool {
		$count = \count( $ring );
		if ( $count < 3 ) {
			return false;
		}

		$inside = false;
		$eps = 1e-12;

		for ( $i = 0, $j = $count - 1; $i < $count; $j = $i++ ) {
			$xi = (float) $ring[ $i ][0];
			$yi = (float) $ring[ $i ][1];
			$xj = (float) $ring[ $j ][0];
			$yj = (float) $ring[ $j ][1];

			if ( $this->point_on_segment( $x, $y, $xi, $yi, $xj, $yj, $eps ) ) {
				return true;
			}

			$intersects = ( ( $yi > $y ) !== ( $yj > $y ) )
				&& ( $x < ( ( $xj - $xi ) * ( $y - $yi ) ) / ( ( $yj - $yi ) + 0.0 ) + $xi );

			if ( $intersects ) {
				$inside = ! $inside;
			}
		}

		return $inside;
	}

	/**
	 * Check if a point lies on a line segment.
	 *
	 * @param float $px Point X.
	 * @param float $py Point Y.
	 * @param float $x1 Segment start X.
	 * @param float $y1 Segment start Y.
	 * @param float $x2 Segment end X.
	 * @param float $y2 Segment end Y.
	 * @param float $eps Tolerance.
	 * @return bool
	 */
	private function point_on_segment( float $px, float $py, float $x1, float $y1, float $x2, float $y2, float $eps ): bool {
		$cross = ( $py - $y1 ) * ( $x2 - $x1 ) - ( $px - $x1 ) * ( $y2 - $y1 );
		if ( \abs( $cross ) > $eps ) {
			return false;
		}

		$min_x = \min( $x1, $x2 ) - $eps;
		$max_x = \max( $x1, $x2 ) + $eps;
		$min_y = \min( $y1, $y2 ) - $eps;
		$max_y = \max( $y1, $y2 ) + $eps;

		return ( $px >= $min_x && $px <= $max_x && $py >= $min_y && $py <= $max_y );
	}

	/**
	 * Load Ontario boundary rings from the shapefile.
	 *
	 * @return array{bbox:array{xmin:float,ymin:float,xmax:float,ymax:float}|null,rings:array<int,array<int,array{0:float,1:float}>>,error:string}
	 */
	private static function load_geometry(): array {
		if ( null !== self::$geometry_cache ) {
			return self::$geometry_cache;
		}

		$path = __DIR__ . '/Province.shp';
		if ( ! \file_exists( $path ) ) {
			self::$geometry_cache = array(
				'bbox'  => null,
				'rings' => array(),
				'error' => 'Shapefile not found.',
			);
			return self::$geometry_cache;
		}

		$fh = \fopen( $path, 'rb' );
		if ( ! $fh ) {
			self::$geometry_cache = array(
				'bbox'  => null,
				'rings' => array(),
				'error' => 'Unable to open shapefile.',
			);
			return self::$geometry_cache;
		}

		$header = \fread( $fh, 100 );
		if ( ! \is_string( $header ) || 100 !== \strlen( $header ) ) {
			\fclose( $fh );
			self::$geometry_cache = array(
				'bbox'  => null,
				'rings' => array(),
				'error' => 'Invalid shapefile header.',
			);
			return self::$geometry_cache;
		}

		$bbox = array(
			'xmin' => (float) \unpack( 'e', \substr( $header, 36, 8 ) )[1],
			'ymin' => (float) \unpack( 'e', \substr( $header, 44, 8 ) )[1],
			'xmax' => (float) \unpack( 'e', \substr( $header, 52, 8 ) )[1],
			'ymax' => (float) \unpack( 'e', \substr( $header, 60, 8 ) )[1],
		);

		$rings = array();
		while ( ! \feof( $fh ) ) {
			$rec_header = \fread( $fh, 8 );
			if ( ! \is_string( $rec_header ) || 8 !== \strlen( $rec_header ) ) {
				break;
			}

			$rec = \unpack( 'Nnum/Nlen', $rec_header );
			$content_words = isset( $rec['len'] ) ? (int) $rec['len'] : 0;
			$content_len = $content_words > 0 ? $content_words * 2 : 0;
			if ( $content_len <= 0 ) {
				break;
			}

			$content = \fread( $fh, $content_len );
			if ( ! \is_string( $content ) || \strlen( $content ) !== $content_len ) {
				break;
			}

			$shape_type = (int) ( \unpack( 'V', \substr( $content, 0, 4 ) )[1] ?? 0 );
			if ( 0 === $shape_type ) {
				continue;
			}

			if ( ! \in_array( $shape_type, array( 5, 15, 25 ), true ) ) {
				continue;
			}

			$offset = 4 + 32; // shape type + bbox.
			$num_parts  = (int) ( \unpack( 'V', \substr( $content, $offset, 4 ) )[1] ?? 0 );
			$offset += 4;
			$num_points = (int) ( \unpack( 'V', \substr( $content, $offset, 4 ) )[1] ?? 0 );
			$offset += 4;

			if ( $num_parts <= 0 || $num_points <= 0 ) {
				continue;
			}

			$parts = array();
			for ( $i = 0; $i < $num_parts; $i++ ) {
				$parts[] = (int) ( \unpack( 'V', \substr( $content, $offset, 4 ) )[1] ?? 0 );
				$offset += 4;
			}

			$points_bytes = $num_points * 16;
			$points_bin = \substr( $content, $offset, $points_bytes );
			if ( ! \is_string( $points_bin ) || \strlen( $points_bin ) !== $points_bytes ) {
				continue;
			}

			$doubles = \unpack( 'e*', $points_bin );
			if ( ! \is_array( $doubles ) || \count( $doubles ) < ( $num_points * 2 ) ) {
				continue;
			}

			$points = array();
			for ( $i = 0; $i < $num_points; $i++ ) {
				$points[] = array(
					(float) $doubles[ ( $i * 2 ) + 1 ],
					(float) $doubles[ ( $i * 2 ) + 2 ],
				);
			}

			for ( $part = 0; $part < $num_parts; $part++ ) {
				$start = (int) $parts[ $part ];
				$end = ( $part + 1 < $num_parts ) ? (int) $parts[ $part + 1 ] : $num_points;
				$len = $end - $start;
				if ( $start < 0 || $len < 3 || $end > $num_points ) {
					continue;
				}

				$ring = \array_slice( $points, $start, $len );
				$ring_count = \count( $ring );
				if ( $ring_count < 3 ) {
					continue;
				}

				$first = $ring[0];
				$last  = $ring[ $ring_count - 1 ];
				if ( \abs( $first[0] - $last[0] ) < 1e-12 && \abs( $first[1] - $last[1] ) < 1e-12 ) {
					\array_pop( $ring );
				}

				if ( \count( $ring ) >= 3 ) {
					$rings[] = $ring;
				}
			}
		}

		\fclose( $fh );

		self::$geometry_cache = array(
			'bbox'  => $bbox,
			'rings' => $rings,
			'error' => '',
		);

		return self::$geometry_cache;
	}
}
