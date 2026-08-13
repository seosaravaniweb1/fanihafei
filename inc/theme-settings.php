<?php
/**
 * صفحه یکپارچه «تنظیمات قالب» — با تب‌های صفحه اصلی، صفحه محصول و پیشخوان کاربری.
 *
 * همه‌ی محتوای قابل‌ویرایش قالب (درباره سایت، پرسش‌های متداول، نشانه‌های اعتماد،
 * تضمین‌های صفحه محصول، پیام پیشخوان مشتریان) در یک آپشن ذخیره می‌شود و از یک
 * صفحه با تب مدیریت می‌شود؛ به‌جای پراکنده‌بودن بین پست‌تایپ و سفارشی‌سازی.
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
		'satisfaction'      => '',
		'trust_items'       => '',
		'about_title'       => '',
		'about_content'     => '',
		'faq'               => array(),
		'guarantees'        => '',
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
		$settings['guarantees'] = isset( $_POST['guarantees'] ) ? sanitize_textarea_field( wp_unslash( $_POST['guarantees'] ) ) : '';
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

				<p style="margin-top:20px">
					<button type="submit" class="button button-primary button-hero">ذخیره تنظیمات</button>
				</p>
			</form>

			<hr style="margin:32px 0">

			<?php fs_trust_tab_content(); ?>

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
	</style>
	<script>
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
