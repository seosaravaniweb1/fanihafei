<?php
/**
 * تسویه‌حساب یک‌مرحله‌ای، حذف فیلدهای نشانی و رفع مشکل دسترسی به دانلود.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

/**
 * فیلدهایی که این فروشگاه اصلاً لازم ندارد (فروش فایل است).
 *
 * @return string[]
 */
function fs_removed_billing_fields() {
	return apply_filters(
		'fs_removed_billing_fields',
		array(
			'billing_company',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_postcode',
			'billing_country',
			'billing_state',
		)
	);
}

/**
 * حذف فیلدهای نشانی از فرم تسویه‌حساب.
 *
 * @param array $fields فیلدها.
 * @return array
 */
function fs_checkout_fields( $fields ) {
	foreach ( fs_removed_billing_fields() as $key ) {
		unset( $fields['billing'][ $key ] );
	}

	unset( $fields['shipping'] );
	unset( $fields['order']['order_comments'] );

	if ( isset( $fields['billing']['billing_first_name'] ) ) {
		$fields['billing']['billing_first_name']['label']    = 'نام';
		$fields['billing']['billing_first_name']['priority'] = 10;
		$fields['billing']['billing_first_name']['class']    = array( 'form-row-first' );
	}

	if ( isset( $fields['billing']['billing_last_name'] ) ) {
		$fields['billing']['billing_last_name']['label']    = 'نام خانوادگی';
		$fields['billing']['billing_last_name']['priority'] = 20;
		$fields['billing']['billing_last_name']['class']    = array( 'form-row-last' );
	}

	if ( isset( $fields['billing']['billing_phone'] ) ) {
		$fields['billing']['billing_phone']['label']    = 'شماره موبایل';
		$fields['billing']['billing_phone']['required'] = true;
		$fields['billing']['billing_phone']['priority'] = 30;
		$fields['billing']['billing_phone']['class']    = array( 'form-row-wide' );
	}

	// ایمیل خودکار است؛ نمایش داده می‌شود ولی اجباری نیست و اگر خالی بماند
	// از روی شماره موبایل ساخته می‌شود.
	if ( isset( $fields['billing']['billing_email'] ) ) {
		$fields['billing']['billing_email']['label']       = 'ایمیل (اختیاری)';
		$fields['billing']['billing_email']['required']    = false;
		$fields['billing']['billing_email']['priority']    = 40;
		$fields['billing']['billing_email']['class']       = array( 'form-row-wide' );
		$fields['billing']['billing_email']['description'] = 'اگر خالی بگذارید، خودکار از روی شماره موبایل ساخته می‌شود.';
	}

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'fs_checkout_fields', 20 );

/**
 * حذف همان فیلدها از هر جای دیگر (پیشخوان، ویرایش نشانی).
 *
 * @param array $fields فیلدها.
 * @return array
 */
function fs_billing_fields( $fields ) {
	foreach ( fs_removed_billing_fields() as $key ) {
		unset( $fields[ str_replace( 'billing_', '', $key ) ] );
	}

	return $fields;
}
add_filter( 'woocommerce_billing_fields', 'fs_billing_fields', 20 );

add_filter( 'woocommerce_default_address_fields', 'fs_default_address_fields', 20 );

/**
 * حذف فیلدهای نشانی از قالب پیش‌فرض نشانی‌ها.
 *
 * @param array $fields فیلدها.
 * @return array
 */
function fs_default_address_fields( $fields ) {
	foreach ( array( 'company', 'address_1', 'address_2', 'city', 'postcode', 'country', 'state' ) as $key ) {
		unset( $fields[ $key ] );
	}

	return $fields;
}

/**
 * ایمیل خودکار اگر کاربر ایمیلی وارد نکرده باشد.
 *
 * @param array $data داده‌های ارسالی.
 * @return array
 */
function fs_fill_email( $data ) {
	if ( ! empty( $data['billing_email'] ) && is_email( $data['billing_email'] ) ) {
		return $data;
	}

	$phone = '';

	if ( ! empty( $data['billing_phone'] ) ) {
		$phone = fs_normalize_phone( $data['billing_phone'] );
	}

	if ( ! $phone && is_user_logged_in() ) {
		$phone = get_user_meta( get_current_user_id(), FS_PHONE_META, true );
	}

	if ( $phone ) {
		$data['billing_email'] = fs_auth_phone_email( $phone );
	} elseif ( is_user_logged_in() ) {
		$data['billing_email'] = wp_get_current_user()->user_email;
	}

	return $data;
}
add_filter( 'woocommerce_checkout_posted_data', 'fs_fill_email', 20 );

/**
 * یکسان‌سازی شماره موبایل ثبت‌شده در سفارش.
 *
 * @param array $data داده‌های ارسالی.
 * @return array
 */
function fs_normalize_order_phone( $data ) {
	if ( ! empty( $data['billing_phone'] ) ) {
		$normalized = fs_normalize_phone( $data['billing_phone'] );

		if ( $normalized ) {
			$data['billing_phone'] = $normalized;
		}
	}

	return $data;
}
add_filter( 'woocommerce_checkout_posted_data', 'fs_normalize_order_phone', 10 );

/**
 * خرید فقط با حساب کاربری — مهمان‌ها به صفحه ورود می‌روند و پس از ورود
 * دوباره به تسویه‌حساب برمی‌گردند.
 *
 * @return void
 */
function fs_require_login_for_checkout() {
	if ( ! fs_has_woo() || is_user_logged_in() || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}

	wp_safe_redirect(
		add_query_arg( 'redirect_to', rawurlencode( wc_get_checkout_url() ), fs_account_url() )
	);
	exit;
}
add_action( 'template_redirect', 'fs_require_login_for_checkout' );

/**
 * غیرفعال‌کردن خرید مهمان.
 *
 * @return string
 */
function fs_disable_guest_checkout() {
	return 'no';
}
add_filter( 'pre_option_woocommerce_enable_guest_checkout', 'fs_disable_guest_checkout' );

/**
 * سفارش را به حساب کاربر وصل کن (اگر به هر دلیلی وصل نشده بود).
 *
 * بدون این اتصال، کاربر بعد از خرید در پیشخوانش فایلی نمی‌بیند.
 *
 * @param int $order_id شناسه سفارش.
 * @return void
 */
function fs_attach_order_to_user( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( ! $order || $order->get_customer_id() ) {
		return;
	}

	$user = null;
	$mail = $order->get_billing_email();

	if ( $mail ) {
		$user = get_user_by( 'email', $mail );
	}

	if ( ! $user ) {
		$phone = fs_normalize_phone( $order->get_billing_phone() );

		if ( $phone ) {
			$user = fs_find_user_by_phone( $phone );
		}
	}

	if ( $user ) {
		$order->set_customer_id( $user->ID );
		$order->save();
	}
}
add_action( 'woocommerce_checkout_order_processed', 'fs_attach_order_to_user', 20 );
add_action( 'woocommerce_thankyou', 'fs_attach_order_to_user', 5 );

/**
 * سفارش‌هایی که فقط فایل دانلودی دارند، پس از پرداخت مستقیم «تکمیل‌شده» شوند
 * تا لینک دانلود بلافاصله فعال شود.
 *
 * @param string $status   وضعیت پیشنهادی.
 * @param int    $order_id شناسه سفارش.
 * @param object $order    سفارش.
 * @return string
 */
function fs_autocomplete_downloadables( $status, $order_id = 0, $order = null ) {
	if ( ! $order ) {
		$order = wc_get_order( $order_id );
	}

	if ( ! $order ) {
		return $status;
	}

	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();

		if ( ! $product || ! $product->is_downloadable() ) {
			return $status;
		}
	}

	return 'completed';
}
add_filter( 'woocommerce_payment_complete_order_status', 'fs_autocomplete_downloadables', 20, 3 );

/**
 * دسترسی دانلود به‌محض پرداخت داده شود، نه فقط در وضعیت «تکمیل‌شده».
 *
 * @return string
 */
function fs_grant_access_after_payment() {
	return 'yes';
}
add_filter( 'pre_option_woocommerce_downloads_grant_access_after_payment', 'fs_grant_access_after_payment' );

/**
 * اجازه دانلود برای هر سفارش پرداخت‌شده.
 *
 * علت رایج «عدم دسترسی به فایل» این است که وضعیت سفارش هنوز «در حال انجام» است.
 *
 * @param bool   $permitted آیا مجاز است.
 * @param object $order     سفارش.
 * @return bool
 */
function fs_download_permitted( $permitted, $order ) {
	if ( $permitted || ! $order ) {
		return $permitted;
	}

	return $order->is_paid() || $order->has_status( array( 'processing', 'completed' ) );
}
add_filter( 'woocommerce_order_is_download_permitted', 'fs_download_permitted', 20, 2 );

/**
 * اگر کاربر لاگین است ولی سفارش به ایمیل دیگری ثبت شده، باز هم اجازه دانلود
 * داشته باشد — تطبیق بر اساس شناسه کاربر سفارش.
 *
 * @param bool  $granted وضعیت فعلی.
 * @param array $data    داده‌های دانلود.
 * @return bool
 */
function fs_download_access( $granted, $data ) {
	if ( $granted || ! is_user_logged_in() || empty( $data['order_id'] ) ) {
		return $granted;
	}

	$order = wc_get_order( $data['order_id'] );

	return $order && (int) $order->get_customer_id() === get_current_user_id();
}
add_filter( 'woocommerce_download_product_permissions_check', 'fs_download_access', 20, 2 );

/**
 * حذف مراحل اضافی: بدون حمل‌ونقل، بدون یادداشت سفارش.
 *
 * @return bool
 */
function fs_no_shipping() {
	return false;
}
add_filter( 'woocommerce_cart_needs_shipping', 'fs_no_shipping' );
add_filter( 'woocommerce_cart_needs_shipping_address', 'fs_no_shipping' );

/**
 * متن همه‌ی دکمه‌های خرید — فروشگاه تک‌فروشی است و هر کلیک یعنی خرید همان
 * یک فایل، نه انباشتن سبد؛ پس «افزودن به سبد خرید» گمراه‌کننده است.
 *
 * @return string
 */
function fs_add_to_cart_text() {
	return apply_filters( 'fs_buy_button_text', 'خرید و دانلود' );
}
add_filter( 'woocommerce_product_single_add_to_cart_text', 'fs_add_to_cart_text' );
add_filter( 'woocommerce_product_add_to_cart_text', 'fs_add_to_cart_text' );

/**
 * هر فایل فقط یک نسخه — با این فیلتر خود ووکامرس کادر «تعداد» را حذف می‌کند
 * و دیگر جایی برای سفارش دو نسخه از یک فایل دانلودی نمی‌ماند.
 *
 * @return bool
 */
function fs_sold_individually() {
	return true;
}
add_filter( 'woocommerce_is_sold_individually', 'fs_sold_individually', 20 );

/**
 * تک‌فروشی: پیش از افزودن فایل تازه، هرچه در سبد بوده پاک می‌شود تا
 * تسویه‌حساب همیشه دقیقاً همان یک فایلی باشد که کاربر همین حالا انتخاب کرده.
 *
 * @param bool $passed آیا اعتبارسنجی رد شده است.
 * @return bool
 */
function fs_single_item_cart( $passed ) {
	if ( $passed && fs_has_woo() && function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
		WC()->cart->empty_cart( false );
	}

	return $passed;
}
add_filter( 'woocommerce_add_to_cart_validation', 'fs_single_item_cart', 20 );

/**
 * بعد از انتخاب فایل، مستقیم به مراحل خرید برود.
 *
 * @param string $url نشانی پیشنهادی ووکامرس.
 * @return string
 */
function fs_add_to_cart_redirect( $url ) {
	return fs_has_woo() ? wc_get_checkout_url() : $url;
}
add_filter( 'woocommerce_add_to_cart_redirect', 'fs_add_to_cart_redirect', 20 );

/**
 * همان رفتار برای فرم‌های بدون اجاکس.
 *
 * @return string
 */
function fs_redirect_after_add() {
	return 'yes';
}
add_filter( 'pre_option_woocommerce_cart_redirect_after_add', 'fs_redirect_after_add' );
