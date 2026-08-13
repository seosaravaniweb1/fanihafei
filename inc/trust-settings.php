<?php
/**
 * تنظیمات «نمادها و بانک‌ها» — تب «صفحه محصول» در «تنظیمات قالب».
 *
 * نماد اعتماد، ساماندهی، آیکون بانک‌ها و متن زیر آن‌ها؛ ذخیره با اجاکس.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

const FS_TRUST_OPTION = 'fs_trust_settings';

/**
 * مقادیر پیش‌فرض.
 *
 * @return array{badges:array,banks:array,caption:string}
 */
function fs_trust_defaults() {
	return array(
		'badges'  => array(),
		'banks'   => array(),
		'caption' => 'قابل خرید با تمامی کارت‌های عضو شتاب',
	);
}

/**
 * خواندن تنظیمات.
 *
 * @return array{badges:array,banks:array,caption:string}
 */
function fs_get_trust_settings() {
	$saved = get_option( FS_TRUST_OPTION, array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return wp_parse_args( $saved, fs_trust_defaults() );
}

/**
 * بارگذاری اسکریپت‌های صفحه تنظیمات — روی تب «صفحه محصول» در «تنظیمات قالب».
 *
 * @param string $hook شناسه صفحه.
 * @return void
 */
function fs_trust_assets( $hook ) {
	$is_settings_page = isset( $_GET['page'] ) && 'fs-theme-settings' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! $is_settings_page || false === strpos( $hook, 'fs-theme-settings' ) ) {
		return;
	}

	wp_enqueue_media();

	wp_enqueue_script(
		'fs-admin-trust',
		get_theme_file_uri( 'assets/js/admin-trust.js' ),
		array( 'jquery' ),
		FS_VERSION,
		true
	);

	wp_localize_script(
		'fs-admin-trust',
		'fsTrust',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'fs_trust_save' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'fs_trust_assets' );

/**
 * چاپ یک ردیف تصویر.
 *
 * @param string $group  نام گروه (badges یا banks).
 * @param array  $item   آیتم.
 * @return void
 */
function fs_trust_row( $group, $item = array() ) {
	$id    = isset( $item['id'] ) ? (int) $item['id'] : 0;
	$label = isset( $item['label'] ) ? $item['label'] : '';
	$link  = isset( $item['link'] ) ? $item['link'] : '';
	$src   = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
	?>
	<div class="fs-trow" data-group="<?php echo esc_attr( $group ); ?>">
		<div class="fs-trow__img">
			<img src="<?php echo esc_url( $src ); ?>" alt="" <?php echo $src ? '' : 'hidden'; ?>>
			<span class="fs-trow__ph" <?php echo $src ? 'hidden' : ''; ?>>بدون تصویر</span>
		</div>
		<div class="fs-trow__fields">
			<input type="hidden" class="fs-trow__id" value="<?php echo esc_attr( $id ); ?>">
			<input type="text" class="fs-trow__label" placeholder="نام (اختیاری)" value="<?php echo esc_attr( $label ); ?>">
			<?php if ( 'badges' === $group ) : ?>
				<input type="url" class="fs-trow__link" placeholder="لینک (اختیاری)" value="<?php echo esc_attr( $link ); ?>">
			<?php endif; ?>
		</div>
		<div class="fs-trow__actions">
			<button type="button" class="button fs-trow__pick">انتخاب تصویر</button>
			<button type="button" class="button-link fs-trow__del">حذف</button>
		</div>
	</div>
	<?php
}

/**
 * محتوای تب «نمادها و بانک‌ها» — داخل تب «صفحه محصول» در «تنظیمات قالب» جاسازی می‌شود.
 *
 * @return void
 */
function fs_trust_tab_content() {
	$settings = fs_get_trust_settings();
	?>
	<div class="fs-trust-wrap">
		<h2>نمادها و بانک‌ها</h2>
		<p class="description">این موارد در نوار کناری صفحه محصول، زیر دکمه خرید، نمایش داده می‌شوند. هر بخشی که خالی بماند نمایش داده نمی‌شود.</p>

		<h3>نمادها (اعتماد الکترونیکی، ساماندهی و…)</h3>
		<div class="fs-trows" id="fs-badges">
			<?php
			foreach ( $settings['badges'] as $badge ) {
				fs_trust_row( 'badges', $badge );
			}
			?>
		</div>
		<button type="button" class="button fs-add" data-target="fs-badges" data-group="badges">افزودن نماد</button>

		<h3 style="margin-top:32px">آیکون بانک‌ها</h3>
		<p class="description">در هر سطر شش بانک نمایش داده می‌شود.</p>
		<div class="fs-trows" id="fs-banks">
			<?php
			foreach ( $settings['banks'] as $bank ) {
				fs_trust_row( 'banks', $bank );
			}
			?>
		</div>
		<button type="button" class="button fs-add" data-target="fs-banks" data-group="banks">افزودن بانک</button>

		<h3 style="margin-top:32px">متن زیر آیکون‌ها</h3>
		<textarea id="fs-caption" rows="2" class="large-text"><?php echo esc_textarea( $settings['caption'] ); ?></textarea>

		<p style="margin-top:26px">
			<button type="button" class="button button-primary button-hero" id="fs-save">ذخیره تنظیمات</button>
			<span id="fs-save-msg" class="fs-save-msg"></span>
		</p>

		<template id="fs-row-badges"><?php fs_trust_row( 'badges' ); ?></template>
		<template id="fs-row-banks"><?php fs_trust_row( 'banks' ); ?></template>
	</div>

	<style>
		.fs-trows { display: flex; flex-direction: column; gap: 10px; margin: 14px 0; max-width: 860px; }
		.fs-trow { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 12px; }
		.fs-trow__img { width: 78px; height: 56px; flex: none; border: 1px solid #f0f0f1; border-radius: 6px; background: #f6f7f7; display: flex; align-items: center; justify-content: center; overflow: hidden; }
		.fs-trow__img img { max-width: 100%; max-height: 100%; object-fit: contain; }
		.fs-trow__ph { font-size: 11px; color: #8c8f94; }
		.fs-trow__fields { flex: 1; display: flex; gap: 8px; flex-wrap: wrap; }
		.fs-trow__fields input { flex: 1; min-width: 160px; }
		.fs-trow__actions { display: flex; align-items: center; gap: 10px; flex: none; }
		.fs-trow__del { color: #b32d2e; }
		.fs-save-msg { margin-right: 12px; font-weight: 600; }
		.fs-save-msg.ok { color: #007017; }
		.fs-save-msg.err { color: #b32d2e; }
	</style>
	<?php
}

/**
 * ذخیره با اجاکس.
 *
 * @return void
 */
function fs_trust_save() {
	check_ajax_referer( 'fs_trust_save', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
	}

	$raw = isset( $_POST['payload'] ) ? json_decode( wp_unslash( $_POST['payload'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- در ادامه پاک‌سازی می‌شود.

	if ( ! is_array( $raw ) ) {
		wp_send_json_error( array( 'message' => 'داده‌ها نامعتبر است.' ), 400 );
	}

	$clean = fs_trust_defaults();

	foreach ( array( 'badges', 'banks' ) as $group ) {
		if ( empty( $raw[ $group ] ) || ! is_array( $raw[ $group ] ) ) {
			continue;
		}

		foreach ( $raw[ $group ] as $item ) {
			$id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;

			if ( ! $id ) {
				continue;
			}

			$clean[ $group ][] = array(
				'id'    => $id,
				'label' => isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '',
				'link'  => isset( $item['link'] ) ? esc_url_raw( $item['link'] ) : '',
			);
		}
	}

	if ( isset( $raw['caption'] ) ) {
		$clean['caption'] = sanitize_text_field( $raw['caption'] );
	}

	update_option( FS_TRUST_OPTION, $clean );

	wp_send_json_success(
		array(
			'message' => 'ذخیره شد.',
			'badges'  => count( $clean['badges'] ),
			'banks'   => count( $clean['banks'] ),
		)
	);
}
add_action( 'wp_ajax_fs_trust_save', 'fs_trust_save' );
