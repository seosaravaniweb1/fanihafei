<?php
/**
 * لایه داده — فقط داده‌ی واقعی سایت.
 *
 * هیچ محتوای نمونه‌ای وجود ندارد: اگر ووکامرس نصب نباشد یا محصول/دسته/نوشته‌ای
 * ثبت نشده باشد، توابع آرایه‌ی خالی برمی‌گردانند و بخش مربوطه رندر نمی‌شود.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

/**
 * آیا ووکامرس فعال است؟
 *
 * @return bool
 */
function fs_has_woo() {
	return class_exists( 'WooCommerce' );
}

/**
 * تعداد سوال یک محصول.
 *
 * @param int $product_id شناسه محصول.
 * @return string
 */
function fs_product_questions( $product_id ) {
	$key   = apply_filters( 'fs_questions_meta_key', 'question_count' );
	$value = get_post_meta( $product_id, $key, true );

	return $value ? fs_fa_num( $value ) : '';
}

/**
 * تعداد بازدید یک محصول.
 *
 * @param int $product_id شناسه محصول.
 * @return string
 */
function fs_product_views( $product_id ) {
	$key   = apply_filters( 'fs_views_meta_key', 'view_count' );
	$value = get_post_meta( $product_id, $key, true );

	return $value ? fs_fa_num( $value ) : '';
}

/**
 * یک فیلد اختصاصی محصول.
 *
 * @param int    $product_id شناسه محصول.
 * @param string $key        کلید متا.
 * @return string
 */
function fs_product_field( $product_id, $key ) {
	$value = get_post_meta( $product_id, $key, true );

	return $value ? (string) $value : '';
}

/**
 * قیمت نمایشی محصول — فقط قیمت نهایی (اگر تخفیف داشته باشد، فقط قیمت
 * تخفیف‌خورده)، بدون قیمت خط‌خورده‌ی قبلی کنارش.
 *
 * قیمت اصلی و درصد/مبلغ تخفیف هرجا لازم باشد جداگانه و با طراحی خودش
 * نمایش داده می‌شود (نگاه کنید به سایدبار خرید در single-product.php) تا
 * دو عدد قیمت به‌هم‌چسبیده و گیج‌کننده نشود.
 *
 * @param WC_Product $product شیء محصول.
 * @return string
 */
function fs_product_price( $product ) {
	return fs_fa_num_html( wc_price( wc_get_price_to_display( $product ) ) );
}

/**
 * تبدیل یک محصول ووکامرس به آرایه‌ی مورد نیاز کارت‌ها.
 *
 * @param WC_Product $product شیء محصول.
 * @param int        $index   اندیس برای انتخاب گرادیان پس‌زمینه.
 * @return array
 */
function fs_product_to_card( $product, $index = 0 ) {
	$id = $product->get_id();

	return array(
		'id'     => $id,
		'title'  => $product->get_name(),
		'link'   => get_permalink( $id ),
		'q'      => fs_product_questions( $id ),
		'price'  => fs_product_price( $product ),
		'views'  => fs_product_views( $id ),
		'thumb'  => get_the_post_thumbnail( $id, 'medium', array( 'loading' => 'lazy', 'decoding' => 'async' ) ),
		'grad'   => fs_grad( $index ),
		'badges' => fs_product_badges_line( $id ),
	);
}

/**
 * دسته‌بندی‌های محصول به‌همراه زیردسته‌ها.
 *
 * @return array<int, array{name:string,count:string,link:string,subs:array}>
 */
function fs_get_categories() {
	if ( ! fs_has_woo() ) {
		return array();
	}

	$cached = wp_cache_get( 'fs_categories', 'fanni-soal' );

	if ( false !== $cached ) {
		return $cached;
	}

	$out = array();

	$parents = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => false,
			'number'     => (int) apply_filters( 'fs_parent_cats_limit', 16 ),
		)
	);

	if ( ! is_wp_error( $parents ) && $parents ) {
		foreach ( $parents as $parent ) {
			$children = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'parent'     => $parent->term_id,
					'hide_empty' => false,
					'number'     => 8,
				)
			);

			$subs = array();

			if ( ! is_wp_error( $children ) && $children ) {
				foreach ( $children as $child ) {
					$subs[] = array(
						'name'  => $child->name,
						'count' => fs_fa_num( $child->count ),
						'link'  => get_term_link( $child ),
					);
				}
			}

			$out[] = array(
				'name'  => $parent->name,
				'count' => fs_fa_num( $parent->count ),
				'link'  => get_term_link( $parent ),
				'subs'  => $subs,
			);
		}
	}

	wp_cache_set( 'fs_categories', $out, 'fanni-soal', HOUR_IN_SECONDS );

	return $out;
}

/**
 * جدیدترین محصولات.
 *
 * @param int $limit تعداد.
 * @return array
 */
function fs_get_newest( $limit = 6 ) {
	if ( ! fs_has_woo() ) {
		return array();
	}

	$out = array();

	$products = wc_get_products(
		array(
			'status'  => 'publish',
			'limit'   => $limit,
			'orderby' => 'date',
			'order'   => 'DESC',
		)
	);

	foreach ( $products as $i => $product ) {
		$out[] = fs_product_to_card( $product, $i );
	}

	return $out;
}

/**
 * تب‌های بخش پربازدیدترین‌ها به‌همراه آیتم‌هایشان.
 *
 * @param int $tabs  تعداد تب.
 * @param int $items تعداد آیتم هر تب.
 * @return array<int, array{name:string,link:string,items:array}>
 */
function fs_get_popular( $tabs = 5, $items = 8 ) {
	if ( ! fs_has_woo() ) {
		return array();
	}

	$out = array();

	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'number'     => $tabs,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);

	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	foreach ( $terms as $term ) {
		$products = wc_get_products(
			array(
				'status'   => 'publish',
				'limit'    => $items,
				'category' => array( $term->slug ),
				'orderby'  => 'popularity',
			)
		);

		$cards = array();

		foreach ( $products as $i => $product ) {
			$cards[] = fs_product_to_card( $product, $i );
		}

		if ( $cards ) {
			$out[] = array(
				'name'  => $term->name,
				'link'  => get_term_link( $term ),
				'items' => $cards,
			);
		}
	}

	return $out;
}

/**
 * مقالات.
 *
 * @param int $limit تعداد.
 * @return array
 */
function fs_get_articles( $limit = 3 ) {
	$out = array();

	$posts = get_posts(
		array(
			'numberposts'      => $limit,
			'post_status'      => 'publish',
			'suppress_filters' => false,
		)
	);

	foreach ( $posts as $i => $post ) {
		$minutes = max( 1, (int) round( mb_strlen( wp_strip_all_tags( $post->post_content ) ) / 900 ) );

		$out[] = array(
			't'     => get_the_title( $post ),
			'link'  => get_permalink( $post ),
			'meta'  => sprintf( '%s دقیقه مطالعه', fs_fa_num( $minutes ) ),
			'thumb' => get_the_post_thumbnail( $post, 'medium_large', array( 'loading' => 'lazy', 'decoding' => 'async' ) ),
			'grad'  => fs_grad( $i ),
		);
	}

	return $out;
}

/**
 * یک ستون از نوار متحرک.
 *
 * @param int $offset نقطه شروع.
 * @param int $limit  تعداد.
 * @return array
 */
function fs_get_marquee_column( $offset, $limit = 6 ) {
	if ( ! fs_has_woo() ) {
		return array();
	}

	$products = wc_get_products(
		array(
			'status'  => 'publish',
			'limit'   => $limit,
			'offset'  => $offset,
			'orderby' => 'date',
			'order'   => 'DESC',
		)
	);

	$source = array();

	foreach ( $products as $product ) {
		$source[] = array(
			'title' => $product->get_name(),
			'price' => fs_product_price( $product ),
			'link'  => get_permalink( $product->get_id() ),
		);
	}

	if ( ! $source ) {
		return array();
	}

	// تکرار دوبرابر — انیمیشن با translateY(-50%) بی‌وقفه تکرار می‌شود.
	return array_merge( $source, $source );
}

/**
 * آماری که واقعاً از سایت خوانده می‌شود.
 *
 * @return array<int, array{v:string,k:string}>
 */
function fs_get_stats() {
	$out = array();

	if ( fs_has_woo() ) {
		$products = wp_count_posts( 'product' );
		$count    = isset( $products->publish ) ? (int) $products->publish : 0;

		if ( $count ) {
			$out[] = array(
				'v' => fs_fa_num( number_format_i18n( $count ) ),
				'k' => 'فایل نمونه سوال',
			);
		}

		$terms = wp_count_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			)
		);

		if ( ! is_wp_error( $terms ) && (int) $terms ) {
			$out[] = array(
				'v' => fs_fa_num( number_format_i18n( (int) $terms ) ),
				'k' => 'رشته و مهارت',
			);
		}
	}

	$satisfaction = fs_get_satisfaction();

	if ( $satisfaction ) {
		$out[] = array(
			'v' => fs_fa_num( $satisfaction ),
			'k' => 'رضایت خریداران',
		);
	}

	return $out;
}

/**
 * محصولی که کاربر اخیراً دیده است.
 *
 * @return array|null
 */
function fs_get_recently_viewed() {
	if ( ! fs_has_woo() || empty( $_COOKIE['woocommerce_recently_viewed'] ) ) {
		return null;
	}

	$ids = array_filter( array_map( 'absint', explode( '|', wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) ) );
	$ids = array_reverse( $ids );

	if ( ! $ids ) {
		return null;
	}

	$product = wc_get_product( $ids[0] );

	if ( ! $product || 'publish' !== $product->get_status() ) {
		return null;
	}

	$id        = $product->get_id();
	$questions = fs_product_questions( $id );
	$badges    = fs_product_badges_line( $id );

	return array(
		'title'  => $product->get_name(),
		'link'   => get_permalink( $id ),
		'meta'   => $questions ? sprintf( '%s سوال · %s', $questions, $badges ) : $badges,
		'meta_m' => $questions ? sprintf( '%s سوال · PDF', $questions ) : 'PDF',
		'price'  => fs_product_price( $product ),
	);
}

/**
 * نشانی حساب کاربری.
 *
 * @return string
 */
function fs_account_url() {
	return fs_has_woo() ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
}

/**
 * برچسب دکمه حساب کاربری.
 *
 * @return string
 */
function fs_account_label() {
	return is_user_logged_in() ? 'پنل کاربری' : 'ورود';
}

/**
 * نشانی آرشیو محصولات.
 *
 * @return string
 */
function fs_shop_url() {
	return fs_has_woo() ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
}
