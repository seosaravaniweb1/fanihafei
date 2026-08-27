<?php
/**
 * تبدیل تاریخ میلادی به شمسی — بدون وابستگی به افزونه.
 *
 * برای «آخرین آپدیت» و «ویژه آزمون ...» که باید خودکار با ماه و سال جاری
 * هماهنگ بمانند.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

/**
 * نام ماه‌های شمسی.
 *
 * @return string[]
 */
function fs_jalali_months() {
	return array(
		'فروردین',
		'اردیبهشت',
		'خرداد',
		'تیر',
		'مرداد',
		'شهریور',
		'مهر',
		'آبان',
		'آذر',
		'دی',
		'بهمن',
		'اسفند',
	);
}

/**
 * تبدیل تاریخ میلادی به شمسی.
 *
 * @param int $gy سال میلادی.
 * @param int $gm ماه میلادی.
 * @param int $gd روز میلادی.
 * @return array{0:int,1:int,2:int} سال، ماه و روز شمسی.
 */
function fs_gregorian_to_jalali( $gy, $gm, $gd ) {
	$g_days_in_month = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

	$gy2 = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;

	$days = 355666
		+ ( 365 * $gy )
		+ (int) ( ( $gy2 + 3 ) / 4 )
		- (int) ( ( $gy2 + 99 ) / 100 )
		+ (int) ( ( $gy2 + 399 ) / 400 )
		+ $gd
		+ $g_days_in_month[ $gm - 1 ];

	$jy    = -1595 + ( 33 * (int) ( $days / 12053 ) );
	$days %= 12053;

	$jy   += 4 * (int) ( $days / 1461 );
	$days %= 1461;

	if ( $days > 365 ) {
		$jy  += (int) ( ( $days - 1 ) / 365 );
		$days = ( $days - 1 ) % 365;
	}

	if ( $days < 186 ) {
		$jm = 1 + (int) ( $days / 31 );
		$jd = 1 + ( $days % 31 );
	} else {
		$jm = 7 + (int) ( ( $days - 186 ) / 30 );
		$jd = 1 + ( ( $days - 186 ) % 30 );
	}

	return array( $jy, $jm, $jd );
}

/**
 * تاریخ شمسی امروز بر اساس زمان محلی سایت.
 *
 * @return array{0:int,1:int,2:int}
 */
function fs_jalali_today() {
	$now = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- زمان محلی سایت لازم است.

	return fs_gregorian_to_jalali(
		(int) gmdate( 'Y', $now ),
		(int) gmdate( 'n', $now ),
		(int) gmdate( 'j', $now )
	);
}

/**
 * سال شمسی جاری.
 *
 * @return int
 */
function fs_jalali_year() {
	$today = fs_jalali_today();

	return (int) $today[0];
}

/**
 * ماه و سال شمسی جاری — مثلاً «مرداد ۱۴۰۵».
 *
 * @return string
 */
function fs_jalali_month_year() {
	$today  = fs_jalali_today();
	$months = fs_jalali_months();

	return $months[ $today[1] - 1 ] . ' ' . fs_fa_num( $today[0] );
}

/**
 * ماه و سال شمسی یک زمان مشخص — مثلاً «خرداد ۱۴۰۴».
 *
 * @param int|string $timestamp مهر زمانی یا رشته‌ی تاریخ.
 * @return string
 */
function fs_jalali_month_year_of( $timestamp ) {
	if ( ! is_numeric( $timestamp ) ) {
		$timestamp = strtotime( (string) $timestamp );
	}

	$timestamp = (int) $timestamp;

	if ( $timestamp <= 0 ) {
		return '';
	}

	// زمان محلی سایت، نه UTC — وگرنه اول و آخر ماه یک روز جابه‌جا می‌شود.
	$local = $timestamp + (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );

	list( $jy, $jm ) = fs_gregorian_to_jalali(
		(int) gmdate( 'Y', $local ),
		(int) gmdate( 'n', $local ),
		(int) gmdate( 'j', $local )
	);

	$months = fs_jalali_months();

	return $months[ $jm - 1 ] . ' ' . fs_fa_num( $jy );
}

/**
 * تاریخ درج و بروزرسانی یک محصول — خودکار از خود وردپرس خوانده می‌شود.
 *
 * اگر محصول بعد از انتشار ویرایش نشده باشد (یا در همان ماه ویرایش شده باشد)
 * فقط تاریخ درج برگردانده می‌شود تا خط اضافه‌ی بی‌معنی نمایش داده نشود.
 *
 * @param int $product_id شناسه محصول.
 * @return array{published:string,updated:string}
 */
function fs_product_dates( $product_id ) {
	$published = get_post_time( 'U', false, $product_id );
	$modified  = get_post_modified_time( 'U', false, $product_id );

	$published_label = $published ? fs_jalali_month_year_of( $published ) : '';
	$updated_label   = $modified ? fs_jalali_month_year_of( $modified ) : '';

	if ( $updated_label === $published_label ) {
		$updated_label = '';
	}

	return array(
		'published' => $published_label,
		'updated'   => $updated_label,
	);
}
