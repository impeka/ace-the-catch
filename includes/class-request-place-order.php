<?php
/**
 * Request: place an order / process payment (PRG).
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class RequestPlaceOrder implements Request {

	/**
	 * POST field name indicating the user is placing an order.
	 */
	private const PLACE_ORDER_FIELD = 'ace_place_order';

	/**
	 * Nonce field name for placing orders.
	 */
	private const PLACE_ORDER_NONCE_FIELD = 'ace_place_order_nonce';

	/**
	 * Nonce action for placing orders.
	 */
	private const PLACE_ORDER_NONCE_ACTION = 'ace_place_order';

	private CatchTheAceCheckout $checkout;

	private int $post_id;

	private float $ticket_price;

	public function __construct( CatchTheAceCheckout $checkout, int $post_id, float $ticket_price ) {
		$this->checkout     = $checkout;
		$this->post_id      = $post_id;
		$this->ticket_price = $ticket_price;
	}

	public function matches(): bool {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			return false;
		}

		return isset( $_POST[ self::PLACE_ORDER_FIELD ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public function handle(): void {
		$result = $this->process_place_order();

		$this->checkout->persist_notice_cookie( $result['notice'] );
		if ( $result['success'] ) {
			$this->checkout->clear_cart_cookies();
		}

		\wp_safe_redirect( $this->checkout->get_checkout_url( $this->post_id ) );
		exit;
	}

	/**
	 * Process an order submission by charging via the configured payment processor.
	 *
	 * @return array{success:bool,notice:array,cart_result:array}
	 */
	private function process_place_order(): array {
		$nonce = isset( $_POST[ self::PLACE_ORDER_NONCE_FIELD ] ) ? \sanitize_text_field( (string) \wp_unslash( $_POST[ self::PLACE_ORDER_NONCE_FIELD ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $nonce ) || ! \wp_verify_nonce( $nonce, self::PLACE_ORDER_NONCE_ACTION ) ) {
			return array(
				'success'     => false,
				'notice'      => array(
					'type'    => 'error',
					'message' => \__( 'Security check failed. Please try again.', 'ace-the-catch' ),
				),
				'cart_result' => array(
					'cart_items'   => array(),
					'warnings'     => array(),
					'total_amount' => 0.0,
				),
			);
		}

		$raw_cart = isset( $_POST['envelope'] ) && \is_array( $_POST['envelope'] ) ? \wp_unslash( $_POST['envelope'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_cart = $this->checkout->sanitize_raw_cart( $raw_cart );
		$cart_result = $this->checkout->validate_cart( $this->post_id, $this->ticket_price, $raw_cart );

		$clean_cart = array();
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
			$existing_cart = $orders->get_order_cart( $order_id );
			if ( $existing_cart !== $clean_cart ) {
				$orders->update_order_cart( $order_id, $clean_cart, $total_amount, $currency );
			} else {
				$orders->touch_order( $order_id );
			}
		}

		if ( $order_id > 0 && $order_key ) {
			$orders->persist_order_cookie( $this->post_id, $order_id, $order_key );
			foreach ( $cart_result['warnings'] as $warning ) {
				$orders->append_log( $order_id, (string) $warning );
			}
		}

		if ( empty( $cart_result['cart_items'] ) ) {
			if ( $order_id > 0 ) {
				$orders->append_log( $order_id, \__( 'Place order attempted with an empty or invalid cart.', 'ace-the-catch' ) );
			}
			return array(
				'success'     => false,
				'notice'      => array(
					'type'     => 'error',
					'message'  => \__( 'Your cart is empty or no longer valid. Please go back and select envelopes.', 'ace-the-catch' ),
					'warnings' => $cart_result['warnings'],
				),
				'cart_result' => $cart_result,
			);
		}

		$first_name = isset( $_POST['ace_first_name'] ) ? \sanitize_text_field( (string) \wp_unslash( $_POST['ace_first_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_name  = isset( $_POST['ace_last_name'] ) ? \sanitize_text_field( (string) \wp_unslash( $_POST['ace_last_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email      = isset( $_POST['ace_email'] ) ? \sanitize_email( (string) \wp_unslash( $_POST['ace_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$phone      = isset( $_POST['ace_phone'] ) ? \sanitize_text_field( (string) \wp_unslash( $_POST['ace_phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$location   = isset( $_POST['ace_location'] ) ? \sanitize_text_field( (string) \wp_unslash( $_POST['ace_location'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$agree_terms = isset( $_POST['ace_agree_terms'] ) ? \sanitize_text_field( (string) \wp_unslash( $_POST['ace_agree_terms'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$benefactor_term_id = isset( $_POST['ace_benefactor'] ) ? (int) \wp_unslash( $_POST['ace_benefactor'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $first_name ) || empty( $last_name ) || empty( $email ) ) {
			if ( $order_id > 0 ) {
				$orders->append_log( $order_id, \__( 'Place order failed: missing customer details.', 'ace-the-catch' ) );
			}
			return array(
				'success'     => false,
				'notice'      => array(
					'type'     => 'error',
					'message'  => \__( 'Please provide your first name, last name, and email.', 'ace-the-catch' ),
					'warnings' => $cart_result['warnings'],
				),
				'cart_result' => $cart_result,
			);
		}

		if ( empty( $phone ) || empty( $location ) ) {
			if ( $order_id > 0 ) {
				$orders->append_log( $order_id, \__( 'Place order failed: missing phone or location.', 'ace-the-catch' ) );
			}
			return array(
				'success'     => false,
				'notice'      => array(
					'type'     => 'error',
					'message'  => \__( 'Please provide your telephone number and general location.', 'ace-the-catch' ),
					'warnings' => $cart_result['warnings'],
				),
				'cart_result' => $cart_result,
			);
		}

		if ( '1' !== $agree_terms ) {
			if ( $order_id > 0 ) {
				$orders->append_log( $order_id, \__( 'Place order failed: terms not accepted.', 'ace-the-catch' ) );
			}
			return array(
				'success'     => false,
				'notice'      => array(
					'type'     => 'error',
					'message'  => \__( 'You must agree to the terms and conditions to place an order.', 'ace-the-catch' ),
					'warnings' => $cart_result['warnings'],
				),
				'cart_result' => $cart_result,
			);
		}

		if ( $order_id > 0 ) {
			$orders->set_customer( $order_id, $first_name, $last_name, $email, $phone, $location );

			$terms_url = (string) \get_option( CatchTheAceSettings::OPTION_TERMS_URL, '' );
			$orders->set_terms_acceptance( $order_id, $terms_url );

			$benefactor_label = '';
			if ( $benefactor_term_id > 0 && \taxonomy_exists( CatchTheAceBenefactors::TAXONOMY ) ) {
				if ( \has_term( $benefactor_term_id, CatchTheAceBenefactors::TAXONOMY, $this->post_id ) ) {
					$term = \get_term( $benefactor_term_id, CatchTheAceBenefactors::TAXONOMY );
					if ( $term instanceof \WP_Term ) {
						$benefactor_label = (string) $term->name;
					}
				} else {
					$benefactor_term_id = 0;
					$cart_result['warnings'][] = \__( 'Selected benefactor is not available for this session. Defaulted to all benefactors.', 'ace-the-catch' );
				}
			}

			$orders->set_benefactor( $order_id, $benefactor_term_id, $benefactor_label );
		}

		$processor = $this->checkout->get_processor_instance();
		if ( ! $processor ) {
			if ( $order_id > 0 ) {
				$orders->append_log( $order_id, \__( 'Place order failed: payment processor not configured.', 'ace-the-catch' ) );
			}
			return array(
				'success'     => false,
				'notice'      => array(
					'type'     => 'error',
					'message'  => \__( 'Payment processor is not configured. Please contact the site administrator.', 'ace-the-catch' ),
					'warnings' => $cart_result['warnings'],
				),
				'cart_result' => $cart_result,
			);
		}

		$amount   = (float) ( $cart_result['total_amount'] ?? 0 );

		if ( $amount <= 0 ) {
			if ( $order_id > 0 ) {
				$orders->append_log( $order_id, \__( 'Place order failed: invalid amount.', 'ace-the-catch' ) );
			}
			return array(
				'success'     => false,
				'notice'      => array(
					'type'     => 'error',
					'message'  => \__( 'Payment amount is invalid. Please contact the site administrator.', 'ace-the-catch' ),
					'warnings' => $cart_result['warnings'],
				),
				'cart_result' => $cart_result,
			);
		}

		if ( $order_id > 0 ) {
			\update_post_meta( $order_id, CatchTheAceOrders::META_ORDER_TOTAL, $amount );
			\update_post_meta( $order_id, CatchTheAceOrders::META_ORDER_CURRENCY, $currency );
		}

		if ( ! $processor->supports_currency( $currency ) ) {
			if ( $order_id > 0 ) {
				$orders->append_log( $order_id, \__( 'Place order failed: unsupported currency.', 'ace-the-catch' ) );
			}
			return array(
				'success'     => false,
				'notice'      => array(
					'type'     => 'error',
					'message'  => \__( 'This payment method does not support the selected currency.', 'ace-the-catch' ),
					'warnings' => $cart_result['warnings'],
				),
				'cart_result' => $cart_result,
			);
		}

		$items = array();
		foreach ( $cart_result['cart_items'] as $item ) {
			$env = isset( $item['envelope'] ) ? (int) $item['envelope'] : 0;
			$qty = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			if ( $env > 0 && $qty > 0 ) {
				$items[ $env ] = $qty;
			}
		}

		$order_number = 0;
		if ( $order_id > 0 ) {
			$order_number = (int) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_NUMBER, true );
		}

		$payment_reference = '';
		if ( $order_id > 0 ) {
			$payment_reference = (string) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_PAYMENT_REFERENCE, true );
		}

		$payload = array(
			'amount'      => $amount,
			'currency'    => $currency,
			'description' => \sprintf( \__( 'Catch the Ace purchase (Session #%d)', 'ace-the-catch' ), $this->post_id ),
			'order_id'    => $order_id,
			'order_number'=> $order_number,
			'reference'   => $payment_reference,
			'customer'    => array(
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'email'      => $email,
			),
			'items'       => $items,
		);

		$stripe_token = isset( $_POST['stripe_token'] ) ? \sanitize_text_field( (string) \wp_unslash( $_POST['stripe_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $stripe_token ) {
			$payload['stripe_token'] = $stripe_token;
		}

		if ( $order_id > 0 ) {
			$orders->set_order_status( $order_id, CatchTheAceOrders::STATUS_IN_PROCESS );
			$orders->append_log( $order_id, \__( 'Payment processing started.', 'ace-the-catch' ) );
			$orders->touch_order( $order_id );
		}

		$config = $this->checkout->get_processor_config();
		$payload = $processor->attach_metadata( $payload );
		$payment_result = $processor->process_payment( $payload, $config );

		$status    = isset( $payment_result['status'] ) ? (string) $payment_result['status'] : 'failed';
		$reference = isset( $payment_result['reference'] ) ? (string) $payment_result['reference'] : '';
		$error     = isset( $payment_result['error'] ) ? (string) $payment_result['error'] : \__( 'Payment failed.', 'ace-the-catch' );

		if ( 'succeeded' === $status ) {
			if ( $order_id > 0 ) {
				$orders->set_payment( $order_id, \get_option( CatchTheAceSettings::OPTION_PAYMENT_PROC, '' ), $reference );
				$orders->set_order_status( $order_id, CatchTheAceOrders::STATUS_COMPLETED );
				$orders->append_log(
					$order_id,
					$reference
						? \sprintf( \__( 'Payment completed (reference: %s).', 'ace-the-catch' ), $reference )
						: \__( 'Payment completed.', 'ace-the-catch' )
				);
				Plugin::instance()->get_emails()->send_successful_transaction_email( $order_id, $reference );
			}
			return array(
				'success'     => true,
				'notice'      => array(
					'type'      => 'success',
					'message'   => \__( 'Payment successful. Thank you for your purchase!', 'ace-the-catch' ),
					'reference' => $reference,
					'warnings'  => $cart_result['warnings'],
				),
				'cart_result' => $cart_result,
			);
		}

		if ( $order_id > 0 ) {
			$orders->set_payment( $order_id, \get_option( CatchTheAceSettings::OPTION_PAYMENT_PROC, '' ), $reference );
			$orders->set_order_status( $order_id, CatchTheAceOrders::STATUS_FAILED );
			$orders->append_log( $order_id, $reference ? \sprintf( \__( 'Payment failed (reference: %1$s): %2$s', 'ace-the-catch' ), $reference, $error ) : \sprintf( \__( 'Payment failed: %s', 'ace-the-catch' ), $error ) );
			$orders->touch_order( $order_id );
		}

		return array(
			'success'     => false,
			'notice'      => array(
				'type'      => 'error',
				'message'   => $error,
				'reference' => $reference,
				'warnings'  => $cart_result['warnings'],
			),
			'cart_result' => $cart_result,
		);
	}
}
