<?php
/**
 * ACF field registration for Catch the Ace.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class CatchTheAceAcf {

	/**
	 * Card options (excluding jokers).
	 *
	 * @var array<string>
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
	 * Boot hooks.
	 */
	public function __construct() {
		\add_action( 'acf/init', array( $this, 'register_field_groups' ) );
	}

	/**
	 * Register ACF field groups.
	 *
	 * @return void
	 */
	public function register_field_groups(): void {
		if ( ! \function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		$this->register_session_details_group();
		$this->register_draw_details_group();
		$this->register_winning_draws_group();
	}

	/**
	 * Session details.
	 */
	private function register_session_details_group(): void {
		\acf_add_local_field_group(
			array(
				'key'      => 'group_catch_session_details',
				'title'    => \__( 'Session Details', 'ace-the-catch' ),
				'fields'   => array(
					array(
						'key'               => 'field_session_start',
						'label'             => \__( 'Session Start', 'ace-the-catch' ),
						'name'              => 'session_start',
						'type'              => 'date_time_picker',
						'display_format'    => 'F j, Y g:i a',
						'return_format'     => 'c',
						'first_day'         => 0,
					),
					array(
						'key'               => 'field_session_ended',
						'label'             => \__( 'Session Ended', 'ace-the-catch' ),
						'name'              => 'session_ended',
						'type'              => 'true_false',
						'ui'                => 1,
						'default_value'     => 0,
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'catch-the-ace',
						),
					),
				),
			)
		);
	}

	/**
	 * Draw details.
	 */
	private function register_draw_details_group(): void {
		$days = array(
			'Sunday'    => \__( 'Sunday', 'ace-the-catch' ),
			'Monday'    => \__( 'Monday', 'ace-the-catch' ),
			'Tuesday'   => \__( 'Tuesday', 'ace-the-catch' ),
			'Wednesday' => \__( 'Wednesday', 'ace-the-catch' ),
			'Thursday'  => \__( 'Thursday', 'ace-the-catch' ),
			'Friday'    => \__( 'Friday', 'ace-the-catch' ),
			'Saturday'  => \__( 'Saturday', 'ace-the-catch' ),
		);

		\acf_add_local_field_group(
			array(
				'key'      => 'group_catch_draw_details',
				'title'    => \__( 'Draw Details', 'ace-the-catch' ),
				'fields'   => array(
					array(
						'key'   => 'field_sales_open_group',
						'label' => \__( 'Ticket Sales Open On', 'ace-the-catch' ),
						'name'  => 'ticket_sales_open_on',
						'type'  => 'group',
						'layout'=> 'block',
						'sub_fields' => array(
							array(
								'key'     => 'field_sales_open_day',
								'label'   => \__( 'Day of Week', 'ace-the-catch' ),
								'name'    => 'day',
								'type'    => 'select',
								'choices' => $days,
								'ui'      => 1,
							),
							array(
								'key'           => 'field_sales_open_time',
								'label'         => \__( 'Time', 'ace-the-catch' ),
								'name'          => 'time',
								'type'          => 'time_picker',
								'display_format'=> 'g:i a',
								'return_format' => 'H:i',
							),
						),
					),
					array(
						'key'   => 'field_sales_close_group',
						'label' => \__( 'Ticket Sales End On', 'ace-the-catch' ),
						'name'  => 'ticket_sales_end_on',
						'type'  => 'group',
						'layout'=> 'block',
						'sub_fields' => array(
							array(
								'key'     => 'field_sales_close_day',
								'label'   => \__( 'Day of Week', 'ace-the-catch' ),
								'name'    => 'day',
								'type'    => 'select',
								'choices' => $days,
								'ui'      => 1,
							),
							array(
								'key'           => 'field_sales_close_time',
								'label'         => \__( 'Time', 'ace-the-catch' ),
								'name'          => 'time',
								'type'          => 'time_picker',
								'display_format'=> 'g:i a',
								'return_format' => 'H:i',
							),
						),
					),
					array(
						'key'   => 'field_weekly_draw',
						'label' => \__( 'Weekly Draw Occurs On', 'ace-the-catch' ),
						'name'  => 'weekly_draw_on',
						'type'  => 'group',
						'layout'=> 'block',
						'sub_fields' => array(
							array(
								'key'     => 'field_weekly_draw_day',
								'label'   => \__( 'Day of Week', 'ace-the-catch' ),
								'name'    => 'day',
								'type'    => 'select',
								'choices' => $days,
								'ui'      => 1,
							),
							array(
								'key'           => 'field_weekly_draw_time',
								'label'         => \__( 'Time', 'ace-the-catch' ),
								'name'          => 'time',
								'type'          => 'time_picker',
								'display_format'=> 'g:i a',
								'return_format' => 'H:i',
							),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'catch-the-ace',
						),
					),
				),
			)
		);
	}

	/**
	 * Winning draws repeater.
	 */
	private function register_winning_draws_group(): void {
		$card_choices = array();
		foreach ( $this->cards as $card ) {
			$card_choices[ $card ] = $this->format_card_label( $card );
		}

		\acf_add_local_field_group(
			array(
				'key'      => 'group_catch_winning_draws',
				'title'    => \__( 'Winning Draws', 'ace-the-catch' ),
				'fields'   => array(
					array(
						'key'          => 'field_winning_draws',
						'label'        => \__( 'Winning Draws', 'ace-the-catch' ),
						'name'         => 'winning_draws',
						'type'         => 'repeater',
						'layout'       => 'row',
						'button_label' => \__( 'Add Draw', 'ace-the-catch' ),
						'sub_fields'   => array(
							array(
								'key'           => 'field_winning_draw_date',
								'label'         => \__( 'Draw Date', 'ace-the-catch' ),
								'name'          => 'draw_date',
								'type'          => 'date_picker',
								'display_format'=> 'F j, Y',
								'return_format' => 'Y-m-d',
							),
							array(
								'key'           => 'field_winning_envelope',
								'label'         => \__( 'Selected Envelope', 'ace-the-catch' ),
								'name'          => 'selected_envelope',
								'type'          => 'number',
								'min'           => 1,
								'max'           => 52,
								'step'          => 1,
							),
							array(
								'key'     => 'field_card_within',
								'label'   => \__( 'Card Within', 'ace-the-catch' ),
								'name'    => 'card_within',
								'type'    => 'select',
								'choices' => $card_choices,
								'ui'      => 1,
								'allow_null' => 0,
							),
							array(
								'key'   => 'field_winnings',
								'label' => \__( 'Winnings', 'ace-the-catch' ),
								'name'  => 'winnings',
								'type'  => 'number',
								'prepend' => '$',
								'step'  => 'any',
							),
							array(
								'key'           => 'field_winning_note',
								'label'         => \__( 'Winning Note', 'ace-the-catch' ),
								'name'          => 'winning_note',
								'type'          => 'wysiwyg',
								'tabs'          => 'visual',
								'toolbar'       => 'basic',
								'media_upload'  => 0,
								'delay'         => 1,
							),
						),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'catch-the-ace',
						),
					),
				),
			)
		);
	}

	/**
	 * Convert card slug (e.g., spade-1) to a label with suit symbol and rank (e.g., ♠A).
	 *
	 * @param string $card Card slug.
	 * @return string
	 */
	private function format_card_label( string $card ): string {
		list( $suit, $rank_slug ) = \explode( '-', $card, 2 );

		$suits = array(
			'club'    => "\u{2663}\u{FE0F}", // ♣️
			'diamond' => "\u{2666}\u{FE0F}", // ♦️
			'heart'   => "\u{2665}\u{FE0F}", // ♥️
			'spade'   => "\u{2660}\u{FE0F}", // ♠️
		);

		$face = '';
		switch ( $rank_slug ) {
			case '1':
				$face = 'A';
				break;
			case '11-jack':
				$face = 'J';
				break;
			case '12-queen':
				$face = 'Q';
				break;
			case '13-king':
				$face = 'K';
				break;
			default:
				$face = $rank_slug;
				break;
		}

		$suit_symbol = $suits[ $suit ] ?? '';

		return $suit_symbol . $face;
	}
}
