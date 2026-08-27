<?php
/**
 * توابع کمکی قالب: آیکون‌های SVG، اعداد فارسی و رنگ‌بندی دسته‌ها.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

/**
 * پالت رنگ آیکون دسته‌ها — خانواده‌ی بنفش/نیلی هویت قالب.
 *
 * @return array<int, array{0:string,1:string}>
 */
function fs_tints() {
	return array(
		array( 'rgba(124,58,237,.12)', '#6d28d9' ),
		array( 'rgba(219,39,119,.12)', '#be185d' ),
		array( 'rgba(79,70,229,.12)', '#4338ca' ),
		array( 'rgba(192,38,211,.12)', '#a21caf' ),
		array( 'rgba(139,92,246,.14)', '#7c3aed' ),
		array( 'rgba(14,165,233,.12)', '#0369a1' ),
	);
}

/**
 * گرادیان‌های تصویر شاخص — هم‌خانواده با گرادیانت اصلی قالب.
 *
 * @return string[]
 */
function fs_grads() {
	return array(
		'linear-gradient(135deg,#ede9fe,#faf5ff)',
		'linear-gradient(135deg,#fce7f3,#fdf4ff)',
		'linear-gradient(135deg,#e0e7ff,#f5f3ff)',
		'linear-gradient(135deg,#f3e8ff,#fdf2f8)',
		'linear-gradient(135deg,#ddd6fe,#eef2ff)',
		'linear-gradient(135deg,#e2e8f0,#f8fafc)',
	);
}

/**
 * استایل درون‌خطی آیکون دسته بر اساس اندیس.
 *
 * @param int $i اندیس دسته.
 * @return string
 */
function fs_tint_style( $i ) {
	$tints = fs_tints();
	$tint  = $tints[ $i % count( $tints ) ];

	return sprintf( 'background:%s;color:%s', $tint[0], $tint[1] );
}

/**
 * گرادیان تصویر شاخص بر اساس اندیس.
 *
 * @param int $i اندیس آیتم.
 * @return string
 */
function fs_grad( $i ) {
	$grads = fs_grads();

	return $grads[ $i % count( $grads ) ];
}

/**
 * تبدیل ارقام لاتین به فارسی.
 *
 * @param string|int $value مقدار ورودی.
 * @return string
 */
function fs_fa_num( $value ) {
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

	return str_replace( $en, $fa, (string) $value );
}

/**
 * تبدیل ارقام فارسی و عربی به لاتین.
 *
 * @param string $value مقدار ورودی.
 * @return string
 */
function fs_en_num( $value ) {
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

	return str_replace( $ar, $en, str_replace( $fa, $en, (string) $value ) );
}

/**
 * تبدیل ارقام درون HTML بدون دست‌زدن به تگ‌ها و اتریبیوت‌ها.
 *
 * @param string $html رشته HTML.
 * @return string
 */
function fs_fa_num_html( $html ) {
	$parts = preg_split( '/(<[^>]*>)/', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE );

	if ( ! is_array( $parts ) ) {
		return $html;
	}

	foreach ( $parts as $i => $part ) {
		if ( '' === $part || '<' === $part[0] ) {
			continue;
		}
		$parts[ $i ] = fs_fa_num( $part );
	}

	return implode( '', $parts );
}

/**
 * مجموعه آیکون‌های SVG استفاده‌شده در طرح.
 *
 * توجه: در فایل طرح، اتریبیوت‌ها به شکل React نوشته شده بودند
 * (strokeWidth / strokeLinecap). اینجا به معادل معتبر HTML
 * (stroke-width / stroke-linecap) تبدیل شده‌اند تا مرورگر ضخامت خط را
 * درست رندر کند.
 *
 * @param string $name  نام آیکون.
 * @param int    $size  اندازه بر حسب پیکسل.
 * @param array  $args  آرگومان‌های اختیاری: stroke، width (ضخامت خط)، class.
 * @return string
 */
function fs_icon( $name, $size = 16, $args = array() ) {
	$stroke = isset( $args['stroke'] ) ? $args['stroke'] : 'currentColor';
	$sw     = isset( $args['width'] ) ? $args['width'] : '2';
	$class  = isset( $args['class'] ) ? $args['class'] : '';

	$paths = array(
		'book'        => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>',
		'search'      => '<circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path>',
		'cart'        => '<circle cx="9" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.5 3h2l2.6 12.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 7H6"></path>',
		'user'        => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
		'menu'        => '<path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path>',
		'menu-mobile' => '<path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h10"></path>',
		'chevron-down' => '<path d="m6 9 6 6 6-6"></path>',
		'chevron-prev' => '<path d="m14 6-6 6 6 6"></path>',
		'chevron-next' => '<path d="m10 6 6 6-6 6"></path>',
		'grid'        => '<path d="M4 4h7v7H4z"></path><path d="M13 4h7v7h-7z"></path><path d="M4 13h7v7H4z"></path><path d="M13 13h7v7h-7z"></path>',
		'download'    => '<path d="M12 3v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 21h14"></path>',
		'zap'         => '<path d="M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12z"></path>',
		'check'       => '<path d="m5 12.5 4.5 4.5L19 7"></path>',
		'file'        => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path>',
		'file-lines'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M9 13h6"></path><path d="M9 17h4"></path>',
		'plus'        => '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
		'clock'       => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path>',
		'trash'       => '<path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M6 7l1 13h10l1-13"></path><path d="M9 7V4h6v3"></path>',
		'trending'    => '<path d="m3 17 5-5 4 4 8-8"></path><path d="M16 8h4v4"></path>',
		'chat'        => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.9-.9L3 20.5l1.5-4.4A8.4 8.4 0 0 1 3.6 11.5 8.4 8.4 0 0 1 12 3.1a8.4 8.4 0 0 1 9 8.4z"></path>',
		'close'       => '<path d="M6 6 18 18"></path><path d="M18 6 6 18"></path>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return sprintf(
		'<svg width="%1$s" height="%1$s" viewBox="0 0 24 24" fill="none" stroke="%2$s" stroke-width="%3$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"%4$s>%5$s</svg>',
		esc_attr( $size ),
		esc_attr( $stroke ),
		esc_attr( $sw ),
		$class ? ' class="' . esc_attr( $class ) . '"' : '',
		$paths[ $name ]
	);
}

/**
 * چاپ آیکون.
 *
 * @param string $name نام آیکون.
 * @param int    $size اندازه.
 * @param array  $args آرگومان‌ها.
 * @return void
 */
function fs_the_icon( $name, $size = 16, $args = array() ) {
	echo fs_icon( $name, $size, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی SVG ثابت و کنترل‌شده است.
}

/**
 * آیکون هر ردیف جعبه‌ی «چرا ما؟» — چون متن ردیف‌ها از پنل مدیریت می‌آید،
 * آیکون‌ها به‌ترتیب و چرخشی انتخاب می‌شوند تا جعبه یکنواخت نشود.
 *
 * @param int $i اندیس ردیف.
 * @return string
 */
function fs_why_icon( $i ) {
	$icons = apply_filters( 'fs_why_icons', array( 'zap', 'check', 'download', 'clock', 'file-lines', 'trending' ) );

	return $icons[ $i % count( $icons ) ];
}
