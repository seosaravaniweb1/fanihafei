<?php
/**
 * ورود و ثبت‌نام — با کد پیامکی و با رمز عبور.
 *
 * ایمیل هنگام ثبت‌نام با موبایل اختیاری است و اگر وارد نشود خودکار از روی شماره
 * ساخته می‌شود (مثلاً 09121234567@example.com) تا ووکامرس همیشه ایمیل داشته باشد
 * و لینک دانلود بعد از خرید کار کند.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

const FS_OTP_TTL      = 120; // اعتبار کد پیامکی (ثانیه).
const FS_OTP_RESEND   = 90;  // فاصله لازم تا ارسال دوباره (ثانیه).
const FS_OTP_TRIES    = 5;   // حداکثر تلاش برای هر کد.
const FS_PHONE_META   = 'billing_phone';

/*
 * محدودیت نرخ.
 *
 * FS_OTP_RESEND فقط برای «همان شماره» بود؛ مهاجم با شماره‌های متفاوت آن را
 * دور می‌زد و می‌توانست هزاران پیامک به حساب شما بفرستد. سقف زیر روی آی‌پی
 * است، مستقل از اینکه چه شماره‌ای وارد می‌شود.
 */
const FS_LOGIN_MAX       = 8;                  // تلاش ناموفق رمز
const FS_LOGIN_WINDOW    = 15 * MINUTE_IN_SECONDS;

// نشانه‌ی کاربرانی که ایمیل واقعی ندادند و نشانی‌شان خودکار ساخته شده است.
const FS_AUTO_EMAIL_META = '_has_auto_generated_email';

/**
 * یکسان‌سازی شماره موبایل به شکل 09xxxxxxxxx.
 *
 * @param string $raw ورودی خام.
 * @return string شماره معتبر یا رشته خالی.
 */
function fs_normalize_phone( $raw ) {
	$digits = preg_replace( '/\D+/', '', fs_en_num( $raw ) );

	if ( ! $digits ) {
		return '';
	}

	if ( 0 === strpos( $digits, '0098' ) ) {
		$digits = substr( $digits, 4 );
	} elseif ( 0 === strpos( $digits, '98' ) && 12 === strlen( $digits ) ) {
		$digits = substr( $digits, 2 );
	}

	if ( 10 === strlen( $digits ) && '9' === $digits[0] ) {
		$digits = '0' . $digits;
	}

	return preg_match( '/^09\d{9}$/', $digits ) ? $digits : '';
}

/**
 * دامنه‌ی ایمیل خودکار.
 *
 * @return string
 */
function fs_auth_email_domain() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$host = preg_replace( '/^www\./i', '', (string) $host );

	return apply_filters( 'fs_auth_email_domain', $host ? $host : 'example.com' );
}

/**
 * ایمیل خودکار بر اساس شماره موبایل.
 *
 * @param string $phone شماره.
 * @return string
 */
function fs_auth_phone_email( $phone ) {
	return $phone . '@' . fs_auth_email_domain();
}

/**
 * یافتن کاربر با شماره موبایل.
 *
 * @param string $phone شماره.
 * @return WP_User|null
 */
function fs_find_user_by_phone( $phone ) {
	$users = get_users(
		array(
			'meta_key'   => FS_PHONE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => $phone, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'number'     => 1,
			'fields'     => 'all',
		)
	);

	if ( $users ) {
		return $users[0];
	}

	// اگر متا نبود، ایمیل خودکار را امتحان کن.
	$user = get_user_by( 'email', fs_auth_phone_email( $phone ) );

	return $user ? $user : null;
}

/**
 * تشخیص کاربر از روی موبایل، ایمیل یا نام کاربری.
 *
 * @param string $login ورودی.
 * @return array{user:?WP_User,phone:string}
 */
function fs_resolve_login( $login ) {
	$login = trim( (string) $login );
	$phone = fs_normalize_phone( $login );

	if ( $phone ) {
		return array(
			'user'  => fs_find_user_by_phone( $phone ),
			'phone' => $phone,
		);
	}

	$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );

	return array(
		'user'  => $user ? $user : null,
		'phone' => '',
	);
}

/* -------------------------------------------------------------------------
   کد یکبار مصرف
   ------------------------------------------------------------------------- */

/**
 * کلید ترنزینت کد.
 *
 * @param string $phone شماره.
 * @return string
 */
function fs_otp_key( $phone ) {
	return 'fs_otp_' . md5( $phone );
}

/**
 * ساخت و ارسال کد پیامکی.
 *
 * @param string $phone شماره.
 * @return true|WP_Error
 */
function fs_otp_send( $phone, $token = '' ) {
	// اینجا تنها جایی است که کد ساخته می‌شود، پس مرزِ حساب‌های مدیریتی باید
	// همین‌جا باشد نه در fs_ajax_auth_entry؛ وگرنه مسیر «ارسال دوباره» که
	// شماره‌ی خام می‌گیرد، دور می‌خورد.
	$owner = fs_find_user_by_phone( $phone );

	if ( $owner && ! fs_user_may_otp( $owner ) ) {
		return new WP_Error( 'fs_otp_privileged', 'برای این حساب، ورود فقط با رمز عبور ممکن است.' );
	}

	// همه‌ی سدها پیش از خرج‌کردن یک پیامک: بن، ساعت مجاز، سقف آی‌پی، سقف
	// شماره، فاصله‌ی اجباری و کپچا.
	$allowed = fs_guard_check_send( $phone, $token );

	if ( is_wp_error( $allowed ) ) {
		return $allowed;
	}

	$key   = fs_otp_key( $phone );
	$saved = get_transient( $key );

	if ( is_array( $saved ) && isset( $saved['sent'] ) && ( time() - $saved['sent'] ) < FS_OTP_RESEND ) {
		return new WP_Error(
			'fs_otp_wait',
			sprintf( 'تا ارسال دوباره %s ثانیه صبر کنید.', fs_fa_num( FS_OTP_RESEND - ( time() - $saved['sent'] ) ) )
		);
	}

	$code = fs_otp_make_code();

	set_transient(
		$key,
		array(
			'hash'  => wp_hash_password( $code ),
			'sent'  => time(),
			'tries' => 0,
		),
		FS_OTP_TTL
	);

	$message = apply_filters(
		'fs_otp_message',
		sprintf( 'کد ورود شما به %1$s: %2$s', get_bloginfo( 'name' ), $code ),
		$code,
		$phone
	);

	/**
	 * ارسال پیامک.
	 *
	 * سرویس پیامک خود را اینجا وصل کنید:
	 *
	 *   add_filter( 'fs_send_sms', function ( $sent, $phone, $message, $code ) {
	 *       // درخواست به وب‌سرویس پیامک
	 *       return true; // یا WP_Error
	 *   }, 10, 4 );
	 */
	$sent = apply_filters( 'fs_send_sms', null, $phone, $message, $code );

	if ( is_wp_error( $sent ) ) {
		delete_transient( $key );

		return $sent;
	}

	// اگر سرویس پیامکی وصل نشده باشد، در حالت اشکال‌زدایی کد را لاگ می‌کنیم.
	if ( null === $sent && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( '[si-file] OTP for %s: %s', $phone, $code ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	return true;
}

/**
 * بررسی کد واردشده.
 *
 * @param string $phone شماره.
 * @param string $code  کد.
 * @return true|WP_Error
 */
function fs_otp_verify( $phone, $code ) {
	$key   = fs_otp_key( $phone );
	$saved = get_transient( $key );

	if ( ! is_array( $saved ) ) {
		return new WP_Error( 'fs_otp_expired', 'کد منقضی شده است. دوباره درخواست کنید.' );
	}

	if ( $saved['tries'] >= FS_OTP_TRIES ) {
		delete_transient( $key );

		return new WP_Error( 'fs_otp_locked', 'تعداد تلاش‌ها بیش از حد بود. کد تازه بگیرید.' );
	}

	$code = preg_replace( '/\D+/', '', fs_en_num( $code ) );

	if ( ! wp_check_password( $code, $saved['hash'] ) ) {
		++$saved['tries'];
		set_transient( $key, $saved, FS_OTP_TTL );

		// شمارنده‌ی بن جدا از tries است: tries با گرفتن کد تازه صفر می‌شود،
		// این یکی نمی‌شود. وگرنه مهاجم فقط کافی بود هر پنج حدس یک کد تازه
		// بگیرد و بی‌نهایت ادامه دهد.
		fs_guard_note_failure( $phone );

		return new WP_Error( 'fs_otp_wrong', 'کد واردشده درست نیست.' );
	}

	delete_transient( $key );
	fs_guard_clear( $phone );

	return true;
}

/**
 * ساخت کد یکبارمصرف.
 *
 * از wp_rand استفاده می‌شود که روی CSPRNG سیستم سوار است، نه rand ساده؛ کدی
 * که با مولد ضعیف ساخته شود از روی کدهای قبلی قابل حدس است.
 *
 * @return string
 */
function fs_otp_make_code() {
	$length = fs_otp_length();
	$min    = (int) pow( 10, $length - 1 );
	$max    = (int) pow( 10, $length ) - 1;

	return (string) wp_rand( $min, $max );
}

/**
 * طول کد یکبارمصرف.
 *
 * باید با تعداد رقم‌هایی که در الگوی پنل پیامک نوشته‌اید یکی باشد.
 *
 * @return int
 */
function fs_otp_length() {
	$length = function_exists( 'fs_get_sms_settings' ) ? (int) fs_get_sms_settings()['code_length'] : 5;

	return min( 8, max( 4, $length ) );
}

/* -------------------------------------------------------------------------
   ساخت کاربر و ورود
   ------------------------------------------------------------------------- */

/**
 * ساخت کاربر تازه با شماره موبایل.
 *
 * @param string $phone شماره.
 * @param array  $args  نام، نام خانوادگی، ایمیل و رمز اختیاری.
 * @return WP_User|WP_Error
 */
function fs_create_user( $phone, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'first_name' => '',
			'last_name'  => '',
			'email'      => '',
			'password'   => '',
		)
	);

	$email = $args['email'] ? sanitize_email( $args['email'] ) : '';
	$auto  = false;

	// ایمیل اختیاری است؛ اگر نبود، خودکار از روی شماره ساخته می‌شود.
	if ( ! $email || ! is_email( $email ) || email_exists( $email ) ) {
		$email = fs_auth_phone_email( $phone );
		$auto  = true;
	}

	if ( email_exists( $email ) ) {
		return new WP_Error( 'fs_email_exists', 'این ایمیل قبلاً ثبت شده است.' );
	}

	$password = $args['password'] ? $args['password'] : wp_generate_password( 16 );

	$user_id = wp_insert_user(
		array(
			'user_login'   => $phone,
			'user_email'   => $email,
			'user_pass'    => $password,
			'first_name'   => sanitize_text_field( $args['first_name'] ),
			'last_name'    => sanitize_text_field( $args['last_name'] ),
			'display_name' => trim( $args['first_name'] . ' ' . $args['last_name'] ),
			'role'         => 'customer',
		)
	);

	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	// نشانه‌گذاری کاربرانی که ایمیل واقعی ندارند تا هیچ ایمیلی به نشانی
	// ساختگی‌شان فرستاده نشود و از گزارش‌های ایمیلی بیرون بمانند.
	if ( $auto ) {
		update_user_meta( $user_id, FS_AUTO_EMAIL_META, 'yes' );
	}

	fs_sync_user_billing( $user_id, $phone, $args['first_name'], $args['last_name'], $email );

	return get_user_by( 'id', $user_id );
}

/**
 * آیا این نشانی، ایمیل ساختگیِ ساخته‌شده از روی شماره موبایل است؟
 *
 * @param string $email نشانی.
 * @return bool
 */
function fs_is_auto_email( $email ) {
	$email = trim( (string) $email );

	if ( ! $email || false === strpos( $email, '@' ) ) {
		return false;
	}

	list( $local, $domain ) = explode( '@', $email, 2 );

	return (bool) preg_match( '/^09\d{9}$/', $local )
		&& strtolower( $domain ) === strtolower( fs_auth_email_domain() );
}

/**
 * همگام‌سازی اطلاعات صورتحساب کاربر.
 *
 * ایمیل و شماره باید همیشه روی پروفایل باشند، وگرنه ووکامرس بعد از خرید
 * دسترسی دانلود نمی‌دهد.
 *
 * @param int    $user_id شناسه کاربر.
 * @param string $phone   شماره.
 * @param string $first   نام.
 * @param string $last    نام خانوادگی.
 * @param string $email   ایمیل.
 * @return void
 */
function fs_sync_user_billing( $user_id, $phone = '', $first = '', $last = '', $email = '' ) {
	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return;
	}

	$phone = $phone ? $phone : get_user_meta( $user_id, FS_PHONE_META, true );
	$first = $first ? $first : $user->first_name;
	$last  = $last ? $last : $user->last_name;
	$email = $email ? $email : $user->user_email;

	if ( $phone ) {
		update_user_meta( $user_id, FS_PHONE_META, $phone );
	}

	update_user_meta( $user_id, 'billing_email', $email );
	update_user_meta( $user_id, 'billing_first_name', $first );
	update_user_meta( $user_id, 'billing_last_name', $last );
}

/**
 * ورود کاربر و ست‌کردن کوکی.
 *
 * @param WP_User $user کاربر.
 * @return void
 */
function fs_login_user( $user ) {
	// تابع خود ووکامرس علاوه بر کوکی ورود، کوکی نشست ووکامرس را هم از حالت
	// مهمان به حساب کاربر منتقل می‌کند. چون ورود اینجا اجاکسی است و init با
	// شناسه‌ی مهمان اجرا شده، بدون این انتقال سبد زیر کلید مهمان جا می‌ماند.
	if ( function_exists( 'wc_set_customer_auth_cookie' ) ) {
		wc_set_customer_auth_cookie( $user->ID );
	} else {
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
	}

	do_action( 'wp_login', $user->user_login, $user );

	fs_sync_user_billing( $user->ID );
}

/**
 * نشانی بازگشت پس از ورود.
 *
 * @param string $requested نشانی درخواستی.
 * @return string
 */
function fs_auth_redirect( $requested = '' ) {
	$requested = trim( (string) $requested );

	/*
	 * بررسی با strpos امن نبود.
	 *
	 * شرط قبلی فقط می‌دید که نشانی با home_url() «شروع» می‌شود؛ یعنی
	 * https://luxu.ir.evil.com از تست رد می‌شد و کاربر بعد از ورود به دامنه‌ی
	 * مهاجم می‌رفت — یک Open Redirect تمام‌عیار روی مسیر ورود و ثبت‌نام.
	 *
	 * wp_validate_redirect خودِ هاست را می‌سنجد، نه پیشوند رشته را، و اگر
	 * بیرونی باشد رشته‌ی خالی برمی‌گرداند.
	 */
	if ( $requested ) {
		$safe = wp_validate_redirect( $requested, '' );

		if ( $safe ) {
			return $safe;
		}
	}

	if ( fs_has_woo() && WC()->cart && ! WC()->cart->is_empty() ) {
		return wc_get_checkout_url();
	}

	return fs_account_url();
}

/* -------------------------------------------------------------------------
   نقاط اجاکس
   ------------------------------------------------------------------------- */

/**
 * بررسی nonce درخواست‌های ورود.
 *
 * @return void
 */
function fs_auth_check_nonce() {
	if ( ! check_ajax_referer( 'fs_auth', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'درخواست معتبر نیست. صفحه را تازه کنید.' ), 400 );
	}
}

/**
 * توکن کپچا از درخواست جاری.
 *
 * هر سرویس نام فیلد خودش را دارد؛ هر دو را می‌پذیریم تا تغییر سرویس در
 * تنظیمات، نیازی به تغییر جاوااسکریپت نداشته باشد.
 *
 * @return string
 */
function fs_captcha_token() {
	foreach ( array( 'captcha_token', 'g-recaptcha-response', 'h-captcha-response' ) as $field ) {
		if ( ! empty( $_POST[ $field ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return sanitize_text_field( wp_unslash( $_POST[ $field ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	return '';
}

/**
 * گام اول: تشخیص اینکه شماره ثبت شده یا نه.
 *
 * @return void
 */
function fs_ajax_auth_entry() {
	fs_auth_check_nonce();

	$login    = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';
	$resolved = fs_resolve_login( $login );

	if ( ! $resolved['phone'] && ! $resolved['user'] ) {
		wp_send_json_error( array( 'message' => 'شماره موبایل، ایمیل یا نام کاربری درست نیست.' ) );
	}

	// ورود با ایمیل یا نام کاربری فقط با رمز عبور ممکن است.
	if ( ! $resolved['phone'] ) {
		wp_send_json_success(
			array(
				'step'  => 'password',
				'login' => $login,
			)
		);
	}

	// حساب‌های مدیریتی از فرم پیامکی وارد نمی‌شوند؛ به رمز عبور هدایت می‌شوند.
	// پیام عمداً نمی‌گوید «این شماره مدیر است» تا از همین فرم نشود فهمید کدام
	// شماره حساب مدیریتی دارد.
	if ( $resolved['user'] && ! fs_user_may_otp( $resolved['user'] ) ) {
		wp_send_json_success(
			array(
				'step'   => 'password',
				'login'  => $login,
				'notice' => 'برای این حساب، ورود فقط با رمز عبور ممکن است.',
			)
		);
	}

	$sent = fs_otp_send( $resolved['phone'], fs_captcha_token() );

	if ( is_wp_error( $sent ) ) {
		wp_send_json_error( array( 'message' => $sent->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'step'      => 'otp',
			'phone'     => $resolved['phone'],
			'isNew'     => ! $resolved['user'],
			'resendIn'  => FS_OTP_RESEND,
			'expiresIn' => FS_OTP_TTL,
		)
	);
}
add_action( 'wp_ajax_nopriv_fs_auth_entry', 'fs_ajax_auth_entry' );
add_action( 'wp_ajax_fs_auth_entry', 'fs_ajax_auth_entry' );

/**
 * ارسال دوباره کد.
 *
 * @return void
 */
function fs_ajax_auth_resend() {
	fs_auth_check_nonce();

	$phone = fs_normalize_phone( isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( ! $phone ) {
		wp_send_json_error( array( 'message' => 'شماره موبایل درست نیست.' ) );
	}

	$sent = fs_otp_send( $phone, fs_captcha_token() );

	if ( is_wp_error( $sent ) ) {
		wp_send_json_error( array( 'message' => $sent->get_error_message() ) );
	}

	wp_send_json_success( array( 'resendIn' => FS_OTP_RESEND ) );
}
add_action( 'wp_ajax_nopriv_fs_auth_resend', 'fs_ajax_auth_resend' );
add_action( 'wp_ajax_fs_auth_resend', 'fs_ajax_auth_resend' );

/**
 * گام دوم: بررسی کد پیامکی.
 *
 * اگر شماره تازه باشد، گام نام و نام خانوادگی برگردانده می‌شود.
 *
 * @return void
 */
function fs_ajax_auth_otp() {
	fs_auth_check_nonce();

	$phone = fs_normalize_phone( isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$code  = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

	if ( ! $phone ) {
		wp_send_json_error( array( 'message' => 'شماره موبایل درست نیست.' ) );
	}

	$ok = fs_otp_verify( $phone, $code );

	if ( is_wp_error( $ok ) ) {
		wp_send_json_error( array( 'message' => $ok->get_error_message() ) );
	}

	$user = fs_find_user_by_phone( $phone );

	if ( $user ) {
		fs_login_user( $user );

		wp_send_json_success(
			array(
				'step'     => 'done',
				'redirect' => fs_auth_redirect( isset( $_POST['redirect'] ) ? wp_unslash( $_POST['redirect'] ) : '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			)
		);
	}

	// شماره تازه: با یک ژتون کوتاه‌مدت به گام نام و نام خانوادگی می‌رویم.
	$ticket = wp_generate_password( 20, false );
	set_transient( 'fs_reg_' . $ticket, $phone, 15 * MINUTE_IN_SECONDS );

	wp_send_json_success(
		array(
			'step'   => 'profile',
			'ticket' => $ticket,
			'phone'  => $phone,
		)
	);
}
add_action( 'wp_ajax_nopriv_fs_auth_otp', 'fs_ajax_auth_otp' );
add_action( 'wp_ajax_fs_auth_otp', 'fs_ajax_auth_otp' );

/**
 * گام سوم: نام و نام خانوادگی (و ایمیل اختیاری) و ساخت حساب.
 *
 * @return void
 */
function fs_ajax_auth_profile() {
	fs_auth_check_nonce();

	$ticket = isset( $_POST['ticket'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket'] ) ) : '';
	$phone  = $ticket ? get_transient( 'fs_reg_' . $ticket ) : '';

	if ( ! $phone ) {
		wp_send_json_error( array( 'message' => 'مهلت ثبت‌نام تمام شد. دوباره شروع کنید.' ) );
	}

	$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
	$last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';

	if ( ! $first ) {
		wp_send_json_error( array( 'message' => 'نام را وارد کنید.' ) );
	}

	$user = fs_create_user(
		$phone,
		array(
			'first_name' => $first,
			'last_name'  => $last,
			'email'      => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
		)
	);

	if ( is_wp_error( $user ) ) {
		wp_send_json_error( array( 'message' => $user->get_error_message() ) );
	}

	delete_transient( 'fs_reg_' . $ticket );
	fs_login_user( $user );

	wp_send_json_success(
		array(
			'step'     => 'done',
			'redirect' => fs_auth_redirect( isset( $_POST['redirect'] ) ? wp_unslash( $_POST['redirect'] ) : '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		)
	);
}
add_action( 'wp_ajax_nopriv_fs_auth_profile', 'fs_ajax_auth_profile' );
add_action( 'wp_ajax_fs_auth_profile', 'fs_ajax_auth_profile' );

/**
 * ورود با رمز عبور.
 *
 * @return void
 */
function fs_ajax_auth_password() {
	fs_auth_check_nonce();

	$login    = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';
	$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- رمز نباید تغییر کند.

	/*
	 * قفل تلاش ناموفق.
	 *
	 * این نقطه nopriv است و nonce دارد، ولی nonce جلوی حمله را نمی‌گیرد —
	 * مهاجم یک بار صفحه را می‌گیرد و با همان nonce هزاران رمز را امتحان
	 * می‌کند. سقف هم روی آی‌پی است و هم روی خودِ نام کاربری، تا نه یک آی‌پی
	 * بتواند چند حساب را بکوبد و نه چند آی‌پی یک حساب را.
	 */
	$ip  = fs_client_ip();
	$ban = fs_guard_banned();

	// کسی که با حدس‌زدن کد پیامکی بن شده، نباید بتواند فقط با عوض‌کردن فرم
	// سراغ حدس‌زدن رمز برود.
	if ( $ban ) {
		wp_send_json_error(
			array(
				'message' => sprintf( 'به دلیل تلاش‌های ناموفق، تا %s دقیقه امکان ورود نیست.', fs_fa_num( (int) ceil( $ban / 60 ) ) ),
			),
			429
		);
	}

	foreach ( array( array( 'login_ip', $ip ), array( 'login_user', strtolower( $login ) ) ) as $gate ) {
		$blocked = fs_rate_limit_hit( $gate[0], $gate[1], FS_LOGIN_MAX, FS_LOGIN_WINDOW );

		if ( $blocked ) {
			wp_send_json_error(
				array(
					'message' => sprintf( 'تلاش بیش از حد. %s دقیقه دیگر تلاش کنید.', fs_fa_num( (int) ceil( $blocked / 60 ) ) ),
				),
				429
			);
		}
	}

	$resolved = fs_resolve_login( $login );

	if ( ! $resolved['user'] ) {
		wp_send_json_error( array( 'message' => 'نام کاربری یا رمز عبور درست نیست.' ) );
	}

	$user = wp_authenticate( $resolved['user']->user_login, $password );

	if ( is_wp_error( $user ) ) {
		wp_send_json_error( array( 'message' => 'نام کاربری یا رمز عبور درست نیست.' ) );
	}

	// ورود موفق، شمارنده‌ها آزاد.
	fs_rate_limit_clear( 'login_ip', $ip );
	fs_rate_limit_clear( 'login_user', strtolower( $login ) );

	fs_login_user( $user );

	wp_send_json_success(
		array(
			'step'     => 'done',
			'redirect' => fs_auth_redirect( isset( $_POST['redirect'] ) ? wp_unslash( $_POST['redirect'] ) : '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		)
	);
}
add_action( 'wp_ajax_nopriv_fs_auth_password', 'fs_ajax_auth_password' );
add_action( 'wp_ajax_fs_auth_password', 'fs_ajax_auth_password' );

/**
 * ثبت‌نام با رمز عبور (بدون پیامک).
 *
 * @return void
 */
function fs_ajax_auth_register() {
	fs_auth_check_nonce();

	$phone = fs_normalize_phone( isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( ! $phone ) {
		wp_send_json_error( array( 'message' => 'شماره موبایل درست نیست.' ) );
	}

	if ( fs_find_user_by_phone( $phone ) ) {
		wp_send_json_error( array( 'message' => 'این شماره قبلاً ثبت شده است. وارد شوید.' ) );
	}

	$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( strlen( $password ) < 8 ) {
		wp_send_json_error( array( 'message' => 'رمز عبور باید دست‌کم ۸ نویسه باشد.' ) );
	}

	$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';

	if ( ! $first ) {
		wp_send_json_error( array( 'message' => 'نام را وارد کنید.' ) );
	}

	$user = fs_create_user(
		$phone,
		array(
			'first_name' => $first,
			'last_name'  => isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '',
			'email'      => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'password'   => $password,
		)
	);

	if ( is_wp_error( $user ) ) {
		wp_send_json_error( array( 'message' => $user->get_error_message() ) );
	}

	fs_login_user( $user );

	wp_send_json_success(
		array(
			'step'     => 'done',
			'redirect' => fs_auth_redirect( isset( $_POST['redirect'] ) ? wp_unslash( $_POST['redirect'] ) : '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		)
	);
}
add_action( 'wp_ajax_nopriv_fs_auth_register', 'fs_ajax_auth_register' );
add_action( 'wp_ajax_fs_auth_register', 'fs_ajax_auth_register' );

/**
 * هر بار که پروفایل کاربر ذخیره می‌شود، اطلاعات صورتحساب هم هم‌گام شود.
 *
 * @param int $user_id شناسه کاربر.
 * @return void
 */
function fs_sync_on_profile_save( $user_id ) {
	fs_sync_user_billing( $user_id );
}
add_action( 'profile_update', 'fs_sync_on_profile_save' );
add_action( 'woocommerce_save_account_details', 'fs_sync_on_profile_save' );
