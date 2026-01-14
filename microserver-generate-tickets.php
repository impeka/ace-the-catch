<?php
/**
 * CLI worker for generating tickets from completed orders.
 *
 * Usage:
 *   php microserver-generate-tickets.php
 */

if ( 'cli' !== \php_sapi_name() && 'phpdbg' !== \php_sapi_name() ) {
	\header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

$wp_load = \dirname( __DIR__, 3 ) . '/wp-load.php';
if ( ! \file_exists( $wp_load ) ) {
	\fwrite( \STDERR, "Could not locate wp-load.php at: {$wp_load}\n" );
	exit( 1 );
}

require_once $wp_load;

if ( ! \class_exists( '\\Impeka\\Lotto\\Plugin' ) ) {
	\fwrite( \STDERR, "Plugin not loaded.\n" );
	exit( 1 );
}

\Impeka\Lotto\Plugin::instance()->get_tickets()->process_pending_ticket_generation();

