<?php
/**
 * Tickets storage and generation worker.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class CatchTheAceTickets {

	private const TABLE_SLUG = 'catch_the_ace_tickets';

	private const OPTION_DB_VERSION = 'catch_the_ace_tickets_db_version';

	private const DB_VERSION = 1;

	public const CRON_HOOK_GENERATE_TICKETS = 'catch_the_ace_generate_tickets';

	private const CRON_SCHEDULE = 'catch_the_ace_every_five_minutes';

	public const OPTION_LAST_RUN = 'catch_the_ace_generate_tickets_last_run';

	public const STATUS_NOT_GENERATED = 'not_generated';
	public const STATUS_GENERATE      = 'generate';
	public const STATUS_IN_PROCESS    = 'in_process';
	public const STATUS_GENERATED     = 'generated';

	/**
	 * Last worker error message (best-effort, per-request).
	 *
	 * @var string
	 */
	private string $last_error = '';

	public function __construct() {
		\add_action( 'init', array( $this, 'maybe_create_table' ), 5 );
		\add_filter( 'cron_schedules', array( $this, 'register_cron_schedule' ) );
		\add_action( 'init', array( $this, 'ensure_cron_scheduled' ), 30 );
		\add_action( self::CRON_HOOK_GENERATE_TICKETS, array( $this, 'process_pending_ticket_generation' ) );
		\add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		\add_action( 'wp_ajax_ace_the_catch_export_tickets', array( $this, 'ajax_export_tickets' ) );
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
	 * Register a 5-minute cron schedule.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function register_cron_schedule( array $schedules ): array {
		if ( ! isset( $schedules[ self::CRON_SCHEDULE ] ) ) {
			$schedules[ self::CRON_SCHEDULE ] = array(
				'interval' => 300,
				'display'  => \__( 'Every 5 minutes', 'ace-the-catch' ),
			);
		}
		return $schedules;
	}

	/**
	 * Ensure the ticket generation worker is scheduled.
	 *
	 * @return void
	 */
	public function ensure_cron_scheduled(): void {
		if ( ! \wp_next_scheduled( self::CRON_HOOK_GENERATE_TICKETS ) ) {
			\wp_schedule_event( time() + 300, self::CRON_SCHEDULE, self::CRON_HOOK_GENERATE_TICKETS );
		}
	}

	/**
	 * Create the tickets table if needed.
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
	 * Create the tickets table.
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
			ticket_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			envelope_number int(11) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (ticket_id),
			KEY order_id (order_id),
			KEY order_envelope (order_id, envelope_number)
		) ENGINE=InnoDB {$charset_collate};";

		\dbDelta( $sql );

		// Best-effort: add a foreign key constraint if supported (dbDelta doesn't manage constraints).
		$posts_table = $wpdb->posts;
		$constraint  = 'cta_tickets_order_fk';
		$wpdb->query(
			"ALTER TABLE {$table_name}
			ADD CONSTRAINT {$constraint}
			FOREIGN KEY (order_id) REFERENCES {$posts_table}(ID)
			ON DELETE CASCADE"
		);
	}

	/**
	 * Whether the tickets table exists.
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
	 * Get the fully-qualified tickets table name.
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
	 * Ticket status labels.
	 *
	 * @return array<string,string>
	 */
	public function get_status_labels(): array {
		return array(
			self::STATUS_NOT_GENERATED => \__( 'Not generated', 'ace-the-catch' ),
			self::STATUS_GENERATE      => \__( 'Generate', 'ace-the-catch' ),
			self::STATUS_IN_PROCESS    => \__( 'In process', 'ace-the-catch' ),
			self::STATUS_GENERATED     => \__( 'Generated', 'ace-the-catch' ),
		);
	}

	/**
	 * Process completed orders with tickets pending generation.
	 *
	 * @return void
	 */
	public function process_pending_ticket_generation(): void {
		\update_option( self::OPTION_LAST_RUN, \current_time( 'mysql' ), false );

		$query = new \WP_Query(
			array(
				'post_type'      => CatchTheAceOrders::POST_TYPE,
				'post_status'    => array( CatchTheAceOrders::STATUS_COMPLETED ),
				'fields'         => 'ids',
				'posts_per_page' => 25,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => CatchTheAceOrders::META_TICKET_STATUS,
						'value'   => self::STATUS_NOT_GENERATED,
						'compare' => '=',
					),
					array(
						'key'     => CatchTheAceOrders::META_TICKET_STATUS,
						'value'   => self::STATUS_GENERATE,
						'compare' => '=',
					),
					array(
						'key'     => CatchTheAceOrders::META_TICKET_STATUS,
						'value'   => 'not generated',
						'compare' => '=',
					),
					array(
						'key'     => CatchTheAceOrders::META_TICKET_STATUS,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return;
		}

		$orders = Plugin::instance()->get_orders();

		foreach ( $query->posts as $order_id ) {
			$order_id = (int) $order_id;
			if ( $order_id <= 0 ) {
				continue;
			}

			if ( ! $this->claim_order_for_generation( $order_id ) ) {
				continue;
			}

			$orders->append_log( $order_id, \__( 'Ticket generation started.', 'ace-the-catch' ) );

			$cart = $orders->get_order_cart( $order_id );
			if ( empty( $cart ) ) {
				\update_post_meta( $order_id, CatchTheAceOrders::META_TICKET_STATUS, self::STATUS_GENERATED );
				$orders->append_log( $order_id, \__( 'No tickets were generated because the order cart is empty.', 'ace-the-catch' ) );
				continue;
			}

			$result = $this->generate_tickets_for_order( $order_id, $cart );
			if ( false === $result ) {
				\update_post_meta( $order_id, CatchTheAceOrders::META_TICKET_STATUS, self::STATUS_NOT_GENERATED );
				$message = \__( 'Ticket generation failed. The order was reset for retry.', 'ace-the-catch' );
				if ( $this->last_error ) {
					$message .= ' ' . $this->last_error;
				}
				$orders->append_log( $order_id, $message );
				continue;
			}

			\update_post_meta( $order_id, CatchTheAceOrders::META_TICKET_STATUS, self::STATUS_GENERATED );
			$orders->append_log( $order_id, \sprintf( \__( 'Tickets generated: %d.', 'ace-the-catch' ), $result ) );
			Plugin::instance()->get_emails()->send_ticket_delivery_email( $order_id );
		}
	}

	/**
	 * Atomically claim an order for ticket generation.
	 *
	 * @param int $order_id Order post ID.
	 * @return bool
	 */
	private function claim_order_for_generation( int $order_id ): bool {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return false;
		}

		$current = (string) \get_post_meta( $order_id, CatchTheAceOrders::META_TICKET_STATUS, true );
		if ( '' === $current ) {
			\add_post_meta( $order_id, CatchTheAceOrders::META_TICKET_STATUS, self::STATUS_NOT_GENERATED, true );
		}

		$meta_table = $wpdb->postmeta;
		$sql = $wpdb->prepare(
			"UPDATE {$meta_table}
			SET meta_value = %s
			WHERE post_id = %d
				AND meta_key = %s
				AND meta_value IN (%s, %s, %s)",
			self::STATUS_IN_PROCESS,
			$order_id,
			CatchTheAceOrders::META_TICKET_STATUS,
			self::STATUS_NOT_GENERATED,
			self::STATUS_GENERATE,
			'not generated'
		);

		$affected = $wpdb->query( $sql );
		return \is_int( $affected ) && $affected > 0;
	}

	/**
	 * Generate tickets for the given order cart, idempotently.
	 *
	 * @param int          $order_id Order post ID.
	 * @param array<int,int> $cart Envelope => qty.
	 * @return int|false Number of tickets inserted, or false on failure.
	 */
	private function generate_tickets_for_order( int $order_id, array $cart ) {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return false;
		}

		$this->last_error = '';

		$table = self::get_table_name();
		$now   = \current_time( 'mysql' );

		$existing = $this->get_ticket_counts_by_envelope( $order_id );

		$inserted = 0;
		foreach ( $cart as $env => $qty ) {
			$env = (int) $env;
			$qty = (int) $qty;
			if ( $env <= 0 || $qty <= 0 ) {
				continue;
			}

			$have   = isset( $existing[ $env ] ) ? (int) $existing[ $env ] : 0;
			$needed = $qty - $have;
			if ( $needed <= 0 ) {
				continue;
			}

			$chunk = 250;
			$remaining = $needed;
			while ( $remaining > 0 ) {
				$count = ( $remaining > $chunk ) ? $chunk : $remaining;
				$remaining -= $count;

				$placeholders = array();
				$values       = array();

				for ( $i = 0; $i < $count; $i++ ) {
					$placeholders[] = '(%d,%d,%s)';
					$values[] = $order_id;
					$values[] = $env;
					$values[] = $now;
				}

				$sql = "INSERT INTO {$table} (order_id, envelope_number, created_at) VALUES " . \implode( ',', $placeholders );
				$prepared = $wpdb->prepare( $sql, ...$values );
				$result = $wpdb->query( $prepared );

				if ( false === $result ) {
					$this->last_error = $wpdb->last_error ? (string) $wpdb->last_error : '';

					$last_query = $wpdb->last_query ? (string) $wpdb->last_query : '';
					if ( $last_query && strlen( $last_query ) > 1000 ) {
						$last_query = substr( $last_query, 0, 1000 ) . '…';
					}

					Plugin::instance()->get_error_logs()->log(
						CatchTheAceErrorLogs::TYPE_MYSQL,
						$this->last_error ? 'Ticket generation MySQL error: ' . $this->last_error : 'Ticket generation MySQL error.',
						array(
							'order_id' => $order_id,
							'envelope' => $env,
							'needed'   => $needed,
							'chunk'    => $count,
							'last_query' => $last_query,
						),
						$order_id,
						'tickets'
					);
					return false;
				}

				$inserted += $count;
			}
		}

		return $inserted;
	}

	/**
	 * Get ticket counts per envelope for an order.
	 *
	 * @param int $order_id Order post ID.
	 * @return array<int,int>
	 */
	private function get_ticket_counts_by_envelope( int $order_id ): array {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return array();
		}

		$table = self::get_table_name();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT envelope_number, COUNT(*) AS qty
				FROM {$table}
				WHERE order_id = %d
				GROUP BY envelope_number",
				$order_id
			),
			ARRAY_A
		);

		if ( empty( $rows ) || ! \is_array( $rows ) ) {
			return array();
		}

		$counts = array();
		foreach ( $rows as $row ) {
			if ( ! \is_array( $row ) ) {
				continue;
			}
			$env = isset( $row['envelope_number'] ) ? (int) $row['envelope_number'] : 0;
			$qty = isset( $row['qty'] ) ? (int) $row['qty'] : 0;
			if ( $env > 0 && $qty > 0 ) {
				$counts[ $env ] = $qty;
			}
		}

		return $counts;
	}

	/**
	 * Register the Tickets meta box on the Order edit screen.
	 *
	 * @return void
	 */
	public function register_meta_boxes(): void {
		\add_meta_box(
			'cta-order-tickets',
			\__( 'Tickets', 'ace-the-catch' ),
			array( $this, 'render_tickets_meta_box' ),
			CatchTheAceOrders::POST_TYPE,
			'normal',
			'default'
		);

		\add_meta_box(
			'cta-session-export-tickets',
			\__( 'Export Tickets', 'ace-the-catch' ),
			array( $this, 'render_export_tickets_meta_box' ),
			'catch-the-ace',
			'side',
			'default'
		);
	}

	/**
	 * Render the Tickets meta box.
	 *
	 * @param \WP_Post $post Order post.
	 * @return void
	 */
	public function render_tickets_meta_box( \WP_Post $post ): void {
		$status = (string) \get_post_meta( $post->ID, CatchTheAceOrders::META_TICKET_STATUS, true );
		$labels = $this->get_status_labels();
		$label  = $labels[ $status ] ?? $status;

		echo '<p><strong>' . \esc_html__( 'Ticket Status:', 'ace-the-catch' ) . '</strong> ' . \esc_html( $label ?: \__( 'Not generated', 'ace-the-catch' ) ) . '</p>';

		// Only show tickets when at least one exists.
		$tickets = $this->get_tickets_for_order( (int) $post->ID );
		if ( empty( $tickets ) ) {
			echo '<p class="description">' . \esc_html__( 'No tickets generated yet.', 'ace-the-catch' ) . '</p>';
			return;
		}

		echo '<p><strong>' . \esc_html__( 'Tickets:', 'ace-the-catch' ) . '</strong> ' . \esc_html( (string) \count( $tickets ) ) . '</p>';
		echo '<table class="widefat striped" style="max-width: 900px">';
		echo '<thead><tr><th style="width:140px">' . \esc_html__( 'Ticket #', 'ace-the-catch' ) . '</th><th>' . \esc_html__( 'Envelope', 'ace-the-catch' ) . '</th><th>' . \esc_html__( 'Created', 'ace-the-catch' ) . '</th></tr></thead>';
		echo '<tbody>';
		foreach ( $tickets as $ticket ) {
			$ticket_id = isset( $ticket['ticket_id'] ) ? (int) $ticket['ticket_id'] : 0;
			$envelope  = isset( $ticket['envelope_number'] ) ? (int) $ticket['envelope_number'] : 0;
			$created   = isset( $ticket['created_at'] ) ? (string) $ticket['created_at'] : '';
			echo '<tr>';
			echo '<td>' . \esc_html( (string) $ticket_id ) . '</td>';
			echo '<td>' . \esc_html( '#' . (string) $envelope ) . '</td>';
			echo '<td>' . \esc_html( $created ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Render the Export Tickets meta box on the session edit screen.
	 *
	 * @param \WP_Post $post Session post.
	 * @return void
	 */
	public function render_export_tickets_meta_box( \WP_Post $post ): void {
		if ( 'catch-the-ace' !== $post->post_type ) {
			return;
		}

		$nonce = \wp_create_nonce( 'ace_the_catch_ticket_export' );
		$tz    = \wp_timezone_string();
		if ( '' === $tz ) {
			$tz = 'UTC';
		}

		echo '<div class="cta-ticket-export" data-session-id="' . \esc_attr( (string) $post->ID ) . '" data-nonce="' . \esc_attr( $nonce ) . '">';
		echo '<p class="description">' . \esc_html( \sprintf( __( 'Exports tickets generated for this session between two dates (timezone: %s).', 'ace-the-catch' ), $tz ) ) . '</p>';

		echo '<p style="margin: 10px 0 6px;">';
		echo '<label for="cta_ticket_export_from"><strong>' . \esc_html__( 'From', 'ace-the-catch' ) . '</strong></label><br />';
		echo '<input type="date" id="cta_ticket_export_from" class="widefat" />';
		echo '</p>';

		echo '<p style="margin: 10px 0 6px;">';
		echo '<label for="cta_ticket_export_to"><strong>' . \esc_html__( 'To', 'ace-the-catch' ) . '</strong></label><br />';
		echo '<input type="date" id="cta_ticket_export_to" class="widefat" />';
		echo '</p>';

		echo '<p style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;">';
		echo '<button type="button" class="button button-secondary" data-cta-ticket-export="csv">' . \esc_html__( 'Export to CSV', 'ace-the-catch' ) . '</button>';
		echo '<button type="button" class="button button-secondary" data-cta-ticket-export="pdf">' . \esc_html__( 'Print Tickets', 'ace-the-catch' ) . '</button>';
		echo '</p>';

		echo '<div class="cta-ticket-export__status" style="display:none;margin-top:8px;"></div>';
		echo '</div>';
	}

	/**
	 * Fetch tickets for an order (limited for admin rendering).
	 *
	 * @param int $order_id Order post ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_tickets_for_order( int $order_id ): array {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return array();
		}

		$table = self::get_table_name();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ticket_id, envelope_number, created_at
				FROM {$table}
				WHERE order_id = %d
				ORDER BY ticket_id ASC
				LIMIT 500",
				$order_id
			),
			ARRAY_A
		);

		return \is_array( $rows ) ? $rows : array();
	}

	/**
	 * AJAX: export tickets for a session between two datetimes.
	 *
	 * @return void
	 */
	public function ajax_export_tickets(): void {
		if ( ! \current_user_can( 'edit_posts' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Unauthorized.', 'ace-the-catch' ) ), 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( (string) \wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! \wp_verify_nonce( $nonce, 'ace_the_catch_ticket_export' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid request.', 'ace-the-catch' ) ), 403 );
		}

		$session_id = isset( $_POST['sessionId'] ) ? (int) \wp_unslash( $_POST['sessionId'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $session_id <= 0 || 'catch-the-ace' !== \get_post_type( $session_id ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid session.', 'ace-the-catch' ) ), 400 );
		}

		if ( ! \current_user_can( 'edit_post', $session_id ) ) {
			\wp_send_json_error( array( 'message' => \__( 'You do not have permission to export tickets for this session.', 'ace-the-catch' ) ), 403 );
		}

		$from_raw = isset( $_POST['from'] ) ? \sanitize_text_field( (string) \wp_unslash( $_POST['from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$to_raw   = isset( $_POST['to'] ) ? \sanitize_text_field( (string) \wp_unslash( $_POST['to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$from_dt = $this->parse_datetime_local( $from_raw, false );
		$to_dt   = $this->parse_datetime_local( $to_raw, true );

		if ( ! $from_dt || ! $to_dt ) {
			\wp_send_json_error( array( 'message' => \__( 'Please provide both "from" and "to" dates.', 'ace-the-catch' ) ), 400 );
		}

		if ( $from_dt > $to_dt ) {
			\wp_send_json_error( array( 'message' => \__( '"From" must be earlier than "to".', 'ace-the-catch' ) ), 400 );
		}

		$tickets = $this->get_tickets_for_session_between(
			$session_id,
			$from_dt->format( 'Y-m-d H:i:s' ),
			$to_dt->format( 'Y-m-d H:i:s' )
		);

		\wp_send_json_success(
			array(
				'tickets' => $tickets,
			)
		);
	}

	/**
	 * Parse an input[type=date] value into a DateTimeImmutable in site timezone.
	 *
	 * @param string $value Raw datetime-local string.
	 * @param bool   $end_of_day Whether to normalize date-only values to 23:59:59.
	 * @return \DateTimeImmutable|null
	 */
	private function parse_datetime_local( string $value, bool $end_of_day ): ?\DateTimeImmutable {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}

		$tz = \wp_timezone();

		// Date only (ex: 2026-01-14).
		$date_only = ( 1 === \preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $value ) );

		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d\\TH:i', $value, $tz );
		if ( $dt instanceof \DateTimeImmutable ) {
			return $dt;
		}

		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d\\TH:i:s', $value, $tz );
		if ( $dt instanceof \DateTimeImmutable ) {
			return $dt;
		}

		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $value, $tz );
		if ( $dt instanceof \DateTimeImmutable ) {
			return $dt;
		}

		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value, $tz );
		if ( $dt instanceof \DateTimeImmutable ) {
			return ( $end_of_day && $date_only ) ? $dt->setTime( 23, 59, 59 ) : $dt;
		}

		return null;
	}

	/**
	 * Fetch tickets for a session between two MySQL datetime strings (ticket created_at).
	 *
	 * @param int    $session_post_id Session post ID.
	 * @param string $from_mysql Inclusive range start (Y-m-d H:i:s).
	 * @param string $to_mysql Inclusive range end (Y-m-d H:i:s).
	 * @return array<int,array<string,mixed>>
	 */
	private function get_tickets_for_session_between( int $session_post_id, string $from_mysql, string $to_mysql ): array {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return array();
		}

		$table    = self::get_table_name();
		$posts    = $wpdb->posts;
		$postmeta = $wpdb->postmeta;

		$meta_keys = array(
			CatchTheAceOrders::META_ORDER_NUMBER,
			CatchTheAceOrders::META_ORDER_CUSTOMER_FIRST_NAME,
			CatchTheAceOrders::META_ORDER_CUSTOMER_LAST_NAME,
			CatchTheAceOrders::META_ORDER_CUSTOMER_EMAIL,
			CatchTheAceOrders::META_ORDER_CUSTOMER_PHONE,
			CatchTheAceOrders::META_ORDER_CUSTOMER_LOCATION,
			CatchTheAceOrders::META_ORDER_BENEFACTOR_LABEL,
			CatchTheAceOrders::META_ORDER_TERMS_ACCEPTED_AT,
			CatchTheAceOrders::META_ORDER_TOTAL,
			CatchTheAceOrders::META_ORDER_CURRENCY,
			CatchTheAceOrders::META_ORDER_PAYMENT_REFERENCE,
		);

		$meta_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		$k_order_number    = \esc_sql( CatchTheAceOrders::META_ORDER_NUMBER );
		$k_first_name      = \esc_sql( CatchTheAceOrders::META_ORDER_CUSTOMER_FIRST_NAME );
		$k_last_name       = \esc_sql( CatchTheAceOrders::META_ORDER_CUSTOMER_LAST_NAME );
		$k_email           = \esc_sql( CatchTheAceOrders::META_ORDER_CUSTOMER_EMAIL );
		$k_phone           = \esc_sql( CatchTheAceOrders::META_ORDER_CUSTOMER_PHONE );
		$k_location        = \esc_sql( CatchTheAceOrders::META_ORDER_CUSTOMER_LOCATION );
		$k_benefactor      = \esc_sql( CatchTheAceOrders::META_ORDER_BENEFACTOR_LABEL );
		$k_terms_accepted  = \esc_sql( CatchTheAceOrders::META_ORDER_TERMS_ACCEPTED_AT );
		$k_total           = \esc_sql( CatchTheAceOrders::META_ORDER_TOTAL );
		$k_currency        = \esc_sql( CatchTheAceOrders::META_ORDER_CURRENCY );
		$k_payment_ref     = \esc_sql( CatchTheAceOrders::META_ORDER_PAYMENT_REFERENCE );

		$sql = $wpdb->prepare(
			"SELECT
				t.ticket_id AS ticket_number,
				t.envelope_number AS envelope_number,
				t.created_at AS ticket_created_at,
				p.ID AS order_id,
				p.post_status AS order_status,
				p.post_date AS order_created_at,
				MAX(CASE WHEN pm.meta_key = '{$k_order_number}' THEN pm.meta_value END) AS order_number,
				MAX(CASE WHEN pm.meta_key = '{$k_first_name}' THEN pm.meta_value END) AS first_name,
				MAX(CASE WHEN pm.meta_key = '{$k_last_name}' THEN pm.meta_value END) AS last_name,
				MAX(CASE WHEN pm.meta_key = '{$k_email}' THEN pm.meta_value END) AS email,
				MAX(CASE WHEN pm.meta_key = '{$k_phone}' THEN pm.meta_value END) AS telephone,
				MAX(CASE WHEN pm.meta_key = '{$k_location}' THEN pm.meta_value END) AS location,
				MAX(CASE WHEN pm.meta_key = '{$k_benefactor}' THEN pm.meta_value END) AS benefactor,
				MAX(CASE WHEN pm.meta_key = '{$k_terms_accepted}' THEN pm.meta_value END) AS terms_accepted_at,
				MAX(CASE WHEN pm.meta_key = '{$k_total}' THEN pm.meta_value END) AS total,
				MAX(CASE WHEN pm.meta_key = '{$k_currency}' THEN pm.meta_value END) AS currency,
				MAX(CASE WHEN pm.meta_key = '{$k_payment_ref}' THEN pm.meta_value END) AS payment_reference
			FROM {$table} t
			INNER JOIN {$posts} p
				ON p.ID = t.order_id
			INNER JOIN {$postmeta} pm_session
				ON pm_session.post_id = p.ID
				AND pm_session.meta_key = %s
				AND pm_session.meta_value = %d
			LEFT JOIN {$postmeta} pm
				ON pm.post_id = p.ID
				AND pm.meta_key IN ({$meta_placeholders})
			WHERE p.post_type = %s
				AND t.created_at >= %s
				AND t.created_at <= %s
			GROUP BY t.ticket_id, t.envelope_number, t.created_at, p.ID, p.post_status, p.post_date
			ORDER BY t.ticket_id ASC",
			array_merge(
				array(
					CatchTheAceOrders::META_ORDER_SESSION,
					$session_post_id,
				),
				$meta_keys,
				array(
					CatchTheAceOrders::POST_TYPE,
					$from_mysql,
					$to_mysql,
				)
			)
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $rows ) || ! \is_array( $rows ) ) {
			return array();
		}

		$tickets = array();
		foreach ( $rows as $row ) {
			if ( ! \is_array( $row ) ) {
				continue;
			}

			$tickets[] = array(
				'ticket_number'     => isset( $row['ticket_number'] ) ? (int) $row['ticket_number'] : 0,
				'envelope_number'   => isset( $row['envelope_number'] ) ? (int) $row['envelope_number'] : 0,
				'ticket_created_at' => isset( $row['ticket_created_at'] ) ? (string) $row['ticket_created_at'] : '',
				'order_id'          => isset( $row['order_id'] ) ? (int) $row['order_id'] : 0,
				'order_number'      => isset( $row['order_number'] ) ? (int) $row['order_number'] : 0,
				'order_status'      => isset( $row['order_status'] ) ? (string) $row['order_status'] : '',
				'order_created_at'  => isset( $row['order_created_at'] ) ? (string) $row['order_created_at'] : '',
				'first_name'        => isset( $row['first_name'] ) ? \sanitize_text_field( (string) $row['first_name'] ) : '',
				'last_name'         => isset( $row['last_name'] ) ? \sanitize_text_field( (string) $row['last_name'] ) : '',
				'email'             => isset( $row['email'] ) ? \sanitize_email( (string) $row['email'] ) : '',
				'telephone'         => isset( $row['telephone'] ) ? \sanitize_text_field( (string) $row['telephone'] ) : '',
				'location'          => isset( $row['location'] ) ? \sanitize_text_field( (string) $row['location'] ) : '',
				'benefactor'        => isset( $row['benefactor'] ) ? \sanitize_text_field( (string) $row['benefactor'] ) : '',
				'terms_accepted_at' => isset( $row['terms_accepted_at'] ) ? \sanitize_text_field( (string) $row['terms_accepted_at'] ) : '',
				'total'             => isset( $row['total'] ) ? (string) $row['total'] : '',
				'currency'          => isset( $row['currency'] ) ? \sanitize_text_field( (string) $row['currency'] ) : '',
				'payment_reference' => isset( $row['payment_reference'] ) ? \sanitize_text_field( (string) $row['payment_reference'] ) : '',
			);
		}

		return $tickets;
	}
}
