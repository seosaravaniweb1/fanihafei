<?php
/**
 * بخش «درباره سایت» و «پرسش‌های متداول».
 *
 * محتوای هر دو از پست‌تایپ «تنظیمات قالب» خوانده می‌شود.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

$fs_about = fs_get_about();
$fs_faq   = fs_get_faq();

if ( ! $fs_about && ! $fs_faq ) {
	return;
}
?>
<section class="fs-section fs-section--about">

	<div class="fs-about-faq<?php echo ( $fs_about && $fs_faq ) ? '' : ' fs-about-faq--single'; ?>">

		<?php if ( $fs_about ) : ?>
			<div class="fs-about">
				<h2 class="fs-block__title"><?php echo esc_html( $fs_about['title'] ); ?></h2>

				<div class="fs-collapse" data-collapse>
					<div class="fs-collapse__inner fs-rich"><?php echo wp_kses_post( $fs_about['content'] ); ?></div>
					<button class="fs-collapse__btn" type="button" aria-expanded="false" hidden
						data-more="<?php echo esc_attr( fs_copy( 'read_more' ) ); ?>"
						data-less="<?php echo esc_attr( fs_copy( 'read_less' ) ); ?>">
						<?php echo esc_html( fs_copy( 'read_more' ) ); ?>
						<?php fs_the_icon( 'chevron-down', 14, array( 'width' => '2.2' ) ); ?>
					</button>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $fs_faq ) : ?>
			<div class="fs-faq">
				<h2 class="fs-block__title"><?php echo esc_html( fs_copy( 'faq_title' ) ); ?></h2>

				<?php foreach ( $fs_faq as $fs_i => $fs_item ) : ?>
					<div class="fs-faq__item">
						<button class="fs-faq__q" type="button" aria-expanded="false" aria-controls="fs-faq-a-<?php echo (int) $fs_i; ?>">
							<span><?php echo esc_html( $fs_item['title'] ); ?></span>
							<?php fs_the_icon( 'plus', 15, array( 'stroke' => '#059669', 'width' => '2.2' ) ); ?>
						</button>

						<?php if ( trim( wp_strip_all_tags( $fs_item['content'] ) ) ) : ?>
							<div class="fs-faq__a fs-rich" id="fs-faq-a-<?php echo (int) $fs_i; ?>" hidden>
								<?php echo wp_kses_post( $fs_item['content'] ); ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

	</div>

</section>
