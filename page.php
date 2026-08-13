<?php
/**
 * قالب برگه — شامل برگه‌های ووکامرس (سبد خرید، تسویه‌حساب، حساب کاربری).
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

get_header();

$fs_classes = array( 'fs-section', 'fs-page' );

if ( fs_has_woo() ) {
	if ( is_cart() ) {
		$fs_classes[] = 'fs-page--cart';
	} elseif ( is_checkout() ) {
		$fs_classes[] = 'fs-page--checkout';
	} elseif ( is_account_page() ) {
		$fs_classes[] = 'fs-page--account';
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

		<div class="fs-rich fs-page__body">
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
