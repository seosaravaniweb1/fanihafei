<?php
/**
 * کارت محصول در گرید دسته‌بندی — با دکمه‌ی «افزودن به سبد خرید» روی خود کارت.
 *
 * برگردان دقیق کارت طرح «Category Listing UI»: نشان PDF/پاسخنامه، عنوان، تعداد
 * سوال، قیمت و دکمه‌ی نارنجی خرید — نه فقط آیکون دانلود مثل کارت‌های ریلی.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

$fs_product = isset( $args['product'] ) ? $args['product'] : null;

if ( ! $fs_product instanceof WC_Product ) {
	return;
}

$fs_index = isset( $args['index'] ) ? (int) $args['index'] : 0;
$fs_id    = $fs_product->get_id();
$fs_q     = fs_product_questions( $fs_id );

// «جدید»: محصولی که در ۱۴ روز گذشته منتشر شده — نه یک برچسب دلبخواه.
$fs_created = $fs_product->get_date_created();
$fs_is_new  = $fs_created && ( time() - $fs_created->getTimestamp() ) < 14 * DAY_IN_SECONDS;
?>
<div class="fs-gcard">

	<?php if ( is_user_logged_in() ) : ?>
		<span class="fs-gcard__heart"><?php fs_the_wishlist_button( $fs_id ); ?></span>
	<?php endif; ?>

	<a class="fs-gcard__link" href="<?php echo esc_url( get_permalink( $fs_id ) ); ?>">
		<span class="fs-gcard__thumb" style="background:<?php echo esc_attr( fs_grad( $fs_index ) ); ?>">
			<?php if ( $fs_is_new ) : ?>
				<span class="fs-gcard__badge">جدید</span>
			<?php endif; ?>
			<?php
			if ( has_post_thumbnail( $fs_id ) ) {
				echo get_the_post_thumbnail( $fs_id, 'medium', array( 'loading' => 'lazy', 'decoding' => 'async' ) );
			} else {
				fs_the_icon( 'file', 34, array( 'stroke' => 'rgba(15,23,42,.3)', 'width' => '1.4' ) );
			}
			?>
		</span>

		<span class="fs-gcard__body">
			<span class="fs-gcard__fmt">
				<?php fs_the_icon( 'file', 12, array( 'stroke' => '#94a3b8' ) ); ?>
				<?php echo esc_html( fs_product_badges_line( $fs_id ) ); ?>
			</span>

			<span class="fs-gcard__title"><?php echo esc_html( $fs_product->get_name() ); ?></span>

			<?php if ( $fs_q ) : ?>
				<span class="fs-gcard__q"><?php echo esc_html( $fs_q ); ?> سوال</span>
			<?php endif; ?>

			<span class="fs-gcard__price"><?php echo wp_kses_post( fs_product_price( $fs_product ) ); ?></span>
		</span>
	</a>

	<div class="fs-gcard__cta">
		<?php
		echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی استاندارد ووکامرس، از قبل امن است.
			'woocommerce_loop_add_to_cart_link',
			sprintf(
				'<a href="%s" data-quantity="1" class="%s" %s>%s</a>',
				esc_url( $fs_product->add_to_cart_url() ),
				esc_attr( implode( ' ', array_filter( array( 'fs-gcard__btn', 'add_to_cart_button', $fs_product->supports( 'ajax_add_to_cart' ) && $fs_product->is_purchasable() ? 'ajax_add_to_cart' : '' ) ) ) ),
				wc_implode_html_attributes(
					array(
						'data-product_id'  => $fs_id,
						'data-product_sku' => $fs_product->get_sku(),
						'aria-label'       => $fs_product->add_to_cart_description(),
						'rel'              => 'nofollow',
					)
				),
				esc_html( $fs_product->add_to_cart_text() )
			),
			$fs_product,
			array()
		);
		?>
	</div>

</div>
