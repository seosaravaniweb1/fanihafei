<?php
/**
 * پیشخوان کاربری.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

/**
 * حذف «نشانی‌ها» از منوی پیشخوان — این فروشگاه نشانی پستی ندارد.
 *
 * @param array $items آیتم‌ها.
 * @return array
 */
function fs_account_menu( $items ) {
	unset( $items['edit-address'] );

	$labels = array(
		'dashboard'       => 'پیشخوان',
		'orders'          => 'سفارش‌های من',
		'downloads'       => 'فایل‌های دانلودی',
		'edit-account'    => 'ویرایش مشخصات',
		'customer-logout' => 'خروج',
	);

	foreach ( $labels as $key => $label ) {
		if ( isset( $items[ $key ] ) ) {
			$items[ $key ] = $label;
		}
	}

	return $items;
}
add_filter( 'woocommerce_account_menu_items', 'fs_account_menu', 20 );

/**
 * حذف مسیر «نشانی‌ها».
 *
 * @param array $endpoints نقاط پایانی.
 * @return array
 */
function fs_account_endpoints( $endpoints ) {
	unset( $endpoints['edit-address'] );

	return $endpoints;
}
add_filter( 'woocommerce_get_query_vars', 'fs_account_endpoints', 20 );

/**
 * آیکون هر آیتم منوی پیشخوان.
 *
 * @param string $key کلید.
 * @return string
 */
function fs_account_icon( $key ) {
	$map = array(
		'dashboard'       => 'grid',
		'orders'          => 'file',
		'downloads'       => 'download',
		'wishlist'        => 'zap',
		'edit-account'    => 'user',
		'customer-logout' => 'chevron-prev',
	);

	return isset( $map[ $key ] ) ? $map[ $key ] : 'chevron-prev';
}

/**
 * آمار پیشخوان کاربر — سه کارت طرح: دانلودی، سفارش، ذخیره‌شده.
 *
 * @param int $user_id شناسه کاربر.
 * @return array<int, array{v:string,k:string,icon:string}>
 */
function fs_account_stats( $user_id ) {
	if ( ! fs_has_woo() ) {
		return array();
	}

	$downloads = wc_get_customer_available_downloads( $user_id );

	$orders = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'limit'       => -1,
			'return'      => 'ids',
		)
	);

	return array(
		array(
			'v'    => fs_fa_num( count( $downloads ) ),
			'k'    => 'فایل قابل دانلود',
			'icon' => 'download',
		),
		array(
			'v'    => fs_fa_num( count( $orders ) ),
			'k'    => 'سفارش‌ها',
			'icon' => 'file',
		),
		array(
			'v'    => fs_fa_num( count( fs_get_wishlist( $user_id ) ) ),
			'k'    => 'ذخیره‌شده‌ها',
			'icon' => 'zap',
		),
	);
}

/**
 * درصد تکمیل پروفایل.
 *
 * @param WP_User $user کاربر.
 * @return int
 */
function fs_profile_completeness( $user ) {
	// ایمیل عمداً شمرده نمی‌شود: خرید و دانلود با شماره موبایل انجام می‌شود و
	// کاربری که ایمیل ندارد نباید تا ابد جعبه‌ی «تکمیل پروفایل» را ببیند.
	$checks = array(
		(bool) $user->first_name,
		(bool) $user->last_name,
		(bool) get_user_meta( $user->ID, FS_PHONE_META, true ),
	);

	$done = count( array_filter( $checks ) );

	return (int) round( ( $done / count( $checks ) ) * 100 );
}

/**
 * ذخیره‌ی فرم تکمیل سریع پروفایل (پیشخوان و ویرایش مشخصات) با اجاکس.
 *
 * @return void
 */
function fs_ajax_update_profile() {
	check_ajax_referer( 'fs_auth', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'ابتدا وارد حساب شوید.' ), 401 );
	}

	$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
	$last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
	$phone = isset( $_POST['phone'] ) ? fs_normalize_phone( wp_unslash( $_POST['phone'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( ! $first ) {
		wp_send_json_error( array( 'message' => 'نام را وارد کنید.' ) );
	}

	if ( isset( $_POST['phone'] ) && wp_unslash( $_POST['phone'] ) && ! $phone ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		wp_send_json_error( array( 'message' => 'شماره موبایل درست نیست.' ) );
	}

	$user_id = get_current_user_id();

	if ( $email && is_email( $email ) ) {
		$existing = get_user_by( 'email', $email );

		if ( $existing && $existing->ID !== $user_id ) {
			wp_send_json_error( array( 'message' => 'این ایمیل قبلاً برای حساب دیگری ثبت شده است.' ) );
		}

		wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => $email,
			)
		);
	}

	wp_update_user(
		array(
			'ID'           => $user_id,
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => trim( $first . ' ' . $last ),
		)
	);

	fs_sync_user_billing( $user_id, $phone, $first, $last, $email );

	wp_send_json_success( array( 'message' => 'مشخصات ذخیره شد.' ) );
}
add_action( 'wp_ajax_fs_account_update_profile', 'fs_ajax_update_profile' );
