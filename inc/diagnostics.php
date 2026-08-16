<?php
/**
 * تب «عیب‌یابی» در تنظیمات قالب.
 *
 * وقتی در تسویه‌حساب پیام «هیچ روش پرداختی در دسترس نیست» می‌آید، علتش تقریباً
 * همیشه یکی از چند چیز مشخص است: درگاه غیرفعال، واحد پول پشتیبانی‌نشده، مبلغ
 * صفر، یا محدودیت مبلغ درگاه. این صفحه همان‌ها را یک‌جا و بدون حدس نشان می‌دهد.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

/**
 * وضعیت درگاه‌های پرداخت.
 *
 * @return array<int, array{id:string,title:string,enabled:bool,available:bool,note:string}>
 */
function fs_gateway_report() {
	if ( ! fs_has_woo() || ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
		return array();
	}

	$all       = WC()->payment_gateways()->payment_gateways();
	$available = WC()->payment_gateways()->get_available_payment_gateways();
	$out       = array();

	foreach ( $all as $gateway ) {
		$enabled   = 'yes' === $gateway->enabled;
		$is_avail  = isset( $available[ $gateway->id ] );
		$note      = '';

		if ( ! $enabled ) {
			$note = 'در ووکامرس ← تنظیمات ← پرداخت‌ها فعال نشده است.';
		} elseif ( ! $is_avail ) {
			// درگاه فعال است ولی خودش می‌گوید در دسترس نیستم.
			$note = 'فعال است اما خودش را «در دسترس» اعلام نمی‌کند — معمولاً یعنی واحد پول فروشگاه را پشتیبانی نمی‌کند یا محدودیت مبلغ/کشور دارد.';

			if ( ! empty( $gateway->max_amount ) ) {
				$note .= ' سقف مبلغ این درگاه: ' . wc_price( $gateway->max_amount ) . '.';
			}
		} else {
			$note = 'سالم است و در تسویه‌حساب نمایش داده می‌شود.';
		}

		$out[] = array(
			'id'        => $gateway->id,
			'title'     => $gateway->get_title() ? $gateway->get_title() : $gateway->id,
			'enabled'   => $enabled,
			'available' => $is_avail,
			'note'      => $note,
		);
	}

	return $out;
}

/**
 * محتوای تب عیب‌یابی.
 *
 * @return void
 */
function fs_diagnostics_tab() {
	if ( ! fs_has_woo() ) {
		echo '<div class="notice notice-error"><p>ووکامرس فعال نیست.</p></div>';

		return;
	}

	$currency  = get_woocommerce_currency();
	$symbol    = get_woocommerce_currency_symbol( $currency );
	$country   = WC()->countries ? WC()->countries->get_base_country() : '';
	$gateways  = fs_gateway_report();
	$healthy   = wp_list_filter( $gateways, array( 'available' => true ) );
	?>

	<h2 style="margin-top:24px">وضعیت پرداخت</h2>

	<?php if ( ! $gateways ) : ?>
		<div class="notice notice-error inline"><p>هیچ درگاه پرداختی روی سایت نصب نشده است.</p></div>
	<?php elseif ( ! $healthy ) : ?>
		<div class="notice notice-error inline">
			<p><strong>هیچ درگاه فعالی در دسترس نیست</strong> — به همین دلیل در تسویه‌حساب پیام خطا می‌بینید. جدول زیر علت هر درگاه را می‌گوید.</p>
		</div>
	<?php else : ?>
		<div class="notice notice-success inline">
			<p><?php echo esc_html( fs_fa_num( count( $healthy ) ) ); ?> درگاه سالم و در دسترس است.</p>
		</div>
	<?php endif; ?>

	<table class="widefat striped" style="max-width:940px;margin-top:14px">
		<thead>
			<tr>
				<th style="width:200px">درگاه</th>
				<th style="width:90px">فعال</th>
				<th style="width:110px">در دسترس</th>
				<th>توضیح</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $gateways ) : ?>
				<tr><td colspan="4">درگاهی یافت نشد.</td></tr>
			<?php else : ?>
				<?php foreach ( $gateways as $gateway ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $gateway['title'] ); ?></strong><br><code><?php echo esc_html( $gateway['id'] ); ?></code></td>
						<td><?php echo $gateway['enabled'] ? '✅' : '❌'; ?></td>
						<td><?php echo $gateway['available'] ? '✅' : '❌'; ?></td>
						<td><?php echo wp_kses_post( $gateway['note'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<h2 style="margin-top:32px">تنظیمات مؤثر بر پرداخت</h2>
	<table class="widefat striped" style="max-width:940px">
		<tbody>
			<tr>
				<td style="width:260px"><strong>واحد پول فروشگاه</strong></td>
				<td>
					<code><?php echo esc_html( $currency ); ?></code> — <?php echo wp_kses_post( $symbol ); ?>
					<p class="description">
						اگر درگاه ایرانی دارید و واحد پول را تازه عوض کرده‌اید، بیشتر افزونه‌های درگاه فقط
						<code>IRR</code> (ریال) و <code>IRT</code> (تومان) را می‌شناسند. واحد پول دست‌ساز یا افزونه‌ای
						ممکن است برایشان ناشناخته باشد و درگاه خودش را غیرفعال کند.
					</p>
				</td>
			</tr>
			<tr>
				<td><strong>کشور فروشگاه</strong></td>
				<td><?php echo esc_html( $country ? $country : '— تعیین نشده —' ); ?></td>
			</tr>
			<tr>
				<td><strong>خرید مهمان</strong></td>
				<td>غیرفعال (خرید فقط با حساب کاربری) — این قالب عمداً چنین تنظیم کرده است.</td>
			</tr>
			<tr>
				<td><strong>محصولات بدون قیمت</strong></td>
				<td>
					<?php
					$fs_zero = 0;

					foreach ( wc_get_products( array( 'status' => 'publish', 'limit' => 200 ) ) as $fs_p ) {
						if ( '' === $fs_p->get_price() || 0 >= (float) $fs_p->get_price() ) {
							++$fs_zero;
						}
					}

					if ( $fs_zero ) {
						printf(
							'<span style="color:#b32d2e"><strong>%s محصول</strong> قیمت ندارند.</span> سفارشی که مبلغش صفر باشد اصلاً به درگاه نمی‌رود.',
							esc_html( fs_fa_num( $fs_zero ) )
						);
					} else {
						echo 'همه‌ی محصولات قیمت دارند.';
					}
					?>
				</td>
			</tr>
		</tbody>
	</table>

	<p class="description" style="margin-top:16px;max-width:940px">
		این جدول از خود ووکامرس خوانده می‌شود. اگر درگاهی «فعال» است ولی «در دسترس» نیست، مشکل از قالب نیست —
		خودِ افزونه‌ی درگاه به ووکامرس می‌گوید در این شرایط قابل استفاده نیستم.
	</p>
	<?php
}
