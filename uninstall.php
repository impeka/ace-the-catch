<?php
/**
 * Uninstall handler.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

\delete_option( 'lotto_version' );
