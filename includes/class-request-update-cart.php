<?php
/**
 * Request: update checkout cart (PRG).
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class RequestUpdateCart implements Request {

	/**
	 * POST field name indicating cart submission from the card table.
	 */
	private const CART_SUBMIT_FIELD = 'ace_checkout_cart';

	/**
	 * Nonce field name for cart submissions.
	 */
	private const CART_NONCE_FIELD = 'ace_checkout_cart_nonce';

	/**
	 * Nonce action prefix for cart submissions (suffix: session post ID).
	 */
	private const CART_NONCE_ACTION_PREFIX = 'ace_checkout_cart_';

	/**
	 * Legacy POST field name indicating cart submission from the card table.
	 */
	private const CART_SUBMIT_FIELD_LEGACY = 'ace_checkout';

	/**
	 * POST field name indicating the user is placing an order.
	 */
	private const PLACE_ORDER_FIELD = 'ace_place_order';

	private CatchTheAceCheckout $checkout;

	private int $post_id;

	private float $ticket_price;

	public function __construct( CatchTheAceCheckout $checkout, int $post_id, float $ticket_price ) {
		$this->checkout      = $checkout;
		$this->post_id       = $post_id;
		$this->ticket_price  = $ticket_price;
	}

	public function matches(): bool {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			return false;
		}

		// When placing an order, do not trigger the cart PRG flow.
		if ( isset( $_POST[ self::PLACE_ORDER_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}

		if ( isset( $_POST[ self::CART_SUBMIT_FIELD ] ) || isset( $_POST[ self::CART_SUBMIT_FIELD_LEGACY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return true;
		}

		// Back-compat / robustness: if cart items are posted, treat it as a cart submission.
		if ( isset( $_POST['envelope'] ) && \is_array( $_POST['envelope'] ) && ! empty( $_POST['envelope'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return true;
		}

		return false;
	}

	public function handle(): void {
		$nonce = isset( $_POST[ self::CART_NONCE_FIELD ] ) ? \sanitize_text_field( (string) \wp_unslash( $_POST[ self::CART_NONCE_FIELD ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $nonce ) || ! \wp_verify_nonce( $nonce, self::CART_NONCE_ACTION_PREFIX . (string) $this->post_id ) ) {
			$this->checkout->persist_notice_cookie(
				array(
					'type'    => 'error',
					'message' => \__( 'Security check failed. Please try again.', 'ace-the-catch' ),
				)
			);
			\wp_safe_redirect( $this->checkout->get_checkout_url( $this->post_id ) );
			exit;
		}

		$sales = $this->checkout->get_sales_status( $this->post_id );
		if ( empty( $sales['open'] ) ) {
			$message = isset( $sales['message'] ) ? (string) $sales['message'] : \__( 'Ticket sales are currently closed.', 'ace-the-catch' );
			$this->checkout->persist_notice_cookie(
				array(
					'type'    => 'error',
					'message' => $message,
				)
			);
			\wp_safe_redirect( $this->checkout->get_checkout_url( $this->post_id ) );
			exit;
		}

		$raw_cart = isset( $_POST['envelope'] ) && \is_array( $_POST['envelope'] ) ? \wp_unslash( $_POST['envelope'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_cart = $this->checkout->sanitize_raw_cart( $raw_cart );

		$cart_result = $this->checkout->validate_cart( $this->post_id, $this->ticket_price, $raw_cart );
		$clean_cart  = array();
		if ( ! empty( $cart_result['cart_items'] ) && \is_array( $cart_result['cart_items'] ) ) {
			foreach ( $cart_result['cart_items'] as $item ) {
				$env = isset( $item['envelope'] ) ? (int) $item['envelope'] : 0;
				$qty = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
				if ( $env > 0 && $qty > 0 ) {
					$clean_cart[ $env ] = $qty;
				}
			}
		}

		$orders       = Plugin::instance()->get_orders();
		$current      = $orders->get_current_order( $this->post_id );
		$order_id     = $current['order_id'] ?? 0;
		$order_key    = $current['order_key'] ?? '';
		$total_amount = (float) ( $cart_result['total_amount'] ?? 0 );
		$currency     = $this->checkout->get_currency();

		if ( empty( $order_id ) ) {
			if ( ! empty( $clean_cart ) ) {
				$meta = array(
					CatchTheAceOrders::META_ORDER_PAYMENT_PROCESSOR => \get_option( CatchTheAceSettings::OPTION_PAYMENT_PROC, '' ),
					CatchTheAceOrders::META_ORDER_TOTAL            => $total_amount,
					CatchTheAceOrders::META_ORDER_CURRENCY         => $currency,
				);
				$created   = $orders->create_order( $this->post_id, $clean_cart, $meta );
				$order_id  = (int) ( $created['order_id'] ?? 0 );
				$order_key = (string) ( $created['order_key'] ?? '' );
			}
		} else {
			if ( CatchTheAceOrders::STATUS_FAILED === ( \get_post_status( $order_id ) ?: '' ) ) {
				$orders->set_order_status( $order_id, CatchTheAceOrders::STATUS_STARTED );
				$orders->append_log( $order_id, \__( 'Order restarted after cart update.', 'ace-the-catch' ) );
			}
			$orders->update_order_cart( $order_id, $clean_cart, $total_amount, $currency );
		}

		if ( $order_id > 0 && $order_key ) {
			$orders->persist_order_cookie( $this->post_id, $order_id, $order_key );
			foreach ( $cart_result['warnings'] as $warning ) {
				$orders->append_log( $order_id, (string) $warning );
			}
		}

		if ( ! empty( $cart_result['warnings'] ) ) {
			$this->checkout->persist_notice_cookie(
				array(
					'type'     => '',
					'message'  => '',
					'warnings' => $cart_result['warnings'],
				)
			);
		}

		\wp_safe_redirect( $this->checkout->get_checkout_url( $this->post_id ) );
		exit;
	}
}
