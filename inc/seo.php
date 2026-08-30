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

/* -------------------------------------------------------------------------
   متادیتا: کنونیکال آرشیو و توضیحات متا
   ------------------------------------------------------------------------- */

/**
 * آیا افزونه‌ی سئویی نصب است که خودش متادیتا می‌نویسد؟
 *
 * هر چیزی که در ادامه می‌آید فقط «پرکننده‌ی جای خالی» است. اگر رنک‌مث یا یواست
 * فعال باشد، آن‌ها canonical و description را می‌نویسند و خروجی دوباره‌ی ما
 * فقط تگ تکراری می‌سازد — که خودش خطای سرچ کنسول است.
 *
 * @return bool
 */
function fs_seo_plugin_active() {
	$active = defined( 'RANK_MATH_VERSION' )
		|| defined( 'WPSEO_VERSION' )
		|| defined( 'SEOPRESS_VERSION' )
		|| defined( 'AIOSEO_VERSION' )
		|| defined( 'THE_SEO_FRAMEWORK_VERSION' );

	return (bool) apply_filters( 'fs_seo_plugin_active', $active );
}

/**
 * نشانی کنونیکال آرشیوها.
 *
 * `rel_canonical` هسته‌ی وردپرس فقط روی `is_singular()` اجرا می‌شود؛ یعنی
 * صفحه‌ی دسته با `?orderby=price` و بدون آن، دو نشانی جدا و بدون هیچ سیگنالی
 * برای گوگل بودند. اینجا نشانی پاکِ همان صفحه ساخته می‌شود: پارامترهای
 * مرتب‌سازی و فیلتر حذف، ولی شماره‌ی صفحه نگه داشته می‌شود — صفحه‌ی دوم
 * محتوای دیگری دارد و نباید به صفحه‌ی اول کنونیکال بخورد.
 *
 * @return string نشانی یا رشته‌ی خالی.
 */
function fs_archive_canonical_url() {
	if ( is_singular() || is_front_page() || is_404() || is_search() ) {
		return '';
	}

	$base = '';

	if ( is_tax( array( 'product_cat', 'product_tag' ) ) || is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			$link = get_term_link( $term );
			$base = is_wp_error( $link ) ? '' : $link;
		}
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$shop_id = wc_get_page_id( 'shop' );
		$base    = $shop_id > 0 ? get_permalink( $shop_id ) : '';
	} elseif ( is_home() ) {
		$blog_id = (int) get_option( 'page_for_posts' );
		$base    = $blog_id ? get_permalink( $blog_id ) : home_url( '/' );
	}

	if ( ! $base ) {
		return '';
	}

	$paged = max( 1, (int) get_query_var( 'paged' ) );

	if ( $paged > 1 ) {
		$base = trailingslashit( $base ) . 'page/' . $paged . '/';
	}

	return $base;
}

/**
 * چاپ کنونیکال آرشیو.
 *
 * @return void
 */
function fs_print_archive_canonical() {
	if ( fs_seo_plugin_active() ) {
		return;
	}

	$url = fs_archive_canonical_url();

	if ( $url ) {
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) );
	}
}
add_action( 'wp_head', 'fs_print_archive_canonical', 5 );

/**
 * متن توضیحات متا برای نمای جاری.
 *
 * @return string
 */
function fs_meta_description_text() {
	$text = '';

	if ( is_singular() ) {
		$post = get_queried_object();

		if ( $post instanceof WP_Post ) {
			$text = $post->post_excerpt;

			if ( ! $text && function_exists( 'wc_get_product' ) && 'product' === $post->post_type ) {
				$product = wc_get_product( $post->ID );

				if ( $product ) {
					$text = $product->get_short_description();
				}
			}

			if ( ! $text ) {
				$text = $post->post_content;
			}
		}
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			$text = $term->description;
		}
	} elseif ( is_front_page() || is_home() ) {
		$text = get_bloginfo( 'description' );
	}

	$text = wp_strip_all_tags( strip_shortcodes( (string) $text ), true );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

	if ( ! $text ) {
		return '';
	}

	// ۱۶۰ نویسه سقف عملی گوگل است؛ برش روی مرز واژه تا وسط کلمه قطع نشود.
	if ( mb_strlen( $text, 'UTF-8' ) > 160 ) {
		$text = mb_substr( $text, 0, 160, 'UTF-8' );
		$cut  = mb_strrpos( $text, ' ', 0, 'UTF-8' );

		if ( $cut && $cut > 80 ) {
			$text = mb_substr( $text, 0, $cut, 'UTF-8' );
		}

		$text .= '…';
	}

	return $text;
}

/**
 * چاپ توضیحات متا وقتی هیچ افزونه‌ی سئویی نیست.
 *
 * @return void
 */
function fs_print_meta_description() {
	if ( fs_seo_plugin_active() ) {
		return;
	}

	$text = fs_meta_description_text();

	if ( $text ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $text ) );
	}
}
add_action( 'wp_head', 'fs_print_meta_description', 5 );

/* -------------------------------------------------------------------------
   SoftwareApplication و Article
   ------------------------------------------------------------------------- */

/**
 * فرمت‌هایی که یعنی محصول واقعاً یک نرم‌افزار است.
 *
 * عمداً کوتاه و محافظه‌کارانه: PDF و Word نرم‌افزار نیستند و برچسب‌زدنشان با
 * SoftwareApplication داده‌ی نادرست به گوگل می‌دهد.
 *
 * @return string[]
 */
function fs_software_formats() {
	return (array) apply_filters(
		'fs_software_formats',
		array( 'exe', 'apk', 'msi', 'dmg', 'ipa', 'appimage', 'setup' )
	);
}

/**
 * آیا این محصول نرم‌افزار است؟
 *
 * @param WC_Product $product محصول.
 * @return bool
 */
function fs_product_is_software( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	$is = false;

	if ( function_exists( 'fs_product_format' ) ) {
		$format = strtolower( fs_product_format( $product->get_id() ) );

		foreach ( fs_software_formats() as $needle ) {
			if ( false !== strpos( $format, strtolower( $needle ) ) ) {
				$is = true;

				break;
			}
		}
	}

	return (bool) apply_filters( 'fs_product_is_software', $is, $product );
}

/**
 * افزودن SoftwareApplication به همان موجودیت محصول.
 *
 * روش درست، ساختن یک نود جدا نیست — دو موجودیت برای یک چیز، گوگل را دوقلو
 * می‌بیند. راه پذیرفته‌شده این است که `@type` آرایه شود تا همان یک موجودیت هم
 * Product باشد هم SoftwareApplication؛ این‌طور ریچ‌ریزالت قیمت هم حفظ می‌شود.
 *
 * @param array      $data    داده‌ی اسکیما.
 * @param WC_Product $product محصول.
 * @return array
 */
function fs_schema_software( $data, $product ) {
	if ( ! is_array( $data ) || ! fs_product_is_software( $product ) ) {
		return $data;
	}

	$type = isset( $data['@type'] ) ? (array) $data['@type'] : array( 'Product' );

	if ( ! in_array( 'SoftwareApplication', $type, true ) ) {
		$type[] = 'SoftwareApplication';
	}

	$data['@type'] = $type;

	if ( empty( $data['applicationCategory'] ) ) {
		$data['applicationCategory'] = (string) apply_filters(
			'fs_schema_application_category',
			'UtilitiesApplication',
			$product
		);
	}

	if ( empty( $data['operatingSystem'] ) ) {
		$data['operatingSystem'] = (string) apply_filters(
			'fs_schema_operating_system',
			'Windows, Android',
			$product
		);
	}

	if ( empty( $data['fileSize'] ) && function_exists( 'fs_product_field' ) ) {
		$size = fs_product_field( $product->get_id(), 'file_size' );

		if ( $size ) {
			$data['fileSize'] = $size;
		}
	}

	return $data;
}
add_filter( 'woocommerce_structured_data_product', 'fs_schema_software', 30, 2 );

/**
 * اسکیمای مقاله برای نوشته‌های وبلاگ.
 *
 * ووکامرس فقط محصول را پوشش می‌دهد و نوشته‌ها هیچ داده‌ی ساختاریافته‌ای
 * نداشتند. اگر افزونه‌ی سئویی فعال باشد این را نمی‌نویسیم تا موجودیت تکراری
 * نسازیم.
 *
 * @return void
 */
function fs_print_article_schema() {
	if ( fs_seo_plugin_active() || ! is_singular( 'post' ) ) {
		return;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$data = array(
		'@context'         => 'https://schema.org',
		'@type'            => 'BlogPosting',
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => get_permalink( $post ),
		),
		'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
		'datePublished'    => get_the_date( DATE_W3C, $post ),
		'dateModified'     => get_the_modified_date( DATE_W3C, $post ),
		'author'           => array(
			'@type' => 'Person',
			'name'  => get_the_author_meta( 'display_name', (int) $post->post_author ),
		),
		'publisher'        => array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		),
	);

	$description = fs_meta_description_text();

	if ( $description ) {
		$data['description'] = $description;
	}

	$thumb = get_the_post_thumbnail_url( $post, 'large' );

	if ( $thumb ) {
		$data['image'] = $thumb;
	}

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
	);
}
add_action( 'wp_footer', 'fs_print_article_schema', 20 );
