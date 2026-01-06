<?php
/**
 * Archive template for Catch the Ace sessions.
 *
 * @package Impeka\Lotto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Catch the Ace Sessions', 'ace-the-catch' ); ?></h1>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="catch-the-ace-archive catch-the-ace__wrapper">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<header class="entry-header">
						<h2 class="entry-title">
							<a href="<?php the_permalink(); ?>">
								<?php the_title(); ?>
							</a>
						</h2>
					</header>
					<div class="entry-meta">
						<?php esc_html_e( 'Session', 'ace-the-catch' ); ?>
					</div>
				</article>
				<?php
			endwhile;
			?>
		</div>

		<?php the_posts_navigation(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'No sessions found.', 'ace-the-catch' ); ?></p>
	<?php endif; ?>
</main>

<?php
get_footer();
