<?php
/**
 * تسویه‌حساب یک‌صفحه‌ای — برگردان طرح «Checkout Flow UI».
 *
 * سبد خرید، اطلاعات دریافت فایل و انتخاب درگاه، همه در یک صفحه.
 *
 * @package FanniSoal
 *
 * @var WC_Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );

	return;
}

$fs_cart = WC()->cart;
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout fs-checkout"
	action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

	<div class="fs-checkout__grid">

		<div class="fs-checkout__main">

			<!-- ۱ · فایل خریداری‌شده -->
			<section class="fs-cbox">
				<div class="fs-cbox__head">
					<h2 class="fs-cbox__title">
						<span class="fs-cbox__icon fs-cbox__icon--green"><?php fs_the_icon( 'file', 16, array( 'stroke' => '#059669' ) ); ?></span>
						۱ · فایل شما
					</h2>
				</div>

				<div class="fs-cbox__body">
					<?php
					foreach ( $fs_cart->get_cart() as $fs_key => $fs_item ) :
						$fs_product = $fs_item['data'];

						if ( ! $fs_product || ! $fs_product->exists() || $fs_item['quantity'] <= 0 ) {
							continue;
						}

						$fs_pid = $fs_item['product_id'];
						?>
						<div class="fs-citem">
							<span class="fs-citem__thumb" style="background:<?php echo esc_attr( fs_grad( $fs_pid ) ); ?>">
								<?php
								if ( has_post_thumbnail( $fs_pid ) ) {
									echo get_the_post_thumbnail( $fs_pid, 'thumbnail', array( 'loading' => 'lazy' ) );
								} else {
									fs_the_icon( 'file', 20, array( 'stroke' => 'rgba(15,23,42,.4)', 'width' => '1.6' ) );
								}
								?>
							</span>

							<span class="fs-citem__body">
								<a class="fs-citem__title" href="<?php echo esc_url( get_permalink( $fs_pid ) ); ?>">
									<?php echo esc_html( $fs_product->get_name() ); ?>
								</a>
								<span class="fs-citem__pills">
									<span class="fs-citem__pill">
										<?php
										$fs_q = fs_product_questions( $fs_pid );
										echo $fs_q ? esc_html( $fs_q ) . ' سوال · PDF' : 'PDF';
										?>
									</span>
									<span class="fs-citem__pill fs-citem__pill--muted">دانلود فوری</span>
								</span>
							</span>

							<span class="fs-citem__price">
								<?php if ( $fs_product->is_on_sale() && $fs_product->get_regular_price() ) : ?>
									<span class="fs-citem__was"><?php echo wp_kses_post( fs_fa_num_html( wc_price( (float) $fs_product->get_regular_price() * $fs_item['quantity'] ) ) ); ?></span>
								<?php endif; ?>
								<?php echo wp_kses_post( fs_fa_num_html( $fs_cart->get_product_subtotal( $fs_product, $fs_item['quantity'] ) ) ); ?>
							</span>
						</div>
					<?php endforeach; ?>

					<?php if ( wc_coupons_enabled() ) : ?>
						<div class="fs-coupon">
							<label class="fs-coupon__label" for="fs-coupon-code">کد تخفیف دارید؟</label>
							<div class="fs-coupon__row">
								<input class="fs-coupon__input" id="fs-coupon-code" type="text" name="coupon_code" placeholder="کد تخفیف" autocomplete="off">
								<button class="fs-coupon__btn" type="submit" name="fs_apply_coupon" value="1" formnovalidate>اعمال کد</button>
							</div>
							<?php wp_nonce_field( 'fs_apply_coupon', 'fs_coupon_nonce' ); ?>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<!-- ۲ · اطلاعات دریافت فایل -->
			<section class="fs-cbox">
				<div class="fs-cbox__head">
					<h2 class="fs-cbox__title">
						<span class="fs-cbox__icon fs-cbox__icon--blue"><?php fs_the_icon( 'user', 16, array( 'stroke' => '#2563eb', 'width' => '1.9' ) ); ?></span>
						۲ · اطلاعات دریافت فایل
					</h2>
					<?php if ( is_user_logged_in() ) : ?>
						<span class="fs-cbox__ok">
							<?php fs_the_icon( 'check', 12, array( 'stroke' => '#059669', 'width' => '2.8' ) ); ?>
							وارد شده‌اید
						</span>
					<?php endif; ?>
				</div>

				<div class="fs-cbox__body">
					<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

					<div class="woocommerce-billing-fields fs-fields">
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
					</div>

					<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
				</div>
			</section>

			<!-- ۳ · انتخاب درگاه -->
			<section class="fs-cbox">
				<div class="fs-cbox__head">
					<h2 class="fs-cbox__title">
						<span class="fs-cbox__icon fs-cbox__icon--orange"><?php fs_the_icon( 'file', 16, array( 'stroke' => '#ea580c' ) ); ?></span>
						۳ · انتخاب درگاه پرداخت
					</h2>
				</div>

				<div class="fs-cbox__body fs-cbox__body--gateways">
					<?php woocommerce_checkout_payment(); ?>
				</div>

			</section>

		</div>

		<!-- خلاصه سفارش -->
		<aside class="fs-checkout__side">
			<div class="fs-summary">
				<h2 class="fs-summary__title">خلاصه سفارش</h2>

				<div id="order_review" class="fs-summary__review">
					<?php woocommerce_order_review(); ?>
				</div>

				<?php do_action( 'woocommerce_review_order_before_submit' ); ?>

				<button type="submit" class="button alt fs-summary__pay" name="woocommerce_checkout_place_order"
					id="place_order" value="پرداخت و دریافت فایل" data-value="پرداخت و دریافت فایل">
					<?php fs_the_icon( 'download', 17, array( 'stroke' => '#fff' ) ); ?>
					پرداخت و دریافت فایل
				</button>

				<?php do_action( 'woocommerce_review_order_after_submit' ); ?>

				<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>

				<?php
				$fs_trust = fs_get_trust_settings();

				if ( $fs_trust['badges'] ) :
					?>
					<div class="fs-trust-badges fs-summary__trust">
						<?php
						foreach ( $fs_trust['badges'] as $fs_badge ) :
							$fs_img = wp_get_attachment_image( $fs_badge['id'], 'medium', false, array( 'loading' => 'lazy' ) );

							if ( ! $fs_img ) {
								continue;
							}
							?>
							<span class="fs-trust-badge"><?php echo wp_kses_post( $fs_img ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<p class="fs-summary__note">پس از پرداخت، لینک دانلود بلافاصله در پیشخوان شما فعال می‌شود.</p>
			</div>
		</aside>

	</div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
