<?php
/**
 * نشان‌های اعتماد و درگاه‌های پرداخت — انتهای نوار کناری تب توضیحات محصول،
 * درست زیر جعبه‌ی «چرا ما؟» و تضمین‌ها.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

$fs_trust = isset( $args['trust'] ) ? $args['trust'] : fs_get_trust_settings();

if ( ! $fs_trust['badges'] && ! $fs_trust['banks'] && ! $fs_trust['caption'] ) {
	return;
}
?>
<div class="fs-ptrust">

	<?php if ( $fs_trust['badges'] ) : ?>
		<div class="fs-trust-badges">
			<?php
			foreach ( $fs_trust['badges'] as $fs_badge ) :
				$fs_img = wp_get_attachment_image(
					$fs_badge['id'],
					'medium',
					false,
					array(
						'loading' => 'lazy',
						'alt'     => $fs_badge['label'],
					)
				);

				if ( ! $fs_img ) {
					continue;
				}

				if ( $fs_badge['link'] ) :
					?>
					<a class="fs-trust-badge" href="<?php echo esc_url( $fs_badge['link'] ); ?>" target="_blank" rel="noopener nofollow">
						<?php echo wp_kses_post( $fs_img ); ?>
					</a>
				<?php else : ?>
					<span class="fs-trust-badge"><?php echo wp_kses_post( $fs_img ); ?></span>
					<?php
				endif;
			endforeach;
			?>
		</div>
	<?php endif; ?>

	<?php if ( $fs_trust['banks'] ) : ?>
		<div class="fs-banks">
			<?php
			foreach ( $fs_trust['banks'] as $fs_bank ) :
				$fs_img = wp_get_attachment_image(
					$fs_bank['id'],
					'thumbnail',
					false,
					array(
						'loading' => 'lazy',
						'alt'     => $fs_bank['label'],
					)
				);

				if ( ! $fs_img ) {
					continue;
				}
				?>
				<span class="fs-bank" <?php echo $fs_bank['label'] ? 'title="' . esc_attr( $fs_bank['label'] ) . '"' : ''; ?>>
					<?php echo wp_kses_post( $fs_img ); ?>
				</span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $fs_trust['caption'] ) : ?>
		<p class="fs-banks__caption"><?php echo esc_html( $fs_trust['caption'] ); ?></p>
	<?php endif; ?>

</div>
