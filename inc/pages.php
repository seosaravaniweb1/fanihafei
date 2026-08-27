<?php
/**
 * برگه‌های آماده و استایل بلوک‌های گوتنبرگ.
 *
 * سه کار انجام می‌شود:
 *
 * ۱. سبک‌های بلوک (register_block_style) — وقتی روی یک بلوک «گروه» کلیک کنید،
 *    در نوار کناری گوتنبرگ سبک‌های آماده‌ی قالب را می‌بینید و با یک کلیک
 *    جعبه‌ی کارت، جعبه‌ی نکته یا جعبه‌ی هشدار می‌سازید.
 *
 * ۲. الگوها (register_block_pattern) — بخش‌های آماده‌ای مثل «مراحل خرید»،
 *    «پرسش و پاسخ» و «جعبه ویدیو» که از دکمه‌ی + گوتنبرگ، تب «الگوها»،
 *    دسته‌ی «قالب لوکسو فایل» درج می‌شوند.
 *
 * ۳. برگه‌های آماده — پنج برگه‌ی کامل و از پیش طراحی‌شده که از «تنظیمات قالب ←
 *    برگه‌ها» با یک کلیک ساخته می‌شوند و بعد در ویرایشگر گوتنبرگ قابل ویرایش‌اند.
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

/* -------------------------------------------------------------------------
   ساخت برگه‌های آماده
   ------------------------------------------------------------------------- */

/**
 * شناسه‌ی برگه‌ای که قبلاً از روی همین کلید ساخته شده.
 *
 * @param string $key کلید برگه.
 * @return int
 */
function fs_starter_page_id( $key ) {
	$map = (array) get_option( 'fs_starter_pages', array() );
	$id  = isset( $map[ $key ] ) ? (int) $map[ $key ] : 0;

	if ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
		return $id;
	}

	return 0;
}

/**
 * ساخت یک برگه‌ی آماده.
 *
 * اگر برگه قبلاً ساخته شده باشد دوباره ساخته نمی‌شود تا ویرایش‌های کاربر از
 * بین نرود؛ برای بازسازی باید اول برگه‌ی قبلی حذف شود.
 *
 * @param string $key کلید برگه.
 * @return int|WP_Error شناسه برگه.
 */
function fs_create_starter_page( $key ) {
	$pages = fs_starter_pages();

	if ( ! isset( $pages[ $key ] ) ) {
		return new WP_Error( 'fs_no_page', 'این برگه تعریف نشده است.' );
	}

	if ( fs_starter_page_id( $key ) ) {
		return new WP_Error( 'fs_page_exists', 'این برگه از قبل ساخته شده است.' );
	}

	$page = $pages[ $key ];

	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $page['title'],
			'post_content' => call_user_func( $page['callback'] ),
		),
		true
	);

	if ( is_wp_error( $id ) ) {
		return $id;
	}

	$map         = (array) get_option( 'fs_starter_pages', array() );
	$map[ $key ] = (int) $id;
	update_option( 'fs_starter_pages', $map );

	return (int) $id;
}

/**
 * ساخت برگه از دکمه‌ی پنل.
 *
 * @return void
 */
function fs_handle_create_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}

	check_admin_referer( 'fs_create_page' );

	$key    = isset( $_POST['page_key'] ) ? sanitize_key( wp_unslash( $_POST['page_key'] ) ) : '';
	$result = fs_create_starter_page( $key );
	$status = is_wp_error( $result ) ? 'error' : 'created';

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'    => 'fs-theme-settings',
				'tab'     => 'pages',
				'fs_page' => $status,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_fs_create_page', 'fs_handle_create_page' );

/**
 * بازنشانی یک برگه به نسخه‌ی اولیه.
 *
 * محتوای فعلی دور ریخته نمی‌شود: وردپرس پیش از جایگزینی یک نسخه‌ی بازبینی
 * ذخیره می‌کند، پس اگر پشیمان شدید از بخش «بازبینی‌ها»ی همان برگه قابل
 * برگرداندن است.
 *
 * @return void
 */
function fs_handle_reset_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}

	check_admin_referer( 'fs_reset_page' );

	$key   = isset( $_POST['page_key'] ) ? sanitize_key( wp_unslash( $_POST['page_key'] ) ) : '';
	$pages = fs_starter_pages();
	$id    = fs_starter_page_id( $key );

	$status = 'error';

	if ( $id && isset( $pages[ $key ] ) ) {
		$updated = wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => call_user_func( $pages[ $key ]['callback'] ),
			),
			true
		);

		$status = is_wp_error( $updated ) ? 'error' : 'reset';
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'    => 'fs-theme-settings',
				'tab'     => 'pages',
				'fs_page' => $status,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_fs_reset_page', 'fs_handle_reset_page' );

/**
 * محتوای تب «برگه‌ها».
 *
 * @return void
 */
function fs_pages_tab_content() {
	?>
	<h2 style="margin-top:24px">برگه‌های آماده</h2>
	<p class="description">
		هر برگه با بلوک‌های استاندارد گوتنبرگ ساخته می‌شود و بعد از ساخت، مثل هر برگه‌ی دیگری قابل ویرایش است.
		استایل همه‌ی این بلوک‌ها در خود قالب تعریف شده، پس نیازی به افزونه‌ی صفحه‌ساز نیست.
	</p>

	<?php if ( isset( $_GET['fs_page'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php
		$fs_state = sanitize_key( wp_unslash( $_GET['fs_page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$fs_messages = array(
			'created' => array( 'success', 'برگه ساخته شد.' ),
			'reset'   => array( 'success', 'برگه به نسخه‌ی اولیه برگشت. محتوای قبلی در «بازبینی‌ها»ی همان برگه باقی است.' ),
		);

		$fs_msg = isset( $fs_messages[ $fs_state ] ) ? $fs_messages[ $fs_state ] : array( 'warning', 'کار انجام نشد؛ احتمالاً برگه از قبل وجود دارد.' );
		?>
		<div class="notice notice-<?php echo esc_attr( $fs_msg[0] ); ?> is-dismissible">
			<p><?php echo esc_html( $fs_msg[1] ); ?></p>
		</div>
	<?php endif; ?>

	<table class="widefat striped" style="max-width:900px;margin-top:14px">
		<thead>
			<tr>
				<th style="width:220px">برگه</th>
				<th>توضیح</th>
				<th style="width:190px">وضعیت</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( fs_starter_pages() as $fs_key => $fs_page ) : ?>
				<?php $fs_id = fs_starter_page_id( $fs_key ); ?>
				<tr>
					<td><strong><?php echo esc_html( $fs_page['title'] ); ?></strong></td>
					<td><?php echo esc_html( $fs_page['desc'] ); ?></td>
					<td>
						<?php if ( $fs_id ) : ?>
							<a class="button button-primary" href="<?php echo esc_url( get_edit_post_link( $fs_id ) ); ?>">ویرایش متن</a>
							<a class="button" href="<?php echo esc_url( get_permalink( $fs_id ) ); ?>" target="_blank" rel="noopener">مشاهده</a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:6px 0 0"
								onsubmit="return confirm('محتوای فعلی این برگه با نسخه‌ی اولیه جایگزین می‌شود. ادامه می‌دهید؟');">
								<?php wp_nonce_field( 'fs_reset_page' ); ?>
								<input type="hidden" name="action" value="fs_reset_page">
								<input type="hidden" name="page_key" value="<?php echo esc_attr( $fs_key ); ?>">
								<button type="submit" class="button-link" style="color:#b32d2e">بازنشانی به نسخه اولیه</button>
							</form>
						<?php else : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0">
								<?php wp_nonce_field( 'fs_create_page' ); ?>
								<input type="hidden" name="action" value="fs_create_page">
								<input type="hidden" name="page_key" value="<?php echo esc_attr( $fs_key ); ?>">
								<button type="submit" class="button button-primary">ساخت برگه</button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<div class="notice notice-info inline" style="max-width:900px;margin:16px 0 0;padding:4px 12px">
		<p><strong>ویرایش متن برگه‌ها:</strong> بعد از ساخت، هر برگه یک برگه‌ی عادی وردپرس است.
		روی «ویرایش متن» بزنید (یا از منوی <strong>برگه‌ها</strong> بازش کنید) و مثل هر برگه‌ی دیگری
		متن‌ها را در گوتنبرگ عوض کنید و «به‌روزرسانی» بزنید. تغییرات بلافاصله روی سایت اعمال می‌شود.</p>
		<p>محتوای اولیه فقط یک نقطه‌ی شروع است؛ به‌روزرسانی قالب هیچ‌وقت متن برگه‌های شما را
		بازنویسی نمی‌کند. اگر خواستید به نسخه‌ی اول برگردید، دکمه‌ی «بازنشانی» همین جدول را بزنید.</p>
	</div>

	<h2 style="margin-top:30px">الگوهای آماده در گوتنبرگ</h2>
	<p class="description">
		داخل ویرایشگر هر برگه، از دکمه‌ی <strong>+</strong> و تب <strong>الگوها</strong>، دسته‌ی
		«قالب لوکسو فایل» را باز کنید: متن آغازین، سه کارت ویژگی، مراحل شماره‌دار، جعبه ویدیو،
		پرسش و پاسخ بازشونده، جعبه هشدار و دعوت به تماس آماده‌ی درج‌اند.
		همچنین وقتی روی یک بلوک «گروه» کلیک کنید، در نوار کناری بخش «سبک‌ها» می‌توانید
		کارت، جعبه نکته، جعبه هشدار یا مراحل شماره‌دار را انتخاب کنید.
	</p>
	<?php
}
