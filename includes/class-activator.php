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
		CatchTheAceOrders::install();
		CatchTheAceTickets::install();
		CatchTheAceErrorLogs::install();
		if ( ! \wp_next_scheduled( CatchTheAceOrders::CRON_HOOK_ABANDON_ORDERS ) ) {
			\wp_schedule_event( time() + 3600, 'daily', CatchTheAceOrders::CRON_HOOK_ABANDON_ORDERS );
		}
		if ( ! \wp_next_scheduled( CatchTheAceTickets::CRON_HOOK_GENERATE_TICKETS ) ) {
			\wp_schedule_event( time() + 300, 'catch_the_ace_every_five_minutes', CatchTheAceTickets::CRON_HOOK_GENERATE_TICKETS );
		}
		\flush_rewrite_rules();
	}
}
