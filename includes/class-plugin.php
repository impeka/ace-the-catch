<?php
/**
 * Core plugin class.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The main plugin controller.
 */
final class Plugin {

	/**
	 * The singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Payment processor factory instance.
	 *
	 * @var PaymentProcessorFactory
	 */
	private PaymentProcessorFactory $payment_processor_factory;

	/**
	 * Geo locator factory instance.
	 *
	 * @var GeoLocatorFactory
	 */
	private GeoLocatorFactory $geo_locator_factory;

	/**
	 * Envelope shortcode handler.
	 *
	 * @var EnvelopeDealer
	 */
	private EnvelopeDealer $envelope_dealer;

	/**
	 * Catch the Ace custom post type.
	 *
	 * @var CatchTheAceCpt
	 */
	private CatchTheAceCpt $catch_the_ace_cpt;

	/**
	 * Catch the Ace ACF registration.
	 *
	 * @var CatchTheAceAcf
	 */
	private CatchTheAceAcf $catch_the_ace_acf;

	/**
	 * Catch the Ace settings.
	 *
	 * @var CatchTheAceSettings
	 */
	private CatchTheAceSettings $catch_the_ace_settings;

	/**
	 * Catch the Ace orders.
	 *
	 * @var CatchTheAceOrders
	 */
	private CatchTheAceOrders $catch_the_ace_orders;

	/**
	 * Catch the Ace tickets.
	 *
	 * @var CatchTheAceTickets
	 */
	private CatchTheAceTickets $catch_the_ace_tickets;

	/**
	 * Email notifications service.
	 *
	 * @var CatchTheAceEmails
	 */
	private CatchTheAceEmails $catch_the_ace_emails;

	/**
	 * Admin queue status page.
	 *
	 * @var CatchTheAceLogs
	 */
	private CatchTheAceLogs $catch_the_ace_logs;

	/**
	 * Error logs store.
	 *
	 * @var CatchTheAceErrorLogs
	 */
	private CatchTheAceErrorLogs $catch_the_ace_error_logs;

	/**
	 * Benefactors taxonomy.
	 *
	 * @var CatchTheAceBenefactors
	 */
	private CatchTheAceBenefactors $catch_the_ace_benefactors;

	/**
	 * Template manager for theme overrides.
	 *
	 * @var TemplateManager
	 */
	private TemplateManager $template_manager;

	/**
	 * Private constructor to enforce singleton.
	 */
	private function __construct() {
		$this->version = \defined( 'LOTTO_VERSION' ) ? LOTTO_VERSION : '0.8.0';

		$this->payment_processor_factory = new PaymentProcessorFactory();
		$this->register_builtin_payment_processors();
		$this->geo_locator_factory       = new GeoLocatorFactory();
		$this->register_builtin_locators();
		$this->envelope_dealer           = new EnvelopeDealer();
		$this->catch_the_ace_cpt         = new CatchTheAceCpt();
		$this->catch_the_ace_acf         = new CatchTheAceAcf();
		$this->catch_the_ace_settings    = new CatchTheAceSettings();
		$this->catch_the_ace_orders      = new CatchTheAceOrders();
		$this->catch_the_ace_tickets     = new CatchTheAceTickets();
		$this->catch_the_ace_error_logs  = new CatchTheAceErrorLogs();
		$this->catch_the_ace_emails      = new CatchTheAceEmails( $this->catch_the_ace_orders );
		$this->catch_the_ace_logs        = new CatchTheAceLogs();
		$this->catch_the_ace_benefactors = new CatchTheAceBenefactors();
		$this->template_manager          = new TemplateManager();

		$this->init_hooks();
	}

	/**
	 * Returns the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register core hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		\add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		\add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		\add_action( 'init', array( $this, 'maybe_upgrade' ), 20 );
		\add_action( 'init', array( $this, 'register_shortcodes' ) );
		\add_action( 'wp_ajax_ace_the_catch_geo_locate', array( $this, 'ajax_geo_locate' ) );
		\add_action( 'wp_ajax_nopriv_ace_the_catch_geo_locate', array( $this, 'ajax_geo_locate' ) );
	}

	/**
	 * Handle one-time upgrade routines between plugin versions.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$stored_version = \get_option( 'lotto_version', '' );
		if ( $stored_version === LOTTO_VERSION ) {
			return;
		}

		\update_option( 'lotto_version', LOTTO_VERSION );
		\flush_rewrite_rules();
	}

	/**
	 * Load plugin translation files.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		\load_plugin_textdomain(
			'ace-the-catch',
			false,
			\dirname( \plugin_basename( LOTTO_FILE ) ) . '/languages'
		);
	}

	/**
	 * Enqueue admin-only assets.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets(): void {
		$version = $this->version;

		$admin_css_path = LOTTO_PATH . 'assets/css/admin.css';
		$admin_js_path  = LOTTO_PATH . 'assets/js/admin.js';
		$admin_css_ver  = \file_exists( $admin_css_path ) ? (string) \filemtime( $admin_css_path ) : $version;
		$admin_js_ver   = \file_exists( $admin_js_path ) ? (string) \filemtime( $admin_js_path ) : $version;

		\wp_enqueue_style(
			'ace-the-catch-admin',
			LOTTO_URL . 'assets/css/admin.css',
			array(),
			$admin_css_ver
		);

		$screen = \function_exists( 'get_current_screen' ) ? \get_current_screen() : null;
		$is_session_editor = $screen instanceof \WP_Screen
			&& 'catch-the-ace' === ( $screen->post_type ?? '' )
			&& \in_array( (string) ( $screen->base ?? '' ), array( 'post', 'post-new' ), true );

		if ( $is_session_editor && \file_exists( $admin_js_path ) ) {
			$deps = array( 'jquery' );

			$jspdf_rel  = 'node_modules/jspdf/dist/jspdf.umd.min.js';
			$jspdf_path = LOTTO_PATH . $jspdf_rel;
			if ( \file_exists( $jspdf_path ) ) {
				\wp_enqueue_script(
					'ace-the-catch-jspdf',
					LOTTO_URL . $jspdf_rel,
					array(),
					(string) \filemtime( $jspdf_path ),
					true
				);
				$deps[] = 'ace-the-catch-jspdf';
			}

			\wp_enqueue_script(
				'ace-the-catch-admin',
				LOTTO_URL . 'assets/js/admin.js',
				$deps,
				$admin_js_ver,
				true
			);

			\wp_localize_script(
				'ace-the-catch-admin',
				'aceTheCatchAdmin',
				array(
					'ajaxUrl'          => \admin_url( 'admin-ajax.php' ),
					'ticketSheetWidth' => 2550,
					'ticketSheetHeight'=> 3300,
				)
			);
		}
	}

	/**
	 * Enqueue public-facing assets.
	 *
	 * @return void
	 */
	public function enqueue_public_assets(): void {
		$version = $this->version;

		$css_main_path   = LOTTO_PATH . 'assets/css/ace-the-catch.css';
		$css_public_path = LOTTO_PATH . 'assets/css/public.css';
		$js_public_path  = LOTTO_PATH . 'assets/js/public.bundle.js';

		$css_main_ver   = \file_exists( $css_main_path ) ? (string) \filemtime( $css_main_path ) : $version;
		$css_public_ver = \file_exists( $css_public_path ) ? (string) \filemtime( $css_public_path ) : $version;
		$js_public_ver  = \file_exists( $js_public_path ) ? (string) \filemtime( $js_public_path ) : $version;

		\wp_enqueue_style(
			'ace-the-catch',
			LOTTO_URL . 'assets/css/ace-the-catch.css',
			array(),
			$css_main_ver
		);

		\wp_enqueue_style(
			'ace-the-catch-public',
			LOTTO_URL . 'assets/css/public.css',
			array(),
			$css_public_ver
		);

		\wp_enqueue_script(
			'ace-the-catch-public',
			LOTTO_URL . 'assets/js/public.bundle.js',
			array( 'jquery' ),
			$js_public_ver,
			true
		);

		$locator_key = (string) \get_option( CatchTheAceSettings::OPTION_GEO_LOCATOR, '' );
		$needs_location = ( OntarioBrowserGeoLocator::ID === $locator_key ) ? ! OntarioBrowserGeoLocator::has_valid_cookie() : false;

		\wp_localize_script(
			'ace-the-catch-public',
			'aceTheCatchGeo',
			array(
				'ajaxUrl'       => \admin_url( 'admin-ajax.php' ),
				'nonce'         => \wp_create_nonce( 'ace_the_catch_geo_locate' ),
				'locator'       => $locator_key,
				'browserLocator'=> OntarioBrowserGeoLocator::ID,
				'needsLocation' => $needs_location,
				'promptTitle'   => \__( 'Location required', 'ace-the-catch' ),
				'promptMessage' => \__( 'This lottery is only available in Ontario. Please allow your browser to share your location so we can verify eligibility.', 'ace-the-catch' ),
				'promptButton'  => \__( 'Allow location', 'ace-the-catch' ),
				'promptCancel'  => \__( 'Not now', 'ace-the-catch' ),
				'outsideTitle'  => \__( 'Not available in your region', 'ace-the-catch' ),
				'errorTitle'    => \__( 'Location error', 'ace-the-catch' ),
			)
		);
	}

	/**
	 * AJAX handler for browser-based geo checks.
	 *
	 * @return void
	 */
	public function ajax_geo_locate(): void {
		$nonce = isset( $_POST['nonce'] ) ? \sanitize_text_field( (string) \wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! \wp_verify_nonce( $nonce, 'ace_the_catch_geo_locate' ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid request.', 'ace-the-catch' ) ), 403 );
		}

		$locator_key = (string) \get_option( CatchTheAceSettings::OPTION_GEO_LOCATOR, '' );
		if ( OntarioBrowserGeoLocator::ID !== $locator_key ) {
			\wp_send_json_error( array( 'message' => \__( 'Browser location is not enabled.', 'ace-the-catch' ) ), 400 );
		}

		$lat_raw = isset( $_POST['lat'] ) ? (string) \wp_unslash( $_POST['lat'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$lng_raw = isset( $_POST['lng'] ) ? (string) \wp_unslash( $_POST['lng'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $lat_raw || '' === $lng_raw || ! \is_numeric( $lat_raw ) || ! \is_numeric( $lng_raw ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Missing coordinates.', 'ace-the-catch' ) ), 400 );
		}

		$lat = (float) $lat_raw;
		$lng = (float) $lng_raw;
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			\wp_send_json_error( array( 'message' => \__( 'Invalid coordinates.', 'ace-the-catch' ) ), 400 );
		}

		$locator = $this->geo_locator_factory->create( $locator_key );
		if ( ! ( $locator instanceof OntarioBrowserGeoLocator ) ) {
			\wp_send_json_error( array( 'message' => \__( 'Locator is not available.', 'ace-the-catch' ) ), 500 );
		}

		$result = $locator->locate(
			array(
				'lat' => $lat,
				'lng' => $lng,
			)
		);

		$in_ontario = isset( $result['in_ontario'] ) ? (bool) $result['in_ontario'] : false;
		OntarioBrowserGeoLocator::persist_cookie( $lat, $lng, $in_ontario );

		$default_message = \__( 'Ticket sales are not available in your region.', 'ace-the-catch' );
		$admin_message   = \get_option( CatchTheAceSettings::OPTION_OUTSIDE_MESSAGE, '' );
		$message_html    = $admin_message ? \wp_kses_post( $admin_message ) : $default_message;

		\wp_send_json_success(
			array(
				'in_ontario' => $in_ontario,
				'message'    => $in_ontario ? '' : $message_html,
			)
		);
	}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function register_shortcodes(): void {
		$this->envelope_dealer->register();
	}

	/**
	 * Get the payment processor factory.
	 *
	 * @return PaymentProcessorFactory
	 */
	public function get_payment_processor_factory(): PaymentProcessorFactory {
		return $this->payment_processor_factory;
	}

	/**
	 * Get the envelope dealer.
	 *
	 * @return EnvelopeDealer
	 */
	public function get_envelope_dealer(): EnvelopeDealer {
		return $this->envelope_dealer;
	}

	/**
	 * Get the geo locator factory.
	 *
	 * @return GeoLocatorFactory
	 */
	public function get_geo_locator_factory(): GeoLocatorFactory {
		return $this->geo_locator_factory;
	}

	/**
	 * Get the orders service.
	 *
	 * @return CatchTheAceOrders
	 */
	public function get_orders(): CatchTheAceOrders {
		return $this->catch_the_ace_orders;
	}

	/**
	 * Get the tickets service.
	 *
	 * @return CatchTheAceTickets
	 */
	public function get_tickets(): CatchTheAceTickets {
		return $this->catch_the_ace_tickets;
	}

	/**
	 * Get the email notifications service.
	 *
	 * @return CatchTheAceEmails
	 */
	public function get_emails(): CatchTheAceEmails {
		return $this->catch_the_ace_emails;
	}

	/**
	 * Get the error logs store.
	 *
	 * @return CatchTheAceErrorLogs
	 */
	public function get_error_logs(): CatchTheAceErrorLogs {
		return $this->catch_the_ace_error_logs;
	}

	/**
	 * Register built-in payment processors.
	 *
	 * @return void
	 */
	private function register_builtin_payment_processors(): void {
		$this->payment_processor_factory->register(
			'dummy_success',
			static function () {
				return new DummyPaymentProcessorSuccess();
			}
		);

		$this->payment_processor_factory->register(
			'dummy_failure',
			static function () {
				return new DummyPaymentProcessorFailure();
			}
		);

		$this->payment_processor_factory->register(
			'stripe',
			static function () {
				return new StripePaymentProcessor();
			}
		);
	}

	/**
	 * Register built-in geo locator providers.
	 *
	 * @return void
	 */
	private function register_builtin_locators(): void {
		$this->geo_locator_factory->register(
			'dummy_ontario',
			static function () {
				return new DummyGeoLocatorOntario();
			}
		);

		$this->geo_locator_factory->register(
			'dummy_outside',
			static function () {
				return new DummyGeoLocatorOutsideOntario();
			}
		);

		$this->geo_locator_factory->register(
			'maxmind',
			static function () {
				return new MaxMindGeoLocator();
			}
		);

		$this->geo_locator_factory->register(
			OntarioBrowserGeoLocator::ID,
			static function () {
				return new OntarioBrowserGeoLocator();
			}
		);
	}
}
