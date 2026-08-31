<?php
/**
 * راه‌اندازی قالب لوکسو فایل.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

define( 'FS_VERSION', '2.4.0' );

require_once get_theme_file_path( 'inc/helpers.php' );
require_once get_theme_file_path( 'inc/jalali.php' );
require_once get_theme_file_path( 'inc/theme-data.php' );
require_once get_theme_file_path( 'inc/data.php' );
require_once get_theme_file_path( 'inc/theme-settings.php' );
require_once get_theme_file_path( 'inc/trust-settings.php' );
require_once get_theme_file_path( 'inc/footer-settings.php' );
require_once get_theme_file_path( 'inc/pages.php' );
require_once get_theme_file_path( 'inc/woocommerce.php' );
require_once get_theme_file_path( 'inc/seo.php' );
require_once get_theme_file_path( 'inc/auth.php' );
require_once get_theme_file_path( 'inc/auth-guard.php' );
require_once get_theme_file_path( 'inc/sms.php' );
require_once get_theme_file_path( 'inc/downloads.php' );
require_once get_theme_file_path( 'inc/search.php' );
require_once get_theme_file_path( 'inc/cart.php' );
require_once get_theme_file_path( 'inc/checkout.php' );
require_once get_theme_file_path( 'inc/checkout-repair.php' );
require_once get_theme_file_path( 'inc/account.php' );
require_once get_theme_file_path( 'inc/wishlist.php' );

/**
 * بازنویسی مسیرها پس از فعال‌سازی قالب — لازم برای نقطه‌ی پایانی «ذخیره‌شده‌ها».
 *
 * @return void
 */
function fs_flush_rewrites() {
	fs_add_wishlist_endpoint();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'fs_flush_rewrites' );

/**
 * پشتیبانی‌های قالب.
 *
 * @return void
 */
function fs_setup() {
	load_theme_textdomain( 'si-file', get_theme_file_path( 'languages' ) );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );

	// لوگوی سربرگ و پاورقی از «سفارشی‌سازی ← هویت سایت» خوانده می‌شود.
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 72,
			'width'       => 320,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_theme_support(
		'html5',
		array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);

	// ووکامرس.
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus(
		array(
			'primary'    => 'منوی سربرگ (کنار دسته‌بندی‌ها و منوی موبایل)',
			'footer-1'   => 'پاورقی — ستون اول',
			'footer-2'   => 'پاورقی — ستون دوم',
			'footer-3'   => 'پاورقی — ستون سوم',
			'footer-seo' => 'پاورقی — لینک‌های سئو',
		)
	);
}
add_action( 'after_setup_theme', 'fs_setup' );

/**
 * بارگذاری استایل‌ها و اسکریپت‌ها.
 *
 * @return void
 */
function fs_assets() {
	/*
	 * فونت از روی همین دامنه سرو می‌شود، نه فونت‌های گوگل.
	 *
	 * چرا: درخواست به fonts.googleapis.com از داخل ایران هم تأخیر DNS و مسیریابی
	 * دارد و هم اغلب اصلاً برنمی‌گردد. استایلی که آنجا لود می‌شد render-blocking
	 * بود، یعنی مرورگر تا تعیین‌تکلیفش هیچ چیزی رنگ نمی‌کرد و LCP قربانی می‌شد.
	 * حالا یک فایل متغیر (وزن ۱۰۰ تا ۹۰۰) روی خود سرور است: یک درخواست، بدون
	 * DNS خارجی، و با preload قبل از رسیدن به CSS شروع به دانلود می‌کند.
	 */
	wp_enqueue_style( 'fs-style', get_stylesheet_uri(), array(), FS_VERSION );

	wp_enqueue_script(
		'fs-main',
		get_theme_file_uri( 'assets/js/main.js' ),
		array(),
		FS_VERSION,
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	wp_localize_script(
		'fs-main',
		'fsData',
		array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'fs_auth' ),
			'cartNonce'   => wp_create_nonce( 'fs_cart' ),
			'loginUrl'    => fs_account_url(),
			'checkoutUrl' => fs_has_woo() ? wc_get_checkout_url() : '',
			'shopUrl'     => fs_has_woo() ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' ),
			'otpLength'   => fs_otp_length(),
			'captcha'     => fs_captcha_config(),
		)
	);

	// اسکریپت کپچا فقط روی صفحه‌ی ورود بارگذاری می‌شود؛ روی بقیه‌ی صفحه‌ها یک
	// درخواست اضافه به گوگل است بی‌آنکه به کاری بیاید.
	/*
	 * اسکریپت پاسخ‌دادن وردپرس. بدون آن، لینک «پاسخ» فرم را زیر دیدگاه نمی‌برد
	 * و comment_parent خالی می‌ماند؛ یعنی هر پاسخ به‌شکل یک دیدگاه تازه ثبت
	 * می‌شد و هیچ‌وقت زیر نظر کاربر دیده نمی‌شد.
	 */
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	$captcha = fs_is_auth_screen() ? fs_captcha_config() : null;

	if ( $captcha ) {
		wp_enqueue_script( 'fs-captcha', $captcha['script'], array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}
}
add_action( 'wp_enqueue_scripts', 'fs_assets' );

/**
 * پیش‌بارگذاری فونت متغیر قالب.
 *
 * چرا این‌قدر زود: مرورگر فونت را تازه وقتی کشف می‌کند که CSS را گرفته و
 * پارس کرده باشد؛ آن‌وقت یک رفت‌وبرگشت دیگر لازم است و متن تا آمدن فونت با
 * فونت جایگزین رندر می‌شود (پرش چیدمان و LCP دیرتر). با preload، دانلود فونت
 * هم‌زمان با CSS شروع می‌شود.
 *
 * نکته: هرچند فایل روی همان دامنه است، اتریبیوت crossorigin برای فونت الزامی
 * است — درخواست فونت همیشه در حالت CORS انجام می‌شود و بدون آن مرورگر فایل را
 * دوباره دانلود می‌کند.
 *
 * @return void
 */
function fs_preload_font() {
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( fs_font_url() )
	);
}
add_action( 'wp_head', 'fs_preload_font', 1 );

/**
 * نشانی فایل فونت — یک جا تعریف شده تا preload و ‎@font-face‎ هرگز از هم جدا نیفتند.
 *
 * @return string
 */
function fs_font_url() {
	return add_query_arg( 'v', FS_VERSION, get_theme_file_uri( 'assets/fonts/vazirmatn-var.woff2' ) );
}

/**
 * تعریف ‎@font-face‎ به‌صورت درون‌خطی، پیش از استایل اصلی.
 *
 * چرا درون‌خطی: اگر داخل style.css بماند، مرورگر باید اول کل شیت را بگیرد.
 * چند خط درون‌خطی یعنی تعریف فونت از همان بایت‌های اول HTML در دسترس است.
 * font-display:swap هم جلوی متن نامرئی (FOIT) را می‌گیرد.
 *
 * @return void
 */
function fs_font_face() {
	printf(
		'<style id="fs-font-face">@font-face{font-family:Vazirmatn;src:url(%s) format("woff2-variations");font-weight:100 900;font-style:normal;font-display:swap;}</style>' . "\n",
		esc_url( fs_font_url() )
	);
}
add_action( 'wp_head', 'fs_font_face', 2 );

/**
 * سبک‌سازی خروجی: حذف اموجی، نسخه وردپرس و استایل‌های بلااستفاده.
 *
 * @return void
 */
function fs_trim_head() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
}
add_action( 'init', 'fs_trim_head' );

/**
 * حذف استایل‌ها و اسکریپت‌های بلااستفاده‌ی هسته و ووکامرس از فرانت.
 *
 * چرا: قالب استایل کامل خودش را دارد و هیچ‌کدام از این شیت‌ها استفاده نمی‌شوند،
 * ولی هر کدام یک درخواست render-blocking اضافه می‌کنند. نسخه‌ی قبلی این تابع
 * روی is_singular() زودهنگام برمی‌گشت — یعنی دقیقاً روی صفحه‌ی محصول و نوشته،
 * که مهم‌ترین صفحات ورودی گوگل‌اند، همه‌ی این‌ها لود می‌ماندند.
 *
 * برای اینکه محتوایی که واقعاً با بلوک‌های گوتنبرگ ساخته شده نشکند، شیت بلوک‌ها
 * فقط وقتی حذف می‌شود که متن همان صفحه هیچ بلوکی نداشته باشد.
 *
 * @return void
 */
function fs_dequeue_bloat() {
	if ( is_admin() ) {
		return;
	}

	// استایل‌هایی که قالب هیچ‌جا از آن‌ها استفاده نمی‌کند.
	foreach ( array( 'classic-theme-styles', 'global-styles', 'wp-block-library-theme' ) as $handle ) {
		wp_dequeue_style( $handle );
		wp_deregister_style( $handle );
	}

	// شیت بلوک‌ها فقط وقتی محتوای صفحه بلوکی نباشد.
	if ( ! fs_page_has_blocks() ) {
		foreach ( array( 'wp-block-library', 'wc-blocks-style', 'wc-blocks-packages-style' ) as $handle ) {
			wp_dequeue_style( $handle );
		}
	}

	// اموجی هسته: یک اسکریپت و یک شیت که این قالب لازمشان ندارد.
	wp_dequeue_style( 'wp-emoji-styles' );
}
add_action( 'wp_enqueue_scripts', 'fs_dequeue_bloat', 100 );

/**
 * آیا محتوای همین درخواست بلوک گوتنبرگ دارد؟
 *
 * @return bool
 */
function fs_page_has_blocks() {
	if ( ! is_singular() || ! function_exists( 'has_blocks' ) ) {
		return false;
	}

	return has_blocks( get_queried_object_id() );
}

/**
 * اسکریپت‌های سبد ووکامرس که این قالب لازم ندارد.
 *
 * wc-add-to-cart: قالب خودش افزودن به سبد را با اجاکس انجام می‌دهد؛ اگر هر دو
 * فعال باشند هر کلیک دو بار پردازش می‌شود.
 *
 * wc-cart-fragments: روی هر بارگذاری صفحه یک درخواست admin-ajax می‌زند تا
 * شمارنده‌ی سبد را تازه کند. این درخواست هم کش صفحه را بی‌اثر می‌کند و هم روی
 * INP اثر مستقیم دارد، چون در همان لحظه‌ی تعامل اولیه‌ی کاربر نخ اصلی را
 * مشغول می‌کند. قالب شمارنده را سمت سرور رندر می‌کند و فقط جایی که واقعاً
 * لازم است (سبد و تسویه‌حساب) اجازه می‌دهد قطعه‌ها بمانند.
 *
 * @return void
 */
function fs_dequeue_woo_scripts() {
	wp_dequeue_script( 'wc-add-to-cart' );

	$needs_fragments = fs_has_woo() && ( is_cart() || is_checkout() );

	if ( ! $needs_fragments ) {
		wp_dequeue_script( 'wc-cart-fragments' );
	}
}
add_action( 'wp_enqueue_scripts', 'fs_dequeue_woo_scripts', 100 );

/**
 * سرعت نوار متحرک به‌صورت متغیر CSS — معادل prop مقدار `mqSpeed` در طرح.
 *
 * @return void
 */
function fs_inline_vars() {
	$speed = (int) apply_filters( 'fs_marquee_speed', 26 );
	$speed = max( 8, min( 60, $speed ) );

	wp_add_inline_style( 'fs-style', sprintf( ':root{--fs-mq-speed:%ds}', $speed ) );
}
add_action( 'wp_enqueue_scripts', 'fs_inline_vars', 20 );

/**
 * تازه‌کردن کش دسته‌بندی‌ها و شمارنده‌هایشان.
 *
 * با یک واحد بالابردن شماره‌ی نسخه، همه‌ی کلیدهای قبلی بی‌اثر می‌شوند؛ خودشان
 * هم حداکثر یک ساعت بعد منقضی می‌شوند. علاوه بر تغییر دسته‌ها، انتشار یا حذف
 * محصول هم باید کش را تازه کند وگرنه شمارنده‌ها عقب می‌مانند.
 *
 * @return void
 */
function fs_flush_cat_cache() {
	update_option( 'fs_cat_cache_v', fs_cat_cache_version() + 1, false );
}
add_action( 'edited_product_cat', 'fs_flush_cat_cache' );
add_action( 'created_product_cat', 'fs_flush_cat_cache' );
add_action( 'delete_product_cat', 'fs_flush_cat_cache' );

/**
 * همان تازه‌سازی، وقتی وضعیت یک محصول عوض می‌شود.
 *
 * @param string  $new    وضعیت جدید.
 * @param string  $old    وضعیت قبلی.
 * @param WP_Post $post   نوشته.
 * @return void
 */
function fs_flush_cat_cache_on_product( $new, $old, $post ) {
	if ( $post instanceof WP_Post && 'product' === $post->post_type && $new !== $old ) {
		fs_flush_cat_cache();
	}
}
add_action( 'transition_post_status', 'fs_flush_cat_cache_on_product', 10, 3 );

/**
 * لینک‌های یک محل منو. اگر منویی تعیین نشده باشد آرایه‌ی خالی برمی‌گردد
 * و بخش مربوطه رندر نمی‌شود.
 *
 * @param string $location محل منو.
 * @return array<int, array{title:string,url:string}>
 */
function fs_menu_items( $location ) {
	$locations = get_nav_menu_locations();

	if ( empty( $locations[ (string) $location ] ) ) {
		return array();
	}

	$menu_items = wp_get_nav_menu_items( $locations[ (string) $location ] );

	if ( ! $menu_items ) {
		return array();
	}

	$items = array();

	foreach ( $menu_items as $menu_item ) {
		if ( (int) $menu_item->menu_item_parent ) {
			continue;
		}

		$items[] = array(
			'title' => $menu_item->title,
			'url'   => $menu_item->url,
		);
	}

	return $items;
}

/**
 * نام منوی یک محل — به‌عنوان عنوان ستون پاورقی استفاده می‌شود.
 *
 * @param string $location محل منو.
 * @return string
 */
function fs_menu_name( $location ) {
	$locations = get_nav_menu_locations();

	if ( empty( $locations[ (string) $location ] ) ) {
		return '';
	}

	$menu = wp_get_nav_menu_object( $locations[ (string) $location ] );

	return $menu ? $menu->name : '';
}

/**
 * لوگوی سایت — از «سفارشی‌سازی ← هویت سایت». اگر لوگویی تنظیم نشده باشد،
 * نام سایت با نشان پیش‌فرض نمایش داده می‌شود.
 *
 * @param string $variant 'header' یا 'footer'.
 * @return void
 */
function fs_the_logo( $variant = 'header' ) {
	$is_footer = 'footer' === $variant;
	$prefix    = $is_footer ? 'fs-footer__' : 'fs-brand__';
	$logo_id   = (int) get_theme_mod( 'custom_logo' );

	echo '<a class="' . esc_attr( $is_footer ? 'fs-footer__brand' : 'fs-brand' ) . '" href="' . esc_url( home_url( '/' ) ) . '" rel="home">';

	if ( $logo_id ) {
		echo wp_get_attachment_image(
			$logo_id,
			'full',
			false,
			array(
				'class' => 'fs-logo fs-logo--' . esc_attr( $variant ),
				'alt'   => esc_attr( get_bloginfo( 'name' ) ),
			)
		);
	} else {
		echo '<span class="' . esc_attr( $prefix . 'mark' ) . '">' . fs_icon( 'book', $is_footer ? 17 : 19, array( 'stroke' => '#fff' ) ) . '</span>';
		echo '<span class="' . esc_attr( $prefix . 'name' ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
	}

	echo '</a>';
}

/**
 * نشانی امن برای لینک‌هایی که ممکن است خالی باشند.
 *
 * @param string $url نشانی.
 * @return string
 */
function fs_url( $url ) {
	if ( ! $url || is_wp_error( $url ) ) {
		return '#';
	}

	return $url;
}
