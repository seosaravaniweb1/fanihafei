<?php
/**
 * صفحه‌ی «پرداخت موفق» — برگردان طرح «Checkout Flow UI» (حالت done).
 *
 * @package FanniSoal
 *
 * @var WC_Order|false $order
 */

defined( 'ABSPATH' ) || exit;

if ( ! $order ) {
	return;
}

/*
 * پرداخت ناموفق: تا وقتی سفارش پرداخت نشده، نباید پیام «پرداخت با موفقیت
 * انجام شد» نمایش داده شود. درگاه‌های ایرانی (زیبال / زرین‌پال) در صورت
 * انصراف یا خطای بانک، سفارش را با وضعیت `failed` یا `pending` به همین
 * صفحه برمی‌گردانند.
 */
if ( $order->has_status( array( 'failed', 'cancelled', 'pending' ) ) ) {
	$fs_gateway  = $order->get_meta( '_fs_failure_gateway' );
	$fs_gateway  = $fs_gateway ? $fs_gateway : $order->get_payment_method();
	$fs_reason   = $order->get_meta( '_fs_failure_reason' );
	$fs_guidance = fs_get_payment_failure_guidance( $fs_gateway );
	?>

	<div class="fs-failed">
		<div class="fs-failed__hero">
			<span class="fs-failed__mark" aria-hidden="true">!</span>
			<div>
				<div class="fs-failed__title">پرداخت انجام نشد</div>
				<div class="fs-failed__sub">
					تراکنش سفارش <?php echo esc_html( fs_fa_num( $order->get_order_number() ) ); ?> ناتمام ماند. اگر مبلغی از حساب شما کسر شده باشد، طبق قوانین بانک مرکزی حداکثر تا ۷۲ ساعت به‌صورت خودکار برمی‌گردد.
				</div>
			</div>
		</div>

		<?php if ( $fs_reason ) : ?>
			<p class="fs-failed__reason">پیام درگاه پرداخت: <?php echo esc_html( $fs_reason ); ?></p>
		<?php endif; ?>

		<a class="fs-failed__retry" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>">
			<?php fs_the_icon( 'download', 16, array( 'stroke' => '#fff' ) ); ?>
			تلاش دوباره برای پرداخت
		</a>

		<?php if ( $fs_guidance ) : ?>
			<div class="fs-failed__help">
				<h2 class="fs-failed__help-title">چه کاری انجام دهم؟</h2>
				<ol class="fs-failed__steps">
					<?php foreach ( $fs_guidance as $fs_i => $fs_step ) : ?>
						<li>
							<span class="fs-failed__num"><?php echo esc_html( fs_fa_num( $fs_i + 1 ) ); ?></span>
							<?php echo esc_html( $fs_step ); ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		<?php endif; ?>

		<a class="fs-failed__back" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">بازگشت به پیشخوان</a>
	</div>

	<?php
	return;
}
?>

<div class="fs-thanks">

	<div class="fs-thanks__main">
		<div class="fs-thanks__hero">
			<span class="fs-thanks__glow" aria-hidden="true"></span>
			<span class="fs-thanks__check">
				<?php fs_the_icon( 'check', 28, array( 'stroke' => '#fff', 'width' => '2.8' ) ); ?>
			</span>
			<div>
				<div class="fs-thanks__title">پرداخت با موفقیت انجام شد</div>
				<div class="fs-thanks__sub">
					شماره پیگیری: <span><?php echo esc_html( fs_fa_num( $order->get_transaction_id() ? $order->get_transaction_id() : $order->get_order_key() ) ); ?></span>
					· سفارش <?php echo esc_html( fs_fa_num( $order->get_order_number() ) ); ?>
				</div>
			</div>
		</div>

		<div class="fs-thanks__body">
			<h2 class="fs-thanks__files-title">فایل‌های شما آماده دانلود است</h2>

			<div class="fs-dlist">
				<?php
				$fs_downloads = $order->get_downloadable_items();
				$fs_i         = 0;

				foreach ( $fs_downloads as $fs_dl ) :
					?>
					<div class="fs-ditem">
						<span class="fs-ditem__icon" style="background:<?php echo esc_attr( fs_grad( $fs_i ) ); ?>">
							<?php fs_the_icon( 'file', 17, array( 'stroke' => 'rgba(15,23,42,.45)', 'width' => '1.6' ) ); ?>
						</span>
						<span class="fs-ditem__body">
							<span class="fs-ditem__title"><?php echo esc_html( $fs_dl['product_name'] ); ?></span>
							<span class="fs-ditem__meta">دسترسی مادام‌العمر</span>
						</span>
						<a class="fs-ditem__btn" href="<?php echo esc_url( $fs_dl['download_url'] ); ?>">
							<?php fs_the_icon( 'download', 14, array( 'stroke' => '#fff' ) ); ?>
							دانلود
						</a>
					</div>
					<?php
					++$fs_i;
				endforeach;
				?>
			</div>

			<div class="fs-thanks__note">
				<?php fs_the_icon( 'user', 16, array( 'stroke' => '#059669', 'width' => '1.9' ) ); ?>
				<span>لینک دانلود به ایمیل و شماره‌ی شما هم ارسال شد. همیشه از «دانلودهای من» در پیشخوان قابل دسترسی است.</span>
			</div>
		</div>
	</div>

	<aside class="fs-thanks__side">
		<div class="fs-receipt">
			<h3 class="fs-receipt__title">رسید پرداخت</h3>

			<div class="fs-receipt__row">
				<span>شماره سفارش</span>
				<b><?php echo esc_html( fs_fa_num( $order->get_order_number() ) ); ?></b>
			</div>
			<div class="fs-receipt__row">
				<span>تاریخ</span>
				<b><?php echo esc_html( fs_fa_num( wc_format_datetime( $order->get_date_created() ) ) ); ?></b>
			</div>
			<div class="fs-receipt__row">
				<span>درگاه</span>
				<b><?php echo esc_html( $order->get_payment_method_title() ); ?></b>
			</div>
			<div class="fs-receipt__row">
				<span>مبلغ پرداخت‌شده</span>
				<b><?php echo wp_kses_post( fs_fa_num_html( $order->get_formatted_order_total() ) ); ?></b>
			</div>

			<a class="fs-receipt__cta" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">مشاهده در پیشخوان</a>
		</div>
	</aside>

</div>
