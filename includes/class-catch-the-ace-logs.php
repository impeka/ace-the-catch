<?php
/**
 * Admin page: logs (queue status + error logs).
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class CatchTheAceLogs {

	public const MENU_SLUG = 'catch-the-ace-logs';

	private const TAB_QUEUE_STATUS = 'queue-status';
	private const TAB_ERROR_LOGS   = 'error-logs';

	public function __construct() {
		\add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register submenu under the Catch the Ace CPT.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		\add_submenu_page(
			'edit.php?post_type=catch-the-ace',
			\__( 'Catch the Ace Logs', 'ace-the-catch' ),
			\__( 'Logs', 'ace-the-catch' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the logs page with tabs.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs        = $this->get_tabs();
		$current_tab = $this->get_current_tab();
		$base_url    = \admin_url( 'edit.php?post_type=catch-the-ace&page=' . self::MENU_SLUG );

		?>
		<div class="wrap">
			<h1><?php \esc_html_e( 'Catch the Ace Logs', 'ace-the-catch' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<?php
					$url = \add_query_arg( 'tab', $tab_key, $base_url );
					$classes = 'nav-tab' . ( $current_tab === $tab_key ? ' nav-tab-active' : '' );
					?>
					<a href="<?php echo \esc_url( $url ); ?>" class="<?php echo \esc_attr( $classes ); ?>"><?php echo \esc_html( $tab_label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<?php if ( self::TAB_ERROR_LOGS === $current_tab ) : ?>
				<?php $this->render_error_logs_tab( $base_url ); ?>
			<?php else : ?>
				<?php $this->render_queue_status_tab(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Tab labels.
	 *
	 * @return array<string,string>
	 */
	private function get_tabs(): array {
		return array(
			self::TAB_QUEUE_STATUS => \__( 'Queue Status', 'ace-the-catch' ),
			self::TAB_ERROR_LOGS   => \__( 'Error Logs', 'ace-the-catch' ),
		);
	}

	/**
	 * Get current tab.
	 *
	 * @return string
	 */
	private function get_current_tab(): string {
		$tab  = isset( $_GET['tab'] ) ? \sanitize_key( (string) \wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = \array_keys( $this->get_tabs() );
		return \in_array( $tab, $tabs, true ) ? $tab : self::TAB_QUEUE_STATUS;
	}

	/**
	 * Render Queue Status tab.
	 *
	 * @return void
	 */
	private function render_queue_status_tab(): void {
		$pending    = $this->count_completed_orders_with_ticket_status( array( CatchTheAceTickets::STATUS_NOT_GENERATED, CatchTheAceTickets::STATUS_GENERATE, 'not generated' ), true );
		$in_process = $this->count_completed_orders_with_ticket_status( array( CatchTheAceTickets::STATUS_IN_PROCESS ), false );
		$generated  = $this->count_completed_orders_with_ticket_status( array( CatchTheAceTickets::STATUS_GENERATED ), false );

		$next_ticket_run = \wp_next_scheduled( CatchTheAceTickets::CRON_HOOK_GENERATE_TICKETS );
		$last_ticket_run = (string) \get_option( CatchTheAceTickets::OPTION_LAST_RUN, '' );
		?>
		<h2><?php \esc_html_e( 'Ticket Generation', 'ace-the-catch' ); ?></h2>
		<table class="widefat striped" style="max-width: 820px;">
			<tbody>
				<tr>
					<th style="width: 260px;"><?php \esc_html_e( 'Pending Orders', 'ace-the-catch' ); ?></th>
					<td><?php echo \esc_html( (string) $pending ); ?></td>
				</tr>
				<tr>
					<th><?php \esc_html_e( 'Orders In Process', 'ace-the-catch' ); ?></th>
					<td><?php echo \esc_html( (string) $in_process ); ?></td>
				</tr>
				<tr>
					<th><?php \esc_html_e( 'Generated Orders', 'ace-the-catch' ); ?></th>
					<td><?php echo \esc_html( (string) $generated ); ?></td>
				</tr>
				<tr>
					<th><?php \esc_html_e( 'Last Run', 'ace-the-catch' ); ?></th>
					<td><?php echo $last_ticket_run ? \esc_html( $last_ticket_run ) : \esc_html__( 'Unknown', 'ace-the-catch' ); ?></td>
				</tr>
				<tr>
					<th><?php \esc_html_e( 'Next Scheduled Run', 'ace-the-catch' ); ?></th>
					<td>
						<?php
						if ( $next_ticket_run ) {
							echo \esc_html( \wp_date( 'Y-m-d H:i:s', (int) $next_ticket_run ) );
						} else {
							\esc_html_e( 'Not scheduled', 'ace-the-catch' );
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render Error Logs tab.
	 *
	 * @param string $base_url Base admin URL for pagination links.
	 * @return void
	 */
	private function render_error_logs_tab( string $base_url ): void {
		$logs = Plugin::instance()->get_error_logs();

		$paged = isset( $_GET['paged'] ) ? (int) \wp_unslash( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = $paged > 0 ? $paged : 1;

		$per_page = 50;
		$total    = $logs->count_logs();
		$pages    = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
		$offset   = ( $paged - 1 ) * $per_page;

		$rows = $logs->get_logs( $per_page, $offset );

		echo '<p class="description">' . \esc_html__( 'Recent MySQL and email delivery errors recorded by Catch the Ace.', 'ace-the-catch' ) . '</p>';

		if ( empty( $rows ) ) {
			echo '<p>' . \esc_html__( 'No error logs found.', 'ace-the-catch' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped" style="max-width: 1100px;">';
		echo '<thead><tr>';
		echo '<th style="width:170px">' . \esc_html__( 'Date', 'ace-the-catch' ) . '</th>';
		echo '<th style="width:90px">' . \esc_html__( 'Type', 'ace-the-catch' ) . '</th>';
		echo '<th style="width:110px">' . \esc_html__( 'Source', 'ace-the-catch' ) . '</th>';
		echo '<th style="width:140px">' . \esc_html__( 'Order', 'ace-the-catch' ) . '</th>';
		echo '<th>' . \esc_html__( 'Message', 'ace-the-catch' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			if ( ! \is_array( $row ) ) {
				continue;
			}

			$created_at = isset( $row['created_at'] ) ? (string) $row['created_at'] : '';
			$type       = isset( $row['error_type'] ) ? (string) $row['error_type'] : '';
			$source     = isset( $row['source'] ) ? (string) $row['source'] : '';
			$order_id   = isset( $row['order_id'] ) ? (int) $row['order_id'] : 0;
			$message    = isset( $row['message'] ) ? (string) $row['message'] : '';
			$context    = isset( $row['context'] ) ? (string) $row['context'] : '';

			$order_label = '-';
			if ( $order_id > 0 ) {
				$order_number = (int) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_NUMBER, true );
				$order_label = $order_number > 0 ? \sprintf( \__( 'Order #%d', 'ace-the-catch' ), $order_number ) : \sprintf( \__( 'Order (%d)', 'ace-the-catch' ), $order_id );
				$link = \get_edit_post_link( $order_id );
				if ( $link ) {
					$order_label = '<a href="' . \esc_url( $link ) . '">' . \esc_html( $order_label ) . '</a>';
				} else {
					$order_label = \esc_html( $order_label );
				}
			}

			$message_html = \esc_html( $message );
			if ( $context ) {
				$decoded = \json_decode( $context, true );
				$pretty = $decoded ? \wp_json_encode( $decoded, JSON_PRETTY_PRINT ) : $context;
				$message_html .= '<details style="margin-top:6px;"><summary>' . \esc_html__( 'Context', 'ace-the-catch' ) . '</summary><pre style="white-space:pre-wrap;margin:8px 0 0;">' . \esc_html( (string) $pretty ) . '</pre></details>';
			}

			echo '<tr>';
			echo '<td>' . \esc_html( $created_at ) . '</td>';
			echo '<td>' . \esc_html( $type ) . '</td>';
			echo '<td>' . \esc_html( $source ) . '</td>';
			echo '<td>' . $order_label . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<td>' . $message_html . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</tr>';
		}

		echo '</tbody></table>';

		if ( $pages > 1 ) {
			$paginate_base = \add_query_arg(
				array(
					'tab'   => self::TAB_ERROR_LOGS,
					'paged' => '%#%',
				),
				$base_url
			);

			$links = \paginate_links(
				array(
					'base'      => $paginate_base,
					'format'    => '',
					'current'   => $paged,
					'total'     => $pages,
					'prev_text' => \__( '&laquo; Previous', 'ace-the-catch' ),
					'next_text' => \__( 'Next &raquo;', 'ace-the-catch' ),
					'type'      => 'plain',
				)
			);

			if ( $links ) {
				echo '<div class="tablenav"><div class="tablenav-pages" style="margin:12px 0;">' . \wp_kses_post( $links ) . '</div></div>';
			}
		}
	}

	/**
	 * Count completed orders by ticket status.
	 *
	 * @param string[] $meta_values Allowed values.
	 * @param bool     $include_missing Whether to include orders with no meta row.
	 * @return int
	 */
	private function count_completed_orders_with_ticket_status( array $meta_values, bool $include_missing ): int {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return 0;
		}

		$posts    = $wpdb->posts;
		$postmeta = $wpdb->postmeta;
		$key      = CatchTheAceOrders::META_TICKET_STATUS;

		$values = array_values( array_filter( array_map( 'strval', $meta_values ) ) );
		if ( empty( $values ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );
		$where_values = $values;

		$where = "pm.meta_value IN ({$placeholders})";
		if ( $include_missing ) {
			$where = "(pm.meta_id IS NULL OR {$where})";
		}

		$sql = $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID)
			FROM {$posts} p
			LEFT JOIN {$postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = %s
			WHERE p.post_type = %s
				AND p.post_status = %s
				AND {$where}",
			array_merge(
				array(
					$key,
					CatchTheAceOrders::POST_TYPE,
					CatchTheAceOrders::STATUS_COMPLETED,
				),
				$where_values
			)
		);

		return (int) $wpdb->get_var( $sql );
	}
}
