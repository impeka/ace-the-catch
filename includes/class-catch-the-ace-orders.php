<?php
/**
 * Orders custom post type and admin helpers.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class CatchTheAceOrders {

	public const POST_TYPE = 'cta_order';

	private const TABLE_SLUG = 'catch_the_ace_orders';

	private const OPTION_DB_VERSION = 'catch_the_ace_orders_db_version';

	private const DB_VERSION = 3;

	public const OPTION_ORDER_SEQUENCE = 'catch_the_ace_order_sequence';

	public const COOKIE_ORDER_STATE = 'ace_order_state';

	public const CRON_HOOK_ABANDON_ORDERS = 'catch_the_ace_abandon_orders';

	public const STATUS_STARTED    = 'cta_started';
	public const STATUS_IN_PROCESS = 'cta_in_process';
	public const STATUS_FAILED     = 'cta_failed';
	public const STATUS_COMPLETED  = 'cta_completed';
	public const STATUS_ABANDONED  = 'cta_abandoned';
	public const STATUS_REFUNDED   = 'cta_refunded';

	public const META_ORDER_NUMBER  = '_cta_order_number';
	public const META_ORDER_KEY     = '_cta_order_key';
	public const META_ORDER_SESSION = '_cta_order_session_id';
	public const META_ORDER_CART    = '_cta_order_cart';
	public const META_ORDER_TOTAL   = '_cta_order_total';
	public const META_ORDER_CURRENCY = '_cta_order_currency';
	public const META_ORDER_PAYMENT_REFERENCE = '_cta_order_payment_reference';
	public const META_ORDER_PAYMENT_CLIENT_SECRET = '_cta_order_payment_client_secret';
	public const META_ORDER_PAYMENT_PROCESSOR = '_cta_order_payment_processor';
	public const META_ORDER_CUSTOMER_FIRST_NAME = '_cta_order_customer_first_name';
	public const META_ORDER_CUSTOMER_LAST_NAME  = '_cta_order_customer_last_name';
	public const META_ORDER_CUSTOMER_EMAIL      = '_cta_order_customer_email';
	public const META_ORDER_CUSTOMER_PHONE      = '_cta_order_customer_phone';
	public const META_ORDER_CUSTOMER_LOCATION   = '_cta_order_customer_location';
	public const META_ORDER_BENEFACTOR_TERM_ID  = '_cta_order_benefactor_term_id';
	public const META_ORDER_BENEFACTOR_LABEL    = '_cta_order_benefactor_label';
	public const META_ORDER_TERMS_ACCEPTED_AT   = '_cta_order_terms_accepted_at';
	public const META_ORDER_TERMS_URL           = '_cta_order_terms_url';
	public const META_ORDER_RULES_URL           = '_cta_order_rules_url';
	public const META_TICKET_STATUS = '_cta_ticket_status';
	public const META_ORDER_LOG = '_cta_order_log';

	private const ORDER_ACTIVE_TRANSIENT_PREFIX = 'catch_the_ace_order_active_';

	public function __construct() {
		\add_action( 'init', array( $this, 'maybe_create_table' ), 5 );
		\add_action( 'init', array( $this, 'register_post_type' ) );
		\add_action( 'init', array( $this, 'register_post_statuses' ) );
		\add_action( 'init', array( $this, 'ensure_cron_scheduled' ), 30 );
		\add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		\add_action( 'admin_post_cta_resend_receipt', array( $this, 'handle_resend_receipt' ) );
		\add_action( 'admin_post_cta_resend_tickets', array( $this, 'handle_resend_tickets' ) );
		\add_action( 'admin_post_cta_refund_order', array( $this, 'handle_refund_order' ) );
		\add_action( 'save_post_' . self::POST_TYPE, array( $this, 'enforce_order_title' ), 10, 3 );
		\add_filter( 'manage_edit-' . self::POST_TYPE . '_columns', array( $this, 'filter_admin_columns' ) );
		\add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'filter_sortable_columns' ) );
		\add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
		\add_action( 'pre_get_posts', array( $this, 'maybe_adjust_admin_list_query' ) );
		\add_filter( 'posts_orderby', array( $this, 'maybe_override_admin_orderby' ), 10, 2 );
		\add_action( 'admin_footer-post.php', array( $this, 'print_status_dropdown_script' ) );
		\add_action( 'admin_footer-post-new.php', array( $this, 'print_status_dropdown_script' ) );
		\add_action( self::CRON_HOOK_ABANDON_ORDERS, array( $this, 'handle_abandon_orders_cron' ) );
	}

	/**
	 * Install DB schema (activation/upgrade).
	 *
	 * @return void
	 */
	public static function install(): void {
		self::create_table();
		if ( self::table_exists() ) {
			self::migrate_order_numbers();
			self::backfill_created_at();
			\update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
		}
	}

	/**
	 * Create the orders table if needed.
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
	 * Ensure the daily cron event is scheduled (covers plugin upgrades without reactivation).
	 *
	 * @return void
	 */
	public function ensure_cron_scheduled(): void {
		if ( ! \wp_next_scheduled( self::CRON_HOOK_ABANDON_ORDERS ) ) {
			\wp_schedule_event( time() + 3600, 'daily', self::CRON_HOOK_ABANDON_ORDERS );
		}
	}

	/**
	 * Ensure the order title remains in the "Order #X" format.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 * @param bool     $update Whether this is an existing post.
	 * @return void
	 */
	public function enforce_order_title( int $post_id, \WP_Post $post, bool $update ): void {
		if ( \wp_is_post_revision( $post_id ) || \wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$order_number = (int) \get_post_meta( $post_id, self::META_ORDER_NUMBER, true );
		if ( $order_number <= 0 ) {
			return;
		}

		$expected = $this->format_order_title( $order_number );
		if ( $post->post_title === $expected ) {
			return;
		}

		\remove_action( 'save_post_' . self::POST_TYPE, array( $this, 'enforce_order_title' ), 10 );
		\wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $expected,
			)
		);
		\add_action( 'save_post_' . self::POST_TYPE, array( $this, 'enforce_order_title' ), 10, 3 );
	}

	/**
	 * Register the Orders custom post type under the Catch the Ace menu.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'               => \__( 'Orders', 'ace-the-catch' ),
			'singular_name'      => \__( 'Order', 'ace-the-catch' ),
			'menu_name'          => \__( 'Orders', 'ace-the-catch' ),
			'name_admin_bar'     => \__( 'Order', 'ace-the-catch' ),
			'add_new'            => \__( 'Add Order', 'ace-the-catch' ),
			'add_new_item'       => \__( 'Add Order', 'ace-the-catch' ),
			'edit_item'          => \__( 'Edit Order', 'ace-the-catch' ),
			'new_item'           => \__( 'New Order', 'ace-the-catch' ),
			'view_item'          => \__( 'View Order', 'ace-the-catch' ),
			'search_items'       => \__( 'Search Orders', 'ace-the-catch' ),
			'not_found'          => \__( 'No orders found.', 'ace-the-catch' ),
			'not_found_in_trash' => \__( 'No orders found in Trash.', 'ace-the-catch' ),
			'all_items'          => \__( 'All Orders', 'ace-the-catch' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=catch-the-ace',
			'show_in_rest'       => false,
			'exclude_from_search'=> true,
			'has_archive'        => false,
			'rewrite'            => false,
			'query_var'          => false,
			'supports'           => array( 'title' ),
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'capabilities'       => array(
				'create_posts' => 'do_not_allow',
			),
		);

		\register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Get the fully-qualified orders table name.
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
	 * Whether the orders table exists.
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
	 * Check whether a column exists in a table.
	 *
	 * @param string $table_name Table name.
	 * @param string $column_name Column name.
	 * @return bool
	 */
	private static function table_has_column( string $table_name, string $column_name ): bool {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return false;
		}

		$column = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table_name} LIKE %s",
				$column_name
			)
		);

		return ! empty( $column );
	}

	/**
	 * Backfill legacy timestamp values (0000-00-00 00:00:00) from the order post date.
	 *
	 * @return void
	 */
	private static function backfill_created_at(): void {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return;
		}

		$table_name = self::get_table_name();
		if ( ! self::table_exists() ) {
			return;
		}

		$posts_table = $wpdb->posts;
		if ( self::table_has_column( $table_name, 'created_at' ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} t
					INNER JOIN {$posts_table} p ON p.ID = t.order_id
					SET t.created_at = p.post_date
					WHERE t.created_at = %s",
					'0000-00-00 00:00:00'
				)
			);
		}

		if ( self::table_has_column( $table_name, 'updated_at' ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} t
					INNER JOIN {$posts_table} p ON p.ID = t.order_id
					SET t.updated_at = p.post_date
					WHERE t.updated_at = %s",
					'0000-00-00 00:00:00'
				)
			);
		}
	}

	/**
	 * Create the order numbers table.
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
			order_number bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (order_number),
			UNIQUE KEY order_id (order_id)
		) ENGINE=InnoDB {$charset_collate};";

		\dbDelta( $sql );

		// Back-compat: fix legacy created_at values if the column exists.
		self::backfill_created_at();

		// Best-effort: add a foreign key constraint if supported (dbDelta doesn't manage constraints).
		$posts_table = $wpdb->posts;
		$constraint  = 'cta_orders_post_fk';
		$existing_fk = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT CONSTRAINT_NAME
				FROM information_schema.KEY_COLUMN_USAGE
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = %s
				AND CONSTRAINT_NAME = %s
				LIMIT 1",
				$table_name,
				$constraint
			)
		);

		if ( ! $existing_fk ) {
			$wpdb->query(
				"ALTER TABLE {$table_name}
				ADD CONSTRAINT {$constraint}
				FOREIGN KEY (order_id) REFERENCES {$posts_table}(ID)
				ON DELETE CASCADE"
			);
		}
	}

	/**
	 * Migrate existing order numbers (meta/title) into the order table.
	 *
	 * @return void
	 */
	private static function migrate_order_numbers(): void {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) || ! self::table_exists() ) {
			return;
		}

		$table = self::get_table_name();
		$posts = $wpdb->posts;
		$postmeta = $wpdb->postmeta;
		$has_created = self::table_has_column( $table, 'created_at' );
		$has_updated = self::table_has_column( $table, 'updated_at' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as order_id, p.post_date as post_date, p.post_title as post_title, t.order_number as mapped_order_number, pm.meta_value as meta_order_number
				FROM {$posts} p
				LEFT JOIN {$table} t ON t.order_id = p.ID
				LEFT JOIN {$postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				WHERE p.post_type = %s",
				self::META_ORDER_NUMBER,
				self::POST_TYPE
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$order_id = (int) ( $row['order_id'] ?? 0 );
			if ( $order_id <= 0 ) {
				continue;
			}

			$mapped_number = (int) ( $row['mapped_order_number'] ?? 0 );
			if ( $mapped_number > 0 ) {
				if ( empty( $row['meta_order_number'] ) || (int) $row['meta_order_number'] !== $mapped_number ) {
					\update_post_meta( $order_id, self::META_ORDER_NUMBER, $mapped_number );
				}
				continue;
			}

			$order_number = (int) ( $row['meta_order_number'] ?? 0 );
			if ( $order_number <= 0 ) {
				$title = (string) ( $row['post_title'] ?? '' );
				if ( preg_match( '/#\s*(\d+)/', $title, $matches ) ) {
					$order_number = (int) $matches[1];
				}
			}

			if ( $order_number <= 0 ) {
				continue;
			}

			$post_date = isset( $row['post_date'] ) ? (string) $row['post_date'] : '';
			if ( '' === $post_date ) {
				$post_date = \current_time( 'mysql' );
			}

			if ( $has_created && $has_updated ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$table} (order_number, order_id, created_at, updated_at) VALUES (%d, %d, %s, %s)",
						$order_number,
						$order_id,
						$post_date,
						$post_date
					)
				);
			} elseif ( $has_created ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$table} (order_number, order_id, created_at) VALUES (%d, %d, %s)",
						$order_number,
						$order_id,
						$post_date
					)
				);
			} elseif ( $has_updated ) {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$table} (order_number, order_id, updated_at) VALUES (%d, %d, %s)",
						$order_number,
						$order_id,
						$post_date
					)
				);
			} else {
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$table} (order_number, order_id) VALUES (%d, %d)",
						$order_number,
						$order_id
					)
				);
			}

			if ( empty( $row['meta_order_number'] ) ) {
				\update_post_meta( $order_id, self::META_ORDER_NUMBER, $order_number );
			}
		}
	}

	/**
	 * Register order statuses as post statuses.
	 *
	 * @return void
	 */
	public function register_post_statuses(): void {
		foreach ( $this->get_statuses() as $key => $label ) {
			\register_post_status(
				$key,
				array(
					'label'                     => $label,
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => \_n_noop(
						$label . ' <span class="count">(%s)</span>',
						$label . ' <span class="count">(%s)</span>',
						'ace-the-catch'
					),
				)
			);
		}
	}

	/**
	 * Cron handler: mark stale started orders as abandoned when their transient marker has expired.
	 *
	 * @return void
	 */
	public function handle_abandon_orders_cron(): void {
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( self::STATUS_STARTED ),
				'fields'         => 'ids',
				'posts_per_page' => 200,
				'no_found_rows'  => true,
			)
		);

		if ( empty( $query->posts ) ) {
			return;
		}

		foreach ( $query->posts as $order_id ) {
			$order_id = (int) $order_id;
			$order_number = (int) \get_post_meta( $order_id, self::META_ORDER_NUMBER, true );
			if ( $order_number <= 0 ) {
				continue;
			}

			$transient_key = $this->get_active_transient_key( $order_number );
			$active = \get_transient( $transient_key );
			if ( false !== $active ) {
				continue;
			}

			$this->set_order_status( $order_id, self::STATUS_ABANDONED );
			$this->append_log( $order_id, \__( 'Order marked as abandoned due to inactivity.', 'ace-the-catch' ) );
		}
	}

	/**
	 * Create a new order with a sequential order number.
	 *
	 * @param int   $session_post_id Session post ID.
	 * @param array $cart Envelope => qty.
	 * @param array $meta Additional meta values to set.
	 * @return array{order_id:int,order_number:int,order_key:string}
	 */
	public function create_order( int $session_post_id, array $cart = array(), array $meta = array() ): array {
		$order_key    = $this->generate_order_key();

		$insert_result = \wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::STATUS_STARTED,
				'post_title'  => \__( 'Order', 'ace-the-catch' ),
			),
			true
		);

		if ( \is_wp_error( $insert_result ) || ! \is_int( $insert_result ) || $insert_result <= 0 ) {
			return array(
				'order_id'     => 0,
				'order_number' => 0,
				'order_key'    => '',
			);
		}

		$order_id = $insert_result;

		$order_number = $this->allocate_order_number( $order_id );
		if ( $order_number <= 0 ) {
			$order_number = $this->next_order_number();
			$this->persist_order_number_mapping( $order_id, $order_number );
		}

		\update_post_meta( $order_id, self::META_ORDER_NUMBER, $order_number );
		\update_post_meta( $order_id, self::META_ORDER_KEY, $order_key );
		\update_post_meta( $order_id, self::META_ORDER_SESSION, $session_post_id );
		\update_post_meta( $order_id, self::META_ORDER_CART, array() );
		\update_post_meta( $order_id, self::META_TICKET_STATUS, CatchTheAceTickets::STATUS_NOT_GENERATED );

		\wp_update_post(
			array(
				'ID'         => $order_id,
				'post_title' => $this->format_order_title( $order_number ),
			)
		);

		foreach ( $meta as $key => $value ) {
			if ( \is_string( $key ) && '' !== $key ) {
				\update_post_meta( $order_id, $key, $value );
			}
		}

		$this->touch_order( $order_id );
		$this->append_log( $order_id, \__( 'Order started.', 'ace-the-catch' ) );

		if ( ! empty( $cart ) ) {
			$total    = isset( $meta[ self::META_ORDER_TOTAL ] ) ? (float) $meta[ self::META_ORDER_TOTAL ] : 0.0;
			$currency = isset( $meta[ self::META_ORDER_CURRENCY ] ) ? (string) $meta[ self::META_ORDER_CURRENCY ] : 'cad';
			$this->update_order_cart( $order_id, $cart, $total, $currency );
		}

		return array(
			'order_id'     => $order_id,
			'order_number' => $order_number,
			'order_key'    => $order_key,
		);
	}

	/**
	 * Allocate the next sequential order number using the orders table.
	 *
	 * @param int $order_id Order post ID.
	 * @return int
	 */
	private function allocate_order_number( int $order_id ): int {
		if ( $order_id <= 0 ) {
			return 0;
		}

		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) || ! self::table_exists() ) {
			return 0;
		}

		$table = self::get_table_name();

		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT order_number FROM {$table} WHERE order_id = %d LIMIT 1",
				$order_id
			)
		);
		if ( $existing > 0 ) {
			return $existing;
		}

		$data    = array( 'order_id' => $order_id );
		$formats = array( '%d' );

		// Back-compat: populate legacy timestamp columns if they exist.
		$now = \current_time( 'mysql' );
		if ( self::table_has_column( $table, 'created_at' ) ) {
			$data['created_at'] = $now;
			$formats[]          = '%s';
		}
		if ( self::table_has_column( $table, 'updated_at' ) ) {
			$data['updated_at'] = $now;
			$formats[]          = '%s';
		}

		$inserted = $wpdb->insert( $table, $data, $formats );

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Best-effort: persist an explicit mapping (used when falling back from DB allocation).
	 *
	 * @param int $order_id Order post ID.
	 * @param int $order_number Order number.
	 * @return void
	 */
	private function persist_order_number_mapping( int $order_id, int $order_number ): void {
		if ( $order_id <= 0 || $order_number <= 0 ) {
			return;
		}

		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) || ! self::table_exists() ) {
			return;
		}

		$table = self::get_table_name();
		$now         = \current_time( 'mysql' );
		$has_created = self::table_has_column( $table, 'created_at' );
		$has_updated = self::table_has_column( $table, 'updated_at' );

		if ( $has_created && $has_updated ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table} (order_number, order_id, created_at, updated_at) VALUES (%d, %d, %s, %s)",
					$order_number,
					$order_id,
					$now,
					$now
				)
			);
			return;
		}

		if ( $has_created ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table} (order_number, order_id, created_at) VALUES (%d, %d, %s)",
					$order_number,
					$order_id,
					$now
				)
			);
			return;
		}

		if ( $has_updated ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$table} (order_number, order_id, updated_at) VALUES (%d, %d, %s)",
					$order_number,
					$order_id,
					$now
				)
			);
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (order_number, order_id) VALUES (%d, %d)",
				$order_number,
				$order_id
			)
		);
	}

	/**
	 * Resolve an order post ID by its sequential order number.
	 *
	 * @param int $order_number Order number.
	 * @return int
	 */
	private function lookup_order_id_by_number( int $order_number ): int {
		if ( $order_number <= 0 ) {
			return 0;
		}

		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return 0;
		}

		if ( self::table_exists() ) {
			$table = self::get_table_name();
			$order_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT order_id FROM {$table} WHERE order_number = %d LIMIT 1",
					$order_number
				)
			);
			if ( $order_id > 0 ) {
				return $order_id;
			}
		}

		$posts = $wpdb->posts;
		$postmeta = $wpdb->postmeta;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$posts} p
				INNER JOIN {$postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				WHERE p.post_type = %s AND pm.meta_value = %s
				LIMIT 1",
				self::META_ORDER_NUMBER,
				self::POST_TYPE,
				(string) $order_number
			)
		);
	}

	/**
	 * Update an existing order's cart and derived totals, writing a log entry for changes.
	 *
	 * @param int    $order_id Order post ID.
	 * @param array  $cart Envelope => qty (validated).
	 * @param float  $total Total amount.
	 * @param string $currency Currency code.
	 * @return void
	 */
	public function update_order_cart( int $order_id, array $cart, float $total, string $currency = 'cad' ): void {
		$old_cart = $this->get_order_cart( $order_id );
		\update_post_meta( $order_id, self::META_ORDER_CART, $cart );
		\update_post_meta( $order_id, self::META_ORDER_TOTAL, $total );
		\update_post_meta( $order_id, self::META_ORDER_CURRENCY, $currency );

		$this->touch_order( $order_id );

		$messages = $this->diff_cart_messages( $old_cart, $cart );
		foreach ( $messages as $message ) {
			$this->append_log( $order_id, $message );
		}

		$this->sync_order_payment_object( $order_id, $cart, $total, $currency );
	}

	/**
	 * Sync/create any provider-side payment object for this order (e.g. Stripe PaymentIntent).
	 *
	 * @param int    $order_id Order post ID.
	 * @param array  $cart Envelope => qty.
	 * @param float  $total Total amount.
	 * @param string $currency Currency code.
	 * @return void
	 */
	private function sync_order_payment_object( int $order_id, array $cart, float $total, string $currency ): void {
		if ( $order_id <= 0 || $total <= 0 || empty( $cart ) ) {
			return;
		}

		$processor_key = (string) \get_post_meta( $order_id, self::META_ORDER_PAYMENT_PROCESSOR, true );
		if ( '' === $processor_key ) {
			$processor_key = (string) \get_option( CatchTheAceSettings::OPTION_PAYMENT_PROC, '' );
		}
		if ( '' === $processor_key ) {
			return;
		}

		$factory   = Plugin::instance()->get_payment_processor_factory();
		$processor = $factory->create( $processor_key );
		if ( ! ( $processor instanceof PaymentProcessor ) ) {
			return;
		}

		$configs = \get_option( CatchTheAceSettings::OPTION_PAYMENT_PROC_CFG, array() );
		$config  = array();
		if ( \is_array( $configs ) && isset( $configs[ $processor_key ] ) && \is_array( $configs[ $processor_key ] ) ) {
			$config = $configs[ $processor_key ];
		}

		$order_number = (int) \get_post_meta( $order_id, self::META_ORDER_NUMBER, true );
		$reference    = (string) \get_post_meta( $order_id, self::META_ORDER_PAYMENT_REFERENCE, true );

		$first_name = \sanitize_text_field( (string) \get_post_meta( $order_id, self::META_ORDER_CUSTOMER_FIRST_NAME, true ) );
		$last_name  = \sanitize_text_field( (string) \get_post_meta( $order_id, self::META_ORDER_CUSTOMER_LAST_NAME, true ) );
		$email      = \sanitize_email( (string) \get_post_meta( $order_id, self::META_ORDER_CUSTOMER_EMAIL, true ) );

		$description = $order_number > 0
			? \sprintf( \__( 'Catch the Ace order #%d', 'ace-the-catch' ), $order_number )
			: \__( 'Catch the Ace order', 'ace-the-catch' );

		$result = $processor->sync_order_payment(
			array(
				'order_id'     => $order_id,
				'order_number' => $order_number,
				'amount'       => $total,
				'currency'     => $currency,
				'description'  => $description,
				'customer'     => array(
					'first_name' => $first_name,
					'last_name'  => $last_name,
					'email'      => $email,
				),
				'items'        => $cart,
				'reference'    => $reference,
			),
			$config
		);

		$new_reference = isset( $result['reference'] ) ? (string) $result['reference'] : '';
		if ( $new_reference && $new_reference !== $reference ) {
			\update_post_meta( $order_id, self::META_ORDER_PAYMENT_REFERENCE, $new_reference );
		}

		$client_secret = isset( $result['client_secret'] ) ? (string) $result['client_secret'] : '';
		if ( $client_secret ) {
			\update_post_meta( $order_id, self::META_ORDER_PAYMENT_CLIENT_SECRET, $client_secret );
		}

		$error = isset( $result['error'] ) ? (string) $result['error'] : '';
		if ( $error ) {
			$this->append_log( $order_id, \sprintf( \__( 'Payment sync failed: %s', 'ace-the-catch' ), $error ) );
		}
	}

	/**
	 * Set customer details on the order.
	 *
	 * @param int    $order_id Order post ID.
	 * @param string $first_name First name.
	 * @param string $last_name Last name.
	 * @param string $email Email.
	 * @param string $phone Phone.
	 * @param string $location General location.
	 * @return void
	 */
	public function set_customer( int $order_id, string $first_name, string $last_name, string $email, string $phone = '', string $location = '' ): void {
		\update_post_meta( $order_id, self::META_ORDER_CUSTOMER_FIRST_NAME, $first_name );
		\update_post_meta( $order_id, self::META_ORDER_CUSTOMER_LAST_NAME, $last_name );
		\update_post_meta( $order_id, self::META_ORDER_CUSTOMER_EMAIL, $email );
		\update_post_meta( $order_id, self::META_ORDER_CUSTOMER_PHONE, $phone );
		\update_post_meta( $order_id, self::META_ORDER_CUSTOMER_LOCATION, $location );
		$this->append_log(
			$order_id,
			\sprintf(
				/* translators: 1: full name, 2: email */
				\__( 'Customer set: %1$s <%2$s>.', 'ace-the-catch' ),
				\trim( $first_name . ' ' . $last_name ),
				$email
			)
		);
	}

	/**
	 * Set benefactor selection on the order.
	 *
	 * @param int    $order_id Order post ID.
	 * @param int    $term_id Benefactor term ID (0 = all).
	 * @param string $label Benefactor label.
	 * @return void
	 */
	public function set_benefactor( int $order_id, int $term_id, string $label = '' ): void {
		\update_post_meta( $order_id, self::META_ORDER_BENEFACTOR_TERM_ID, $term_id );
		\update_post_meta( $order_id, self::META_ORDER_BENEFACTOR_LABEL, $label );
	}

	/**
	 * Record terms acceptance on the order.
	 *
	 * @param int    $order_id Order post ID.
	 * @param string $terms_url Terms URL used by the user.
	 * @param string $rules_url Rules of play URL used by the user.
	 * @return void
	 */
	public function set_terms_acceptance( int $order_id, string $terms_url = '', string $rules_url = '' ): void {
		\update_post_meta( $order_id, self::META_ORDER_TERMS_ACCEPTED_AT, \current_time( 'mysql' ) );
		if ( $terms_url ) {
			\update_post_meta( $order_id, self::META_ORDER_TERMS_URL, $terms_url );
		}
		if ( $rules_url ) {
			\update_post_meta( $order_id, self::META_ORDER_RULES_URL, $rules_url );
		}
	}

	/**
	 * Set payment details on the order.
	 *
	 * @param int    $order_id Order post ID.
	 * @param string $processor_key Payment processor key.
	 * @param string $reference Payment reference/ID.
	 * @return void
	 */
	public function set_payment( int $order_id, string $processor_key, string $reference = '' ): void {
		if ( $processor_key ) {
			\update_post_meta( $order_id, self::META_ORDER_PAYMENT_PROCESSOR, $processor_key );
		}
		if ( $reference ) {
			\update_post_meta( $order_id, self::META_ORDER_PAYMENT_REFERENCE, $reference );
		}
	}

	/**
	 * Set the order post status.
	 *
	 * @param int    $order_id Order post ID.
	 * @param string $status Order status slug.
	 * @return void
	 */
	public function set_order_status( int $order_id, string $status ): void {
		\wp_update_post(
			array(
				'ID'          => $order_id,
				'post_status' => $status,
			)
		);
	}

	/**
	 * Load the current order from cookie for a given session.
	 *
	 * @param int $session_post_id Session post ID.
	 * @return array{order_id:int,order_number:int,order_key:string} | null
	 */
	public function get_current_order( int $session_post_id ): ?array {
		$data = $this->get_order_cookie();
		if ( ! $data ) {
			return null;
		}

		$order_id   = (int) ( $data['orderId'] ?? 0 );
		$order_key  = (string) ( $data['orderKey'] ?? '' );
		$cookie_sid = (int) ( $data['sessionId'] ?? 0 );

		if ( $order_id <= 0 || '' === $order_key || $cookie_sid !== $session_post_id ) {
			return null;
		}

		$post = \get_post( $order_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$stored_key = (string) \get_post_meta( $order_id, self::META_ORDER_KEY, true );
		$stored_sid = (int) \get_post_meta( $order_id, self::META_ORDER_SESSION, true );
		if ( ! \hash_equals( $stored_key, $order_key ) || $stored_sid !== $session_post_id ) {
			return null;
		}

		if ( \in_array( $post->post_status, array( self::STATUS_COMPLETED, self::STATUS_REFUNDED, self::STATUS_ABANDONED ), true ) ) {
			$this->clear_order_cookie();
			return null;
		}

		// If a started order is no longer active, mark it as abandoned and clear cookie.
		if ( self::STATUS_STARTED === $post->post_status ) {
			$order_number = (int) \get_post_meta( $order_id, self::META_ORDER_NUMBER, true );
			if ( $order_number > 0 ) {
				$active = \get_transient( $this->get_active_transient_key( $order_number ) );
				if ( false === $active ) {
					$this->set_order_status( $order_id, self::STATUS_ABANDONED );
					$this->append_log( $order_id, \__( 'Order marked as abandoned due to inactivity.', 'ace-the-catch' ) );
					$this->clear_order_cookie();
					return null;
				}
			}
		}

		return array(
			'order_id'     => $order_id,
			'order_number' => (int) \get_post_meta( $order_id, self::META_ORDER_NUMBER, true ),
			'order_key'    => $order_key,
		);
	}

	/**
	 * Persist the current order cookie.
	 *
	 * @param int    $session_post_id Session post ID.
	 * @param int    $order_id Order post ID.
	 * @param string $order_key Order key.
	 * @param int    $ttl Cookie TTL seconds.
	 * @return void
	 */
	public function persist_order_cookie( int $session_post_id, int $order_id, string $order_key, int $ttl = 86400 ): void {
		$payload = array(
			'sessionId' => $session_post_id,
			'orderId'   => $order_id,
			'orderKey'  => $order_key,
		);
		$json = \wp_json_encode( $payload );
		if ( false === $json ) {
			return;
		}
		\setcookie( self::COOKIE_ORDER_STATE, \rawurlencode( $json ), time() + $ttl, '/' );
	}

	/**
	 * Clear the current order cookie.
	 *
	 * @return void
	 */
	public function clear_order_cookie(): void {
		\setcookie( self::COOKIE_ORDER_STATE, '', time() - 3600, '/' );
	}

	/**
	 * Touch/update the active-order transient marker.
	 *
	 * @param int $order_id Order post ID.
	 * @param int $ttl Seconds.
	 * @return void
	 */
	public function touch_order( int $order_id, int $ttl = 86400 ): void {
		$order_number = (int) \get_post_meta( $order_id, self::META_ORDER_NUMBER, true );
		if ( $order_number <= 0 ) {
			return;
		}
		\set_transient( $this->get_active_transient_key( $order_number ), $order_id, $ttl );
	}

	/**
	 * Get order cart from post meta.
	 *
	 * @param int $order_id Order post ID.
	 * @return array<int,int>
	 */
	public function get_order_cart( int $order_id ): array {
		$cart = \get_post_meta( $order_id, self::META_ORDER_CART, true );
		if ( ! \is_array( $cart ) ) {
			return array();
		}

		$clean = array();
		foreach ( $cart as $env => $qty ) {
			$env_num = (int) $env;
			$qty_num = (int) $qty;
			if ( $env_num > 0 && $qty_num > 0 ) {
				$clean[ $env_num ] = $qty_num;
			}
		}

		return $clean;
	}

	/**
	 * Append a log entry to the order.
	 *
	 * @param int    $order_id Order post ID.
	 * @param string $message Log message.
	 * @param array  $context Optional context.
	 * @return void
	 */
	public function append_log( int $order_id, string $message, array $context = array() ): void {
		$log = \get_post_meta( $order_id, self::META_ORDER_LOG, true );
		if ( ! \is_array( $log ) ) {
			$log = array();
		}

		$entry = array(
			'time'    => \current_time( 'mysql' ),
			'message' => $message,
		);
		if ( ! empty( $context ) ) {
			$entry['context'] = $context;
		}

		$log[] = $entry;

		\update_post_meta( $order_id, self::META_ORDER_LOG, $log );
	}

	/**
	 * Get the active transient key for a given order number.
	 *
	 * @param int $order_number Order number.
	 * @return string
	 */
	private function get_active_transient_key( int $order_number ): string {
		return self::ORDER_ACTIVE_TRANSIENT_PREFIX . $order_number;
	}

	/**
	 * Parse the order state cookie.
	 *
	 * @return array|null
	 */
	private function get_order_cookie(): ?array {
		if ( empty( $_COOKIE[ self::COOKIE_ORDER_STATE ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			return null;
		}

		$json = \urldecode( (string) $_COOKIE[ self::COOKIE_ORDER_STATE ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = \json_decode( $json, true );

		return \is_array( $data ) ? $data : null;
	}

	/**
	 * Format an order title from its order number.
	 *
	 * @param int $order_number Order number.
	 * @return string
	 */
	public function format_order_title( int $order_number ): string {
		return \sprintf( 'Order #%d', $order_number );
	}

	/**
	 * Generate the next sequential order number (atomic when possible).
	 *
	 * @return int
	 */
	private function next_order_number(): int {
		global $wpdb;

		if ( $wpdb instanceof \wpdb ) {
			$table = $wpdb->options;
			$sql = $wpdb->prepare(
				"INSERT INTO {$table} (option_name, option_value, autoload) VALUES (%s, %d, 'no')
				ON DUPLICATE KEY UPDATE option_value = LAST_INSERT_ID(option_value + 1)",
				self::OPTION_ORDER_SEQUENCE,
				1
			);

			$result = $wpdb->query( $sql );
			if ( false !== $result ) {
				$next = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
				if ( $next > 0 ) {
					return $next;
				}
			}
		}

		$current = (int) \get_option( self::OPTION_ORDER_SEQUENCE, 0 );
		$next    = $current + 1;
		\update_option( self::OPTION_ORDER_SEQUENCE, $next, false );
		return $next;
	}

	/**
	 * Generate a secret order key for cookie verification.
	 *
	 * @return string
	 */
	private function generate_order_key(): string {
		if ( \function_exists( 'random_bytes' ) ) {
			try {
				return \bin2hex( \random_bytes( 16 ) );
			} catch ( \Throwable $e ) {
				// Fall back to WP helper.
			}
		}

		return \wp_generate_password( 32, false, false );
	}

	/**
	 * Human-readable messages describing differences between two carts.
	 *
	 * @param array<int,int> $old Old cart.
	 * @param array<int,int> $new New cart.
	 * @return string[]
	 */
	private function diff_cart_messages( array $old, array $new ): array {
		$messages = array();

		$removed = \array_diff_key( $old, $new );
		foreach ( $removed as $env => $qty ) {
			$messages[] = \sprintf( \__( 'Removed Envelope #%d (qty %d).', 'ace-the-catch' ), (int) $env, (int) $qty );
		}

		$added = \array_diff_key( $new, $old );
		foreach ( $added as $env => $qty ) {
			$messages[] = \sprintf( \__( 'Added Envelope #%d (qty %d).', 'ace-the-catch' ), (int) $env, (int) $qty );
		}

		foreach ( $new as $env => $qty ) {
			$env = (int) $env;
			$qty = (int) $qty;
			if ( isset( $old[ $env ] ) && (int) $old[ $env ] !== $qty ) {
				$messages[] = \sprintf(
					/* translators: 1: envelope number, 2: old qty, 3: new qty */
					\__( 'Updated Envelope #%1$d quantity from %2$d to %3$d.', 'ace-the-catch' ),
					$env,
					(int) $old[ $env ],
					$qty
				);
			}
		}

		if ( empty( $messages ) ) {
			$messages[] = \__( 'Cart updated (no changes detected).', 'ace-the-catch' );
		}

		return $messages;
	}

	/**
	 * Status slug => label map.
	 *
	 * @return array<string,string>
	 */
	public function get_statuses(): array {
		return array(
			self::STATUS_STARTED    => \__( 'Started', 'ace-the-catch' ),
			self::STATUS_IN_PROCESS => \__( 'In process', 'ace-the-catch' ),
			self::STATUS_FAILED     => \__( 'Failed', 'ace-the-catch' ),
			self::STATUS_COMPLETED  => \__( 'Completed', 'ace-the-catch' ),
			self::STATUS_ABANDONED  => \__( 'Abandoned', 'ace-the-catch' ),
			self::STATUS_REFUNDED   => \__( 'Refunded / Cancelled', 'ace-the-catch' ),
		);
	}

	/**
	 * Register meta boxes for order details and logs.
	 *
	 * @return void
	 */
	public function register_meta_boxes(): void {
		\add_meta_box(
			'cta-order-details',
			\__( 'Order Details', 'ace-the-catch' ),
			array( $this, 'render_order_details_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		\add_meta_box(
			'cta-order-log',
			\__( 'Order Log', 'ace-the-catch' ),
			array( $this, 'render_order_log_meta_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);

		\add_meta_box(
			'cta-order-refund',
			\__( 'Refund', 'ace-the-catch' ),
			array( $this, 'render_order_refund_meta_box' ),
			self::POST_TYPE,
			'side',
			'low'
		);

		\add_meta_box(
			'cta-order-email-actions',
			\__( 'Email Actions', 'ace-the-catch' ),
			array( $this, 'render_order_email_actions_meta_box' ),
			self::POST_TYPE,
			'side',
			'low'
		);
	}

	/**
	 * Render the order log meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_order_log_meta_box( \WP_Post $post ): void {
		$log = \get_post_meta( $post->ID, self::META_ORDER_LOG, true );
		if ( ! \is_array( $log ) || empty( $log ) ) {
			echo '<p class="description">' . \esc_html__( 'No log entries yet.', 'ace-the-catch' ) . '</p>';
			return;
		}

		echo '<div class="cta-order-log__scroll">';
		echo '<ol class="cta-order-log__list">';
		foreach ( $log as $entry ) {
			if ( ! \is_array( $entry ) ) {
				continue;
			}
			$time    = isset( $entry['time'] ) ? (string) $entry['time'] : '';
			$message = isset( $entry['message'] ) ? (string) $entry['message'] : '';
			if ( '' === $time && '' === $message ) {
				continue;
			}
			echo '<li><strong>' . \esc_html( $time ) . '</strong> - ' . \esc_html( $message ) . '</li>';
		}
		echo '</ol>';
		echo '</div>';
	}

	/**
	 * Render refund action meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_order_refund_meta_box( \WP_Post $post ): void {
		$order_id = (int) $post->ID;

		$status        = (string) $post->post_status;
		$ticket_status = (string) \get_post_meta( $order_id, self::META_TICKET_STATUS, true );
		$order_number  = (int) \get_post_meta( $order_id, self::META_ORDER_NUMBER, true );
		$total_amount  = (float) \get_post_meta( $order_id, self::META_ORDER_TOTAL, true );
		$currency      = (string) \get_post_meta( $order_id, self::META_ORDER_CURRENCY, true );

		$refund_enabled = true;
		$disabled_reasons = array();

		if ( self::STATUS_REFUNDED === $status ) {
			$refund_enabled = false;
			$disabled_reasons[] = \__( 'Order is already refunded.', 'ace-the-catch' );
		}

		if ( self::STATUS_COMPLETED !== $status ) {
			$refund_enabled = false;
			$disabled_reasons[] = \__( 'Only completed orders can be refunded.', 'ace-the-catch' );
		}

		if ( CatchTheAceTickets::STATUS_IN_PROCESS === $ticket_status ) {
			$refund_enabled = false;
			$disabled_reasons[] = \__( 'Tickets are currently being generated.', 'ace-the-catch' );
		}

		$processor_key = (string) \get_post_meta( $order_id, self::META_ORDER_PAYMENT_PROCESSOR, true );
		$payment_reference = (string) \get_post_meta( $order_id, self::META_ORDER_PAYMENT_REFERENCE, true );
		if ( '' === trim( $processor_key ) || '' === trim( $payment_reference ) ) {
			$refund_enabled = false;
			$disabled_reasons[] = \__( 'Payment information is missing on this order.', 'ace-the-catch' );
		}

		$notice_key = isset( $_GET['cta_order_notice'] ) ? \sanitize_key( (string) \wp_unslash( $_GET['cta_order_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice_map = array(
			'refund_succeeded'        => array( 'success', \__( 'Refund completed and tickets cancelled.', 'ace-the-catch' ) ),
			'refund_failed'           => array( 'error', \__( 'Refund failed. Check the order log for details.', 'ace-the-catch' ) ),
			'refund_disabled'         => array( 'warning', \__( 'Refund is only available for completed orders.', 'ace-the-catch' ) ),
			'refund_already'          => array( 'info', \__( 'Order is already refunded.', 'ace-the-catch' ) ),
			'refund_blocked_generating' => array( 'warning', \__( 'Refund is temporarily disabled while tickets are being generated.', 'ace-the-catch' ) ),
		);
		if ( $notice_key && isset( $notice_map[ $notice_key ] ) ) {
			$notice_type = $notice_map[ $notice_key ][0] ?? 'info';
			$notice_text = $notice_map[ $notice_key ][1] ?? '';
			$notice_class = 'notice notice-' . $notice_type;
			echo '<div class="' . \esc_attr( $notice_class ) . '" style="margin: 0 0 10px 0;"><p style="margin:6px 0;">' . \esc_html( (string) $notice_text ) . '</p></div>';
		}

		$refund_url = \wp_nonce_url(
			\admin_url( 'admin-post.php?action=cta_refund_order&order_id=' . (string) $order_id ),
			'cta_refund_order_' . (string) $order_id
		);

		$currency_display = $currency ? strtoupper( $currency ) : '';
		$amount_display = '$' . \number_format_i18n( $total_amount, 2 ) . ( $currency_display ? ' ' . $currency_display : '' );

		if ( $refund_enabled ) {
			echo '<button type="button" class="button cta-refund-button" data-cta-refund-url="' . \esc_url( $refund_url ) . '" data-cta-order-number="' . \esc_attr( (string) $order_number ) . '" data-cta-order-total="' . \esc_attr( (string) $total_amount ) . '" data-cta-order-amount-display="' . \esc_attr( $amount_display ) . '">' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				. '<span class="dashicons dashicons-money-alt" aria-hidden="true"></span>'
				. \esc_html__( 'Refund Order', 'ace-the-catch' )
				. '</button>';
		} else {
			echo '<button type="button" class="button cta-refund-button" disabled>'
				. '<span class="dashicons dashicons-money-alt" aria-hidden="true"></span>'
				. \esc_html__( 'Refund Order', 'ace-the-catch' )
				. '</button>';
		}

		echo '<p class="description" style="margin: 10px 0 0;">' . \esc_html__( 'Refunds the customer and cancels all tickets for this order.', 'ace-the-catch' ) . '</p>';

		if ( ! empty( $disabled_reasons ) ) {
			echo '<p class="description" style="margin: 6px 0 0;">' . \esc_html( \implode( ' ', $disabled_reasons ) ) . '</p>';
		}
	}

	/**
	 * Render order email action buttons meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_order_email_actions_meta_box( \WP_Post $post ): void {
		$ticket_status = (string) \get_post_meta( $post->ID, self::META_TICKET_STATUS, true );

		$receipt_enabled = ( self::STATUS_COMPLETED === $post->post_status );
		$tickets_enabled = ( CatchTheAceTickets::STATUS_GENERATED === $ticket_status );

		$notice_key = isset( $_GET['cta_order_notice'] ) ? \sanitize_key( (string) \wp_unslash( $_GET['cta_order_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice_map = array(
			'receipt_sent'     => array( 'success', \__( 'Receipt email sent.', 'ace-the-catch' ) ),
			'receipt_failed'   => array( 'error', \__( 'Receipt email failed to send.', 'ace-the-catch' ) ),
			'receipt_disabled' => array( 'warning', \__( 'Receipt can only be resent for completed orders.', 'ace-the-catch' ) ),
			'tickets_sent'     => array( 'success', \__( 'Ticket email sent.', 'ace-the-catch' ) ),
			'tickets_failed'   => array( 'error', \__( 'Ticket email failed to send.', 'ace-the-catch' ) ),
			'tickets_disabled' => array( 'warning', \__( 'Tickets can only be resent after they have been generated.', 'ace-the-catch' ) ),
		);
		if ( $notice_key && isset( $notice_map[ $notice_key ] ) ) {
			$notice_type = $notice_map[ $notice_key ][0] ?? 'info';
			$notice_text = $notice_map[ $notice_key ][1] ?? '';
			$notice_class = 'notice notice-' . $notice_type;
			echo '<div class="' . \esc_attr( $notice_class ) . '" style="margin: 0 0 10px 0;"><p style="margin:6px 0;">' . \esc_html( (string) $notice_text ) . '</p></div>';
		}

		$receipt_url = \wp_nonce_url(
			\admin_url( 'admin-post.php?action=cta_resend_receipt&order_id=' . (string) $post->ID ),
			'cta_resend_receipt_' . (string) $post->ID
		);
		$tickets_url = \wp_nonce_url(
			\admin_url( 'admin-post.php?action=cta_resend_tickets&order_id=' . (string) $post->ID ),
			'cta_resend_tickets_' . (string) $post->ID
		);

		echo '<p style="margin: 0 0 8px;">';
		if ( $receipt_enabled ) {
			echo '<a class="button button-secondary" style="width:100%;text-align:center;" href="' . \esc_url( $receipt_url ) . '">' . \esc_html__( 'Resend receipt', 'ace-the-catch' ) . '</a>';
		} else {
			echo '<button type="button" class="button button-secondary" style="width:100%;" disabled>' . \esc_html__( 'Resend receipt', 'ace-the-catch' ) . '</button>';
		}
		echo '</p>';

		echo '<p style="margin: 0 0 8px;">';
		if ( $tickets_enabled ) {
			echo '<a class="button button-secondary" style="width:100%;text-align:center;" href="' . \esc_url( $tickets_url ) . '">' . \esc_html__( 'Resend tickets', 'ace-the-catch' ) . '</a>';
		} else {
			echo '<button type="button" class="button button-secondary" style="width:100%;" disabled>' . \esc_html__( 'Resend tickets', 'ace-the-catch' ) . '</button>';
		}
		echo '</p>';

		if ( ! $receipt_enabled || ! $tickets_enabled ) {
			echo '<p class="description" style="margin: 0;">';
			$messages = array();
			if ( ! $receipt_enabled ) {
				$messages[] = \__( 'Receipt is available once the order is completed.', 'ace-the-catch' );
			}
			if ( ! $tickets_enabled ) {
				$messages[] = \__( 'Tickets are available once they have been generated.', 'ace-the-catch' );
			}
			echo \esc_html( \implode( ' ', $messages ) );
			echo '</p>';
		}
	}

	/**
	 * Admin action: resend transaction receipt email.
	 *
	 * @return void
	 */
	public function handle_resend_receipt(): void {
		$order_id = isset( $_REQUEST['order_id'] ) ? (int) \wp_unslash( $_REQUEST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $order_id <= 0 || self::POST_TYPE !== \get_post_type( $order_id ) ) {
			\wp_die( \esc_html__( 'Invalid request.', 'ace-the-catch' ), '', array( 'response' => 400 ) );
		}

		if ( ! \current_user_can( 'edit_post', $order_id ) ) {
			\wp_die( \esc_html__( 'Unauthorized.', 'ace-the-catch' ), '', array( 'response' => 403 ) );
		}

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? \sanitize_text_field( (string) \wp_unslash( $_REQUEST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! \wp_verify_nonce( $nonce, 'cta_resend_receipt_' . (string) $order_id ) ) {
			\wp_die( \esc_html__( 'Invalid request.', 'ace-the-catch' ), '', array( 'response' => 403 ) );
		}

		$status = (string) \get_post_status( $order_id );
		if ( self::STATUS_COMPLETED !== $status ) {
			$this->redirect_to_order_with_notice( $order_id, 'receipt_disabled' );
		}

		$sent = Plugin::instance()->get_emails()->send_successful_transaction_email( $order_id );
		$this->redirect_to_order_with_notice( $order_id, $sent ? 'receipt_sent' : 'receipt_failed' );
	}

	/**
	 * Admin action: resend ticket delivery email.
	 *
	 * @return void
	 */
	public function handle_resend_tickets(): void {
		$order_id = isset( $_REQUEST['order_id'] ) ? (int) \wp_unslash( $_REQUEST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $order_id <= 0 || self::POST_TYPE !== \get_post_type( $order_id ) ) {
			\wp_die( \esc_html__( 'Invalid request.', 'ace-the-catch' ), '', array( 'response' => 400 ) );
		}

		if ( ! \current_user_can( 'edit_post', $order_id ) ) {
			\wp_die( \esc_html__( 'Unauthorized.', 'ace-the-catch' ), '', array( 'response' => 403 ) );
		}

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? \sanitize_text_field( (string) \wp_unslash( $_REQUEST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! \wp_verify_nonce( $nonce, 'cta_resend_tickets_' . (string) $order_id ) ) {
			\wp_die( \esc_html__( 'Invalid request.', 'ace-the-catch' ), '', array( 'response' => 403 ) );
		}

		$ticket_status = (string) \get_post_meta( $order_id, self::META_TICKET_STATUS, true );
		if ( CatchTheAceTickets::STATUS_GENERATED !== $ticket_status ) {
			$this->redirect_to_order_with_notice( $order_id, 'tickets_disabled' );
		}

		$sent = Plugin::instance()->get_emails()->send_ticket_delivery_email( $order_id );
		$this->redirect_to_order_with_notice( $order_id, $sent ? 'tickets_sent' : 'tickets_failed' );
	}

	/**
	 * Admin action: refund an order and cancel its tickets.
	 *
	 * @return void
	 */
	public function handle_refund_order(): void {
		$order_id = isset( $_REQUEST['order_id'] ) ? (int) \wp_unslash( $_REQUEST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $order_id <= 0 || self::POST_TYPE !== \get_post_type( $order_id ) ) {
			\wp_die( \esc_html__( 'Invalid request.', 'ace-the-catch' ), '', array( 'response' => 400 ) );
		}

		if ( ! \current_user_can( 'edit_post', $order_id ) ) {
			\wp_die( \esc_html__( 'Unauthorized.', 'ace-the-catch' ), '', array( 'response' => 403 ) );
		}

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? \sanitize_text_field( (string) \wp_unslash( $_REQUEST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! \wp_verify_nonce( $nonce, 'cta_refund_order_' . (string) $order_id ) ) {
			\wp_die( \esc_html__( 'Invalid request.', 'ace-the-catch' ), '', array( 'response' => 403 ) );
		}

		$status = (string) \get_post_status( $order_id );
		if ( self::STATUS_REFUNDED === $status ) {
			$this->redirect_to_order_with_notice( $order_id, 'refund_already' );
		}

		if ( self::STATUS_COMPLETED !== $status ) {
			$this->redirect_to_order_with_notice( $order_id, 'refund_disabled' );
		}

		$ticket_status = (string) \get_post_meta( $order_id, self::META_TICKET_STATUS, true );
		if ( CatchTheAceTickets::STATUS_IN_PROCESS === $ticket_status ) {
			$this->redirect_to_order_with_notice( $order_id, 'refund_blocked_generating' );
		}

		$processor_key = (string) \get_post_meta( $order_id, self::META_ORDER_PAYMENT_PROCESSOR, true );
		$processor_key = trim( $processor_key );
		if ( '' === $processor_key ) {
			$this->append_log( $order_id, \__( 'Refund failed: payment processor is missing on the order.', 'ace-the-catch' ) );
			$this->redirect_to_order_with_notice( $order_id, 'refund_failed' );
		}

		$processor = Plugin::instance()->get_payment_processor_factory()->create( $processor_key );
		if ( ! $processor ) {
			$this->append_log( $order_id, \sprintf( \__( 'Refund failed: payment processor "%s" is not available.', 'ace-the-catch' ), $processor_key ) );
			$this->redirect_to_order_with_notice( $order_id, 'refund_failed' );
		}

		$payment_reference = (string) \get_post_meta( $order_id, self::META_ORDER_PAYMENT_REFERENCE, true );
		$payment_reference = trim( $payment_reference );
		if ( '' === $payment_reference ) {
			$this->append_log( $order_id, \__( 'Refund failed: payment reference is missing on the order.', 'ace-the-catch' ) );
			$this->redirect_to_order_with_notice( $order_id, 'refund_failed' );
		}

		$total_amount = (float) \get_post_meta( $order_id, self::META_ORDER_TOTAL, true );
		$currency     = (string) \get_post_meta( $order_id, self::META_ORDER_CURRENCY, true );
		$order_number = (int) \get_post_meta( $order_id, self::META_ORDER_NUMBER, true );

		$config = $this->get_payment_processor_config( $processor_key );
		$refund_result = $processor->refund_payment(
			array(
				'order_id'     => $order_id,
				'order_number' => $order_number,
				'amount'       => $total_amount,
				'currency'     => $currency,
				'reference'    => $payment_reference,
			),
			$config
		);

		$refund_status = isset( $refund_result['status'] ) ? (string) $refund_result['status'] : 'failed';
		$refund_ref    = isset( $refund_result['reference'] ) ? (string) $refund_result['reference'] : '';
		$refund_error  = isset( $refund_result['error'] ) ? (string) $refund_result['error'] : \__( 'Refund failed.', 'ace-the-catch' );

		$user = \wp_get_current_user();
		$user_label = ( $user instanceof \WP_User && $user->exists() )
			? trim( (string) ( $user->display_name ?: $user->user_login ) ) . ' (ID ' . (string) $user->ID . ')'
			: \__( 'Unknown user', 'ace-the-catch' );

		if ( 'succeeded' !== $refund_status && 'pending' !== $refund_status ) {
			$this->append_log(
				$order_id,
				\sprintf(
					/* translators: 1: user label, 2: refund error */
					\__( 'Refund failed by %1$s: %2$s', 'ace-the-catch' ),
					$user_label,
					$refund_error
				)
			);
			$this->redirect_to_order_with_notice( $order_id, 'refund_failed' );
		}

		$cancelled_at = \current_time( 'mysql' );
		$cancelled_count = $this->cancel_tickets_for_order( $order_id, $cancelled_at );

		\update_post_meta( $order_id, self::META_TICKET_STATUS, CatchTheAceTickets::STATUS_CANCELLED );
		$this->set_order_status( $order_id, self::STATUS_REFUNDED );

		$this->append_log(
			$order_id,
			\sprintf(
				/* translators: 1: user label, 2: refund reference, 3: ticket count */
				\__( 'Order refunded by %1$s (refund ref: %2$s). Tickets cancelled: %3$d.', 'ace-the-catch' ),
				$user_label,
				$refund_ref ? $refund_ref : '-',
				$cancelled_count
			),
			array(
				'refund_status' => $refund_status,
				'refund_ref'    => $refund_ref,
				'processor'     => $processor_key,
				'cancelled_at'  => $cancelled_at,
				'cancelled_tickets' => $cancelled_count,
			)
		);

		Plugin::instance()->get_emails()->send_refund_email( $order_id, $refund_ref, $refund_status );

		$this->redirect_to_order_with_notice( $order_id, 'refund_succeeded' );
	}

	/**
	 * Get payment processor config array for a given provider key.
	 *
	 * @param string $processor_key Provider key.
	 * @return array
	 */
	private function get_payment_processor_config( string $processor_key ): array {
		$configs = \get_option( CatchTheAceSettings::OPTION_PAYMENT_PROC_CFG, array() );
		if ( ! \is_array( $configs ) || empty( $configs[ $processor_key ] ) || ! \is_array( $configs[ $processor_key ] ) ) {
			return array();
		}

		return $configs[ $processor_key ];
	}

	/**
	 * Cancel all tickets for an order by setting cancelled_at.
	 *
	 * @param int    $order_id Order post ID.
	 * @param string $cancelled_at MySQL datetime string.
	 * @return int Number of tickets cancelled.
	 */
	private function cancel_tickets_for_order( int $order_id, string $cancelled_at ): int {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return 0;
		}

		$table = CatchTheAceTickets::get_table_name();
		$affected = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET cancelled_at = %s
				WHERE order_id = %d
					AND (cancelled_at IS NULL OR cancelled_at = '')",
				$cancelled_at,
				$order_id
			)
		);

		return \is_int( $affected ) && $affected > 0 ? $affected : 0;
	}

	/**
	 * Redirect back to the order edit screen with an inline notice key.
	 *
	 * @param int    $order_id Order post ID.
	 * @param string $notice_key Notice key.
	 * @return void
	 */
	private function redirect_to_order_with_notice( int $order_id, string $notice_key ): void {
		$redirect = \wp_get_referer();
		if ( ! $redirect ) {
			$redirect = \admin_url( 'post.php?post=' . $order_id . '&action=edit' );
		}

		$redirect = \remove_query_arg( array( 'cta_order_notice' ), $redirect );
		$redirect = \add_query_arg( 'cta_order_notice', $notice_key, $redirect );

		\wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render order details and log meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_order_details_meta_box( \WP_Post $post ): void {
		$order_number = (int) \get_post_meta( $post->ID, self::META_ORDER_NUMBER, true );
		$session_id   = (int) \get_post_meta( $post->ID, self::META_ORDER_SESSION, true );
		$total        = (float) \get_post_meta( $post->ID, self::META_ORDER_TOTAL, true );
		$currency     = (string) \get_post_meta( $post->ID, self::META_ORDER_CURRENCY, true );
		$reference    = (string) \get_post_meta( $post->ID, self::META_ORDER_PAYMENT_REFERENCE, true );
		$first_name   = (string) \get_post_meta( $post->ID, self::META_ORDER_CUSTOMER_FIRST_NAME, true );
		$last_name    = (string) \get_post_meta( $post->ID, self::META_ORDER_CUSTOMER_LAST_NAME, true );
		$email        = (string) \get_post_meta( $post->ID, self::META_ORDER_CUSTOMER_EMAIL, true );
		$phone        = (string) \get_post_meta( $post->ID, self::META_ORDER_CUSTOMER_PHONE, true );
		$location     = (string) \get_post_meta( $post->ID, self::META_ORDER_CUSTOMER_LOCATION, true );
		$benefactor_term_id = (int) \get_post_meta( $post->ID, self::META_ORDER_BENEFACTOR_TERM_ID, true );
		$benefactor_label   = (string) \get_post_meta( $post->ID, self::META_ORDER_BENEFACTOR_LABEL, true );
		$terms_accepted_at  = (string) \get_post_meta( $post->ID, self::META_ORDER_TERMS_ACCEPTED_AT, true );
		$terms_url          = (string) \get_post_meta( $post->ID, self::META_ORDER_TERMS_URL, true );
		$rules_url          = (string) \get_post_meta( $post->ID, self::META_ORDER_RULES_URL, true );
		$ticket_status = (string) \get_post_meta( $post->ID, self::META_TICKET_STATUS, true );
		$cart         = \get_post_meta( $post->ID, self::META_ORDER_CART, true );

		$statuses = $this->get_statuses();
		$status_label = isset( $statuses[ $post->post_status ] ) ? $statuses[ $post->post_status ] : $post->post_status;

		echo '<table class="widefat striped" style="max-width: 900px">';
		echo '<tbody>';
		echo '<tr><th style="width:220px">' . \esc_html__( 'Order Number', 'ace-the-catch' ) . '</th><td>' . \esc_html( $order_number ? (string) $order_number : '-' ) . '</td></tr>';
		echo '<tr><th>' . \esc_html__( 'Status', 'ace-the-catch' ) . '</th><td>' . \esc_html( $status_label ) . '</td></tr>';
		echo '<tr><th>' . \esc_html__( 'Session', 'ace-the-catch' ) . '</th><td>';
		if ( $session_id ) {
			$session_link = \get_edit_post_link( $session_id );
			$session_title = \get_the_title( $session_id );
			if ( $session_link ) {
				echo '<a href="' . \esc_url( $session_link ) . '">' . \esc_html( $session_title ? $session_title : (string) $session_id ) . '</a>';
			} else {
				echo \esc_html( $session_title ? $session_title : (string) $session_id );
			}
		} else {
			echo '-';
		}
		echo '</td></tr>';
		echo '<tr><th>' . \esc_html__( 'Customer', 'ace-the-catch' ) . '</th><td>' . \esc_html( \trim( $first_name . ' ' . $last_name ) ) . ( $email ? ' &lt;' . \esc_html( $email ) . '&gt;' : '' ) . '</td></tr>';
		echo '<tr><th>' . \esc_html__( 'Telephone', 'ace-the-catch' ) . '</th><td>' . ( $phone ? \esc_html( $phone ) : '-' ) . '</td></tr>';
		echo '<tr><th>' . \esc_html__( 'Location', 'ace-the-catch' ) . '</th><td>' . ( $location ? \esc_html( $location ) : '-' ) . '</td></tr>';
		echo '<tr><th>' . \esc_html__( 'Benefactor', 'ace-the-catch' ) . '</th><td>';
		if ( $benefactor_term_id > 0 ) {
			echo \esc_html( $benefactor_label ? $benefactor_label : (string) $benefactor_term_id );
		} else {
			echo \esc_html__( 'All charities', 'ace-the-catch' );
		}
		echo '</td></tr>';
		echo '<tr><th>' . \esc_html__( 'Terms Accepted', 'ace-the-catch' ) . '</th><td>';
		if ( $terms_accepted_at ) {
			echo \esc_html( $terms_accepted_at );
			$links = array();
			if ( $terms_url ) {
				$links[] = '<a href="' . \esc_url( $terms_url ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'View terms', 'ace-the-catch' ) . '</a>';
			}
			if ( $rules_url ) {
				$links[] = '<a href="' . \esc_url( $rules_url ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html__( 'View rules of play', 'ace-the-catch' ) . '</a>';
			}
			if ( ! empty( $links ) ) {
				echo ' &mdash; ' . \implode( ' | ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		} else {
			echo '-';
		}
		echo '</td></tr>';
		echo '<tr><th>' . \esc_html__( 'Total', 'ace-the-catch' ) . '</th><td>' . \esc_html( $total ? '$' . \number_format_i18n( $total, 2 ) : '-' ) . ( $currency ? ' ' . \esc_html( \strtoupper( $currency ) ) : '' ) . '</td></tr>';
		echo '<tr><th>' . \esc_html__( 'Payment Reference', 'ace-the-catch' ) . '</th><td>' . ( $reference ? \esc_html( $reference ) : '-' ) . '</td></tr>';
		echo '<tr><th>' . \esc_html__( 'Ticket Status', 'ace-the-catch' ) . '</th><td>' . \esc_html( $this->format_ticket_status( $ticket_status ) ) . '</td></tr>';
		echo '</tbody>';
		echo '</table>';

		echo '<h3 style="margin-top:16px">' . \esc_html__( 'Order Contents', 'ace-the-catch' ) . '</h3>';

		if ( ! \is_array( $cart ) || empty( $cart ) ) {
			echo '<p class="description">' . \esc_html__( 'No items in this order.', 'ace-the-catch' ) . '</p>';
		} else {
			\ksort( $cart );
			echo '<table class="widefat striped" style="max-width: 900px">';
			echo '<thead><tr><th style="width:220px">' . \esc_html__( 'Envelope', 'ace-the-catch' ) . '</th><th>' . \esc_html__( 'Quantity', 'ace-the-catch' ) . '</th></tr></thead>';
			echo '<tbody>';
			foreach ( $cart as $env => $qty ) {
				$env = (int) $env;
				$qty = (int) $qty;
				if ( $env <= 0 || $qty <= 0 ) {
					continue;
				}
				echo '<tr><td>' . \esc_html( '#' . (string) $env ) . '</td><td>' . \esc_html( (string) $qty ) . '</td></tr>';
			}
			echo '</tbody>';
			echo '</table>';
		}
	}

	/**
	 * Add custom admin list columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function filter_admin_columns( array $columns ): array {
		$new = array();

		if ( isset( $columns['cb'] ) ) {
			$new['cb'] = $columns['cb'];
		}

		$new['cta_order_number'] = \__( 'Order #', 'ace-the-catch' );
		$new['title']            = $columns['title'] ?? \__( 'Title', 'ace-the-catch' );
		$new['cta_order_status'] = \__( 'Status', 'ace-the-catch' );
		$new['cta_order_total']  = \__( 'Total', 'ace-the-catch' );
		$new['date']             = $columns['date'] ?? \__( 'Date', 'ace-the-catch' );

		return $new;
	}

	/**
	 * Register sortable columns for the order admin list.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function filter_sortable_columns( array $columns ): array {
		$columns['cta_order_status'] = 'cta_order_status';
		$columns['cta_order_number'] = 'cta_order_number';
		return $columns;
	}

	/**
	 * Adjust the Orders list table query for sorting and search.
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function maybe_adjust_admin_list_query( \WP_Query $query ): void {
		if ( ! \is_admin() || ! $query->is_main_query() ) {
			return;
		}

		global $pagenow;
		if ( 'edit.php' !== ( $pagenow ?? '' ) ) {
			return;
		}

		$screen = \function_exists( 'get_current_screen' ) ? \get_current_screen() : null;
		if ( $screen ) {
			if ( self::POST_TYPE !== ( $screen->post_type ?? '' ) ) {
				return;
			}
			$query->set( 'post_type', self::POST_TYPE );
		} else {
			$post_type = $query->get( 'post_type' );
			if ( \is_array( $post_type ) ) {
				$post_type = reset( $post_type );
			}
			if ( self::POST_TYPE !== $post_type ) {
				return;
			}
		}

		$post_status = $query->get( 'post_status' );
		if ( empty( $post_status ) || 'any' === $post_status || 'all' === $post_status ) {
			$query->set( 'post_status', array_keys( $this->get_statuses() ) );
		}

		$orderby = (string) $query->get( 'orderby' );

		// Sort by order number (meta).
		if ( 'cta_order_number' === $orderby ) {
			$query->set( 'meta_key', self::META_ORDER_NUMBER );
			$query->set( 'orderby', 'meta_value_num' );
		}

		// Search by order number (exact match) when the search term is numeric (e.g. "123" or "Order #123").
		if ( $query->is_search() ) {
			$search = (string) $query->get( 's' );
			if ( preg_match( '/^\s*(?:order\s*)?#?\s*(\d+)\s*$/i', $search, $matches ) ) {
				$order_number = (int) $matches[1];
				if ( $order_number > 0 ) {
					$order_id = $this->lookup_order_id_by_number( $order_number );
					if ( $order_id > 0 ) {
						$query->set( 's', '' );
						$query->set( 'post__in', array( $order_id ) );
					}
				}
			}
		}
	}

	/**
	 * Override ORDER BY clause for sorting by our custom status column.
	 *
	 * @param string   $orderby SQL ORDER BY clause.
	 * @param \WP_Query $query Query.
	 * @return string
	 */
	public function maybe_override_admin_orderby( string $orderby, \WP_Query $query ): string {
		if ( ! \is_admin() || ! $query->is_main_query() ) {
			return $orderby;
		}

		global $pagenow;
		if ( 'edit.php' !== ( $pagenow ?? '' ) ) {
			return $orderby;
		}

		$post_type = $query->get( 'post_type' );
		if ( self::POST_TYPE !== $post_type ) {
			return $orderby;
		}

		if ( 'cta_order_status' !== (string) $query->get( 'orderby' ) ) {
			return $orderby;
		}

		global $wpdb;
		$order = strtoupper( (string) $query->get( 'order' ) );
		$order = ( 'ASC' === $order ) ? 'ASC' : 'DESC';

		return "{$wpdb->posts}.post_status {$order}, {$wpdb->posts}.post_date DESC";
	}

	/**
	 * Render custom admin list column values.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_admin_column( string $column, int $post_id ): void {
		if ( 'cta_order_number' === $column ) {
			$order_number = (int) \get_post_meta( $post_id, self::META_ORDER_NUMBER, true );
			echo $order_number ? \esc_html( (string) $order_number ) : '-';
			return;
		}

		if ( 'cta_order_status' === $column ) {
			$post = \get_post( $post_id );
			if ( ! $post ) {
				echo '-';
				return;
			}
			$statuses = $this->get_statuses();
			echo isset( $statuses[ $post->post_status ] ) ? \esc_html( $statuses[ $post->post_status ] ) : \esc_html( $post->post_status );
			return;
		}

		if ( 'cta_order_total' === $column ) {
			$total    = (float) \get_post_meta( $post_id, self::META_ORDER_TOTAL, true );
			$currency = (string) \get_post_meta( $post_id, self::META_ORDER_CURRENCY, true );
			if ( $total <= 0 ) {
				echo '-';
				return;
			}
			$display = '$' . \number_format_i18n( $total, 2 );
			if ( $currency ) {
				$display .= ' ' . \strtoupper( $currency );
			}
			echo \esc_html( $display );
			return;
		}
	}

	/**
	 * Inject our custom statuses into the core status dropdown on the order edit screen.
	 *
	 * @return void
	 */
	public function print_status_dropdown_script(): void {
		$screen = \function_exists( 'get_current_screen' ) ? \get_current_screen() : null;
		if ( ! $screen || self::POST_TYPE !== ( $screen->post_type ?? '' ) ) {
			return;
		}

		$statuses = $this->get_statuses();
		$json = \wp_json_encode( $statuses );
		if ( false === $json ) {
			return;
		}

		echo '<script>
			(function($){
				var statuses = ' . $json . ';
				var $select = $("#post_status");
				if (!$select.length) { return; }
				Object.keys(statuses).forEach(function(key){
					if ($select.find("option[value=\'"+key+"\']").length) { return; }
					$select.append($("<option/>").val(key).text(statuses[key]));
				});
				var current = $select.val();
				if (statuses[current]) {
					$("#post-status-display").text(statuses[current]);
				}
			})(jQuery);
		</script>';
	}

	/**
	 * Human-readable ticket status display.
	 *
	 * @param string $status Status value.
	 * @return string
	 */
	private function format_ticket_status( string $status ): string {
		$map = array(
			CatchTheAceTickets::STATUS_NOT_GENERATED => \__( 'Not generated', 'ace-the-catch' ),
			CatchTheAceTickets::STATUS_GENERATE      => \__( 'Generate', 'ace-the-catch' ),
			CatchTheAceTickets::STATUS_IN_PROCESS    => \__( 'In process', 'ace-the-catch' ),
			CatchTheAceTickets::STATUS_GENERATED     => \__( 'Generated', 'ace-the-catch' ),
			CatchTheAceTickets::STATUS_CANCELLED     => \__( 'Cancelled', 'ace-the-catch' ),
			'not generated' => \__( 'Not generated', 'ace-the-catch' ),
		);

		if ( '' === $status ) {
			$status = CatchTheAceTickets::STATUS_NOT_GENERATED;
		}

		return $map[ $status ] ?? $status;
	}
}
