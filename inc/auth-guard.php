<?php
/**
 * لایه‌ی امنیتی ورود با پیامک.
 *
 * هر پیامک برای صاحب سایت پول است و هر کد اشتباه یک قدم به حدس‌زدن نزدیک‌تر.
 * این فایل پنج سد پشت سر هم می‌گذارد؛ هر کدام یک نوع سوءاستفاده را می‌گیرد:
 *
 * ۱. سقف تعداد در بازه (Rate Limiting) — هم روی آی‌پی، هم روی خود شماره.
 *    بدون سقف روی شماره، یک نفر می‌توانست با عوض‌کردن آی‌پی برای یک قربانی
 *    صدها پیامک بفرستد (SMS Bombing).
 * ۲. فاصله‌ی اجباری بین درخواست‌ها (Velocity) — جلوی رگبار سریع را می‌گیرد،
 *    حتی وقتی هنوز به سقف نرسیده است.
 * ۳. بن هوشمند — کسی که پشت سر هم کد اشتباه می‌زند، برای مدتی کنار گذاشته
 *    می‌شود؛ مدت بن با هر بار تکرار بیشتر می‌شود.
 * ۴. پنجره‌ی ساعتی ثبت‌نام — اگر مدیر بخواهد، ساخت حساب فقط در ساعت‌های
 *    مشخصی از شبانه‌روز ممکن است.
 * ۵. کپچا — reCAPTCHA v3 یا v2 یا hCaptcha.
 *
 * و یک قانون که سد نیست، مرز است: حساب‌های مدیریتی اصلاً از فرم پیامکی وارد
 * نمی‌شوند. اگر کسی شماره‌ی مدیر را داشته باشد، سیم‌کارتش برای گرفتن کنترل
 * سایت کافی نیست.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

const FS_GUARD_OPTION = 'fs_auth_guard';

/* -------------------------------------------------------------------------
   تنظیمات
   ------------------------------------------------------------------------- */

/**
 * مقادیر پیش‌فرض لایه‌ی امنیتی.
 *
 * مقادیر پیش‌فرض عمداً سخت‌گیر نیستند؛ یک کاربر عادی هیچ‌وقت به آن‌ها نمی‌خورد.
 *
 * @return array<string, mixed>
 */
function fs_guard_defaults() {
	return array(
		'ip_max'        => 5,   // پیامک از یک آی‌پی در بازه.
		'ip_window'     => 15,  // بازه (دقیقه).
		'phone_max'     => 4,   // پیامک به یک شماره در بازه.
		'phone_window'  => 60,  // بازه (دقیقه).
		'velocity'      => 20,  // کمینه فاصله بین دو درخواست از یک آی‌پی (ثانیه).
		'ban_after'     => 8,   // تعداد کد اشتباه تا بن.
		'ban_minutes'   => 30,  // مدت بن پایه (دقیقه).
		'hours_enabled' => false,
		'hours_from'    => 8,
		'hours_to'      => 23,
		'captcha'       => 'none', // none | recaptcha_v3 | recaptcha_v2 | hcaptcha
		'site_key'      => '',
		'secret_key'    => '',
		'score'         => 0.5,    // آستانه‌ی reCAPTCHA v3.
	);
}

/**
 * تنظیمات ذخیره‌شده‌ی لایه‌ی امنیتی.
 *
 * @return array<string, mixed>
 */
function fs_guard_settings() {
	$saved = get_option( FS_GUARD_OPTION, array() );

	return wp_parse_args( is_array( $saved ) ? $saved : array(), fs_guard_defaults() );
}

/**
 * یک مقدار از تنظیمات.
 *
 * @param string $key کلید.
 * @return mixed
 */
function fs_guard( $key ) {
	$settings = fs_guard_settings();

	return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
}

/* -------------------------------------------------------------------------
   بن هوشمند
   ------------------------------------------------------------------------- */

/**
 * کلید بن.
 *
 * بن روی ترکیب آی‌پی و شماره نیست، روی هرکدام جداگانه است: مهاجمی که شماره
 * عوض می‌کند با کلید آی‌پی گرفته می‌شود، و کسی که آی‌پی عوض می‌کند با کلید
 * شماره.
 *
 * @param string $kind  نوع (ip یا phone).
 * @param string $value مقدار.
 * @return string
 */
function fs_guard_ban_key( $kind, $value ) {
	return 'fs_ban_' . $kind . '_' . md5( (string) $value );
}

/**
 * آیا این آی‌پی یا شماره بن است؟
 *
 * @param string $phone شماره (اختیاری).
 * @return int ثانیه‌های باقی‌مانده؛ صفر یعنی آزاد.
 */
function fs_guard_banned( $phone = '' ) {
	$now  = time();
	$left = 0;

	$keys = array( fs_guard_ban_key( 'ip', fs_client_ip() ) );

	if ( $phone ) {
		$keys[] = fs_guard_ban_key( 'phone', $phone );
	}

	foreach ( $keys as $key ) {
		$ban = get_transient( $key );

		if ( is_array( $ban ) && ! empty( $ban['until'] ) && $ban['until'] > $now ) {
			$left = max( $left, (int) $ban['until'] - $now );
		}
	}

	return $left;
}

/**
 * ثبت یک کد اشتباه و بن‌کردن در صورت لزوم.
 *
 * مدت بن با هر دور تکرار دو برابر می‌شود (۳۰ دقیقه، ۱ ساعت، ۲ ساعت…) تا سقف
 * ۲۴ ساعت. کاربری که یک بار اشتباه تایپ کرده هیچ‌وقت به اینجا نمی‌رسد؛ کسی که
 * دارد کد حدس می‌زند خیلی زود می‌رسد.
 *
 * @param string $phone شماره.
 * @return void
 */
function fs_guard_note_failure( $phone ) {
	$limit = max( 1, (int) fs_guard( 'ban_after' ) );
	$base  = max( 1, (int) fs_guard( 'ban_minutes' ) ) * MINUTE_IN_SECONDS;

	foreach ( array( 'ip' => fs_client_ip(), 'phone' => $phone ) as $kind => $value ) {
		if ( '' === (string) $value ) {
			continue;
		}

		$key   = fs_guard_ban_key( $kind, $value );
		$state = get_transient( $key );
		$state = is_array( $state ) ? $state : array(
			'fails'  => 0,
			'rounds' => 0,
			'until'  => 0,
		);

		++$state['fails'];

		if ( $state['fails'] >= $limit ) {
			++$state['rounds'];
			$state['fails'] = 0;
			$state['until'] = time() + min( DAY_IN_SECONDS, $base * pow( 2, $state['rounds'] - 1 ) );
		}

		// رکورد را بیش از یک روز نگه نمی‌داریم تا شمارش برای همیشه نچسبد.
		set_transient( $key, $state, DAY_IN_SECONDS );
	}
}

/**
 * پاک‌کردن سابقه پس از ورود موفق.
 *
 * @param string $phone شماره.
 * @return void
 */
function fs_guard_clear( $phone ) {
	delete_transient( fs_guard_ban_key( 'ip', fs_client_ip() ) );

	if ( $phone ) {
		delete_transient( fs_guard_ban_key( 'phone', $phone ) );
	}
}

/* -------------------------------------------------------------------------
   پنجره‌ی ساعتی
   ------------------------------------------------------------------------- */

/**
 * آیا الان در ساعت مجاز ثبت‌نام هستیم؟
 *
 * از wp_date استفاده می‌شود تا ساعت، ساعتِ محلی سایت باشد نه UTC سرور.
 * بازه‌ی شب‌گرد (مثلاً ۲۲ تا ۶) هم پشتیبانی می‌شود.
 *
 * @return bool
 */
function fs_guard_hours_open() {
	if ( empty( fs_guard( 'hours_enabled' ) ) ) {
		return true;
	}

	$from = (int) fs_guard( 'hours_from' );
	$to   = (int) fs_guard( 'hours_to' );
	$now  = (int) wp_date( 'G' );

	if ( $from === $to ) {
		return true;
	}

	return $from < $to
		? ( $now >= $from && $now < $to )
		: ( $now >= $from || $now < $to ); // بازه‌ای که از نیمه‌شب رد می‌شود.
}

/* -------------------------------------------------------------------------
   کپچا
   ------------------------------------------------------------------------- */

/**
 * آیا کپچا فعال و کامل تنظیم شده است؟
 *
 * @return bool
 */
function fs_captcha_on() {
	$s = fs_guard_settings();

	return 'none' !== $s['captcha']
		&& '' !== trim( (string) $s['site_key'] )
		&& '' !== trim( (string) $s['secret_key'] );
}

/**
 * نشانی اسکریپت کپچا و نام فیلدی که توکن در آن می‌آید.
 *
 * @return array{script:string, field:string, provider:string, sitekey:string, score:float}|null
 */
function fs_captcha_config() {
	if ( ! fs_captcha_on() ) {
		return null;
	}

	$s        = fs_guard_settings();
	$provider = (string) $s['captcha'];

	$scripts = array(
		'recaptcha_v3' => 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $s['site_key'] ),
		'recaptcha_v2' => 'https://www.google.com/recaptcha/api.js',
		'hcaptcha'     => 'https://js.hcaptcha.com/1/api.js',
	);

	if ( ! isset( $scripts[ $provider ] ) ) {
		return null;
	}

	return array(
		'provider' => $provider,
		'script'   => $scripts[ $provider ],
		'sitekey'  => (string) $s['site_key'],
		'field'    => 'hcaptcha' === $provider ? 'h-captcha-response' : 'g-recaptcha-response',
		'score'    => (float) $s['score'],
	);
}

/**
 * بررسی توکن کپچا نزد سرویس‌دهنده.
 *
 * @param string $token توکن ارسالی از مرورگر.
 * @return true|WP_Error
 */
function fs_captcha_verify( $token ) {
	$config = fs_captcha_config();

	if ( ! $config ) {
		return true; // کپچا خاموش است.
	}

	if ( '' === trim( (string) $token ) ) {
		return new WP_Error( 'fs_captcha_missing', 'تایید امنیتی انجام نشد. صفحه را تازه کنید.' );
	}

	$endpoint = 'hcaptcha' === $config['provider']
		? 'https://api.hcaptcha.com/siteverify'
		: 'https://www.google.com/recaptcha/api/siteverify';

	$response = wp_remote_post(
		$endpoint,
		array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => fs_guard( 'secret_key' ),
				'response' => $token,
				'remoteip' => fs_client_ip(),
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		// سرویس کپچا در دسترس نیست. اگر اینجا کاربر را رد کنیم، یک قطعی در
		// گوگل یعنی تعطیلی فروشگاه؛ پس رد نمی‌کنیم و فقط ثبت می‌کنیم.
		fs_guard_log( 'کپچا در دسترس نبود: ' . $response->get_error_message() );

		return true;
	}

	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $body ) || empty( $body['success'] ) ) {
		$codes = isset( $body['error-codes'] ) ? implode( ', ', (array) $body['error-codes'] ) : '?';
		fs_guard_log( 'کپچا رد شد: ' . $codes );

		return new WP_Error( 'fs_captcha_failed', 'تایید امنیتی رد شد. دوباره تلاش کنید.' );
	}

	// v3 نمره می‌دهد؛ زیر آستانه یعنی به احتمال زیاد ربات.
	if ( 'recaptcha_v3' === $config['provider'] && isset( $body['score'] ) && (float) $body['score'] < $config['score'] ) {
		fs_guard_log( sprintf( 'نمره‌ی کپچا پایین بود: %s', $body['score'] ) );

		return new WP_Error( 'fs_captcha_score', 'تایید امنیتی رد شد. دوباره تلاش کنید.' );
	}

	return true;
}

/* -------------------------------------------------------------------------
   دروازه‌ی اصلی
   ------------------------------------------------------------------------- */

/**
 * همه‌ی سدها، پیش از خرج‌کردن یک پیامک.
 *
 * ترتیب عمدی است: اول چیزهایی که رایگان‌اند (بن، ساعت، سقف)، آخر از همه کپچا
 * که یک درخواست شبکه به گوگل دارد.
 *
 * @param string $phone شماره‌ی گیرنده.
 * @param string $token توکن کپچا.
 * @return true|WP_Error
 */
function fs_guard_check_send( $phone, $token = '' ) {
	$ban = fs_guard_banned( $phone );

	if ( $ban ) {
		return new WP_Error(
			'fs_guard_banned',
			sprintf( 'به دلیل تلاش‌های ناموفق، تا %s دقیقه امکان درخواست کد نیست.', fs_fa_num( (int) ceil( $ban / 60 ) ) )
		);
	}

	if ( ! fs_guard_hours_open() ) {
		return new WP_Error(
			'fs_guard_hours',
			sprintf(
				'ثبت‌نام و ورود در این ساعت باز نیست. از ساعت %1$s تا %2$s تلاش کنید.',
				fs_fa_num( (int) fs_guard( 'hours_from' ) ),
				fs_fa_num( (int) fs_guard( 'hours_to' ) )
			)
		);
	}

	// فاصله‌ی اجباری: حتی پیش از رسیدن به سقف، رگبار را می‌گیرد.
	$velocity = max( 0, (int) fs_guard( 'velocity' ) );

	if ( $velocity ) {
		$gate = 'fs_vel_' . md5( fs_client_ip() );

		if ( get_transient( $gate ) ) {
			return new WP_Error( 'fs_guard_velocity', 'کمی آرام‌تر؛ چند ثانیه دیگر دوباره تلاش کنید.' );
		}

		set_transient( $gate, 1, $velocity );
	}

	$ip_block = fs_rate_limit_hit(
		'otp_ip',
		fs_client_ip(),
		(int) fs_guard( 'ip_max' ),
		(int) fs_guard( 'ip_window' ) * MINUTE_IN_SECONDS
	);

	if ( $ip_block ) {
		return new WP_Error(
			'fs_guard_ip',
			sprintf( 'درخواست بیش از حد. %s دقیقه دیگر تلاش کنید.', fs_fa_num( (int) ceil( $ip_block / 60 ) ) )
		);
	}

	// سقف روی خود شماره: بدون این، عوض‌کردن آی‌پی کافی بود تا برای یک نفر
	// پشت سر هم پیامک فرستاده شود.
	$phone_block = fs_rate_limit_hit(
		'otp_phone',
		$phone,
		(int) fs_guard( 'phone_max' ),
		(int) fs_guard( 'phone_window' ) * MINUTE_IN_SECONDS
	);

	if ( $phone_block ) {
		return new WP_Error(
			'fs_guard_phone',
			sprintf( 'برای این شماره بیش از حد کد فرستاده شده. %s دقیقه دیگر تلاش کنید.', fs_fa_num( (int) ceil( $phone_block / 60 ) ) )
		);
	}

	return fs_captcha_verify( $token );
}

/**
 * ثبت رویداد امنیتی برای مدیر.
 *
 * @param string $message متن.
 * @return void
 */
function fs_guard_log( $message ) {
	$settings               = fs_guard_settings();
	$settings['last_event'] = sprintf( '[%s] %s', wp_date( 'Y/m/d H:i' ), $message );

	update_option( FS_GUARD_OPTION, $settings, false );
}

/* -------------------------------------------------------------------------
   مرز حساب‌های مدیریتی
   ------------------------------------------------------------------------- */

/**
 * آیا این کاربر حق ورود با پیامک دارد؟
 *
 * حساب‌هایی که به پیشخوان دسترسی دارند از این مسیر وارد نمی‌شوند. دلیلش ساده
 * است: کد پیامکی به اندازه‌ی رمز عبور قوی نیست و روی چیزی سوار است که از
 * کنترل ما بیرون است — سیم‌کارت. برای حسابی که می‌تواند محصول و کاربر و
 * تنظیمات را عوض کند، این ریسک پذیرفتنی نیست.
 *
 * @param WP_User|int $user کاربر.
 * @return bool
 */
function fs_user_may_otp( $user ) {
	$user = is_numeric( $user ) ? get_user_by( 'id', (int) $user ) : $user;

	if ( ! $user instanceof WP_User ) {
		return true; // کاربر تازه؛ هنوز نقشی ندارد.
	}

	$privileged = array( 'manage_options', 'edit_posts', 'edit_others_posts', 'manage_woocommerce', 'promote_users', 'install_plugins' );

	foreach ( $privileged as $cap ) {
		if ( user_can( $user, $cap ) ) {
			return false;
		}
	}

	return (bool) apply_filters( 'fs_user_may_otp', true, $user );
}

/* -------------------------------------------------------------------------
   تنظیمات در پیشخوان
   ------------------------------------------------------------------------- */

/**
 * ذخیره‌ی تنظیمات لایه‌ی امنیتی.
 *
 * @return void
 */
function fs_guard_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}

	check_admin_referer( 'fs_guard_save' );

	$settings = fs_guard_settings();

	// هر عدد یک کف و سقف دارد: صفر یعنی «سد باز است» و عدد نجومی یعنی
	// «کاربر واقعی هم بیرون می‌ماند»؛ هیچ‌کدام تنظیم درستی نیست.
	$numbers = array(
		'ip_max'       => array( 1, 100 ),
		'ip_window'    => array( 1, 1440 ),
		'phone_max'    => array( 1, 50 ),
		'phone_window' => array( 1, 1440 ),
		'velocity'     => array( 0, 600 ),
		'ban_after'    => array( 2, 100 ),
		'ban_minutes'  => array( 1, 1440 ),
		'hours_from'   => array( 0, 23 ),
		'hours_to'     => array( 0, 23 ),
	);

	foreach ( $numbers as $key => $range ) {
		$value            = isset( $_POST[ $key ] ) ? (int) $_POST[ $key ] : $settings[ $key ];
		$settings[ $key ] = min( $range[1], max( $range[0], $value ) );
	}

	$settings['hours_enabled'] = ! empty( $_POST['hours_enabled'] );

	$providers           = array( 'none', 'recaptcha_v3', 'recaptcha_v2', 'hcaptcha' );
	$captcha             = isset( $_POST['captcha'] ) ? sanitize_key( wp_unslash( $_POST['captcha'] ) ) : 'none';
	$settings['captcha'] = in_array( $captcha, $providers, true ) ? $captcha : 'none';

	$settings['site_key'] = isset( $_POST['site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['site_key'] ) ) : '';
	$settings['score']    = isset( $_POST['score'] ) ? min( 1.0, max( 0.0, (float) $_POST['score'] ) ) : 0.5;

	// کلید محرمانه مثل کلید API فقط وقتی عوض می‌شود که چیزی تایپ شده باشد.
	$secret = isset( $_POST['secret_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['secret_key'] ) ) ) : '';

	if ( '' !== $secret ) {
		$settings['secret_key'] = $secret;
	}

	if ( ! empty( $_POST['clear_secret'] ) ) {
		$settings['secret_key'] = '';
	}

	update_option( FS_GUARD_OPTION, $settings, false );

	wp_safe_redirect( add_query_arg( 'fs_guard', 'saved', admin_url( 'admin.php?page=fs-theme-settings&tab=security' ) ) );
	exit;
}
add_action( 'admin_post_fs_guard_save', 'fs_guard_save' );

/**
 * برداشتن همه‌ی بن‌ها و شمارنده‌ها.
 *
 * وقتی لازم است که تنظیمات را سخت‌گیرانه گذاشته‌اید و کاربری بی‌گناه گیر کرده،
 * یا خودتان موقع تست بن شده‌اید.
 *
 * @return void
 */
function fs_guard_reset() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}

	check_admin_referer( 'fs_guard_reset' );

	global $wpdb;

	// شمارنده‌ها همه transient‌اند و پیشوند مشترک دارند، پس یک کوئری کافی است.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '\_transient\_fs\_ban\_%'
		    OR option_name LIKE '\_transient\_timeout\_fs\_ban\_%'
		    OR option_name LIKE '\_transient\_fs\_rl\_%'
		    OR option_name LIKE '\_transient\_timeout\_fs\_rl\_%'
		    OR option_name LIKE '\_transient\_fs\_vel\_%'
		    OR option_name LIKE '\_transient\_timeout\_fs\_vel\_%'"
	);

	wp_cache_flush();

	wp_safe_redirect( add_query_arg( 'fs_guard', 'reset', admin_url( 'admin.php?page=fs-theme-settings&tab=security' ) ) );
	exit;
}
add_action( 'admin_post_fs_guard_reset', 'fs_guard_reset' );

/**
 * محتوای تب «امنیت ورود».
 *
 * @return void
 */
function fs_guard_tab_content() {
	$s      = fs_guard_settings();
	$notice = isset( $_GET['fs_guard'] ) ? sanitize_key( wp_unslash( $_GET['fs_guard'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$messages = array(
		'saved' => array( 'updated', 'تنظیمات امنیتی ذخیره شد.' ),
		'reset' => array( 'updated', 'همه‌ی بن‌ها و شمارنده‌ها پاک شد.' ),
	);

	if ( isset( $messages[ $notice ] ) ) {
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $notice ][0] ),
			esc_html( $messages[ $notice ][1] )
		);
	}
	?>

	<h2 style="margin-top:24px">امنیت ورود با پیامک</h2>
	<p class="description" style="max-width:700px">
		هر پیامک برای شما هزینه دارد و هر کد اشتباه یک قدم به حدس‌زدن نزدیک‌تر است.
		مقادیر پیش‌فرض طوری چیده شده‌اند که کاربر عادی هیچ‌وقت به آن‌ها نخورد.
	</p>

	<div class="notice notice-info inline" style="max-width:700px;margin:14px 0">
		<p>
			<strong>حساب‌های مدیریتی از فرم پیامکی وارد نمی‌شوند.</strong>
			این قانون ثابت است و تنظیم ندارد: کد پیامکی روی سیم‌کارت سوار است که از کنترل
			سایت بیرون است، و برای حسابی که می‌تواند محصول و کاربر و تنظیمات را عوض کند
			این ریسک پذیرفتنی نیست. مدیرها با رمز عبور وارد می‌شوند.
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'fs_guard_save' ); ?>
		<input type="hidden" name="action" value="fs_guard_save">

		<h3>سقف تعداد درخواست</h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">از یک آی‌پی</th>
				<td>
					حداکثر <input type="number" name="ip_max" min="1" max="100" style="width:80px" value="<?php echo esc_attr( $s['ip_max'] ); ?>">
					پیامک در <input type="number" name="ip_window" min="1" max="1440" style="width:80px" value="<?php echo esc_attr( $s['ip_window'] ); ?>"> دقیقه
					<p class="description">جلوی کسی را می‌گیرد که با یک اتصال، شماره‌های مختلف را می‌کوبد.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">به یک شماره</th>
				<td>
					حداکثر <input type="number" name="phone_max" min="1" max="50" style="width:80px" value="<?php echo esc_attr( $s['phone_max'] ); ?>">
					پیامک در <input type="number" name="phone_window" min="1" max="1440" style="width:80px" value="<?php echo esc_attr( $s['phone_window'] ); ?>"> دقیقه
					<p class="description">
						بدون این سد، عوض‌کردن آی‌پی کافی بود تا برای یک قربانی صدها پیامک فرستاده شود
						(SMS Bombing).
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">فاصله‌ی اجباری</th>
				<td>
					دست‌کم <input type="number" name="velocity" min="0" max="600" style="width:80px" value="<?php echo esc_attr( $s['velocity'] ); ?>"> ثانیه بین دو درخواست
					<p class="description">رگبار سریع را می‌گیرد، حتی وقتی هنوز به سقف بالا نرسیده. صفر یعنی خاموش.</p>
				</td>
			</tr>
		</table>

		<h3>بن هوشمند</h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">مسدودسازی خودکار</th>
				<td>
					پس از <input type="number" name="ban_after" min="2" max="100" style="width:80px" value="<?php echo esc_attr( $s['ban_after'] ); ?>"> کد اشتباه،
					<input type="number" name="ban_minutes" min="1" max="1440" style="width:80px" value="<?php echo esc_attr( $s['ban_minutes'] ); ?>"> دقیقه مسدود شود
					<p class="description">
						مدت بن با هر بار تکرار دو برابر می‌شود تا سقف ۲۴ ساعت. شمارش هم روی آی‌پی است
						هم روی شماره، و با گرفتن کد تازه صفر نمی‌شود — وگرنه مهاجم فقط کافی بود هر چند
						حدس یک کد نو بگیرد.
					</p>
				</td>
			</tr>
		</table>

		<h3>ساعت مجاز</h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">محدودیت ساعتی</th>
				<td>
					<label>
						<input type="checkbox" name="hours_enabled" value="1" <?php checked( ! empty( $s['hours_enabled'] ) ); ?>>
						ورود و ثبت‌نام فقط در بازه‌ی زیر ممکن باشد
					</label>
					<p style="margin-top:10px">
						از ساعت <input type="number" name="hours_from" min="0" max="23" style="width:70px" value="<?php echo esc_attr( $s['hours_from'] ); ?>">
						تا ساعت <input type="number" name="hours_to" min="0" max="23" style="width:70px" value="<?php echo esc_attr( $s['hours_to'] ); ?>">
					</p>
					<p class="description">
						به وقت محلی سایت. بازه‌ی شب‌گرد هم کار می‌کند (مثلاً ۲۲ تا ۶).
						<strong>حواستان باشد این سد جلوی خرید شبانه را هم می‌گیرد.</strong>
					</p>
				</td>
			</tr>
		</table>

		<h3>کپچا</h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="fs-guard-captcha">سرویس</label></th>
				<td>
					<select id="fs-guard-captcha" name="captcha">
						<option value="none" <?php selected( $s['captcha'], 'none' ); ?>>بدون کپچا</option>
						<option value="recaptcha_v3" <?php selected( $s['captcha'], 'recaptcha_v3' ); ?>>reCAPTCHA v3 (نامرئی)</option>
						<option value="recaptcha_v2" <?php selected( $s['captcha'], 'recaptcha_v2' ); ?>>reCAPTCHA v2 (تیک «ربات نیستم»)</option>
						<option value="hcaptcha" <?php selected( $s['captcha'], 'hcaptcha' ); ?>>hCaptcha</option>
					</select>
					<p class="description">
						reCAPTCHA به سرورهای گوگل وصل می‌شود؛ اگر کاربران شما به آن دسترسی ندارند،
						hCaptcha گزینه‌ی امن‌تری است.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="fs-guard-site">کلید عمومی (Site Key)</label></th>
				<td><input class="regular-text ltr" id="fs-guard-site" type="text" name="site_key" dir="ltr" value="<?php echo esc_attr( $s['site_key'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="fs-guard-secret">کلید محرمانه (Secret Key)</label></th>
				<td>
					<input class="regular-text ltr" id="fs-guard-secret" type="password" name="secret_key" dir="ltr"
						autocomplete="new-password"
						placeholder="<?php echo $s['secret_key'] ? 'ذخیره شده — برای تغییر، کلید تازه را وارد کنید' : ''; ?>">
					<?php if ( $s['secret_key'] ) : ?>
						<p class="description">
							<label><input type="checkbox" name="clear_secret" value="1"> پاک کردن کلید</label>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="fs-guard-score">آستانه‌ی نمره (فقط v3)</label></th>
				<td>
					<input id="fs-guard-score" type="number" name="score" min="0" max="1" step="0.1" style="width:80px" value="<?php echo esc_attr( $s['score'] ); ?>">
					<p class="description">
						هر چه بالاتر، سخت‌گیرانه‌تر. ۰٫۵ نقطه‌ی شروع خوبی است؛ اگر کاربران واقعی رد شدند پایین‌تر بیاورید.
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( 'ذخیره تنظیمات امنیتی' ); ?>
	</form>

	<hr>

	<h2>پاک‌کردن بن‌ها</h2>
	<p class="description" style="max-width:700px">
		همه‌ی مسدودی‌ها و شمارنده‌ها را صفر می‌کند. وقتی لازم می‌شود که کاربری بی‌گناه گیر
		کرده باشد یا خودتان موقع تست بن شده باشید.
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'fs_guard_reset' ); ?>
		<input type="hidden" name="action" value="fs_guard_reset">
		<?php submit_button( 'پاک‌کردن همه‌ی بن‌ها', 'secondary', 'submit', false ); ?>
	</form>

	<?php if ( ! empty( $s['last_event'] ) ) : ?>
		<h2 style="margin-top:28px">آخرین رویداد امنیتی</h2>
		<p class="description" dir="ltr" style="text-align:left"><?php echo esc_html( $s['last_event'] ); ?></p>
	<?php endif; ?>
	<?php
}
