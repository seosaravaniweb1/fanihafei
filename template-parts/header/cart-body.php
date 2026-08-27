<?php
/**
 * بدنه‌ی کشوی سبد خرید — همین قالب هم موقع رندر صفحه و هم در پاسخ اجاکس
 * استفاده می‌شود، پس هر بار وضعیت سبد را از نو می‌خواند.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

$fs_items = fs_cart_items();

if ( ! $fs_items ) :
	?>
	<div class="fs-cart__empty">
		<span class="fs-cart__empty-icon"><?php fs_the_icon( 'cart', 26, array( 'stroke' => '#94a3b8', 'width' => '1.6' ) ); ?></span>
		<p class="fs-cart__empty-title">سبد خرید شما خالی است</p>
		<p class="fs-cart__empty-sub">فایل‌ها را از فروشگاه انتخاب کنید تا اینجا نمایش داده شوند.</p>
		<a class="fs-cart__empty-btn" href="<?php echo esc_url( fs_shop_url() ); ?>">مشاهده فایل‌ها</a>
	</div>
	<?php
	return;
endif;
?>

<div class="fs-cart__list">
	<?php foreach ( $fs_items as $fs_item ) : ?>
		<div class="fs-cart__item" data-cart-key="<?php echo esc_attr( $fs_item['key'] ); ?>">

			<a class="fs-cart__thumb" href="<?php echo esc_url( fs_url( $fs_item['link'] ) ); ?>">
				<?php
				if ( $fs_item['thumb'] ) {
					echo wp_kses_post( $fs_item['thumb'] );
				} else {
					fs_the_icon( 'file', 20, array( 'stroke' => 'rgba(15,23,42,.3)', 'width' => '1.5' ) );
				}
				?>
			</a>

			<div class="fs-cart__info">
				<a class="fs-cart__title" href="<?php echo esc_url( fs_url( $fs_item['link'] ) ); ?>">
					<?php echo esc_html( $fs_item['title'] ); ?>
				</a>
				<span class="fs-cart__meta">
					<?php echo esc_html( $fs_item['fmt'] ); ?>
					<?php if ( $fs_item['qty'] > 1 ) : ?>
						· <?php echo esc_html( fs_fa_num( $fs_item['qty'] ) ); ?> عدد
					<?php endif; ?>
				</span>
				<span class="fs-cart__price"><?php echo wp_kses_post( $fs_item['price'] ); ?></span>
			</div>

			<button class="fs-cart__del" type="button" data-cart-remove="<?php echo esc_attr( $fs_item['key'] ); ?>">
				<span class="fs-sr-only">حذف از سبد خرید</span>
				<?php fs_the_icon( 'trash', 15, array( 'stroke' => '#94a3b8', 'width' => '1.8' ) ); ?>
			</button>

		</div>
	<?php endforeach; ?>
</div>

<div class="fs-cart__foot">
	<div class="fs-cart__sum">
		<span>جمع سبد خرید</span>
		<b><?php echo wp_kses_post( fs_cart_total() ); ?></b>
	</div>

	<a class="fs-cart__checkout" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">
		تسویه حساب و دانلود
	</a>

	<button class="fs-cart__continue" type="button" data-cart-close>ادامه خرید</button>
</div>
