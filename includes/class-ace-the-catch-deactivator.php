<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Ace_The_Catch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivation routine.
 */
class Ace_The_Catch_Deactivator {

	/**
	 * Run deactivation logic.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
