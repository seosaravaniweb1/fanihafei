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

const FS_DL_OPTION = 'fs_downloads';

/**
 * تنظیمات تحویل فایل.
 *
 * @return array<string, string>
 */
function fs_dl_settings() {
	$saved = get_option( FS_DL_OPTION, array() );

	return wp_parse_args(
		is_array( $saved ) ? $saved : array(),
		array(
			'base_url'  => '',
			'base_path' => '',
		)
	);
}

/* -------------------------------------------------------------------------
   ترجمه‌ی نشانی به مسیر محلی
   ------------------------------------------------------------------------- */

/**
 * مسیرهای محتملی که یک نشانی می‌تواند روی دیسک داشته باشد.
 *
 * ترتیب مهم است: اول نگاشت دستی مدیر (اگر داده باشد)، بعد چیدمان‌های رایج
 * دایرکت‌ادمین و سی‌پنل. اولین مسیری که واقعاً فایل در آن باشد برنده است.
 *
 * @param string $url نشانی فایل.
 * @return string[]
 */
function fs_dl_candidate_paths( $url ) {
	$parts = wp_parse_url( $url );

	if ( empty( $parts['host'] ) || empty( $parts['path'] ) ) {
		return array();
	}

	$host = $parts['host'];
	$path = ltrim( rawurldecode( $parts['path'] ), '/' );

	$candidates = array();

	// ۱) نگاشت دستی.
	$settings = fs_dl_settings();

	if ( $settings['base_url'] && $settings['base_path'] ) {
		$base_url = trailingslashit( $settings['base_url'] );

		if ( 0 === strpos( $url, $base_url ) ) {
			$candidates[] = trailingslashit( $settings['base_path'] ) . ltrim( substr( $url, strlen( $base_url ) ), '/' );
		}
	}

	// docroot سایت، و پوشه‌ی بالاتر از آن.
	$docroot = untrailingslashit( ABSPATH );
	$domains = dirname( dirname( $docroot ) ); // .../domains

	// ۲) دایرکت‌ادمین: هر زیردامنه پوشه‌ی خودش را دارد.
	$candidates[] = $domains . '/' . $host . '/public_html/' . $path;

	// ۳) دایرکت‌ادمین (حالت دیگر): زیردامنه به‌شکل پوشه زیر دامنه‌ی اصلی.
	$label = strtok( $host, '.' );

	if ( $label ) {
		$candidates[] = $docroot . '/' . $label . '/' . $path;
		$candidates[] = $domains . '/' . $host . '/' . $path;
	}

	// ۴) همان دامنه، مسیر مستقیم زیر docroot.
	$candidates[] = $docroot . '/' . $path;

	return (array) apply_filters( 'fs_dl_candidate_paths', $candidates, $url );
}

/**
 * ترجمه‌ی نشانی فایل به مسیر محلی، اگر ممکن باشد.
 *
 * realpath هم مسیر را نرمال می‌کند و هم — مهم‌تر — نتیجه‌ی یک مسیر ساختگی با
 * ../ را به بیرون از ریشه لو می‌دهد؛ بعدش بررسی می‌کنیم که واقعاً زیر یکی از
 * ریشه‌های مجاز مانده باشد.
 *
 * @param string $url نشانی.
 * @return string مسیر محلی یا رشته‌ی خالی.
 */
function fs_dl_local_path( $url ) {
	$roots = array( realpath( ABSPATH ), realpath( dirname( dirname( untrailingslashit( ABSPATH ) ) ) ) );

	$settings = fs_dl_settings();

	if ( $settings['base_path'] ) {
		$roots[] = realpath( $settings['base_path'] );
	}

	$roots = array_filter( $roots );

	foreach ( fs_dl_candidate_paths( $url ) as $candidate ) {
		$real = realpath( $candidate );

		if ( ! $real || ! is_file( $real ) || ! is_readable( $real ) ) {
			continue;
		}

		foreach ( $roots as $root ) {
			if ( 0 === strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
				return $real;
			}
		}
	}

	return '';
}

/**
 * جایگزینی نشانی فایل با مسیر محلی، پیش از تحویل.
 *
 * فقط وقتی که فایل واقعاً روی همین سرور پیدا شود. اگر پیدا نشود، هیچ تغییری
 * نمی‌دهیم و ووکامرس همان کاری را می‌کند که قبلاً می‌کرد.
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
   تنظیمات و عیب‌یابی
   ------------------------------------------------------------------------- */

/**
 * ذخیره‌ی تنظیمات تحویل فایل.
 *
 * @return void
 */
function fs_dl_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}

	check_admin_referer( 'fs_dl_save' );

	update_option(
		FS_DL_OPTION,
		array(
			'base_url'  => isset( $_POST['base_url'] ) ? esc_url_raw( wp_unslash( $_POST['base_url'] ) ) : '',
			'base_path' => isset( $_POST['base_path'] ) ? untrailingslashit( sanitize_text_field( wp_unslash( $_POST['base_path'] ) ) ) : '',
		),
		false
	);

	wp_safe_redirect( add_query_arg( 'fs_dl', 'saved', admin_url( 'admin.php?page=fs-theme-settings&tab=downloads' ) ) );
	exit;
}
add_action( 'admin_post_fs_dl_save', 'fs_dl_save' );

/**
 * چند فایل نمونه از محصولات دانلودی، برای نشان‌دادن نتیجه‌ی ترجمه.
 *
 * @param int $limit تعداد.
 * @return array<int, array{name:string, url:string, local:string}>
 */
function fs_dl_sample_files( $limit = 5 ) {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return array();
	}

	$products = wc_get_products(
		array(
			'limit'        => $limit,
			'downloadable' => true,
			'status'       => 'publish',
			'return'       => 'objects',
		)
	);

	$out = array();

	foreach ( $products as $product ) {
		foreach ( $product->get_downloads() as $download ) {
			$url = $download->get_file();

			$out[] = array(
				'name'  => $product->get_name() . ' — ' . $download->get_name(),
				'url'   => $url,
				'local' => preg_match( '#^https?://#i', $url ) ? fs_dl_local_path( $url ) : $url,
			);

			if ( count( $out ) >= $limit ) {
				return $out;
			}
		}
	}

	return $out;
}

/**
 * محتوای تب «دانلودها».
 *
 * @return void
 */
function fs_dl_tab_content() {
	$settings = fs_dl_settings();
	$notice   = isset( $_GET['fs_dl'] ) ? sanitize_key( wp_unslash( $_GET['fs_dl'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( 'saved' === $notice ) {
		echo '<div class="notice notice-updated is-dismissible"><p>تنظیمات ذخیره شد.</p></div>';
	}

	$samples = fs_dl_sample_files();
	$method  = get_option( 'woocommerce_file_download_method', 'force' );
	?>

	<h2 style="margin-top:24px">تحویل فایل</h2>
	<p class="description" style="max-width:760px">
		وقتی فایل‌ها روی زیردامنه باشند، ووکامرس آن‌ها را «فایل دور» می‌بیند و ممکن است
		به‌جای دانلود، مرورگر را به همان زیردامنه بفرستد. آنجا هدر <code dir="ltr">Content-Disposition</code>
		وجود ندارد، پس مرورگر PDF و Word را <strong>باز می‌کند</strong> نه دانلود —
		دقیقاً همان تفاوتی که با فایل‌های zip می‌بینید.
	</p>
	<p class="description" style="max-width:760px">
		اگر زیردامنه روی همین سرور باشد، قالب نشانی را به مسیر واقعی فایل روی دیسک ترجمه
		می‌کند؛ آن‌وقت ووکامرس یک فایل محلی می‌خواند: سریع، و با هدر درست.
	</p>

	<table class="widefat striped" style="max-width:620px;margin:16px 0">
		<tbody>
			<tr>
				<th style="width:190px">روش دانلود ووکامرس</th>
				<td><code dir="ltr"><?php echo esc_html( $method ); ?></code></td>
			</tr>
			<tr>
				<th>allow_url_fopen</th>
				<td><?php echo ini_get( 'allow_url_fopen' ) ? 'روشن' : '<strong>خاموش</strong> — بدون ترجمه‌ی مسیر، دانلود به تغییر مسیر می‌افتد'; ?></td>
			</tr>
			<tr>
				<th>ریشه‌ی سایت</th>
				<td><code dir="ltr" style="direction:ltr"><?php echo esc_html( untrailingslashit( ABSPATH ) ); ?></code></td>
			</tr>
		</tbody>
	</table>

	<h3>نگاشت دستی</h3>
	<p class="description" style="max-width:760px">
		قالب خودش چند چیدمان رایج را امتحان می‌کند. اگر جدول پایین نشان داد که فایلی پیدا
		نشده، نشانی و مسیر را دستی بدهید.
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'fs_dl_save' ); ?>
		<input type="hidden" name="action" value="fs_dl_save">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="fs-dl-url">نشانی پایه</label></th>
				<td>
					<input class="regular-text ltr" id="fs-dl-url" type="url" name="base_url" dir="ltr"
						placeholder="https://dl.luxu.ir/" value="<?php echo esc_attr( $settings['base_url'] ); ?>">
					<p class="description">همان ابتدای نشانی فایل‌ها.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="fs-dl-path">مسیر پایه روی سرور</label></th>
				<td>
					<input class="regular-text ltr" id="fs-dl-path" type="text" name="base_path" dir="ltr"
						placeholder="/home/user/domains/dl.luxu.ir/public_html" value="<?php echo esc_attr( $settings['base_path'] ); ?>">
					<p class="description">پوشه‌ای که نشانی بالا به آن اشاره می‌کند. در دایرکت‌ادمین از «مدیر فایل» قابل دیدن است.</p>
				</td>
			</tr>
		</table>

		<?php submit_button( 'ذخیره' ); ?>
	</form>

	<h3>نتیجه‌ی ترجمه روی فایل‌های واقعی</h3>

	<?php if ( ! $samples ) : ?>
		<p class="description">هنوز محصول دانلودی با فایل ثبت‌شده پیدا نشد.</p>
	<?php else : ?>
		<table class="widefat striped" style="max-width:900px">
			<thead>
				<tr><th style="width:240px">فایل</th><th>نتیجه</th></tr>
			</thead>
			<tbody>
				<?php foreach ( $samples as $sample ) : ?>
					<tr>
						<td><?php echo esc_html( $sample['name'] ); ?></td>
						<td dir="ltr" style="direction:ltr;text-align:left">
							<?php if ( $sample['local'] ) : ?>
								<span style="color:#008a20">✓</span> <code><?php echo esc_html( $sample['local'] ); ?></code>
							<?php else : ?>
								<span style="color:#d63638">✗</span>
								<code><?php echo esc_html( $sample['url'] ); ?></code>
								<div style="color:#d63638;direction:rtl;text-align:right;margin-top:4px">روی این سرور پیدا نشد — نگاشت دستی لازم است.</div>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<div class="notice notice-info inline" style="max-width:900px;margin-top:20px">
		<p>
			<strong>اگر زیردامنه روی سرور دیگری است،</strong> هیچ کد قالبی نمی‌تواند از دوبار
			جابه‌جا شدن فایل جلوگیری کند. در آن حالت این را در فایل
			<code dir="ltr">.htaccess</code> پوشه‌ی فایل‌های زیردامنه بگذارید تا مرورگر
			به‌جای بازکردن، دانلود کند:
		</p>
		<pre dir="ltr" style="background:#fff;border:1px solid #dcdcde;padding:12px;white-space:pre-wrap;text-align:left">&lt;FilesMatch "\.(pdf|doc|docx|ppt|pptx|xls|xlsx|txt|rtf)$"&gt;
    Header set Content-Disposition "attachment"
&lt;/FilesMatch&gt;</pre>
	</div>
	<?php
}
