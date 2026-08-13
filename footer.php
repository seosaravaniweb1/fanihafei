<?php
/**
 * پاورقی سایت.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

$fs_cols = array();

foreach ( array( 'footer-1', 'footer-2', 'footer-3' ) as $fs_location ) {
	$fs_links = fs_menu_items( $fs_location );

	if ( $fs_links ) {
		$fs_cols[] = array(
			'head'  => fs_menu_name( $fs_location ),
			'links' => $fs_links,
		);
	}
}

$fs_seo         = fs_menu_items( 'footer-seo' );
$fs_description = get_bloginfo( 'description' );
?>
	</main>

	<footer class="fs-footer">

		<div class="fs-footer__grid">

			<div>
				<?php fs_the_logo( 'footer' ); ?>

				<?php if ( $fs_description ) : ?>
					<p class="fs-footer__desc"><?php echo esc_html( $fs_description ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( $fs_cols ) : ?>
				<div class="fs-footer__cols">
					<?php foreach ( $fs_cols as $fs_col ) : ?>
						<div>
							<?php if ( $fs_col['head'] ) : ?>
								<div class="fs-footer__head"><?php echo esc_html( $fs_col['head'] ); ?></div>
							<?php endif; ?>
							<nav class="fs-footer__links" aria-label="<?php echo esc_attr( $fs_col['head'] ); ?>">
								<?php foreach ( $fs_col['links'] as $fs_link ) : ?>
									<a href="<?php echo esc_url( fs_url( $fs_link['url'] ) ); ?>"><?php echo esc_html( $fs_link['title'] ); ?></a>
								<?php endforeach; ?>
							</nav>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		</div>

		<?php if ( $fs_seo ) : ?>
			<nav class="fs-footer__seo" aria-label="جست‌وجوهای پرتکرار">
				<?php foreach ( $fs_seo as $fs_link ) : ?>
					<a href="<?php echo esc_url( fs_url( $fs_link['url'] ) ); ?>"><?php echo esc_html( $fs_link['title'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<div class="fs-footer__copy">
			© <?php echo esc_html( fs_fa_num( wp_date( 'Y' ) ) ); ?> <?php bloginfo( 'name' ); ?> — همه حقوق محفوظ است.
		</div>

	</footer>

</div><!-- .fs-container -->

<?php wp_footer(); ?>
</body>
</html>
