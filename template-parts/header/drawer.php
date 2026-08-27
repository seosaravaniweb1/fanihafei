<?php
/**
 * کشوی دسته‌بندی (موبایل).
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

$fs_cats = isset( $args['cats'] ) ? $args['cats'] : fs_get_categories();

if ( ! $fs_cats ) {
	return;
}
?>
<div class="fs-drawer" id="fs-drawer" hidden>

	<div class="fs-drawer__hint"><?php echo esc_html( fs_copy( 'drawer_hint' ) ); ?></div>

	<?php foreach ( $fs_cats as $fs_i => $fs_cat ) : ?>
		<div class="fs-drawer__item">

			<div class="fs-drawer__row">
				<span class="fs-drawer__icon" style="<?php echo esc_attr( fs_tint_style( $fs_i ) ); ?>">
					<?php fs_the_icon( 'grid', 15, array( 'width' => '1.9' ) ); ?>
				</span>

				<a class="fs-drawer__link" href="<?php echo esc_url( fs_url( $fs_cat['link'] ) ); ?>">
					<span class="fs-drawer__name"><?php echo esc_html( $fs_cat['name'] ); ?></span>
					<?php if ( $fs_cat['n'] ) : ?>
						<span class="fs-drawer__n"><?php echo esc_html( $fs_cat['count'] ); ?> <?php echo esc_html( fs_copy( 'cats_unit' ) ); ?></span>
					<?php endif; ?>
				</a>

				<button class="fs-drawer__caret"
					type="button"
					aria-expanded="false"
					aria-controls="fs-drawer-panel-<?php echo (int) $fs_i; ?>">
					<span class="fs-sr-only">زیردسته‌های <?php echo esc_attr( $fs_cat['name'] ); ?></span>
					<?php fs_the_icon( 'chevron-down', 14, array( 'width' => '2.4' ) ); ?>
				</button>
			</div>

			<div class="fs-drawer__panel" id="fs-drawer-panel-<?php echo (int) $fs_i; ?>" hidden>
				<div class="fs-drawer__subs">
					<?php foreach ( $fs_cat['subs'] as $fs_sub ) : ?>
						<a class="fs-drawer__sub" href="<?php echo esc_url( fs_url( $fs_sub['link'] ) ); ?>">
							<span class="fs-drawer__sub-name"><?php echo esc_html( $fs_sub['name'] ); ?></span>
							<?php if ( $fs_sub['n'] ) : ?>
								<span class="fs-drawer__sub-n"><?php echo esc_html( $fs_sub['count'] ); ?> <?php echo esc_html( fs_copy( 'cats_unit' ) ); ?></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>

				<a class="fs-drawer__all" href="<?php echo esc_url( fs_url( $fs_cat['link'] ) ); ?>">
					مشاهده همه فایل‌های <?php echo esc_html( $fs_cat['name'] ); ?>
					<?php fs_the_icon( 'chevron-prev', 14, array( 'stroke' => '#fff', 'width' => '2.2' ) ); ?>
				</a>
			</div>

		</div>
	<?php endforeach; ?>

</div>
