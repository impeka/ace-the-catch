<?php
/**
 * Email dispatcher that sends EmailMessage instances via wp_mail.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class EmailDispatcher {

	/**
	 * Send an email message.
	 *
	 * @param EmailMessage $message Email message.
	 * @return bool
	 */
	public function send( EmailMessage $message ): bool {
		$to = $message->get_to();
		if ( empty( $to ) ) {
			return false;
		}

		$subject = trim( $message->get_subject() );
		if ( '' === $subject ) {
			return false;
		}

		$headers = array_merge(
			array(
				'Content-Type: text/html; charset=UTF-8',
			),
			$message->get_headers()
		);

		$body = $this->wrap_html( $message->get_body_html() );

		/**
		 * Filter email payload before sending.
		 *
		 * @param array{to:array,subject:string,body:string,headers:array} $payload Payload.
		 * @param EmailMessage $message Message instance.
		 */
		$payload = \apply_filters(
			'catch_the_ace_email_payload',
			array(
				'to'      => $to,
				'subject' => $subject,
				'body'    => $body,
				'headers' => $headers,
			),
			$message
		);

		return (bool) \wp_mail(
			$payload['to'],
			$payload['subject'],
			$payload['body'],
			$payload['headers']
		);
	}

	/**
	 * Wrap body content into a consistent HTML email layout.
	 *
	 * @param string $body Body HTML.
	 * @return string
	 */
	private function wrap_html( string $body ): string {
		$site_name = \wp_specialchars_decode( \get_bloginfo( 'name' ), ENT_QUOTES );

		return '<!doctype html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#f6f7f7;">'
			. '<div style="max-width:640px;margin:0 auto;padding:24px;">'
			. '<div style="background:#ffffff;border:1px solid #e5e5e5;border-radius:8px;padding:24px;font-family:Arial,sans-serif;line-height:1.5;color:#1d2327;">'
			. '<h2 style="margin:0 0 16px 0;font-size:18px;">' . \esc_html( $site_name ) . '</h2>'
			. '<div>' . $body . '</div>'
			. '<hr style="margin:24px 0;border:0;border-top:1px solid #e5e5e5;">'
			. '<p style="margin:0;color:#646970;font-size:12px;">' . \esc_html( $site_name ) . '</p>'
			. '</div>'
			. '</div>'
			. '</body></html>';
	}
}

