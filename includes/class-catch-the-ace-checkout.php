<?php
/**
 * Checkout view model builder.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class CatchTheAceCheckout {

	/**
	 * Cookie storing a one-time checkout notice for PRG.
	 */
	private const NOTICE_COOKIE = 'ace_checkout_notice';

	/**
	 * Build the checkout view model for a given session.
	 *
	 * @param int $post_id Session post ID.
	 * @return array{
	 *   back_url:string,
	 *   geo_blocked:bool,
	 *   geo_message:string,
	 *   geo_needs_location:bool,
	 *   notice:array{type:string,message:string,reference?:string,warnings?:array} | array{},
	 *   cart_items:array<int,array{envelope:int,quantity:int,subtotal:float}>,
	 *   warnings:string[],
	 *   total_amount:float,
	 *   processor_label:string,
	 *   processor_key:string,
	 *   stripe_pk:string,
	 *   processor:?PaymentProcessor,
	 *   processor_config:array,
	 *   currency:string,
	 *   customer_first_name:string,
	 *   customer_last_name:string,
	 *   customer_email:string,
	 *   customer_phone:string,
	 *   customer_location:string,
	 *   benefactors:array<int,array{term_id:int,name:string,slug:string}>,
	 *   selected_benefactor:int,
	 *   terms_url:string,
	 *   rules_url:string,
	 *   checkout_url:string
	 * }
	 */
	public function build_view_model( int $post_id ): array {
		$permalink    = \get_permalink( $post_id );
		$back_url     = \trailingslashit( $permalink );
		$ticket_price = (float) \get_option( CatchTheAceSettings::OPTION_TICKET_PRICE, 0 );
		$checkout_url = $this->get_checkout_url( $post_id );
		$notice       = $this->consume_notice_cookie();
		$currency     = $this->get_currency();
		$terms_url    = $this->get_terms_url();
		$rules_url    = $this->get_rules_url();
		$benefactors  = $this->get_session_benefactors( $post_id );

		$geo = $this->evaluate_geo();
		if ( $geo['blocked'] ) {
			return array(
				'back_url'        => $back_url,
				'sales_open'      => false,
				'sales_message'   => '',
				'geo_blocked'     => true,
				'geo_message'     => $geo['message'],
				'geo_needs_location' => (bool) ( $geo['needs_location'] ?? false ),
				'notice'          => $notice,
				'cart_items'      => array(),
				'warnings'        => array(),
				'total_amount'    => 0.0,
				'processor_label' => '',
				'processor_key'   => $this->get_processor_key(),
				'stripe_pk'       => $this->get_stripe_publishable_key(),
				'processor'       => null,
				'processor_config'=> array(),
				'currency'        => $currency,
				'customer_first_name' => '',
				'customer_last_name'  => '',
				'customer_email'      => '',
				'customer_phone'      => '',
				'customer_location'   => '',
				'benefactors'     => $benefactors,
				'selected_benefactor' => 0,
				'terms_url'       => $terms_url,
				'rules_url'       => $rules_url,
				'checkout_url'    => $checkout_url,
			);
		}

		// Handle any checkout-related form submissions before rendering (PRG).
		$requests = array(
			new RequestPlaceOrder( $this, $post_id, $ticket_price ),
			new RequestUpdateCart( $this, $post_id, $ticket_price ),
		);
		foreach ( $requests as $request ) {
			if ( $request->matches() ) {
				$request->handle();
				exit;
			}
		}

		$sales_status  = $this->get_sales_status( $post_id );
		$sales_open    = (bool) ( $sales_status['open'] ?? false );
		$sales_message = isset( $sales_status['message'] ) ? (string) $sales_status['message'] : \__( 'Ticket sales are currently closed.', 'ace-the-catch' );

		// When ticket sales are closed, do not create orders from cart cookies (prevents bypassing client checks).
		if ( ! $sales_open && 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			return array(
				'back_url'        => $back_url,
				'sales_open'      => false,
				'sales_message'   => $sales_message,
				'geo_blocked'     => false,
				'geo_message'     => '',
				'geo_needs_location' => false,
				'notice'          => $notice,
				'cart_items'      => array(),
				'warnings'        => array(),
				'total_amount'    => 0.0,
				'processor_label' => $this->get_processor_label(),
				'processor_key'   => $this->get_processor_key(),
				'stripe_pk'       => $this->get_stripe_publishable_key(),
				'processor'       => $this->get_processor_instance(),
				'processor_config'=> $this->get_processor_config(),
				'currency'        => $currency,
				'customer_first_name' => '',
				'customer_last_name'  => '',
				'customer_email'      => '',
				'customer_phone'      => '',
				'customer_location'   => '',
				'benefactors'     => $benefactors,
				'selected_benefactor' => 0,
				'terms_url'       => $terms_url,
				'rules_url'       => $rules_url,
				'checkout_url'    => $checkout_url,
			);
		}

		$orders = Plugin::instance()->get_orders();
		$order  = $orders->get_current_order( $post_id );

		$raw_cart = array();
		if ( $order ) {
			$raw_cart = $orders->get_order_cart( $order['order_id'] );
		} else {
			// Allow direct /checkout visits by importing the JS cart cookie into a new order.
			$raw_cart = $this->get_cart_from_cookie( $post_id );
			if ( ! empty( $raw_cart ) ) {
				$cart_result = $this->validate_cart( $post_id, $ticket_price, $raw_cart );
				$clean_cart  = $this->extract_cart_map( $cart_result );
				if ( ! empty( $clean_cart ) ) {
					$meta    = array(
						CatchTheAceOrders::META_ORDER_PAYMENT_PROCESSOR => $this->get_processor_key(),
						CatchTheAceOrders::META_ORDER_TOTAL            => (float) ( $cart_result['total_amount'] ?? 0 ),
						CatchTheAceOrders::META_ORDER_CURRENCY         => $currency,
					);
					$created = $orders->create_order( $post_id, $clean_cart, $meta );
					if ( ! empty( $created['order_id'] ) ) {
						$orders->persist_order_cookie( $post_id, (int) $created['order_id'], (string) $created['order_key'] );
						foreach ( $cart_result['warnings'] as $warning ) {
							$orders->append_log( (int) $created['order_id'], (string) $warning );
						}
						$order   = $created;
						$raw_cart = $clean_cart;
					}
				}
			}
		}

		$cart_result = $this->validate_cart( $post_id, $ticket_price, $raw_cart );
		if ( $order ) {
			$clean_cart = $this->extract_cart_map( $cart_result );
			if ( $clean_cart !== $raw_cart ) {
				$orders->update_order_cart( (int) $order['order_id'], $clean_cart, (float) ( $cart_result['total_amount'] ?? 0 ), $currency );
				foreach ( $cart_result['warnings'] as $warning ) {
					$orders->append_log( (int) $order['order_id'], (string) $warning );
				}
			} else {
				$current_currency = (string) \get_post_meta( (int) $order['order_id'], CatchTheAceOrders::META_ORDER_CURRENCY, true );
				if ( $current_currency !== $currency ) {
					\update_post_meta( (int) $order['order_id'], CatchTheAceOrders::META_ORDER_CURRENCY, $currency );
				}
				$orders->touch_order( (int) $order['order_id'] );
			}
		}

		$warnings_raw = \array_merge( $cart_result['warnings'], $notice['warnings'] ?? array() );
		$warnings     = \array_values( \array_unique( \array_map( 'strval', $warnings_raw ) ) );

		$customer_first_name = '';
		$customer_last_name  = '';
		$customer_email      = '';
		$customer_phone      = '';
		$customer_location   = '';
		$selected_benefactor = 0;
		if ( $order && ! empty( $order['order_id'] ) ) {
			$order_id = (int) $order['order_id'];
			$customer_first_name = \sanitize_text_field( (string) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_CUSTOMER_FIRST_NAME, true ) );
			$customer_last_name  = \sanitize_text_field( (string) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_CUSTOMER_LAST_NAME, true ) );
			$customer_email      = \sanitize_email( (string) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_CUSTOMER_EMAIL, true ) );
			$customer_phone      = \sanitize_text_field( (string) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_CUSTOMER_PHONE, true ) );
			$customer_location   = \sanitize_text_field( (string) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_CUSTOMER_LOCATION, true ) );
			$selected_benefactor = (int) \get_post_meta( $order_id, CatchTheAceOrders::META_ORDER_BENEFACTOR_TERM_ID, true );
		}

		return array(
			'back_url'        => $back_url,
			'sales_open'      => $sales_open,
			'sales_message'   => $sales_message,
			'geo_blocked'     => false,
			'geo_message'     => '',
			'geo_needs_location' => false,
			'notice'          => $notice,
			'cart_items'      => $cart_result['cart_items'],
			'warnings'        => $warnings,
			'total_amount'    => $cart_result['total_amount'],
			'processor_label' => $this->get_processor_label(),
			'processor_key'   => $this->get_processor_key(),
			'stripe_pk'       => $this->get_stripe_publishable_key(),
			'processor'       => $this->get_processor_instance(),
			'processor_config'=> $this->get_processor_config(),
			'currency'        => $currency,
			'customer_first_name' => $customer_first_name,
			'customer_last_name'  => $customer_last_name,
			'customer_email'      => $customer_email,
			'customer_phone'      => $customer_phone,
			'customer_location'   => $customer_location,
			'benefactors'     => $benefactors,
			'selected_benefactor' => $selected_benefactor,
			'terms_url'       => $terms_url,
			'rules_url'       => $rules_url,
			'checkout_url'    => $checkout_url,
		);
	}

	/**
	 * Determine ticket sales status for a session.
	 *
	 * @param int $post_id Session post ID.
	 * @return array{open:bool,message:string,close_epoch:int,open_epoch:int}
	 */
	public function get_sales_status( int $post_id ): array {
		$default = array(
			'open'        => false,
			'message'     => \__( 'Ticket sales are currently closed.', 'ace-the-catch' ),
			'close_epoch' => 0,
			'open_epoch'  => 0,
		);

		$dealer = Plugin::instance()->get_envelope_dealer();
		if ( ! ( $dealer instanceof EnvelopeDealer ) ) {
			return $default;
		}

		$status = $dealer->get_sales_status( $post_id );
		if ( ! \is_array( $status ) ) {
			return $default;
		}

		return array(
			'open'        => ! empty( $status['open'] ),
			'message'     => isset( $status['message'] ) ? (string) $status['message'] : $default['message'],
			'close_epoch' => isset( $status['close_epoch'] ) ? (int) $status['close_epoch'] : 0,
			'open_epoch'  => isset( $status['open_epoch'] ) ? (int) $status['open_epoch'] : 0,
		);
	}

	/**
	 * Convert a validate_cart() result to a normalized envelope => qty map.
	 *
	 * @param array $cart_result Cart result from validate_cart().
	 * @return array<int,int>
	 */
	private function extract_cart_map( array $cart_result ): array {
		$map = array();
		if ( empty( $cart_result['cart_items'] ) || ! \is_array( $cart_result['cart_items'] ) ) {
			return $map;
		}

		foreach ( $cart_result['cart_items'] as $item ) {
			$env = isset( $item['envelope'] ) ? (int) $item['envelope'] : 0;
			$qty = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			if ( $env > 0 && $qty > 0 ) {
				$map[ $env ] = $qty;
			}
		}

		return $map;
	}

	/**
	 * Persist a one-time notice cookie for checkout PRG.
	 *
	 * @param array $notice Notice payload.
	 * @return void
	 */
	public function persist_notice_cookie( array $notice ): void {
		$json = \wp_json_encode( $notice );
		if ( false === $json ) {
			return;
		}
		$this->set_cookie( self::NOTICE_COOKIE, \rawurlencode( $json ), time() + 120, true );
	}

	/**
	 * Consume the checkout notice cookie (read and clear).
	 *
	 * @return array
	 */
	private function consume_notice_cookie(): array {
		if ( empty( $_COOKIE[ self::NOTICE_COOKIE ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			return array();
		}

		$json = \urldecode( (string) $_COOKIE[ self::NOTICE_COOKIE ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = \json_decode( $json, true );
		$this->set_cookie( self::NOTICE_COOKIE, '', time() - 3600, true );

		return \is_array( $data ) ? $data : array();
	}

	/**
	 * Determine geo eligibility via selected locator.
	 *
	 * @return array{blocked:bool,message:string,needs_location?:bool}
	 */
	private function evaluate_geo(): array {
		$blocked      = false;
		$default_msg  = \__( 'Ticket sales are not available in your region.', 'ace-the-catch' );
		$message      = \get_option( CatchTheAceSettings::OPTION_OUTSIDE_MESSAGE, $default_msg );
		$locator_key  = \get_option( CatchTheAceSettings::OPTION_GEO_LOCATOR, '' );
		$locator_cfg  = \get_option( CatchTheAceSettings::OPTION_GEO_LOCATOR_CFG, array() );
		$needs_location = false;

		if ( empty( $locator_key ) ) {
			return array( 'blocked' => false, 'message' => $message, 'needs_location' => false );
		}

		$factory = Plugin::instance()->get_geo_locator_factory();
		$locator = $factory->create( $locator_key );
		if ( ! $locator ) {
			return array( 'blocked' => false, 'message' => $message, 'needs_location' => false );
		}

		$config = ( \is_array( $locator_cfg ) && isset( $locator_cfg[ $locator_key ] ) && \is_array( $locator_cfg[ $locator_key ] ) )
			? $locator_cfg[ $locator_key ]
			: array();

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$result = $locator->locate(
			array(
				'ip'     => $ip,
				'config' => $config,
			)
		);

		$needs_location = isset( $result['needs_location'] ) ? (bool) $result['needs_location'] : false;

		if ( isset( $result['in_ontario'] ) && ! $result['in_ontario'] ) {
			$blocked = true;
		}

		return array(
			'blocked' => $blocked,
			'message' => $message,
			'needs_location' => $needs_location,
		);
	}

	/**
	 * Get envelopes that are already used (winners).
	 *
	 * @param int $post_id Session post ID.
	 * @return array<int,bool>
	 */
	private function get_used_envelopes( int $post_id ): array {
		$used = array();
		if ( ! \function_exists( 'get_field' ) ) {
			return $used;
		}
		$winning_draws = \get_field( 'winning_draws', $post_id );
		if ( \is_array( $winning_draws ) ) {
			foreach ( $winning_draws as $draw ) {
				$env = isset( $draw['selected_envelope'] ) ? (int) $draw['selected_envelope'] : 0;
				if ( $env ) {
					$used[ $env ] = true;
				}
			}
		}
		return $used;
	}

	/**
	 * Get configured payment processor label.
	 *
	 * @return string
	 */
	private function get_processor_label(): string {
		$processor_key = \get_option( CatchTheAceSettings::OPTION_PAYMENT_PROC, '' );
		if ( empty( $processor_key ) ) {
			return '';
		}
		$pp_factory  = Plugin::instance()->get_payment_processor_factory();
		$pp_instance = $pp_factory->create( $processor_key );
		return $pp_instance ? $pp_instance->get_label() : '';
	}

	/**
	 * Get configured payment processor key.
	 *
	 * @return string
	 */
	private function get_processor_key(): string {
		return \get_option( CatchTheAceSettings::OPTION_PAYMENT_PROC, '' );
	}

	/**
	 * Get Stripe publishable key if configured.
	 *
	 * @return string
	 */
	private function get_stripe_publishable_key(): string {
		$configs = \get_option( CatchTheAceSettings::OPTION_PAYMENT_PROC_CFG, array() );
		if ( ! \is_array( $configs ) || empty( $configs['stripe']['publishable_key'] ) ) {
			return '';
		}
		return (string) $configs['stripe']['publishable_key'];
	}

	/**
	 * Get configured payment processor instance.
	 *
	 * @return PaymentProcessor|null
	 */
	public function get_processor_instance(): ?PaymentProcessor {
		$key      = $this->get_processor_key();
		$factory  = Plugin::instance()->get_payment_processor_factory();
		$instance = $key ? $factory->create( $key ) : null;
		return $instance instanceof PaymentProcessor ? $instance : null;
	}

	/**
	 * Get config for the current processor.
	 *
	 * @return array
	 */
	public function get_processor_config(): array {
		$key     = $this->get_processor_key();
		$configs = \get_option( CatchTheAceSettings::OPTION_PAYMENT_PROC_CFG, array() );
		if ( ! $key || ! \is_array( $configs ) || empty( $configs[ $key ] ) || ! \is_array( $configs[ $key ] ) ) {
			return array();
		}
		return $configs[ $key ];
	}

	/**
	 * Get the configured currency code for the current payment processor.
	 *
	 * @return string
	 */
	public function get_currency(): string {
		$config   = $this->get_processor_config();
		$currency = isset( $config['currency'] ) ? (string) $config['currency'] : '';
		$currency = strtolower( trim( $currency ) );

		if ( '' === $currency ) {
			$currency = 'cad';
		}

		if ( ! preg_match( '/^[a-z]{3}$/', $currency ) ) {
			$currency = 'cad';
		}

		return $currency;
	}

	/**
	 * Get the configured Terms & Conditions URL.
	 *
	 * @return string
	 */
	private function get_terms_url(): string {
		$url = (string) \get_option( CatchTheAceSettings::OPTION_TERMS_URL, '' );
		$url = trim( $url );
		return $url;
	}

	/**
	 * Get the configured Rules of Play URL.
	 *
	 * @return string
	 */
	private function get_rules_url(): string {
		$url = (string) \get_option( CatchTheAceSettings::OPTION_RULES_URL, '' );
		$url = trim( $url );
		return $url;
	}

	/**
	 * Get benefactor terms assigned to this session.
	 *
	 * @param int $post_id Session post ID.
	 * @return array<int,array{term_id:int,name:string,slug:string}>
	 */
	private function get_session_benefactors( int $post_id ): array {
		if ( ! \taxonomy_exists( CatchTheAceBenefactors::TAXONOMY ) ) {
			return array();
		}

		$terms = \get_the_terms( $post_id, CatchTheAceBenefactors::TAXONOMY );
		if ( empty( $terms ) || \is_wp_error( $terms ) || ! \is_array( $terms ) ) {
			return array();
		}

		$list = array();
		foreach ( $terms as $term ) {
			if ( ! ( $term instanceof \WP_Term ) ) {
				continue;
			}

			$list[] = array(
				'term_id' => (int) $term->term_id,
				'name'    => (string) $term->name,
				'slug'    => (string) $term->slug,
			);
		}

		usort(
			$list,
			static function( array $a, array $b ): int {
				return strcasecmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
			}
		);

		return $list;
	}

	/**
	 * Attempt to read cart data from cookie for direct /checkout access.
	 *
	 * @param int $post_id Session post ID.
	 * @return array
	 */
	private function get_cart_from_cookie( int $post_id ): array {
		if ( empty( $_COOKIE['ace_cart_state'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			return array();
		}

		$raw = (string) $_COOKIE['ace_cart_state']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$json = \urldecode( $raw );
		$data = \json_decode( $json, true );

		if ( ! \is_array( $data ) ) {
			return array();
		}

		// Ensure the cart matches this session/week.
		$session_id   = isset( $data['sessionId'] ) ? (int) $data['sessionId'] : 0;
		$session_week = isset( $data['sessionWeek'] ) ? (int) $data['sessionWeek'] : 0;
		if ( $session_id !== $post_id ) {
			return array();
		}

		if ( empty( $data['cart'] ) || ! \is_array( $data['cart'] ) ) {
			return array();
		}

		$cart = array();
		foreach ( $data['cart'] as $env => $item ) {
			$env_num = (int) $env;
			$qty     = isset( $item['entries'] ) ? (int) $item['entries'] : 0;
			if ( $env_num > 0 && $qty > 0 ) {
				$cart[ $env_num ] = $qty;
			}
		}

		return $cart;
	}

	/**
	 * Read cart persisted during checkout (sanitized) to avoid resubmission.
	 *
	 * @param int $post_id Session post ID.
	 * @return array{cart:array,warnings:array}
	 */
	private function get_checkout_cart_from_cookie( int $post_id ): array {
		if ( empty( $_COOKIE['ace_checkout_cart'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			return array(
				'cart'     => array(),
				'warnings' => array(),
			);
		}

		$json = \urldecode( (string) $_COOKIE['ace_checkout_cart'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = \json_decode( $json, true );
		if ( ! \is_array( $data ) ) {
			return array(
				'cart'     => array(),
				'warnings' => array(),
			);
		}

		if ( (int) ( $data['sessionId'] ?? 0 ) !== $post_id ) {
			return array(
				'cart'     => array(),
				'warnings' => array(),
			);
		}

		return array(
			'cart'     => isset( $data['cart'] ) && \is_array( $data['cart'] ) ? $data['cart'] : array(),
			'warnings' => isset( $data['warnings'] ) && \is_array( $data['warnings'] ) ? $data['warnings'] : array(),
		);
	}

	/**
	 * Persist processed checkout cart in a cookie for PRG.
	 *
	 * @param int   $post_id Session post ID.
	 * @param array $cart_result Result of validate_cart().
	 * @return void
	 */
	public function persist_checkout_cart_cookie( int $post_id, array $cart_result ): void {
		$cart = array();
		if ( isset( $cart_result['cart_items'] ) && \is_array( $cart_result['cart_items'] ) ) {
			foreach ( $cart_result['cart_items'] as $item ) {
				$env = isset( $item['envelope'] ) ? (int) $item['envelope'] : 0;
				$qty = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
				if ( $env > 0 && $qty > 0 ) {
					$cart[ $env ] = $qty;
				}
			}
		}
		$warnings = isset( $cart_result['warnings'] ) && \is_array( $cart_result['warnings'] ) ? $cart_result['warnings'] : array();

		$payload = array(
			'sessionId' => $post_id,
			'cart'      => $cart,
			'warnings'  => $warnings,
		);
		$json = \json_encode( $payload );
		if ( false === $json ) {
			return;
		}
		$this->set_cookie( 'ace_checkout_cart', \rawurlencode( $json ), time() + 600, true );
	}

	/**
	 * Clear persisted cart cookies after successful checkout.
	 *
	 * @return void
	 */
	public function clear_cart_cookies(): void {
		Plugin::instance()->get_orders()->clear_order_cookie();
		$this->set_cookie( 'ace_checkout_cart', '', time() - 3600, true );
		$this->set_cookie( 'ace_cart_state', '', time() - 3600, false );
	}

	/**
	 * Set a cookie with consistent security flags.
	 *
	 * @param string $name Cookie name.
	 * @param string $value Cookie value.
	 * @param int    $expires Unix timestamp.
	 * @param bool   $http_only Whether to set HttpOnly.
	 * @return void
	 */
	private function set_cookie( string $name, string $value, int $expires, bool $http_only ): void {
		$secure = \is_ssl();

		if ( \defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID >= 70300 ) {
			\setcookie(
				$name,
				$value,
				array(
					'expires'  => $expires,
					'path'     => '/',
					'secure'   => $secure,
					'httponly' => $http_only,
					'samesite' => 'Lax',
				)
			);
		} else {
			\setcookie( $name, $value, $expires, '/; samesite=Lax', '', $secure, $http_only );
		}

		if ( '' === $value || time() > $expires ) {
			unset( $_COOKIE[ $name ] );
		} else {
			$_COOKIE[ $name ] = $value;
		}
	}

	/**
	 * Normalize raw cart input to envelope => qty pairs.
	 *
	 * @param array $raw Raw POST cart.
	 * @return array
	 */
	public function sanitize_raw_cart( array $raw ): array {
		$cart = array();
		foreach ( $raw as $env => $qty ) {
			$env_num = (int) $env;
			$qty_num = (int) $qty;
			if ( $env_num > 0 && $qty_num > 0 ) {
				$cart[ $env_num ] = $qty_num;
			}
		}
		return $cart;
	}

	/**
	 * Validate a raw cart against winning envelopes and compute totals.
	 *
	 * @param int   $post_id Session post ID.
	 * @param float $ticket_price Ticket price.
	 * @param array $raw_cart Envelope => qty.
	 * @return array{cart_items:array,warnings:array,total_amount:float}
	 */
	public function validate_cart( int $post_id, float $ticket_price, array $raw_cart ): array {
		$used_envelopes = $this->get_used_envelopes( $post_id );
		$cart_items     = array();
		$warnings       = array();
		$total_amount   = 0.0;

		foreach ( $raw_cart as $env_num => $qty_num ) {
			$env_num = (int) $env_num;
			$qty_num = (int) $qty_num;
			if ( $env_num <= 0 || $qty_num <= 0 ) {
				continue;
			}
			if ( isset( $used_envelopes[ $env_num ] ) ) {
				$warnings[] = \sprintf( __( 'Envelope #%d was removed because it is no longer available.', 'ace-the-catch' ), $env_num );
				continue;
			}
			$subtotal               = $qty_num * $ticket_price;
			$total_amount          += $subtotal;
			$cart_items[ $env_num ] = array(
				'envelope' => $env_num,
				'quantity' => $qty_num,
				'subtotal' => $subtotal,
			);
		}

		return array(
			'cart_items'   => $cart_items,
			'warnings'     => $warnings,
			'total_amount' => $total_amount,
		);
	}

	/**
	 * Build the checkout URL for this session.
	 *
	 * @param int $post_id Session post ID.
	 * @return string
	 */
	public function get_checkout_url( int $post_id ): string {
		$permalink = \get_permalink( $post_id );
		return \trailingslashit( $permalink . 'checkout' );
	}
}
