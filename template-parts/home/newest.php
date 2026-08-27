<?php
/**
 * بخش «جدیدترین فایل‌ها».
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

$fs_newest = fs_get_newest( 6 );

if ( ! $fs_newest ) {
	return;
}
?>
<section class="fs-section">

	<div class="fs-section__head">
		<div>
			<h2 class="fs-section__title"><?php echo esc_html( fs_copy( 'newest_title' ) ); ?></h2>
			<div class="fs-section__sub"><?php echo esc_html( fs_copy( 'newest_sub' ) ); ?></div>
		</div>

		<div class="fs-arrows">
			<button class="fs-arrow" type="button" data-rail="fs-newest" data-dir="prev">
				<span class="fs-sr-only">قبلی</span>
				<?php fs_the_icon( 'chevron-prev', 16 ); ?>
			</button>
			<button class="fs-arrow" type="button" data-rail="fs-newest" data-dir="next">
				<span class="fs-sr-only">بعدی</span>
				<?php fs_the_icon( 'chevron-next', 16 ); ?>
			</button>
		</div>

		<a class="fs-section__link fs-only-mobile" href="<?php echo esc_url( fs_shop_url() ); ?>">همه ›</a>
	</div>

	<div class="fs-rail" id="fs-newest">
		<?php
		foreach ( $fs_newest as $fs_item ) {
			get_template_part(
				'template-parts/card/product',
				null,
				array(
					'card'  => $fs_item,
					'badge' => 'جدید',
				)
			);
		}
		?>
	</div>

</section>
