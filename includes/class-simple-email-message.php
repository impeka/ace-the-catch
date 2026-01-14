<?php
/**
 * Simple email message implementation.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class SimpleEmailMessage implements EmailMessage {

	/**
	 * @var string[]
	 */
	private array $to;

	private string $subject;

	private string $body_html;

	/**
	 * @var string[]
	 */
	private array $headers;

	/**
	 * @param string[] $to
	 * @param string   $subject
	 * @param string   $body_html
	 * @param string[] $headers
	 */
	public function __construct( array $to, string $subject, string $body_html, array $headers = array() ) {
		$this->to        = array_values( array_filter( array_map( 'strval', $to ) ) );
		$this->subject   = $subject;
		$this->body_html = $body_html;
		$this->headers   = array_values( array_filter( array_map( 'strval', $headers ) ) );
	}

	public function get_to(): array {
		return $this->to;
	}

	public function get_subject(): string {
		return $this->subject;
	}

	public function get_body_html(): string {
		return $this->body_html;
	}

	public function get_headers(): array {
		return $this->headers;
	}
}

