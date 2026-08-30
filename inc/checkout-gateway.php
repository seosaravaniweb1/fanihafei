<?php
/**
 * اتصال به درگاه پرداخت — همان مسیری که در قالب «رمانینو» تست‌شده و جواب می‌دهد.
 *
 * سه مشکل اصلی صفحه‌ی تسویه‌حساب اینجا حل می‌شود:
 *
 * ۱. اگر برگه‌ی «تسویه‌حساب» با بلوک گوتنبرگ ووکامرس ساخته شده باشد، قالب‌های
 *    PHP این تم (woocommerce/checkout/*.php) اصلاً اجرا نمی‌شوند و ووکامرس
 *    ظاهر پیش‌فرض و بدون استایل خودش را نشان می‌دهد — دقیقاً همان چیزی که در
 *    اسکرین‌شات دیده می‌شود. بلوک را با شورت‌کد کلاسیک جایگزین می‌کنیم.
 * ۲. اسکریپت اجاکسی wc-checkout در صفحه‌ی پرداخت غیرفعال می‌شود تا «ثبت سفارش»
 *    یک POST واقعی باشد و ریدایرکت درگاه (زیبال / زرین‌پال) بدون مشکل انجام شود.
 *    این دقیقاً همان کاری است که رمانینو می‌کند.
 * ۳. کد تخفیف داخل فرم تسویه‌حساب با دست پردازش می‌شود (فرم تودرتو مجاز نیست).
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
   ۱ · اجبار به تسویه‌حساب کلاسیک (شورت‌کد) به‌جای بلوک ووکامرس
   ------------------------------------------------------------------------- */

/**
 * بلوک‌های «سبد خرید» و «تسویه‌حساب» ووکامرس با شورت‌کد کلاسیک معادلشان.
 *
 * بلوک‌ها با ری‌اکت رندر می‌شوند و هیچ‌کدام از فایل‌های
 * woocommerce/checkout/*.php این قالب را صدا نمی‌زنند؛ در نتیجه طراحی قالب،
 * فیلترهای fs_checkout_fields و قلاب‌های افزونه‌های درگاه ایرانی نادیده
 * گرفته می‌شوند. با شورت‌کد، همه‌چیز سر جای خودش برمی‌گردد.
 *
 * @param string $content خروجی رندرشده‌ی بلوک.
 * @param array  $block   داده‌های بلوک.
 * @return string
 */
function fs_classic_woo_blocks( $content, $block ) {
	if ( is_admin() || empty( $block['blockName'] ) ) {
		return $content;
	}

	$shortcodes = array(
		'woocommerce/checkout' => '[woocommerce_checkout]',
		'woocommerce/cart'     => '[woocommerce_cart]',
	);

	if ( ! isset( $shortcodes[ $block['blockName'] ] ) ) {
		return $content;
	}

	return do_shortcode( $shortcodes[ $block['blockName'] ] );
}
add_filter( 'render_block', 'fs_classic_woo_blocks', 10, 2 );

/* -------------------------------------------------------------------------
   ۲ · ثبت سفارش بدون اجاکس — لازم برای ریدایرکت درگاه
   ------------------------------------------------------------------------- */

/**
 * غیرفعال‌کردن اسکریپت اجاکسی تسویه‌حساب.
 *
 * با فعال بودن wc-checkout، دکمه‌ی «پرداخت» سفارش را با اجاکس می‌فرستد و
 * ریدایرکت به درگاه به جاوااسکریپت وابسته می‌شود؛ کوچک‌ترین خطای اسکریپت
 * یعنی کاربر روی همان صفحه می‌ماند. بدون این اسکریپت، فرم یک POST معمولی
 * می‌شود و خود ووکامرس کاربر را به درگاه می‌فرستد.
 *
 * @return void
 */
function fs_force_non_ajax_checkout() {
	if ( ! fs_has_woo() || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}

	wp_dequeue_script( 'wc-checkout' );
	wp_deregister_script( 'wc-checkout' );
}
add_action( 'wp_enqueue_scripts', 'fs_force_non_ajax_checkout', 100 );

/* -------------------------------------------------------------------------
   ۳ · کد تخفیف داخل فرم تسویه‌حساب
   ------------------------------------------------------------------------- */

/**
 * اعمال کد تخفیفی که از باکس «۱ · فایل شما» ارسال شده است.
 *
 * فرم تسویه‌حساب یکی است و نمی‌توان فرم دیگری داخلش گذاشت، پس دکمه‌ی کد
 * تخفیف با نام اختصاصی خودش ارسال می‌شود و اینجا پردازش می‌گردد.
 *
 * @return void
 */
function fs_apply_checkout_coupon() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- نانس بلافاصله پایین‌تر بررسی می‌شود.
	if ( empty( $_POST['fs_apply_coupon'] ) ) {
		return;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	if ( ! isset( $_POST['fs_coupon_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fs_coupon_nonce'] ) ), 'fs_apply_coupon' ) ) {
		return;
	}

	if ( ! fs_has_woo() || ! WC()->cart ) {
		return;
	}

	$code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';

	if ( '' === trim( $code ) ) {
		wc_add_notice( 'کد تخفیف را وارد کنید.', 'error' );
	} else {
		WC()->cart->apply_coupon( wc_format_coupon_code( $code ) );
	}

	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}
add_action( 'wp_loaded', 'fs_apply_checkout_coupon', 30 );

/* -------------------------------------------------------------------------
   ۴ · پرداخت ناموفق
   ------------------------------------------------------------------------- */

/**
 * ثبت دلیل و درگاهِ پرداخت ناموفق روی سفارش.
 *
 * افزونه‌های درگاه (زیبال، زرین‌پال و…) پیام بانک را به‌صورت یادداشت روی سفارش
 * می‌گذارند؛ همان را برمی‌داریم تا در صفحه‌ی نتیجه به کاربر نشان دهیم.
 *
 * @param int      $order_id شناسه سفارش.
 * @param WC_Order $order    سفارش.
 * @return void
 */
function fs_capture_failure_reason( $order_id, $order = null ) {
	if ( ! $order ) {
		$order = wc_get_order( $order_id );
	}

	if ( ! $order ) {
		return;
	}

	$order->update_meta_data( '_fs_failure_gateway', $order->get_payment_method() );

	if ( ! $order->get_meta( '_fs_failure_reason' ) && function_exists( 'wc_get_order_notes' ) ) {
		$notes = wc_get_order_notes(
			array(
				'order_id' => $order->get_id(),
				'limit'    => 1,
				'type'     => 'internal',
			)
		);

		if ( $notes ) {
			$order->update_meta_data( '_fs_failure_reason', wp_strip_all_tags( $notes[0]->content ) );
		}
	}

	$order->save();
}
add_action( 'woocommerce_order_status_failed', 'fs_capture_failure_reason', 10, 2 );

/**
 * راهنمای گام‌به‌گام برای کاربری که پرداختش ناموفق بوده است.
 *
 * متن پیش‌فرض عمداً عمومی و مطمئن است، نه توضیح کدهای خطای اختصاصی هر درگاه.
 * برای متن اختصاصی هر درگاه از فیلتر `fs_payment_failure_guidance` استفاده کنید.
 *
 * @param string $gateway_id شناسه درگاه، مثلاً `zibal` یا `zarinpal`.
 * @return string[]
 */
function fs_get_payment_failure_guidance( $gateway_id = '' ) {
	$steps = array(
		'موجودی حساب و سقف تراکنش کارت خود را بررسی کنید.',
		'اگر رمز پویا (رمز دوم) فعال نیست، از طریق همراه‌بانک آن را فعال کنید.',
		'اتصال اینترنت خود را بررسی و دوباره تلاش کنید.',
		'اگر مبلغ از حساب شما کم شده، تا ۷۲ ساعت به‌صورت خودکار برمی‌گردد؛ در غیر این صورت با پشتیبانی تماس بگیرید.',
	);

	return apply_filters( 'fs_payment_failure_guidance', $steps, $gateway_id );
}

/* -------------------------------------------------------------------------
   ۵ · ترجمه‌ی رشته‌هایی که ووکامرس انگلیسی نشان می‌دهد
   ------------------------------------------------------------------------- */

/**
 * جایگزینی چند رشته‌ی پرکاربرد تسویه‌حساب که در نصب‌های بدون فایل ترجمه
 * انگلیسی باقی می‌مانند («Coupon code»، «Apply» و …).
 *
 * @param string $translated متن ترجمه‌شده.
 * @param string $original   متن اصلی.
 * @param string $domain     دامنه‌ی ترجمه.
 * @return string
 */
function fs_woo_strings( $translated, $original, $domain ) {
	if ( 'woocommerce' !== $domain || is_admin() ) {
		return $translated;
	}

	$map = array(
		'Coupon code'                             => 'کد تخفیف',
		'Apply'                                   => 'اعمال کد',
		'Apply coupon'                            => 'اعمال کد تخفیف',
		'Have a coupon?'                          => 'کد تخفیف دارید؟',
		'If you have a coupon code, please apply it below.' => 'اگر کد تخفیف دارید، آن را در کادر زیر وارد کنید.',
		'Click here to enter your code'           => 'برای وارد کردن کد اینجا کلیک کنید',
		'You must be logged in to checkout.'      => 'برای تکمیل خرید باید وارد حساب کاربری خود شوید.',
		'Place order'                             => 'پرداخت و دریافت فایل',
		'Your order'                              => 'سفارش شما',
		'Billing details'                         => 'اطلاعات دریافت فایل',
		'Additional information'                  => 'توضیحات بیشتر',
	);

	return isset( $map[ $original ] ) ? $map[ $original ] : $translated;
}
add_filter( 'gettext', 'fs_woo_strings', 20, 3 );

/* -------------------------------------------------------------------------
   ۶ · جلوگیری از کش‌شدن صفحه‌های خرید
   -------------------------------------------------------------------------
   نشانه‌ی مشکل: کاربر ناشناس در حالت ناشناس (Incognito) نوار مدیریت و نام
   «pooga» و حتی نام و موبایل و ایمیل او را روی صفحه‌ی تسویه‌حساب می‌دید.
   یعنی HTMLی که یک‌بار برای کاربر لاگین‌شده ساخته شده بود، از کش سرور/افزونه/
   CDN به همه سرو می‌شد.

   این فقط یک مشکل ظاهری نیست: آن صفحه‌ی کش‌شده nonce و سشن کاربر دیگری را
   هم با خودش دارد، و ووکامرس هنگام ثبت سفارش آن را رد می‌کند
   (WC_Checkout::process_checkout — «نشست شما منقضی شده است») و کاربر دقیقاً
   همان‌جا روی صفحه‌ی خرید می‌ماند.

   ⚠️ توجه: خود ووکامرس از قبل روی سبد خرید / تسویه‌حساب / حساب کاربری
   ثابت DONOTCACHEPAGE را تعریف و هدر no-cache می‌فرستد
   (WC_Cache_Helper::prevent_caching). پس اینکه صفحه باز هم کش شده یعنی
   کشی که جلوی PHP نشسته — کش سطح سرور (Nginx FastCGI / LiteSpeed) یا CDN —
   این علامت‌ها را نادیده می‌گیرد. **درمان اصلی، تنظیم همان کش یا CDN است.**

   چیزی که کد پایین اضافه می‌کند و ووکامرس ندارد:
   - پوشش‌دادن «هر بازدید کاربر لاگین‌شده» در تمام صفحه‌ها، نه فقط سه صفحه‌ی
     فروشگاهی — همین بود که نوار مدیریت pooga را به صفحه‌ی کاربر ناشناس برد.
   - هدرهای صریح no-store / private و Vary: Cookie و هدرهای اختصاصی
     Nginx و LiteSpeed که ووکامرس نمی‌فرستد.
   ------------------------------------------------------------------------- */

/**
 * علامت‌زدن صفحه‌های خصوصی به‌عنوان «کش نشود».
 *
 * @return void
 */
function fs_never_cache_private_pages() {
	if ( is_admin() ) {
		return;
	}

	$is_private = is_user_logged_in();

	if ( ! $is_private && fs_has_woo() ) {
		$is_private = is_cart() || is_checkout() || is_account_page();
	}

	if ( ! $is_private ) {
		return;
	}

	// ثابت‌هایی که W3 Total Cache، WP Super Cache، WP Rocket، Comet Cache و… می‌شناسند.
	foreach ( array( 'DONOTCACHEPAGE', 'DONOTCACHEOBJECT', 'DONOTCACHEDB' ) as $fs_const ) {
		if ( ! defined( $fs_const ) ) {
			define( $fs_const, true );
		}
	}

	// لایت‌اسپید کش.
	do_action( 'litespeed_control_set_nocache', 'fanni-soal: private page' );

	if ( headers_sent() ) {
		return;
	}

	nocache_headers();
	header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private', true );
	header( 'Pragma: no-cache' );
	header( 'Vary: Cookie', false );
	header( 'X-Accel-Expires: 0' );          // Nginx FastCGI / proxy cache.
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
}
add_action( 'template_redirect', 'fs_never_cache_private_pages', 0 );
