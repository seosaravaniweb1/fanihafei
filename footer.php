<?php
/**
 * پاورقی سایت.
 *
 * چیدمان: سه جعبه‌ی تماس بالای پاورقی، ستون درباره ما همراه لوگو، دو ستون
 * لینک‌های مهم (از فهرست‌ها)، ستون محصولات با دو تب خودکار، و ستون نمادهای
 * اعتماد به‌همراه آیکون بانک‌ها. هرچه محتوا نداشته باشد رندر نمی‌شود.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

$fs_boxes = fs_get_footer_boxes();

$fs_cols = array();

foreach ( array( 'footer-1', 'footer-2' ) as $fs_location ) {
	$fs_links = fs_menu_items( $fs_location );

	if ( $fs_links ) {
		$fs_cols[] = array(
			'head'  => fs_menu_name( $fs_location ),
			'links' => $fs_links,
		);
	}
}

$fs_seo      = fs_menu_items( 'footer-seo' );
$fs_about    = fs_get_footer_about();
$fs_about    = $fs_about ? $fs_about : get_bloginfo( 'description' );
$fs_symbols  = fs_get_footer_symbols();
$fs_banks    = fs_get_footer_banks();
$fs_bank_cap = fs_get_footer_banks_caption();

// ستون محصولات: دو تب خودکار — جدیدترین و پربازدیدترین.
$fs_ptabs = array();

if ( fs_footer_products_enabled() && fs_has_woo() ) {
	$fs_new_items = fs_get_footer_products( 'date', 4 );
	$fs_pop_items = fs_get_footer_products( 'popularity', 4 );

	if ( $fs_new_items ) {
		$fs_ptabs[] = array(
			'name'  => 'جدیدترین‌ها',
			'items' => $fs_new_items,
		);
	}

	if ( $fs_pop_items ) {
		$fs_ptabs[] = array(
			'name'  => 'پربازدیدترین‌ها',
			'items' => $fs_pop_items,
		);
	}
}
?>
	</main>

	<footer class="fs-footer">

		<?php if ( $fs_boxes ) : ?>
			<div class="fs-fboxes">
				<?php
				foreach ( $fs_boxes as $fs_box ) :
					$fs_tag = $fs_box['link'] ? 'a' : 'div';
					?>
					<<?php echo esc_html( $fs_tag ); ?> class="fs-fbox"
						<?php echo $fs_box['link'] ? 'href="' . esc_url( $fs_box['link'] ) . '" rel="noopener"' : ''; ?>>
						<span class="fs-fbox__icon"><?php fs_the_icon( $fs_box['icon'], 20, array( 'stroke' => '#fff', 'width' => '1.9' ) ); ?></span>
						<span class="fs-fbox__body">
							<?php if ( $fs_box['title'] ) : ?>
								<span class="fs-fbox__title"><?php echo esc_html( $fs_box['title'] ); ?></span>
							<?php endif; ?>
							<?php if ( $fs_box['text'] ) : ?>
								<span class="fs-fbox__text"><?php echo esc_html( fs_fa_num( $fs_box['text'] ) ); ?></span>
							<?php endif; ?>
						</span>
					</<?php echo esc_html( $fs_tag ); ?>>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="fs-footer__grid">

			<div class="fs-footer__about">
				<?php fs_the_logo( 'footer' ); ?>

				<?php if ( $fs_about ) : ?>
					<p class="fs-footer__desc"><?php echo esc_html( $fs_about ); ?></p>
				<?php endif; ?>
			</div>

			<?php foreach ( $fs_cols as $fs_col ) : ?>
				<div class="fs-footer__col">
					<?php if ( $fs_col['head'] ) : ?>
						<div class="fs-footer__head"><?php echo esc_html( $fs_col['head'] ); ?></div>
					<?php endif; ?>
					<nav class="fs-footer__links" aria-label="<?php echo esc_attr( $fs_col['head'] ); ?>">
						<?php foreach ( $fs_col['links'] as $fs_link ) : ?>
							<a href="<?php echo esc_url( fs_url( $fs_link['url'] ) ); ?>">
								<?php fs_the_icon( 'chevron-prev', 12, array( 'stroke' => 'currentColor', 'width' => '2.4' ) ); ?>
								<span><?php echo esc_html( $fs_link['title'] ); ?></span>
							</a>
						<?php endforeach; ?>
					</nav>
				</div>
			<?php endforeach; ?>

			<?php if ( $fs_ptabs ) : ?>
				<div class="fs-footer__col fs-fprod" data-fprod>
					<div class="fs-fprod__tabs" role="tablist">
						<?php foreach ( $fs_ptabs as $fs_ti => $fs_ptab ) : ?>
							<button class="fs-fprod__tab<?php echo 0 === $fs_ti ? ' is-active' : ''; ?>" type="button" role="tab"
								aria-selected="<?php echo 0 === $fs_ti ? 'true' : 'false'; ?>">
								<?php echo esc_html( $fs_ptab['name'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>

					<?php foreach ( $fs_ptabs as $fs_ti => $fs_ptab ) : ?>
						<div class="fs-fprod__pane" role="tabpanel" <?php echo 0 === $fs_ti ? '' : 'hidden'; ?>>
							<?php foreach ( $fs_ptab['items'] as $fs_item ) : ?>
								<a class="fs-fprod__item" href="<?php echo esc_url( $fs_item['link'] ); ?>">
									<span class="fs-fprod__thumb">
										<?php
										if ( $fs_item['thumb'] ) {
											echo wp_kses_post( $fs_item['thumb'] );
										} else {
											fs_the_icon( 'file', 16, array( 'stroke' => 'rgba(255,255,255,.4)', 'width' => '1.6' ) );
										}
										?>
									</span>
									<span class="fs-fprod__body">
										<span class="fs-fprod__title"><?php echo esc_html( $fs_item['title'] ); ?></span>
										<span class="fs-fprod__price"><?php echo wp_kses_post( $fs_item['price'] ); ?></span>
									</span>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $fs_symbols || $fs_banks ) : ?>
				<div class="fs-footer__col fs-fsym">
					<div class="fs-footer__head">نمادهای اعتماد</div>

					<?php if ( $fs_symbols ) : ?>
						<div class="fs-fsym__row">
							<?php
							foreach ( $fs_symbols as $fs_symbol ) :
								$fs_img = wp_get_attachment_image(
									$fs_symbol['id'],
									'medium',
									false,
									array(
										'loading' => 'lazy',
										'alt'     => $fs_symbol['label'] ? $fs_symbol['label'] : 'نماد اعتماد',
									)
								);

								if ( ! $fs_img ) {
									continue;
								}

								if ( $fs_symbol['link'] ) :
									?>
									<a class="fs-fsym__item" href="<?php echo esc_url( $fs_symbol['link'] ); ?>" target="_blank" rel="noopener nofollow">
										<?php echo wp_kses_post( $fs_img ); ?>
									</a>
								<?php else : ?>
									<span class="fs-fsym__item"><?php echo wp_kses_post( $fs_img ); ?></span>
									<?php
								endif;
							endforeach;
							?>
						</div>
					<?php endif; ?>

					<?php if ( $fs_banks ) : ?>
						<div class="fs-fbanks">
							<?php
							foreach ( $fs_banks as $fs_bank ) :
								$fs_img = wp_get_attachment_image(
									$fs_bank['id'],
									'thumbnail',
									false,
									array(
										'loading' => 'lazy',
										'alt'     => $fs_bank['label'] ? $fs_bank['label'] : 'بانک',
									)
								);

								if ( ! $fs_img ) {
									continue;
								}
								?>
								<span class="fs-fbank" <?php echo $fs_bank['label'] ? 'title="' . esc_attr( $fs_bank['label'] ) . '"' : ''; ?>>
									<?php echo wp_kses_post( $fs_img ); ?>
								</span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $fs_bank_cap ) : ?>
						<p class="fs-fbanks__caption"><?php echo esc_html( $fs_bank_cap ); ?></p>
					<?php endif; ?>
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
