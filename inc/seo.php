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
 * تبدیل هر چیزی که فیلترها پاس می‌دهند به یک WC_Product.
 *
 * ووکامرس همیشه WC_Product می‌دهد، ولی رنک‌مث شیء خودش را پاس می‌کند — و
 * چون فروشگاه از رنک‌مث استفاده می‌کند، بدون این تبدیل هیچ‌کدام از این
 * فیلترها روی سایت اجرا نمی‌شدند.
 *
 * @param mixed $product ورودی فیلتر.
 * @return WC_Product|null
 */
function fs_resolve_wc_product( $product ) {
	if ( $product instanceof WC_Product ) {
		return $product;
	}

	if ( ! function_exists( 'wc_get_product' ) ) {
		return null;
	}

	$id = 0;

	if ( is_numeric( $product ) ) {
		$id = (int) $product;
	} elseif ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
		$id = (int) $product->get_id();
	}

	if ( ! $id ) {
		$id = get_the_ID();
	}

	$resolved = $id ? wc_get_product( $id ) : null;

	return $resolved instanceof WC_Product ? $resolved : null;
}

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
	$product = fs_resolve_wc_product( $product );

	if ( ! $product ) {
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
function fs_schema_software( $data, $product = null ) {
	$product = fs_resolve_wc_product( $product );

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
add_filter( 'rank_math/snippet/rich_snippet_product_entity', 'fs_schema_software', 30, 2 );

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

/* -------------------------------------------------------------------------
   متن جایگزین تصاویر
   ------------------------------------------------------------------------- */

/**
 * شناسه‌ی نوشته‌ای که همین حالا تصویر شاخصش رندر می‌شود.
 *
 * @param int|null $set مقدار تازه، یا null برای فقط خواندن.
 * @return int
 */
function fs_thumbnail_owner( $set = null ) {
	static $owner = 0;

	if ( null !== $set ) {
		$owner = (int) $set;
	}

	return $owner;
}

/**
 * گرفتن صاحب تصویر شاخص، درست پیش از رندرشدنش.
 *
 * چرا این راه: فیلتر اتریبیوت‌های تصویر فقط خودِ پیوست را می‌بیند و نمی‌داند
 * برای کدام نوشته رندر می‌شود. تکیه‌کردن به post_parent هم کافی نیست — تصویری
 * که از کتابخانه‌ی رسانه انتخاب شود اصلاً والد ندارد، و دقیقاً همین حالت در
 * عمل رایج‌ترین است.
 *
 * @param int $post_id شناسه نوشته.
 * @return void
 */
function fs_capture_thumbnail_owner( $post_id ) {
	fs_thumbnail_owner( $post_id );
}
add_action( 'begin_fetch_post_thumbnail_html', 'fs_capture_thumbnail_owner' );

/**
 * رهاکردن صاحب تصویر پس از رندر.
 *
 * @return void
 */
function fs_release_thumbnail_owner() {
	fs_thumbnail_owner( 0 );
}
add_action( 'end_fetch_post_thumbnail_html', 'fs_release_thumbnail_owner' );

/**
 * اگر تصویری alt نداشت، از نام محصولی که به آن تعلق دارد استفاده کن.
 *
 * قالب هیچ‌جا alt را دستی پاس نمی‌دهد و درست هم همین است — متن جایگزین باید
 * از خود رسانه بیاید. ولی در عمل فیلد «متن جایگزین» کتابخانه‌ی رسانه معمولاً
 * خالی می‌ماند و نتیجه‌اش کاور و اسکرین‌شات‌هایی است که با alt="" رندر
 * می‌شوند: نه در جست‌وجوی تصویر گوگل می‌آیند و نه برای صفحه‌خوان معنا دارند.
 *
 * سه محدودیت عمدی:
 * — متن دستی ادمین همیشه برنده است؛ این فقط جای خالی را پر می‌کند.
 * — فقط محصول‌ها. تصاویر تزئینی (نماد اعتماد، لوگوی بانک‌ها) که عمداً alt
 *   خالی دارند دست‌نخورده می‌مانند.
 * — هیچ کوئری اضافه‌ای نمی‌زند؛ صاحب تصویر از همان چرخه‌ی رندر می‌آید.
 *
 * @param array   $attr       اتریبیوت‌های تصویر.
 * @param WP_Post $attachment پیوست.
 * @return array
 */
function fs_fallback_image_alt( $attr, $attachment ) {
	if ( ! empty( $attr['alt'] ) || ! $attachment instanceof WP_Post ) {
		return $attr;
	}

	// اول نوشته‌ای که همین حالا تصویر شاخصش رندر می‌شود، بعد والد پیوست
	// (تصویرهایی که از خود صفحه‌ی محصول بارگذاری شده‌اند)، و در نهایت محصولی
	// که صفحه‌اش باز است — برای تصاویر گالری.
	$owner = fs_thumbnail_owner();

	if ( ! $owner ) {
		$owner = (int) $attachment->post_parent;
	}

	if ( ! $owner && is_singular( 'product' ) ) {
		$owner = get_queried_object_id();
	}

	if ( ! $owner || 'product' !== get_post_type( $owner ) ) {
		return $attr;
	}

	$attr['alt'] = wp_strip_all_tags( get_the_title( $owner ) );

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'fs_fallback_image_alt', 10, 2 );

/* -------------------------------------------------------------------------
   فیلدهای اختصاصی محصول در اسکیما
   ------------------------------------------------------------------------- */

/**
 * یک ویژگی PropertyValue.
 *
 * @param string $name  نام ویژگی.
 * @param string $value مقدار.
 * @return array
 */
function fs_schema_property( $name, $value ) {
	return array(
		'@type' => 'PropertyValue',
		'name'  => $name,
		// ادمین «۱۲ مگابایت» می‌نویسد؛ ارقام فارسی برای خواننده‌ی ماشینی عدد
		// نیستند، پس در اسکیما لاتین می‌روند. ظاهر سایت دست نمی‌خورد.
		'value' => fs_en_num( (string) $value ),
	);
}

/**
 * مقدار خام «مقدار» و واحدش — بدون تبدیل به عدد فارسی.
 *
 * fs_product_amount() خروجی نمایشی می‌دهد (ارقام فارسی) که برای اسکیما
 * بی‌فایده است؛ گوگل عدد لاتین می‌خواهد.
 *
 * @param int $product_id شناسه محصول.
 * @return array{n:string,unit:string}|null
 */
function fs_schema_amount( $product_id ) {
	$units = fs_amount_units();
	$value = fs_product_field( $product_id, 'amount' );
	$unit  = fs_product_field( $product_id, 'amount_unit' );

	if ( '' === $value ) {
		foreach ( array( 'page_count' => 'page', 'question_count' => 'question' ) as $legacy => $legacy_unit ) {
			$found = fs_product_field( $product_id, $legacy );

			if ( '' !== $found ) {
				$value = $found;
				$unit  = $legacy_unit;

				break;
			}
		}
	}

	if ( '' === $value ) {
		return null;
	}

	if ( ! isset( $units[ $unit ] ) ) {
		$unit = 'page';
	}

	return array(
		'n'    => fs_en_num( $value ),
		'unit' => $units[ $unit ],
	);
}

/**
 * افزودن فیلدهای اختصاصی محصول به داده‌ی ساختاریافته.
 *
 * یک نکته‌ی مهم که باید بدانید: از این‌ها فقط ویدیو در نتایج گوگل ظاهر
 * می‌شود (ریچ‌ریزالت ویدیو). حجم فایل، تعداد صفحه و ضمیمه‌ها را گوگل نمایش
 * نمی‌دهد؛ ولی داده‌ی معتبرند و به درک موجودیت صفحه کمک می‌کنند.
 *
 * فرمت فایل، نویسنده و مترجم روی خود موجودیت می‌نشینند — این‌ها ویژگی‌های
 * CreativeWork هستند نه Product، پس نوع موجودیت هم گسترش پیدا می‌کند. بقیه
 * به‌صورت additionalProperty می‌روند که راه استاندارد بیان مشخصات دلخواه
 * روی Product است.
 *
 * @param array      $data    داده‌ی اسکیما.
 * @param WC_Product $product محصول.
 * @return array
 */
function fs_schema_product_details( $data, $product = null ) {
	$product = fs_resolve_wc_product( $product );

	if ( ! is_array( $data ) || ! $product ) {
		return $data;
	}

	$id = $product->get_id();

	// --- نوع موجودیت ------------------------------------------------------
	$type = isset( $data['@type'] ) ? (array) $data['@type'] : array( 'Product' );

	// SoftwareApplication خودش زیرمجموعه‌ی CreativeWork است؛ دوباره نگذاریم.
	if ( ! array_intersect( array( 'CreativeWork', 'SoftwareApplication' ), $type ) ) {
		$type[] = 'CreativeWork';
	}

	$data['@type'] = $type;

	if ( empty( $data['inLanguage'] ) ) {
		$data['inLanguage'] = 'fa-IR';
	}

	// --- فرمت فایل --------------------------------------------------------
	$format = fs_product_field( $id, 'file_format' );

	if ( $format && empty( $data['encodingFormat'] ) ) {
		$data['encodingFormat'] = $format;
	}

	// --- نویسنده و مترجم --------------------------------------------------
	if ( empty( $data['author'] ) ) {
		$authors = fs_get_product_authors( $id );

		if ( $authors ) {
			$person = array(
				'@type' => 'Person',
				'name'  => $authors[0]['name'],
			);

			if ( ! empty( $authors[0]['link'] ) ) {
				$person['url'] = $authors[0]['link'];
			}

			$data['author'] = $person;
		}
	}

	$translator = fs_product_field( $id, 'translator_name' );

	if ( $translator && empty( $data['translator'] ) ) {
		$data['translator'] = array(
			'@type' => 'Person',
			'name'  => $translator,
		);
	}

	// --- مشخصات به‌صورت ویژگی --------------------------------------------
	$props = isset( $data['additionalProperty'] ) ? (array) $data['additionalProperty'] : array();

	if ( $format ) {
		$props[] = fs_schema_property( 'فرمت فایل', $format );
	}

	$size = fs_product_field( $id, 'file_size' );

	if ( $size ) {
		$props[] = fs_schema_property( 'حجم فایل', $size );
	}

	$amount = fs_schema_amount( $id );

	if ( $amount ) {
		$props[] = fs_schema_property( 'تعداد ' . $amount['unit'], $amount['n'] );
	}

	if ( fs_product_flag( $id, 'has_answers' ) ) {
		$props[] = fs_schema_property( 'پاسخنامه', 'دارد' );
	}

	$extras = fs_get_product_attachments( $id );

	if ( $extras ) {
		$props[] = fs_schema_property( 'ضمیمه‌ها', implode( '، ', $extras ) );
	}

	if ( $props ) {
		$data['additionalProperty'] = $props;
	}

	// --- رسانه‌ی معرفی ----------------------------------------------------
	$media = array();
	$audio = fs_product_field( $id, 'audio_url' );

	if ( $audio ) {
		$media[] = array(
			'@type'      => 'AudioObject',
			'name'       => 'نمونه صوتی ' . wp_strip_all_tags( get_the_title( $id ) ),
			'contentUrl' => fs_safe_media_url( $audio ),
		);
	}

	$video = fs_schema_video_object( $product );

	if ( $video ) {
		$media[] = $video;
	}

	if ( $media && empty( $data['associatedMedia'] ) ) {
		$data['associatedMedia'] = $media;
	}

	return $data;
}
add_filter( 'woocommerce_structured_data_product', 'fs_schema_product_details', 40, 2 );
add_filter( 'rank_math/snippet/rich_snippet_product_entity', 'fs_schema_product_details', 40, 2 );

/**
 * موجودیت ویدیوی معرفی.
 *
 * گوگل برای ریچ‌ریزالت ویدیو سه چیز را لازم دارد: name، thumbnailUrl و
 * uploadDate. اگر تصویر شاخص نباشد، thumbnailUrl نداریم و ساختن موجودیت
 * ناقص فقط اخطار سرچ کنسول می‌سازد — پس در آن حالت چیزی نمی‌سازیم.
 *
 * @param WC_Product $product محصول.
 * @return array|null
 */
function fs_schema_video_object( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return null;
	}

	$id  = $product->get_id();
	$raw = fs_product_field( $id, 'video_url' );

	if ( ! $raw ) {
		return null;
	}

	$source = fs_video_source( $raw );

	if ( empty( $source['src'] ) ) {
		return null;
	}

	$thumb = get_the_post_thumbnail_url( $id, 'large' );

	if ( ! $thumb ) {
		return null;
	}

	$created = $product->get_date_created();

	$video = array(
		'@type'        => 'VideoObject',
		'name'         => 'ویدیوی معرفی ' . wp_strip_all_tags( get_the_title( $id ) ),
		'description'  => wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : get_the_title( $id ) ),
		'thumbnailUrl' => $thumb,
		'uploadDate'   => $created ? $created->date( DATE_W3C ) : get_the_date( DATE_W3C, $id ),
	);

	// فایل مستقیم contentUrl است و آپارات embedUrl؛ جابه‌جا گذاشتنشان اخطار
	// می‌سازد چون گوگل انتظار دارد contentUrl خودِ فایل ویدیو باشد.
	if ( 'file' === $source['type'] ) {
		$video['contentUrl'] = fs_safe_media_url( $source['src'] );
	} else {
		$video['embedUrl'] = $source['src'];
	}

	return $video;
}
