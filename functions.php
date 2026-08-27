<?php
/**
 * راه‌اندازی قالب لوکسو فایل.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

define( 'FS_VERSION', '2.0.0' );

require_once get_theme_file_path( 'inc/helpers.php' );
require_once get_theme_file_path( 'inc/jalali.php' );
require_once get_theme_file_path( 'inc/theme-data.php' );
require_once get_theme_file_path( 'inc/data.php' );
require_once get_theme_file_path( 'inc/theme-settings.php' );
require_once get_theme_file_path( 'inc/trust-settings.php' );
require_once get_theme_file_path( 'inc/woocommerce.php' );
require_once get_theme_file_path( 'inc/auth.php' );
require_once get_theme_file_path( 'inc/cart.php' );
require_once get_theme_file_path( 'inc/checkout.php' );
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
	// فونت وزیرمتن — همان وزن‌های طرح.
	wp_enqueue_style(
		'fs-vazirmatn',
		'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&display=swap',
		array(),
		null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- نسخه در URL گوگل مدیریت می‌شود.
	);

	wp_enqueue_style( 'fs-style', get_stylesheet_uri(), array( 'fs-vazirmatn' ), FS_VERSION );

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
		)
	);
}
add_action( 'wp_enqueue_scripts', 'fs_assets' );

/**
 * preconnect برای فونت گوگل — دقیقاً مثل طرح.
 *
 * @param array  $urls           نشانی‌ها.
 * @param string $relation_type  نوع رابطه.
 * @return array
 */
function fs_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array(
			'href' => 'https://fonts.googleapis.com',
		);
		$urls[] = array(
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		);
	}

	return $urls;
}
add_filter( 'wp_resource_hints', 'fs_resource_hints', 10, 2 );

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
 * حذف CSS بلوک‌های گوتنبرگ در فرانت — قالب استایل خودش را دارد.
 *
 * @return void
 */
function fs_dequeue_block_styles() {
	if ( is_admin() || is_singular() ) {
		return;
	}

	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wc-blocks-style' );
	wp_dequeue_style( 'classic-theme-styles' );
}
add_action( 'wp_enqueue_scripts', 'fs_dequeue_block_styles', 100 );

/**
 * اسکریپت افزودن‌به‌سبد ووکامرس لازم نیست — قالب خودش همین کار را با اجاکس
 * و کشوی سبد انجام می‌دهد و اگر هر دو فعال باشند محصول دوبار افزوده می‌شود.
 *
 * @return void
 */
function fs_dequeue_woo_add_to_cart() {
	wp_dequeue_script( 'wc-add-to-cart' );
	wp_dequeue_script( 'wc-cart-fragments' );
}
add_action( 'wp_enqueue_scripts', 'fs_dequeue_woo_add_to_cart', 100 );

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
