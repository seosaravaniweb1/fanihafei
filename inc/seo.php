<?php
/**
 * داده‌ی ساختاریافته‌ی محصول (JSON-LD).
 *
 * هدف: صفر اخطار در سرچ کنسول برای اسنیپت محصول. سه مشکل ریشه‌ای حل می‌شود:
 *
 * ۱. واحد پول. فروشگاه‌های ایرانی قیمت را به «تومان» نشان می‌دهند و اغلب واحد
 *    ووکامرس هم روی تومان تنظیم است. اما گوگل فقط کد ISO 4217 را می‌پذیرد و
 *    «تومان» یا IRT کد معتبری نیست؛ نتیجه‌اش اخطار priceCurrency است. تومان
 *    واحد رسمی نیست، ریال است: هر تومان ده ریال. پس در اسکیما کد IRR می‌رود و
 *    عدد در ۱۰ ضرب می‌شود. ظاهر سایت اصلاً دست نمی‌خورد.
 *
 * ۲. priceValidUntil. اگر offer تاریخ انقضا نداشته باشد گوگل اخطار می‌دهد و
 *    بعد از مدتی اسنیپت قیمت را نشان نمی‌دهد. اینجا اگر تخفیف زمان‌دار باشد
 *    همان تاریخ می‌رود، وگرنه یک تاریخ پویا یک سال بعد ساخته می‌شود.
 *
 * ۳. فیلدهای ناقص. sku، brand، review و aggregateRating یا باید درست و کامل
 *    باشند یا اصلاً نباشند؛ فیلد خالی یا aggregateRating با تعداد صفر خودش
 *    خطاساز است.
 *
 * همه‌ی این‌ها هم روی خروجی خود ووکامرس اعمال می‌شود و هم روی رنک‌مث، اگر نصب
 * باشد — چون در آن حالت رنک‌مث اسکیمای ووکامرس را خاموش می‌کند و خودش می‌نویسد.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

/**
 * آیا واحد فروشگاه تومان است؟
 *
 * ووکامرس فارسی‌شده معمولاً یکی از این کدها را می‌گذارد. اگر فروشگاه مستقیماً
 * روی IRR باشد، هیچ تبدیلی لازم نیست.
 *
 * @return bool
 */
function fs_shop_is_toman() {
	$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

	return in_array( strtoupper( $currency ), fs_toman_currency_codes(), true );
}

/**
 * کدهایی که یعنی «تومان».
 *
 * @return string[]
 */
function fs_toman_currency_codes() {
	return (array) apply_filters( 'fs_toman_currency_codes', array( 'IRT', 'TOMAN', 'TMN' ) );
}

/**
 * کد واحد پول برای داده‌ی ساختاریافته — همیشه یک کد معتبر ISO 4217.
 *
 * @return string
 */
function fs_schema_currency() {
	if ( fs_shop_is_toman() ) {
		return 'IRR';
	}

	$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';

	return $currency ? $currency : 'IRR';
}

/**
 * تبدیل قیمت نمایشی به عددی که باید در اسکیما برود.
 *
 * فقط وقتی ضرب در ۱۰ انجام می‌شود که واحد فروشگاه تومان باشد؛ اگر فروشگاه
 * خودش روی ریال تنظیم باشد عدد دست‌نخورده می‌ماند.
 *
 * @param mixed $price قیمت خام.
 * @return string قیمت با دو رقم اعشار.
 */
function fs_schema_price( $price ) {
	$value = (float) wc_format_decimal( $price );

	if ( fs_shop_is_toman() ) {
		$value *= 10;
	}

	return wc_format_decimal( $value, 2 );
}

/**
 * تاریخ اعتبار قیمت.
 *
 * اگر تخفیف تاریخ پایان دارد همان، وگرنه یک سال بعد از امروز — پویا، تا هرگز
 * تاریخِ گذشته در اسکیما نماند.
 *
 * @param WC_Product $product محصول.
 * @return string تاریخ ISO 8601 (Y-m-d).
 */
function fs_schema_price_valid_until( $product ) {
	if ( $product instanceof WC_Product ) {
		$sale_end = $product->get_date_on_sale_to();

		if ( $sale_end ) {
			return $sale_end->date( 'Y-m-d' );
		}
	}

	$months = (int) apply_filters( 'fs_schema_price_valid_months', 12 );

	return gmdate( 'Y-m-d', strtotime( '+' . max( 1, $months ) . ' months' ) );
}

/**
 * اصلاح داده‌ی ساختاریافته‌ی محصولِ خود ووکامرس.
 *
 * @param array      $data    داده‌ی اسکیما.
 * @param WC_Product $product محصول.
 * @return array
 */
function fs_structured_data_product( $data, $product = null ) {
	if ( ! is_array( $data ) || ! $product instanceof WC_Product ) {
		return $data;
	}

	// --- offers: واحد پول، قیمت و تاریخ اعتبار ---------------------------
	if ( ! empty( $data['offers'] ) && is_array( $data['offers'] ) ) {
		foreach ( $data['offers'] as $i => $offer ) {
			if ( ! is_array( $offer ) ) {
				continue;
			}

			$offer['priceCurrency'] = fs_schema_currency();

			if ( isset( $offer['price'] ) ) {
				$offer['price'] = fs_schema_price( $offer['price'] );
			}

			foreach ( array( 'lowPrice', 'highPrice' ) as $key ) {
				if ( isset( $offer[ $key ] ) ) {
					$offer[ $key ] = fs_schema_price( $offer[ $key ] );
				}
			}

			if ( isset( $offer['priceSpecification'] ) && is_array( $offer['priceSpecification'] ) ) {
				if ( isset( $offer['priceSpecification']['price'] ) ) {
					$offer['priceSpecification']['price'] = fs_schema_price( $offer['priceSpecification']['price'] );
				}
				$offer['priceSpecification']['priceCurrency'] = fs_schema_currency();
			}

			$offer['priceValidUntil'] = fs_schema_price_valid_until( $product );

			$data['offers'][ $i ] = $offer;
		}
	}

	$data = fs_schema_common_fields( $data, $product );

	return $data;
}
add_filter( 'woocommerce_structured_data_product', 'fs_structured_data_product', 20, 2 );

/**
 * فیلدهای مشترک بین ووکامرس و رنک‌مث: sku، brand، امتیازها.
 *
 * @param array      $data    داده‌ی اسکیما.
 * @param WC_Product $product محصول.
 * @return array
 */
function fs_schema_common_fields( $data, $product ) {
	$id = $product->get_id();

	// --- sku: اگر خالی باشد گوگل اخطار می‌دهد؛ شناسه‌ی محصول جایگزین امنی است.
	if ( empty( $data['sku'] ) ) {
		$sku         = $product->get_sku();
		$data['sku'] = $sku ? $sku : (string) $id;
	}

	// --- brand: از همان تاکسونومی برندی که صفحه‌ی محصول «نویسنده» را از آن می‌خواند.
	if ( empty( $data['brand'] ) ) {
		$authors = function_exists( 'fs_get_product_authors' ) ? fs_get_product_authors( $id ) : array();

		if ( $authors ) {
			$data['brand'] = array(
				'@type' => 'Brand',
				'name'  => $authors[0]['name'],
			);
		}
	}

	// --- امتیازها: aggregateRating فقط وقتی دیدگاه واقعی هست.
	$count = (int) $product->get_review_count();

	if ( $count < 1 ) {
		unset( $data['aggregateRating'], $data['review'] );
	} elseif ( empty( $data['aggregateRating'] ) ) {
		$average = (float) $product->get_average_rating();

		if ( $average > 0 ) {
			$data['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $average,
				'reviewCount' => (string) $count,
			);
		}
	}

	return $data;
}

/* -------------------------------------------------------------------------
   سازگاری با رنک‌مث
   ------------------------------------------------------------------------- */

/**
 * همان اصلاحات، روی اسکیمای محصولِ رنک‌مث.
 *
 * وقتی رنک‌مث فعال است، اسکیمای ووکامرس را خاموش می‌کند و خودش می‌نویسد؛ پس
 * فیلتر بالا اصلاً اجرا نمی‌شود و بدون این، مشکل واحد پول سر جایش می‌ماند.
 *
 * @param array  $entity  موجودیت اسکیما.
 * @param object $product محصول رنک‌مث یا ووکامرس.
 * @return array
 */
function fs_rank_math_product_schema( $entity, $product = null ) {
	if ( ! is_array( $entity ) ) {
		return $entity;
	}

	$wc_product = null;

	if ( $product instanceof WC_Product ) {
		$wc_product = $product;
	} elseif ( function_exists( 'wc_get_product' ) ) {
		$wc_product = wc_get_product( get_the_ID() );
	}

	if ( ! $wc_product instanceof WC_Product ) {
		return $entity;
	}

	if ( ! empty( $entity['offers'] ) && is_array( $entity['offers'] ) ) {
		// رنک‌مث گاهی یک offer تکی می‌دهد و گاهی آرایه‌ای از offerها.
		$offers    = isset( $entity['offers']['@type'] ) ? array( $entity['offers'] ) : $entity['offers'];
		$is_single = isset( $entity['offers']['@type'] );

		foreach ( $offers as $i => $offer ) {
			if ( ! is_array( $offer ) ) {
				continue;
			}

			$offer['priceCurrency'] = fs_schema_currency();

			foreach ( array( 'price', 'lowPrice', 'highPrice' ) as $key ) {
				if ( isset( $offer[ $key ] ) ) {
					$offer[ $key ] = fs_schema_price( $offer[ $key ] );
				}
			}

			$offer['priceValidUntil'] = fs_schema_price_valid_until( $wc_product );

			$offers[ $i ] = $offer;
		}

		$entity['offers'] = $is_single ? $offers[0] : $offers;
	}

	return fs_schema_common_fields( $entity, $wc_product );
}
add_filter( 'rank_math/snippet/rich_snippet_product_entity', 'fs_rank_math_product_schema', 20, 2 );

/**
 * رنک‌مث واحد پول را جداگانه هم صدا می‌زند.
 *
 * @return string
 */
function fs_rank_math_currency() {
	return fs_schema_currency();
}
add_filter( 'rank_math/woocommerce/og_currency', 'fs_rank_math_currency', 20 );
