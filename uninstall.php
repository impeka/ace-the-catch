<?php
/**
 * Uninstall handler.
 *
 * @package Ace_The_Catch
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ace_the_catch_version' );
