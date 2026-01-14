<?php
/**
 * Session-level shortcodes and admin helper meta box.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class CatchTheAceSessionShortcodes {

	public const SHORTCODE_DRAW_COUNTDOWN         = 'cta_draw_countdown';
	public const SHORTCODE_SALES_CLOSE_COUNTDOWN  = 'cta_tickets_sales_close_countdown';
	public const SHORTCODE_TICKETS_CLOSED_MESSAGE = 'cta_tickets_closed_message';
	public const SHORTCODE_TICKETS_OPEN_MESSAGE   = 'cta_tickets_open_message';
	public const SHORTCODE_MINI_CARDS             = 'cta_mini_cards';

	public function __construct() {
		\add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
	}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function register(): void {
		\add_shortcode( self::SHORTCODE_DRAW_COUNTDOWN, array( $this, 'render_draw_countdown' ) );
		\add_shortcode( self::SHORTCODE_SALES_CLOSE_COUNTDOWN, array( $this, 'render_sales_close_countdown' ) );
		\add_shortcode( self::SHORTCODE_TICKETS_CLOSED_MESSAGE, array( $this, 'render_tickets_closed_message' ) );
		\add_shortcode( self::SHORTCODE_TICKETS_OPEN_MESSAGE, array( $this, 'render_tickets_open_message' ) );
		\add_shortcode( self::SHORTCODE_MINI_CARDS, array( $this, 'render_mini_cards' ) );
	}

	/**
	 * Add session admin meta boxes.
	 *
	 * @return void
	 */
	public function register_meta_boxes(): void {
		\add_meta_box(
			'cta-session-shortcodes',
			\__( 'Session Shortcodes', 'ace-the-catch' ),
			array( $this, 'render_shortcodes_meta_box' ),
			'catch-the-ace',
			'side',
			'default'
		);
	}

	/**
	 * Render the Session Shortcodes meta box.
	 *
	 * @param \WP_Post $post Session post.
	 * @return void
	 */
	public function render_shortcodes_meta_box( \WP_Post $post ): void {
		if ( 'catch-the-ace' !== $post->post_type ) {
			return;
		}

		$post_id = (int) $post->ID;

		$shortcodes = array(
			self::SHORTCODE_DRAW_COUNTDOWN        => sprintf( '[%s id="%d"]', self::SHORTCODE_DRAW_COUNTDOWN, $post_id ),
			self::SHORTCODE_SALES_CLOSE_COUNTDOWN => sprintf( '[%s id="%d"]', self::SHORTCODE_SALES_CLOSE_COUNTDOWN, $post_id ),
			self::SHORTCODE_TICKETS_CLOSED_MESSAGE => sprintf(
				'[%s id="%d"]%s[/%s]',
				self::SHORTCODE_TICKETS_CLOSED_MESSAGE,
				$post_id,
				\__( 'Ticket sales are currently closed.', 'ace-the-catch' ),
				self::SHORTCODE_TICKETS_CLOSED_MESSAGE
			),
			self::SHORTCODE_TICKETS_OPEN_MESSAGE => sprintf(
				'[%s id="%d"]%s[/%s]',
				self::SHORTCODE_TICKETS_OPEN_MESSAGE,
				$post_id,
				\__( 'Ticket sales are open.', 'ace-the-catch' ),
				self::SHORTCODE_TICKETS_OPEN_MESSAGE
			),
			self::SHORTCODE_MINI_CARDS            => sprintf( '[%s id="%d" max_width="600px" columns="8"]', self::SHORTCODE_MINI_CARDS, $post_id ),
		);

		echo '<p class="description">' . \esc_html__( 'Use these shortcodes to embed session components elsewhere (pages, posts, widgets).', 'ace-the-catch' ) . '</p>';
		echo '<ul class="cta-session-shortcodes__list">';
		foreach ( $shortcodes as $label => $code ) {
			echo '<li class="cta-session-shortcodes__item">';
			echo '<code class="cta-session-shortcodes__code">' . \esc_html( $code ) . '</code>';
			echo '<button type="button" class="cta-session-shortcodes__copy" data-cta-copy-shortcode="' . \esc_attr( $code ) . '" aria-label="' . \esc_attr__( 'Copy shortcode', 'ace-the-catch' ) . '" title="' . \esc_attr__( 'Copy shortcode', 'ace-the-catch' ) . '">';
			echo '<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>';
			echo '<span class="screen-reader-text">' . \esc_html__( 'Copy shortcode', 'ace-the-catch' ) . '</span>';
			echo '</button>';
			echo '</li>';
		}
		echo '</ul>';
		echo '<p class="description">' . \esc_html__( 'For cta_mini_cards, use max_width to control overall size and columns to control cards per row.', 'ace-the-catch' ) . '</p>';
	}

	/**
	 * Shortcode: draw countdown (matches the card table header countdown).
	 *
	 * @param array       $atts Attributes.
	 * @param string|null $content Content (unused).
	 * @param string      $tag Shortcode tag.
	 * @return string
	 */
	public function render_draw_countdown( array $atts = array(), ?string $content = null, string $tag = '' ): string {
		$session_id = $this->get_session_id_from_atts( $atts, $tag ?: self::SHORTCODE_DRAW_COUNTDOWN );
		if ( $session_id <= 0 ) {
			return '';
		}

		$sales_status = $this->get_sales_status( $session_id );
		if ( empty( $sales_status['open'] ) ) {
			return '';
		}

		$next_draw = $this->get_next_draw_datetime( $session_id );
		if ( ! $next_draw ) {
			return '';
		}

		$target_id = $this->unique_dom_id( 'cta-draw-countdown-' . $session_id . '-' );

		return '<div class="ace-countdown card-table-header__countdown" data-countdown-iso="'
			. \esc_attr( $next_draw->format( 'c' ) )
			. '" data-countdown-target="#' . \esc_attr( $target_id ) . '">'
			. '<div class="card-table-header__countdown-label">' . \esc_html__( 'Next draw in:', 'ace-the-catch' ) . '</div>'
			. '<div class="card-table-header__countdown-timer simply-countdown" id="' . \esc_attr( $target_id ) . '"></div>'
			. '</div>';
	}

	/**
	 * Shortcode: ticket sales close countdown.
	 *
	 * @param array       $atts Attributes.
	 * @param string|null $content Content (unused).
	 * @param string      $tag Shortcode tag.
	 * @return string
	 */
	public function render_sales_close_countdown( array $atts = array(), ?string $content = null, string $tag = '' ): string {
		$session_id = $this->get_session_id_from_atts( $atts, $tag ?: self::SHORTCODE_SALES_CLOSE_COUNTDOWN );
		if ( $session_id <= 0 ) {
			return '';
		}

		$sales_status = $this->get_sales_status( $session_id );
		if ( empty( $sales_status['open'] ) ) {
			return '';
		}

		$close_epoch = isset( $sales_status['close_epoch'] ) ? (int) $sales_status['close_epoch'] : 0;
		if ( $close_epoch <= 0 || $close_epoch <= time() ) {
			return '';
		}

		$tz = \wp_timezone();
		$close_dt = ( new \DateTimeImmutable( '@' . (string) $close_epoch ) )->setTimezone( $tz );

		$target_id = $this->unique_dom_id( 'cta-sales-close-countdown-' . $session_id . '-' );

		return '<div class="ace-countdown card-table-header__countdown card-table-header__countdown--sales-close" data-countdown-iso="'
			. \esc_attr( $close_dt->format( 'c' ) )
			. '" data-countdown-target="#' . \esc_attr( $target_id ) . '">'
			. '<div class="card-table-header__countdown-label">' . \esc_html__( 'Ticket sales close in:', 'ace-the-catch' ) . '</div>'
			. '<div class="card-table-header__countdown-timer simply-countdown" id="' . \esc_attr( $target_id ) . '"></div>'
			. '</div>';
	}

	/**
	 * Shortcode: mini cards grid (read-only).
	 *
	 * @param array       $atts Attributes.
	 * @param string|null $content Content (unused).
	 * @param string      $tag Shortcode tag.
	 * @return string
	 */
	public function render_mini_cards( array $atts = array(), ?string $content = null, string $tag = '' ): string {
		$atts = \shortcode_atts(
			array(
				'id'         => '',
				'session_id' => '',
				'max_width'  => '600px',
				'columns'    => '8',
			),
			$atts,
			$tag ?: self::SHORTCODE_MINI_CARDS
		);

		$session_id = $this->get_session_id_from_atts( $atts, $tag ?: self::SHORTCODE_MINI_CARDS );
		if ( $session_id <= 0 ) {
			return '';
		}

		$max_width = $this->sanitize_css_length( (string) $atts['max_width'] );
		if ( '' === $max_width ) {
			$max_width = '600px';
		}

		$columns = isset( $atts['columns'] ) ? (int) $atts['columns'] : 0;
		if ( $columns < 1 ) {
			$columns = 8;
		}
		if ( $columns > 52 ) {
			$columns = 52;
		}

		$table_style = 'grid-template-columns:repeat(' . $columns . ', minmax(0, 1fr));';

		$card_map = $this->get_card_map( $session_id );

		$items = array();
		for ( $index = 1; $index <= 52; $index++ ) {
			$card = $card_map[ $index ] ?? '';
			$card = ( \is_string( $card ) && preg_match( '/^[a-z0-9\\-]+$/', $card ) ) ? $card : '';

			$classes = 'envelope' . ( $card ? ' has-card' : '' );

			// Always include data-card to prevent the interactive JS from attaching click handlers.
			$data_card = ' data-card="' . \esc_attr( $card ) . '"';

			$flip_order = ( 'spade-1' === $card ) ? 53 : $index;
			$style      = '--order:' . $index . ';--flip-order:' . $flip_order;

			$items[] = '<div class="' . \esc_attr( $classes ) . '" data-envelope="' . \esc_attr( (string) $index ) . '" style="' . \esc_attr( $style ) . '"' . $data_card . '>
				<div class="__card">
					<div class="__back" data-number="' . \esc_attr( (string) $index ) . '"></div>
					<div class="__front"></div>
				</div>
			</div>';
		}

		return '<div class="cta-mini-cards" style="max-width:' . \esc_attr( $max_width ) . ';width:100%;">'
			. '<div class="card-table cta-mini-cards__table" style="' . \esc_attr( $table_style ) . '">' . \implode( '', $items ) . '</div>'
			. '</div>';
	}

	/**
	 * Shortcode: show content only when ticket sales are closed.
	 *
	 * @param array       $atts Attributes.
	 * @param string|null $content Content.
	 * @param string      $tag Shortcode tag.
	 * @return string
	 */
	public function render_tickets_closed_message( array $atts = array(), ?string $content = null, string $tag = '' ): string {
		$session_id = $this->get_session_id_from_atts( $atts, $tag ?: self::SHORTCODE_TICKETS_CLOSED_MESSAGE );
		if ( $session_id <= 0 ) {
			return '';
		}

		$sales_status = $this->get_sales_status( $session_id );
		if ( ! empty( $sales_status['open'] ) ) {
			return '';
		}

		return \do_shortcode( (string) $content );
	}

	/**
	 * Shortcode: show content only when ticket sales are open.
	 *
	 * @param array       $atts Attributes.
	 * @param string|null $content Content.
	 * @param string      $tag Shortcode tag.
	 * @return string
	 */
	public function render_tickets_open_message( array $atts = array(), ?string $content = null, string $tag = '' ): string {
		$session_id = $this->get_session_id_from_atts( $atts, $tag ?: self::SHORTCODE_TICKETS_OPEN_MESSAGE );
		if ( $session_id <= 0 ) {
			return '';
		}

		$sales_status = $this->get_sales_status( $session_id );
		if ( empty( $sales_status['open'] ) ) {
			return '';
		}

		return \do_shortcode( (string) $content );
	}

	/**
	 * Resolve session ID from shortcode attributes and validate the post type.
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $tag Shortcode tag.
	 * @return int
	 */
	private function get_session_id_from_atts( array $atts, string $tag ): int {
		$atts = \shortcode_atts(
			array(
				'id'         => '',
				'session_id' => '',
			),
			$atts,
			$tag
		);

		$id = (int) ( $atts['session_id'] ?: $atts['id'] );
		if ( $id <= 0 ) {
			return 0;
		}

		$post = \get_post( $id );
		return ( $post && 'catch-the-ace' === $post->post_type ) ? $id : 0;
	}

	/**
	 * Generate a unique DOM id.
	 *
	 * @param string $prefix Prefix.
	 * @return string
	 */
	private function unique_dom_id( string $prefix ): string {
		$raw = \function_exists( 'wp_unique_id' ) ? \wp_unique_id( $prefix ) : \uniqid( $prefix, false );
		$raw = preg_replace( '/[^a-zA-Z0-9_\\-]/', '-', (string) $raw );
		return $raw ? $raw : ( $prefix . '1' );
	}

	/**
	 * Sanitize a CSS length value.
	 *
	 * @param string $value Input.
	 * @return string
	 */
	private function sanitize_css_length( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^\\d+(?:\\.\\d+)?$/', $value ) ) {
			return $value . 'px';
		}

		if ( preg_match( '/^\\d+(?:\\.\\d+)?(px|em|rem|%|vw|vh)$/', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Determine sales window status for a session.
	 *
	 * @param int $post_id Session post ID.
	 * @return array{open:bool,close_epoch:int}
	 */
	private function get_sales_status( int $post_id ): array {
		$default = array(
			'open'        => false,
			'close_epoch' => 0,
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

		$wraps_week = ( $open_dt > $close_dt );

		$is_open = $wraps_week
			? ( $now >= $open_dt || $now <= $close_dt )
			: ( $now >= $open_dt && $now <= $close_dt );

		$close_dt_for_epoch = $close_dt;
		if ( $wraps_week && $is_open && $now >= $open_dt ) {
			$close_dt_for_epoch = $close_dt->modify( '+7 days' );
		}

		return array(
			'open'        => $is_open,
			'close_epoch' => $close_dt_for_epoch->getTimestamp(),
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

		if ( $target <= $now ) {
			$target = $target->modify( '+7 days' );
		}

		return $target;
	}

	/**
	 * Build envelope => card slug map based on ACF winning draws.
	 *
	 * @param int $post_id Session post ID.
	 * @return array<int,string>
	 */
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

			if ( $envelope < 1 || $envelope > 52 || '' === $card ) {
				continue;
			}

			$map[ $envelope ] = $card;
		}

		return $map;
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
