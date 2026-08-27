<?php
/**
 * خلاصه سفارش — فقط مبالغ.
 *
 * فهرست فایل‌ها در بخش «۱ · فایل شما» نمایش داده می‌شود، پس اینجا تکرار نمی‌شود.
 * نام کلاس جدول عمداً حفظ شده تا اجاکس ووکامرس بتواند آن را تازه کند.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="shop_table woocommerce-checkout-review-order-table fs-summary__table">
	<tfoot>

		<tr class="cart-subtotal">
			<th>جمع فایل‌ها</th>
			<td><?php echo wp_kses_post( fs_fa_num_html( WC()->cart->get_cart_subtotal() ) ); ?></td>
		</tr>

		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
				<th>تخفیف «<?php wc_cart_totals_coupon_label( $coupon ); ?>»</th>
				<td><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
			<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
				<tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
					<th><?php echo esc_html( $tax->label ); ?></th>
					<td><?php echo wp_kses_post( fs_fa_num_html( $tax->formatted_amount ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<tr class="order-total">
			<th>مبلغ قابل پرداخت</th>
			<td><?php echo wp_kses_post( fs_fa_num_html( WC()->cart->get_total() ) ); ?></td>
		</tr>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>

	</tfoot>
</table>
