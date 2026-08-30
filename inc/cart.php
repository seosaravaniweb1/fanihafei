<?php
/**
 * سبد خرید.
 *
 * کاربر می‌تواند چند فایل را کنار هم بگذارد و یک‌جا بپردازد. پس از هر افزودن،
 * سمت کاربر پرسیده می‌شود که به پرداخت برود یا فایل بیشتری اضافه کند؛ سرور
 * فقط فایل را اضافه می‌کند و وضعیت تازه‌ی سبد را برمی‌گرداند.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

/**
 * تعداد اقلام سبد خرید.
 *
 * @return int
 */
function fs_cart_count() {
	if ( ! fs_has_woo() || ! WC()->cart ) {
		return 0;
	}

	return (int) WC()->cart->get_cart_contents_count();
}

/**
 * مجموع مبلغ سبد خرید با ارقام فارسی.
 *
 * @return string
 */
function fs_cart_total() {
	if ( ! fs_has_woo() || ! WC()->cart ) {
		return '';
	}

	return fs_fa_num_html( WC()->cart->get_cart_subtotal() );
}

/**
 * اقلام سبد خرید برای رندر کشو.
 *
 * @return array<int, array>
 */
function fs_cart_items() {
	if ( ! fs_has_woo() || ! WC()->cart ) {
		return array();
	}

	$out = array();

	foreach ( WC()->cart->get_cart() as $key => $item ) {
		$product = isset( $item['data'] ) ? $item['data'] : null;

		if ( ! $product instanceof WC_Product ) {
			continue;
		}

		$id = $product->get_id();

		$out[] = array(
			'key'   => $key,
			'id'    => $id,
			'title' => $product->get_name(),
			'link'  => get_permalink( $id ),
			'qty'   => (int) $item['quantity'],
			'price' => fs_fa_num_html( wc_price( (float) WC()->cart->get_product_subtotal( $product, $item['quantity'] ) ) ),
			'thumb' => get_the_post_thumbnail( $id, 'thumbnail', array( 'loading' => 'lazy' ) ),
			'fmt'   => fs_product_format( $id ),
		);
	}

	return $out;
}

/**
 * بدنه‌ی کشوی سبد خرید — همین خروجی هم در بارگذاری اولیه و هم در پاسخ اجاکس
 * استفاده می‌شود تا یک منبع حقیقت بیشتر نداشته باشیم.
 *
 * @return string
 */
function fs_cart_body_html() {
	ob_start();
	get_template_part( 'template-parts/header/cart-body' );

	return (string) ob_get_clean();
}

/**
 * پاسخ استاندارد اجاکس سبد.
 *
 * @return array{count:int,total:string,body:string}
 */
function fs_cart_payload() {
	if ( fs_has_woo() && WC()->cart ) {
		WC()->cart->calculate_totals();
	}

	return array(
		'count' => fs_cart_count(),
		'total' => fs_cart_total(),
		'body'  => fs_cart_body_html(),
	);
}

/**
 * بررسی nonce مشترک درخواست‌های سبد.
 *
 * @return void
 */
function fs_cart_check_nonce() {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'fs_cart' ) ) {
		wp_send_json_error( array( 'message' => 'درخواست معتبر نیست. صفحه را تازه کنید.' ), 400 );
	}

	if ( ! fs_has_woo() || ! WC()->cart ) {
		wp_send_json_error( array( 'message' => 'سبد خرید در دسترس نیست.' ), 400 );
	}
}

/**
 * افزودن محصول به سبد.
 *
 * @return void
 */
function fs_ajax_cart_add() {
	fs_cart_check_nonce();

	$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

	if ( ! $product_id ) {
		wp_send_json_error( array( 'message' => 'محصول پیدا نشد.' ), 400 );
	}

	$product = wc_get_product( $product_id );

	if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		wp_send_json_error( array( 'message' => 'این فایل در حال حاضر قابل خرید نیست.' ), 400 );
	}

	// از هر فایل یک نسخه؛ اگر همین حالا در سبد باشد، دوباره اضافه نمی‌شود.
	$already = false;

	foreach ( WC()->cart->get_cart() as $fs_item ) {
		if ( isset( $fs_item['product_id'] ) && (int) $fs_item['product_id'] === $product_id ) {
			$already = true;

			break;
		}
	}

	$added = $already ? true : WC()->cart->add_to_cart( $product_id, 1 );

	if ( ! $added ) {
		$notice = wc_get_notices( 'error' );
		wc_clear_notices();

		wp_send_json_error(
			array( 'message' => $notice ? wp_strip_all_tags( $notice[0]['notice'] ) : 'خرید این فایل انجام نشد.' ),
			400
		);
	}

	wp_send_json_success(
		array_merge(
			fs_cart_payload(),
			array(
				'message'     => $already ? 'این فایل از قبل در سبد شماست.' : 'به سبد خرید اضافه شد.',
				'already'     => $already,
				'title'       => $product->get_name(),
				// برای انیمیشن پرواز به سبد: تصویر کوچک همین فایل.
				'thumb'       => (string) get_the_post_thumbnail_url( $product_id, 'thumbnail' ),
				'checkoutUrl' => wc_get_checkout_url(),
			)
		)
	);
}
add_action( 'wp_ajax_fs_cart_add', 'fs_ajax_cart_add' );
add_action( 'wp_ajax_nopriv_fs_cart_add', 'fs_ajax_cart_add' );

/**
 * حذف یک قلم از سبد.
 *
 * @return void
 */
function fs_ajax_cart_remove() {
	fs_cart_check_nonce();

	$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

	if ( ! $key || ! WC()->cart->get_cart_item( $key ) ) {
		wp_send_json_error( array( 'message' => 'این قلم در سبد نیست.' ), 400 );
	}

	WC()->cart->remove_cart_item( $key );

	wp_send_json_success(
		array_merge(
			fs_cart_payload(),
			array( 'message' => 'از سبد خرید حذف شد.' )
		)
	);
}
add_action( 'wp_ajax_fs_cart_remove', 'fs_ajax_cart_remove' );
add_action( 'wp_ajax_nopriv_fs_cart_remove', 'fs_ajax_cart_remove' );

/**
 * خواندن وضعیت سبد — بعد از بازگشت از کش مرورگر یا صفحه‌ی کش‌شده لازم است.
 *
 * @return void
 */
function fs_ajax_cart_fragment() {
	fs_cart_check_nonce();

	wp_send_json_success( fs_cart_payload() );
}
add_action( 'wp_ajax_fs_cart_fragment', 'fs_ajax_cart_fragment' );
add_action( 'wp_ajax_nopriv_fs_cart_fragment', 'fs_ajax_cart_fragment' );
