<?php
/**
 * تحویل فایل: مسیر محلی، نشانه‌ی شروع دانلود و ابزار عیب‌یابی.
 *
 * مسئله‌ای که این فایل حل می‌کند از یک تصمیم میزبانی می‌آید: فایل‌ها روی یک
 * زیردامنه‌اند، نه روی دامنه‌ی اصلی. برای ووکامرس این یعنی «فایل دور» و همین
 * دو مشکل می‌سازد:
 *
 * ۱. در حالت «اجبار به دانلود»، ووکامرس اول تلاش می‌کند فایل را خودش بخواند و
 *    بفرستد. برای نشانی HTTP این کار به allow_url_fopen نیاز دارد؛ اگر خاموش
 *    باشد ووکامرس به «تغییر مسیر» عقب می‌نشیند و مرورگر را مستقیم به زیردامنه
 *    می‌فرستد. آنجا دیگر هدر Content-Disposition وجود ندارد، پس مرورگر PDF و
 *    Word را به‌جای دانلود، *باز* می‌کند. برای zip این اتفاق نمی‌افتد چون
 *    مرورگر بلد نیست نمایشش دهد — دقیقاً همان تفاوتی که در عمل دیده می‌شود.
 *
 * ۲. حتی وقتی allow_url_fopen روشن است، فایل دو بار جابه‌جا می‌شود: زیردامنه
 *    به سرور، سرور به کاربر. برای یک فایل چند ده مگابایتی این یعنی ده‌ها ثانیه
 *    انتظار روی صفحه‌ای که هیچ نشانه‌ای از کارکردن نمی‌دهد.
 *
 * راه‌حل هر دو یکی است: اگر زیردامنه روی همین سرور باشد، نشانی HTTP را به
 * مسیر واقعی فایل روی دیسک ترجمه کنیم. آن‌وقت ووکامرس یک فایل محلی می‌خواند —
 * سریع، و با هدر درست.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
   ترجمه‌ی نشانی به مسیر محلی
   ------------------------------------------------------------------------- */

/**
 * ریشه‌هایی که فایل مجاز است داخلشان باشد.
 *
 * روی هاست‌های اشتراکی (دایرکت‌ادمین و سی‌پنل) همه‌ی دامنه‌ها و زیردامنه‌های یک
 * حساب زیر یک پوشه‌ی مشترک‌اند؛ همان می‌شود مرز مجاز. هر مسیری که بیرون از
 * این ریشه‌ها بیفتد رد می‌شود، حتی اگر فایل واقعاً آنجا باشد.
 *
 * @return string[]
 */
function fs_dl_roots() {
	$docroot = untrailingslashit( ABSPATH );

	$roots = array(
		realpath( $docroot ),                 // خود سایت
		realpath( dirname( $docroot ) ),      // پوشه‌ی دامنه
		realpath( dirname( dirname( $docroot ) ) ), // .../domains
	);

	return array_values( array_unique( array_filter( (array) apply_filters( 'fs_dl_roots', $roots ) ) ) );
}

/**
 * میزبان نشانی، بدون www.
 *
 * این یک جزئیات کوچک با اثر بزرگ است: نشانی فایل‌ها با www ذخیره شده
 * (www.dl.luxu.ir) ولی پوشه روی سرور بدون آن است (dl.luxu.ir). بدون این
 * پاک‌سازی هیچ‌کدام از مسیرهای حدس‌زده‌شده به فایل نمی‌رسید و ترجمه همیشه
 * شکست می‌خورد — بی‌آنکه خطایی بدهد، چون عقب‌نشینی به همان مسیر دور بی‌صداست.
 *
 * @param string $host میزبان.
 * @return string
 */
function fs_dl_bare_host( $host ) {
	return preg_replace( '/^www\./i', '', (string) $host );
}

/**
 * مسیرهای محتملی که یک نشانی می‌تواند روی دیسک داشته باشد.
 *
 * چیدمان‌های رایج هاست ایرانی همه امتحان می‌شوند و در آخر، اگر هیچ‌کدام
 * نگرفت، بین دامنه‌های همان حساب می‌گردیم. ترتیب از دقیق به عمومی است تا
 * جست‌وجوی پرهزینه فقط وقتی اجرا شود که لازم باشد.
 *
 * @param string $url نشانی فایل.
 * @return string[]
 */
function fs_dl_candidate_paths( $url ) {
	$parts = wp_parse_url( $url );

	if ( empty( $parts['host'] ) || empty( $parts['path'] ) ) {
		return array();
	}

	$host = fs_dl_bare_host( $parts['host'] );
	$path = ltrim( rawurldecode( $parts['path'] ), '/' );

	$docroot = untrailingslashit( ABSPATH );
	$domains = dirname( dirname( $docroot ) );

	// اولین برچسب میزبان: برای dl.luxu.ir می‌شود dl.
	$label = strtok( $host, '.' );

	$candidates = array(
		// زیردامنه پوشه‌ی مستقل دارد.
		$domains . '/' . $host . '/public_html/' . $path,
		$domains . '/' . $host . '/' . $path,
		// چیدمان پیش‌فرض دایرکت‌ادمین: زیردامنه، پوشه‌ای داخل دامنه‌ی اصلی.
		$docroot . '/' . $label . '/' . $path,
		// فایل روی خود دامنه.
		$docroot . '/' . $path,
	);

	return (array) apply_filters( 'fs_dl_candidate_paths', $candidates, $url );
}

/**
 * گشتن بین دامنه‌های همان حساب.
 *
 * آخرین چاره، وقتی هیچ‌کدام از چیدمان‌های شناخته‌شده نگرفت. نتیجه — چه پیدا
 * شود چه نشود — کش می‌شود، چون این جست‌وجو به دیسک می‌زند و نباید سر هر
 * دانلود تکرار شود.
 *
 * @param string $path مسیر نسبی فایل.
 * @return string
 */
function fs_dl_scan_domains( $path ) {
	$domains = dirname( dirname( untrailingslashit( ABSPATH ) ) );
	$dirs    = glob( $domains . '/*/public_html', GLOB_ONLYDIR );

	if ( ! $dirs ) {
		return '';
	}

	foreach ( $dirs as $dir ) {
		$try = $dir . '/' . $path;

		if ( is_file( $try ) && is_readable( $try ) ) {
			return $try;
		}
	}

	return '';
}

/**
 * ترجمه‌ی نشانی فایل به مسیر محلی، اگر ممکن باشد.
 *
 * realpath هم مسیر را نرمال می‌کند و هم — مهم‌تر — نتیجه‌ی یک مسیر ساختگی با
 * ../ را لو می‌دهد؛ بعدش بررسی می‌کنیم که واقعاً زیر یکی از ریشه‌های مجاز
 * مانده باشد.
 *
 * @param string $url نشانی.
 * @return string مسیر محلی یا رشته‌ی خالی.
 */
function fs_dl_local_path( $url ) {
	$key    = 'fs_dlp_' . md5( $url );
	$cached = get_transient( $key );

	if ( is_string( $cached ) ) {
		return $cached;
	}

	$roots  = fs_dl_roots();
	$found  = '';
	$tries  = fs_dl_candidate_paths( $url );
	$parts  = wp_parse_url( $url );

	if ( ! empty( $parts['path'] ) ) {
		$tries[] = fs_dl_scan_domains( ltrim( rawurldecode( $parts['path'] ), '/' ) );
	}

	foreach ( array_filter( $tries ) as $candidate ) {
		$real = realpath( $candidate );

		if ( ! $real || ! is_file( $real ) || ! is_readable( $real ) ) {
			continue;
		}

		foreach ( $roots as $root ) {
			if ( 0 === strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
				$found = $real;
				break 2;
			}
		}
	}

	/*
	 * نتیجه‌ی منفی هم کش می‌شود، ولی کوتاه‌تر: اگر فایل تازه آپلود شود یا
	 * مسیرها عوض شوند، ظرف یک ساعت خودش درست می‌شود بی‌آنکه کسی کاری کند.
	 */
	set_transient( $key, $found, $found ? DAY_IN_SECONDS : HOUR_IN_SECONDS );

	return $found;
}

/**
 * جایگزینی نشانی فایل با مسیر محلی، پیش از تحویل.
 *
 * چرا این مهم است: ووکامرس برای «فایل دور» کل فایل را با HTTP از زیردامنه
 * می‌گیرد و بعد به کاربر می‌دهد. یعنی هر بایت دو بار جابه‌جا می‌شود و کاربر
 * تا تمام‌شدن نوبت اول هیچ چیزی نمی‌بیند — همان انتظار ده‌ها ثانیه‌ای. با
 * مسیر محلی، فایل مستقیم از دیسک خوانده و هم‌زمان فرستاده می‌شود.
 *
 * @param string $file_path مسیر یا نشانی فایل.
 * @return string
 */
function fs_dl_localize_path( $file_path ) {
	if ( ! is_string( $file_path ) || ! preg_match( '#^https?://#i', $file_path ) ) {
		return $file_path;
	}

	$local = fs_dl_local_path( $file_path );

	return $local ? $local : $file_path;
}
add_filter( 'woocommerce_download_product_filepath', 'fs_dl_localize_path', 20 );

/* -------------------------------------------------------------------------
   نشانه‌ی شروع دانلود
   ------------------------------------------------------------------------- */

/**
 * ست‌کردن کوکی در لحظه‌ای که فایل واقعاً شروع به آمدن می‌کند.
 *
 * مرورگر هیچ رویدادی برای «دانلود شروع شد» به جاوااسکریپت نمی‌دهد؛ کلیک روی
 * لینک دانلود از نظر صفحه یک ناوبری معمولی است که هیچ‌وقت تمام نمی‌شود. تنها
 * راه استانداردِ فهمیدنش همین است: سرور همراه پاسخِ فایل یک کوکی می‌فرستد و
 * صفحه منتظر پیدا شدنش می‌ماند.
 *
 * کوکی باید *پیش از* هر خروجی ست شود، و این قلاب دقیقاً پیش از فرستادن فایل
 * اجرا می‌شود.
 *
 * @return void
 */
function fs_dl_mark_started() {
	$token = isset( $_GET['fs_dl'] ) ? sanitize_key( wp_unslash( $_GET['fs_dl'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! $token || headers_sent() ) {
		return;
	}

	setcookie( 'fs_dl_' . $token, '1', time() + 300, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
}
add_action( 'woocommerce_download_product', 'fs_dl_mark_started', 1 );

/**
 * افزودن نشانه به یک لینک دانلود.
 *
 * @param string $url   نشانی دانلود.
 * @param string $token نشانه.
 * @return string
 */
function fs_dl_tag_url( $url, $token ) {
	return add_query_arg( 'fs_dl', $token, $url );
}

/**
 * ساخت یک نشانه‌ی یکتا برای هر دکمه.
 *
 * @return string
 */
function fs_dl_token() {
	static $n = 0;

	++$n;

	return 'x' . substr( md5( uniqid( (string) $n, true ) ), 0, 12 );
}

/**
 * چاپ یک دکمه‌ی دانلود با نشانه و حالت انتظار.
 *
 * @param string $url   نشانی دانلود.
 * @param string $label متن دکمه.
 * @param string $class کلاس دکمه.
 * @return void
 */
function fs_the_download_button( $url, $label = 'دانلود', $class = 'fs-ditem__btn', $file = '' ) {
	$token = fs_dl_token();
	?>
	<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( fs_dl_tag_url( $url, $token ) ); ?>"
		data-download="<?php echo esc_attr( $token ); ?>"
		data-download-name="<?php echo esc_attr( $file ); ?>" rel="nofollow">
		<span class="fs-dlbtn__idle">
			<?php fs_the_icon( 'download', 14, array( 'stroke' => '#fff' ) ); ?>
			<?php echo esc_html( $label ); ?>
		</span>
		<span class="fs-dlbtn__wait" hidden>
			<span class="fs-dlbtn__spin" aria-hidden="true"></span>
			در حال آماده‌سازی…
		</span>
	</a>
	<?php
}

/**
 * جایگزینی دکمه‌ی دانلود در برگه‌ی «دانلودهای من» ووکامرس.
 *
 * آن برگه قالب خودش را دارد و قالب سایت رویش نیامده، پس لینک‌هایش ساده
 * می‌ماندند و پوشش انتظار برایشان باز نمی‌شد — درست همان‌جایی که کاربر
 * دیرتر و با حوصله‌ی کمتر برمی‌گردد سراغ فایلش.
 *
 * @param array $download داده‌ی دانلود.
 * @return void
 */
function fs_dl_account_button( $download ) {
	fs_the_download_button(
		$download['download_url'],
		isset( $download['download_name'] ) ? $download['download_name'] : 'دانلود',
		'woocommerce-MyAccount-downloads-file button alt fs-ditem__btn',
		isset( $download['product_name'] ) ? $download['product_name'] : ''
	);
}

/**
 * برداشتن دکمه‌ی پیش‌فرض و گذاشتن دکمه‌ی خودمان.
 *
 * روی init انجام می‌شود چون کال‌بک پیش‌فرض ووکامرس هنگام بارگذاری افزونه ثبت
 * می‌شود و پیش از آن چیزی برای برداشتن وجود ندارد.
 *
 * @return void
 */
function fs_dl_swap_account_button() {
	remove_action( 'woocommerce_account_downloads_column_download-file', 'woocommerce_account_downloads_column_download_file' );
	add_action( 'woocommerce_account_downloads_column_download-file', 'fs_dl_account_button' );
}
add_action( 'init', 'fs_dl_swap_account_button', 20 );

/**
 * پوشش انتظار دانلود.
 *
 * یک بار در پاورقی چاپ می‌شود و همه‌ی دکمه‌های دانلود صفحه از آن استفاده
 * می‌کنند. چرا پوشش تمام‌صفحه و نه فقط اسپینر روی دکمه: انتظار اینجا ده‌ها
 * ثانیه است، نه یک لحظه. یک چرخنده‌ی کوچک گوشه‌ی دکمه در آن مدت به چشم
 * نمی‌آید و کاربر — همان‌طور که در عمل دیده شده — نتیجه می‌گیرد صفحه خراب
 * است و می‌بندد. متن باید جایی باشد که نشود ندیدش.
 *
 * نوار پیشرفت درصد واقعی نشان نمی‌دهد و عمداً هم عددی چاپ نمی‌کند: مرورگر
 * هیچ راهی برای گزارش پیشرفتِ یک دانلودِ ناوبری نمی‌دهد. نوار روی منحنی‌ای
 * جلو می‌رود که هیچ‌وقت به انتها نمی‌رسد و فقط وقتی پر می‌شود که دانلود
 * واقعاً شروع شده باشد. یعنی «دارد کار می‌کند» را می‌گوید، نه یک عدد ساختگی.
 *
 * @return void
 */
function fs_download_overlay() {
	if ( is_admin() ) {
		return;
	}
	?>
	<div class="fs-dlwait" id="fs-dlwait" hidden role="dialog" aria-modal="true"
		aria-labelledby="fs-dlwait-title" aria-describedby="fs-dlwait-note">
		<div class="fs-dlwait__box">

			<button class="fs-dlwait__close" type="button" data-dlwait-close aria-label="بستن">
				<?php fs_the_icon( 'close', 16, array( 'width' => '2.2' ) ); ?>
			</button>

			<div class="fs-dlwait__glass" aria-hidden="true">
				<svg viewBox="0 0 48 64" width="72" height="96">
					<defs>
						<clipPath id="fs-hg-top"><path d="M9 5h30c0 12-9 16-9 21H18c0-5-9-9-9-21z"/></clipPath>
						<clipPath id="fs-hg-bot"><path d="M18 38h12c0 5 9 9 9 21H9c0-12 9-16 9-21z"/></clipPath>
					</defs>

					<g class="fs-hg__frame">
						<path d="M8 3h32M8 61h32"/>
						<path d="M9 5c0 12 9 16 9 21s-9 9-9 21"/>
						<path d="M39 5c0 12-9 16-9 21s9 9 9 21"/>
					</g>

					<g clip-path="url(#fs-hg-top)">
						<rect class="fs-hg__sand-top" x="8" y="5" width="32" height="24"/>
					</g>

					<g clip-path="url(#fs-hg-bot)">
						<rect class="fs-hg__sand-bot" x="8" y="59" width="32" height="0"/>
					</g>

					<line class="fs-hg__stream" x1="24" y1="27" x2="24" y2="45"/>
				</svg>
			</div>

			<h2 class="fs-dlwait__title" id="fs-dlwait-title" data-dlwait-title>در حال ساخت لینک دانلود</h2>

			<p class="fs-dlwait__file" data-dlwait-file hidden></p>

			<div class="fs-dlwait__bar" aria-hidden="true">
				<span class="fs-dlwait__fill" data-dlwait-fill></span>
			</div>

			<p class="fs-dlwait__note" id="fs-dlwait-note">
				کاربر عزیز، به علت حجم بالای برخی فایل‌ها در دانلود شدن کامل فایل
				صبوری به خرج دهید و عجله نکنید.
			</p>

			<p class="fs-dlwait__meta">
				<span data-dlwait-timer>۰۰:۰۰</span> · این پنجره را نبندید
			</p>

			<div class="fs-dlwait__slow" data-dlwait-slow hidden>
				اگر دانلود شروع نشد،
				<a href="#" data-dlwait-retry>یک بار دیگر امتحان کنید</a>
				یا با پشتیبانی تماس بگیرید.
			</div>

			<div class="fs-dlwait__done" data-dlwait-done hidden>
				<span class="fs-dlwait__tick"><?php fs_the_icon( 'check', 18, array( 'stroke' => '#fff', 'width' => '3' ) ); ?></span>
				دانلود شروع شد. فایل در پوشه‌ی دانلودهای مرورگر شما ذخیره می‌شود.
			</div>

		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'fs_download_overlay', 30 );

/* -------------------------------------------------------------------------
   دسترسی مادام‌العمر و ترمیم خودکار مجوزها
   ------------------------------------------------------------------------- */

/**
 * دانلودها هیچ‌وقت منقضی نشوند.
 *
 * صفحه‌ی «فایل‌های من» به کاربر «دسترسی مادام‌العمر» را وعده می‌دهد، پس تاریخ
 * انقضا نباید اصلاً ثبت شود. اگر ثبت شود، ووکامرس آن ردیف را از فهرست کنار
 * می‌گذارد و کاربر با یک صفحه‌ی خالی روبه‌رو می‌شود — بی‌آنکه هیچ توضیحی
 * ببیند و بی‌آنکه سفارشش جایی گم شده باشد.
 *
 * @param mixed $value مقدار تنظیم.
 * @return string
 */
function fs_dl_never_expire( $value ) {
	unset( $value );

	return '';
}
add_filter( 'pre_option_woocommerce_downloads_expire', 'fs_dl_never_expire' );
add_filter( 'option_woocommerce_downloads_expire', 'fs_dl_never_expire' );

/**
 * برداشتن تاریخ انقضا از مجوزی که تازه صادر می‌شود.
 *
 * @param array $data داده‌ی مجوز.
 * @return array
 */
function fs_dl_clear_expiry( $data ) {
	if ( is_array( $data ) ) {
		$data['access_expires'] = null;
	}

	return $data;
}
add_filter( 'woocommerce_downloadable_file_permission_data', 'fs_dl_clear_expiry' );

/**
 * ترمیم مجوزهای دانلودِ کاربری که همین حالا فایل‌هایش را می‌بیند.
 *
 * سه چیز را با هم درست می‌کند و هر سه دلیلِ «هیچی نمی‌آورد» بوده‌اند:
 *
 * ۱. سفارش‌هایی که مجوزشان با user_id = 0 صادر شده. ووکامرس فهرست را با
 *    `WHERE user_id = %d` می‌گیرد، پس آن ردیف‌ها هرگز به کاربر نمی‌رسند.
 * ۲. مجوزهایی که تاریخ انقضایشان گذشته است.
 * ۳. سفارش‌های پرداخت‌شده‌ای که اصلاً مجوزی برایشان صادر نشده.
 *
 * چرا اینجا و نه با یک دکمه در پیشخوان: کاربر وقتی متوجه مشکل می‌شود که به
 * این صفحه می‌آید؛ همان لحظه هم باید حل شود. یک بار در روز برای هر کاربر
 * اجرا می‌شود تا بار اضافه‌ای نسازد.
 *
 * @return void
 */
function fs_dl_repair_for_current_user() {
	if ( ! is_user_logged_in() || ! function_exists( 'wc_get_orders' ) ) {
		return;
	}

	$user_id = get_current_user_id();
	$guard   = 'fs_dlfix_' . $user_id;

	if ( get_transient( $guard ) ) {
		return;
	}

	set_transient( $guard, 1, DAY_IN_SECONDS );

	global $wpdb;

	$table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
	$user  = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return;
	}

	// ۱) انقضای گذشته را برمی‌داریم؛ دسترسی این فروشگاه مادام‌العمر است.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"UPDATE {$table} SET access_expires = NULL WHERE user_id = %d AND access_expires IS NOT NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_id
		)
	);

	// ۲) ردیف‌های بی‌صاحبِ همین ایمیل را به حساب وصل می‌کنیم.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"UPDATE {$table} SET user_id = %d WHERE ( user_id = 0 OR user_id IS NULL ) AND user_email = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$user_id,
			$user->user_email
		)
	);

	// ۳) سفارش‌های پرداخت‌شده‌ای که هیچ مجوزی ندارند.
	$orders = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'status'      => array( 'wc-processing', 'wc-completed' ),
			'limit'       => 50,
			'return'      => 'ids',
		)
	);

	foreach ( (array) $orders as $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->has_downloadable_item() ) {
			continue;
		}

		$rows = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE order_id = %d", $order_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $rows ) {
			wc_downloadable_product_permissions( $order_id, true );
		}
	}

	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'customer_download' );
	}
}

/**
 * اجرای ترمیم روی صفحه‌های حساب کاربری.
 *
 * @return void
 */
function fs_dl_maybe_repair() {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}

	fs_dl_repair_for_current_user();
}
add_action( 'template_redirect', 'fs_dl_maybe_repair', 5 );
