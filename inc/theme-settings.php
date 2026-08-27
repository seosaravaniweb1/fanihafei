<?php
/**
 * صفحه یکپارچه «تنظیمات قالب» — با تب‌های صفحه اصلی، صفحه محصول و پیشخوان کاربری.
 *
 * همه‌ی محتوای قابل‌ویرایش قالب (درباره سایت، پرسش‌های متداول، نشانه‌های اعتماد،
 * جعبه‌ی «چرا ما؟» صفحه محصول، پیام پیشخوان مشتریان) در یک آپشن ذخیره می‌شود و از یک
 * صفحه با تب مدیریت می‌شود؛ به‌جای پراکنده‌بودن بین پست‌تایپ و سفارشی‌سازی.
 *
 * @package SiFile
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
		'support_url'       => '',
		'support_label'     => '',
		'satisfaction'      => '',
		'product_satisf'    => '',
		'trust_items'       => '',
		'about_title'       => '',
		'about_content'     => '',
		'faq'               => array(),
		'why_title'         => '',
		'why_items'         => array(),
		'why_foot'          => '',
		'dashboard_title'   => '',
		'dashboard_message' => '',
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
 * نشانی چت پشتیبانی — دکمه‌ی کنار «پنل کاربری» در سربرگ.
 *
 * @return string
 */
function fs_support_url() {
	return trim( fs_get_theme_settings()['support_url'] );
}

/**
 * برچسب دکمه‌ی چت پشتیبانی.
 *
 * @return string
 */
function fs_support_label() {
	$saved = trim( fs_get_theme_settings()['support_label'] );

	return $saved ? $saved : fs_copy( 'support_label' );
}

/**
 * درصد رضایت مشتریان — نشان کنار عنوان در صفحه محصول.
 *
 * @return string
 */
function fs_product_satisfaction() {
	$saved = trim( fs_get_theme_settings()['product_satisf'] );

	if ( ! $saved ) {
		$saved = trim( fs_get_theme_settings()['satisfaction'] );
	}

	return $saved;
}

/**
 * جعبه‌ی «چرا ما؟» در نوار کناری توضیحات محصول.
 *
 * هر ردیف یک آیکونِ انتخابیِ مدیر دارد و یک متن. مقدارهای نسخه‌ی قبلی که یک
 * متن چندخطی بودند هم خوانده می‌شوند تا محتوای ثبت‌شده از دست نرود.
 *
 * @return array{title:string,items:array<int, array{icon:string,text:string}>,foot:string}|null
 */
function fs_get_why_box() {
	$settings = fs_get_theme_settings();
	$saved    = $settings['why_items'];
	$items    = array();

	if ( is_string( $saved ) ) {
		// سازگاری با نسخه‌ی متنی قدیمی.
		foreach ( array_filter( array_map( 'trim', explode( "\n", $saved ) ) ) as $line ) {
			$items[] = array(
				'icon' => 'check',
				'text' => $line,
			);
		}
	} elseif ( is_array( $saved ) ) {
		foreach ( $saved as $row ) {
			$text = isset( $row['text'] ) ? trim( $row['text'] ) : '';

			if ( '' === $text ) {
				continue;
			}

			$items[] = array(
				'icon' => fs_valid_icon( isset( $row['icon'] ) ? $row['icon'] : '' ),
				'text' => $text,
			);
		}
	}

	if ( ! $items ) {
		return null;
	}

	return array(
		'title' => $settings['why_title'] ? $settings['why_title'] : sprintf( 'چرا %s؟', get_bloginfo( 'name' ) ),
		'items' => $items,
		'foot'  => trim( $settings['why_foot'] ),
	);
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
		'header'    => 'سربرگ و پشتیبانی',
		'footer'    => 'فوتر',
		'pages'     => 'برگه‌ها',
		'dashboard' => 'پیشخوان کاربری',
	);
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

		$faq   = array();
		$fq    = isset( $_POST['faq_q'] ) ? (array) wp_unslash( $_POST['faq_q'] ) : array();
		$fa    = isset( $_POST['faq_a'] ) ? (array) wp_unslash( $_POST['faq_a'] ) : array();
		$count = max( count( $fq ), count( $fa ) );

		for ( $i = 0; $i < $count; $i++ ) {
			$q = isset( $fq[ $i ] ) ? sanitize_text_field( $fq[ $i ] ) : '';
			$a = isset( $fa[ $i ] ) ? wp_kses_post( $fa[ $i ] ) : '';

			if ( '' === $q ) {
				continue;
			}

			$faq[] = array(
				'q' => $q,
				'a' => $a,
			);
		}

		$settings['faq'] = $faq;
	} elseif ( 'product' === $tab ) {
		$settings['product_satisf'] = isset( $_POST['product_satisf'] ) ? sanitize_text_field( wp_unslash( $_POST['product_satisf'] ) ) : '';
		$settings['why_title']      = isset( $_POST['why_title'] ) ? sanitize_text_field( wp_unslash( $_POST['why_title'] ) ) : '';
		$settings['why_foot']       = isset( $_POST['why_foot'] ) ? sanitize_text_field( wp_unslash( $_POST['why_foot'] ) ) : '';

		$why   = array();
		$texts = isset( $_POST['why_text'] ) ? (array) wp_unslash( $_POST['why_text'] ) : array();
		$icons = isset( $_POST['why_icon'] ) ? (array) wp_unslash( $_POST['why_icon'] ) : array();

		foreach ( $texts as $i => $text ) {
			$text = sanitize_text_field( $text );

			if ( '' === $text ) {
				continue;
			}

			$why[] = array(
				'icon' => fs_valid_icon( isset( $icons[ $i ] ) ? sanitize_key( $icons[ $i ] ) : '' ),
				'text' => $text,
			);
		}

		$settings['why_items'] = $why;
	} elseif ( 'header' === $tab ) {
		$settings['support_url']   = isset( $_POST['support_url'] ) ? esc_url_raw( wp_unslash( $_POST['support_url'] ) ) : '';
		$settings['support_label'] = isset( $_POST['support_label'] ) ? sanitize_text_field( wp_unslash( $_POST['support_label'] ) ) : '';
	} elseif ( 'dashboard' === $tab ) {
		$settings['dashboard_title']   = isset( $_POST['dashboard_title'] ) ? sanitize_text_field( wp_unslash( $_POST['dashboard_title'] ) ) : '';
		$settings['dashboard_message'] = isset( $_POST['dashboard_message'] ) ? wp_kses_post( wp_unslash( $_POST['dashboard_message'] ) ) : '';
	}

	update_option( FS_SETTINGS_OPTION, $settings );

	wp_safe_redirect( add_query_arg( array( 'page' => 'fs-theme-settings', 'tab' => $tab, 'updated' => 1 ), admin_url( 'admin.php' ) ) );
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
	<div class="fs-faq-row">
		<div class="fs-faq-row__fields">
			<input type="text" name="faq_q[]" placeholder="متن پرسش" value="<?php echo esc_attr( $q ); ?>">
			<textarea name="faq_a[]" rows="2" placeholder="متن پاسخ"><?php echo esc_textarea( $a ); ?></textarea>
		</div>
		<button type="button" class="button-link fs-faq-row__del">حذف</button>
	</div>
	<?php
}

/**
 * چاپ یک ردیف «چرا ما؟» — انتخاب آیکون به‌همراه پیش‌نمایش زنده و متن مورد.
 *
 * @param string $icon نام آیکون.
 * @param string $text متن مورد.
 * @return void
 */
function fs_why_row( $icon = 'check', $text = '' ) {
	$icon = fs_valid_icon( $icon );
	?>
	<div class="fs-why-row">
		<span class="fs-why-row__preview" data-icon-preview>
			<?php fs_the_icon( $icon, 17, array( 'stroke' => '#6d28d9', 'width' => '2' ) ); ?>
		</span>

		<select name="why_icon[]" class="fs-why-row__icon" data-icon-select>
			<?php foreach ( fs_selectable_icons() as $fs_key => $fs_label ) : ?>
				<option value="<?php echo esc_attr( $fs_key ); ?>" <?php selected( $icon, $fs_key ); ?>>
					<?php echo esc_html( $fs_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<input type="text" name="why_text[]" class="fs-why-row__text" placeholder="متن این مورد" value="<?php echo esc_attr( $text ); ?>">

		<button type="button" class="button-link fs-why-row__del">حذف</button>
	</div>
	<?php
}

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

		<?php if ( isset( $_GET['updated'] ) ) : ?>
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

				<h2 style="margin-top:24px">آمار هیرو</h2>
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

				<h2 style="margin-top:8px">درباره سایت (انتهای صفحه اصلی)</h2>
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
						'textarea_rows' => 8,
					)
				);
				?>
				<p class="description">اگر متن خالی بماند، این بخش اصلاً در صفحه اصلی نمایش داده نمی‌شود.</p>

				<h2 style="margin-top:28px">پرسش‌های متداول</h2>
				<p class="description">هر ردیف یک پرسش و پاسخ است. ردیف‌های با پرسش خالی نادیده گرفته می‌شوند.</p>
				<div id="fs-faq-rows">
					<?php
					$fs_faq_items = (array) $settings['faq'];

					if ( ! $fs_faq_items ) {
						$fs_faq_items = array( array( 'q' => '', 'a' => '' ) );
					}

					foreach ( $fs_faq_items as $fs_item ) {
						fs_faq_row( $fs_item['q'] ?? '', $fs_item['a'] ?? '' );
					}
					?>
				</div>
				<button type="button" class="button" id="fs-faq-add">افزودن پرسش</button>
				<template id="fs-faq-row-tpl"><?php fs_faq_row(); ?></template>

				<p style="margin-top:26px">
					<button type="submit" class="button button-primary button-hero">ذخیره تنظیمات</button>
				</p>
			</form>

		<?php elseif ( 'product' === $tab ) : ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fs_theme_settings_save' ); ?>
				<input type="hidden" name="action" value="fs_theme_settings_save">
				<input type="hidden" name="fs_tab" value="product">

				<h2 style="margin-top:24px">نشان بالای عنوان محصول</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="fs-product-satisf">درصد رضایت مشتریان</label></th>
						<td>
							<input type="text" id="fs-product-satisf" name="product_satisf" class="regular-text" value="<?php echo esc_attr( $settings['product_satisf'] ); ?>" placeholder="مثلاً ۹۷٪">
							<p class="description">کنار «تعداد فروش موفق» بالای عنوان محصول نمایش داده می‌شود. خالی بگذارید تا از مقدار «رضایت خریداران» تب صفحه اصلی استفاده شود؛ اگر هر دو خالی باشند نمایش داده نمی‌شود.</p>
						</td>
					</tr>
				</table>

				<h2 style="margin-top:8px">جعبه «چرا ما؟» در نوار کناری توضیحات</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="fs-why-title">عنوان جعبه</label></th>
						<td>
							<input type="text" id="fs-why-title" name="why_title" class="regular-text" value="<?php echo esc_attr( $settings['why_title'] ); ?>" placeholder="<?php echo esc_attr( sprintf( 'چرا %s؟', get_bloginfo( 'name' ) ) ); ?>">
						</td>
					</tr>
					<tr>
						<th>موارد</th>
						<td>
							<div id="fs-why-rows">
								<?php
								$fs_why_saved = fs_get_why_box();
								$fs_why_rows  = $fs_why_saved ? $fs_why_saved['items'] : array( array( 'icon' => 'check', 'text' => '' ) );

								foreach ( $fs_why_rows as $fs_row ) {
									fs_why_row( $fs_row['icon'], $fs_row['text'] );
								}
								?>
							</div>
							<button type="button" class="button" id="fs-why-add">افزودن مورد</button>
							<template id="fs-why-row-tpl"><?php fs_why_row(); ?></template>
							<p class="description">برای هر مورد یک آیکون انتخاب کنید و متنش را بنویسید. مورد بدون متن ذخیره نمی‌شود؛ اگر هیچ موردی نماند، کل جعبه در صفحه محصول نمایش داده نمی‌شود.</p>
						</td>
					</tr>
					<tr>
						<th><label for="fs-why-foot">متن پایین جعبه</label></th>
						<td>
							<input type="text" id="fs-why-foot" name="why_foot" class="regular-text" value="<?php echo esc_attr( $settings['why_foot'] ); ?>" placeholder="مثلاً قابل خرید با تمامی کارت‌های عضو شتاب">
						</td>
					</tr>
				</table>

				<p style="margin-top:20px">
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

				<h2 style="margin-top:24px">دکمه چت پشتیبانی</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="fs-support-url">نشانی چت پشتیبانی</label></th>
						<td>
							<input type="url" id="fs-support-url" name="support_url" class="regular-text ltr" dir="ltr" value="<?php echo esc_attr( $settings['support_url'] ); ?>" placeholder="https://t.me/username">
							<p class="description">نشانی تلگرام، واتس‌اپ، گفتینو، رایچت یا هر صفحه‌ای که پشتیبانی روی آن انجام می‌شود. خالی بگذارید تا دکمه در سربرگ نمایش داده نشود.</p>
						</td>
					</tr>
					<tr>
						<th><label for="fs-support-label">متن دکمه</label></th>
						<td>
							<input type="text" id="fs-support-label" name="support_label" class="regular-text" value="<?php echo esc_attr( $settings['support_label'] ); ?>" placeholder="چت پشتیبانی">
						</td>
					</tr>
				</table>

				<p style="margin-top:20px">
					<button type="submit" class="button button-primary button-hero">ذخیره تنظیمات</button>
				</p>
			</form>

		<?php elseif ( 'footer' === $tab ) : ?>

			<?php fs_footer_tab_content(); ?>

		<?php elseif ( 'pages' === $tab ) : ?>

			<?php fs_pages_tab_content(); ?>

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

		<?php endif; ?>

	</div>

	<style>
		.fs-faq-row { display: flex; align-items: flex-start; gap: 12px; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 12px; margin-bottom: 10px; max-width: 760px; }
		.fs-faq-row__fields { flex: 1; display: flex; flex-direction: column; gap: 8px; }
		.fs-faq-row__fields input, .fs-faq-row__fields textarea { width: 100%; }
		.fs-faq-row__del { color: #b32d2e; flex: none; margin-top: 6px; }

		.fs-why-row { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 10px; margin-bottom: 8px; max-width: 760px; }
		.fs-why-row__preview { flex: none; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 8px; background: #f5f3ff; border: 1px solid #ddd6fe; }
		.fs-why-row__icon { flex: none; width: 170px; }
		.fs-why-row__text { flex: 1; }
		.fs-why-row__del { color: #b32d2e; flex: none; }
	</style>
	<script>
	// هر آیکون یک بار سمت سرور رندر می‌شود تا پیش‌نمایش بدون درخواست شبکه عوض شود.
	var fsIcons = <?php
		$fs_sprite = array();

		foreach ( array_keys( fs_selectable_icons() ) as $fs_key ) {
			$fs_sprite[ $fs_key ] = fs_icon( $fs_key, 17, array( 'stroke' => '#6d28d9', 'width' => '2' ) );
		}

		echo wp_json_encode( $fs_sprite );
	?>;

	(function () {
		var whyWrap = document.getElementById( 'fs-why-rows' );
		var whyTpl  = document.getElementById( 'fs-why-row-tpl' );
		var whyAdd  = document.getElementById( 'fs-why-add' );

		function paintIcon( row ) {
			var select  = row.querySelector( '[data-icon-select]' );
			var preview = row.querySelector( '[data-icon-preview]' );

			if ( select && preview && fsIcons[ select.value ] ) {
				preview.innerHTML = fsIcons[ select.value ];
			}
		}

		if ( whyWrap && whyTpl && whyAdd ) {
			whyAdd.addEventListener( 'click', function () {
				whyWrap.appendChild( whyTpl.content.cloneNode( true ) );
			} );

			whyWrap.addEventListener( 'change', function ( e ) {
				if ( e.target && e.target.hasAttribute( 'data-icon-select' ) ) {
					paintIcon( e.target.closest( '.fs-why-row' ) );
				}
			} );

			whyWrap.addEventListener( 'click', function ( e ) {
				if ( e.target && e.target.classList.contains( 'fs-why-row__del' ) ) {
					e.target.closest( '.fs-why-row' ).remove();
				}
			} );
		}
	})();

	(function () {
		var wrap = document.getElementById( 'fs-faq-rows' );
		var tpl  = document.getElementById( 'fs-faq-row-tpl' );
		var add  = document.getElementById( 'fs-faq-add' );

		if ( ! wrap || ! tpl || ! add ) {
			return;
		}

		add.addEventListener( 'click', function () {
			wrap.appendChild( tpl.content.cloneNode( true ) );
		} );

		wrap.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.classList.contains( 'fs-faq-row__del' ) ) {
				e.target.closest( '.fs-faq-row' ).remove();
			}
		} );
	})();
	</script>
	<?php
}
