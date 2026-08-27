<?php
/**
 * بخش «هزار فایل، آماده دانلود» — سه ستون متحرک.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

$fs_cols = array();

foreach ( array( array( 0, 'up' ), array( 6, 'down' ), array( 12, 'up' ) ) as $fs_spec ) {
	$fs_items = fs_get_marquee_column( $fs_spec[0] );

	if ( $fs_items ) {
		$fs_cols[] = array(
			'items' => $fs_items,
			'dir'   => $fs_spec[1],
		);
	}
}

if ( ! $fs_cols ) {
	return;
}
?>
<section class="fs-section fs-section--mq">

	<div class="fs-mq">

		<div>
			<h2 class="fs-mq__title"><?php echo esc_html( fs_copy( 'mq_title' ) ); ?></h2>

			<p class="fs-mq__desc fs-only-desktop"><?php echo esc_html( fs_copy( 'mq_desc' ) ); ?></p>
			<p class="fs-mq__desc fs-only-mobile"><?php echo esc_html( fs_copy( 'mq_desc_m' ) ); ?></p>

			<a class="fs-mq__btn" href="<?php echo esc_url( fs_shop_url() ); ?>"><?php echo esc_html( fs_copy( 'mq_btn' ) ); ?></a>
		</div>

		<div class="fs-mq__cols" aria-hidden="true" style="grid-template-columns:repeat(<?php echo (int) count( $fs_cols ); ?>,1fr)">
			<?php foreach ( $fs_cols as $fs_col ) : ?>
				<div class="fs-mq__col">
					<div class="fs-mq__track<?php echo 'down' === $fs_col['dir'] ? ' fs-mq__track--down' : ''; ?>">
						<?php foreach ( $fs_col['items'] as $fs_item ) : ?>
							<div class="fs-mq__item">
								<div class="fs-mq__item-title"><?php echo esc_html( $fs_item['title'] ); ?></div>
								<div class="fs-mq__item-price"><?php echo wp_kses_post( $fs_item['price'] ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

	</div>

</section>
