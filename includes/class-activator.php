<?php
/**
 * Fired during plugin activation.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation routine.
 */
class Activator {

	/**
	 * Run activation logic.
	 *
	 * @return void
	 */
	public static function activate(): void {
		\update_option( 'lotto_version', LOTTO_VERSION );
		\flush_rewrite_rules();
	}
}
