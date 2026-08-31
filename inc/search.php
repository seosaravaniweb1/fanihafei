<?php
/**
 * جست‌وجوی محصولات: پیشنهاد زنده و رتبه‌بندی بر اساس ربط.
 *
 * جست‌وجوی پیش‌فرض وردپرس برای یک فروشگاه فایل دو مشکل جدی دارد:
 *
 * ۱. رتبه‌بندی ندارد. `LIKE '%کلمه%'` روی عنوان و متن اجرا می‌شود و نتیجه به
 *    ترتیب تاریخ برمی‌گردد. یعنی فایلی که کلمه‌ی جست‌وجو دقیقاً عنوانش است،
 *    ممکن است بعد از ده فایلی بیاید که آن کلمه یک بار وسط متنشان آمده.
 *
 * ۲. فارسی را یک‌دست نمی‌بیند. «کتاب» با «كتاب» (کافِ عربی)، «های» با «هاي»،
 *    و «۱۲» با «12» از نظر MySQL سه چیز متفاوت‌اند. کاربری که با کیبورد
 *    عربی تایپ می‌کند هیچ نتیجه‌ای نمی‌گیرد.
 *
 * این فایل هر دو را حل می‌کند: متن پیش از مقایسه یک‌دست می‌شود، و نتایج با
 * یک امتیاز مرتب می‌شوند که عنوان را از متن، و تطابق کامل را از جزئی، جدا
 * می‌کند.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
   یک‌دست‌سازی متن فارسی
   ------------------------------------------------------------------------- */

/**
 * یک‌دست‌کردن یک رشته برای مقایسه.
 *
 * حروفی که در عربی و فارسی شکل متفاوت دارند به شکل فارسی می‌آیند، اعراب و
 * نیم‌فاصله برداشته می‌شود و ارقام به لاتین می‌روند. نتیجه فقط برای مقایسه
 * است؛ چیزی که به کاربر نشان داده می‌شود دست‌نخورده می‌ماند.
 *
 * @param string $text متن.
 * @return string
 */
function fs_search_normalize( $text ) {
	$text = (string) $text;

	$map = array(
		// کاف و یای عربی.
		'ك' => 'ک',
		'ي' => 'ی',
		'ﻯ' => 'ی',
		'ى' => 'ی',
		// همزه‌های روی الف و واو: کاربر معمولاً ساده تایپ می‌کند.
		'أ' => 'ا',
		'إ' => 'ا',
		'آ' => 'ا',
		'ٱ' => 'ا',
		'ؤ' => 'و',
		'ئ' => 'ی',
		'ة' => 'ه',
		// نیم‌فاصله و فاصله‌های نامرئی.
		"\xE2\x80\x8C" => ' ',
		"\xE2\x80\x8F" => '',
		"\xE2\x80\x8E" => '',
		"\xC2\xA0"     => ' ',
	);

	// اعراب: فتحه، کسره، ضمه، تنوین، تشدید، سکون.
	$marks = array( 'َ', 'ِ', 'ُ', 'ً', 'ٍ', 'ٌ', 'ّ', 'ْ', 'ٰ', 'ٓ' );

	foreach ( $marks as $mark ) {
		$map[ $mark ] = '';
	}

	$text = strtr( $text, $map );

	if ( function_exists( 'fs_en_num' ) ) {
		$text = fs_en_num( $text );
	}

	$text = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );

	return trim( preg_replace( '/\s+/u', ' ', mb_strtolower( $text, 'UTF-8' ) ) );
}

/**
 * شکستن عبارت جست‌وجو به کلمه‌های معنادار.
 *
 * کلمه‌های یک‌حرفی و حروف ربط کنار گذاشته می‌شوند: «کتاب و جزوه» نباید روی
 * «و» امتیاز بگیرد، وگرنه هر محصولی که «و» دارد — یعنی همه — بالا می‌آید.
 *
 * @param string $query عبارت.
 * @return string[]
 */
function fs_search_terms( $query ) {
	$stop = (array) apply_filters(
		'fs_search_stopwords',
		array( 'و', 'در', 'به', 'از', 'با', 'را', 'که', 'این', 'برای', 'یا', 'تا', 'هم', 'است', 'های', 'ها' )
	);

	$words = explode( ' ', fs_search_normalize( $query ) );
	$out   = array();

	foreach ( $words as $word ) {
		if ( mb_strlen( $word, 'UTF-8' ) < 2 || in_array( $word, $stop, true ) ) {
			continue;
		}

		$out[] = $word;
	}

	// اگر همه‌ی کلمه‌ها حذف شدند (مثلاً کاربر فقط «ها» زده)، خودِ عبارت می‌ماند.
	if ( ! $out ) {
		$whole = fs_search_normalize( $query );

		if ( '' !== $whole ) {
			$out[] = $whole;
		}
	}

	return array_slice( array_unique( $out ), 0, 6 );
}

/* -------------------------------------------------------------------------
   امتیازدهی
   ------------------------------------------------------------------------- */

/**
 * وزن هر جایی که کلمه می‌تواند پیدا شود.
 *
 * ترتیب اعداد مهم است، نه مقدار مطلقشان: عنوان باید از متن بالاتر باشد و
 * تطابق کامل از تطابق جزئی، وگرنه رتبه‌بندی بی‌معنی می‌شود.
 *
 * @return array<string, int>
 */
function fs_search_weights() {
	return (array) apply_filters(
		'fs_search_weights',
		array(
			'title_exact'  => 1000, // عنوان دقیقاً همان عبارت است.
			'title_starts' => 400,  // عنوان با عبارت شروع می‌شود.
			'title_word'   => 120,  // کلمه‌ای از عبارت در عنوان هست.
			'sku'          => 300,
			'brand'        => 90,   // نویسنده / پدیدآورنده.
			'category'     => 70,
			'tag'          => 60,
			'excerpt'      => 40,   // توضیح کوتاه — چکیده‌ی دستیِ محتوا.
			'content'      => 12,
		)
	);
}

/**
 * امتیاز ربط یک محصول به عبارت جست‌وجو.
 *
 * @param WC_Product $product محصول.
 * @param string[]   $terms   کلمه‌های جست‌وجو.
 * @param string     $phrase  کل عبارت، یک‌دست‌شده.
 * @return int
 */
function fs_search_score( $product, $terms, $phrase ) {
	$w     = fs_search_weights();
	$id    = $product->get_id();
	$score = 0;

	$title = fs_search_normalize( $product->get_name() );

	if ( $phrase && $title === $phrase ) {
		$score += $w['title_exact'];
	} elseif ( $phrase && 0 === strpos( $title, $phrase ) ) {
		$score += $w['title_starts'];
	}

	$haystacks = array(
		'brand'    => fs_search_terms_text( $id, fs_brand_taxonomy() ),
		'category' => fs_search_terms_text( $id, 'product_cat' ),
		'tag'      => fs_search_terms_text( $id, 'product_tag' ),
		'excerpt'  => fs_search_normalize( $product->get_short_description() ),
		'content'  => fs_search_normalize( wp_strip_all_tags( strip_shortcodes( $product->get_description() ) ) ),
	);

	$sku = fs_search_normalize( $product->get_sku() );

	foreach ( $terms as $term ) {
		if ( fs_search_has_word( $title, $term ) ) {
			$score += $w['title_word'];
		}

		if ( $sku && false !== strpos( $sku, $term ) ) {
			$score += $w['sku'];
		}

		foreach ( $haystacks as $key => $text ) {
			if ( $text && fs_search_has_word( $text, $term ) ) {
				$score += $w[ $key ];
			}
		}
	}

	/*
	 * محصولی که هیچ کلمه‌ای را در عنوان یا دسته ندارد و فقط یک بار وسط متن
	 * آمده، نباید بالای فهرست بیاید؛ ولی حذفش هم نمی‌کنیم چون گاهی همان
	 * چیزی است که کاربر می‌خواهد.
	 */
	return (int) apply_filters( 'fs_search_score', $score, $product, $terms, $phrase );
}

/**
 * آیا کلمه در متن هست؟
 *
 * مرز کلمه در نظر گرفته می‌شود تا «کتاب» با «کتابخانه» یکی نشود، ولی جست‌وجوی
 * ناقص هم از دست نرود: اگر کلمه ابتدای یک کلمه‌ی دیگر باشد امتیاز می‌گیرد.
 *
 * @param string $haystack متن.
 * @param string $needle   کلمه.
 * @return bool
 */
function fs_search_has_word( $haystack, $needle ) {
	if ( '' === $haystack || '' === $needle ) {
		return false;
	}

	return (bool) preg_match( '/(^|\s)' . preg_quote( $needle, '/' ) . '/u', $haystack );
}

/**
 * نام ترم‌های یک تاکسونومی به‌شکل یک رشته‌ی یک‌دست.
 *
 * @param int    $post_id  شناسه.
 * @param string $taxonomy تاکسونومی.
 * @return string
 */
function fs_search_terms_text( $post_id, $taxonomy ) {
	if ( ! $taxonomy ) {
		return '';
	}

	$terms = get_the_terms( $post_id, $taxonomy );

	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}

	return fs_search_normalize( implode( ' ', wp_list_pluck( $terms, 'name' ) ) );
}

/* -------------------------------------------------------------------------
   اجرای جست‌وجو
   ------------------------------------------------------------------------- */

/**
 * یافتن محصولات مرتبط با یک عبارت، مرتب‌شده بر اساس ربط.
 *
 * @param string $query    عبارت.
 * @param int    $limit    تعداد نتیجه.
 * @param string $category اسلاگ دسته برای محدودکردن جست‌وجو.
 * @return WC_Product[]
 */
function fs_search_products( $query, $limit = 8, $category = '' ) {
	$terms = fs_search_terms( $query );

	if ( ! $terms || ! function_exists( 'wc_get_products' ) ) {
		return array();
	}

	$phrase = fs_search_normalize( $query );

	/*
	 * چرا چند کوئری و نه یکی:
	 *
	 * جست‌وجوی «s» وردپرس همه‌ی کلمه‌ها را با AND می‌بندد، پس «جزوه ریاضی
	 * گسسته» هیچ نتیجه‌ای نمی‌دهد مگر هر سه کلمه در یک فیلد باشند. اینجا
	 * علاوه بر کوئری اصلی، یک کوئری هم روی تک‌تک کلمه‌ها می‌زنیم و مجموعه را
	 * یکی می‌کنیم؛ رتبه‌بندی بعداً تصمیم می‌گیرد کدام بالاتر بیاید.
	 */
	$pool = array();
	$args = array(
		'status' => 'publish',
		'limit'  => (int) apply_filters( 'fs_search_pool_size', 60 ),
		'return' => 'ids',
	);

	if ( $category ) {
		$args['category'] = array( $category );
	}

	$searches = array_slice( array_merge( array( $query ), $terms ), 0, 4 );

	foreach ( $searches as $needle ) {
		$found = wc_get_products( array_merge( $args, array( 's' => $needle ) ) );

		foreach ( (array) $found as $id ) {
			$pool[ (int) $id ] = true;
		}
	}

	if ( ! $pool ) {
		return array();
	}

	$scored = array();

	foreach ( array_keys( $pool ) as $id ) {
		$product = wc_get_product( $id );

		if ( ! $product || ! $product->is_visible() ) {
			continue;
		}

		$score = fs_search_score( $product, $terms, $phrase );

		if ( $score <= 0 ) {
			continue;
		}

		$scored[] = array(
			'product' => $product,
			'score'   => $score,
		);
	}

	// امتیاز برابر: تازه‌تر بالاتر. بدون این، ترتیب به شناسه‌ی دیتابیس می‌افتاد.
	usort(
		$scored,
		static function ( $a, $b ) {
			if ( $a['score'] === $b['score'] ) {
				return $b['product']->get_id() - $a['product']->get_id();
			}

			return $b['score'] - $a['score'];
		}
	);

	return wp_list_pluck( array_slice( $scored, 0, max( 1, (int) $limit ) ), 'product' );
}

/* -------------------------------------------------------------------------
   نقطه‌ی اجاکس
   ------------------------------------------------------------------------- */

/**
 * پیشنهادهای زنده‌ی جست‌وجو.
 *
 * @return void
 */
function fs_ajax_live_search() {
	$query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$cat   = isset( $_GET['cat'] ) ? sanitize_title( wp_unslash( $_GET['cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( mb_strlen( trim( $query ), 'UTF-8' ) < 2 ) {
		wp_send_json_success( array( 'items' => array(), 'more' => '' ) );
	}

	/*
	 * نتایج برای همه یکسان است و به کاربر بستگی ندارد، پس کش‌کردنش امن است.
	 * تایپ زنده یعنی چند درخواست پشت سر هم؛ بدون کش، هر حرف یک دور کامل
	 * کوئری روی دیتابیس بود.
	 */
	$key    = 'fs_ls_' . md5( fs_search_normalize( $query ) . '|' . $cat );
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		wp_send_json_success( $cached );
	}

	$items = array();

	foreach ( fs_search_products( $query, 6, $cat ) as $product ) {
		$id = $product->get_id();

		$items[] = array(
			'title' => $product->get_name(),
			'url'   => get_permalink( $id ),
			'price' => wp_strip_all_tags( $product->get_price_html() ),
			'thumb' => get_the_post_thumbnail_url( $id, 'thumbnail' ),
			'meta'  => function_exists( 'fs_product_badges_line' ) ? fs_product_badges_line( $id ) : '',
		);
	}

	$payload = array(
		'items' => $items,
		'more'  => $items ? add_query_arg(
			array_filter(
				array(
					's'         => $query,
					'post_type' => 'product',
					'product_cat' => $cat,
				)
			),
			home_url( '/' )
		) : '',
	);

	set_transient( $key, $payload, (int) apply_filters( 'fs_live_search_ttl', 10 * MINUTE_IN_SECONDS ) );

	wp_send_json_success( $payload );
}
add_action( 'wp_ajax_fs_live_search', 'fs_ajax_live_search' );
add_action( 'wp_ajax_nopriv_fs_live_search', 'fs_ajax_live_search' );

/* -------------------------------------------------------------------------
   صفحه‌ی نتایج جست‌وجو
   ------------------------------------------------------------------------- */

/**
 * مرتب‌کردن نتایج صفحه‌ی جست‌وجو بر اساس همان امتیاز.
 *
 * وردپرس نتیجه‌ها را به ترتیب تاریخ می‌دهد. اینجا شناسه‌ها را بر اساس ربط
 * مرتب می‌کنیم و به کوئری برمی‌گردانیم — بدون دست‌زدن به خود کوئری، تا
 * صفحه‌بندی و شمارش دست‌نخورده بماند.
 *
 * @param WP_Post[] $posts نتایج.
 * @param WP_Query  $query کوئری.
 * @return WP_Post[]
 */
function fs_sort_search_results( $posts, $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() || ! $posts ) {
		return $posts;
	}

	$phrase = fs_search_normalize( $query->get( 's' ) );
	$terms  = fs_search_terms( $query->get( 's' ) );

	if ( ! $terms ) {
		return $posts;
	}

	$scored = array();

	foreach ( $posts as $i => $post ) {
		$product = 'product' === $post->post_type && function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;

		$scored[] = array(
			'post'  => $post,
			'order' => $i,
			'score' => $product ? fs_search_score( $product, $terms, $phrase ) : 0,
		);
	}

	usort(
		$scored,
		static function ( $a, $b ) {
			if ( $a['score'] === $b['score'] ) {
				return $a['order'] - $b['order'];
			}

			return $b['score'] - $a['score'];
		}
	);

	return wp_list_pluck( $scored, 'post' );
}
add_filter( 'the_posts', 'fs_sort_search_results', 20, 2 );
