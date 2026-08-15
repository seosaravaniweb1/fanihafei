<?php
/**
 * توابع کمکی قالب: آیکون‌های SVG، اعداد فارسی و رنگ‌بندی دسته‌ها.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

/**
 * پالت رنگ آیکون دسته‌ها — دقیقاً همان TINTS طرح اصلی.
 *
 * @return array<int, array{0:string,1:string}>
 */
function fs_tints() {
	return array(
		array( 'rgba(16,185,129,.12)', '#059669' ),
		array( 'rgba(249,115,22,.12)', '#ea580c' ),
		array( 'rgba(59,130,246,.12)', '#2563eb' ),
		array( 'rgba(168,85,247,.12)', '#7c3aed' ),
		array( 'rgba(236,72,153,.12)', '#db2777' ),
		array( 'rgba(20,184,166,.12)', '#0d9488' ),
	);
}

/**
 * گرادیان‌های تصویر شاخص — دقیقاً همان GRADS طرح اصلی.
 *
 * @return string[]
 */
function fs_grads() {
	return array(
		'linear-gradient(135deg,#e0f2fe,#f0fdfa)',
		'linear-gradient(135deg,#fef3c7,#ffedd5)',
		'linear-gradient(135deg,#ede9fe,#faf5ff)',
		'linear-gradient(135deg,#dcfce7,#f0fdf4)',
		'linear-gradient(135deg,#fce7f3,#fef2f8)',
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
 * تبدیل ارقام درون HTML بدون دست‌زدن به تگ‌ها، اتریبیوت‌ها و موجودیت‌های HTML.
 *
 * موجودیت‌ها هم باید دست‌نخورده بمانند: نماد واحد پول «تومان» در ووکامرس به شکل
 * &#x062A;&#x0648;&#x0645;&#x0627;&#x0646; ذخیره شده است. اگر ارقام داخل همین
 * موجودیت‌ها فارسی شوند، موجودیت خراب می‌شود و مرورگر به‌جای «تومان» خودِ کد را
 * روی صفحه چاپ می‌کند.
 *
 * @param string $html رشته HTML.
 * @return string
 */
function fs_fa_num_html( $html ) {
	$parts = preg_split(
		'/(<[^>]*>|&(?:#[0-9]+|#[xX][0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]{1,30});)/',
		(string) $html,
		-1,
		PREG_SPLIT_DELIM_CAPTURE
	);

	if ( ! is_array( $parts ) ) {
		return $html;
	}

	foreach ( $parts as $i => $part ) {
		if ( '' === $part || '<' === $part[0] || '&' === $part[0] ) {
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
		'phone'       => '<path d="M21 16.9v2.5a2 2 0 0 1-2.2 2 19.6 19.6 0 0 1-8.5-3 19.3 19.3 0 0 1-6-6 19.6 19.6 0 0 1-3-8.6A2 2 0 0 1 3.3 2h2.5a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L6.9 9.8a16 16 0 0 0 6 6l1.2-1.1a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"></path>',
		'send'        => '<path d="M21.5 2.5 10.5 13.5"></path><path d="M21.5 2.5 14.5 21.5l-4-8-8-4z"></path>',
		'chat'        => '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.9 8.9 0 0 1-3.8-.9L3 20.5l1.6-4.9A8.4 8.4 0 0 1 12 3.1a8.4 8.4 0 0 1 9 8.4z"></path>',
		'mail'        => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3.5 6.5 8.5 6 8.5-6"></path>',
		'headset'     => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"></path><path d="M4 14h2.5a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1z"></path><path d="M20 14h-2.5a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1H19a1 1 0 0 0 1-1z"></path>',
		'instagram'   => '<rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><path d="M17.5 6.5h.01"></path>',
		'map'         => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"></path><circle cx="12" cy="10" r="3"></circle>',
		'book-open'   => '<path d="M12 6.5C10.5 5 8.5 4.5 4 4.5v13c4.5 0 6.5.5 8 2 1.5-1.5 3.5-2 8-2v-13c-4.5 0-6.5.5-8 2z"></path><path d="M12 6.5v13"></path>',
		'shield'      => '<path d="M12 2.5 4.5 6v6c0 4.6 3.1 8.4 7.5 9.5 4.4-1.1 7.5-4.9 7.5-9.5V6z"></path><path d="m9 12 2 2 4-4"></path>',
		'star'        => '<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6-5.4-2.8-5.4 2.8 1-6L3.2 9.4l6.1-.9z"></path>',
		'infinity'    => '<path d="M7 15.5a3.5 3.5 0 1 1 0-7c3 0 4 7 7 7a3.5 3.5 0 0 0 0-7c-3 0-4 7-7 7z"></path>',
		'layers'      => '<path d="m12 3 9 5-9 5-9-5z"></path><path d="m3 13 9 5 9-5"></path>',
		'hash'        => '<path d="M5 9h14"></path><path d="M5 15h14"></path><path d="M10 4 8.5 20"></path><path d="M15.5 4 14 20"></path>',
		'eye'         => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"></path><circle cx="12" cy="12" r="3"></circle>',
		'calendar'    => '<rect x="3.5" y="5" width="17" height="16" rx="2"></rect><path d="M3.5 10h17"></path><path d="M8 3v4"></path><path d="M16 3v4"></path>',
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
