<?php
/**
 * Core plugin class.
 *
 * @package Ace_The_Catch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The main plugin controller.
 */
final class Ace_The_Catch {

	/**
	 * The singleton instance.
	 *
	 * @var Ace_The_Catch|null
	 */
	private static $instance = null;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Private constructor to enforce singleton.
	 */
	private function __construct() {
		$this->version = defined( 'ACE_THE_CATCH_VERSION' ) ? ACE_THE_CATCH_VERSION : '0.1.0';

		$this->init_hooks();
	}

	/**
	 * Returns the singleton instance.
	 *
	 * @return Ace_The_Catch
	 */
	public static function instance() {
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
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
	}

	/**
	 * Load plugin translation files.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'ace-the-catch',
			false,
			dirname( plugin_basename( ACE_THE_CATCH_FILE ) ) . '/languages'
		);
	}

	/**
	 * Enqueue admin-only assets.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		$version = $this->version;

		wp_enqueue_style(
			'ace-the-catch-admin',
			ACE_THE_CATCH_URL . 'assets/css/admin.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'ace-the-catch-admin',
			ACE_THE_CATCH_URL . 'assets/js/admin.js',
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
	public function enqueue_public_assets() {
		$version = $this->version;

		wp_enqueue_style(
			'ace-the-catch-public',
			ACE_THE_CATCH_URL . 'assets/css/public.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'ace-the-catch-public',
			ACE_THE_CATCH_URL . 'assets/js/public.js',
			array( 'jquery' ),
			$version,
			true
		);
	}
}
