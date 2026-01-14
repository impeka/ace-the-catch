<?php
/**
 * Checkout template for Catch the Ace session.
 *
 * @package Impeka\Lotto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id        = get_the_ID();
$checkout_model = new \Impeka\Lotto\CatchTheAceCheckout();
$view           = $checkout_model->build_view_model( $post_id );

$geo_needs_location = ! empty( $view['geo_needs_location'] );

if ( $view['geo_blocked'] && ! $geo_needs_location ) {
	wp_safe_redirect( $view['back_url'] );
	exit;
}

$back_url         = $view['back_url'];
$cart_items       = $view['cart_items'];
$warnings         = $view['warnings'];
$total_amount     = $view['total_amount'];
$notice           = $view['notice'] ?? array();
$processor_label  = $view['processor_label'];
$processor_key    = $view['processor_key'];
$stripe_pk        = $view['stripe_pk'];
$processor        = $view['processor'];
$processor_config = $view['processor_config'];
$currency         = $view['currency'];
$customer_first   = $view['customer_first_name'] ?? '';
$customer_last    = $view['customer_last_name'] ?? '';
$customer_email   = $view['customer_email'] ?? '';
$customer_phone   = $view['customer_phone'] ?? '';
$customer_location = $view['customer_location'] ?? '';
$benefactors      = $view['benefactors'] ?? array();
$selected_benefactor = (int) ( $view['selected_benefactor'] ?? 0 );
$terms_url        = $view['terms_url'] ?? '';

get_header();

?>

<main id="primary" class="site-main">
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'catch-the-ace__wrapper' ); ?>>
		<header class="entry-header">
			<h1 class="entry-title"><?php the_title(); ?> — <?php esc_html_e( 'Checkout', 'ace-the-catch' ); ?></h1>
		</header>

		<div class="entry-content">
			<?php if ( ! empty( $notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( ( 'success' === ( $notice['type'] ?? '' ) ) ? 'success' : 'error' ); ?>">
					<p><?php echo esc_html( (string) $notice['message'] ); ?></p>
					<?php if ( ! empty( $notice['reference'] ) ) : ?>
						<p><small><?php echo esc_html( sprintf( __( 'Reference: %s', 'ace-the-catch' ), (string) $notice['reference'] ) ); ?></small></p>
					<?php endif; ?>
				</div>
				<?php if ( 'success' === ( $notice['type'] ?? '' ) ) : ?>
					<script>
						try { window.localStorage && window.localStorage.removeItem('ace_cart_state'); } catch (e) {}
					</script>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( ! empty( $warnings ) ) : ?>
				<div class="notice notice-warning">
					<?php foreach ( $warnings as $msg ) : ?>
						<p><?php echo esc_html( $msg ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $geo_needs_location ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'To continue, please allow location access so we can verify you are within Ontario.', 'ace-the-catch' ); ?></p>
				</div>
				<div class="ace-geo-gate" data-ace-geo-gate="1" hidden></div>
				<p>
					<button type="button" class="button button-primary ace-geo-request"><?php esc_html_e( 'Enable location', 'ace-the-catch' ); ?></button>
					<a class="button" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Return to game', 'ace-the-catch' ); ?></a>
				</p>
			<?php elseif ( empty( $cart_items ) ) : ?>
				<?php if ( 'success' !== ( $notice['type'] ?? '' ) ) : ?>
					<p><?php esc_html_e( 'Your cart is empty or invalid. Please go back and select envelopes.', 'ace-the-catch' ); ?></p>
				<?php endif; ?>
				<p><a class="button" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Return to game', 'ace-the-catch' ); ?></a></p>
			<?php else : ?>
				<h2><?php esc_html_e( 'Order Summary', 'ace-the-catch' ); ?></h2>
				<table class="ace-cart__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Envelope', 'ace-the-catch' ); ?></th>
							<th><?php esc_html_e( 'Entries', 'ace-the-catch' ); ?></th>
							<th><?php esc_html_e( 'Subtotal', 'ace-the-catch' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cart_items as $item ) : ?>
							<tr>
								<td><?php echo esc_html( '#' . $item['envelope'] ); ?></td>
								<td><?php echo esc_html( $item['quantity'] ); ?></td>
								<td><?php echo esc_html( '$' . number_format_i18n( $item['subtotal'], 2 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th colspan="2" style="text-align:right;"><?php esc_html_e( 'Total', 'ace-the-catch' ); ?></th>
							<th><?php echo esc_html( '$' . number_format_i18n( $total_amount, 2 ) ); ?></th>
						</tr>
					</tfoot>
				</table>

				<h2><?php esc_html_e( 'Checkout Details', 'ace-the-catch' ); ?></h2>
				<form method="post" action="" id="ace-checkout-form" class="ace-checkout-form">
					<input type="hidden" name="view_checkout" value="1" />
					<input type="hidden" name="ace_place_order" value="1" />
					<?php wp_nonce_field( 'ace_place_order', 'ace_place_order_nonce' ); ?>
					<?php foreach ( $cart_items as $item ) : ?>
						<input type="hidden" name="envelope[<?php echo esc_attr( $item['envelope'] ); ?>]" value="<?php echo esc_attr( $item['quantity'] ); ?>" />
					<?php endforeach; ?>

					<div class="ace-checkout-form__grid">
						<p class="ace-checkout-form__field">
							<label for="ace_first_name"><?php esc_html_e( 'First Name', 'ace-the-catch' ); ?></label>
							<input type="text" id="ace_first_name" name="ace_first_name" class="regular-text" value="<?php echo esc_attr( (string) $customer_first ); ?>" required />
						</p>
						<p class="ace-checkout-form__field">
							<label for="ace_last_name"><?php esc_html_e( 'Last Name', 'ace-the-catch' ); ?></label>
							<input type="text" id="ace_last_name" name="ace_last_name" class="regular-text" value="<?php echo esc_attr( (string) $customer_last ); ?>" required />
						</p>
						<p class="ace-checkout-form__field ace-checkout-form__field--full">
							<label for="ace_email"><?php esc_html_e( 'Email', 'ace-the-catch' ); ?></label>
							<input type="email" id="ace_email" name="ace_email" class="regular-text" value="<?php echo esc_attr( (string) $customer_email ); ?>" required />
						</p>

						<p class="ace-checkout-form__field ace-checkout-form__field--full">
							<label for="ace_phone"><?php esc_html_e( 'Telephone', 'ace-the-catch' ); ?></label>
							<input type="tel" id="ace_phone" name="ace_phone" class="regular-text" value="<?php echo esc_attr( (string) $customer_phone ); ?>" required />
						</p>

						<p class="ace-checkout-form__field ace-checkout-form__field--full">
							<label for="ace_location"><?php esc_html_e( 'Your location', 'ace-the-catch' ); ?></label>
							<input type="text" id="ace_location" name="ace_location" class="regular-text" value="<?php echo esc_attr( (string) $customer_location ); ?>" placeholder="<?php echo esc_attr__( 'Town/City', 'ace-the-catch' ); ?>" required />
							<span class="ace-checkout-form__help"><?php esc_html_e( 'General location only (not a full address).', 'ace-the-catch' ); ?></span>
						</p>

						<p class="ace-checkout-form__field ace-checkout-form__field--full">
							<label for="ace_benefactor"><?php esc_html_e( 'Benefactor', 'ace-the-catch' ); ?></label>
							<select id="ace_benefactor" name="ace_benefactor" class="regular-text">
								<option value="0" <?php selected( $selected_benefactor, 0 ); ?>><?php esc_html_e( 'Distribute lottery proceeds amongst ALL charities', 'ace-the-catch' ); ?></option>
								<?php if ( ! empty( $benefactors ) && is_array( $benefactors ) ) : ?>
									<?php foreach ( $benefactors as $benefactor ) : ?>
										<?php
										$term_id = isset( $benefactor['term_id'] ) ? (int) $benefactor['term_id'] : 0;
										$name    = isset( $benefactor['name'] ) ? (string) $benefactor['name'] : '';
										if ( $term_id <= 0 || '' === $name ) {
											continue;
										}
										?>
										<option value="<?php echo esc_attr( (string) $term_id ); ?>" <?php selected( $selected_benefactor, $term_id ); ?>>
											<?php echo esc_html( $name ); ?>
										</option>
									<?php endforeach; ?>
								<?php endif; ?>
							</select>
						</p>

						<p class="ace-checkout-form__field ace-checkout-form__field--full ace-checkout-form__terms">
							<label>
								<input type="checkbox" name="ace_agree_terms" value="1" required />
								<?php
								if ( ! empty( $terms_url ) ) {
									printf(
										/* translators: 1: opening link tag, 2: closing link tag */
										esc_html__( 'I agree to the %1$sterms and conditions%2$s.', 'ace-the-catch' ),
										'<a href="' . esc_url( (string) $terms_url ) . '" target="_blank" rel="noopener noreferrer">',
										'</a>'
									);
								} else {
									esc_html_e( 'I agree to the terms and conditions.', 'ace-the-catch' );
								}
								?>
							</label>
						</p>

						<?php if ( $processor ) : ?>
							<div class="ace-checkout-form__field ace-checkout-form__field--full ace-checkout-form__payment">
								<?php
								$processor->enqueue_checkout_assets( $processor_config );
								echo $processor->render_checkout_fields( $processor_config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</div>
						<?php endif; ?>

						<div class="ace-checkout-form__actions ace-checkout-form__field--full">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Place Order', 'ace-the-catch' ); ?></button>
							<a class="button" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Back', 'ace-the-catch' ); ?></a>
						</div>
					</div>
				</form>
			<?php endif; ?>
		</div>
	</article>
</main>

<?php
get_footer();
