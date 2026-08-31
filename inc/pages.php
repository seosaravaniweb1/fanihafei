<?php
/**
 * استایل بلوک‌ها و الگوهای گوتنبرگ.
 *
 * دو کار انجام می‌شود:
 *
 * ۱. سبک‌های بلوک (register_block_style) — وقتی روی یک بلوک «گروه» کلیک کنید،
 *    در نوار کناری گوتنبرگ سبک‌های آماده‌ی قالب را می‌بینید و با یک کلیک
 *    جعبه‌ی کارت، جعبه‌ی نکته یا جعبه‌ی هشدار می‌سازید.
 *
 * ۲. الگوها (register_block_pattern) — بخش‌های آماده‌ای مثل «مراحل خرید»،
 *    «پرسش و پاسخ» و «جعبه ویدیو» که از دکمه‌ی + گوتنبرگ، تب «الگوها»،
 *    دسته‌ی «قالب لوکسو فایل» درج می‌شوند.
 *
 * محتوای برگه‌ها اینجا نیست: متن هر برگه را مدیر سایت خودش در ویرایشگر
 * می‌گذارد. قالب فقط ظاهر بلوک‌ها را تعریف می‌کند، نه متن آن‌ها.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

/**
 * سبک‌های آماده‌ی بلوک‌ها.
 *
 * @return void
 */
function fs_register_block_styles() {
	if ( ! function_exists( 'register_block_style' ) ) {
		return;
	}

	$group_styles = array(
		'fs-card'  => 'کارت قالب',
		'fs-note'  => 'جعبه نکته',
		'fs-warn'  => 'جعبه هشدار',
		'fs-steps' => 'مراحل شماره‌دار',
	);

	foreach ( $group_styles as $name => $label ) {
		register_block_style(
			'core/group',
			array(
				'name'  => $name,
				'label' => $label,
			)
		);
	}

	register_block_style(
		'core/list',
		array(
			'name'  => 'fs-check',
			'label' => 'فهرست تیک‌دار',
		)
	);

	register_block_style(
		'core/table',
		array(
			'name'  => 'fs-table',
			'label' => 'جدول قالب',
		)
	);
}
add_action( 'init', 'fs_register_block_styles' );

/**
 * دسته‌ی الگوهای قالب.
 *
 * @return void
 */
function fs_register_pattern_category() {
	if ( ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}

	register_block_pattern_category(
		'si-file',
		array( 'label' => 'قالب لوکسو فایل' )
	);
}
add_action( 'init', 'fs_register_pattern_category', 9 );

/**
 * الگوهای آماده.
 *
 * @return void
 */
function fs_register_block_patterns() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	$patterns = array(
		'lead'    => array(
			'title'   => 'متن آغازین برگه',
			'content' => fs_pattern_lead(),
		),
		'cards'   => array(
			'title'   => 'سه کارت ویژگی',
			'content' => fs_pattern_cards(),
		),
		'steps'   => array(
			'title'   => 'مراحل شماره‌دار',
			'content' => fs_pattern_steps(),
		),
		'video'   => array(
			'title'   => 'جعبه ویدیوی آموزشی',
			'content' => fs_pattern_video(),
		),
		'faq'     => array(
			'title'   => 'پرسش و پاسخ بازشونده',
			'content' => fs_pattern_faq(),
		),
		'notice'  => array(
			'title'   => 'جعبه هشدار',
			'content' => fs_pattern_notice(),
		),
		'contact' => array(
			'title'   => 'دعوت به تماس',
			'content' => fs_pattern_contact(),
		),
	);

	foreach ( $patterns as $slug => $pattern ) {
		register_block_pattern(
			'si-file/' . $slug,
			array(
				'title'      => $pattern['title'],
				'categories' => array( 'si-file' ),
				'content'    => $pattern['content'],
			)
		);
	}
}
add_action( 'init', 'fs_register_block_patterns' );

/* -------------------------------------------------------------------------
   الگوها
   ------------------------------------------------------------------------- */

/**
 * متن آغازین برگه.
 *
 * @return string
 */
function fs_pattern_lead() {
	return '<!-- wp:paragraph {"className":"fs-lead"} -->
<p class="fs-lead">این متن آغازین برگه است؛ در دو یا سه جمله بگویید کاربر در این صفحه چه چیزی پیدا می‌کند.</p>
<!-- /wp:paragraph -->';
}

/**
 * سه کارت ویژگی.
 *
 * @return string
 */
function fs_pattern_cards() {
	return '<!-- wp:columns {"className":"fs-cardrow"} -->
<div class="wp-block-columns fs-cardrow">
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"className":"is-style-fs-card"} -->
<div class="wp-block-group is-style-fs-card">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">عنوان اول</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>توضیح کوتاه این ویژگی را اینجا بنویسید.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"className":"is-style-fs-card"} -->
<div class="wp-block-group is-style-fs-card">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">عنوان دوم</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>توضیح کوتاه این ویژگی را اینجا بنویسید.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"className":"is-style-fs-card"} -->
<div class="wp-block-group is-style-fs-card">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">عنوان سوم</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>توضیح کوتاه این ویژگی را اینجا بنویسید.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->';
}

/**
 * مراحل شماره‌دار.
 *
 * @return string
 */
function fs_pattern_steps() {
	return '<!-- wp:group {"className":"is-style-fs-steps"} -->
<div class="wp-block-group is-style-fs-steps">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">گام اول</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>توضیح این مرحله.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">گام دوم</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>توضیح این مرحله.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">گام سوم</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>توضیح این مرحله.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->';
}

/**
 * جعبه ویدیوی آموزشی.
 *
 * @return string
 */
function fs_pattern_video() {
	return '<!-- wp:group {"className":"fs-videobox"} -->
<div class="wp-block-group fs-videobox">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">آموزش ویدیویی</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>این بلوک را انتخاب کنید و به‌جای این متن، بلوک «ویدیو» یا «جاسازی ← آپارات» را بگذارید و نشانی ویدیو را وارد کنید.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->';
}

/**
 * پرسش و پاسخ بازشونده.
 *
 * @return string
 */
function fs_pattern_faq() {
	return '<!-- wp:details {"className":"fs-acc"} -->
<details class="wp-block-details fs-acc"><summary>پرسش نمونه را اینجا بنویسید</summary>
<!-- wp:paragraph -->
<p>پاسخ پرسش را اینجا بنویسید.</p>
<!-- /wp:paragraph -->
</details>
<!-- /wp:details -->';
}

/**
 * جعبه هشدار.
 *
 * @return string
 */
function fs_pattern_notice() {
	return '<!-- wp:group {"className":"is-style-fs-warn"} -->
<div class="wp-block-group is-style-fs-warn">
<!-- wp:paragraph -->
<p><strong>توجه:</strong> متن هشدار یا نکته‌ی مهم را اینجا بنویسید.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->';
}

/**
 * دعوت به تماس.
 *
 * @return string
 */
function fs_pattern_contact() {
	return '<!-- wp:group {"className":"fs-cta"} -->
<div class="wp-block-group fs-cta">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">هنوز سوالی دارید؟</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>پشتیبانی ما هر روز هفته پاسخگوی شماست.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">تماس با پشتیبانی</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->';
}
