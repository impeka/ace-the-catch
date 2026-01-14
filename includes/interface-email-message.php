<?php
/**
 * Email message contract.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes an email message to be sent.
 */
interface EmailMessage {

	/**
	 * Primary recipients.
	 *
	 * @return string[]
	 */
	public function get_to(): array;

	/**
	 * Email subject.
	 *
	 * @return string
	 */
	public function get_subject(): string;

	/**
	 * HTML body (will be wrapped by the mailer).
	 *
	 * @return string
	 */
	public function get_body_html(): string;

	/**
	 * Extra headers (e.g. Bcc).
	 *
	 * @return string[]
	 */
	public function get_headers(): array;
}

