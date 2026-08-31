<?php
/**
 * پنل پیامک — sms.ir
 *
 * سیستم ورود قالب کد یکبارمصرف را می‌سازد و آن را روی قلاب `fs_send_sms`
 * تحویل می‌دهد؛ اینجا آن قلاب به وب‌سرویس sms.ir وصل می‌شود.
 *
 * از سرویس «ارسال وریفای» (الگو) استفاده می‌شود، نه ارسال متن آزاد: در ایران
 * پیامک تبلیغاتی شبانه محدودیت دارد و به خطوط خاموش نمی‌رسد، ولی پیامک الگو
 * همیشه و بدون محدودیت ساعت ارسال می‌شود. الگو را باید یک بار در پنل sms.ir
 * بسازید و شناسه‌اش را اینجا وارد کنید.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

const FS_SMS_OPTION = 'fs_sms';

/**
 * مقادیر پیش‌فرض.
 *
 * @return array<string, string|bool>
 */
function fs_sms_defaults() {
	return array(
		'enabled'     => false,
		'api_key'     => '',
		'template_id' => '',
		'param_name'  => 'CODE',
		'code_length' => 5,
		'line_number' => '',
		'webotp'      => false,
	);
}

/**
 * تنظیمات ذخیره‌شده.
 *
 * @return array<string, string|bool>
 */
function fs_get_sms_settings() {
	$saved = get_option( FS_SMS_OPTION, array() );

	return wp_parse_args( is_array( $saved ) ? $saved : array(), fs_sms_defaults() );
}

/**
 * آیا سرویس پیامک آماده‌ی کار است؟
 *
 * @return bool
 */
function fs_sms_ready() {
	$settings = fs_get_sms_settings();

	return ! empty( $settings['enabled'] )
		&& '' !== trim( (string) $settings['api_key'] )
		&& '' !== trim( (string) $settings['template_id'] );
}

/**
 * ریشه‌ی وب‌سرویس sms.ir.
 *
 * نسخه‌ی فعلی v1 است و با هدر X-API-KEY کار می‌کند (نسخه‌ی قدیمی
 * RestfulSms.com اول توکن می‌گرفت؛ آن مسیر اینجا استفاده نمی‌شود).
 */
const FS_SMS_API = 'https://api.sms.ir/v1';

/* -------------------------------------------------------------------------
   ارتباط با وب‌سرویس
   ------------------------------------------------------------------------- */

/**
 * یک درخواست به sms.ir.
 *
 * همه‌ی سرویس‌ها یک پوسته‌ی مشترک دارند: `{status, message, data}` که در آن
 * `status = 1` یعنی موفق. پس تفسیر پاسخ یک بار اینجا انجام می‌شود و بقیه‌ی
 * توابع فقط `data` را می‌گیرند.
 *
 * @param string     $path   مسیر بعد از /v1 (مثلاً send/verify).
 * @param string     $method GET یا POST.
 * @param array|null $body   بدنه‌ی JSON برای POST.
 * @return mixed|WP_Error همان بخش data در پاسخ موفق.
 */
function fs_smsir_request( $path, $method = 'GET', $body = null ) {
	$settings = fs_get_sms_settings();
	$key      = trim( (string) $settings['api_key'] );

	if ( '' === $key ) {
		return new WP_Error( 'fs_sms_nokey', 'کلید API وارد نشده است.' );
	}

	$args = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'X-API-KEY'    => $key,
		),
	);

	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}

	$response = wp_remote_request( FS_SMS_API . '/' . ltrim( $path, '/' ), $args );

	if ( is_wp_error( $response ) ) {
		fs_sms_log( sprintf( '%s — خطای شبکه: %s', $path, $response->get_error_message() ) );

		return new WP_Error( 'fs_sms_network', 'ارتباط با سرویس پیامک برقرار نشد.' );
	}

	$code   = (int) wp_remote_retrieve_response_code( $response );
	$parsed = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	$status = is_array( $parsed ) && isset( $parsed['status'] ) ? (int) $parsed['status'] : 0;

	if ( 1 === $status ) {
		return isset( $parsed['data'] ) ? $parsed['data'] : true;
	}

	$detail = is_array( $parsed ) && ! empty( $parsed['message'] )
		? (string) $parsed['message']
		: wp_remote_retrieve_response_message( $response );

	fs_sms_log( sprintf( '%s — HTTP %d / status %d: %s', $path, $code, $status, $detail ) );

	return new WP_Error( 'fs_sms_failed', fs_smsir_reason( $code, $status ), array( 'detail' => $detail ) );
}

/**
 * ترجمه‌ی کد خطای sms.ir به یک جمله‌ی قابل‌فهم برای مدیر.
 *
 * متن خام سرویس گاهی انگلیسی و گاهی مبهم است؛ این جدول رایج‌ترین‌ها را به
 * کاری که باید انجام شود ترجمه می‌کند. پیام‌ها برای مدیر است، نه کاربر —
 * چیزی که به کاربر نشان داده می‌شود همیشه یک جمله‌ی خنثی است.
 *
 * @param int $http   کد وضعیت HTTP.
 * @param int $status فیلد status در بدنه.
 * @return string
 */
function fs_smsir_reason( $http, $status ) {
	$by_http = array(
		400 => 'درخواست نامعتبر بود — شناسه‌ی الگو یا نام پارامتر را بررسی کنید.',
		401 => 'کلید API پذیرفته نشد. کلید تازه از پنل sms.ir بگیرید و دوباره وارد کنید.',
		403 => 'دسترسی رد شد. احتمالاً آی‌پی سرور در پنل sms.ir مجاز نشده است.',
		404 => 'الگو یا سرویس پیدا نشد. شناسه‌ی الگو را بررسی کنید.',
		409 => 'اعتبار حساب کافی نیست. حساب sms.ir را شارژ کنید.',
		422 => 'مقدارهای فرستاده‌شده پذیرفته نشد — شماره یا پارامترهای الگو را بررسی کنید.',
		429 => 'تعداد درخواست‌ها از سقف سرویس بیشتر شد. کمی بعد دوباره تلاش کنید.',
		500 => 'خطای داخلی سرویس پیامک. کمی بعد دوباره تلاش کنید.',
	);

	if ( isset( $by_http[ $http ] ) ) {
		return $by_http[ $http ];
	}

	// status صفر یعنی بدنه اصلاً JSON استاندارد نبود.
	if ( 0 === $status ) {
		return 'پاسخ سرویس پیامک قابل خواندن نبود.';
	}

	return sprintf( 'سرویس پیامک درخواست را نپذیرفت (کد %d).', $status );
}

/**
 * اعتبار باقی‌مانده‌ی حساب.
 *
 * @return float|WP_Error
 */
function fs_smsir_credit() {
	$data = fs_smsir_request( 'credit' );

	return is_wp_error( $data ) ? $data : (float) $data;
}

/**
 * خطوط ارسال حساب.
 *
 * @return array|WP_Error
 */
function fs_smsir_lines() {
	$data = fs_smsir_request( 'line' );

	return is_wp_error( $data ) ? $data : (array) $data;
}

/* -------------------------------------------------------------------------
   ارسال
   ------------------------------------------------------------------------- */

/**
 * ارسال کد با sms.ir.
 *
 * روی قلاب `fs_send_sms` می‌نشیند. اگر پنل تنظیم نشده باشد `null` برمی‌گرداند
 * تا قالب همان رفتار قبلی را داشته باشد (در حالت اشکال‌زدایی کد را لاگ کند).
 *
 * @param mixed  $sent    نتیجه‌ی سرویس‌های قبلی روی همین قلاب.
 * @param string $phone   شماره‌ی گیرنده (۰۹xxxxxxxxx).
 * @param string $message متن آماده — در حالت الگو استفاده نمی‌شود.
 * @param string $code    خود کد.
 * @return true|WP_Error|null
 */
function fs_smsir_send( $sent, $phone, $message, $code ) {
	unset( $message );

	// اگر سرویس دیگری قبلاً پیامک را فرستاده، دوباره نفرست.
	if ( null !== $sent ) {
		return $sent;
	}

	if ( ! fs_sms_ready() ) {
		return null;
	}

	$settings = fs_get_sms_settings();

	$data = fs_smsir_request(
		'send/verify',
		'POST',
		array(
			'mobile'     => $phone,
			'templateId' => (int) $settings['template_id'],
			'parameters' => fs_smsir_parameters( $code ),
		)
	);

	if ( is_wp_error( $data ) ) {
		// متن خطا برای مدیر در تنظیمات ثبت شده؛ به کاربر جمله‌ی خنثی می‌دهیم
		// تا وضعیت حساب پیامکی سایت از فرم ورود قابل استنتاج نباشد.
		return new WP_Error( 'fs_sms_failed', 'ارسال پیامک انجام نشد. کمی بعد دوباره تلاش کنید.' );
	}

	fs_sms_note_ok( is_array( $data ) ? $data : array() );

	return true;
}

/**
 * پارامترهای الگو.
 *
 * الگوی پنل معمولاً یک پارامتر دارد (خود کد). اگر WebOTP روشن باشد، یک
 * پارامتر دوم هم فرستاده می‌شود که دامنه‌ی سایت است؛ مرورگر برای پرکردن
 * خودکار کد، به آن خط انتهایی نیاز دارد.
 *
 * @param string $code کد.
 * @return array<int, array{name:string, value:string}>
 */
function fs_smsir_parameters( $code ) {
	$settings = fs_get_sms_settings();

	$parameters = array(
		array(
			'name'  => (string) $settings['param_name'],
			'value' => (string) $code,
		),
	);

	if ( ! empty( $settings['webotp'] ) ) {
		$parameters[] = array(
			'name'  => 'DOMAIN',
			'value' => fs_webotp_domain(),
		);
	}

	return apply_filters( 'fs_smsir_parameters', $parameters, $code );
}

/**
 * دامنه‌ای که مرورگر برای WebOTP با آن تطبیق می‌دهد.
 *
 * باید دقیقاً همان میزبانی باشد که فرم ورود روی آن باز است، بدون پروتکل و
 * بدون مسیر؛ وگرنه مرورگر پیشنهاد پرکردن خودکار را نشان نمی‌دهد.
 *
 * @return string
 */
function fs_webotp_domain() {
	return (string) wp_parse_url( home_url(), PHP_URL_HOST );
}

/**
 * ثبت آخرین ارسال موفق.
 *
 * @param array $data بخش data در پاسخ.
 * @return void
 */
function fs_sms_note_ok( $data ) {
	$settings = fs_get_sms_settings();

	$settings['last_ok'] = sprintf(
		'[%s] شناسه پیام: %s — هزینه: %s',
		wp_date( 'Y/m/d H:i' ),
		isset( $data['messageId'] ) ? $data['messageId'] : '—',
		isset( $data['cost'] ) ? $data['cost'] : '—'
	);

	update_option( FS_SMS_OPTION, $settings, false );
}
add_filter( 'fs_send_sms', 'fs_smsir_send', 10, 4 );

/**
 * ثبت آخرین خطای سرویس پیامک.
 *
 * متن خطای سرویس نباید به کاربر نشان داده شود (ممکن است جزئیات حساب را لو
 * دهد)، ولی مدیر برای عیب‌یابی به آن نیاز دارد؛ پس در تنظیمات نگه داشته
 * می‌شود و در همان تب دیده می‌شود.
 *
 * @param string $message متن.
 * @return void
 */
function fs_sms_log( $message ) {
	$settings              = fs_get_sms_settings();
	$settings['last_error'] = sprintf( '[%s] %s', wp_date( 'Y/m/d H:i' ), $message );

	update_option( FS_SMS_OPTION, $settings, false );
}

/* -------------------------------------------------------------------------
   تنظیمات در پیشخوان
   ------------------------------------------------------------------------- */

/**
 * ذخیره‌ی تنظیمات پیامک.
 *
 * @return void
 */
function fs_sms_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}

	check_admin_referer( 'fs_sms_save' );

	$settings = fs_get_sms_settings();

	$settings['enabled']     = ! empty( $_POST['enabled'] );
	$settings['webotp']      = ! empty( $_POST['webotp'] );
	$settings['template_id'] = isset( $_POST['template_id'] ) ? preg_replace( '/\D+/', '', wp_unslash( $_POST['template_id'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$settings['param_name']  = isset( $_POST['param_name'] ) ? sanitize_text_field( wp_unslash( $_POST['param_name'] ) ) : 'CODE';
	$settings['line_number'] = isset( $_POST['line_number'] ) ? preg_replace( '/\D+/', '', wp_unslash( $_POST['line_number'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	// طول کد باید با تعداد رقم‌های الگو یکی باشد؛ خارج از این بازه پذیرفته
	// نمی‌شود تا کدی ساخته نشود که نه امن است نه در الگو جا می‌شود.
	$length                  = isset( $_POST['code_length'] ) ? (int) $_POST['code_length'] : 5;
	$settings['code_length'] = min( 8, max( 4, $length ) );

	// کلید فقط وقتی عوض می‌شود که چیزی تایپ شده باشد؛ کادر خالی یعنی «دست نزن».
	$key = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';

	if ( '' !== $key ) {
		$settings['api_key'] = $key;
	}

	if ( ! empty( $_POST['clear_key'] ) ) {
		$settings['api_key'] = '';
	}

	if ( '' === $settings['param_name'] ) {
		$settings['param_name'] = 'CODE';
	}

	update_option( FS_SMS_OPTION, $settings, false );

	wp_safe_redirect( add_query_arg( 'fs_sms', 'saved', admin_url( 'admin.php?page=fs-theme-settings&tab=sms' ) ) );
	exit;
}
add_action( 'admin_post_fs_sms_save', 'fs_sms_save' );

/**
 * ارسال پیامک آزمایشی.
 *
 * @return void
 */
function fs_sms_test() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}

	check_admin_referer( 'fs_sms_test' );

	$phone  = isset( $_POST['test_phone'] ) ? fs_normalize_phone( wp_unslash( $_POST['test_phone'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$result = 'badphone';

	if ( $phone ) {
		// عمداً از خود مسیر واقعی رد می‌شویم تا همان چیزی تست شود که کاربر
		// هنگام ورود تجربه می‌کند، نه یک مسیر شبیه‌سازی‌شده.
		$sent = fs_smsir_send( null, $phone, '', (string) wp_rand( 10000, 99999 ) );

		if ( true === $sent ) {
			$result = 'sent';
		} elseif ( is_wp_error( $sent ) ) {
			$result = 'failed';
		} else {
			$result = 'off';
		}
	}

	wp_safe_redirect( add_query_arg( 'fs_sms', $result, admin_url( 'admin.php?page=fs-theme-settings&tab=sms' ) ) );
	exit;
}
add_action( 'admin_post_fs_sms_test', 'fs_sms_test' );

/**
 * تست اتصال بدون خرج‌کردن پیامک.
 *
 * سرویس «اعتبار» برای این کار انتخاب شده چون هم کلید را می‌سنجد، هم شارژ
 * حساب را نشان می‌دهد، و هیچ پیامکی نمی‌فرستد. برای بررسی اولیه‌ی تنظیمات
 * همیشه باید اول این را زد، نه ارسال آزمایشی را.
 *
 * @return void
 */
function fs_sms_ping() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}

	check_admin_referer( 'fs_sms_ping' );

	$credit = fs_smsir_credit();
	$result = is_wp_error( $credit ) ? 'pingfail' : 'pingok';

	if ( ! is_wp_error( $credit ) ) {
		$settings           = fs_get_sms_settings();
		$settings['credit'] = $credit;
		$lines              = fs_smsir_lines();
		$settings['lines']  = is_wp_error( $lines ) ? array() : $lines;

		update_option( FS_SMS_OPTION, $settings, false );
	}

	wp_safe_redirect( add_query_arg( 'fs_sms', $result, admin_url( 'admin.php?page=fs-theme-settings&tab=sms' ) ) );
	exit;
}
add_action( 'admin_post_fs_sms_ping', 'fs_sms_ping' );

/**
 * محتوای تب «پیامک».
 *
 * @return void
 */
function fs_sms_tab_content() {
	$settings = fs_get_sms_settings();
	$notice   = isset( $_GET['fs_sms'] ) ? sanitize_key( wp_unslash( $_GET['fs_sms'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$messages = array(
		'saved'    => array( 'updated', 'تنظیمات ذخیره شد.' ),
		'sent'     => array( 'updated', 'پیامک آزمایشی فرستاده شد. اگر نرسید، شناسه‌ی الگو و نام پارامتر را بررسی کنید.' ),
		'failed'   => array( 'error', 'ارسال انجام نشد. متن خطای سرویس پایین همین صفحه است.' ),
		'off'      => array( 'error', 'پنل پیامک هنوز کامل تنظیم نشده است.' ),
		'badphone' => array( 'error', 'شماره موبایل درست نیست.' ),
		'pingok'   => array( 'updated', 'اتصال به sms.ir برقرار است.' ),
		'pingfail' => array( 'error', 'اتصال برقرار نشد. متن خطای سرویس پایین همین صفحه است.' ),
	);

	if ( isset( $messages[ $notice ] ) ) {
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $notice ][0] ),
			esc_html( $messages[ $notice ][1] )
		);
	}
	?>

	<h2 style="margin-top:24px">اتصال به sms.ir</h2>
	<p class="description" style="max-width:700px">
		کد ورود از سرویس <strong>«ارسال وریفای»</strong> فرستاده می‌شود، نه پیامک متن‌آزاد.
		دلیلش این است که پیامک تبلیغاتی شبانه محدودیت دارد و به شماره‌های ثبت‌شده در سامانه‌ی
		عدم دریافت تبلیغات اصلاً نمی‌رسد؛ پیامک الگو این محدودیت‌ها را ندارد.
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'fs_sms_save' ); ?>
		<input type="hidden" name="action" value="fs_sms_save">

		<table class="form-table" role="presentation">

			<tr>
				<th scope="row">وضعیت</th>
				<td>
					<label>
						<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
						ارسال کد ورود با پیامک فعال باشد
					</label>
					<p class="description">
						تا وقتی این تیک نخورده، کد ورود ارسال نمی‌شود و کاربران نمی‌توانند با پیامک وارد شوند.
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="fs-sms-key">کلید API</label></th>
				<td>
					<input class="regular-text ltr" id="fs-sms-key" type="password" name="api_key" dir="ltr"
						autocomplete="new-password"
						placeholder="<?php echo $settings['api_key'] ? 'ذخیره شده — برای تغییر، کلید تازه را وارد کنید' : 'کلید را از پنل sms.ir بگیرید'; ?>">

					<?php if ( $settings['api_key'] ) : ?>
						<p class="description">
							کلید ذخیره شده است و نمایش داده نمی‌شود.
							<label style="margin-inline-start:8px">
								<input type="checkbox" name="clear_key" value="1"> پاک کردن کلید
							</label>
						</p>
					<?php else : ?>
						<p class="description">پنل sms.ir ← توسعه‌دهندگان ← کلید API</p>
					<?php endif; ?>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="fs-sms-template">شناسه الگو</label></th>
				<td>
					<input class="regular-text ltr" id="fs-sms-template" type="text" name="template_id" dir="ltr"
						inputmode="numeric" value="<?php echo esc_attr( $settings['template_id'] ); ?>">
					<p class="description">
						عدد الگویی که در پنل sms.ir ساخته‌اید (پنل ← ارسال وریفای ← الگوها).
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="fs-sms-param">نام پارامتر</label></th>
				<td>
					<input class="regular-text ltr" id="fs-sms-param" type="text" name="param_name" dir="ltr"
						value="<?php echo esc_attr( $settings['param_name'] ); ?>">
					<p class="description">
						همان نامی که داخل متن الگو نوشته‌اید. اگر الگو را این‌طور ساخته‌اید:
						<code>کد ورود شما: #CODE#</code> پس نام پارامتر <code>CODE</code> است.
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="fs-sms-len">تعداد رقم کد</label></th>
				<td>
					<select id="fs-sms-len" name="code_length">
						<?php foreach ( range( 4, 8 ) as $fs_n ) : ?>
							<option value="<?php echo esc_attr( $fs_n ); ?>" <?php selected( (int) $settings['code_length'], $fs_n ); ?>>
								<?php echo esc_html( fs_fa_num( $fs_n ) ); ?> رقم
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						باید با تعداد خانه‌های فرم ورود یکی باشد — همین تنظیم هر دو را با هم عوض می‌کند.
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="fs-sms-line">شماره خط</label></th>
				<td>
					<input class="regular-text ltr" id="fs-sms-line" type="text" name="line_number" dir="ltr"
						inputmode="numeric" value="<?php echo esc_attr( $settings['line_number'] ); ?>">
					<p class="description">
						اختیاری. سرویس «ارسال وریفای» خط را از خود الگو برمی‌دارد؛ این فیلد فقط برای
						یادداشت و استفاده‌ی افزونه‌های دیگر است.
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">پرکردن خودکار کد</th>
				<td>
					<label>
						<input type="checkbox" name="webotp" value="1" <?php checked( ! empty( $settings['webotp'] ) ); ?>>
						دامنه‌ی سایت را هم به الگو بفرست (لازم برای WebOTP)
					</label>
					<p class="description" style="max-width:700px">
						مرورگر فقط وقتی کد را خودکار پر می‌کند که متن پیامک با یک خط جدا و به این شکل تمام شود:
						<code dir="ltr" style="display:inline-block">@<?php echo esc_html( fs_webotp_domain() ); ?> #<?php echo esc_html( $settings['param_name'] ); ?>#</code>
						<br>
						پس در الگوی پنل sms.ir یک پارامتر دوم به نام <code>DOMAIN</code> اضافه کنید و آخر متن
						الگو بنویسید: <code dir="ltr" style="display:inline-block">@#DOMAIN# #<?php echo esc_html( $settings['param_name'] ); ?>#</code>
						<br>
						این قابلیت روی کروم اندروید کار می‌کند. روی بقیه، فرم مثل قبل دستی پر می‌شود.
					</p>
				</td>
			</tr>

		</table>

		<?php submit_button( 'ذخیره تنظیمات' ); ?>
	</form>

	<hr>

	<h2>بررسی اتصال</h2>
	<p class="description" style="max-width:700px">
		اول این را بزنید. اعتبار حساب را می‌خواند، پس کلید API را می‌سنجد
		<strong>بدون اینکه پیامکی بفرستد یا هزینه‌ای بشود</strong>.
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:10px">
		<?php wp_nonce_field( 'fs_sms_ping' ); ?>
		<input type="hidden" name="action" value="fs_sms_ping">
		<?php submit_button( 'بررسی اتصال و اعتبار', 'secondary', 'submit', false ); ?>
	</form>

	<?php if ( isset( $settings['credit'] ) ) : ?>
		<table class="widefat striped" style="max-width:520px;margin-bottom:20px">
			<tbody>
				<tr>
					<th style="width:150px">اعتبار باقی‌مانده</th>
					<td><?php echo esc_html( fs_fa_num( number_format( (float) $settings['credit'] ) ) ); ?> ریال</td>
				</tr>
				<tr>
					<th>خطوط حساب</th>
					<td dir="ltr" style="text-align:left">
						<?php echo $settings['lines'] ? esc_html( implode( '، ', (array) $settings['lines'] ) ) : '—'; ?>
					</td>
				</tr>
			</tbody>
		</table>
	<?php endif; ?>

	<hr>

	<h2>ارسال آزمایشی</h2>
	<p class="description" style="max-width:700px">
		یک کد تصادفی به شماره‌ی زیر فرستاده می‌شود — از همان مسیری که کاربر واقعی
		استفاده می‌کند. <strong>این یکی هزینه دارد</strong> و الگو و نام پارامتر را هم می‌سنجد.
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'fs_sms_test' ); ?>
		<input type="hidden" name="action" value="fs_sms_test">

		<input class="regular-text ltr" type="text" name="test_phone" dir="ltr"
			placeholder="۰۹۱۲۱۲۳۴۵۶۷" inputmode="tel">

		<?php submit_button( 'ارسال پیامک آزمایشی', 'secondary', 'submit', false ); ?>
	</form>

	<?php if ( ! empty( $settings['last_ok'] ) ) : ?>
		<h2 style="margin-top:28px">آخرین ارسال موفق</h2>
		<p class="description" dir="ltr" style="text-align:left"><?php echo esc_html( $settings['last_ok'] ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $settings['last_error'] ) ) : ?>
		<h2 style="margin-top:28px">آخرین خطای سرویس</h2>
		<p class="description">این متن از خود sms.ir می‌آید و فقط برای عیب‌یابی است؛ به کاربران نشان داده نمی‌شود.</p>
		<pre style="background:#fff;border:1px solid #dcdcde;padding:12px;max-width:700px;white-space:pre-wrap;direction:ltr;text-align:left"><?php echo esc_html( $settings['last_error'] ); ?></pre>
	<?php endif; ?>
	<?php
}
