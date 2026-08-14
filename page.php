<?php
/**
 * قالب برگه — شامل برگه‌های ووکامرس (سبد خرید، تسویه‌حساب، حساب کاربری).
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

get_header();

$fs_classes = array( 'fs-section', 'fs-page' );

// برگه‌های ووکامرس رابط کاربری‌اند نه متن؛ کلاس متن غنی روی آن‌ها گذاشته
// نمی‌شود تا استایل پاراگراف و لینک و تیتر، اجزای پیشخوان و تسویه‌حساب را
// به‌هم نریزد (زیرخط لینک منو، اندازه‌ی تیتر بلوک‌ها و حاشیه‌های اضافه).
$fs_body_class = 'fs-rich fs-page__body';

if ( fs_has_woo() ) {
	if ( is_cart() ) {
		$fs_classes[]  = 'fs-page--cart';
		$fs_body_class = 'fs-page__body';
	} elseif ( is_checkout() ) {
		$fs_classes[]  = 'fs-page--checkout';
		$fs_body_class = 'fs-page__body';
	} elseif ( is_account_page() ) {
		$fs_classes[]  = 'fs-page--account';
		$fs_body_class = 'fs-page__body';
	}
}

while ( have_posts() ) :
	the_post();
	?>
	<section class="<?php echo esc_attr( implode( ' ', $fs_classes ) ); ?>">

		<?php if ( ! is_front_page() ) : ?>
			<div class="fs-section__head">
				<div>
					<h1 class="fs-section__title"><?php the_title(); ?></h1>
				</div>
			</div>
		<?php endif; ?>

		<div class="<?php echo esc_attr( $fs_body_class ); ?>">
			<?php
			the_content();

			wp_link_pages(
				array(
					'before' => '<div class="fs-pagination">',
					'after'  => '</div>',
				)
			);
			?>
		</div>

	</section>
	<?php
endwhile;

get_footer();
