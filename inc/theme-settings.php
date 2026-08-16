<?php
/**
 * صفحه یکپارچه «تنظیمات قالب» — با تب‌های صفحه اصلی، صفحه محصول، هدر، فوتر
 * و پیشخوان کاربری.
 *
 * همه‌ی محتوای قابل‌ویرایش قالب (درباره سایت، پرسش‌های متداول، نشانه‌های اعتماد،
 * تضمین‌های صفحه محصول، سایدبار اطمینان، بخش‌های پاورقی و پیام پیشخوان مشتریان)
 * در یک آپشن ذخیره می‌شود و از یک صفحه با تب مدیریت می‌شود.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

const FS_SETTINGS_OPTION = 'fs_theme_settings';

/**
 * مقادیر پیش‌فرض.
 *
 * @return array
 */
function fs_theme_settings_defaults() {
	return array(
		// صفحه اصلی.
		'satisfaction'         => '',
		'trust_items'          => '',
		'about_title'          => '',
		'about_content'        => '',
		'faq'                  => array(),

		// صفحه محصول.
		'guarantees'           => '',
		'assurance_title'      => '',
		'assurances'           => array(),

		// هدر.
		'header_notice'        => '',
		'header_notice_link'   => '',
		'header_phone'         => '',

		// پاورقی.
		'footer_boxes'         => array(),
		'footer_about'         => '',
		'footer_products'      => 'yes',
		'footer_symbols'       => array(),
		'footer_banks'         => array(),
		'footer_banks_caption' => '',

		// پیشخوان کاربری.
		'dashboard_title'      => '',
		'dashboard_message'    => '',
	);
}

/**
 * خواندن تنظیمات ذخیره‌شده.
 *
 * @return array
 */
function fs_get_theme_settings() {
	$saved = get_option( FS_SETTINGS_OPTION, array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return wp_parse_args( $saved, fs_theme_settings_defaults() );
}

/**
 * آیکون‌های قابل انتخاب برای جعبه‌های بالای پاورقی.
 *
 * @return array<string, string>
 */
function fs_footer_box_icons() {
	return array(
		'phone'     => 'تلفن',
		'send'      => 'تلگرام / پیام‌رسان',
		'chat'      => 'چت پشتیبانی',
		'mail'      => 'ایمیل',
		'headset'   => 'پشتیبانی',
		'instagram' => 'اینستاگرام',
		'clock'     => 'ساعت کاری',
		'map'       => 'نشانی',
	);
}

/**
 * بخش «درباره سایت».
 *
 * @return array{title:string,content:string}|null
 */
function fs_get_about() {
	$settings = fs_get_theme_settings();

	if ( ! trim( wp_strip_all_tags( $settings['about_content'] ) ) ) {
		return null;
	}

	return array(
		'title'   => $settings['about_title'] ? $settings['about_title'] : 'درباره ما',
		'content' => $settings['about_content'],
	);
}

/**
 * پرسش‌های متداول.
 *
 * @return array<int, array{title:string,content:string}>
 */
function fs_get_faq() {
	$settings = fs_get_theme_settings();
	$out      = array();

	foreach ( (array) $settings['faq'] as $item ) {
		$q = isset( $item['q'] ) ? trim( $item['q'] ) : '';
		$a = isset( $item['a'] ) ? trim( $item['a'] ) : '';

		if ( '' === $q ) {
			continue;
		}

		$out[] = array(
			'title'   => $q,
			'content' => $a,
		);
	}

	return $out;
}

/**
 * پیام تبلیغاتی بالای پیشخوان مشتریان.
 *
 * @return array{title:string,content:string}|null
 */
function fs_dashboard_message() {
	$settings = fs_get_theme_settings();

	if ( ! trim( wp_strip_all_tags( $settings['dashboard_message'] ) ) ) {
		return null;
	}

	return array(
		'title'   => $settings['dashboard_title'],
		'content' => $settings['dashboard_message'],
	);
}

/**
 * رضایت خریداران — برای کارت‌های آمار صفحه اصلی.
 *
 * @return string
 */
function fs_get_satisfaction() {
	return trim( fs_get_theme_settings()['satisfaction'] );
}

/**
 * نشانه‌های اعتماد زیر هیرو.
 *
 * @return string[]
 */
function fs_get_trust_items() {
	$saved = fs_get_theme_settings()['trust_items'];

	if ( $saved ) {
		$items = array_filter( array_map( 'trim', explode( "\n", $saved ) ) );

		if ( $items ) {
			return array_values( $items );
		}
	}

	return array();
}

/**
 * تضمین‌های کنار دکمه خرید در صفحه محصول.
 *
 * @return string[]
 */
function fs_get_guarantees() {
	$saved = fs_get_theme_settings()['guarantees'];

	if ( ! $saved ) {
		return array();
	}

	return array_values( array_filter( array_map( 'trim', explode( "\n", $saved ) ) ) );
}

/**
 * متن‌های سایدبار اطمینان صفحه محصول (کنار توضیحات بلند).
 *
 * @return string[]
 */
function fs_get_assurances() {
	$out = array();

	foreach ( (array) fs_get_theme_settings()['assurances'] as $item ) {
		$text = is_array( $item ) ? ( isset( $item['text'] ) ? trim( $item['text'] ) : '' ) : trim( (string) $item );

		if ( '' === $text ) {
			continue;
		}

		$out[] = $text;
	}

	return $out;
}

/**
 * عنوان سایدبار اطمینان صفحه محصول.
 *
 * @return string
 */
function fs_get_assurance_title() {
	$title = trim( fs_get_theme_settings()['assurance_title'] );

	return $title ? $title : 'چرا از ما بخرید؟';
}

/**
 * جعبه‌های بالای پاورقی — حداکثر سه مورد با آیکون، متن و لینک دلخواه.
 *
 * @return array<int, array{icon:string,title:string,text:string,link:string}>
 */
function fs_get_footer_boxes() {
	$out = array();

	foreach ( (array) fs_get_theme_settings()['footer_boxes'] as $box ) {
		$title = isset( $box['title'] ) ? trim( $box['title'] ) : '';
		$text  = isset( $box['text'] ) ? trim( $box['text'] ) : '';

		if ( '' === $title && '' === $text ) {
			continue;
		}

		$out[] = array(
			'icon'  => isset( $box['icon'] ) && $box['icon'] ? $box['icon'] : 'phone',
			'title' => $title,
			'text'  => $text,
			'link'  => isset( $box['link'] ) ? $box['link'] : '',
		);
	}

	return array_slice( $out, 0, 3 );
}

/**
 * متن «درباره ما»ی پاورقی.
 *
 * @return string
 */
function fs_get_footer_about() {
	return trim( fs_get_theme_settings()['footer_about'] );
}

/**
 * نمادهای اعتماد پاورقی (اینماد، ساماندهی، انجمن صنفی و…).
 *
 * @return array<int, array{id:int,label:string,link:string}>
 */
function fs_get_footer_symbols() {
	return fs_clean_image_rows( fs_get_theme_settings()['footer_symbols'] );
}

/**
 * آیکون بانک‌های پاورقی.
 *
 * @return array<int, array{id:int,label:string,link:string}>
 */
function fs_get_footer_banks() {
	return fs_clean_image_rows( fs_get_theme_settings()['footer_banks'] );
}

/**
 * متن زیر آیکون بانک‌های پاورقی.
 *
 * @return string
 */
function fs_get_footer_banks_caption() {
	return trim( fs_get_theme_settings()['footer_banks_caption'] );
}

/**
 * آیا تب محصولات پاورقی نمایش داده شود؟
 *
 * @return bool
 */
function fs_footer_products_enabled() {
	return 'yes' === fs_get_theme_settings()['footer_products'];
}

/**
 * پاک‌سازی ردیف‌های تصویری ذخیره‌شده.
 *
 * @param mixed $rows ردیف‌ها.
 * @return array<int, array{id:int,label:string,link:string}>
 */
function fs_clean_image_rows( $rows ) {
	$out = array();

	foreach ( (array) $rows as $row ) {
		$id = isset( $row['id'] ) ? (int) $row['id'] : 0;

		if ( ! $id ) {
			continue;
		}

		$out[] = array(
			'id'    => $id,
			'label' => isset( $row['label'] ) ? $row['label'] : '',
			'link'  => isset( $row['link'] ) ? $row['link'] : '',
		);
	}

	return $out;
}

/**
 * ثبت منوی مدیریت.
 *
 * @return void
 */
function fs_theme_settings_menu() {
	add_menu_page(
		'تنظیمات قالب',
		'تنظیمات قالب',
		'manage_options',
		'fs-theme-settings',
		'fs_theme_settings_page',
		'dashicons-admin-customizer',
		59
	);
}
add_action( 'admin_menu', 'fs_theme_settings_menu' );

/**
 * تب‌های صفحه.
 *
 * @return array<string, string>
 */
function fs_theme_settings_tabs() {
	return array(
		'home'      => 'صفحه اصلی',
		'product'   => 'صفحه محصول',
		'header'    => 'هدر',
		'footer'    => 'فوتر',
		'dashboard' => 'پیشخوان کاربری',
		'health'    => 'عیب‌یابی',
	);
}

/**
 * خواندن یک آرایه‌ی ردیفی از POST.
 *
 * @param string   $prefix   پیشوند نام فیلدها.
 * @param string[] $keys     کلیدهای هر ردیف.
 * @return array<int, array<string, string>>
 */
function fs_post_rows( $prefix, $keys ) {
	$columns = array();
	$count   = 0;

	foreach ( $keys as $key ) {
		$name             = $prefix . '_' . $key;
		$columns[ $key ]  = isset( $_POST[ $name ] ) ? (array) wp_unslash( $_POST[ $name ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- در ادامه پاک‌سازی می‌شود؛ nonce در تابع فراخوان بررسی شده است.
		$count            = max( $count, count( $columns[ $key ] ) );
	}

	$rows = array();

	for ( $i = 0; $i < $count; $i++ ) {
		$row = array();

		foreach ( $keys as $key ) {
			$row[ $key ] = isset( $columns[ $key ][ $i ] ) ? $columns[ $key ][ $i ] : '';
		}

		$rows[] = $row;
	}

	return $rows;
}

/**
 * ذخیره تنظیمات.
 *
 * @return void
 */
function fs_theme_settings_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}

	check_admin_referer( 'fs_theme_settings_save' );

	$tab      = isset( $_POST['fs_tab'] ) ? sanitize_key( wp_unslash( $_POST['fs_tab'] ) ) : 'home';
	$settings = fs_get_theme_settings();

	if ( 'home' === $tab ) {
		$settings['satisfaction']  = isset( $_POST['satisfaction'] ) ? sanitize_text_field( wp_unslash( $_POST['satisfaction'] ) ) : '';
		$settings['trust_items']   = isset( $_POST['trust_items'] ) ? sanitize_textarea_field( wp_unslash( $_POST['trust_items'] ) ) : '';
		$settings['about_title']   = isset( $_POST['about_title'] ) ? sanitize_text_field( wp_unslash( $_POST['about_title'] ) ) : '';
		$settings['about_content'] = isset( $_POST['about_content'] ) ? wp_kses_post( wp_unslash( $_POST['about_content'] ) ) : '';

		$faq = array();

		foreach ( fs_post_rows( 'faq', array( 'q', 'a' ) ) as $row ) {
			$q = sanitize_text_field( $row['q'] );

			if ( '' === $q ) {
				continue;
			}

			$faq[] = array(
				'q' => $q,
				'a' => wp_kses_post( $row['a'] ),
			);
		}

		$settings['faq'] = $faq;
	} elseif ( 'product' === $tab ) {
		$settings['guarantees']      = isset( $_POST['guarantees'] ) ? sanitize_textarea_field( wp_unslash( $_POST['guarantees'] ) ) : '';
		$settings['assurance_title'] = isset( $_POST['assurance_title'] ) ? sanitize_text_field( wp_unslash( $_POST['assurance_title'] ) ) : '';

		$assurances = array();

		foreach ( fs_post_rows( 'assurance', array( 'text' ) ) as $row ) {
			$text = sanitize_text_field( $row['text'] );

			if ( '' === $text ) {
				continue;
			}

			$assurances[] = array( 'text' => $text );
		}

		$settings['assurances'] = $assurances;
	} elseif ( 'header' === $tab ) {
		$settings['header_notice']      = isset( $_POST['header_notice'] ) ? sanitize_text_field( wp_unslash( $_POST['header_notice'] ) ) : '';
		$settings['header_notice_link'] = isset( $_POST['header_notice_link'] ) ? esc_url_raw( wp_unslash( $_POST['header_notice_link'] ) ) : '';
		$settings['header_phone']       = isset( $_POST['header_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['header_phone'] ) ) : '';
	} elseif ( 'footer' === $tab ) {
		$icons = fs_footer_box_icons();
		$boxes = array();

		foreach ( fs_post_rows( 'fbox', array( 'icon', 'title', 'text', 'link' ) ) as $row ) {
			$title = sanitize_text_field( $row['title'] );
			$text  = sanitize_text_field( $row['text'] );

			if ( '' === $title && '' === $text ) {
				continue;
			}

			$icon = sanitize_key( $row['icon'] );

			$boxes[] = array(
				'icon'  => isset( $icons[ $icon ] ) ? $icon : 'phone',
				'title' => $title,
				'text'  => $text,
				'link'  => esc_url_raw( $row['link'] ),
			);
		}

		$settings['footer_boxes']    = array_slice( $boxes, 0, 3 );
		$settings['footer_about']    = isset( $_POST['footer_about'] ) ? sanitize_textarea_field( wp_unslash( $_POST['footer_about'] ) ) : '';
		$settings['footer_products'] = isset( $_POST['footer_products'] ) ? 'yes' : 'no';

		$symbols = array();

		foreach ( fs_post_rows( 'fsym', array( 'id', 'label', 'link' ) ) as $row ) {
			$id = (int) $row['id'];

			if ( ! $id ) {
				continue;
			}

			$symbols[] = array(
				'id'    => $id,
				'label' => sanitize_text_field( $row['label'] ),
				'link'  => esc_url_raw( $row['link'] ),
			);
		}

		$settings['footer_symbols'] = $symbols;

		$banks = array();

		foreach ( fs_post_rows( 'fbank', array( 'id', 'label' ) ) as $row ) {
			$id = (int) $row['id'];

			if ( ! $id ) {
				continue;
			}

			$banks[] = array(
				'id'    => $id,
				'label' => sanitize_text_field( $row['label'] ),
				'link'  => '',
			);
		}

		$settings['footer_banks']         = array_slice( $banks, 0, 24 );
		$settings['footer_banks_caption'] = isset( $_POST['footer_banks_caption'] ) ? sanitize_text_field( wp_unslash( $_POST['footer_banks_caption'] ) ) : '';
	} elseif ( 'dashboard' === $tab ) {
		$settings['dashboard_title']   = isset( $_POST['dashboard_title'] ) ? sanitize_text_field( wp_unslash( $_POST['dashboard_title'] ) ) : '';
		$settings['dashboard_message'] = isset( $_POST['dashboard_message'] ) ? wp_kses_post( wp_unslash( $_POST['dashboard_message'] ) ) : '';
	}

	update_option( FS_SETTINGS_OPTION, $settings );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'    => 'fs-theme-settings',
				'tab'     => $tab,
				'updated' => 1,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_fs_theme_settings_save', 'fs_theme_settings_save' );

/**
 * چاپ یک ردیف پرسش/پاسخ در تب صفحه اصلی.
 *
 * @param string $q پرسش.
 * @param string $a پاسخ.
 * @return void
 */
function fs_faq_row( $q = '', $a = '' ) {
	?>
	<div class="fs-row" data-fs-row>
		<div class="fs-row__fields fs-row__fields--stack">
			<input type="text" name="faq_q[]" placeholder="متن پرسش" value="<?php echo esc_attr( $q ); ?>">
			<textarea name="faq_a[]" rows="2" placeholder="متن پاسخ"><?php echo esc_textarea( $a ); ?></textarea>
		</div>
		<button type="button" class="button-link fs-row__del" data-fs-del>حذف</button>
	</div>
	<?php
}

/**
 * یک ردیف متنی ساده (سایدبار اطمینان صفحه محصول).
 *
 * @param string $text متن.
 * @return void
 */
function fs_assurance_row( $text = '' ) {
	?>
	<div class="fs-row" data-fs-row>
		<div class="fs-row__fields">
			<input type="text" name="assurance_text[]" placeholder="مثلاً دسترسی مادام‌العمر به فایل خریداری‌شده" value="<?php echo esc_attr( $text ); ?>">
		</div>
		<button type="button" class="button-link fs-row__del" data-fs-del>حذف</button>
	</div>
	<?php
}

/**
 * یک ردیف جعبه‌ی بالای پاورقی.
 *
 * @param array $box داده‌های جعبه.
 * @return void
 */
function fs_footer_box_row( $box = array() ) {
	$icon  = isset( $box['icon'] ) ? $box['icon'] : 'phone';
	$title = isset( $box['title'] ) ? $box['title'] : '';
	$text  = isset( $box['text'] ) ? $box['text'] : '';
	$link  = isset( $box['link'] ) ? $box['link'] : '';
	?>
	<div class="fs-row" data-fs-row>
		<div class="fs-row__fields">
			<select name="fbox_icon[]">
				<?php foreach ( fs_footer_box_icons() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $icon, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" name="fbox_title[]" placeholder="عنوان (مثلاً پشتیبانی تلگرام)" value="<?php echo esc_attr( $title ); ?>">
			<input type="text" name="fbox_text[]" placeholder="متن یا شماره" value="<?php echo esc_attr( $text ); ?>">
			<input type="text" name="fbox_link[]" placeholder="لینک (اختیاری)" value="<?php echo esc_attr( $link ); ?>">
		</div>
		<button type="button" class="button-link fs-row__del" data-fs-del>حذف</button>
	</div>
	<?php
}

/**
 * یک ردیف تصویری (نماد یا بانک) با انتخابگر رسانه.
 *
 * @param string $prefix  پیشوند نام فیلد.
 * @param array  $item    داده‌ها.
 * @param bool   $has_link آیا فیلد لینک داشته باشد.
 * @return void
 */
function fs_image_row( $prefix, $item = array(), $has_link = true ) {
	$id    = isset( $item['id'] ) ? (int) $item['id'] : 0;
	$label = isset( $item['label'] ) ? $item['label'] : '';
	$link  = isset( $item['link'] ) ? $item['link'] : '';
	$src   = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
	?>
	<div class="fs-row fs-row--img" data-fs-row>
		<div class="fs-row__thumb">
			<img src="<?php echo esc_url( $src ); ?>" alt="" <?php echo $src ? '' : 'hidden'; ?>>
			<span class="fs-row__ph" <?php echo $src ? 'hidden' : ''; ?>>بدون تصویر</span>
		</div>
		<div class="fs-row__fields">
			<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>_id[]" value="<?php echo esc_attr( $id ); ?>" data-fs-img-id>
			<input type="text" name="<?php echo esc_attr( $prefix ); ?>_label[]" placeholder="نام (اختیاری)" value="<?php echo esc_attr( $label ); ?>">
			<?php if ( $has_link ) : ?>
				<input type="text" name="<?php echo esc_attr( $prefix ); ?>_link[]" placeholder="لینک (اختیاری)" value="<?php echo esc_attr( $link ); ?>">
			<?php endif; ?>
		</div>
		<div class="fs-row__actions">
			<button type="button" class="button" data-fs-pick>انتخاب تصویر</button>
			<button type="button" class="button-link fs-row__del" data-fs-del>حذف</button>
		</div>
	</div>
	<?php
}

/**
 * چاپ دکمه «افزودن» به‌همراه قالب ردیف.
 *
 * @param string   $wrap_id  شناسه ظرف ردیف‌ها.
 * @param string   $label    برچسب دکمه.
 * @param callable $renderer تابع رندر ردیف خالی.
 * @return void
 */
function fs_row_adder( $wrap_id, $label, $renderer ) {
	$tpl_id = $wrap_id . '-tpl';
	?>
	<button type="button" class="button" data-fs-add="<?php echo esc_attr( $wrap_id ); ?>" data-fs-tpl="<?php echo esc_attr( $tpl_id ); ?>">
		<?php echo esc_html( $label ); ?>
	</button>
	<template id="<?php echo esc_attr( $tpl_id ); ?>"><?php call_user_func( $renderer ); ?></template>
	<?php
}

/**
 * بارگذاری اسکریپت‌های صفحه تنظیمات قالب.
 *
 * @param string $hook شناسه صفحه.
 * @return void
 */
function fs_settings_assets( $hook ) {
	if ( false === strpos( (string) $hook, 'fs-theme-settings' ) ) {
		return;
	}

	wp_enqueue_media();

	wp_enqueue_script(
		'fs-admin-settings',
		get_theme_file_uri( 'assets/js/admin-settings.js' ),
		array( 'jquery' ),
		fs_asset_version( 'assets/js/admin-settings.js' ),
		true
	);
}
add_action( 'admin_enqueue_scripts', 'fs_settings_assets' );

/**
 * رندر صفحه تنظیمات.
 *
 * @return void
 */
function fs_theme_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tabs     = fs_theme_settings_tabs();
	$tab      = isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $tabs ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'home'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$settings = fs_get_theme_settings();
	?>
	<div class="wrap fs-settings-wrap">
		<h1>تنظیمات قالب</h1>

		<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p>ذخیره شد.</p></div>
		<?php endif; ?>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( array( 'page' => 'fs-theme-settings', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</h2>

		<?php if ( 'home' === $tab ) : ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fs_theme_settings_save' ); ?>
				<input type="hidden" name="action" value="fs_theme_settings_save">
				<input type="hidden" name="fs_tab" value="home">

				<h2 style="margin-top:24px">پرسش‌های متداول صفحه اصلی</h2>
				<p class="description">هر ردیف یک پرسش و پاسخ است. ردیف‌های با پرسش خالی ذخیره نمی‌شوند.</p>
				<div class="fs-rows" id="fs-faq-rows">
					<?php
					$fs_faq_items = (array) $settings['faq'];

					// همیشه دست‌کم پنج ردیف آماده‌ی پرکردن نمایش داده می‌شود.
					while ( count( $fs_faq_items ) < 5 ) {
						$fs_faq_items[] = array(
							'q' => '',
							'a' => '',
						);
					}

					foreach ( $fs_faq_items as $fs_item ) {
						fs_faq_row( $fs_item['q'] ?? '', $fs_item['a'] ?? '' );
					}
					?>
				</div>
				<?php fs_row_adder( 'fs-faq-rows', 'افزودن پرسش', 'fs_faq_row' ); ?>

				<h2 style="margin-top:32px">متن درباره ما (انتهای صفحه اصلی)</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="fs-about-title">عنوان</label></th>
						<td><input type="text" id="fs-about-title" name="about_title" class="regular-text" value="<?php echo esc_attr( $settings['about_title'] ); ?>" placeholder="درباره ما"></td>
					</tr>
				</table>
				<?php
				wp_editor(
					$settings['about_content'],
					'about_content',
					array(
						'textarea_name' => 'about_content',
						'media_buttons' => false,
						'textarea_rows' => 10,
					)
				);
				?>
				<p class="description">هرچه اینجا بنویسید، در بخش «درباره ما»ی صفحه اصلی درج می‌شود. اگر خالی بماند، آن بخش نمایش داده نمی‌شود.</p>

				<h2 style="margin-top:32px">آمار هیرو</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="fs-satisfaction">رضایت خریداران</label></th>
						<td>
							<input type="text" id="fs-satisfaction" name="satisfaction" class="regular-text" value="<?php echo esc_attr( $settings['satisfaction'] ); ?>" placeholder="مثلاً ۹۶٪">
							<p class="description">خالی بگذارید تا این کارت آماری نمایش داده نشود.</p>
						</td>
					</tr>
					<tr>
						<th><label for="fs-trust-items">نشانه‌های اعتماد زیر هیرو</label></th>
						<td>
							<textarea id="fs-trust-items" name="trust_items" rows="4" class="large-text"><?php echo esc_textarea( $settings['trust_items'] ); ?></textarea>
							<p class="description">هر خط یک مورد. خالی بگذارید تا مقادیر پیش‌فرض استفاده شود.</p>
						</td>
					</tr>
				</table>

				<p style="margin-top:26px">
					<button type="submit" class="button button-primary button-hero">ذخیره تنظیمات</button>
				</p>
			</form>

		<?php elseif ( 'product' === $tab ) : ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fs_theme_settings_save' ); ?>
				<input type="hidden" name="action" value="fs_theme_settings_save">
				<input type="hidden" name="fs_tab" value="product">

				<h2 style="margin-top:24px">تضمین‌های کنار دکمه خرید</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="fs-guarantees">تضمین‌ها</label></th>
						<td>
							<textarea id="fs-guarantees" name="guarantees" rows="4" class="large-text"><?php echo esc_textarea( $settings['guarantees'] ); ?></textarea>
							<p class="description">هر خط یک مورد. خالی بگذارید تا نمایش داده نشود.</p>
						</td>
					</tr>
				</table>

				<h2 style="margin-top:32px">سایدبار اطمینان (کنار توضیحات بلند محصول)</h2>
				<p class="description">
					این متن‌ها در ستون کناری بخش توضیحات صفحه محصول نمایش داده می‌شوند؛ زیرشان لوگوی بانک‌ها
					می‌آید که در بخش «نمادها و بانک‌ها»ی همین تب تنظیم می‌شود.
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="fs-assurance-title">عنوان سایدبار</label></th>
						<td><input type="text" id="fs-assurance-title" name="assurance_title" class="regular-text" value="<?php echo esc_attr( $settings['assurance_title'] ); ?>" placeholder="چرا از ما بخرید؟"></td>
					</tr>
				</table>

				<div class="fs-rows" id="fs-assurance-rows">
					<?php
					$fs_assurances = (array) $settings['assurances'];

					while ( count( $fs_assurances ) < 5 ) {
						$fs_assurances[] = array( 'text' => '' );
					}

					foreach ( $fs_assurances as $fs_item ) {
						fs_assurance_row( is_array( $fs_item ) ? ( $fs_item['text'] ?? '' ) : (string) $fs_item );
					}
					?>
				</div>
				<?php fs_row_adder( 'fs-assurance-rows', 'افزودن متن', 'fs_assurance_row' ); ?>

				<p style="margin-top:26px">
					<button type="submit" class="button button-primary button-hero">ذخیره تنظیمات</button>
				</p>
			</form>

			<hr style="margin:32px 0">

			<?php fs_trust_tab_content(); ?>

		<?php elseif ( 'header' === $tab ) : ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fs_theme_settings_save' ); ?>
				<input type="hidden" name="action" value="fs_theme_settings_save">
				<input type="hidden" name="fs_tab" value="header">

				<h2 style="margin-top:24px">نوار اعلان بالای هدر</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="fs-header-notice">متن اعلان</label></th>
						<td>
							<input type="text" id="fs-header-notice" name="header_notice" class="large-text" value="<?php echo esc_attr( $settings['header_notice'] ); ?>" placeholder="مثلاً تخفیف ویژه آزمون‌های این ماه">
							<p class="description">خالی بگذارید تا نوار اعلان اصلاً نمایش داده نشود.</p>
						</td>
					</tr>
					<tr>
						<th><label for="fs-header-notice-link">لینک اعلان</label></th>
						<td><input type="text" id="fs-header-notice-link" name="header_notice_link" class="large-text" value="<?php echo esc_attr( $settings['header_notice_link'] ); ?>" placeholder="https://"></td>
					</tr>
					<tr>
						<th><label for="fs-header-phone">شماره پشتیبانی هدر</label></th>
						<td>
							<input type="text" id="fs-header-phone" name="header_phone" class="regular-text" value="<?php echo esc_attr( $settings['header_phone'] ); ?>" placeholder="۰۹۱۲۰۰۰۰۰۰۰">
							<p class="description">در نوار اعلان، کنار متن نمایش داده می‌شود.</p>
						</td>
					</tr>
				</table>

				<p class="description" style="margin-top:16px">
					لوگوی هدر از «نمایش ← سفارشی‌سازی ← هویت سایت» و منوها از «نمایش ← فهرست‌ها» تنظیم می‌شوند.
				</p>

				<p style="margin-top:26px">
					<button type="submit" class="button button-primary button-hero">ذخیره تنظیمات</button>
				</p>
			</form>

		<?php elseif ( 'footer' === $tab ) : ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fs_theme_settings_save' ); ?>
				<input type="hidden" name="action" value="fs_theme_settings_save">
				<input type="hidden" name="fs_tab" value="footer">

				<h2 style="margin-top:24px">سه جعبه‌ی بالای پاورقی</h2>
				<p class="description">آیکون، عنوان، متن و لینک هرکدام دلخواه است — تلگرام، شماره تلفن، چت بله یا ایتا و…</p>
				<div class="fs-rows" id="fs-fbox-rows">
					<?php
					$fs_boxes = (array) $settings['footer_boxes'];

					while ( count( $fs_boxes ) < 3 ) {
						$fs_boxes[] = array();
					}

					foreach ( array_slice( $fs_boxes, 0, 3 ) as $fs_box ) {
						fs_footer_box_row( $fs_box );
					}
					?>
				</div>
				<?php
				fs_row_adder(
					'fs-fbox-rows',
					'افزودن جعبه',
					function () {
						fs_footer_box_row();
					}
				);
				?>

				<h2 style="margin-top:32px">متن درباره ما در پاورقی</h2>
				<textarea name="footer_about" rows="5" class="large-text"><?php echo esc_textarea( $settings['footer_about'] ); ?></textarea>
				<p class="description">کنار لوگوی سایت در پاورقی نمایش داده می‌شود. خالی بگذارید تا توضیح پیش‌فرض سایت استفاده شود.</p>

				<h2 style="margin-top:32px">ستون لینک‌های مهم</h2>
				<p class="description">
					دو ستون لینک‌های مهم از فهرست‌های «پاورقی — ستون اول» و «پاورقی — ستون دوم» خوانده می‌شوند و
					عنوان هر ستون همان نام فهرست است. آن‌ها را در «نمایش ← فهرست‌ها» بسازید.
				</p>

				<h2 style="margin-top:32px">ستون محصولات</h2>
				<label>
					<input type="checkbox" name="footer_products" value="1" <?php checked( 'yes', $settings['footer_products'] ); ?>>
					نمایش دو تب «جدیدترین» و «پربازدیدترین» محصولات در پاورقی (خودکار)
				</label>

				<h2 style="margin-top:32px">نمادهای اعتماد</h2>
				<p class="description">اینماد، ساماندهی، انجمن صنفی و… — سه لوگوی بزرگ کنار هم نمایش داده می‌شوند.</p>
				<div class="fs-rows" id="fs-fsym-rows">
					<?php
					$fs_symbols = fs_clean_image_rows( $settings['footer_symbols'] );

					while ( count( $fs_symbols ) < 3 ) {
						$fs_symbols[] = array();
					}

					foreach ( $fs_symbols as $fs_symbol ) {
						fs_image_row( 'fsym', $fs_symbol, true );
					}
					?>
				</div>
				<?php
				fs_row_adder(
					'fs-fsym-rows',
					'افزودن نماد',
					function () {
						fs_image_row( 'fsym', array(), true );
					}
				);
				?>

				<h2 style="margin-top:32px">آیکون بانک‌ها</h2>
				<p class="description">تا ۱۲ آیکون مربع کوچک؛ زیر نمادهای اعتماد نمایش داده می‌شوند.</p>
				<div class="fs-rows fs-rows--compact" id="fs-fbank-rows">
					<?php
					$fs_banks = fs_clean_image_rows( $settings['footer_banks'] );

					while ( count( $fs_banks ) < 12 ) {
						$fs_banks[] = array();
					}

					foreach ( $fs_banks as $fs_bank ) {
						fs_image_row( 'fbank', $fs_bank, false );
					}
					?>
				</div>
				<?php
				fs_row_adder(
					'fs-fbank-rows',
					'افزودن بانک',
					function () {
						fs_image_row( 'fbank', array(), false );
					}
				);
				?>

				<h2 style="margin-top:32px">متن زیر آیکون بانک‌ها</h2>
				<input type="text" name="footer_banks_caption" class="large-text" value="<?php echo esc_attr( $settings['footer_banks_caption'] ); ?>" placeholder="قابل خرید با تمامی کارت‌های عضو شتاب">

				<p style="margin-top:26px">
					<button type="submit" class="button button-primary button-hero">ذخیره تنظیمات</button>
				</p>
			</form>

		<?php elseif ( 'dashboard' === $tab ) : ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fs_theme_settings_save' ); ?>
				<input type="hidden" name="action" value="fs_theme_settings_save">
				<input type="hidden" name="fs_tab" value="dashboard">

				<h2 style="margin-top:24px">پیام بالای پیشخوان مشتریان</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="fs-dash-title">عنوان</label></th>
						<td><input type="text" id="fs-dash-title" name="dashboard_title" class="regular-text" value="<?php echo esc_attr( $settings['dashboard_title'] ); ?>" placeholder="مثلاً تخفیف ویژه امروز"></td>
					</tr>
				</table>
				<?php
				wp_editor(
					$settings['dashboard_message'],
					'dashboard_message',
					array(
						'textarea_name' => 'dashboard_message',
						'media_buttons' => false,
						'textarea_rows' => 6,
					)
				);
				?>
				<p class="description">این پیام بالای پیشخوان همه‌ی مشتریان نمایش داده می‌شود. اگر متن خالی بماند، این بخش نمایش داده نمی‌شود.</p>

				<p style="margin-top:26px">
					<button type="submit" class="button button-primary button-hero">ذخیره تنظیمات</button>
				</p>
			</form>

		<?php elseif ( 'health' === $tab ) : ?>

			<?php fs_diagnostics_tab(); ?>

		<?php endif; ?>

	</div>

	<style>
		.fs-rows { display: flex; flex-direction: column; gap: 10px; margin: 14px 0; max-width: 920px; }
		.fs-rows--compact .fs-row__thumb { width: 54px; height: 44px; }
		.fs-row { display: flex; align-items: flex-start; gap: 12px; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 12px; }
		.fs-row--img { align-items: center; }
		.fs-row__fields { flex: 1; display: flex; gap: 8px; flex-wrap: wrap; }
		.fs-row__fields--stack { flex-direction: column; }
		.fs-row__fields input[type="text"], .fs-row__fields textarea { flex: 1; min-width: 170px; width: 100%; }
		.fs-row__thumb { width: 78px; height: 56px; flex: none; border: 1px solid #f0f0f1; border-radius: 6px; background: #f6f7f7; display: flex; align-items: center; justify-content: center; overflow: hidden; }
		.fs-row__thumb img { max-width: 100%; max-height: 100%; object-fit: contain; }
		.fs-row__ph { font-size: 11px; color: #8c8f94; }
		.fs-row__actions { display: flex; align-items: center; gap: 10px; flex: none; }
		.fs-row__del { color: #b32d2e; flex: none; margin-top: 6px; }
		.fs-row--img .fs-row__del { margin-top: 0; }
	</style>
	<?php
}
