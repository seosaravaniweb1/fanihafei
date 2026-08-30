<?php
/**
 * ترمیم مالکیت سفارش، ماندگاری ورود و مهار ایمیل‌های ساختگی.
 *
 * این فایل سه شکاف را می‌بندد که هر سه از یک تصمیم معماری می‌آیند: ایمیل در این
 * فروشگاه اختیاری است و اگر کاربر ندهد، از روی شماره موبایلش ساخته می‌شود.
 *
 * ۱) ردیف‌های جدول مجوز دانلود، شناسه‌ی کاربر را در همان لحظه‌ی صدور «اسنپ‌شات»
 *    می‌گیرند؛ اگر آن لحظه سفارش هنوز به حساب وصل نشده باشد، بعداً اصلاح نمی‌شود.
 * ۲) بازگشت از درگاه نباید کاربر را مهمان نشان دهد — نه با کش، نه با بسته‌شدن
 *    پنجره‌ی مرورگر.
 * ۳) نشانی ساختگی نباید هیچ‌وقت مقصد یک ایمیل واقعی شود.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
   ۱) ترمیم مجوزهای دانلود
   ------------------------------------------------------------------------- */

/**
 * هم‌ترازکردن ردیف‌های مجوز دانلود با مالک واقعی سفارش.
 *
 * ووکامرس هنگام صدور مجوز، `user_id` را از `$order->get_customer_id()` و
 * `user_email` را از ایمیل صورتحساب کپی می‌کند (wc-order-functions.php).
 * اگر سفارش در آن لحظه هنوز به حساب وصل نشده بود، ردیف با `user_id = 0`
 * نوشته می‌شود و دیگر خودبه‌خود درست نمی‌شود، چون
 * `wc_downloadable_product_permissions()` بدون `$force` زودتر return می‌کند.
 *
 * نتیجه‌اش برای کاربر: «فایل‌های دانلودی» پیشخوان خالی است — آن کوئری فقط
 * `WHERE user_id = %d` را می‌بیند — و کلیک روی لینک دانلود پیام
 * «This is not your download link» می‌دهد.
 *
 * اینجا اول بررسی می‌کنیم واقعاً چیزی خراب هست یا نه؛ اگر نبود دست نمی‌زنیم.
 *
 * @param int $order_id شناسه سفارش.
 * @return void
 */
function fs_repair_download_permissions( $order_id ) {
	if ( ! function_exists( 'wc_get_order' ) ) {
		return;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order || ! $order->has_downloadable_item() ) {
		return;
	}

	$user_id = (int) $order->get_customer_id();

	// بدون مالک مشخص چیزی برای ترمیم نیست؛ fs_attach_order_to_user زودتر
	// تلاش خودش را کرده است.
	if ( ! $user_id ) {
		return;
	}

	global $wpdb;

	$table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
	$email = $order->get_billing_email();

	$stale = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE order_id = %d AND ( user_id <> %d OR user_email <> %s )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$order->get_id(),
			$user_id,
			$email
		)
	);

	if ( ! $stale ) {
		return;
	}

	// همان مسیری که خود ووکامرس در wc_update_new_customer_past_orders می‌رود:
	// ردیف‌های قدیمی حذف، بعد صدور دوباره با force.
	WC_Data_Store::load( 'customer-download' )->delete_by_order_id( $order->get_id() );
	wc_downloadable_product_permissions( $order->get_id(), true );
}
add_action( 'woocommerce_payment_complete', 'fs_repair_download_permissions', 20 );
add_action( 'woocommerce_order_status_completed', 'fs_repair_download_permissions', 20 );
add_action( 'woocommerce_thankyou', 'fs_repair_download_permissions', 20 );

/* -------------------------------------------------------------------------
   ۲) ماندگاری ورود
   ------------------------------------------------------------------------- */

/**
 * عمر کوکی ورود مشتری‌ها.
 *
 * خواسته این است که بستن پنجره‌ی مرورگر کاربر را بیرون نیندازد. کوکی «مرا به
 * خاطر بسپار» پیش‌فرض وردپرس ۱۴ روز است؛ برای فروشگاه فایل که کاربر ممکن است
 * هفته‌ها بعد برای دانلود دوباره برگردد، کوتاه است.
 *
 * فقط برای مشتری‌ها بلند می‌شود؛ حساب‌های مدیریتی روی مقدار پیش‌فرض وردپرس
 * می‌مانند تا نشست‌های بلندمدتِ پرخطر نسازیم.
 *
 * @param int  $length   طول فعلی (ثانیه).
 * @param int  $user_id  شناسه کاربر.
 * @param bool $remember آیا «مرا به خاطر بسپار» فعال بوده.
 * @return int
 */
function fs_auth_cookie_life( $length, $user_id = 0, $remember = false ) {
	if ( ! $remember || ! $user_id ) {
		return $length;
	}

	if ( user_can( $user_id, 'edit_posts' ) || user_can( $user_id, 'manage_woocommerce' ) ) {
		return $length;
	}

	return (int) apply_filters( 'fs_customer_session_days', 60 ) * DAY_IN_SECONDS;
}
add_filter( 'auth_cookie_expiration', 'fs_auth_cookie_life', 20, 3 );

/**
 * صفحه‌هایی که هرگز نباید کش شوند.
 *
 * بازگشت از زرین‌پال و زیبال با ریدایرکت GET انجام می‌شود، پس کوکی ورود همراه
 * درخواست هست؛ ولی اگر لایه‌ی کش نسخه‌ی ذخیره‌شده‌ی صفحه را بدهد، کاربر
 * «مهمان» دیده می‌شود در حالی که واقعاً لاگین است.
 *
 * `DONOTCACHEPAGE` را WP Rocket، LiteSpeed، W3TC و WP Super Cache همگی
 * می‌شناسند. این تنظیمِ داخل پلاگین را بی‌نیاز نمی‌کند، ولی تور ایمنی است.
 *
 * @return void
 */
function fs_never_cache_transactional() {
	if ( ! fs_has_woo() ) {
		return;
	}

	$sensitive = is_checkout() || is_cart() || is_account_page()
		|| is_wc_endpoint_url( 'order-received' )
		|| is_wc_endpoint_url( 'order-pay' );

	if ( ! $sensitive ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
		define( 'DONOTCACHEOBJECT', true );
	}

	nocache_headers();
}
add_action( 'template_redirect', 'fs_never_cache_transactional', 0 );

/* -------------------------------------------------------------------------
   ۳) مهار ایمیل‌های ساختگی
   ------------------------------------------------------------------------- */

/**
 * فهرست ایمیل‌های مشتری که نباید به نشانی ساختگی بروند.
 *
 * @return string[]
 */
function fs_customer_email_ids() {
	return array(
		'customer_completed_order',
		'customer_processing_order',
		'customer_on_hold_order',
		'customer_invoice',
		'customer_note',
		'customer_refunded_order',
		'customer_partially_refunded_order',
		'customer_new_account',
		'customer_reset_password',
	);
}

/**
 * خالی‌کردن گیرنده وقتی نشانی ساختگی است.
 *
 * جلوی ساخته‌شدن ایمیل را از همان اول می‌گیرد، پس نه در صف SMTP می‌نشیند و نه
 * در گزارش‌ها به‌عنوان «ارسال‌شده» ثبت می‌شود.
 *
 * @param string $recipient گیرنده.
 * @return string
 */
function fs_drop_auto_email_recipient( $recipient ) {
	if ( ! $recipient || ! function_exists( 'fs_is_auto_email' ) ) {
		return $recipient;
	}

	$keep = array_filter(
		array_map( 'trim', explode( ',', $recipient ) ),
		static function ( $address ) {
			return ! fs_is_auto_email( $address );
		}
	);

	return implode( ',', $keep );
}

foreach ( fs_customer_email_ids() as $fs_email_id ) {
	add_filter( 'woocommerce_email_recipient_' . $fs_email_id, 'fs_drop_auto_email_recipient', 20 );
}

unset( $fs_email_id );

/**
 * تور ایمنی نهایی روی wp_mail.
 *
 * پلاگین فاکتور، افزونه‌ی SMTP و خود وردپرس (اعلان ساخت حساب) مسیرهای دیگری
 * دارند که فیلترهای بالا را رد می‌کنند. اگر *همه‌ی* گیرنده‌ها ساختگی بودند،
 * ارسال را کوتاه می‌کنیم؛ اگر حتی یک نشانی واقعی در فهرست باشد، دست نمی‌زنیم
 * تا رونوشت مدیر از بین نرود.
 *
 * @param null|bool $short_circuit مقدار کوتاه‌کننده.
 * @param array     $atts          آرگومان‌های wp_mail.
 * @return null|bool
 */
function fs_block_mail_to_auto_email( $short_circuit, $atts ) {
	if ( null !== $short_circuit || ! function_exists( 'fs_is_auto_email' ) ) {
		return $short_circuit;
	}

	$to = isset( $atts['to'] ) ? $atts['to'] : '';
	$to = is_array( $to ) ? $to : explode( ',', (string) $to );

	$addresses = array_filter( array_map( 'trim', $to ) );

	if ( ! $addresses ) {
		return $short_circuit;
	}

	foreach ( $addresses as $address ) {
		// نشانی ممکن است به شکل "نام <mail@example.com>" باشد.
		if ( preg_match( '/<([^>]+)>/', $address, $m ) ) {
			$address = $m[1];
		}

		if ( ! fs_is_auto_email( $address ) ) {
			return $short_circuit; // دست‌کم یک گیرنده‌ی واقعی هست.
		}
	}

	// همه ساختگی بودند: وانمود کن ارسال شد تا خطای بی‌مورد ثبت نشود.
	return true;
}
add_filter( 'pre_wp_mail', 'fs_block_mail_to_auto_email', 20, 2 );

/**
 * نشانه‌گذاری کاربران قدیمی که ایمیل ساختگی دارند ولی فلگ‌شان ثبت نشده.
 *
 * @param int $user_id شناسه کاربر.
 * @return void
 */
function fs_backfill_auto_email_flag( $user_id ) {
	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return;
	}

	if ( fs_is_auto_email( $user->user_email ) ) {
		update_user_meta( $user_id, FS_AUTO_EMAIL_META, 'yes' );
	} else {
		delete_user_meta( $user_id, FS_AUTO_EMAIL_META );
	}
}
add_action( 'profile_update', 'fs_backfill_auto_email_flag' );
add_action( 'woocommerce_save_account_details', 'fs_backfill_auto_email_flag' );
