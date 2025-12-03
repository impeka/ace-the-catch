<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivation routine.
 */
class Deactivator {

	/**
	 * Run deactivation logic.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		\flush_rewrite_rules();
	}
}
