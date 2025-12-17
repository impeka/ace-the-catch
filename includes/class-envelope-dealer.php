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

		$deck_id = \sanitize_text_field( $atts['id'] );

		$attributes = $deck_id ? ' id="' . \esc_attr( $deck_id ) . '"' : '';

		$card_header = '<div class="card-table-header">test</div>';

		return '<div class="card-table"' . $attributes . '>' . $card_header . $this->build_envelopes() . '</div>';
	}

	/**
	 * Generate the envelope markup.
	 *
	 * @return string
	 */
	private function build_envelopes(): string {
		$items          = array();
		$cards_shuffled = $this->cards;
		\shuffle( $cards_shuffled );

		$card_slots      = (int) \floor( \count( $this->cards ) / 2 ); // 50% for testing.
		$assigned_cards  = \array_slice( $cards_shuffled, 0, $card_slots );
		$envelope_slots  = \range( 1, 52 );
		\shuffle( $envelope_slots );
		$envelope_slots  = \array_slice( $envelope_slots, 0, $card_slots );
		$card_map        = \array_combine( $envelope_slots, $assigned_cards );

		for ( $index = 1; $index <= 52; $index++ ) {
			$card      = $card_map[ $index ] ?? '';
			$card_attr = $card ? ' data-card="' . \esc_attr( $card ) . '"' : sprintf( ' tabindex="0" role="button" aria-label="%s"', sprintf( __( 'Envelope #%s', 'ace-the-catch' ), $index ) );
			$classes   = 'envelope' . ( $card ? ' has-card' : '' );

			$items[] = '<div class="' . $classes . '" data-envelope="' . $index . '" style="--order:' . $index . '"' . $card_attr . '>
				<div class="__card">
					<div class="__back" data-number="' . \esc_attr( $index ) . '"></div>
					<div class="__front"></div>
				</div>
			</div>';
		}

		return \implode( '', $items );
	}
}
