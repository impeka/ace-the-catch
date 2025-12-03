<?php
/**
 * Fired during plugin activation.
 *
 * @package Ace_The_Catch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation routine.
 */
class Ace_The_Catch_Activator {

	/**
	 * Run activation logic.
	 *
	 * @return void
	 */
	public static function activate() {
		update_option( 'ace_the_catch_version', ACE_THE_CATCH_VERSION );
		flush_rewrite_rules();
	}
}
