<?php
/**
 * بخش «این‌ها رو قبلاً دیدی».
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

$fs_recent = fs_get_recently_viewed();

if ( ! $fs_recent ) {
	return;
}
?>
<section class="fs-section fs-section--recent">

	<div class="fs-recent">

		<div>
			<span class="fs-recent__badge">
				<?php fs_the_icon( 'clock', 13, array( 'stroke' => '#c2410c', 'width' => '2.2' ) ); ?>
				<span class="fs-only-desktop"><?php echo esc_html( fs_copy( 'recent_badge' ) ); ?></span>
				<span class="fs-only-mobile"><?php echo esc_html( fs_copy( 'recent_badge_m' ) ); ?></span>
			</span>

			<h2 class="fs-recent__title"><?php echo esc_html( $fs_recent['title'] ); ?></h2>

			<?php fs_the_product_features( $fs_recent['id'], 'wide' ); ?>

			<div class="fs-recent__row fs-only-mobile fs-flex">
				<span class="fs-recent__price"><?php echo wp_kses_post( $fs_recent['price'] ); ?></span>
			</div>
		</div>

		<div class="fs-recent__side">
			<div class="fs-recent__price fs-only-desktop"><?php echo wp_kses_post( $fs_recent['price'] ); ?></div>

			<a class="fs-btn-orange" href="<?php echo esc_url( fs_url( $fs_recent['link'] ) ); ?>">
				<?php fs_the_icon( 'download', 17, array( 'stroke' => '#fff' ) ); ?>
				<?php echo esc_html( fs_copy( 'recent_cta' ) ); ?>
			</a>
		</div>

	</div>

</section>
