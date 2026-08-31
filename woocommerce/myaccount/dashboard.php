<?php
/**
 * پیشخوان کاربری — برگردان دقیق طرح «Account Dashboard UI».
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

$fs_user      = wp_get_current_user();
$fs_name      = trim( $fs_user->first_name . ' ' . $fs_user->last_name );
$fs_name      = $fs_name ? $fs_name : $fs_user->display_name;
$fs_initial   = function_exists( 'mb_substr' ) ? mb_substr( $fs_name, 0, 1 ) : substr( $fs_name, 0, 1 );
$fs_stats     = fs_account_stats( $fs_user->ID );
$fs_message   = fs_dashboard_message();
$fs_percent   = fs_profile_completeness( $fs_user );
$fs_phone     = get_user_meta( $fs_user->ID, FS_PHONE_META, true );
$fs_downloads = fs_has_woo() ? wc_get_customer_available_downloads( $fs_user->ID ) : array();
?>

<div class="fs-greet">
	<span class="fs-greet__glow" aria-hidden="true"></span>

	<div class="fs-greet__who">
		<span class="fs-greet__avatar"><?php echo esc_html( $fs_initial ); ?></span>
		<div>
			<div class="fs-greet__hi">سلام، <?php echo esc_html( $fs_name ); ?> عزیز</div>
			<div class="fs-greet__meta">
				<?php if ( $fs_user->user_email && ! preg_match( '/^09\d{9}@/', $fs_user->user_email ) ) : ?>
					<span><?php fs_the_icon( 'file', 14, array( 'stroke' => '#c4b5fd', 'width' => '1.9' ) ); ?> <?php echo esc_html( $fs_user->user_email ); ?></span>
				<?php endif; ?>
				<?php if ( $fs_phone ) : ?>
					<span><?php fs_the_icon( 'user', 14, array( 'stroke' => '#c4b5fd', 'width' => '1.9' ) ); ?> <?php echo esc_html( fs_fa_num( $fs_phone ) ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<a class="fs-greet__logout" href="<?php echo esc_url( wc_logout_url() ); ?>">
		<?php fs_the_icon( 'chevron-next', 15 ); ?>
		خروج از حساب
	</a>
</div>

<?php if ( $fs_stats ) : ?>
	<div class="fs-dash__stats">
		<?php foreach ( $fs_stats as $fs_i => $fs_stat ) : ?>
			<div class="fs-dash__stat">
				<span class="fs-dash__stat-icon" style="<?php echo esc_attr( fs_tint_style( $fs_i ) ); ?>">
					<?php fs_the_icon( $fs_stat['icon'], 18, array( 'width' => '1.9' ) ); ?>
				</span>
				<span class="fs-dash__stat-v"><?php echo esc_html( $fs_stat['v'] ); ?></span>
				<span class="fs-dash__stat-k"><?php echo esc_html( $fs_stat['k'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if ( $fs_message ) : ?>
	<div class="fs-promo">
		<span class="fs-promo__icon"><?php fs_the_icon( 'zap', 20, array( 'stroke' => '#fff', 'width' => '1.9' ) ); ?></span>
		<div class="fs-promo__body">
			<div class="fs-promo__title"><?php echo esc_html( $fs_message['title'] ); ?></div>
			<div class="fs-promo__text fs-rich"><?php echo wp_kses_post( $fs_message['content'] ); ?></div>
		</div>
	</div>
<?php endif; ?>

<?php if ( $fs_percent < 100 ) : ?>
	<div class="fs-quickform">
		<div class="fs-quickform__head">
			<span class="fs-quickform__icon"><?php fs_the_icon( 'file-lines', 17, array( 'stroke' => '#6d28d9', 'width' => '1.9' ) ); ?></span>
			<div>
				<div class="fs-quickform__title">تکمیل سریع پروفایل کاربری</div>
				<div class="fs-quickform__sub">برای ارسال لینک دانلود، مشخصات خود را کامل کنید. (سایت فایلی است — نیازی به آدرس پستی نیست.)</div>
			</div>
		</div>

		<?php get_template_part( 'template-parts/account/profile-form', null, array( 'compact' => true ) ); ?>
	</div>
<?php endif; ?>

<?php if ( $fs_downloads ) : ?>
	<div class="fs-dash__block">
		<div class="fs-dash__block-head">
			<h3 class="fs-dash__block-title">آخرین دانلودها</h3>
			<a class="fs-section__link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'downloads' ) ); ?>">همه ›</a>
		</div>

		<div class="fs-dlist">
			<?php foreach ( array_slice( $fs_downloads, 0, 5 ) as $fs_i => $fs_dl ) : ?>
				<div class="fs-ditem">
					<span class="fs-ditem__icon" style="background:<?php echo esc_attr( fs_grad( $fs_i ) ); ?>">
						<?php fs_the_icon( 'file', 17, array( 'stroke' => 'rgba(15,23,42,.45)', 'width' => '1.6' ) ); ?>
					</span>
					<span class="fs-ditem__body">
						<span class="fs-ditem__title"><?php echo esc_html( $fs_dl['product_name'] ); ?></span>
						<span class="fs-ditem__meta">
							<?php
							if ( ! empty( $fs_dl['access_expires'] ) ) {
								echo 'تا ' . esc_html( fs_fa_num( date_i18n( 'Y/m/d', strtotime( $fs_dl['access_expires'] ) ) ) );
							} else {
								echo 'دسترسی نامحدود';
							}
							?>
						</span>
					</span>
					<?php fs_the_download_button( $fs_dl['download_url'], 'دانلود', 'fs-ditem__btn', $fs_dl['product_name'] ); ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
<?php endif; ?>

<?php
if ( fs_has_woo() ) :
	$fs_orders = wc_get_orders(
		array(
			'customer_id' => $fs_user->ID,
			'limit'       => 5,
			'orderby'     => 'date',
			'order'       => 'DESC',
		)
	);

	if ( $fs_orders ) :
		?>
		<div class="fs-dash__block">
			<div class="fs-dash__block-head">
				<h3 class="fs-dash__block-title">سفارش‌های من</h3>
				<a class="fs-section__link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">همه ›</a>
			</div>

			<div class="fs-otable">
				<div class="fs-otable__row fs-otable__row--head">
					<span>شماره سفارش</span>
					<span>تاریخ</span>
					<span>اقلام</span>
					<span>مبلغ</span>
					<span>وضعیت</span>
				</div>
				<?php foreach ( $fs_orders as $fs_order ) : ?>
					<a class="fs-otable__row" href="<?php echo esc_url( $fs_order->get_view_order_url() ); ?>">
						<span class="fs-otable__id"><?php echo esc_html( fs_fa_num( $fs_order->get_order_number() ) ); ?></span>
						<span><?php echo esc_html( fs_fa_num( wc_format_datetime( $fs_order->get_date_created() ) ) ); ?></span>
						<span><?php echo esc_html( fs_fa_num( $fs_order->get_item_count() ) ); ?> فایل</span>
						<span class="fs-otable__total"><?php echo wp_kses_post( fs_fa_num_html( $fs_order->get_formatted_order_total() ) ); ?></span>
						<span><span class="fs-otable__status"><?php echo esc_html( wc_get_order_status_name( $fs_order->get_status() ) ); ?></span></span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	endif;
endif;
?>
