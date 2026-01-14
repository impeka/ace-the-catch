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
		$geo_popup     = $geo_status['popup'];

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

		$checkout_url = \trailingslashit( $permalink . 'checkout' );

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
				<div class="ace-checkout-actions">
					<button type="button" class="ace-checkout-btn" data-checkout-url="' . \esc_url( $checkout_url ) . '">' . \esc_html__( 'Checkout', 'ace-the-catch' ) . '</button>
				</div>
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

		$actions = $this->build_card_table_actions( $post_id, $winners_url );

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

		return '<div class="' . $wrap_classes . '" data-session-id="' . \esc_attr( $post_id ) . '" data-session-week="' . \esc_attr( $draws_count + 1 ) . '" data-sales-open="' . ( $sales_status['open'] ? '1' : '0' ) . '" data-sales-message="' . \esc_attr( $sales_status['message'] ) . '" data-sales-close-epoch="' . \esc_attr( $sales_status['close_epoch'] ) . '" data-geo-block="' . ( $geo_blocked ? '1' : '0' ) . '" data-geo-message="' . \esc_attr( $geo_popup ) . '" data-checkout-url="' . \esc_url( $checkout_url ) . '">' . $actions . '<div class="card-table">' . $card_header . $this->build_envelopes( $card_map, $sales_status['open'] ) . '</div>' . $cart_markup . '</div>';
	}

	/**
	 * Build the card table navigation (links above the card table).
	 *
	 * Links come from ACF "card_table_navigation_links" (repeater of link field),
	 * and "Past Draws" is appended automatically unless "hide_past_draws_link" is enabled.
	 *
	 * @param int    $post_id Session post ID.
	 * @param string $winners_url Winners URL for the session.
	 * @return string Full navigation markup (or empty string).
	 */
	private function build_card_table_actions( int $post_id, string $winners_url ): string {
		$links = array();

		if ( \function_exists( 'get_field' ) ) {
			$rows = \get_field( 'card_table_navigation_links', $post_id );
			if ( \is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					if ( ! \is_array( $row ) ) {
						continue;
					}

					$link = $row['link'] ?? null;
					if ( ! \is_array( $link ) ) {
						continue;
					}

					$url = isset( $link['url'] ) ? \trim( (string) $link['url'] ) : '';
					$title = isset( $link['title'] ) ? \trim( (string) $link['title'] ) : '';
					$target = isset( $link['target'] ) ? \trim( (string) $link['target'] ) : '';

					if ( '' === $url || '' === $title ) {
						continue;
					}

					$links[] = array(
						'url'    => $url,
						'title'  => $title,
						'target' => $target,
					);
				}
			}
		}

		$hide_past_draws = false;
		if ( \function_exists( 'get_field' ) ) {
			$hide_past_draws = (bool) \get_field( 'hide_past_draws_link', $post_id );
		}

		if ( ! $hide_past_draws && '' !== $winners_url ) {
			$links[] = array(
				'url'    => $winners_url,
				'title'  => \__( 'Past Draws', 'ace-the-catch' ),
				'target' => '',
			);
		}

		if ( empty( $links ) ) {
			return '';
		}

		$html = '<div class="card-table-actions">';
		foreach ( $links as $link ) {
			$url = isset( $link['url'] ) ? (string) $link['url'] : '';
			$title = isset( $link['title'] ) ? (string) $link['title'] : '';
			$target = isset( $link['target'] ) ? (string) $link['target'] : '';

			if ( '' === $url || '' === $title ) {
				continue;
			}

			$target_attr = $target ? ' target="' . \esc_attr( $target ) . '"' : '';
			$rel_attr = ( '_blank' === $target ) ? ' rel="noopener noreferrer"' : '';

			$html .= '<a class="card-table-header__btn card-table-header__btn--ghost" href="' . \esc_url( $url ) . '"' . $target_attr . $rel_attr . ' aria-label="' . \esc_attr( $title ) . '">' . \esc_html( $title ) . '</a>';
		}

		$html .= '</div>';

		return $html;
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

		// If the opening day/time occurs after the closing day/time in the same week, treat the window as wrapping
		// across the week boundary (ex: open Friday, close Monday).
		$wraps_week = ( $open_dt > $close_dt );

		$is_open = $wraps_week
			? ( $now >= $open_dt || $now <= $close_dt )
			: ( $now >= $open_dt && $now <= $close_dt );

		// Next weekly opening time (used for countdown when sales are closed).
		$next_open_dt = ( $now < $open_dt ) ? $open_dt : $open_dt->modify( '+7 days' );

		// Ensure close_epoch is in the future when sales are currently open (important for client-side auto-close).
		$close_dt_for_epoch = $close_dt;
		if ( $wraps_week && $is_open && $now >= $open_dt ) {
			$close_dt_for_epoch = $close_dt->modify( '+7 days' );
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
			'close_epoch' => $close_dt_for_epoch->getTimestamp(),
			'open_epoch'  => $next_open_dt->getTimestamp(),
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
	 * @return array{blocked:bool,message:string,popup:string}
	 */
	private function evaluate_geo_access(): array {
		$blocked          = false;
		$default_message  = \__( 'Ticket sales are not available in your region.', 'ace-the-catch' );
		$admin_message    = \get_option( CatchTheAceSettings::OPTION_OUTSIDE_MESSAGE, '' );
		$message_html     = $admin_message ? \wp_kses_post( $admin_message ) : $default_message;
		$message_text     = \wp_strip_all_tags( $message_html );
		$locator_key      = \get_option( CatchTheAceSettings::OPTION_GEO_LOCATOR, '' );
		$locator_configs  = \get_option( CatchTheAceSettings::OPTION_GEO_LOCATOR_CFG, array() );

		if ( empty( $locator_key ) ) {
			return array(
				'blocked' => false,
				'message' => $message_text,
				'popup'   => $message_html,
			);
		}

		$factory = Plugin::instance()->get_geo_locator_factory();
		$locator = $factory->create( $locator_key );

		if ( ! $locator ) {
			return array(
				'blocked' => false,
				'message' => $message_text,
				'popup'   => $message_html,
			);
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

		$popup = $message_html;
		if ( $blocked ) {
			$details_html = $this->build_geo_details_table( $locator, (string) $ip, $result );
			if ( '' !== $details_html ) {
				$popup .= $details_html;
			}
		}

		return array(
			'blocked' => $blocked,
			'message' => $message_text,
			'popup'   => $popup,
		);
	}

	/**
	 * Render a geo details table for blocked users.
	 *
	 * @param GeoLocator $locator Locator implementation.
	 * @param string     $ip IP address used for lookup (when applicable).
	 * @param array      $result Locator result.
	 * @return string
	 */
	private function build_geo_details_table( GeoLocator $locator, string $ip, array $result ): string {
		$rows = array();

		$rows[] = array(
			'label' => \__( 'Geo provider', 'ace-the-catch' ),
			'value' => $locator->get_label(),
		);

		$source = isset( $result['source'] ) ? (string) $result['source'] : '';
		$source_label = '';
		switch ( $source ) {
			case 'ip':
				$source_label = \__( 'IP-based lookup', 'ace-the-catch' );
				break;
			case 'browser':
				$source_label = \__( 'Browser location', 'ace-the-catch' );
				break;
			case 'cookie':
				$source_label = \__( 'Cached browser location', 'ace-the-catch' );
				break;
			case 'dummy':
				$source_label = \__( 'Dummy locator', 'ace-the-catch' );
				break;
		}

		if ( '' !== $source_label ) {
			$rows[] = array(
				'label' => \__( 'Lookup method', 'ace-the-catch' ),
				'value' => $source_label,
			);
		}

		$show_ip = \in_array( $source, array( 'ip', 'dummy', '' ), true );
		$ip_used = isset( $result['ip'] ) && '' !== (string) $result['ip'] ? (string) $result['ip'] : $ip;
		if ( $show_ip && '' !== $ip_used ) {
			$rows[] = array(
				'label' => \__( 'IP address', 'ace-the-catch' ),
				'value' => $ip_used,
			);
		}

		$city = isset( $result['city'] ) ? \trim( (string) $result['city'] ) : '';
		if ( '' !== $city ) {
			$rows[] = array(
				'label' => \__( 'Estimated city', 'ace-the-catch' ),
				'value' => $city,
			);
		}

		$region_code = isset( $result['region'] ) ? \trim( (string) $result['region'] ) : '';
		$region_name = isset( $result['region_name'] ) ? \trim( (string) $result['region_name'] ) : '';
		if ( '' !== $region_name || '' !== $region_code ) {
			$value = $region_name ?: $region_code;
			if ( $region_name && $region_code && $region_name !== $region_code ) {
				$value .= ' (' . $region_code . ')';
			}
			$rows[] = array(
				'label' => \__( 'Estimated region', 'ace-the-catch' ),
				'value' => $value,
			);
		}

		$country_code = isset( $result['country'] ) ? \trim( (string) $result['country'] ) : '';
		$country_name = isset( $result['country_name'] ) ? \trim( (string) $result['country_name'] ) : '';
		if ( '' !== $country_name || '' !== $country_code ) {
			$value = $country_name ?: $country_code;
			if ( $country_name && $country_code && $country_name !== $country_code ) {
				$value .= ' (' . $country_code . ')';
			}
			$rows[] = array(
				'label' => \__( 'Estimated country', 'ace-the-catch' ),
				'value' => $value,
			);
		}

		$postal = isset( $result['postal'] ) ? \trim( (string) $result['postal'] ) : '';
		if ( '' !== $postal ) {
			$rows[] = array(
				'label' => \__( 'Estimated postal code', 'ace-the-catch' ),
				'value' => $postal,
			);
		}

		$lat = $result['lat'] ?? ( $result['latitude'] ?? null );
		$lng = $result['lng'] ?? ( $result['longitude'] ?? null );
		if ( null !== $lat && '' !== (string) $lat && null !== $lng && '' !== (string) $lng ) {
			$lat_str = \is_numeric( $lat ) ? \number_format( (float) $lat, 6, '.', '' ) : (string) $lat;
			$lng_str = \is_numeric( $lng ) ? \number_format( (float) $lng, 6, '.', '' ) : (string) $lng;
			$rows[] = array(
				'label' => \__( 'Coordinates', 'ace-the-catch' ),
				'value' => $lat_str . ', ' . $lng_str,
			);
		}

		$tz = isset( $result['time_zone'] ) ? \trim( (string) $result['time_zone'] ) : '';
		if ( '' !== $tz ) {
			$rows[] = array(
				'label' => \__( 'Estimated time zone', 'ace-the-catch' ),
				'value' => $tz,
			);
		}

		$radius = $result['accuracy_radius'] ?? '';
		if ( '' !== (string) $radius ) {
			$value = \is_numeric( $radius ) ? ( (string) (int) $radius ) . ' km' : (string) $radius;
			$rows[] = array(
				'label' => \__( 'Accuracy radius', 'ace-the-catch' ),
				'value' => $value,
			);
		}

		$rows[] = array(
			'label' => \__( 'Within Ontario', 'ace-the-catch' ),
			'value' => ! empty( $result['in_ontario'] ) ? \__( 'Yes', 'ace-the-catch' ) : \__( 'No', 'ace-the-catch' ),
		);

		$error = isset( $result['error'] ) ? \trim( (string) $result['error'] ) : '';
		if ( '' !== $error ) {
			$rows[] = array(
				'label' => \__( 'Lookup error', 'ace-the-catch' ),
				'value' => $error,
			);
		}

		if ( empty( $rows ) ) {
			return '';
		}

		$html = '<div class="ace-geo-details-wrap"><table class="ace-geo-details"><tbody>';
		foreach ( $rows as $row ) {
			$label = isset( $row['label'] ) ? (string) $row['label'] : '';
			$value = isset( $row['value'] ) ? (string) $row['value'] : '';
			if ( '' === $label || '' === $value ) {
				continue;
			}
			$html .= '<tr><th>' . \esc_html( $label ) . '</th><td>' . \esc_html( $value ) . '</td></tr>';
		}
		$html .= '</tbody></table></div>';

		return $html;
	}
}
