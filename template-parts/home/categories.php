<?php
/**
 * بخش «همه دسته‌بندی‌ها».
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

$fs_cats = fs_get_categories();

if ( ! $fs_cats ) {
	return;
}

$fs_total   = count( $fs_cats );
$fs_visible = 8; // موبایل: هشت دسته‌ی اول، بقیه با دکمه باز می‌شوند.
?>
<section class="fs-section fs-section--cats">

	<div class="fs-section__head">
		<div>
			<h2 class="fs-section__title"><?php echo esc_html( fs_copy( 'cats_title' ) ); ?></h2>
			<div class="fs-section__sub"><?php echo esc_html( fs_copy( 'cats_sub' ) ); ?></div>
		</div>
		<div class="fs-cats__count"
			data-collapsed="<?php echo esc_attr( sprintf( '%s از %s دسته', fs_fa_num( min( $fs_visible, $fs_total ) ), fs_fa_num( $fs_total ) ) ); ?>"
			data-expanded="<?php echo esc_attr( sprintf( '%1$s از %1$s دسته', fs_fa_num( $fs_total ) ) ); ?>">
			<?php echo esc_html( sprintf( '%s از %s دسته', fs_fa_num( min( $fs_visible, $fs_total ) ), fs_fa_num( $fs_total ) ) ); ?>
		</div>
	</div>

	<div class="fs-cats" id="fs-cats">
		<?php foreach ( $fs_cats as $fs_i => $fs_cat ) : ?>
			<a class="fs-cat<?php echo $fs_i >= $fs_visible ? ' is-extra' : ''; ?>"
				href="<?php echo esc_url( fs_url( $fs_cat['link'] ) ); ?>">
				<span class="fs-cat__icon" style="<?php echo esc_attr( fs_tint_style( $fs_i ) ); ?>">
					<?php fs_the_icon( 'grid', 19, array( 'width' => '1.9' ) ); ?>
				</span>
				<span class="fs-cat__text">
					<span class="fs-cat__name"><?php echo esc_html( $fs_cat['name'] ); ?></span>
					<?php if ( $fs_cat['n'] ) : ?>
						<span class="fs-cat__n"><?php echo esc_html( $fs_cat['count'] ); ?> <?php echo esc_html( fs_copy( 'cats_unit' ) ); ?></span>
					<?php endif; ?>
				</span>
			</a>
		<?php endforeach; ?>
	</div>

	<?php if ( $fs_total > $fs_visible ) : ?>
		<button class="fs-cats__toggle"
			type="button"
			aria-controls="fs-cats"
			aria-expanded="false"
			data-collapsed="<?php echo esc_attr( sprintf( 'مشاهده همه %s دسته', fs_fa_num( $fs_total ) ) ); ?>"
			data-expanded="بستن فهرست دسته‌ها">
			<?php echo esc_html( sprintf( 'مشاهده همه %s دسته', fs_fa_num( $fs_total ) ) ); ?>
		</button>
	<?php endif; ?>

</section>
