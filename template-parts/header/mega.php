<?php
/**
 * مگامنوی دسته‌بندی (دسکتاپ).
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

$fs_cats = isset( $args['cats'] ) ? $args['cats'] : fs_get_categories();

if ( ! $fs_cats ) {
	return;
}
?>
<?php
/*
 * چرا ردیف‌ها <a href> هستند و نه <button>:
 *
 * پیش از این نام هر دسته داخل یک دکمه بود و تنها لینکِ آن دسته، دکمه‌ای در
 * پنل کناری با متن «مشاهده صفحه دسته». برای خزنده یعنی مهم‌ترین دسته‌های
 * فروشگاه از سربرگ — که در هر صفحه‌ی سایت تکرار می‌شود — هیچ لینکی با متن
 * معنادار نمی‌گرفتند؛ و متن لنگرِ تکراری «مشاهده صفحه دسته» روی همه‌ی دسته‌ها
 * هیچ سیگنالی از موضوع آن دسته نمی‌داد.
 *
 * حالا خود نام دسته لینک است. رفتار تب هم دست‌نخورده می‌ماند: روی دسکتاپ
 * هاور و فوکوس پنل را عوض می‌کند و کلیک به صفحه‌ی دسته می‌رود.
 */
?>
<div class="fs-mega" id="fs-mega" hidden>

	<div class="fs-mega__list" role="tablist" aria-label="فهرست دسته‌ها">
		<?php foreach ( $fs_cats as $fs_i => $fs_cat ) : ?>
			<a class="fs-mega__row<?php echo 0 === $fs_i ? ' is-active' : ''; ?>"
				href="<?php echo esc_url( fs_url( $fs_cat['link'] ) ); ?>"
				role="tab"
				id="fs-mega-tab-<?php echo (int) $fs_i; ?>"
				aria-controls="fs-mega-pane-<?php echo (int) $fs_i; ?>"
				aria-selected="<?php echo 0 === $fs_i ? 'true' : 'false'; ?>">
				<span class="fs-mega__icon" style="<?php echo esc_attr( fs_tint_style( $fs_i ) ); ?>">
					<?php fs_the_icon( 'grid', 15, array( 'width' => '1.9' ) ); ?>
				</span>
				<span class="fs-mega__row-name"><?php echo esc_html( $fs_cat['name'] ); ?></span>
				<?php fs_the_icon( 'chevron-prev', 13, array( 'width' => '2.2' ) ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="fs-mega__panel">
		<?php foreach ( $fs_cats as $fs_i => $fs_cat ) : ?>
			<div class="fs-mega__pane"
				id="fs-mega-pane-<?php echo (int) $fs_i; ?>"
				role="tabpanel"
				aria-labelledby="fs-mega-tab-<?php echo (int) $fs_i; ?>"
				<?php echo 0 === $fs_i ? '' : 'hidden'; ?>>

				<div class="fs-mega__head">
					<div>
						<div class="fs-mega__title"><?php echo esc_html( $fs_cat['name'] ); ?></div>
						<div class="fs-mega__count"><?php echo esc_html( $fs_cat['count'] ); ?> <?php echo esc_html( fs_copy( 'cats_unit' ) ); ?> در این دسته</div>
					</div>
					<a class="fs-btn-brand-sm" href="<?php echo esc_url( fs_url( $fs_cat['link'] ) ); ?>">
						همه فایل‌های <?php echo esc_html( $fs_cat['name'] ); ?>
						<?php fs_the_icon( 'chevron-prev', 14, array( 'stroke' => '#fff', 'width' => '2.2' ) ); ?>
					</a>
				</div>

				<div class="fs-mega__subs">
					<?php foreach ( $fs_cat['subs'] as $fs_sub ) : ?>
						<a class="fs-mega__sub" href="<?php echo esc_url( fs_url( $fs_sub['link'] ) ); ?>">
							<span class="fs-mega__sub-name"><?php echo esc_html( $fs_sub['name'] ); ?></span>
							<span class="fs-mega__sub-n"><?php echo esc_html( $fs_sub['count'] ); ?> <?php echo esc_html( fs_copy( 'cats_unit' ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>

			</div>
		<?php endforeach; ?>
	</div>

</div>
