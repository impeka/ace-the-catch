<?php
/**
 * Error logs storage for Catch the Ace.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class CatchTheAceErrorLogs {

	private const TABLE_SLUG = 'catch_the_ace_error_logs';

	private const OPTION_DB_VERSION = 'catch_the_ace_error_logs_db_version';

	private const DB_VERSION = 1;

	public const TYPE_MYSQL = 'mysql';
	public const TYPE_EMAIL = 'email';

	public const HEADER_MARKER = 'X-Catch-The-Ace: 1';
	public const HEADER_ORDER  = 'X-Catch-The-Ace-Order:';

	public function __construct() {
		\add_action( 'init', array( $this, 'maybe_create_table' ), 5 );
		\add_action( 'wp_mail_failed', array( $this, 'handle_wp_mail_failed' ), 10, 1 );
	}

	/**
	 * Install DB schema (activation/upgrade).
	 *
	 * @return void
	 */
	public static function install(): void {
		self::create_table();
		if ( self::table_exists() ) {
			\update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
		}
	}

	/**
	 * Create the error logs table if needed.
	 *
	 * @return void
	 */
	public function maybe_create_table(): void {
		$installed = (int) \get_option( self::OPTION_DB_VERSION, 0 );
		if ( $installed >= self::DB_VERSION ) {
			return;
		}

		self::install();
	}

	/**
	 * Get the fully-qualified error logs table name.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		if ( $wpdb instanceof \wpdb ) {
			return $wpdb->prefix . self::TABLE_SLUG;
		}

		return self::TABLE_SLUG;
	}

	/**
	 * Record an error log entry.
	 *
	 * @param string $type Error type (mysql, email).
	 * @param string $message Error message.
	 * @param array  $context Context payload (JSON stored).
	 * @param int    $order_id Related order post ID.
	 * @param string $source Source identifier (tickets, wp_mail, etc).
	 * @return void
	 */
	public function log( string $type, string $message, array $context = array(), int $order_id = 0, string $source = '' ): void {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return;
		}

		$this->maybe_create_table();
		if ( ! self::table_exists() ) {
			return;
		}

		$type = strtolower( trim( $type ) );
		if ( '' === $type ) {
			$type = 'error';
		}

		$source = strtolower( trim( $source ) );
		if ( '' === $source ) {
			$source = 'system';
		}

		$message = trim( (string) $message );
		if ( '' === $message ) {
			$message = 'Unknown error.';
		}

		if ( strlen( $message ) > 2000 ) {
			$message = substr( $message, 0, 2000 ) . '…';
		}

		$context_payload = array_merge(
			array(
				'user_id' => (int) \get_current_user_id(),
				'url'     => isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			),
			$context
		);

		$context_json = '';
		$encoded = \wp_json_encode( $context_payload );
		if ( \is_string( $encoded ) ) {
			$context_json = $encoded;
		}

		$table = self::get_table_name();
		$wpdb->insert(
			$table,
			array(
				'created_at' => \current_time( 'mysql' ),
				'error_type' => $type,
				'source'     => $source,
				'order_id'   => $order_id > 0 ? $order_id : 0,
				'message'    => $message,
				'context'    => $context_json,
			),
			array(
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
			)
		);
	}

	/**
	 * Query recent error logs.
	 *
	 * @param int $limit Number of rows.
	 * @param int $offset Offset.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_logs( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return array();
		}

		if ( ! self::table_exists() ) {
			return array();
		}

		$table = self::get_table_name();
		$limit = $limit > 0 ? $limit : 50;
		$offset = $offset >= 0 ? $offset : 0;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT log_id, created_at, error_type, source, order_id, message, context
				FROM {$table}
				ORDER BY log_id DESC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);

		return \is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count error logs.
	 *
	 * @return int
	 */
	public function count_logs(): int {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return 0;
		}

		if ( ! self::table_exists() ) {
			return 0;
		}

		$table = self::get_table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Hook: capture wp_mail failures for Catch the Ace emails.
	 *
	 * @param \WP_Error $error Error instance.
	 * @return void
	 */
	public function handle_wp_mail_failed( $error ): void {
		if ( ! ( $error instanceof \WP_Error ) ) {
			return;
		}

		$data = $error->get_error_data();
		if ( ! \is_array( $data ) ) {
			return;
		}

		$headers = $data['headers'] ?? array();
		$header_lines = $this->normalize_headers( $headers );
		if ( empty( $header_lines ) ) {
			return;
		}

		if ( ! $this->has_marker_header( $header_lines ) ) {
			return;
		}

		$order_id = $this->extract_order_id( $header_lines );

		$this->log(
			self::TYPE_EMAIL,
			$error->get_error_message(),
			array(
				'to'      => $data['to'] ?? '',
				'subject' => isset( $data['subject'] ) ? (string) $data['subject'] : '',
				'error_code' => $error->get_error_code(),
			),
			$order_id,
			'wp_mail'
		);
	}

	/**
	 * Create the error logs table.
	 *
	 * @return void
	 */
	private static function create_table(): void {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return;
		}

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		if ( ! \function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$sql = "CREATE TABLE {$table_name} (
			log_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			error_type varchar(32) NOT NULL,
			source varchar(64) NOT NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			message text NOT NULL,
			context longtext NULL,
			PRIMARY KEY  (log_id),
			KEY created_at (created_at),
			KEY error_type (error_type),
			KEY order_id (order_id)
		) ENGINE=InnoDB {$charset_collate};";

		\dbDelta( $sql );
	}

	/**
	 * Whether the error logs table exists.
	 *
	 * @return bool
	 */
	private static function table_exists(): bool {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return false;
		}

		$table_name = self::get_table_name();
		$pattern = $wpdb->esc_like( $table_name );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
		return (string) $found === $table_name;
	}

	/**
	 * Normalize headers to an array of header lines.
	 *
	 * @param mixed $headers Headers from wp_mail (array|string).
	 * @return string[]
	 */
	private function normalize_headers( $headers ): array {
		if ( \is_string( $headers ) ) {
			$parts = preg_split( "/\r\n|\r|\n/", $headers );
			return \is_array( $parts ) ? array_values( array_filter( array_map( 'trim', $parts ) ) ) : array();
		}

		if ( \is_array( $headers ) ) {
			return array_values( array_filter( array_map( 'trim', array_map( 'strval', $headers ) ) ) );
		}

		return array();
	}

	/**
	 * Whether the Catch the Ace marker header is present.
	 *
	 * @param string[] $headers Header lines.
	 * @return bool
	 */
	private function has_marker_header( array $headers ): bool {
		foreach ( $headers as $header ) {
			if ( 0 === strcasecmp( trim( $header ), self::HEADER_MARKER ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Extract an order ID from headers, if present.
	 *
	 * @param string[] $headers Header lines.
	 * @return int
	 */
	private function extract_order_id( array $headers ): int {
		foreach ( $headers as $header ) {
			if ( 0 === stripos( $header, self::HEADER_ORDER ) ) {
				$value = trim( substr( $header, strlen( self::HEADER_ORDER ) ) );
				return (int) $value;
			}
		}
		return 0;
	}
}

