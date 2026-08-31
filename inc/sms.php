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
	$response = wp_remote_post(
		'https://api.sms.ir/v1/send/verify',
		array(
			'timeout' => 15,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'x-api-key'    => trim( (string) $settings['api_key'] ),
			),
			'body'    => wp_json_encode(
				array(
					'mobile'     => $phone,
					'templateId' => (int) $settings['template_id'],
					'parameters' => array(
						array(
							'name'  => (string) $settings['param_name'],
							'value' => (string) $code,
						),
					),
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		fs_sms_log( 'خطای شبکه: ' . $response->get_error_message() );

		return new WP_Error( 'fs_sms_network', 'ارتباط با سرویس پیامک برقرار نشد. دوباره تلاش کنید.' );
	}

	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	// sms.ir در پاسخ موفق status=1 می‌دهد؛ هر چیز دیگری خطاست.
	if ( is_array( $body ) && isset( $body['status'] ) && 1 === (int) $body['status'] ) {
		return true;
	}

	$detail = is_array( $body ) && ! empty( $body['message'] ) ? $body['message'] : wp_remote_retrieve_response_message( $response );

	fs_sms_log( sprintf( 'پاسخ ناموفق (کد %s): %s', wp_remote_retrieve_response_code( $response ), $detail ) );

	return new WP_Error( 'fs_sms_failed', 'ارسال پیامک انجام نشد. کمی بعد دوباره تلاش کنید.' );
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
	$settings['template_id'] = isset( $_POST['template_id'] ) ? preg_replace( '/\D+/', '', wp_unslash( $_POST['template_id'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$settings['param_name']  = isset( $_POST['param_name'] ) ? sanitize_text_field( wp_unslash( $_POST['param_name'] ) ) : 'CODE';

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
	);

	if ( isset( $messages[ $notice ] ) ) {
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $notice ][0] ),
			esc_html( $messages[ $notice ][1] )
		);
	}
	?>

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

		</table>

		<?php submit_button( 'ذخیره تنظیمات' ); ?>
	</form>

	<hr>

	<h2>ارسال آزمایشی</h2>
	<p class="description" style="max-width:640px">
		یک کد تصادفی به شماره‌ی زیر فرستاده می‌شود — از همان مسیری که کاربر واقعی
		استفاده می‌کند. اگر اینجا کار کند، ورود با پیامک هم کار می‌کند.
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'fs_sms_test' ); ?>
		<input type="hidden" name="action" value="fs_sms_test">

		<input class="regular-text ltr" type="text" name="test_phone" dir="ltr"
			placeholder="۰۹۱۲۱۲۳۴۵۶۷" inputmode="tel">

		<?php submit_button( 'ارسال پیامک آزمایشی', 'secondary', 'submit', false ); ?>
	</form>

	<?php if ( ! empty( $settings['last_error'] ) ) : ?>
		<h2 style="margin-top:28px">آخرین خطای سرویس</h2>
		<p class="description">این متن از خود sms.ir می‌آید و فقط برای عیب‌یابی است؛ به کاربران نشان داده نمی‌شود.</p>
		<pre style="background:#fff;border:1px solid #dcdcde;padding:12px;max-width:640px;white-space:pre-wrap;direction:ltr;text-align:left"><?php echo esc_html( $settings['last_error'] ); ?></pre>
	<?php endif; ?>
	<?php
}
