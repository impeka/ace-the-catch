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

	const OPTION_TICKET_PRICE      = 'catch_the_ace_ticket_price';
	const OPTION_TERMS_URL         = 'catch_the_ace_terms_url';
	const OPTION_RECEIPT_EMAIL     = 'catch_the_ace_receipt_email';
	const OPTION_PAYMENT_PROC      = 'catch_the_ace_payment_processor';
	const OPTION_PAYMENT_PROC_CFG  = 'catch_the_ace_payment_processor_configs';
	const OPTION_GEO_LOCATOR       = 'catch_the_ace_geo_locator';
	const OPTION_GEO_LOCATOR_CFG   = 'catch_the_ace_geo_locator_configs';
	const OPTION_OUTSIDE_MESSAGE   = 'catch_the_ace_outside_message';
	const OPTION_GROUP             = 'catch_the_ace_options';
	const MENU_SLUG                = 'catch-the-ace-settings';

	public function __construct() {
		\add_action( 'admin_init', array( $this, 'register_settings' ) );
		\add_action( 'admin_menu', array( $this, 'register_menu' ) );
		\add_action( 'admin_head', array( $this, 'admin_styles' ) );
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

		\register_setting(
			self::OPTION_GROUP,
			self::OPTION_PAYMENT_PROC,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_payment_processor' ),
				'default'           => '',
			)
		);

		\register_setting(
			self::OPTION_GROUP,
			self::OPTION_PAYMENT_PROC_CFG,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_payment_processor_configs' ),
				'default'           => array(),
			)
		);

		\register_setting(
			self::OPTION_GROUP,
			self::OPTION_GEO_LOCATOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_geo_locator' ),
				'default'           => '',
			)
		);

		\register_setting(
			self::OPTION_GROUP,
			self::OPTION_GEO_LOCATOR_CFG,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_geo_locator_configs' ),
				'default'           => array(),
			)
		);

		\register_setting(
			self::OPTION_GROUP,
			self::OPTION_OUTSIDE_MESSAGE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_outside_message' ),
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

		\add_settings_field(
			self::OPTION_OUTSIDE_MESSAGE,
			\__( 'Outside Ontario Message', 'ace-the-catch' ),
			array( $this, 'render_outside_message_field' ),
			self::MENU_SLUG,
			'catch_the_ace_main_section'
		);

		\add_settings_section(
			'catch_the_ace_integrations_section',
			\__( 'Integrations', 'ace-the-catch' ),
			'__return_false',
			self::MENU_SLUG
		);

		\add_settings_field(
			self::OPTION_PAYMENT_PROC,
			\__( 'Payment Processor', 'ace-the-catch' ),
			array( $this, 'render_payment_processor_field' ),
			self::MENU_SLUG,
			'catch_the_ace_integrations_section'
		);

		\add_settings_field(
			self::OPTION_GEO_LOCATOR,
			\__( 'GeoIP Locator', 'ace-the-catch' ),
			array( $this, 'render_geo_locator_field' ),
			self::MENU_SLUG,
			'catch_the_ace_integrations_section'
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

	/**
	 * Sanitize WYSIWYG message for outside Ontario visitors.
	 *
	 * @param mixed $value Incoming value.
	 * @return string
	 */
	public function sanitize_outside_message( $value ): string {
		return \wp_kses_post( (string) $value );
	}

	/**
	 * Sanitize payment processor choice to a known provider.
	 *
	 * @param mixed $value Incoming value.
	 * @return string
	 */
	public function sanitize_payment_processor( $value ): string {
		$provider = is_string( $value ) ? $value : '';
		$options  = $this->get_payment_processor_options();
		return isset( $options[ $provider ] ) ? $provider : '';
	}

	/**
	 * Sanitize payment processor configurations.
	 *
	 * @param mixed $value Incoming configs.
	 * @return array
	 */
	public function sanitize_payment_processor_configs( $value ): array {
		$input   = \is_array( $value ) ? $value : array();
		$fields  = $this->get_payment_processor_field_defs();
		$cleaned = array();

		foreach ( $fields as $provider => $defs ) {
			$provider_input = $input[ $provider ] ?? array();
			if ( ! \is_array( $provider_input ) ) {
				$provider_input = array();
			}
			$cleaned[ $provider ] = $this->sanitize_field_values( $provider_input, $defs );
		}

		return $cleaned;
	}

	/**
	 * Sanitize geo locator choice to a known provider.
	 *
	 * @param mixed $value Incoming value.
	 * @return string
	 */
	public function sanitize_geo_locator( $value ): string {
		$provider = is_string( $value ) ? $value : '';
		$options  = $this->get_geo_locator_options();
		return isset( $options[ $provider ] ) ? $provider : '';
	}

	/**
	 * Sanitize geo locator configurations.
	 *
	 * @param mixed $value Incoming configs.
	 * @return array
	 */
	public function sanitize_geo_locator_configs( $value ): array {
		$input   = \is_array( $value ) ? $value : array();
		$fields  = $this->get_geo_locator_field_defs();
		$cleaned = array();

		foreach ( $fields as $provider => $defs ) {
			$provider_input = $input[ $provider ] ?? array();
			if ( ! \is_array( $provider_input ) ) {
				$provider_input = array();
			}
			$cleaned[ $provider ] = $this->sanitize_field_values( $provider_input, $defs );
		}

		return $cleaned;
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

	public function render_outside_message_field(): void {
		$value = \get_option( self::OPTION_OUTSIDE_MESSAGE, '' );
		$editor_id = self::OPTION_OUTSIDE_MESSAGE;
		\wp_editor(
			$value,
			$editor_id,
			array(
				'textarea_name' => self::OPTION_OUTSIDE_MESSAGE,
				'textarea_rows' => 5,
			)
		);
		echo '<p class="description">' . \esc_html__( 'Message shown to visitors outside Ontario.', 'ace-the-catch' ) . '</p>';
	}

	public function render_payment_processor_field(): void {
		$current    = \get_option( self::OPTION_PAYMENT_PROC, '' );
		$configs    = \get_option( self::OPTION_PAYMENT_PROC_CFG, array() );
		$options    = $this->get_payment_processor_options();
		$field_defs = $this->get_payment_processor_field_defs();

		$this->render_provider_cards(
			array(
				'title'       => \__( 'Payment Processor', 'ace-the-catch' ),
				'option_name' => self::OPTION_PAYMENT_PROC,
				'config_name' => self::OPTION_PAYMENT_PROC_CFG,
				'selected'    => $current,
				'options'     => $options,
				'configs'     => \is_array( $configs ) ? $configs : array(),
				'field_defs'  => $field_defs,
				'help'        => \__( 'Select a processor and configure its credentials if required.', 'ace-the-catch' ),
			)
		);
	}

	public function render_geo_locator_field(): void {
		$current    = \get_option( self::OPTION_GEO_LOCATOR, '' );
		$configs    = \get_option( self::OPTION_GEO_LOCATOR_CFG, array() );
		$options    = $this->get_geo_locator_options();
		$field_defs = $this->get_geo_locator_field_defs();

		$this->render_provider_cards(
			array(
				'title'       => \__( 'GeoIP Locator', 'ace-the-catch' ),
				'option_name' => self::OPTION_GEO_LOCATOR,
				'config_name' => self::OPTION_GEO_LOCATOR_CFG,
				'selected'    => $current,
				'options'     => $options,
				'configs'     => \is_array( $configs ) ? $configs : array(),
				'field_defs'  => $field_defs,
				'help'        => \__( 'Select a locator and configure its credentials if required (e.g., API keys).', 'ace-the-catch' ),
			)
		);
	}

	/**
	 * Build list of available processors keyed by id => label.
	 *
	 * @return array<string,string>
	 */
	private function get_payment_processor_options(): array {
		$factory = Plugin::instance()->get_payment_processor_factory();
		$options = array();
		foreach ( $factory->available_providers() as $provider_id ) {
			$instance = $factory->create( $provider_id );
			if ( $instance ) {
				$options[ $provider_id ] = $instance->get_label();
			}
		}
		return $options;
	}

	/**
	 * Build list of available geo locators keyed by id => label.
	 *
	 * @return array<string,string>
	 */
	private function get_geo_locator_options(): array {
		$factory = Plugin::instance()->get_geo_locator_factory();
		$options = array();
		foreach ( $factory->available_providers() as $provider_id ) {
			$instance = $factory->create( $provider_id );
			if ( $instance ) {
				$options[ $provider_id ] = $instance->get_label();
			}
		}
		return $options;
	}

	/**
	 * Field definitions for payment processors (filterable).
	 *
	 * @return array<string,array<string,array>>
	 */
	private function get_payment_processor_field_defs(): array {
		$defs = \apply_filters( 'ace_the_catch_payment_processor_fields', array() );
		return \is_array( $defs ) ? $defs : array();
	}

	/**
	 * Field definitions for geo locators (filterable).
	 *
	 * @return array<string,array<string,array>>
	 */
	private function get_geo_locator_field_defs(): array {
		$defs = \apply_filters( 'ace_the_catch_geo_locator_fields', array(
			'maxmind' => array(
				'account_id'  => array(
					'label'       => \__( 'Account ID', 'ace-the-catch' ),
					'type'        => 'text',
					'placeholder' => '',
					'description' => \__( 'MaxMind account ID for web services.', 'ace-the-catch' ),
				),
				'license_key' => array(
					'label'       => \__( 'License Key', 'ace-the-catch' ),
					'type'        => 'password',
					'placeholder' => '',
					'description' => \__( 'MaxMind license key for web services.', 'ace-the-catch' ),
				),
			),
		) );
		return \is_array( $defs ) ? $defs : array();
	}

	/**
	 * Sanitize an array of field values against definitions.
	 *
	 * @param array $input Values keyed by field key.
	 * @param array $defs Field definitions.
	 * @return array
	 */
	private function sanitize_field_values( array $input, array $defs ): array {
		$clean = array();
		foreach ( $defs as $field_key => $field ) {
			$value = isset( $input[ $field_key ] ) ? $input[ $field_key ] : '';
			if ( is_array( $value ) ) {
				$value = '';
			}
			switch ( $field['type'] ?? 'text' ) {
				case 'password':
					$clean[ $field_key ] = \sanitize_text_field( $value );
					break;
				case 'text':
				default:
					$clean[ $field_key ] = \sanitize_text_field( $value );
					break;
			}
		}
		return $clean;
	}

	/**
	 * Render provider cards with radio selection and config fields.
	 *
	 * @param array $args Arguments.
	 * @return void
	 */
	private function render_provider_cards( array $args ): void {
		$option_name = $args['option_name'] ?? '';
		$config_name = $args['config_name'] ?? '';
		$selected    = $args['selected'] ?? '';
		$options     = $args['options'] ?? array();
		$configs     = $args['configs'] ?? array();
		$field_defs  = $args['field_defs'] ?? array();
		$help        = $args['help'] ?? '';

		if ( empty( $options ) ) {
			echo '<p>' . \esc_html__( 'No providers available.', 'ace-the-catch' ) . '</p>';
			return;
		}

		echo '<div class="cta-provider-cards">';
		foreach ( $options as $key => $label ) {
			$provider_config = $configs[ $key ] ?? array();
			$fields          = $field_defs[ $key ] ?? array();
			$input_id        = $option_name . '_' . $key;
			echo '<div class="cta-provider-card">';
			printf(
				'<label for="%1$s" class="cta-provider-card__header"><input type="radio" name="%2$s" id="%1$s" value="%3$s" %4$s /> <strong>%5$s</strong></label>',
				\esc_attr( $input_id ),
				\esc_attr( $option_name ),
				\esc_attr( $key ),
				checked( $selected, $key, false ),
				\esc_html( $label )
			);

			if ( ! empty( $fields ) ) {
				echo '<div class="cta-provider-card__fields">';
				foreach ( $fields as $field_key => $field ) {
					$field_id    = $input_id . '_' . $field_key;
					$field_label = $field['label'] ?? $field_key;
					$type        = $field['type'] ?? 'text';
					$placeholder = $field['placeholder'] ?? '';
					$desc        = $field['description'] ?? '';
					$value       = isset( $provider_config[ $field_key ] ) ? $provider_config[ $field_key ] : '';

					echo '<div class="cta-provider-card__field">';
					printf(
						'<label for="%1$s">%2$s</label><br/>',
						\esc_attr( $field_id ),
						\esc_html( $field_label )
					);
					printf(
						'<input type="%1$s" id="%2$s" name="%3$s[%4$s][%5$s]" value="%6$s" placeholder="%7$s" class="regular-text" />',
						\esc_attr( $type ),
						\esc_attr( $field_id ),
						\esc_attr( $config_name ),
						\esc_attr( $key ),
						\esc_attr( $field_key ),
						\esc_attr( $value ),
						\esc_attr( $placeholder )
					);
					if ( $desc ) {
						echo '<p class="description">' . \esc_html( $desc ) . '</p>';
					}
					echo '</div>';
				}
				echo '</div>';
			} else {
				echo '<p class="description">' . \esc_html__( 'No configuration required.', 'ace-the-catch' ) . '</p>';
			}

			echo '</div>';
		}
		echo '</div>';

		if ( $help ) {
			echo '<p class="description">' . \esc_html( $help ) . '</p>';
		}
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

	/**
	 * Minimal admin styles for provider cards.
	 *
	 * @return void
	 */
	public function admin_styles(): void {
		echo '<style>
			.cta-provider-cards { display: grid; gap: 12px; }
			.cta-provider-card { border: 1px solid #dcdcde; border-radius: 6px; padding: 10px; background: #fff; }
			.cta-provider-card__header { display: flex; align-items: center; gap: 8px; cursor: pointer; }
			.cta-provider-card__fields { margin-top: 8px; padding-left: 18px; }
			.cta-provider-card__field { margin-bottom: 10px; }
		</style>';
	}
}
