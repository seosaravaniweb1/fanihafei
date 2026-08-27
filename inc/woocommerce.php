<?php
/**
 * یکپارچگی با ووکامرس: فیلدهای اختصاصی محصول و قلاب‌های فروشگاه.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

/**
 * واحدهای شمارش یک فایل.
 *
 * به‌جای سه فیلد جدا برای تعداد صفحه، تعداد سوال و تعداد فایل، یک عدد ثبت
 * می‌شود و همین‌جا مشخص می‌شود که آن عدد چه چیزی را می‌شمارد.
 *
 * @return array<string, string>
 */
function fs_amount_units() {
	return apply_filters(
		'fs_amount_units',
		array(
			'page'     => 'صفحه',
			'question' => 'سوال',
			'file'     => 'فایل',
		)
	);
}

/**
 * فیلدهای اختصاصی محصول.
 *
 * type: text | url | checkbox | textarea | select
 *
 * @return array<string, array{label:string,type:string,desc:string,options?:array}>
 */
function fs_product_fields() {
	return apply_filters(
		'fs_product_fields',
		array(
			'file_format'       => array(
				'label' => 'فرمت فایل',
				'type'  => 'text',
				'desc'  => 'مثلاً PDF یا «PDF + Word». اگر خالی بماند PDF در نظر گرفته می‌شود.',
			),
			'file_size'         => array(
				'label' => 'حجم فایل',
				'type'  => 'text',
				'desc'  => 'مثلاً ۱۲ مگابایت. خالی بگذارید تا نمایش داده نشود.',
			),
			'amount'            => array(
				'label' => 'مقدار',
				'type'  => 'text',
				'desc'  => 'فقط عدد. بسته به واحدی که در فیلد بعدی انتخاب می‌کنید، تعداد صفحه یا تعداد سوال یا تعداد فایل است.',
			),
			'amount_unit'       => array(
				'label'   => 'واحد مقدار',
				'type'    => 'select',
				'desc'    => 'این عدد چه چیزی را می‌شمارد؟ برای کتاب و جزوه «صفحه»، برای نمونه سوال «سوال» و برای بسته‌های تصویری و چندفایلی «فایل».',
				'options' => fs_amount_units(),
			),
			'author_name'       => array(
				'label' => 'نویسنده / پدیدآورنده',
				'type'  => 'text',
				'desc'  => 'اگر نویسنده را در «برندهای ووکامرس» ثبت کرده باشید خودکار خوانده می‌شود و نیازی به این فیلد نیست؛ این فیلد فقط برای زمانی است که برند ثبت نکرده‌اید.',
			),
			'translator_name'   => array(
				'label' => 'مترجم',
				'type'  => 'text',
				'desc'  => 'فقط برای کتاب‌های ترجمه‌شده. خالی بگذارید تا نمایش داده نشود.',
			),
			'has_answers'       => array(
				'label' => 'دارای پاسخنامه',
				'type'  => 'checkbox',
				'desc'  => 'اگر تیک بخورد، «پاسخنامه: دارد» در صفحه محصول نمایش داده می‌شود.',
			),
			'extras'            => array(
				'label' => 'ضمیمه‌ها',
				'type'  => 'textarea',
				'desc'  => 'هر خط یک ضمیمه. مثلاً: جزوه / خلاصه کتاب / نمونه سوال / فایل صوتی',
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
				'label' => 'لینک پیش‌نمایش رایگان',
				'type'  => 'url',
				'desc'  => 'بخشی از فایل برای پیش‌نمایش. خالی بگذارید تا نمایش داده نشود.',
			),
			'toc'               => array(
				'label' => 'سرفصل‌های دستی',
				'type'  => 'textarea',
				'desc'  => 'اختیاری. سرفصل‌ها به‌طور خودکار از تیترهای متن توضیحات ساخته می‌شوند؛ این فیلد فقط وقتی استفاده می‌شود که متن توضیحات هیچ تیتری نداشته باشد. هر خط یک سرفصل.',
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

	echo '<p class="form-field"><span class="description" style="margin:0">فقط فیلدهایی را پر کنید که برای این فایل معنا دارند؛ هر فیلد خالی اصلاً در صفحه محصول نمایش داده نمی‌شود.</span></p>';

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

			case 'select':
				woocommerce_wp_select(
					array(
						'id'          => $key,
						'label'       => $field['label'],
						'description' => $field['desc'],
						'desc_tip'    => true,
						'options'     => isset( $field['options'] ) ? $field['options'] : array(),
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
		} elseif ( 'select' === $field['type'] ) {
			$value   = sanitize_key( $raw );
			$allowed = isset( $field['options'] ) ? $field['options'] : array();

			if ( ! isset( $allowed[ $value ] ) ) {
				$value = '';
			}
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
 * ضمیمه‌های یک فایل — از فیلد «ضمیمه‌ها» (هر خط یک مورد).
 *
 * چک‌باکس‌های نسخه‌ی قبلی قالب (جزوه / کتاب آموزشی) هم خوانده می‌شوند تا
 * محصولات قدیمی مقدارشان را از دست ندهند.
 *
 * @param int $product_id شناسه محصول.
 * @return string[]
 */
function fs_get_product_attachments( $product_id ) {
	$out = array();
	$raw = fs_product_field( $product_id, 'extras' );

	if ( $raw ) {
		$out = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
	}

	// سازگاری با فیلدهای قدیمی.
	if ( fs_product_flag( $product_id, 'attach_note' ) ) {
		$out[] = 'جزوه';
	}

	if ( fs_product_flag( $product_id, 'attach_book' ) ) {
		$out[] = 'کتاب آموزشی';
	}

	return array_values( array_unique( $out ) );
}

/**
 * فرمت فایل — از فیلد محصول، وگرنه PDF.
 *
 * @param int $product_id شناسه محصول.
 * @return string
 */
function fs_product_format( $product_id ) {
	$value = fs_product_field( $product_id, 'file_format' );

	return $value ? $value : apply_filters( 'fs_default_file_format', 'PDF' );
}

/**
 * مقدار فایل به‌همراه واحدش — «۱۲۳ صفحه»، «۲۵۰ سوال» یا «۱۰ فایل».
 *
 * محصولاتی که پیش از یکی‌شدن این فیلدها ثبت شده‌اند هنوز مقدارشان در
 * page_count یا question_count است؛ آن‌ها هم خوانده می‌شوند تا داده‌ای از
 * دست نرود و نیازی به ویرایش دستی تک‌تک محصولات نباشد.
 *
 * @param int $product_id شناسه محصول.
 * @return array{n:string,unit:string,text:string}|null
 */
function fs_product_amount( $product_id ) {
	$units = fs_amount_units();
	$value = fs_product_field( $product_id, 'amount' );
	$unit  = fs_product_field( $product_id, 'amount_unit' );

	// سازگاری با فیلدهای قدیمیِ جدا.
	if ( '' === $value ) {
		$legacy_page = fs_product_field( $product_id, 'page_count' );

		if ( '' !== $legacy_page ) {
			$value = $legacy_page;
			$unit  = 'page';
		} else {
			$legacy_q = fs_product_field( $product_id, 'question_count' );

			if ( '' !== $legacy_q ) {
				$value = $legacy_q;
				$unit  = 'question';
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
		'n'    => fs_fa_num( $value ),
		'unit' => $units[ $unit ],
		'text' => fs_fa_num( $value ) . ' ' . $units[ $unit ],
	);
}

/**
 * نام تاکسونومی برندهای ووکامرس — هسته‌ی ووکامرس یا افزونه‌های رایج برند.
 *
 * @return string
 */
function fs_brand_taxonomy() {
	$candidates = apply_filters(
		'fs_brand_taxonomies',
		array( 'product_brand', 'pwb-brand', 'pa_brand', 'yith_product_brand', 'berocket_brand' )
	);

	foreach ( $candidates as $taxonomy ) {
		if ( taxonomy_exists( $taxonomy ) ) {
			return $taxonomy;
		}
	}

	return '';
}

/**
 * نویسنده‌ی فایل — اگر در «برندهای ووکامرس» ثبت شده باشد خودکار خوانده و به
 * صفحه‌ی همان برند لینک می‌شود؛ اگر برندی ثبت نشده باشد از فیلد متنی محصول
 * خوانده می‌شود و اگر آن هم خالی باشد اصلاً نمایش داده نمی‌شود.
 *
 * @param int $product_id شناسه محصول.
 * @return array<int, array{name:string,link:string}>
 */
function fs_get_product_authors( $product_id ) {
	$out      = array();
	$taxonomy = fs_brand_taxonomy();

	if ( $taxonomy ) {
		$terms = get_the_terms( $product_id, $taxonomy );

		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );

				$out[] = array(
					'name' => $term->name,
					'link' => is_wp_error( $link ) ? '' : $link,
				);
			}
		}
	}

	if ( ! $out ) {
		$manual = fs_product_field( $product_id, 'author_name' );

		if ( $manual ) {
			$out[] = array(
				'name' => $manual,
				'link' => '',
			);
		}
	}

	return $out;
}

/**
 * آخرین شاخه‌ی دسته‌بندی محصول — عمیق‌ترین دسته‌ای که محصول در آن قرار دارد.
 *
 * @param int $product_id شناسه محصول.
 * @return array{name:string,link:string}|null
 */
function fs_get_product_leaf_cat( $product_id ) {
	$terms = get_the_terms( $product_id, 'product_cat' );

	if ( ! $terms || is_wp_error( $terms ) ) {
		return null;
	}

	$deepest = null;
	$depth   = -1;

	foreach ( $terms as $term ) {
		$level  = count( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) );

		if ( $level > $depth ) {
			$depth   = $level;
			$deepest = $term;
		}
	}

	if ( ! $deepest ) {
		return null;
	}

	$link = get_term_link( $deepest );

	return array(
		'name' => $deepest->name,
		'link' => is_wp_error( $link ) ? '' : $link,
	);
}

/**
 * مشخصات محصول برای نمایش — فقط مواردی که مقدار دارند.
 *
 * ترتیب از پرکاربردترین به کم‌کاربردترین چیده شده: فرمت و تعداد صفحه همیشه
 * لازم‌اند، بقیه فقط اگر برای آن فایل پر شده باشند.
 *
 * @param int $product_id شناسه محصول.
 * @return array<int, array{k:string,v:string}>
 */
function fs_get_product_specs( $product_id ) {
	$out = array();

	$out[] = array(
		'k' => 'فرمت فایل',
		'v' => fs_product_format( $product_id ),
	);

	$amount = fs_product_amount( $product_id );

	if ( $amount ) {
		$out[] = array(
			'k' => 'تعداد ' . $amount['unit'],
			'v' => $amount['text'],
		);
	}

	$size = fs_product_field( $product_id, 'file_size' );

	if ( $size ) {
		$out[] = array(
			'k' => 'حجم فایل',
			'v' => fs_fa_num( $size ),
		);
	}

	$translator = fs_product_field( $product_id, 'translator_name' );

	if ( $translator ) {
		$out[] = array(
			'k' => 'مترجم',
			'v' => $translator,
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

	return apply_filters( 'fs_product_specs', $out, $product_id );
}

/**
 * نشان‌های کوتاه کارت محصول — طبق طرح فروشگاه فایل فقط سه چیز: آخرین شاخه‌ی
 * دسته‌بندی، فرمت فایل و تعداد صفحه. هر کدام که مقدار نداشته باشد حذف می‌شود.
 *
 * @param int $product_id شناسه محصول.
 * @return array<int, array{icon:string,text:string}>
 */
function fs_product_chips( $product_id ) {
	$chips = array();
	$cat   = fs_get_product_leaf_cat( $product_id );

	if ( $cat ) {
		$chips[] = array(
			'icon' => 'grid',
			'text' => $cat['name'],
		);
	}

	$chips[] = array(
		'icon' => 'file',
		'text' => fs_product_format( $product_id ),
	);

	$amount = fs_product_amount( $product_id );

	if ( $amount ) {
		$chips[] = array(
			'icon' => 'file-lines',
			'text' => $amount['text'],
		);
	}

	return apply_filters( 'fs_product_chips', $chips, $product_id );
}

/**
 * همان نشان‌ها به‌صورت یک خط متنی — برای جاهایی که فضای چیپ نیست.
 *
 * @param int $product_id شناسه محصول.
 * @return string
 */
function fs_product_badges_line( $product_id ) {
	return implode( ' · ', wp_list_pluck( fs_product_chips( $product_id ), 'text' ) );
}

/**
 * سرفصل‌های محصول — نسخه‌ی دستی، فقط به‌عنوان جایگزین وقتی متن توضیحات
 * هیچ تیتری ندارد. سرفصل خودکار در fs_toc_from_content() ساخته می‌شود.
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
	/*
	 * نکته‌ی مهم معماری:
	 *
	 * قالب چیدمان خودش را دارد، ولی حق ندارد قلاب‌های استاندارد ووکامرس را
	 * حذف کند — افزونه‌ها (نشان تخفیف، مقایسه، لیست علاقه‌مندی، راهنمای سایز،
	 * نظرسنجی، پیکسل‌های تبلیغاتی) دقیقاً روی همین قلاب‌ها می‌نشینند و اگر قلاب
	 * اجرا نشود بی‌صدا از کار می‌افتند.
	 *
	 * پس راه‌حل درست این است: فقط کال‌بک‌های پیش‌فرضِ خودِ ووکامرس که خروجی‌شان
	 * با چیدمان قالب تکراری می‌شود برداشته شوند، اما خود do_action سر جایش
	 * اجرا شود تا هر افزونه‌ی دیگری که روی آن نشسته کار کند.
	 */

	// پوشش‌ها و سایدبار پیش‌فرض — قالب ساختار خودش را دارد.
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

	// خلاصه‌ی محصول: عنوان، قیمت، توضیح کوتاه و دکمه‌ی خرید را خود قالب می‌چیند.
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );

	// تب‌ها، آپسل و محصولات مرتبط را قالب با طرح خودش رندر می‌کند.
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
	remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );

	// نوار مرتب‌سازی و شمارنده‌ی آرشیو، جای خودش در طرح قالب هست.
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
	remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

	// کارت محصول: تصویر، عنوان، قیمت و دکمه در قالب کارت خود ما هستند.
	remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
	remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
	remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
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

	fs_bump_product_views( $post->ID );
}
add_action( 'template_redirect', 'fs_track_product_view', 20 );

/**
 * آستانه‌ای که پس از آن بازدید هر آی‌پی فقط یک بار شمرده می‌شود.
 *
 * @return int
 */
function fs_views_unique_after() {
	return (int) apply_filters( 'fs_views_unique_after', 100 );
}

/**
 * نشانی آی‌پی بازدیدکننده — فقط برای یکتاسازی شمارش، هش‌شده ذخیره می‌شود.
 *
 * @return string
 */
function fs_visitor_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	// پشت کش یا پراکسی، آی‌پی واقعی در سرآیند فوروارد می‌آید.
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' ) as $header ) {
		if ( empty( $_SERVER[ $header ] ) ) {
			continue;
		}

		$forwarded = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
		$candidate = trim( $forwarded[0] );

		if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
			$ip = $candidate;
			break;
		}
	}

	return $ip;
}

/**
 * شمارش خودکار بازدید محصول.
 *
 * تا وقتی شمارنده به آستانه نرسیده، هر بار باز شدن صفحه یک بازدید می‌خورد تا
 * محصول تازه سریع جان بگیرد. بعد از آن شمارش واقع‌بینانه می‌شود و هر آی‌پی
 * فقط یک بار شمرده می‌شود؛ نشانی آی‌پی هم خام ذخیره نمی‌شود، فقط هشِ آن.
 *
 * ربات‌ها و مدیرِ در حال ویرایش شمرده نمی‌شوند.
 *
 * @param int $product_id شناسه محصول.
 * @return void
 */
function fs_bump_product_views( $product_id ) {
	$product_id = (int) $product_id;

	if ( ! $product_id || is_preview() ) {
		return;
	}

	// خزنده‌ها شمارنده را باد می‌کنند.
	if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$agent = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) );

		foreach ( array( 'bot', 'crawl', 'spider', 'slurp', 'preview', 'headless' ) as $needle ) {
			if ( false !== strpos( $agent, $needle ) ) {
				return;
			}
		}
	}

	$key   = apply_filters( 'fs_views_meta_key', 'view_count' );
	$count = (int) get_post_meta( $product_id, $key, true );

	// از آستانه به بعد: هر آی‌پی فقط یک بازدید.
	if ( $count >= fs_views_unique_after() ) {
		$ip = fs_visitor_ip();

		if ( ! $ip ) {
			return;
		}

		$seen_key = 'fs_seen_' . md5( $product_id . '|' . $ip );

		if ( false !== get_transient( $seen_key ) ) {
			return;
		}

		set_transient( $seen_key, 1, (int) apply_filters( 'fs_views_ip_ttl', MONTH_IN_SECONDS ) );
	}

	update_post_meta( $product_id, $key, $count + 1 );
}

/**
 * تعداد محصول در هر صفحه آرشیو.
 *
 * @return int
 */
function fs_products_per_page() {
	return (int) apply_filters( 'fs_products_per_page', 12 );
}
add_filter( 'loop_shop_per_page', 'fs_products_per_page', 20 );


// جعبه‌ی «چرا ما؟» صفحه محصول در تب «صفحه محصول» تنظیمات قالب مدیریت می‌شود؛
// نگاه کنید به fs_get_why_box() در inc/theme-settings.php.

/**
 * شناسه‌ی لنگر برای یک تیتر فارسی.
 *
 * از sanitize_title استفاده نمی‌کنیم چون متن فارسی را درصدی (percent-encoded)
 * می‌کند و آن‌وقت شناسه‌ی تگ با فرگمنت لینک یکی نمی‌شود؛ مرورگر فرگمنت را
 * پیش از مقایسه رمزگشایی می‌کند. اینجا شناسه به‌صورت یونیکد خام ساخته می‌شود
 * تا هم خوانا بماند و هم دقیقاً با href مطابقت داشته باشد.
 *
 * @param string $text متن تیتر.
 * @return string
 */
function fs_anchor_slug( $text ) {
	$text = wp_strip_all_tags( (string) $text );

	// نیم‌فاصله و فاصله‌ی صفرعرض حذف می‌شوند تا واژه‌ها به‌هم نچسبند اما تکه‌تکه هم نشوند.
	$slug = preg_replace( '/[\x{200c}\x{200b}\x{200e}\x{200f}]/u', '', $text );

	// هر جداکننده‌ای به خط تیره.
	$slug = preg_replace( '/[\s\.\/_]+/u', '-', trim( (string) $slug ) );

	// فقط حرف، رقم و خط تیره باقی می‌ماند.
	$slug = preg_replace( '/[^\p{L}\p{N}\-]+/u', '', (string) $slug );
	$slug = preg_replace( '/-+/', '-', (string) $slug );
	$slug = trim( (string) $slug, '-' );

	return '' === $slug ? 'fs-h' : $slug;
}

/**
 * ساخت فهرست سرفصل‌ها از روی تیترهای خود متن.
 *
 * تیترهای h2/h3 متن توضیحات پیدا می‌شوند، به هرکدام یک شناسه‌ی یکتا داده
 * می‌شود و فهرستی از لینک‌های لنگری (شارپ‌لینک) برگردانده می‌شود؛ کاربر با
 * کلیک روی هر سرفصل دقیقاً به همان تیتر در متن می‌رود.
 *
 * @param string $html متن توضیحات.
 * @return array{html:string,items:array<int, array{id:string,text:string,level:int}>}
 */
function fs_toc_from_content( $html ) {
	$html  = (string) $html;
	$items = array();

	if ( ! trim( $html ) || ! preg_match( '/<h[23][\s>]/i', $html ) ) {
		return array(
			'html'  => $html,
			'items' => array(),
		);
	}

	$used = array();

	$html = preg_replace_callback(
		'#<(h[23])([^>]*)>(.*?)</\1>#is',
		function ( $m ) use ( &$items, &$used ) {
			$tag   = strtolower( $m[1] );
			$attrs = $m[2];
			$text  = trim( wp_strip_all_tags( $m[3] ) );

			if ( '' === $text ) {
				return $m[0];
			}

			// اگر خود تیتر از قبل شناسه دارد، همان نگه داشته می‌شود.
			if ( preg_match( '/\sid=["\']([^"\']+)["\']/i', $attrs, $has ) ) {
				$id = $has[1];
			} else {
				$id   = fs_anchor_slug( $text );
				$base = $id;
				$n    = 2;

				while ( isset( $used[ $id ] ) ) {
					$id = $base . '-' . $n;
					++$n;
				}

				$attrs .= ' id="' . esc_attr( $id ) . '"';
			}

			$used[ $id ] = true;

			$items[] = array(
				'id'    => $id,
				'text'  => $text,
				'level' => 'h2' === $tag ? 2 : 3,
			);

			return '<' . $tag . $attrs . ' class="fs-anchor">' . $m[3] . '</' . $tag . '>';
		},
		$html
	);

	return array(
		'html'  => $html,
		'items' => $items,
	);
}

/**
 * تعداد فروش موفق یک محصول — از شمارنده‌ی خود ووکامرس.
 *
 * @param int $product_id شناسه محصول.
 * @return int
 */
function fs_product_sales( $product_id ) {
	return (int) get_post_meta( $product_id, 'total_sales', true );
}

/**
 * تبدیل سرفصل‌های دستی (هر خط یک مورد) به ساختار آیتم‌های فهرست.
 *
 * این‌ها لینک لنگری ندارند چون تیتری در متن نیست که به آن وصل شوند.
 *
 * @param string[] $lines خطوط سرفصل.
 * @return array<int, array{id:string,text:string,level:int}>
 */
function fs_toc_lines_to_items( $lines ) {
	$out = array();

	foreach ( (array) $lines as $line ) {
		$line = trim( (string) $line );

		if ( '' === $line ) {
			continue;
		}

		$out[] = array(
			'id'    => '',
			'text'  => $line,
			'level' => 2,
		);
	}

	return $out;
}
