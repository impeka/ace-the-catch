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
		\wp_clear_scheduled_hook( CatchTheAceOrders::CRON_HOOK_ABANDON_ORDERS );
		\wp_clear_scheduled_hook( CatchTheAceTickets::CRON_HOOK_GENERATE_TICKETS );
		\flush_rewrite_rules();
	}
}
