<?php
/**
 * تنظیمات پاورقی — تب «فوتر» در «تنظیمات قالب».
 *
 * پاورقی از پنج بخش ساخته می‌شود: کارت‌های تماس، معرفی سایت، دو ستون لینک که
 * از فهرست‌های وردپرس می‌آیند، ستون محصولات (جدیدترین‌ها خودکار و
 * پربازدیدترین‌ها دستی) و ستون نمادها با تصاویر بانک‌ها.
 *
 * ذخیره با اجاکس انجام می‌شود چون بخش‌های تصویری و انتخاب محصول با جاوااسکریپت
 * ساخته می‌شوند و فرم معمولی برایشان دست‌وپاگیر است.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

const FS_FOOTER_OPTION = 'fs_footer_settings';

/**
 * مقادیر پیش‌فرض.
 *
 * @return array
 */
function fs_footer_defaults() {
	return array(
		'desc'          => '',
		'contacts'      => array(),
		'newest_title'  => 'جدیدترین‌ها',
		'popular_title' => 'پربازدیدترین‌ها',
		'newest_count'  => 3,
		'popular'       => array(),
		'trust_title'   => 'نمادهای اعتماد',
		'trust_html'    => '',
		'banks'         => array(),
		'banks_note'    => 'قابل خرید از همه بانک‌ها',
	);
}

/**
 * خواندن تنظیمات پاورقی.
 *
 * @return array
 */
function fs_get_footer_settings() {
	$saved = get_option( FS_FOOTER_OPTION, array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return wp_parse_args( $saved, fs_footer_defaults() );
}

/**
 * کارت‌های تماس بالای پاورقی — فقط مواردی که عنوان دارند.
 *
 * @return array<int, array{icon:string,title:string,value:string,link:string}>
 */
function fs_footer_contacts() {
	$out = array();

	foreach ( (array) fs_get_footer_settings()['contacts'] as $row ) {
		$title = isset( $row['title'] ) ? trim( $row['title'] ) : '';

		if ( '' === $title ) {
			continue;
		}

		$out[] = array(
			'icon'  => fs_valid_icon( isset( $row['icon'] ) ? $row['icon'] : '' ),
			'title' => $title,
			'value' => isset( $row['value'] ) ? trim( $row['value'] ) : '',
			'link'  => isset( $row['link'] ) ? trim( $row['link'] ) : '',
		);
	}

	return $out;
}

/**
 * تگ‌ها و اتریبیوت‌های مجاز برای کد نمادها.
 *
 * کد اعتماد الکترونیکی و درگاه‌ها یک لینک و یک تصویر است. onclick و script
 * عمداً مجاز نیستند: این مقدار در همه‌ی صفحات سایت چاپ می‌شود و اگر روزی حساب
 * مدیری لو برود، اجازه‌ی اسکریپت یعنی اجرای کد روی کل سایت.
 *
 * @return array
 */
function fs_trust_allowed_html() {
	return array(
		'a'   => array(
			'href'           => true,
			'target'         => true,
			'rel'            => true,
			'title'          => true,
			'style'          => true,
			'class'          => true,
			'referrerpolicy' => true,
		),
		'img' => array(
			'src'            => true,
			'alt'            => true,
			'width'          => true,
			'height'         => true,
			'style'          => true,
			'class'          => true,
			'loading'        => true,
			'code'           => true,
			'referrerpolicy' => true,
		),
		'br'  => array(),
		'div' => array(
			'class' => true,
			'style' => true,
		),
	);
}

/**
 * محصولات ستون «پربازدیدترین‌ها» — دقیقاً همان‌هایی که مدیر انتخاب کرده و به
 * همان ترتیب.
 *
 * @return array<int, array>
 */
function fs_footer_popular_products() {
	if ( ! fs_has_woo() ) {
		return array();
	}

	$ids = array_filter( array_map( 'absint', (array) fs_get_footer_settings()['popular'] ) );

	if ( ! $ids ) {
		return array();
	}

	$out = array();

	foreach ( $ids as $i => $id ) {
		$product = wc_get_product( $id );

		if ( ! $product || 'publish' !== $product->get_status() ) {
			continue;
		}

		$out[] = fs_product_to_card( $product, $i );
	}

	return $out;
}

/**
 * محصولات ستون «جدیدترین‌ها» — خودکار.
 *
 * @return array<int, array>
 */
function fs_footer_newest_products() {
	$count = (int) fs_get_footer_settings()['newest_count'];

	return fs_get_newest( max( 1, min( 8, $count ) ) );
}

/* -------------------------------------------------------------------------
   بخش مدیریت
   ------------------------------------------------------------------------- */

/**
 * بارگذاری اسکریپت‌های تب فوتر.
 *
 * @param string $hook شناسه صفحه.
 * @return void
 */
function fs_footer_admin_assets( $hook ) {
	if ( false === strpos( $hook, 'fs-theme-settings' ) ) {
		return;
	}

	wp_enqueue_media();

	wp_enqueue_script(
		'fs-admin-footer',
		get_theme_file_uri( 'assets/js/admin-footer.js' ),
		array( 'jquery' ),
		FS_VERSION,
		true
	);

	wp_localize_script(
		'fs-admin-footer',
		'fsFooter',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'fs_footer_save' ),
			'icons'   => fs_icons_sprite(),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'fs_footer_admin_assets' );

/**
 * همه‌ی آیکون‌های قابل انتخاب به‌صورت SVG آماده — برای پیش‌نمایش زنده در پنل.
 *
 * @return array<string, string>
 */
function fs_icons_sprite() {
	$out = array();

	foreach ( array_keys( fs_selectable_icons() ) as $key ) {
		$out[ $key ] = fs_icon( $key, 17, array( 'stroke' => '#6d28d9', 'width' => '2' ) );
	}

	return $out;
}

/**
 * ذخیره‌ی تنظیمات پاورقی با اجاکس.
 *
 * @return void
 */
function fs_ajax_footer_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
	}

	check_ajax_referer( 'fs_footer_save', 'nonce' );

	$raw = isset( $_POST['payload'] ) ? json_decode( wp_unslash( $_POST['payload'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- هر کلید جداگانه پاک‌سازی می‌شود.

	if ( ! is_array( $raw ) ) {
		wp_send_json_error( array( 'message' => 'داده‌ی ارسالی معتبر نیست.' ), 400 );
	}

	$clean = fs_footer_defaults();

	$clean['desc']          = isset( $raw['desc'] ) ? sanitize_textarea_field( $raw['desc'] ) : '';
	$clean['newest_title']  = isset( $raw['newest_title'] ) ? sanitize_text_field( $raw['newest_title'] ) : '';
	$clean['popular_title'] = isset( $raw['popular_title'] ) ? sanitize_text_field( $raw['popular_title'] ) : '';
	$clean['trust_title']   = isset( $raw['trust_title'] ) ? sanitize_text_field( $raw['trust_title'] ) : '';
	$clean['banks_note']    = isset( $raw['banks_note'] ) ? sanitize_text_field( $raw['banks_note'] ) : '';
	$clean['newest_count']  = isset( $raw['newest_count'] ) ? max( 1, min( 8, (int) $raw['newest_count'] ) ) : 3;
	$clean['trust_html']    = isset( $raw['trust_html'] ) ? wp_kses( $raw['trust_html'], fs_trust_allowed_html() ) : '';

	$clean['contacts'] = array();

	foreach ( (array) ( $raw['contacts'] ?? array() ) as $row ) {
		$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';

		if ( '' === $title ) {
			continue;
		}

		$clean['contacts'][] = array(
			'icon'  => fs_valid_icon( isset( $row['icon'] ) ? sanitize_key( $row['icon'] ) : '' ),
			'title' => $title,
			'value' => isset( $row['value'] ) ? sanitize_text_field( $row['value'] ) : '',
			'link'  => isset( $row['link'] ) ? esc_url_raw( $row['link'] ) : '',
		);
	}

	$clean['popular'] = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $raw['popular'] ?? array() ) ) ) ) );
	$clean['banks']   = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $raw['banks'] ?? array() ) ) ) ) );

	update_option( FS_FOOTER_OPTION, $clean );

	wp_send_json_success( array( 'message' => 'تنظیمات پاورقی ذخیره شد.' ) );
}
add_action( 'wp_ajax_fs_footer_save', 'fs_ajax_footer_save' );

/**
 * جست‌وجوی محصول بر اساس عنوان — برای انتخاب محصولات ستون پربازدیدترین‌ها.
 *
 * @return void
 */
function fs_ajax_search_products() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
	}

	check_ajax_referer( 'fs_footer_save', 'nonce' );

	$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';

	if ( mb_strlen( $term ) < 2 ) {
		wp_send_json_success( array( 'items' => array() ) );
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => 12,
			's'                      => $term,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$items = array();

	foreach ( $query->posts as $post ) {
		$items[] = array(
			'id'    => $post->ID,
			'title' => get_the_title( $post ),
			'thumb' => get_the_post_thumbnail_url( $post, 'thumbnail' ),
		);
	}

	wp_send_json_success( array( 'items' => $items ) );
}
add_action( 'wp_ajax_fs_search_products', 'fs_ajax_search_products' );

/**
 * چاپ یک کارت تماس در پنل.
 *
 * @param array $row داده‌ی ردیف.
 * @return void
 */
function fs_footer_contact_row( $row = array() ) {
	$icon = fs_valid_icon( isset( $row['icon'] ) ? $row['icon'] : '' );
	?>
	<div class="fs-frow" data-contact-row>
		<span class="fs-frow__preview" data-icon-preview>
			<?php fs_the_icon( $icon, 17, array( 'stroke' => '#6d28d9', 'width' => '2' ) ); ?>
		</span>

		<select class="fs-frow__icon" data-icon-select>
			<?php foreach ( fs_selectable_icons() as $fs_key => $fs_label ) : ?>
				<option value="<?php echo esc_attr( $fs_key ); ?>" <?php selected( $icon, $fs_key ); ?>>
					<?php echo esc_html( $fs_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<input type="text" class="fs-frow__title" placeholder="عنوان (مثلاً فقط پیامک)"
			value="<?php echo esc_attr( isset( $row['title'] ) ? $row['title'] : '' ); ?>">
		<input type="text" class="fs-frow__value" placeholder="توضیح (مثلاً ۰۹۱۹…)"
			value="<?php echo esc_attr( isset( $row['value'] ) ? $row['value'] : '' ); ?>">
		<input type="url" class="fs-frow__link" dir="ltr" placeholder="لینک (اختیاری)"
			value="<?php echo esc_attr( isset( $row['link'] ) ? $row['link'] : '' ); ?>">

		<button type="button" class="button-link fs-frow__del">حذف</button>
	</div>
	<?php
}

/**
 * چاپ یک محصول انتخاب‌شده در ستون پربازدیدترین‌ها.
 *
 * @param int $id شناسه محصول.
 * @return void
 */
function fs_footer_product_chip( $id ) {
	$id    = (int) $id;
	$title = get_the_title( $id );

	if ( ! $title ) {
		return;
	}

	$thumb = get_the_post_thumbnail_url( $id, 'thumbnail' );
	?>
	<div class="fs-fchip" data-product-id="<?php echo esc_attr( $id ); ?>">
		<?php if ( $thumb ) : ?>
			<img src="<?php echo esc_url( $thumb ); ?>" alt="" width="40" height="40">
		<?php endif; ?>
		<span class="fs-fchip__title"><?php echo esc_html( $title ); ?></span>
		<button type="button" class="fs-fchip__del" aria-label="حذف">&times;</button>
	</div>
	<?php
}

/**
 * چاپ یک تصویر بانک در پنل.
 *
 * @param int $id شناسه پیوست.
 * @return void
 */
function fs_footer_bank_row( $id = 0 ) {
	$id  = (int) $id;
	$src = $id ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
	?>
	<div class="fs-fbank" data-bank-id="<?php echo esc_attr( $id ); ?>">
		<img src="<?php echo esc_url( $src ); ?>" alt="" width="40" height="40" <?php echo $src ? '' : 'hidden'; ?>>
		<button type="button" class="fs-fbank__del" aria-label="حذف">&times;</button>
	</div>
	<?php
}

/**
 * محتوای تب «فوتر» در صفحه تنظیمات قالب.
 *
 * @return void
 */
function fs_footer_tab_content() {
	$settings = fs_get_footer_settings();
	?>
	<div class="fs-footer-wrap">

		<h2 style="margin-top:24px">معرفی سایت</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="fs-footer-desc">متن معرفی</label></th>
				<td>
					<textarea id="fs-footer-desc" rows="5" class="large-text"><?php echo esc_textarea( $settings['desc'] ); ?></textarea>
					<p class="description">متنی که زیر لوگو در پاورقی می‌آید. خالی بگذارید تا «شرح کوتاه» سایت (تنظیمات ← عمومی) نمایش داده شود.</p>
				</td>
			</tr>
		</table>

		<h2>کارت‌های تماس (بالای پاورقی)</h2>
		<p class="description">هر کارت یک آیکون، یک عنوان و یک توضیح دارد. کارت بدون عنوان ذخیره نمی‌شود.</p>
		<div id="fs-contact-rows">
			<?php
			$fs_rows = $settings['contacts'] ? $settings['contacts'] : array( array() );

			foreach ( $fs_rows as $fs_row ) {
				fs_footer_contact_row( $fs_row );
			}
			?>
		</div>
		<button type="button" class="button" id="fs-contact-add">افزودن کارت تماس</button>
		<template id="fs-contact-tpl"><?php fs_footer_contact_row(); ?></template>

		<h2 style="margin-top:28px">ستون محصولات</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="fs-newest-title">عنوان تب اول</label></th>
				<td>
					<input type="text" id="fs-newest-title" class="regular-text" value="<?php echo esc_attr( $settings['newest_title'] ); ?>">
					<p class="description">این تب خودکار پر می‌شود: تازه‌ترین محصولات منتشرشده.</p>
				</td>
			</tr>
			<tr>
				<th><label for="fs-newest-count">تعداد نمایش</label></th>
				<td><input type="number" id="fs-newest-count" min="1" max="8" value="<?php echo esc_attr( $settings['newest_count'] ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th><label for="fs-popular-title">عنوان تب دوم</label></th>
				<td><input type="text" id="fs-popular-title" class="regular-text" value="<?php echo esc_attr( $settings['popular_title'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="fs-product-search">محصولات تب دوم</label></th>
				<td>
					<div class="fs-fsearch">
						<input type="search" id="fs-product-search" class="regular-text" placeholder="نام محصول را بنویسید…" autocomplete="off">
						<div class="fs-fsearch__results" id="fs-product-results" hidden></div>
					</div>
					<div class="fs-fchips" id="fs-popular-chips">
						<?php
						foreach ( (array) $settings['popular'] as $fs_pid ) {
							fs_footer_product_chip( $fs_pid );
						}
						?>
					</div>
					<p class="description">نام محصول را بنویسید تا نتایج نمایش داده شود، بعد رویش کلیک کنید تا اضافه شود. ترتیب نمایش همان ترتیب افزودن است.</p>
				</td>
			</tr>
		</table>

		<h2 style="margin-top:28px">ستون نمادها</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="fs-trust-title">عنوان ستون</label></th>
				<td><input type="text" id="fs-trust-title" class="regular-text" value="<?php echo esc_attr( $settings['trust_title'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="fs-trust-html">کد نمادها</label></th>
				<td>
					<textarea id="fs-trust-html" rows="6" class="large-text code" dir="ltr"><?php echo esc_textarea( $settings['trust_html'] ); ?></textarea>
					<p class="description">کد اعتماد الکترونیکی (اینماد)، ساماندهی و درگاه پرداخت را همین‌جا بگذارید. فقط تگ‌های لینک و تصویر مجاز است؛ اسکریپت به‌دلیل امنیتی پذیرفته نمی‌شود.</p>
				</td>
			</tr>
			<tr>
				<th>تصاویر بانک‌ها</th>
				<td>
					<div class="fs-fbanks" id="fs-bank-rows">
						<?php
						foreach ( (array) $settings['banks'] as $fs_bank ) {
							fs_footer_bank_row( $fs_bank );
						}
						?>
					</div>
					<button type="button" class="button" id="fs-bank-add">افزودن تصویر بانک</button>
					<p class="description">فقط تصویر؛ به‌صورت مربعی زیر نمادها نمایش داده می‌شوند.</p>
				</td>
			</tr>
			<tr>
				<th><label for="fs-banks-note">متن زیر تصاویر</label></th>
				<td><input type="text" id="fs-banks-note" class="regular-text" value="<?php echo esc_attr( $settings['banks_note'] ); ?>"></td>
			</tr>
		</table>

		<p style="margin-top:26px">
			<button type="button" class="button button-primary button-hero" id="fs-footer-save">ذخیره تنظیمات فوتر</button>
			<span class="fs-footer-msg" id="fs-footer-msg"></span>
		</p>

		<p class="description">
			دو ستون لینک پاورقی از فهرست‌های وردپرس خوانده می‌شوند:
			<strong>نمایش ← فهرست‌ها</strong> و انتخاب محل‌های «پاورقی — ستون اول/دوم». عنوان هر ستون، نام همان فهرست است.
		</p>

	</div>

	<style>
		.fs-frow { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 10px; margin-bottom: 8px; max-width: 980px; }
		.fs-frow__preview { flex: none; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #f5f3ff; border: 1px solid #ddd6fe; }
		.fs-frow__icon { flex: none; width: 150px; }
		.fs-frow__title, .fs-frow__value, .fs-frow__link { flex: 1; min-width: 0; }
		.fs-frow__del { color: #b32d2e; flex: none; }

		.fs-fsearch { position: relative; max-width: 420px; }
		.fs-fsearch__results { position: absolute; z-index: 5; inset-inline: 0; top: 100%; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; max-height: 260px; overflow-y: auto; box-shadow: 0 8px 20px rgba(0,0,0,.12); }
		.fs-fsearch__item { display: flex; align-items: center; gap: 9px; padding: 8px 10px; cursor: pointer; border-bottom: 1px solid #f0f0f1; }
		.fs-fsearch__item:hover { background: #f5f3ff; }
		.fs-fsearch__item img { width: 30px; height: 30px; object-fit: cover; border-radius: 5px; }
		.fs-fsearch__empty { padding: 10px; color: #787c82; }

		.fs-fchips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
		.fs-fchip { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #dcdcde; border-radius: 999px; padding: 5px 12px 5px 5px; }
		.fs-fchip img { width: 26px; height: 26px; object-fit: cover; border-radius: 50%; }
		.fs-fchip__title { font-size: 12px; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.fs-fchip__del { border: 0; background: none; color: #b32d2e; font-size: 17px; line-height: 1; cursor: pointer; }

		.fs-fbanks { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
		.fs-fbank { position: relative; width: 62px; height: 62px; border: 1px solid #dcdcde; border-radius: 10px; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; }
		.fs-fbank img { width: 100%; height: 100%; object-fit: contain; padding: 6px; }
		.fs-fbank__del { position: absolute; top: 1px; inset-inline-end: 3px; border: 0; background: none; color: #b32d2e; cursor: pointer; font-size: 15px; line-height: 1; }

		.fs-footer-msg { margin-inline-start: 12px; font-weight: 600; }
		.fs-footer-msg.is-ok { color: #007017; }
		.fs-footer-msg.is-err { color: #b32d2e; }
	</style>
	<?php
}
