<?php
/**
 * راه‌اندازی قالب فنی‌سوال.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

define( 'FS_VERSION', '1.1.0' );

/**
 * نسخه‌ی یک فایل قالب بر پایه‌ی زمان آخرین تغییر آن.
 *
 * بدون این، مرورگر و افزونه‌های کش نسخه‌ی قدیمی style.css یا main.js را نگه
 * می‌دارند و تغییرهای تازه‌ی قالب اصلاً دیده نمی‌شوند.
 *
 * @param string $relative مسیر فایل نسبت به ریشه‌ی قالب.
 * @return string
 */
function fs_asset_version( $relative ) {
	$path = get_theme_file_path( $relative );

	if ( file_exists( $path ) ) {
		return FS_VERSION . '.' . filemtime( $path );
	}

	return FS_VERSION;
}

require_once get_theme_file_path( 'inc/helpers.php' );
require_once get_theme_file_path( 'inc/jalali.php' );
require_once get_theme_file_path( 'inc/theme-data.php' );
require_once get_theme_file_path( 'inc/data.php' );
require_once get_theme_file_path( 'inc/theme-settings.php' );
require_once get_theme_file_path( 'inc/trust-settings.php' );
require_once get_theme_file_path( 'inc/woocommerce.php' );
require_once get_theme_file_path( 'inc/auth.php' );
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
	load_theme_textdomain( 'fanni-soal', get_theme_file_path( 'languages' ) );

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
			'footer-1'   => 'پاورقی — لینک‌های مهم (ستون اول)',
			'footer-2'   => 'پاورقی — لینک‌های مهم (ستون دوم)',
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

	wp_enqueue_style( 'fs-style', get_stylesheet_uri(), array( 'fs-vazirmatn' ), fs_asset_version( 'style.css' ) );

	wp_enqueue_script(
		'fs-main',
		get_theme_file_uri( 'assets/js/main.js' ),
		array(),
		fs_asset_version( 'assets/js/main.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	wp_localize_script(
		'fs-main',
		'fsData',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'fs_auth' ),
			'loginUrl' => fs_account_url(),
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
 * پاک‌سازی کش دسته‌بندی‌ها هنگام تغییر ترم‌ها.
 *
 * @return void
 */
function fs_flush_cat_cache() {
	wp_cache_delete( 'fs_categories', 'fanni-soal' );
}
add_action( 'edited_product_cat', 'fs_flush_cat_cache' );
add_action( 'created_product_cat', 'fs_flush_cat_cache' );
add_action( 'delete_product_cat', 'fs_flush_cat_cache' );

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
 * مسیر راهنما (بردکرامب) با جداکننده‌ی درست.
 *
 * جداکننده‌ی خالی باعث می‌شد لینک‌ها به‌هم بچسبند و خوانده نشوند؛ اینجا هر
 * مرحله در یک span جدا و با یک شورون بین‌شان چاپ می‌شود.
 *
 * @return void
 */
function fs_the_breadcrumb() {
	if ( ! fs_has_woo() ) {
		return;
	}

	$crumbs = ( new WC_Breadcrumb() )->generate();

	if ( ! $crumbs ) {
		return;
	}

	$last = count( $crumbs ) - 1;

	echo '<nav class="fs-crumbs" aria-label="مسیر"><ol class="fs-crumbs__list">';

	foreach ( $crumbs as $i => $crumb ) {
		echo '<li class="fs-crumbs__item">';

		if ( ! empty( $crumb[1] ) && $i !== $last ) {
			printf( '<a href="%s">%s</a>', esc_url( $crumb[1] ), esc_html( $crumb[0] ) );
		} else {
			printf( '<span aria-current="page">%s</span>', esc_html( $crumb[0] ) );
		}

		if ( $i !== $last ) {
			echo '<span class="fs-crumbs__sep" aria-hidden="true">' . fs_icon( 'chevron-prev', 12, array( 'width' => '2.4' ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت قالب.
		}

		echo '</li>';
	}

	echo '</ol></nav>';
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
