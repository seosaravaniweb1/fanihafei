<?php
/**
 * فهرست درگاه‌های پرداخت.
 *
 * دکمه «پرداخت» عمداً اینجا نیست — در خلاصه سفارش (نوار کناری) قرار دارد،
 * دقیقاً مثل طرح. فرم یکی است، پس دکمه همان‌جا کار می‌کند.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="payment" class="woocommerce-checkout-payment fs-gateways">
	<?php if ( WC()->cart->needs_payment() ) : ?>
		<ul class="wc_payment_methods payment_methods methods">
			<?php
			if ( ! empty( $available_gateways ) ) {
				foreach ( $available_gateways as $gateway ) {
					wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
				}
			} else {
				echo '<li class="woocommerce-info">';
				echo wp_kses_post( apply_filters( 'woocommerce_no_available_payment_methods_message', WC()->customer->get_billing_country() ? esc_html__( 'Sorry, it seems that there are no available payment methods for your location. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' ) : esc_html__( 'Please fill in your details above to see available payment methods.', 'woocommerce' ) ) );
				echo '</li>';
			}
			?>
		</ul>
	<?php endif; ?>

	<noscript>
		برای پرداخت، جاوااسکریپت مرورگر باید فعال باشد.
	</noscript>
</div>
