<?php
/**
 * Single template for Catch the Ace session.
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
		$dealer = \Impeka\Lotto\Plugin::instance()->get_envelope_dealer();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'catch-the-ace__wrapper' ); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php the_title(); ?></h1>
			</header>

			<div class="entry-content">
				<?php
				if ( $dealer ) {
					echo $dealer->render_for_post( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
				<?php
				the_content();
				?>
			</div>
		</article>
	<?php endwhile; ?>
</main>

<?php
get_footer();
