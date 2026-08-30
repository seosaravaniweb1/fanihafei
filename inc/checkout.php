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

/* -------------------------------------------------------------------------
   سازگاری با درگاه‌های پرداخت ایرانی
   -------------------------------------------------------------------------
   فرم تسویه‌حساب این فروشگاه فیلدهای نشانی را نشان نمی‌دهد — برای فروش فایل
   دانلودی هیچ‌کدامشان معنا ندارند. ولی «نشان‌ندادن» با «نداشتن» یکی نیست:

   ووکامرس هنوز بر اساس قواعد محلیِ کشور (woocommerce_get_country_locale)
   کدپستی و استان را الزامی می‌داند و در اعتبارسنجی رد می‌کند، و افزونه‌های
   درگاه ایرانی (زیبال، زرین‌پال) هنگام ساخت درخواست پرداخت کشور صورتحساب را
   می‌خوانند. نتیجه‌ی حذف کامل فیلدها این بود که کاربر دکمه‌ی پرداخت را می‌زد و
   هیچ اتفاقی نمی‌افتاد؛ خطا در پاسخ اجاکس می‌ماند و به چشم نمی‌آمد.

   پس فیلدها از فرم بیرون می‌مانند ولی مقدارشان سمت سرور پر می‌شود.
   ------------------------------------------------------------------------- */

/**
 * مقدار پیش‌فرض فیلدهای نشانی که در فرم نیستند.
 *
 * @return array<string, string>
 */
function fs_billing_defaults() {
	return apply_filters(
		'fs_billing_defaults',
		array(
			'billing_country'   => 'IR',
			'billing_state'     => '',
			'billing_city'      => '-',
			'billing_address_1' => '-',
			'billing_address_2' => '',
			'billing_postcode'  => '0000000000',
			'billing_company'   => '',
		)
	);
}

/**
 * پرکردن فیلدهای نشانی در داده‌های ارسالی تسویه‌حساب.
 *
 * اولویت ۵ تا پیش از هر دستکاری دیگری روی همین داده اجرا شود.
 *
 * @param array $data داده‌های ارسالی.
 * @return array
 */
function fs_checkout_posted_defaults( $data ) {
	foreach ( fs_billing_defaults() as $key => $value ) {
		if ( empty( $data[ $key ] ) ) {
			$data[ $key ] = $value;
		}
	}

	return $data;
}
add_filter( 'woocommerce_checkout_posted_data', 'fs_checkout_posted_defaults', 5 );

/**
 * برداشتن الزام فیلدهای نشانی از قواعد محلی کشورها.
 *
 * بدون این، ووکامرس برای ایران کدپستی را الزامی می‌داند و چون فیلدش در فرم
 * نیست، اعتبارسنجی همیشه رد می‌شود.
 *
 * @param array $locale قواعد محلی.
 * @return array
 */
function fs_address_locale_optional( $locale ) {
	$optional = array( 'address_1', 'address_2', 'city', 'state', 'postcode', 'company' );

	foreach ( $locale as $country => $fields ) {
		foreach ( $optional as $field ) {
			if ( isset( $locale[ $country ][ $field ] ) ) {
				$locale[ $country ][ $field ]['required'] = false;
				$locale[ $country ][ $field ]['hidden']   = true;
			}
		}
	}

	return $locale;
}
add_filter( 'woocommerce_get_country_locale', 'fs_address_locale_optional' );

// قالبِ کدپستی و استان هم نباید سنجیده شود؛ مقدارشان ساختگی و بی‌استفاده است.
add_filter( 'woocommerce_validate_postcode', '__return_true', 10, 3 );
add_filter( 'woocommerce_validate_state', '__return_true', 10, 3 );

// کادر «یادداشت سفارش» در فروش فایل کاربردی ندارد و در فرم هم جا نگرفته بود.
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );

/**
 * نشانی صورتحساب در پیشخوان و ایمیل‌ها — فقط چیزی که واقعاً داریم.
 *
 * وگرنه «تهران، -، ۰۰۰۰۰۰۰۰۰۰» به مدیر و مشتری نشان داده می‌شود.
 *
 * @param string   $address نشانی قالب‌بندی‌شده.
 * @param array    $raw     مقادیر خام.
 * @param WC_Order $order   سفارش.
 * @return string
 */
function fs_formatted_billing_address( $address, $raw, $order = null ) {
	unset( $order );

	$parts = array_filter(
		array(
			trim( ( isset( $raw['first_name'] ) ? $raw['first_name'] : '' ) . ' ' . ( isset( $raw['last_name'] ) ? $raw['last_name'] : '' ) ),
			isset( $raw['phone'] ) ? $raw['phone'] : '',
			isset( $raw['email'] ) ? $raw['email'] : '',
		)
	);

	return $parts ? implode( '<br/>', array_map( 'esc_html', $parts ) ) : $address;
}
add_filter( 'woocommerce_order_get_formatted_billing_address', 'fs_formatted_billing_address', 10, 3 );

/**
 * ست‌کردن نشانی پیش‌فرض روی خود مشتری، به‌محض رسیدن به تسویه‌حساب.
 *
 * فقط پرکردن داده‌های ارسالی کافی نیست: ووکامرس فهرست درگاه‌های در دسترس را
 * بر اساس کشور مشتری می‌سازد و اگر کشور خالی باشد ممکن است هیچ درگاهی نمایش
 * داده نشود. این مقدار در سشن ووکامرس می‌ماند و در ثبت سفارش هم به کار می‌آید.
 *
 * @return void
 */
function fs_seed_customer_address() {
	if ( ! fs_has_woo() || ! is_checkout() || is_order_received_page() || ! WC()->customer ) {
		return;
	}

	if ( WC()->customer->get_billing_country() ) {
		return;
	}

	$defaults = fs_billing_defaults();

	WC()->customer->set_billing_country( $defaults['billing_country'] );
	WC()->customer->set_billing_city( $defaults['billing_city'] );
	WC()->customer->set_billing_address_1( $defaults['billing_address_1'] );
	WC()->customer->set_billing_postcode( $defaults['billing_postcode'] );
	WC()->customer->save();
}
add_action( 'template_redirect', 'fs_seed_customer_address', 20 );

/**
 * ثبت سفارش با ارسال واقعی فرم، نه اجاکس.
 *
 * مسیر اجاکس ووکامرس پاسخ را به‌صورت JSON برمی‌گرداند و جاوااسکریپت باید
 * ریدایرکت به درگاه را خودش انجام دهد. هر خطای کوچکی در آن مسیر — یک خطای
 * جاوااسکریپت، پاسخ ناقص، یا افزونه‌ای که خروجی اضافه چاپ کند — به این ختم
 * می‌شود که دکمه‌ی پرداخت هیچ کاری نمی‌کند و کاربر هیچ پیامی نمی‌بیند.
 *
 * ارسال معمولی فرم مسیر پشتیبان خود ووکامرس است: سرور ریدایرکت واقعی به درگاه
 * می‌دهد و اگر خطایی باشد روی همان صفحه دیده می‌شود.
 *
 * @return void
 */
function fs_non_ajax_checkout() {
	if ( ! fs_has_woo() || ! is_checkout() || is_order_received_page() ) {
		return;
	}

	wp_dequeue_script( 'wc-checkout' );
	wp_deregister_script( 'wc-checkout' );
}
add_action( 'wp_enqueue_scripts', 'fs_non_ajax_checkout', 100 );

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

	// اگر کاربر لاگین است و ایمیل واقعی روی حسابش دارد، همان باید در سفارش
	// بنشیند. پیش از این، خالی‌گذاشتن کادر ایمیل باعث می‌شد نشانی ساختگی
	// جای ایمیل واقعی را بگیرد و لینک دانلود هرگز به دست کاربر نرسد.
	if ( is_user_logged_in() ) {
		$account_email = wp_get_current_user()->user_email;

		if ( $account_email && is_email( $account_email ) && ! fs_is_auto_email( $account_email ) ) {
			$data['billing_email'] = $account_email;

			return $data;
		}
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
	// order-received و order-pay هر دو زیرمجموعه‌ی is_checkout() هستند ولی
	// صفحه‌ی «فرم خرید» نیستند: اولی بازگشت از درگاه است و دومی پرداخت دوباره‌ی
	// یک سفارش موجود. ریدایرکت‌کردنشان به صفحه‌ی ورود، بازگشت از زرین‌پال و
	// زیبال را می‌شکند.
	if ( ! fs_has_woo() || is_user_logged_in() || ! is_checkout() ) {
		return;
	}

	if ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) {
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
 * هر فایل فقط یک نسخه.
 *
 * سبد می‌تواند چند فایل مختلف داشته باشد، ولی از یک فایل دو نسخه بی‌معناست؛
 * با این فیلتر خود ووکامرس کادر «تعداد» را حذف می‌کند.
 *
 * @return bool
 */
function fs_sold_individually() {
	return true;
}
add_filter( 'woocommerce_is_sold_individually', 'fs_sold_individually', 20 );
