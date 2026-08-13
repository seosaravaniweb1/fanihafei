<?php
/**
 * یکپارچگی با ووکامرس: فیلدهای اختصاصی محصول و قلاب‌های فروشگاه.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

/**
 * فیلدهای اختصاصی محصول.
 *
 * type: text | url | checkbox | textarea
 *
 * @return array<string, array{label:string,type:string,desc:string}>
 */
function fs_product_fields() {
	return apply_filters(
		'fs_product_fields',
		array(
			'question_count'    => array(
				'label' => 'تعداد سوالات',
				'type'  => 'text',
				'desc'  => 'فقط عدد. مثلاً ۱۷۰',
			),
			'computer_code'     => array(
				'label' => 'کد رایانه‌کار',
				'type'  => 'text',
				'desc'  => 'کد استاندارد رشته با اسلش. مثلاً ۲۱/۴۵۲۶۶',
			),
			'has_answers'       => array(
				'label' => 'دارای پاسخنامه',
				'type'  => 'checkbox',
				'desc'  => 'اگر تیک بخورد، «پاسخنامه: دارد» در صفحه محصول نمایش داده می‌شود.',
			),
			'attach_note'       => array(
				'label' => 'ضمیمه: جزوه',
				'type'  => 'checkbox',
				'desc'  => '',
			),
			'attach_book'       => array(
				'label' => 'ضمیمه: کتاب آموزشی',
				'type'  => 'checkbox',
				'desc'  => '',
			),
			'audio_url'         => array(
				'label' => 'فایل صوتی معرفی',
				'type'  => 'url',
				'desc'  => 'نشانی مستقیم فایل صوتی (mp3). خالی بگذارید تا پخش‌کننده نمایش داده نشود.',
			),
			'video_url'         => array(
				'label' => 'ویدیوی معرفی',
				'type'  => 'url',
				'desc'  => 'نشانی مستقیم ویدیو (mp4) یا نشانی صفحه/امبد آپارات. در پاپ‌آپ پخش می‌شود.',
			),
			'free_download_url' => array(
				'label' => 'لینک دانلود رایگان',
				'type'  => 'url',
				'desc'  => 'بخشی از نمونه سوالات برای پیش‌نمایش. خالی بگذارید تا نمایش داده نشود.',
			),
			'view_count'        => array(
				'label' => 'تعداد بازدید',
				'type'  => 'text',
				'desc'  => 'برای نمایش روی کارت‌ها. خالی بگذارید تا نمایش داده نشود.',
			),
			'toc'               => array(
				'label' => 'سرفصل سوالات',
				'type'  => 'textarea',
				'desc'  => 'هر خط یک سرفصل',
			),
		)
	);
}

/**
 * افزودن تب «مشخصات فایل» به داده‌های محصول.
 *
 * @param array $tabs تب‌ها.
 * @return array
 */
function fs_product_data_tab( $tabs ) {
	$tabs['fs_specs'] = array(
		'label'    => 'مشخصات فایل',
		'target'   => 'fs_specs_data',
		'class'    => array(),
		'priority' => 21,
	);

	return $tabs;
}
add_filter( 'woocommerce_product_data_tabs', 'fs_product_data_tab' );

/**
 * محتوای تب مشخصات فایل.
 *
 * @return void
 */
function fs_product_data_panel() {
	echo '<div id="fs_specs_data" class="panel woocommerce_options_panel">';

	echo '<p class="form-field"><span class="description" style="margin:0">فرمت فایل همه محصولات PDF است و نیازی به ثبت ندارد.</span></p>';

	foreach ( fs_product_fields() as $key => $field ) {
		switch ( $field['type'] ) {
			case 'textarea':
				woocommerce_wp_textarea_input(
					array(
						'id'          => $key,
						'label'       => $field['label'],
						'description' => $field['desc'],
						'desc_tip'    => true,
						'rows'        => 5,
					)
				);
				break;

			case 'checkbox':
				woocommerce_wp_checkbox(
					array(
						'id'          => $key,
						'label'       => $field['label'],
						'description' => $field['desc'],
					)
				);
				break;

			default:
				woocommerce_wp_text_input(
					array(
						'id'          => $key,
						'label'       => $field['label'],
						'description' => $field['desc'],
						'desc_tip'    => true,
						'type'        => 'url' === $field['type'] ? 'url' : 'text',
					)
				);
		}
	}

	echo '</div>';
}
add_action( 'woocommerce_product_data_panels', 'fs_product_data_panel' );

/**
 * ذخیره فیلدهای اختصاصی.
 *
 * @param int $post_id شناسه محصول.
 * @return void
 */
function fs_save_product_fields( $post_id ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- ووکامرس پیش از این قلاب nonce را بررسی کرده است.
	foreach ( fs_product_fields() as $key => $field ) {
		if ( 'checkbox' === $field['type'] ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, 'yes' );
			} else {
				delete_post_meta( $post_id, $key );
			}
			continue;
		}

		if ( ! isset( $_POST[ $key ] ) ) {
			delete_post_meta( $post_id, $key );
			continue;
		}

		$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( 'textarea' === $field['type'] ) {
			$value = sanitize_textarea_field( $raw );
		} elseif ( 'url' === $field['type'] ) {
			$value = esc_url_raw( $raw );
		} else {
			$value = sanitize_text_field( $raw );
		}

		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
}
add_action( 'woocommerce_process_product_meta', 'fs_save_product_fields' );

/**
 * آیا فیلد چک‌باکسی فعال است؟
 *
 * @param int    $product_id شناسه محصول.
 * @param string $key        کلید.
 * @return bool
 */
function fs_product_flag( $product_id, $key ) {
	return 'yes' === get_post_meta( $product_id, $key, true );
}

/**
 * ضمیمه‌های تیک‌خورده.
 *
 * @param int $product_id شناسه محصول.
 * @return string[]
 */
function fs_get_product_attachments( $product_id ) {
	$out = array();

	if ( fs_product_flag( $product_id, 'attach_note' ) ) {
		$out[] = 'جزوه';
	}

	if ( fs_product_flag( $product_id, 'attach_book' ) ) {
		$out[] = 'کتاب آموزشی';
	}

	return $out;
}

/**
 * مشخصات محصول برای نمایش — فقط مواردی که مقدار دارند.
 *
 * @param int $product_id شناسه محصول.
 * @return array<int, array{k:string,v:string}>
 */
function fs_get_product_specs( $product_id ) {
	$out = array();

	// فرمت فایل همیشه PDF است.
	$out[] = array(
		'k' => 'فرمت فایل',
		'v' => 'PDF',
	);

	$questions = fs_product_field( $product_id, 'question_count' );

	if ( $questions ) {
		$out[] = array(
			'k' => 'تعداد سوالات',
			'v' => fs_fa_num( $questions ) . ' نمونه سوال',
		);
	}

	if ( fs_product_flag( $product_id, 'has_answers' ) ) {
		$out[] = array(
			'k' => 'پاسخنامه',
			'v' => 'دارد',
		);
	}

	$attachments = fs_get_product_attachments( $product_id );

	if ( $attachments ) {
		$out[] = array(
			'k' => 'ضمیمه‌ها',
			'v' => implode( ' + ', $attachments ),
		);
	}

	return $out;
}

/**
 * خط کوتاه نشان‌های محصول برای کارت‌ها — «PDF · پاسخنامه دارد · جزوه» و مانند آن،
 * فقط از روی فیلدهای واقعی همان محصول؛ اگر تیک نخورده باشد نشان داده نمی‌شود.
 *
 * @param int $product_id شناسه محصول.
 * @return string
 */
function fs_product_badges_line( $product_id ) {
	$parts = array( 'PDF' );

	if ( fs_product_flag( $product_id, 'has_answers' ) ) {
		$parts[] = 'پاسخنامه دارد';
	}

	foreach ( fs_get_product_attachments( $product_id ) as $fs_attachment ) {
		$parts[] = $fs_attachment;
	}

	return implode( ' · ', $parts );
}

/**
 * سرفصل‌های محصول.
 *
 * @param int $product_id شناسه محصول.
 * @return string[]
 */
function fs_get_product_toc( $product_id ) {
	$raw = fs_product_field( $product_id, 'toc' );

	if ( ! $raw ) {
		return array();
	}

	return array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
}

/**
 * نشانی امبد ویدیو — هم فایل مستقیم، هم آپارات.
 *
 * @param string $url نشانی خام.
 * @return array{type:string,src:string}
 */
function fs_video_source( $url ) {
	$url = trim( (string) $url );

	if ( ! $url ) {
		return array(
			'type' => '',
			'src'  => '',
		);
	}

	// فایل مستقیم.
	if ( preg_match( '/\.(mp4|webm|ogg|m4v)(\?.*)?$/i', $url ) ) {
		return array(
			'type' => 'file',
			'src'  => $url,
		);
	}

	// آپارات: هم نشانی صفحه (aparat.com/v/XXXX) و هم امبد.
	if ( false !== strpos( $url, 'aparat.com' ) ) {
		if ( preg_match( '#aparat\.com/(?:v|video/video/embed/videohash)/([A-Za-z0-9]+)#', $url, $m ) ) {
			return array(
				'type' => 'embed',
				'src'  => 'https://www.aparat.com/video/video/embed/videohash/' . $m[1] . '/vt/frame',
			);
		}

		return array(
			'type' => 'embed',
			'src'  => $url,
		);
	}

	return array(
		'type' => 'embed',
		'src'  => $url,
	);
}

/**
 * فعال‌سازی امتیازدهی ستاره‌ای روی فرم دیدگاه، حتی اگر در تنظیمات ووکامرس خاموش باشد.
 *
 * @return string
 */
function fs_enable_review_rating() {
	return 'yes';
}
add_filter( 'pre_option_woocommerce_enable_review_rating', 'fs_enable_review_rating' );

/**
 * حذف فیلد «نشانی وب‌سایت» از فرم دیدگاه — فقط نام، ایمیل، امتیاز و متن لازم است.
 *
 * @param array $fields فیلدهای پیش‌فرض.
 * @return array
 */
function fs_comment_form_fields( $fields ) {
	unset( $fields['url'] );

	return $fields;
}
add_filter( 'comment_form_default_fields', 'fs_comment_form_fields' );

/**
 * خلاصه‌ی امتیازهای واقعی محصول — میانگین، تعداد و نمودار میله‌ای ۱ تا ۵ ستاره.
 *
 * فقط از دیدگاه‌های واقعاً ثبت‌شده روی محصول ساخته می‌شود؛ اگر دیدگاهی نباشد
 * آرایه‌ی خالی برمی‌گردد و بخش نظرات اصلاً رندر نمی‌شود.
 *
 * @param WC_Product $product شیء محصول.
 * @return array{average:float,count:int,bars:array}|null
 */
function fs_get_review_summary( $product ) {
	$count = (int) $product->get_review_count();

	if ( ! $count ) {
		return null;
	}

	$average = (float) $product->get_average_rating();

	$counts = get_comments(
		array(
			'post_id' => $product->get_id(),
			'status'  => 'approve',
			'type'    => 'review',
			'count'   => false,
			'meta_key' => 'rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		)
	);

	$tally = array(
		5 => 0,
		4 => 0,
		3 => 0,
		2 => 0,
		1 => 0,
	);

	foreach ( $counts as $fs_comment ) {
		$rating = (int) get_comment_meta( $fs_comment->comment_ID, 'rating', true );

		if ( isset( $tally[ $rating ] ) ) {
			++$tally[ $rating ];
		}
	}

	$bars = array();

	foreach ( $tally as $stars => $n ) {
		$bars[] = array(
			'k'       => str_repeat( '★', $stars ),
			'percent' => $count ? round( ( $n / $count ) * 100 ) : 0,
		);
	}

	return array(
		'average' => $average,
		'count'   => $count,
		'bars'    => $bars,
	);
}

/**
 * دیدگاه‌های تاییدشده‌ی یک محصول به‌همراه امتیاز و حروف اول نام.
 *
 * @param int $product_id شناسه محصول.
 * @param int $limit      تعداد.
 * @return array
 */
function fs_get_product_reviews( $product_id, $limit = 20 ) {
	$comments = get_comments(
		array(
			'post_id' => $product_id,
			'status'  => 'approve',
			'type'    => 'review',
			'number'  => $limit,
		)
	);

	$out = array();

	foreach ( $comments as $fs_comment ) {
		$rating = (int) get_comment_meta( $fs_comment->comment_ID, 'rating', true );
		$name   = $fs_comment->comment_author;

		$out[] = array(
			'name'    => $name,
			'initial' => function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 ),
			'stars'   => $rating ? str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ) : '',
			'date'    => fs_fa_num( date_i18n( get_option( 'date_format' ), strtotime( $fs_comment->comment_date ) ) ),
			'text'    => $fs_comment->comment_content,
			'tint'    => fs_tints()[ crc32( $name ) % count( fs_tints() ) ][1],
		);
	}

	return $out;
}

/* -------------------------------------------------------------------------
   قلاب‌های ووکامرس
   ------------------------------------------------------------------------- */

/**
 * حذف پوشش‌ها و نوار کناری پیش‌فرض ووکامرس.
 *
 * @return void
 */
function fs_woo_unhook() {
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
}
add_action( 'init', 'fs_woo_unhook' );

/**
 * ثبت بازدید محصول برای بخش «قبلاً دیدی» صفحه اصلی.
 *
 * @return void
 */
function fs_track_product_view() {
	if ( ! is_singular( 'product' ) ) {
		return;
	}

	global $post;

	$viewed = empty( $_COOKIE['woocommerce_recently_viewed'] )
		? array()
		: (array) explode( '|', wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) );

	$viewed = array_filter( array_map( 'absint', $viewed ) );
	$keys   = array_flip( $viewed );

	if ( isset( $keys[ $post->ID ] ) ) {
		unset( $viewed[ $keys[ $post->ID ] ] );
	}

	$viewed[] = $post->ID;
	$viewed   = array_slice( $viewed, -15 );

	wc_setcookie( 'woocommerce_recently_viewed', implode( '|', $viewed ) );
}
add_action( 'template_redirect', 'fs_track_product_view', 20 );

/**
 * تعداد محصول در هر صفحه آرشیو.
 *
 * @return int
 */
function fs_products_per_page() {
	return (int) apply_filters( 'fs_products_per_page', 12 );
}
add_filter( 'loop_shop_per_page', 'fs_products_per_page', 20 );

// «تضمین‌های کنار دکمه خرید» اکنون در تب «صفحه محصول» تنظیمات قالب مدیریت می‌شود؛
// نگاه کنید به fs_get_guarantees() در inc/theme-settings.php.
