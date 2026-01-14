<?php
/**
 * Request handling contract.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a request handler for form submissions / PRG flows.
 */
interface Request {

	/**
	 * Whether this handler should run for the current request.
	 *
	 * @return bool
	 */
	public function matches(): bool;

	/**
	 * Handle the request.
	 *
	 * Implementations may set cookies, redirect, and exit.
	 *
	 * @return void
	 */
	public function handle(): void;
}

