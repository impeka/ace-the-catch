<?php
/**
 * Catch the Ace settings page.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class CatchTheAceSettings {

	const OPTION_TICKET_PRICE   = 'catch_the_ace_ticket_price';
	const OPTION_TERMS_URL      = 'catch_the_ace_terms_url';
	const OPTION_RECEIPT_EMAIL  = 'catch_the_ace_receipt_email';
	const OPTION_GROUP          = 'catch_the_ace_options';
	const MENU_SLUG             = 'catch-the-ace-settings';

	public function __construct() {
		\add_action( 'admin_init', array( $this, 'register_settings' ) );
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
			\__( 'Catch the Ace Settings', 'ace-the-catch' ),
			\__( 'Settings', 'ace-the-catch' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		\register_setting(
			self::OPTION_GROUP,
			self::OPTION_TICKET_PRICE,
			array(
				'type'              => 'number',
				'sanitize_callback' => array( $this, 'sanitize_price' ),
				'default'           => '',
			)
		);

		\register_setting(
			self::OPTION_GROUP,
			self::OPTION_TERMS_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);

		\register_setting(
			self::OPTION_GROUP,
			self::OPTION_RECEIPT_EMAIL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_email' ),
				'default'           => '',
			)
		);

		\add_settings_section(
			'catch_the_ace_main_section',
			\__( 'Ticket & Receipt Settings', 'ace-the-catch' ),
			'__return_false',
			self::MENU_SLUG
		);

		\add_settings_field(
			self::OPTION_TICKET_PRICE,
			\__( 'Ticket Price', 'ace-the-catch' ),
			array( $this, 'render_price_field' ),
			self::MENU_SLUG,
			'catch_the_ace_main_section'
		);

		\add_settings_field(
			self::OPTION_TERMS_URL,
			\__( 'Terms & Conditions URL', 'ace-the-catch' ),
			array( $this, 'render_terms_field' ),
			self::MENU_SLUG,
			'catch_the_ace_main_section'
		);

		\add_settings_field(
			self::OPTION_RECEIPT_EMAIL,
			\__( 'Receipt Email', 'ace-the-catch' ),
			array( $this, 'render_email_field' ),
			self::MENU_SLUG,
			'catch_the_ace_main_section'
		);
	}

	/**
	 * Sanitize ticket price to a non-negative float string.
	 *
	 * @param mixed $value Incoming value.
	 * @return string
	 */
	public function sanitize_price( $value ): string {
		$number = \floatval( $value );
		if ( $number < 0 ) {
			$number = 0;
		}
		return (string) $number;
	}

	/**
	 * Sanitize email.
	 *
	 * @param mixed $value Incoming value.
	 * @return string
	 */
	public function sanitize_email( $value ): string {
		$email = \sanitize_email( $value );
		return $email ? $email : '';
	}

	public function render_price_field(): void {
		$value = \get_option( self::OPTION_TICKET_PRICE, '' );
		echo '<input type="number" min="0" step="0.01" class="regular-text" name="' . \esc_attr( self::OPTION_TICKET_PRICE ) . '" value="' . \esc_attr( $value ) . '" />';
		echo '<p class="description">' . \esc_html__( 'Set the ticket price for Catch the Ace entries.', 'ace-the-catch' ) . '</p>';
	}

	public function render_terms_field(): void {
		$value = \get_option( self::OPTION_TERMS_URL, '' );
		echo '<input type="url" class="regular-text" name="' . \esc_attr( self::OPTION_TERMS_URL ) . '" value="' . \esc_attr( $value ) . '" />';
		echo '<p class="description">' . \esc_html__( 'Link to the terms and conditions page.', 'ace-the-catch' ) . '</p>';
	}

	public function render_email_field(): void {
		$value = \get_option( self::OPTION_RECEIPT_EMAIL, '' );
		echo '<input type="email" class="regular-text" name="' . \esc_attr( self::OPTION_RECEIPT_EMAIL ) . '" value="' . \esc_attr( $value ) . '" />';
		echo '<p class="description">' . \esc_html__( 'Email address to receive purchase receipts.', 'ace-the-catch' ) . '</p>';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php \esc_html_e( 'Catch the Ace Settings', 'ace-the-catch' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				\settings_fields( self::OPTION_GROUP );
				\do_settings_sections( self::MENU_SLUG );
				\submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
