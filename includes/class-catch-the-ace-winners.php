<?php
/**
 * Helpers to build the winners view model.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class CatchTheAceWinners {

	/**
	 * Build the view model for the winners template.
	 *
	 * @param int $post_id Session post ID.
	 * @return array{
	 *   sales_status: array{open:bool,close_epoch:int,message?:string},
	 *   next_draw: \DateTimeImmutable|null,
	 *   draws: array<int,array{
	 *     week:int,
	 *     date_raw:string,
	 *     date_formatted:string,
	 *     envelope:int,
	 *     card_slug:string,
	 *     card_img_url:string,
	 *     winnings:float,
	 *     winnings_display:string,
	 *     note:string,
	 *     classes:string,
	 *   }>
	 * }
	 */
	public function build_view_model( int $post_id ): array {
		return array(
			'sales_status' => $this->get_sales_status( $post_id ),
			'next_draw'    => $this->get_next_draw_datetime( $post_id ),
			'draws'        => $this->prepare_draws( $post_id ),
		);
	}

	/**
	 * Prepare winning draws with derived data for rendering.
	 *
	 * @param int $post_id Session post ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function prepare_draws( int $post_id ): array {
		if ( ! \function_exists( 'get_field' ) ) {
			return array();
		}

		$raw_draws = \get_field( 'winning_draws', $post_id );
		if ( empty( $raw_draws ) || ! \is_array( $raw_draws ) ) {
			return array();
		}

		// Sort chronological by draw_date descending.
		\usort(
			$raw_draws,
			static function ( $a, $b ) {
				$at = isset( $a['draw_date'] ) ? \strtotime( $a['draw_date'] ) : 0;
				$bt = isset( $b['draw_date'] ) ? \strtotime( $b['draw_date'] ) : 0;
				return $bt <=> $at;
			}
		);

		$total = \count( $raw_draws );
		$draws = array();

		foreach ( $raw_draws as $index => $draw ) {
			$date_raw = isset( $draw['draw_date'] ) ? (string) $draw['draw_date'] : '';
			$envelope = isset( $draw['selected_envelope'] ) ? (int) $draw['selected_envelope'] : 0;
			$card     = isset( $draw['card_within'] ) ? (string) $draw['card_within'] : '';
			$winnings = isset( $draw['winnings'] ) ? (float) $draw['winnings'] : 0.0;
			$note     = isset( $draw['winning_note'] ) ? (string) $draw['winning_note'] : '';
			$week     = $total - $index;

			$is_ace_spades = ( 'spade-1' === $card );
			$classes       = 'ace-winners-list__item' . ( $is_ace_spades ? ' ace-winners-list__item--ace' : '' );

			$draws[] = array(
				'week'             => $week,
				'date_raw'         => $date_raw,
				'date_formatted'   => $date_raw ? \date_i18n( \get_option( 'date_format' ), \strtotime( $date_raw ) ) : '',
				'envelope'         => $envelope,
				'card_slug'        => $card,
				'card_img_url'     => $card ? LOTTO_URL . 'assets/images/playing-cards/' . $card . '.svg' : '',
				'winnings'         => $winnings,
				'winnings_display' => $winnings ? '$' . \number_format_i18n( $winnings, 2 ) : '',
				'note'             => $note,
				'classes'          => $classes,
			);
		}

		return $draws;
	}

	/**
	 * Determine sales window status and message.
	 *
	 * @param int $post_id Session post ID.
	 * @return array{open:bool,close_epoch:int,message?:string}
	 */
	private function get_sales_status( int $post_id ): array {
		$default = array(
			'open'        => false,
			'close_epoch' => 0,
			'message'     => \__( 'Ticket sales are currently closed.', 'ace-the-catch' ),
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
		$now_day    = (int) $now->format( 'w' );
		$week_start = $now->modify( '-' . $now_day . ' days' )->setTime( 0, 0, 0 );

		list( $open_hour, $open_min )   = $this->split_time( $open_time );
		list( $close_hour, $close_min ) = $this->split_time( $close_time );

		$open_dt  = $week_start->modify( '+' . $day_map[ $open_day ] . ' days' )->setTime( $open_hour, $open_min, 0 );
		$close_dt = $week_start->modify( '+' . $day_map[ $close_day ] . ' days' )->setTime( $close_hour, $close_min, 0 );

		$is_open = ( $now >= $open_dt && $now <= $close_dt );

		return array(
			'open'        => $is_open,
			'close_epoch' => $close_dt->getTimestamp(),
			'message'     => $default['message'],
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
}
