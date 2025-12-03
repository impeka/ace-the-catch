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
	 * Private constructor to enforce singleton.
	 */
	private function __construct() {
		$this->version = \defined( 'LOTTO_VERSION' ) ? LOTTO_VERSION : '0.1.0';

		$this->payment_processor_factory = new PaymentProcessorFactory();
		$this->geo_locator_factory       = new GeoLocatorFactory();

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
			'ace-the-catch-public',
			LOTTO_URL . 'assets/css/public.css',
			array(),
			$version
		);

		\wp_enqueue_script(
			'ace-the-catch-public',
			LOTTO_URL . 'assets/js/public.js',
			array( 'jquery' ),
			$version,
			true
		);
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
	 * Get the geo locator factory.
	 *
	 * @return GeoLocatorFactory
	 */
	public function get_geo_locator_factory(): GeoLocatorFactory {
		return $this->geo_locator_factory;
	}
}
