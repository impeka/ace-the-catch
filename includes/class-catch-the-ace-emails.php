<?php
/**
 * Email notifications for Catch the Ace.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class CatchTheAceEmails {

	private CatchTheAceOrders $orders;

	private EmailDispatcher $dispatcher;

	public function __construct( CatchTheAceOrders $orders, ?EmailDispatcher $dispatcher = null ) {
		$this->orders      = $orders;
		$this->dispatcher  = $dispatcher instanceof EmailDispatcher ? $dispatcher : new EmailDispatcher();
	}

	/**
	 * Send the successful transaction notification email.
	 *
	 * @param int    $order_id Order post ID.
	 * @param string $payment_reference Payment reference (optional).
	 * @return bool
	 */
	public function send_successful_transaction_email( int $order_id, string $payment_reference = '' ): bool {
		$order_number = (int) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_NUMBER, true );
		$customer_email = \sanitize_email( (string) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_CUSTOMER_EMAIL, true ) );
		if ( '' === $customer_email ) {
			$this->orders->append_log( $order_id, \__( 'Transaction email not sent: customer email is missing.', 'ace-the-catch' ) );
			return false;
		}

		if ( '' === $payment_reference ) {
			$payment_reference = (string) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_PAYMENT_REFERENCE, true );
		}

		$subject = trim( (string) \get_option( CatchTheAceSettings::OPTION_SUCCESS_EMAIL_SUBJECT, '' ) );
		if ( '' === $subject ) {
			$subject = $order_number > 0
				? \sprintf( \__( 'Order #%d confirmed', 'ace-the-catch' ), $order_number )
				: \__( 'Order confirmed', 'ace-the-catch' );
		}

		$body = $this->format_option_html( (string) \get_option( CatchTheAceSettings::OPTION_SUCCESS_EMAIL_BODY, '' ) );
		if ( '' === trim( \wp_strip_all_tags( $body ) ) ) {
			$body = '<p>' . \esc_html__( 'Thank you for your purchase!', 'ace-the-catch' ) . '</p>';
		}

		$body .= $this->render_transaction_footer( $order_id, $order_number, $payment_reference );

		$headers = array();
		$receipt_email = \sanitize_email( (string) \get_option( CatchTheAceSettings::OPTION_RECEIPT_EMAIL, '' ) );
		if ( $receipt_email && 0 !== strcasecmp( $receipt_email, $customer_email ) ) {
			$headers[] = 'Bcc: ' . $receipt_email;
		}
		$headers[] = CatchTheAceErrorLogs::HEADER_MARKER;
		$headers[] = CatchTheAceErrorLogs::HEADER_ORDER . ' ' . (string) $order_id;
		$headers[] = 'X-Catch-The-Ace-Email: transaction';

		$message = new SimpleEmailMessage(
			array( $customer_email ),
			$subject,
			$body,
			$headers
		);

		$sent = $this->dispatcher->send( $message );
		$this->orders->append_log(
			$order_id,
			$sent
				? \sprintf( \__( 'Transaction email sent to %s.', 'ace-the-catch' ), $customer_email )
				: \sprintf( \__( 'Transaction email failed to send to %s.', 'ace-the-catch' ), $customer_email )
		);

		return $sent;
	}

	/**
	 * Send the ticket delivery email once tickets have been generated.
	 *
	 * @param int $order_id Order post ID.
	 * @return bool
	 */
	public function send_ticket_delivery_email( int $order_id ): bool {
		$order_number = (int) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_NUMBER, true );
		$customer_email = \sanitize_email( (string) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_CUSTOMER_EMAIL, true ) );
		if ( '' === $customer_email ) {
			$this->orders->append_log( $order_id, \__( 'Ticket email not sent: customer email is missing.', 'ace-the-catch' ) );
			return false;
		}

		$subject = trim( (string) \get_option( CatchTheAceSettings::OPTION_TICKET_EMAIL_SUBJECT, '' ) );
		if ( '' === $subject ) {
			$subject = $order_number > 0
				? \sprintf( \__( 'Your tickets for Order #%d', 'ace-the-catch' ), $order_number )
				: \__( 'Your tickets', 'ace-the-catch' );
		}

		$body = $this->format_option_html( (string) \get_option( CatchTheAceSettings::OPTION_TICKET_EMAIL_BODY, '' ) );
		if ( '' === trim( \wp_strip_all_tags( $body ) ) ) {
			$body = '<p>' . \esc_html__( 'Your tickets are ready.', 'ace-the-catch' ) . '</p>';
		}

		$tickets_html = $this->render_tickets_for_order( $order_id, $order_number );
		if ( '' === $tickets_html ) {
			$this->orders->append_log( $order_id, \__( 'Ticket email not sent: no tickets found for this order.', 'ace-the-catch' ) );
			return false;
		}

		$body .= $tickets_html;

		// Marker headers used to capture wp_mail_failed events in the error log.
		$message = new SimpleEmailMessage(
			array( $customer_email ),
			$subject,
			$body,
			array(
				CatchTheAceErrorLogs::HEADER_MARKER,
				CatchTheAceErrorLogs::HEADER_ORDER . ' ' . (string) $order_id,
				'X-Catch-The-Ace-Email: tickets',
			)
		);

		$sent = $this->dispatcher->send( $message );
		$this->orders->append_log(
			$order_id,
			$sent
				? \sprintf( \__( 'Ticket delivery email sent to %s.', 'ace-the-catch' ), $customer_email )
				: \sprintf( \__( 'Ticket delivery email failed to send to %s.', 'ace-the-catch' ), $customer_email )
		);

		return $sent;
	}

	/**
	 * Format option HTML for email usage.
	 *
	 * @param string $html Raw option HTML.
	 * @return string
	 */
	private function format_option_html( string $html ): string {
		$html = \wp_kses_post( $html );
		$html = \do_shortcode( $html );
		$html = \wpautop( $html );
		return $html;
	}

	/**
	 * Render the transaction email footer content (order #, summary, reference).
	 *
	 * @param int    $order_id Order post ID.
	 * @param int    $order_number Order number.
	 * @param string $payment_reference Payment reference.
	 * @return string
	 */
	private function render_transaction_footer( int $order_id, int $order_number, string $payment_reference ): string {
		$cart     = $this->orders->get_order_cart( $order_id );
		$total    = (float) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_TOTAL, true );
		$currency = (string) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_CURRENCY, true );

		$html  = '<hr style="margin:24px 0;border:0;border-top:1px solid #e5e5e5;">';
		if ( $order_number > 0 ) {
			$html .= '<p><strong>' . \esc_html( \sprintf( __( 'Order #%d', 'ace-the-catch' ), $order_number ) ) . '</strong></p>';
		}

		if ( $payment_reference ) {
			$html .= '<p><strong>' . \esc_html__( 'Payment Reference:', 'ace-the-catch' ) . '</strong> ' . \esc_html( $payment_reference ) . '</p>';
		}

		$html .= '<p>' . \esc_html__( 'You will receive your tickets in a separate email.', 'ace-the-catch' ) . '</p>';

		if ( ! empty( $cart ) ) {
			$html .= $this->render_order_table( $cart, $total, $currency );
		}

		return $html;
	}

	/**
	 * Render the order cart as an HTML table.
	 *
	 * @param array<int,int> $cart Envelope => qty.
	 * @param float          $total Total amount.
	 * @param string         $currency Currency code.
	 * @return string
	 */
	private function render_order_table( array $cart, float $total, string $currency ): string {
		$currency = $currency ? strtoupper( $currency ) : '';
		$ticket_price = (float) \get_option( CatchTheAceSettings::OPTION_TICKET_PRICE, 0 );

		$html  = '<table style="width:100%;border-collapse:collapse;margin-top:12px;">';
		$html .= '<thead><tr>';
		$html .= '<th style="text-align:left;border-bottom:1px solid #e5e5e5;padding:8px 6px;">' . \esc_html__( 'Envelope', 'ace-the-catch' ) . '</th>';
		$html .= '<th style="text-align:right;border-bottom:1px solid #e5e5e5;padding:8px 6px;">' . \esc_html__( 'Entries', 'ace-the-catch' ) . '</th>';
		$html .= '<th style="text-align:right;border-bottom:1px solid #e5e5e5;padding:8px 6px;">' . \esc_html__( 'Subtotal', 'ace-the-catch' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		\ksort( $cart );
		foreach ( $cart as $env => $qty ) {
			$env = (int) $env;
			$qty = (int) $qty;
			if ( $env <= 0 || $qty <= 0 ) {
				continue;
			}

			$subtotal = $ticket_price > 0 ? ( $qty * $ticket_price ) : 0.0;

			$html .= '<tr>';
			$html .= '<td style="padding:8px 6px;border-bottom:1px solid #f0f0f1;">' . \esc_html( '#' . (string) $env ) . '</td>';
			$html .= '<td style="padding:8px 6px;text-align:right;border-bottom:1px solid #f0f0f1;">' . \esc_html( (string) $qty ) . '</td>';
			$html .= '<td style="padding:8px 6px;text-align:right;border-bottom:1px solid #f0f0f1;">' . \esc_html( '$' . \number_format_i18n( $subtotal, 2 ) ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody><tfoot><tr>';
		$html .= '<th colspan="2" style="padding:8px 6px;text-align:right;border-top:1px solid #e5e5e5;">' . \esc_html__( 'Total', 'ace-the-catch' ) . '</th>';
		$total_display = '$' . \number_format_i18n( $total, 2 );
		if ( $currency ) {
			$total_display .= ' ' . $currency;
		}
		$html .= '<th style="padding:8px 6px;text-align:right;border-top:1px solid #e5e5e5;">' . \esc_html( $total_display ) . '</th>';
		$html .= '</tr></tfoot></table>';

		return $html;
	}

	/**
	 * Render ticket numbers for an order.
	 *
	 * @param int $order_id Order post ID.
	 * @param int $order_number Order number.
	 * @return string
	 */
	private function render_tickets_for_order( int $order_id, int $order_number ): string {
		global $wpdb;
		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return '';
		}

		$table = CatchTheAceTickets::get_table_name();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ticket_id, envelope_number
				FROM {$table}
				WHERE order_id = %d
				ORDER BY ticket_id ASC",
				$order_id
			),
			ARRAY_A
		);

		if ( empty( $rows ) || ! \is_array( $rows ) ) {
			return '';
		}

		$html  = '<hr style="margin:24px 0;border:0;border-top:1px solid #e5e5e5;">';
		if ( $order_number > 0 ) {
			$html .= '<p><strong>' . \esc_html( \sprintf( __( 'Tickets for Order #%d', 'ace-the-catch' ), $order_number ) ) . '</strong></p>';
		} else {
			$html .= '<p><strong>' . \esc_html__( 'Your Tickets', 'ace-the-catch' ) . '</strong></p>';
		}

		$html .= '<table style="width:100%;border-collapse:collapse;margin-top:12px;">';
		$html .= '<thead><tr>';
		$html .= '<th style="text-align:left;border-bottom:1px solid #e5e5e5;padding:8px 6px;">' . \esc_html__( 'Ticket #', 'ace-the-catch' ) . '</th>';
		$html .= '<th style="text-align:left;border-bottom:1px solid #e5e5e5;padding:8px 6px;">' . \esc_html__( 'Envelope', 'ace-the-catch' ) . '</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			if ( ! \is_array( $row ) ) {
				continue;
			}
			$ticket_id = isset( $row['ticket_id'] ) ? (int) $row['ticket_id'] : 0;
			$envelope  = isset( $row['envelope_number'] ) ? (int) $row['envelope_number'] : 0;
			if ( $ticket_id <= 0 ) {
				continue;
			}

			$html .= '<tr>';
			$html .= '<td style="padding:8px 6px;border-bottom:1px solid #f0f0f1;">' . \esc_html( (string) $ticket_id ) . '</td>';
			$html .= '<td style="padding:8px 6px;border-bottom:1px solid #f0f0f1;">' . \esc_html( $envelope > 0 ? '#' . (string) $envelope : '-' ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';

		return $html;
	}
}
