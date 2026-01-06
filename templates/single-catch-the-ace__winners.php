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

// Helper to format card slug (e.g., spade-1) into readable label (e.g., ♠A).
$format_card_label = static function ( string $card ): string {
	$parts = \explode( '-', $card, 2 );
	if ( \count( $parts ) < 2 ) {
		return $card;
	}
	list( $suit, $rank_slug ) = $parts;
	$suits = array(
		'club'    => "\u{2663}\u{FE0F}",   // ♣
		'diamond' => "\u{2666}\u{FE0F}",   // ♦
		'heart'   => "\u{2665}\u{FE0F}",   // ♥
		'spade'   => "\u{2660}\u{FE0F}",   // ♠
	);
	$face = $rank_slug;
	switch ( $rank_slug ) {
		case '1':
			$face = 'A';
			break;
		case '11-jack':
			$face = 'J';
			break;
		case '12-queen':
			$face = 'Q';
			break;
		case '13-king':
			$face = 'K';
			break;
		default:
			$face = $rank_slug;
			break;
	}
	return ( $suits[ $suit ] ?? '' ) . $face;
};

// Helper to split time "HH:MM".
$split_time = static function ( string $time ): array {
	$parts = \array_pad( \explode( ':', $time ), 2, '0' );
	return array( (int) $parts[0], (int) $parts[1] );
};

// Compute sales window status (open/closed).
$get_sales_status = static function ( int $post_id ) use ( $split_time ): array {
	if ( ! \function_exists( 'get_field' ) ) {
		return array( 'open' => false );
	}

	$open_group  = \get_field( 'ticket_sales_open_on', $post_id );
	$close_group = \get_field( 'ticket_sales_end_on', $post_id );

	$open_day   = $open_group['day'] ?? '';
	$open_time  = $open_group['time'] ?? '';
	$close_day  = $close_group['day'] ?? '';
	$close_time = $close_group['time'] ?? '';

	$day_map = array(
		'Sunday'    => 0,
		'Monday'    => 1,
		'Tuesday'   => 2,
		'Wednesday' => 3,
		'Thursday'  => 4,
		'Friday'    => 5,
		'Saturday'  => 6,
	);

	if ( ! isset( $day_map[ $open_day ], $day_map[ $close_day ] ) || empty( $open_time ) || empty( $close_time ) ) {
		return array( 'open' => false );
	}

	$tz         = \wp_timezone();
	$now        = new \DateTimeImmutable( 'now', $tz );
	$now_day    = (int) $now->format( 'w' );
	$week_start = $now->modify( '-' . $now_day . ' days' )->setTime( 0, 0, 0 );

	list( $open_hour, $open_min )   = $split_time( $open_time );
	list( $close_hour, $close_min ) = $split_time( $close_time );

	$open_dt  = $week_start->modify( '+' . $day_map[ $open_day ] . ' days' )->setTime( $open_hour, $open_min, 0 );
	$close_dt = $week_start->modify( '+' . $day_map[ $close_day ] . ' days' )->setTime( $close_hour, $close_min, 0 );

	$is_open = ( $now >= $open_dt && $now <= $close_dt );

	return array(
		'open'        => $is_open,
		'close_epoch' => $close_dt->getTimestamp(),
	);
};

// Compute next weekly draw datetime.
$get_next_draw_datetime = static function ( int $post_id ) use ( $split_time ) : ?\DateTimeImmutable {
	if ( ! \function_exists( 'get_field' ) ) {
		return null;
	}

	$draw_group = \get_field( 'weekly_draw_on', $post_id );
	$day        = $draw_group['day'] ?? '';
	$time       = $draw_group['time'] ?? '';

	$day_map = array(
		'Sunday'    => 0,
		'Monday'    => 1,
		'Tuesday'   => 2,
		'Wednesday' => 3,
		'Thursday'  => 4,
		'Friday'    => 5,
		'Saturday'  => 6,
	);

	if ( ! isset( $day_map[ $day ] ) || empty( $time ) ) {
		return null;
	}

	$tz         = \wp_timezone();
	$now        = new \DateTimeImmutable( 'now', $tz );
	$now_day    = (int) $now->format( 'w' );
	$week_start = $now->modify( '-' . $now_day . ' days' )->setTime( 0, 0, 0 );

	list( $hour, $minute ) = $split_time( $time );
	$target = $week_start->modify( '+' . $day_map[ $day ] . ' days' )->setTime( $hour, $minute, 0 );

	if ( $target <= $now ) {
		$target = $target->modify( '+7 days' );
	}

	return $target;
};
?>

<main id="primary" class="site-main">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'catch-the-ace__wrapper' ); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php the_title(); ?></h1>
				<p class="catch-ace-winners-intro"><?php esc_html_e( 'Winning draws for this session.', 'ace-the-catch' ); ?></p>
			</header>

			<div class="entry-content">
				<?php
				$post_id      = get_the_ID();
				$sales_status = $get_sales_status( $post_id );
				$next_draw    = $get_next_draw_datetime( $post_id );

				if ( $sales_status['open'] && $next_draw ) {
					$countdown_id = 'ace-winners-countdown-' . $post_id;
					echo '<div class="ace-countdown ace-winners-countdown" data-countdown-iso="' . esc_attr( $next_draw->format( 'c' ) ) . '" data-countdown-target="#' . esc_attr( $countdown_id ) . '">
						<div class="card-table-header__countdown-label">' . esc_html__( 'Next draw in:', 'ace-the-catch' ) . '</div>
						<div class="card-table-header__countdown-timer simply-countdown" id="' . esc_attr( $countdown_id ) . '"></div>
					</div>';
				}

				$draws = \function_exists( 'get_field' ) ? \get_field( 'winning_draws', $post_id ) : array();

				if ( ! empty( $draws ) && \is_array( $draws ) ) {
					// Sort chronological by draw_date.
					\usort(
						$draws,
						static function ( $a, $b ) {
							$at = isset( $a['draw_date'] ) ? \strtotime( $a['draw_date'] ) : 0;
							$bt = isset( $b['draw_date'] ) ? \strtotime( $b['draw_date'] ) : 0;
							return $bt <=> $at;
						}
					);

					echo '<ul class="ace-winners-list">';
					foreach ( $draws as $draw_n => $draw ) {
						$date     = isset( $draw['draw_date'] ) ? $draw['draw_date'] : '';
						$envelope = isset( $draw['selected_envelope'] ) ? (int) $draw['selected_envelope'] : 0;
						$card     = isset( $draw['card_within'] ) ? $draw['card_within'] : '';
						$winnings = isset( $draw['winnings'] ) ? (float) $draw['winnings'] : 0.0;
						$note     = isset( $draw['winning_note'] ) ? $draw['winning_note'] : '';
						$week     = count( $draws ) - $draw_n;

						$is_ace_spades = ( 'spade-1' === $card );

						$date_fmt = $date ? \date_i18n( \get_option( 'date_format' ), \strtotime( $date ) ) : '';
						$card_img = $card ? LOTTO_URL . 'assets/images/playing-cards/' . $card . '.svg' : '';
						$item_classes = 'ace-winners-list__item' . ( $is_ace_spades ? ' ace-winners-list__item--ace' : '' );
						?>
						<li class="<?php echo esc_attr( $item_classes ); ?>" <?php echo $card ? 'data-card="' . esc_attr( $card ) . '"' : ''; ?>>
							<h2 class="ace-winners__title"><?php echo \sprintf( 'Week %d%s', $week, ( $date_fmt ? ', ' . $date_fmt : '' ) ); ?></h2>
							<div class="ace-winners__body">
								<div class="ace-winners-list__card">
									<?php if ( $card_img ) : ?>
										<img src="<?php echo esc_url( $card_img ); ?>" alt="<?php echo esc_attr( $card ); ?>" loading="lazy" />
									<?php else : ?>
										<span class="ace-winners-list__card--placeholder"><?php esc_html_e( 'Card', 'ace-the-catch' ); ?></span>
									<?php endif; ?>
								</div>
								<div class="ace-winners-list__details">
									<div class="ace-winners-list__meta">
										<?php
										if ( $envelope ) {
											echo '<div>' . esc_html( sprintf( __( 'Envelope #%d', 'ace-the-catch' ), $envelope ) ) . '</div>';
										}
										if ( $winnings ) {
											$winnings_display = '$' . number_format_i18n( $winnings, 2 );
											echo '<div>' . esc_html( sprintf( __( 'Winnings: %s', 'ace-the-catch' ), $winnings_display ) ) . '</div>';
										}
										if ( $note ) {
											echo '<div class="ace-winners-list__note">' . wp_kses_post( $note ) . '</div>';
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
