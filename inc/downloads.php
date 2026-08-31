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
function fs_the_download_button( $url, $label = 'دانلود', $class = 'fs-ditem__btn' ) {
	$token = fs_dl_token();
	?>
	<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( fs_dl_tag_url( $url, $token ) ); ?>"
		data-download="<?php echo esc_attr( $token ); ?>" rel="nofollow">
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
