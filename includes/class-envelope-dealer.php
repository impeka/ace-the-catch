<?php
/**
 * Envelope shortcode handler.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Catch the Ace envelope grid.
 */
class EnvelopeDealer {

	const SHORTCODE = 'catch_the_ace';

	/**
	 * Card filenames (without extension). Jokers excluded to keep a 52-card deck.
	 *
	 * @var string[]
	 */
	private array $cards = array(
		'club-1',
		'club-2',
		'club-3',
		'club-4',
		'club-5',
		'club-6',
		'club-7',
		'club-8',
		'club-9',
		'club-10',
		'club-11-jack',
		'club-12-queen',
		'club-13-king',
		'diamond-1',
		'diamond-2',
		'diamond-3',
		'diamond-4',
		'diamond-5',
		'diamond-6',
		'diamond-7',
		'diamond-8',
		'diamond-9',
		'diamond-10',
		'diamond-11-jack',
		'diamond-12-queen',
		'diamond-13-king',
		'heart-1',
		'heart-2',
		'heart-3',
		'heart-4',
		'heart-5',
		'heart-6',
		'heart-7',
		'heart-8',
		'heart-9',
		'heart-10',
		'heart-11-jack',
		'heart-12-queen',
		'heart-13-king',
		'spade-1',
		'spade-2',
		'spade-3',
		'spade-4',
		'spade-5',
		'spade-6',
		'spade-7',
		'spade-8',
		'spade-9',
		'spade-10',
		'spade-11-jack',
		'spade-12-queen',
		'spade-13-king',
	);

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		\add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Render the envelope grid.
	 *
	 * @param array       $atts Shortcode attributes.
	 * @param string|null $content Unused shortcode content.
	 * @param string      $tag Shortcode tag name.
	 * @return string
	 */
	public function render( array $atts = array(), ?string $content = null, string $tag = '' ): string {
		$atts = \shortcode_atts(
			array(
				'id' => '',
			),
			$atts,
			$tag ?: self::SHORTCODE
		);

		$post_id = (int) $atts['id'];
		if ( $post_id <= 0 ) {
			return '';
		}

		$post = \get_post( $post_id );
		if ( ! $post || 'catch-the-ace' !== $post->post_type ) {
			return '';
		}

		return $this->render_for_post( $post_id );
	}

	/**
	 * Render envelopes for a given Catch the Ace session post.
	 *
	 * @param int $post_id Session post ID.
	 * @return string
	 */
	public function render_for_post( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}

		$card_map      = $this->get_card_map( $post_id );
		$ticket_price  = \floatval( \get_option( CatchTheAceSettings::OPTION_TICKET_PRICE, 0 ) );
		$sales_status  = $this->get_sales_status( $post_id );
		$geo_status    = $this->evaluate_geo_access();
		$geo_blocked   = $geo_status['blocked'];
		$geo_message   = $geo_status['message'];

		if ( $geo_blocked ) {
			$sales_status['open']        = false;
			$sales_status['close_epoch'] = 0;
			$sales_status['open_epoch']  = 0;
			$sales_status['message']     = $geo_message;
		}

		$draws_count = $this->get_draws_count( $post_id );
		$permalink   = \get_permalink( $post_id );
		$winners_url = \trailingslashit( $permalink . 'winners' );
		$next_draw   = $this->get_next_draw_datetime( $post_id );

		$cart_markup = '
			<div class="ace-cart" id="ace-cart" hidden data-ticket-price="' . \esc_attr( $ticket_price ) . '">
				<div class="ace-cart__header">Cart</div>
				<table class="ace-cart__table">
					<thead>
						<tr>
							<th>' . \esc_html__( 'Envelope', 'ace-the-catch' ) . '</th>
							<th>' . \esc_html__( 'Entries', 'ace-the-catch' ) . '</th>
							<th class="ace-cart__th--numeric">' . \esc_html__( 'Subtotal', 'ace-the-catch' ) . '</th>
							<th class="ace-cart__th--icon"></th>
						</tr>
					</thead>
					<tbody class="ace-cart__body"></tbody>
					<tfoot class="ace-cart__foot" hidden>
						<tr>
							<td colspan="3" class="ace-cart__total-label">' . \esc_html__( 'Total', 'ace-the-catch' ) . '</td>
							<td class="ace-cart__total-value"></td>
						</tr>
					</tfoot>
				</table>
			</div>';

		$draw_countdown_markup = '';
		if ( $sales_status['open'] && $next_draw ) {
			$draw_countdown_markup = '<div class="ace-countdown card-table-header__countdown" data-countdown-iso="' . \esc_attr( $next_draw->format( 'c' ) ) . '" data-countdown-target="#ace-countdown-' . \esc_attr( $post_id ) . '">
				<div class="card-table-header__countdown-label">' . \esc_html__( 'Next draw in:', 'ace-the-catch' ) . '</div>
				<div class="card-table-header__countdown-timer simply-countdown" id="ace-countdown-' . \esc_attr( $post_id ) . '"></div>
			</div>';
		}

		$sales_open_countdown_markup = '';
		if ( ! $sales_status['open'] && ! empty( $sales_status['open_epoch'] ) ) {
			$open_dt = ( new \DateTimeImmutable() )->setTimestamp( (int) $sales_status['open_epoch'] );
			$sales_open_countdown_markup = '<div class="ace-countdown card-table-header__countdown card-table-header__countdown--sales" data-countdown-iso="' . \esc_attr( $open_dt->format( 'c' ) ) . '" data-countdown-target="#ace-sales-open-countdown-' . \esc_attr( $post_id ) . '">
				<div class="card-table-header__countdown-label">' . \esc_html__( 'Ticket sales open in:', 'ace-the-catch' ) . '</div>
				<div class="card-table-header__countdown-timer simply-countdown" id="ace-sales-open-countdown-' . \esc_attr( $post_id ) . '"></div>
			</div>';
		}

		$header_info = '';
		if ( $sales_status['open'] ) {
			$header_info = '<div class="card-table-header__cta">' . \esc_html__( 'Play now by selecting your cards!', 'ace-the-catch' ) . '</div>' . $draw_countdown_markup;
		} elseif ( $sales_open_countdown_markup ) {
			$header_info = '<div class="card-table-header__cta">' . \esc_html__( 'Ticket sales are currently closed.', 'ace-the-catch' ) . '</div>' . $sales_open_countdown_markup;
		} else {
			$header_info = '<div class="card-table-header__cta">' . \esc_html( $sales_status['message'] ) . '</div>';
		}

		$actions = '<div class="card-table-actions">
				<a class="card-table-header__btn card-table-header__btn--ghost" href="#" aria-label="' . \esc_attr__( 'View Rules', 'ace-the-catch' ) . '">' . \esc_html__( 'Rules', 'ace-the-catch' ) . '</a>
				<a class="card-table-header__btn card-table-header__btn--ghost" href="' . \esc_url( $winners_url ) . '" aria-label="' . \esc_attr__( 'View Past Draws', 'ace-the-catch' ) . '">' . \esc_html__( 'Past Draws', 'ace-the-catch' ) . '</a>
				<a class="card-table-header__btn card-table-header__btn--ghost" href="#" aria-label="' . \esc_attr__( 'View FAQs', 'ace-the-catch' ) . '">' . \esc_html__( 'FAQs', 'ace-the-catch' ) . '</a>
			</div>';

		$card_header = '<div class="card-table-header">
			<div class="card-table-header__week"><span class="__inner">' . \sprintf( \esc_html__( 'Week %d', 'ace-the-catch' ), ( $draws_count + 1 ) ) . '</span></div>
			<div class="card-table-header__content">
				<div class="card-table-header__info">
					' . $header_info . '
				</div>
			</div>
		</div>';

		$wrap_classes = 'card-table-wrap';
		if ( ! $sales_status['open'] ) {
			$wrap_classes .= ' card-table-wrap--closed';
		}

		return '<div class="' . $wrap_classes . '" data-sales-open="' . ( $sales_status['open'] ? '1' : '0' ) . '" data-sales-message="' . \esc_attr( $sales_status['message'] ) . '" data-sales-close-epoch="' . \esc_attr( $sales_status['close_epoch'] ) . '" data-geo-block="' . ( $geo_blocked ? '1' : '0' ) . '" data-geo-message="' . \esc_attr( $geo_message ) . '">' . $actions . '<div class="card-table">' . $card_header . $this->build_envelopes( $card_map, $sales_status['open'] ) . '</div>' . $cart_markup . '</div>';
	}

	/**
	 * Generate the envelope markup.
	 *
	 * @param array<int,string> $card_map Map of envelope number to card slug.
	 * @param bool              $sales_open Whether selection is allowed.
	 * @return string
	 */
	private function build_envelopes( array $card_map, bool $sales_open ): string {
		$items = array();

		for ( $index = 1; $index <= 52; $index++ ) {
			$card      = $card_map[ $index ] ?? '';
			$card_attr = $card ? ' data-card="' . \esc_attr( $card ) . '"' : sprintf( ' tabindex="0" role="button" aria-label="%s"', sprintf( __( 'Envelope #%s', 'ace-the-catch' ), $index ) );
			$classes   = 'envelope' . ( $card ? ' has-card' : '' );

			// Render the disabled state immediately when sales are closed to prevent a brief active flash.
			if ( ! $sales_open ) {
				$classes .= ' envelope--disabled';
			}

			$flip_order = ( 'spade-1' === $card ) ? 53 : $index; // Ensure ace of spades flips last without delaying its intro.
			$style      = '--order:' . $index . ';--flip-order:' . $flip_order;

			$items[] = '<div class="' . $classes . '" data-envelope="' . $index . '" style="' . $style . '"' . $card_attr . '>
				<div class="__card">
					<div class="__back" data-number="' . \esc_attr( $index ) . '"></div>
					<div class="__front"></div>
				</div>
			</div>';
		}

		return \implode( '', $items );
	}

	/**
	 * Determine sales window status and message.
	 *
	 * @param int $post_id Session post ID.
	 * @return array{open:bool,message:string,close_epoch:int,open_epoch:int}
	 */
	private function get_sales_status( int $post_id ): array {
		$default = array(
			'open'        => false,
			'message'     => \__( 'Ticket sales are currently closed.', 'ace-the-catch' ),
			'close_epoch' => 0,
			'open_epoch'  => 0,
		);

		if ( ! \function_exists( 'get_field' ) ) {
			return $default;
		}

		$open_group  = \get_field( 'ticket_sales_open_on', $post_id );
		$close_group = \get_field( 'ticket_sales_end_on', $post_id );

		$open_day   = $open_group['day'] ?? '';
		$open_time  = $open_group['time'] ?? '';
		$close_day  = $close_group['day'] ?? '';
		$close_time = $close_group['time'] ?? '';

		$day_map = array(
			'Sunday'    => 0,
			'Monday'    => 1,
			'Tuesday'   => 2,
			'Wednesday' => 3,
			'Thursday'  => 4,
			'Friday'    => 5,
			'Saturday'  => 6,
		);

		if ( ! isset( $day_map[ $open_day ], $day_map[ $close_day ] ) || empty( $open_time ) || empty( $close_time ) ) {
			return $default;
		}

		$tz         = \wp_timezone();
		$now        = new \DateTimeImmutable( 'now', $tz );
		$now_day    = (int) $now->format( 'w' ); // 0 (Sunday) - 6 (Saturday).
		$week_start = $now->modify( '-' . $now_day . ' days' )->setTime( 0, 0, 0 );

		list( $open_hour, $open_min )   = $this->split_time( $open_time );
		list( $close_hour, $close_min ) = $this->split_time( $close_time );

		$open_dt  = $week_start->modify( '+' . $day_map[ $open_day ] . ' days' )->setTime( $open_hour, $open_min, 0 );
		$close_dt = $week_start->modify( '+' . $day_map[ $close_day ] . ' days' )->setTime( $close_hour, $close_min, 0 );

		$is_open = ( $now >= $open_dt && $now <= $close_dt );

		// Determine the next opening time (this week or next week).
		if ( $now > $close_dt ) {
			$open_dt = $open_dt->modify( '+7 days' );
		}

		$message = $is_open
			? \__( 'Ticket sales are open.', 'ace-the-catch' )
			: \sprintf(
				/* translators: 1: open day, 2: open time, 3: close day, 4: close time */
				\__( 'Ticket sales are currently closed. Sales run from %1$s at %2$s to %3$s at %4$s.', 'ace-the-catch' ),
				$open_day,
				$open_time,
				$close_day,
				$close_time
			);

		return array(
			'open'        => $is_open,
			'message'     => $message,
			'close_epoch' => $close_dt->getTimestamp(),
			'open_epoch'  => $open_dt->getTimestamp(),
		);
	}

	/**
	 * Compute next weekly draw DateTime based on ACF "weekly_draw_on" (day, time).
	 *
	 * @param int $post_id Session post ID.
	 * @return \DateTimeImmutable|null
	 */
	private function get_next_draw_datetime( int $post_id ): ?\DateTimeImmutable {
		if ( ! \function_exists( 'get_field' ) ) {
			return null;
		}

		$draw_group = \get_field( 'weekly_draw_on', $post_id );
		$day        = $draw_group['day'] ?? '';
		$time       = $draw_group['time'] ?? '';

		$day_map = array(
			'Sunday'    => 0,
			'Monday'    => 1,
			'Tuesday'   => 2,
			'Wednesday' => 3,
			'Thursday'  => 4,
			'Friday'    => 5,
			'Saturday'  => 6,
		);

		if ( ! isset( $day_map[ $day ] ) || empty( $time ) ) {
			return null;
		}

		$tz         = \wp_timezone();
		$now        = new \DateTimeImmutable( 'now', $tz );
		$now_day    = (int) $now->format( 'w' );
		$week_start = $now->modify( '-' . $now_day . ' days' )->setTime( 0, 0, 0 );

		list( $hour, $minute ) = $this->split_time( $time );

		$target = $week_start->modify( '+' . $day_map[ $day ] . ' days' )->setTime( $hour, $minute, 0 );

		// If we've already passed this week's draw time, move to next week.
		if ( $target <= $now ) {
			$target = $target->modify( '+7 days' );
		}

		return $target;
	}

	/**
	 * Split time string HH:MM into hours and minutes.
	 *
	 * @param string $time Time string.
	 * @return array{int,int}
	 */
	private function split_time( string $time ): array {
		$parts = \array_pad( \explode( ':', $time ), 2, '0' );
		return array( (int) $parts[0], (int) $parts[1] );
	}

	/**
	 * Get count of winning draws.
	 *
	 * @param int $post_id Session post ID.
	 * @return int
	 */
	private function get_draws_count( int $post_id ): int {
		if ( ! \function_exists( 'get_field' ) ) {
			return 0;
		}

		$winning_draws = \get_field( 'winning_draws', $post_id );

		return \is_array( $winning_draws ) ? \count( $winning_draws ) : 0;
	}

	private function get_card_map( int $post_id ): array {
		$map = array();

		if ( ! \function_exists( 'get_field' ) ) {
			return $map;
		}

		$winning_draws = \get_field( 'winning_draws', $post_id );

		if ( empty( $winning_draws ) || ! \is_array( $winning_draws ) ) {
			return $map;
		}

		foreach ( $winning_draws as $draw ) {
			$envelope = isset( $draw['selected_envelope'] ) ? (int) $draw['selected_envelope'] : 0;
			$card     = isset( $draw['card_within'] ) ? (string) $draw['card_within'] : '';

			if ( $envelope < 1 || $envelope > 52 || ! $card ) {
				continue;
			}

			if ( ! \in_array( $card, $this->cards, true ) ) {
				continue;
			}

			$map[ $envelope ] = $card;
		}

		return $map;
	}

	/**
	 * Evaluate geo access based on selected locator and configured message.
	 *
	 * @return array{blocked:bool,message:string}
	 */
	private function evaluate_geo_access(): array {
		$blocked          = false;
		$default_message  = \__( 'Ticket sales are not available in your region.', 'ace-the-catch' );
		$admin_message    = \get_option( CatchTheAceSettings::OPTION_OUTSIDE_MESSAGE, '' );
		$message          = $admin_message ? \wp_kses_post( $admin_message ) : $default_message;
		$locator_key      = \get_option( CatchTheAceSettings::OPTION_GEO_LOCATOR, '' );
		$locator_configs  = \get_option( CatchTheAceSettings::OPTION_GEO_LOCATOR_CFG, array() );

		if ( empty( $locator_key ) ) {
			return array( 'blocked' => false, 'message' => $message );
		}

		$factory = Plugin::instance()->get_geo_locator_factory();
		$locator = $factory->create( $locator_key );

		if ( ! $locator ) {
			return array( 'blocked' => false, 'message' => $message );
		}

		$config = ( \is_array( $locator_configs ) && isset( $locator_configs[ $locator_key ] ) && \is_array( $locator_configs[ $locator_key ] ) )
			? $locator_configs[ $locator_key ]
			: array();

		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		$result = $locator->locate(
			array(
				'ip'     => $ip,
				'config' => $config,
			)
		);

		$in_ontario = isset( $result['in_ontario'] ) ? (bool) $result['in_ontario'] : false;
		$blocked    = ! $in_ontario;

		return array(
			'blocked' => $blocked,
			'message' => $message,
		);
	}
}
