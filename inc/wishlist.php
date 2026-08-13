<?php
/**
 * علاقه‌مندی‌ها (ذخیره‌شده‌ها) — قابلیت واقعی، نه عدد نمایشی.
 *
 * طرح پیشخوان کاربری یک کارت «ذخیره‌شده‌ها» دارد؛ برای اینکه آن عدد واقعی
 * باشد، یک سیستم ساده‌ی نشان‌کردن محصول ساخته شده که با کلیک روی قلب هر
 * کارت، شناسه‌ی محصول در متای کاربر ذخیره می‌شود.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

const FS_WISHLIST_META = '_fs_wishlist';

/**
 * فهرست شناسه محصولات ذخیره‌شده‌ی یک کاربر — فقط محصولات هنوز منتشرشده.
 *
 * @param int $user_id شناسه کاربر.
 * @return int[]
 */
function fs_get_wishlist( $user_id ) {
	if ( ! $user_id ) {
		return array();
	}

	$ids = get_user_meta( $user_id, FS_WISHLIST_META, true );
	$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();

	if ( ! $ids ) {
		return array();
	}

	$valid = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'post__in'       => $ids,
			'orderby'        => 'post__in',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	return $valid;
}

/**
 * آیا محصول برای کاربر جاری ذخیره شده است؟
 *
 * @param int $product_id شناسه محصول.
 * @return bool
 */
function fs_is_wishlisted( $product_id ) {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	return in_array( (int) $product_id, fs_get_wishlist( get_current_user_id() ), true );
}

/**
 * افزودن/حذف یک محصول از علاقه‌مندی‌ها.
 *
 * @return void
 */
function fs_ajax_wishlist_toggle() {
	check_ajax_referer( 'fs_auth', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'برای ذخیره باید وارد حساب شوید.', 'needsLogin' => true ), 401 );
	}

	$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

	if ( ! $product_id || ! get_post( $product_id ) ) {
		wp_send_json_error( array( 'message' => 'محصول پیدا نشد.' ), 404 );
	}

	$user_id = get_current_user_id();
	$ids     = fs_get_wishlist( $user_id );
	$key     = array_search( $product_id, $ids, true );

	if ( false !== $key ) {
		unset( $ids[ $key ] );
		$saved = false;
	} else {
		$ids[] = $product_id;
		$saved = true;
	}

	update_user_meta( $user_id, FS_WISHLIST_META, array_values( $ids ) );

	wp_send_json_success(
		array(
			'saved' => $saved,
			'count' => count( $ids ),
		)
	);
}
add_action( 'wp_ajax_fs_wishlist_toggle', 'fs_ajax_wishlist_toggle' );

/**
 * دکمه‌ی قلب برای کارت محصول.
 *
 * @param int $product_id شناسه محصول.
 * @return void
 */
function fs_the_wishlist_button( $product_id ) {
	$on = fs_is_wishlisted( $product_id );
	?>
	<button class="fs-heart<?php echo $on ? ' is-on' : ''; ?>" type="button" data-wishlist data-id="<?php echo (int) $product_id; ?>"
		aria-pressed="<?php echo $on ? 'true' : 'false'; ?>" aria-label="افزودن به علاقه‌مندی‌ها">
		<svg width="15" height="15" viewBox="0 0 24 24" fill="<?php echo $on ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"></path>
		</svg>
	</button>
	<?php
}

/**
 * افزودن مسیر «ذخیره‌شده‌ها» به پیشخوان کاربر.
 *
 * @param array $items آیتم‌ها.
 * @return array
 */
function fs_account_menu_wishlist( $items ) {
	$new = array();

	foreach ( $items as $key => $label ) {
		$new[ $key ] = $label;

		if ( 'downloads' === $key ) {
			$new['wishlist'] = 'ذخیره‌شده‌ها';
		}
	}

	return $new;
}
add_filter( 'woocommerce_account_menu_items', 'fs_account_menu_wishlist', 21 );

/**
 * ثبت نقطه‌ی پایانی «ذخیره‌شده‌ها».
 *
 * @return void
 */
function fs_add_wishlist_endpoint() {
	add_rewrite_endpoint( 'wishlist', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'fs_add_wishlist_endpoint' );

/**
 * افزودن به فهرست کوئری‌وارهای ووکامرس.
 *
 * @param array $vars متغیرها.
 * @return array
 */
function fs_wishlist_query_var( $vars ) {
	$vars['wishlist'] = 'wishlist';

	return $vars;
}
add_filter( 'woocommerce_get_query_vars', 'fs_wishlist_query_var' );

/**
 * محتوای صفحه‌ی «ذخیره‌شده‌ها».
 *
 * @return void
 */
function fs_wishlist_endpoint_content() {
	$ids = fs_get_wishlist( get_current_user_id() );

	if ( ! $ids ) {
		echo '<p class="fs-empty">هنوز چیزی ذخیره نکرده‌اید. روی نشان قلب کنار هر فایل بزنید تا اینجا اضافه شود.</p>';

		return;
	}
	?>
	<div class="fs-cgrid fs-cgrid--account">
		<?php
		foreach ( $ids as $fs_i => $fs_pid ) {
			$fs_p = wc_get_product( $fs_pid );

			if ( ! $fs_p ) {
				continue;
			}

			get_template_part(
				'template-parts/card/grid',
				null,
				array(
					'product' => $fs_p,
					'index'   => $fs_i,
				)
			);
		}
		?>
	</div>
	<?php
}
add_action( 'woocommerce_account_wishlist_endpoint', 'fs_wishlist_endpoint_content' );
