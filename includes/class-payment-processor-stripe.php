<?php
/**
 * Stripe payment processor.
 *
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

class StripePaymentProcessor implements PaymentProcessor {

	/**
	 * Stripe currencies with zero decimal minor units.
	 *
	 * @var string[]
	 */
	private array $zero_decimal_currencies = array(
		'bif',
		'clp',
		'djf',
		'gnf',
		'jpy',
		'kmf',
		'krw',
		'mga',
		'pyg',
		'rwf',
		'ugx',
		'vnd',
		'vuv',
		'xaf',
		'xof',
		'xpf',
	);

	/**
	 * Stripe currencies with three decimal minor units.
	 *
	 * @var string[]
	 */
	private array $three_decimal_currencies = array(
		'bhd',
		'jod',
		'kwd',
		'omr',
		'tnd',
	);

	public function get_id(): string {
		return 'stripe';
	}

	public function get_label(): string {
		return \__( 'Stripe', 'ace-the-catch' );
	}

	public function supports_currency( string $currency ): bool {
		// Rely on Stripe's own currency support; assume CAD/USD for this context.
		return true;
	}

	/**
	 * Normalize an items payload into an envelope => qty map.
	 *
	 * @param mixed $items Items payload.
	 * @return array<int,int>
	 */
	private function normalize_items_map( $items ): array {
		if ( ! \is_array( $items ) ) {
			return array();
		}

		$map = array();
		foreach ( $items as $key => $item ) {
			// Allow associative envelope => qty.
			if ( \is_numeric( $key ) && ( \is_numeric( $item ) || \is_string( $item ) ) ) {
				$env = (int) $key;
				$qty = (int) $item;
				if ( $env > 0 && $qty > 0 ) {
					$map[ $env ] = $qty;
				}
				continue;
			}

			// Allow list items with envelope/quantity.
			if ( \is_array( $item ) ) {
				$env = isset( $item['envelope'] ) ? (int) $item['envelope'] : 0;
				$qty = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
				if ( $env > 0 && $qty > 0 ) {
					$map[ $env ] = $qty;
				}
			}
		}

		if ( ! empty( $map ) ) {
			\ksort( $map );
		}

		return $map;
	}

	/**
	 * Convert a float amount to the integer minor-unit amount Stripe expects.
	 *
	 * @param float  $amount Amount in major units.
	 * @param string $currency Currency code.
	 * @return int
	 */
	private function format_amount_minor_units( float $amount, string $currency ): int {
		$currency = strtolower( trim( $currency ) );
		if ( '' === $currency ) {
			$currency = 'cad';
		}

		if ( \in_array( $currency, $this->zero_decimal_currencies, true ) ) {
			return (int) round( $amount );
		}

		if ( \in_array( $currency, $this->three_decimal_currencies, true ) ) {
			return (int) round( $amount * 1000 );
		}

		return (int) round( $amount * 100 );
	}

	/**
	 * Perform a Stripe API request.
	 *
	 * @param string $method HTTP method.
	 * @param string $url Full endpoint URL.
	 * @param string $secret_key Stripe secret key.
	 * @param array  $body Request body.
	 * @param array  $headers Additional headers.
	 * @return array{code:int,data:array,error:string}
	 */
	private function stripe_request( string $method, string $url, string $secret_key, array $body = array(), array $headers = array() ): array {
		$response = \wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'timeout' => 10,
				'headers' => \array_merge(
					array(
						'Authorization' => 'Bearer ' . $secret_key,
					),
					$headers
				),
				'body'    => $body,
			)
		);

		if ( \is_wp_error( $response ) ) {
			return array(
				'code'  => 0,
				'data'  => array(),
				'error' => $response->get_error_message(),
			);
		}

		$code = (int) \wp_remote_retrieve_response_code( $response );
		$raw  = (string) \wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! \is_array( $data ) ) {
			$data = array();
		}

		$error = '';
		if ( $code >= 400 ) {
			$error = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : \__( 'Stripe request failed.', 'ace-the-catch' );
		}

		return array(
			'code'  => $code,
			'data'  => $data,
			'error' => $error,
		);
	}

	/**
	 * Sanitize a metadata key for Stripe (avoid special characters like "#").
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private function sanitize_metadata_key( string $key ): string {
		$key = trim( $key );
		if ( '' === $key ) {
			return '';
		}

		$key = preg_replace( '/[^a-zA-Z0-9_\-]/', '_', $key );
		$key = trim( (string) $key, '_' );

		if ( strlen( $key ) > 40 ) {
			$key = substr( $key, 0, 40 );
		}

		return $key;
	}

	public function attach_metadata( array $payload ): array {
		$metadata = array();
		if ( isset( $payload['metadata'] ) && \is_array( $payload['metadata'] ) ) {
			foreach ( $payload['metadata'] as $key => $value ) {
				$key   = $this->sanitize_metadata_key( (string) $key );
				$value = (string) $value;
				if ( '' !== $key && '' !== $value ) {
					$metadata[ $key ] = $value;
				}
			}
		}

		$order_number = isset( $payload['order_number'] ) ? (int) $payload['order_number'] : 0;
		if ( $order_number > 0 ) {
			$metadata['order_number'] = (string) $order_number;
		}

		$order_id = isset( $payload['order_id'] ) ? (int) $payload['order_id'] : 0;
		if ( $order_id > 0 ) {
			$metadata['order_id'] = (string) $order_id;
		}

		$customer = isset( $payload['customer'] ) && \is_array( $payload['customer'] ) ? $payload['customer'] : array();
		$first    = isset( $customer['first_name'] ) ? \sanitize_text_field( (string) $customer['first_name'] ) : '';
		$last     = isset( $customer['last_name'] ) ? \sanitize_text_field( (string) $customer['last_name'] ) : '';
		$name     = isset( $customer['name'] ) ? \sanitize_text_field( (string) $customer['name'] ) : '';
		$email    = isset( $customer['email'] ) ? \sanitize_email( (string) $customer['email'] ) : '';

		if ( empty( $name ) ) {
			$name = \trim( $first . ' ' . $last );
		}

		if ( $name ) {
			$metadata['customer_name'] = $name;
		}
		if ( $email ) {
			$metadata['customer_email'] = $email;
		}

		$items = $this->normalize_items_map( $payload['items'] ?? array() );
		if ( ! empty( $items ) ) {
			$max_keys = 45;
			$count    = 0;
			$labels   = array();
			foreach ( $items as $env => $qty ) {
				$labels[] = sprintf( 'Envelope #%d: %d', (int) $env, (int) $qty );

				if ( $count < $max_keys ) {
					$metadata[ 'envelope_' . (int) $env ] = (string) (int) $qty;
					$count++;
				}
			}

			$metadata['envelopes'] = implode( ', ', $labels );
		}

		$payload['metadata'] = $metadata;
		return $payload;
	}

	/**
	 * Create or update a Stripe PaymentIntent for the current order.
	 *
	 * @param array $payload Order/payment context.
	 * @param array $config  Stripe configuration (secret key).
	 * @return array
	 */
	public function sync_order_payment( array $payload, array $config = array() ): array {
		$payload = $this->attach_metadata( $payload );

		$secret_key  = isset( $config['secret_key'] ) ? (string) $config['secret_key'] : '';
		$amount      = isset( $payload['amount'] ) ? (float) $payload['amount'] : 0.0;
		$currency    = isset( $payload['currency'] ) ? strtolower( (string) $payload['currency'] ) : 'cad';
		$description = isset( $payload['description'] ) ? (string) $payload['description'] : '';
		$metadata    = isset( $payload['metadata'] ) && \is_array( $payload['metadata'] ) ? $payload['metadata'] : array();
		$reference   = isset( $payload['reference'] ) ? (string) $payload['reference'] : '';

		$customer = isset( $payload['customer'] ) && \is_array( $payload['customer'] ) ? $payload['customer'] : array();
		$email    = isset( $customer['email'] ) ? \sanitize_email( (string) $customer['email'] ) : '';

		if ( $amount <= 0 ) {
			return array(
				'reference' => $reference,
				'status'    => '',
				'error'     => '',
			);
		}

		if ( empty( $secret_key ) ) {
			return array(
				'reference' => $reference,
				'status'    => 'failed',
				'error'     => \__( 'Stripe secret key is missing.', 'ace-the-catch' ),
			);
		}

		$amount_minor = $this->format_amount_minor_units( $amount, $currency );

		$create_body = array(
			'amount'                => $amount_minor,
			'currency'              => $currency,
			'description'           => $description,
			'metadata'              => $metadata,
			'payment_method_types[]'=> 'card',
		);
		if ( $email ) {
			$create_body['receipt_email'] = $email;
		}

		$headers = array();
		$order_id = isset( $payload['order_id'] ) ? (int) $payload['order_id'] : 0;
		if ( $order_id > 0 ) {
			$headers['Idempotency-Key'] = 'cta-order-' . $order_id;
		}

		if ( $reference ) {
			$update_body = array(
				'amount'      => $amount_minor,
				'description' => $description,
				'metadata'    => $metadata,
			);
			if ( $email ) {
				$update_body['receipt_email'] = $email;
			}

			$update = $this->stripe_request(
				'POST',
				'https://api.stripe.com/v1/payment_intents/' . rawurlencode( $reference ),
				$secret_key,
				$update_body
			);

			if ( $update['code'] < 400 && ! empty( $update['data']['id'] ) ) {
				return array(
					'reference'     => (string) $update['data']['id'],
					'client_secret' => isset( $update['data']['client_secret'] ) ? (string) $update['data']['client_secret'] : '',
					'status'        => isset( $update['data']['status'] ) ? (string) $update['data']['status'] : '',
					'error'         => '',
				);
			}
		}

		$create = $this->stripe_request(
			'POST',
			'https://api.stripe.com/v1/payment_intents',
			$secret_key,
			$create_body,
			$headers
		);

		if ( $create['code'] >= 400 || empty( $create['data']['id'] ) ) {
			return array(
				'reference' => '',
				'status'    => 'failed',
				'error'     => $create['error'] ?: \__( 'Unable to create Stripe PaymentIntent.', 'ace-the-catch' ),
			);
		}

		return array(
			'reference'     => (string) $create['data']['id'],
			'client_secret' => isset( $create['data']['client_secret'] ) ? (string) $create['data']['client_secret'] : '',
			'status'        => isset( $create['data']['status'] ) ? (string) $create['data']['status'] : '',
			'error'         => '',
		);
	}

	/**
	 * Process a payment payload containing a Stripe token or PaymentMethod id.
	 *
	 * @param array $payload Payment data.
	 * @param array $config  Provider configuration.
	 * @return array
	 */
	public function process_payment( array $payload, array $config = array() ): array {
		$payload = $this->attach_metadata( $payload );

		$token      = isset( $payload['stripe_token'] ) ? (string) $payload['stripe_token'] : '';
		$amount     = isset( $payload['amount'] ) ? (float) $payload['amount'] : 0.0;
		$currency   = isset( $payload['currency'] ) ? strtolower( (string) $payload['currency'] ) : 'cad';
		$secret_key = isset( $config['secret_key'] ) ? (string) $config['secret_key'] : '';

		if ( empty( $token ) ) {
			return array(
				'status'    => 'failed',
				'reference' => '',
				'error'     => \__( 'Stripe token missing.', 'ace-the-catch' ),
			);
		}

		if ( $amount <= 0 || empty( $secret_key ) ) {
			return array(
				'status'    => 'failed',
				'reference' => '',
				'error'     => \__( 'Invalid amount or Stripe secret key.', 'ace-the-catch' ),
			);
		}

		$reference = isset( $payload['reference'] ) ? (string) $payload['reference'] : '';

		$sync = $this->sync_order_payment(
			array_merge(
				$payload,
				array(
					'reference' => $reference,
				)
			),
			$config
		);

		if ( ! empty( $sync['error'] ) || empty( $sync['reference'] ) ) {
			return array(
				'status'    => 'failed',
				'reference' => '',
				'error'     => ! empty( $sync['error'] ) ? (string) $sync['error'] : \__( 'Unable to prepare Stripe PaymentIntent.', 'ace-the-catch' ),
			);
		}

		$intent_id = (string) $sync['reference'];

		$customer = isset( $payload['customer'] ) && \is_array( $payload['customer'] ) ? $payload['customer'] : array();
		$first    = isset( $customer['first_name'] ) ? \sanitize_text_field( (string) $customer['first_name'] ) : '';
		$last     = isset( $customer['last_name'] ) ? \sanitize_text_field( (string) $customer['last_name'] ) : '';
		$email    = isset( $customer['email'] ) ? \sanitize_email( (string) $customer['email'] ) : '';
		$name     = trim( $first . ' ' . $last );

		$confirm_body = array(
			'payment_method_data[type]'        => 'card',
			'payment_method_data[card][token]' => $token,
		);
		if ( $name ) {
			$confirm_body['payment_method_data[billing_details][name]'] = $name;
		}
		if ( $email ) {
			$confirm_body['payment_method_data[billing_details][email]'] = $email;
			$confirm_body['receipt_email'] = $email;
		}

		$confirm = $this->stripe_request(
			'POST',
			'https://api.stripe.com/v1/payment_intents/' . rawurlencode( $intent_id ) . '/confirm',
			$secret_key,
			$confirm_body
		);

		if ( $confirm['code'] >= 400 ) {
			return array(
				'status'    => 'failed',
				'reference' => $intent_id,
				'error'     => $confirm['error'] ?: \__( 'Stripe PaymentIntent confirmation failed.', 'ace-the-catch' ),
			);
		}

		$status = isset( $confirm['data']['status'] ) ? (string) $confirm['data']['status'] : '';
		if ( 'succeeded' !== $status ) {
			$error = '';
			if ( 'requires_action' === $status ) {
				$error = \__( 'This card requires additional authentication which is not currently supported. Please try another card.', 'ace-the-catch' );
			} elseif ( isset( $confirm['data']['last_payment_error']['message'] ) ) {
				$error = (string) $confirm['data']['last_payment_error']['message'];
			} else {
				$error = \__( 'Stripe PaymentIntent was not successful.', 'ace-the-catch' );
			}

			return array(
				'status'    => 'failed',
				'reference' => $intent_id,
				'error'     => $error,
			);
		}

		return array(
			'status'    => 'succeeded',
			'reference' => $intent_id,
			'error'     => '',
		);
	}

	public function enqueue_checkout_assets( array $config = array() ): void {
		$publishable_key = isset( $config['publishable_key'] ) ? (string) $config['publishable_key'] : '';
		if ( empty( $publishable_key ) ) {
			return;
		}

		if ( ! \wp_script_is( 'stripe-js', 'registered' ) ) {
			\wp_register_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
		}
		\wp_enqueue_script( 'stripe-js' );

		$js = "(function(){\n\tfunction init(){\n\t\tif(typeof Stripe!=='function'){return;}\n\t\tvar form=document.querySelector('#ace-checkout-form');\n\t\tvar mountEl=document.getElementById('ace-stripe-card');\n\t\tvar tokenEl=document.getElementById('ace-stripe-token');\n\t\tvar errorEl=document.getElementById('ace-stripe-error');\n\t\tif(!form||!mountEl||!tokenEl){return;}\n\t\tif(form.dataset&&form.dataset.stripeInitialized==='1'){return;}\n\t\tvar stripe=Stripe(" . \wp_json_encode( $publishable_key ) . ");\n\t\tvar elements=stripe.elements();\n\t\tvar card=elements.create('card',{hidePostalCode:true});\n\t\tcard.mount(mountEl);\n\t\tif(form.dataset){form.dataset.stripeInitialized='1';}\n\t\tform.addEventListener('submit',function(e){\n\t\t\tif(form.dataset&&form.dataset.stripeSubmitting==='1'){return;}\n\t\t\tif(tokenEl.value){return;}\n\t\t\te.preventDefault();\n\t\t\tif(errorEl){errorEl.textContent='';}\n\t\t\tif(form.dataset){form.dataset.stripeSubmitting='1';}\n\t\t\tstripe.createToken(card).then(function(result){\n\t\t\t\tif(result.error){\n\t\t\t\t\tif(errorEl){errorEl.textContent=result.error.message||'Payment error.';}\n\t\t\t\t\tif(form.dataset){form.dataset.stripeSubmitting='0';}\n\t\t\t\t\treturn;\n\t\t\t\t}\n\t\t\t\ttokenEl.value=result.token.id;\n\t\t\t\tform.submit();\n\t\t\t});\n\t\t});\n\t}\n\tif(document.readyState==='loading'){\n\t\tdocument.addEventListener('DOMContentLoaded',init);\n\t}else{\n\t\tinit();\n\t}\n})();";

		\wp_add_inline_script( 'stripe-js', $js, 'after' );
	}

	public function render_checkout_fields( array $config = array() ): string {
		$publishable_key = isset( $config['publishable_key'] ) ? (string) $config['publishable_key'] : '';
		if ( empty( $publishable_key ) ) {
			return '<p>' . \esc_html__( 'Stripe is not configured.', 'ace-the-catch' ) . '</p>';
		}

		return '
			<div id="ace-stripe-card" class="ace-checkout-form__stripe-card"></div>
			<div id="ace-stripe-error" class="ace-checkout-form__error" role="alert" aria-live="polite"></div>
			<input type="hidden" name="stripe_token" id="ace-stripe-token" value="" />
		';
	}
}
