<?php
/**
 * بخش هیرو.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

$fs_stats = fs_get_stats();
$fs_trust = fs_data_trust();
?>
<section class="fs-hero<?php echo ( $fs_stats || $fs_trust ) ? '' : ' fs-hero--solo'; ?>">

	<span class="fs-hero__glow" aria-hidden="true"></span>

	<div class="fs-hero__grid">

		<div>
			<span class="fs-badge-orange">
				<?php fs_the_icon( 'zap', 14, array( 'stroke' => '#c2410c', 'width' => '2.2' ) ); ?>
				<?php echo esc_html( fs_copy( 'hero_badge' ) ); ?>
			</span>

			<h1 class="fs-hero__title"><?php echo esc_html( fs_copy( 'hero_title' ) ); ?></h1>

			<p class="fs-hero__desc fs-only-desktop"><?php echo esc_html( fs_copy( 'hero_desc' ) ); ?></p>
			<p class="fs-hero__desc fs-only-mobile"><?php echo esc_html( fs_copy( 'hero_desc_m' ) ); ?></p>

			<form class="fs-hero__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php fs_the_icon( 'search', 18, array( 'stroke' => '#94a3b8' ) ); ?>
				<label class="fs-sr-only" for="fs-hero-search">جست‌وجوی نمونه سوال</label>
				<input class="fs-hero__search-input" id="fs-hero-search" type="search" name="s"
					value="<?php echo esc_attr( get_search_query() ); ?>"
					placeholder="<?php echo esc_attr( fs_copy( 'hero_search_ph' ) ); ?>">
				<?php if ( fs_has_woo() ) : ?>
					<input type="hidden" name="post_type" value="product">
				<?php endif; ?>
				<button class="fs-btn-search" type="submit">جست‌وجو</button>
			</form>

		</div>

		<?php if ( $fs_stats || $fs_trust ) : ?>
			<div class="fs-hero__side">

				<?php if ( $fs_stats ) : ?>
					<div class="fs-stats" style="grid-template-columns:repeat(<?php echo (int) count( $fs_stats ); ?>,1fr)">
						<?php foreach ( $fs_stats as $fs_stat ) : ?>
							<div class="fs-stat">
								<div class="fs-stat__v"><?php echo esc_html( $fs_stat['v'] ); ?></div>
								<div class="fs-stat__k"><?php echo esc_html( $fs_stat['k'] ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $fs_trust ) : ?>
					<div class="fs-trust">
						<?php foreach ( $fs_trust as $fs_item ) : ?>
							<div class="fs-trust__item">
								<span class="fs-trust__icon">
									<?php fs_the_icon( 'check', 15, array( 'stroke' => '#059669', 'width' => '2.4' ) ); ?>
								</span>
								<span class="fs-trust__text"><?php echo esc_html( $fs_item ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

			</div>
		<?php endif; ?>

	</div>

</section>
