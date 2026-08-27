<?php
/**
 * بخش «زیردسته‌ها را مرور کن».
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

$fs_cats = fs_get_categories();

if ( ! $fs_cats ) {
	return;
}
?>
<section class="fs-section">

	<div class="fs-subcats">

		<div class="fs-subcats__head">
			<div>
				<h2 class="fs-subcats__title"><?php echo esc_html( fs_copy( 'subcats_title' ) ); ?></h2>
				<div class="fs-subcats__sub"><?php echo esc_html( fs_copy( 'subcats_sub' ) ); ?></div>
			</div>
			<a class="fs-subcats__link" href="<?php echo esc_url( fs_url( $fs_cats[0]['link'] ) ); ?>">
				مشاهده صفحه <span class="fs-subcats__linkname"><?php echo esc_html( $fs_cats[0]['name'] ); ?></span>
				<?php fs_the_icon( 'chevron-prev', 15, array( 'stroke' => '#6d28d9' ) ); ?>
			</a>
		</div>

		<div class="fs-tabs" role="tablist" aria-label="دسته‌ها">
			<?php foreach ( $fs_cats as $fs_i => $fs_cat ) : ?>
				<button class="fs-chip<?php echo 0 === $fs_i ? ' is-active' : ''; ?>"
					type="button"
					role="tab"
					id="fs-sub-tab-<?php echo (int) $fs_i; ?>"
					aria-controls="fs-sub-pane-<?php echo (int) $fs_i; ?>"
					aria-selected="<?php echo 0 === $fs_i ? 'true' : 'false'; ?>"
					data-name="<?php echo esc_attr( $fs_cat['name'] ); ?>"
					data-link="<?php echo esc_url( fs_url( $fs_cat['link'] ) ); ?>">
					<?php echo esc_html( $fs_cat['name'] ); ?>
				</button>
			<?php endforeach; ?>
		</div>

		<div class="fs-subcats__mhead">
			<span class="fs-subcats__mname"><?php echo esc_html( $fs_cats[0]['name'] ); ?></span>
			<a class="fs-subcats__mlink" href="<?php echo esc_url( fs_url( $fs_cats[0]['link'] ) ); ?>">مشاهده دسته ›</a>
		</div>

		<?php foreach ( $fs_cats as $fs_i => $fs_cat ) : ?>
			<div class="fs-subcats__pane"
				id="fs-sub-pane-<?php echo (int) $fs_i; ?>"
				role="tabpanel"
				aria-labelledby="fs-sub-tab-<?php echo (int) $fs_i; ?>"
				<?php echo 0 === $fs_i ? '' : 'hidden'; ?>>

				<div class="fs-subcats__grid">
					<?php foreach ( $fs_cat['subs'] as $fs_sub ) : ?>
						<a class="fs-subcard" href="<?php echo esc_url( fs_url( $fs_sub['link'] ) ); ?>">
							<span class="fs-subcard__name"><?php echo esc_html( $fs_sub['name'] ); ?></span>
							<span class="fs-subcard__n"><?php echo esc_html( $fs_sub['count'] ); ?> <?php echo esc_html( fs_copy( 'cats_unit' ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>

			</div>
		<?php endforeach; ?>

	</div>

</section>
