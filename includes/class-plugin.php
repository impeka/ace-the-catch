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
	 * Template manager for theme overrides.
	 *
	 * @var TemplateManager
	 */
	private TemplateManager $template_manager;

	/**
	 * Private constructor to enforce singleton.
	 */
	private function __construct() {
		$this->version = \defined( 'LOTTO_VERSION' ) ? LOTTO_VERSION : '0.1.0';

		$this->payment_processor_factory = new PaymentProcessorFactory();
		$this->geo_locator_factory       = new GeoLocatorFactory();
		$this->register_builtin_locators();
		$this->envelope_dealer           = new EnvelopeDealer();
		$this->catch_the_ace_cpt         = new CatchTheAceCpt();
		$this->catch_the_ace_acf         = new CatchTheAceAcf();
		$this->catch_the_ace_settings    = new CatchTheAceSettings();
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
		\add_action( 'init', array( $this, 'register_shortcodes' ) );
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

		\wp_enqueue_style(
			'ace-the-catch-admin',
			LOTTO_URL . 'assets/css/admin.css',
			array(),
			$version
		);

		\wp_enqueue_script(
			'ace-the-catch-admin',
			LOTTO_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$version,
			true
		);
	}

	/**
	 * Enqueue public-facing assets.
	 *
	 * @return void
	 */
	public function enqueue_public_assets(): void {
		$version = $this->version;

		\wp_enqueue_style(
			'ace-the-catch',
			LOTTO_URL . 'assets/css/ace-the-catch.css',
			array(),
			$version
		);

		\wp_enqueue_style(
			'ace-the-catch-public',
			LOTTO_URL . 'assets/css/public.css',
			array(),
			$version
		);

		\wp_enqueue_script(
			'ace-the-catch-public',
			LOTTO_URL . 'assets/js/public.bundle.js',
			array( 'jquery' ),
			$version,
			true
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
	}
}
