<?php
/**
 * کارت محصول (ریل افقی).
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

$fs_card = isset( $args['card'] ) ? $args['card'] : null;

if ( ! $fs_card ) {
	return;
}

$fs_is_new = ! empty( $args['badge'] );
?>
<a class="fs-pcard" href="<?php echo esc_url( fs_url( $fs_card['link'] ) ); ?>">

	<span class="fs-pcard__thumb" style="background:<?php echo esc_attr( $fs_card['grad'] ); ?>">
		<?php if ( $fs_is_new ) : ?>
			<span class="fs-pcard__badge"><?php echo esc_html( $args['badge'] ); ?></span>
		<?php endif; ?>
		<?php
		if ( $fs_card['thumb'] ) {
			echo wp_kses_post( $fs_card['thumb'] );
		} else {
			fs_the_icon( 'file', 34, array( 'stroke' => 'rgba(15,23,42,.32)', 'width' => '1.5' ) );
		}
		?>
	</span>

	<span class="fs-pcard__body">
		<span class="fs-pcard__title"><?php echo esc_html( $fs_card['title'] ); ?></span>

		<span class="fs-pcard__chips">
			<?php foreach ( $fs_card['chips'] as $fs_chip ) : ?>
				<span class="fs-chipmeta">
					<?php fs_the_icon( $fs_chip['icon'], 12, array( 'stroke' => '#94a3b8' ) ); ?>
					<?php echo esc_html( $fs_chip['text'] ); ?>
				</span>
			<?php endforeach; ?>
		</span>

		<span class="fs-pcard__foot">
			<span class="fs-pcard__price"><?php echo wp_kses_post( $fs_card['price'] ); ?></span>
			<span class="fs-pcard__dl">
				<?php fs_the_icon( 'download', 16, array( 'stroke' => '#6d28d9' ) ); ?>
			</span>
		</span>
	</span>

</a>
