<?php
/**
 * Winners view for Catch the Ace session.
 *
 * @package Impeka\Lotto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main">
	<?php
	while ( have_posts() ) :
		the_post();
		$post_id        = get_the_ID();
		$winners_helper = new \Impeka\Lotto\CatchTheAceWinners();
		$view_data      = $winners_helper->build_view_model( $post_id );
		$sales_status   = $view_data['sales_status'];
		$next_draw      = $view_data['next_draw'];
		$draws          = $view_data['draws'];
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'catch-the-ace__wrapper' ); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php the_title(); ?></h1>
				<p class="catch-ace-winners-intro"><?php esc_html_e( 'Winning draws for this session.', 'ace-the-catch' ); ?></p>
			</header>

			<div class="entry-content">
				<?php
				if ( $sales_status['open'] && $next_draw ) {
					$countdown_id = 'ace-winners-countdown-' . $post_id;
					echo '<div class="ace-countdown ace-winners-countdown" data-countdown-iso="' . esc_attr( $next_draw->format( 'c' ) ) . '" data-countdown-target="#' . esc_attr( $countdown_id ) . '">
						<div class="card-table-header__countdown-label">' . esc_html__( 'Next draw in:', 'ace-the-catch' ) . '</div>
						<div class="card-table-header__countdown-timer simply-countdown" id="' . esc_attr( $countdown_id ) . '"></div>
					</div>';
				}

				if ( ! empty( $draws ) ) {
					echo '<ul class="ace-winners-list">';
					foreach ( $draws as $draw ) {
						?>
						<li class="<?php echo esc_attr( $draw['classes'] ); ?>" <?php echo $draw['card_slug'] ? 'data-card="' . esc_attr( $draw['card_slug'] ) . '"' : ''; ?>>
							<h2 class="ace-winners__title"><?php echo \sprintf( 'Week %d%s', $draw['week'], ( $draw['date_formatted'] ? ', ' . $draw['date_formatted'] : '' ) ); ?></h2>
							<div class="ace-winners__body">
								<div class="ace-winners-list__card">
									<?php if ( $draw['card_img_url'] ) : ?>
										<img src="<?php echo esc_url( $draw['card_img_url'] ); ?>" alt="<?php echo esc_attr( $draw['card_slug'] ); ?>" loading="lazy" />
									<?php else : ?>
										<span class="ace-winners-list__card--placeholder"><?php esc_html_e( 'Card', 'ace-the-catch' ); ?></span>
									<?php endif; ?>
								</div>
								<div class="ace-winners-list__details">
									<div class="ace-winners-list__meta">
										<?php
										if ( $draw['envelope'] ) {
											echo '<div>' . esc_html( sprintf( __( 'Envelope #%d', 'ace-the-catch' ), $draw['envelope'] ) ) . '</div>';
										}
										if ( $draw['winnings_display'] ) {
											echo '<div>' . esc_html( sprintf( __( 'Winnings: %s', 'ace-the-catch' ), $draw['winnings_display'] ) ) . '</div>';
										}
										if ( $draw['note'] ) {
											echo '<div class="ace-winners-list__note">' . wp_kses_post( $draw['note'] ) . '</div>';
										}
										?>
									</div>
								</div>
							</div>
						</li>
						<?php
					}
					echo '</ul>';
				} else {
					echo '<p>' . \esc_html__( 'No winning draws recorded yet.', 'ace-the-catch' ) . '</p>';
				}
				?>
			</div>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
