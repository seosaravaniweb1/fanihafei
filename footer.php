<?php
/**
 * پاورقی سایت.
 *
 * پنج بخش: کارت‌های تماس، معرفی سایت، دو ستون لینک از فهرست‌های وردپرس، ستون
 * محصولات (جدیدترین‌ها خودکار و پربازدیدترین‌ها دستی) و ستون نمادها.
 * هر بخشی که در «تنظیمات قالب ← فوتر» خالی بماند اصلاً رندر نمی‌شود.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

$fs_footer   = fs_get_footer_settings();
$fs_contacts = fs_footer_contacts();

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
$fs_description = $fs_footer['desc'] ? $fs_footer['desc'] : get_bloginfo( 'description' );

$fs_newest  = fs_footer_newest_products();
$fs_popular = fs_footer_popular_products();
$fs_banks   = array_filter( array_map( 'absint', (array) $fs_footer['banks'] ) );
?>
	</main>

	<footer class="fs-footer">

		<?php if ( $fs_contacts ) : ?>
			<div class="fs-fcontacts">
				<?php foreach ( $fs_contacts as $fs_contact ) : ?>
					<?php $fs_tag = $fs_contact['link'] ? 'a' : 'div'; ?>
					<<?php echo esc_attr( $fs_tag ); ?> class="fs-fcontact"
						<?php echo $fs_contact['link'] ? 'href="' . esc_url( $fs_contact['link'] ) . '" target="_blank" rel="noopener"' : ''; ?>>
						<span class="fs-fcontact__icon">
							<?php fs_the_icon( $fs_contact['icon'], 19, array( 'stroke' => '#fff', 'width' => '1.9' ) ); ?>
						</span>
						<span class="fs-fcontact__text">
							<span class="fs-fcontact__title"><?php echo esc_html( $fs_contact['title'] ); ?></span>
							<?php if ( $fs_contact['value'] ) : ?>
								<span class="fs-fcontact__value"><?php echo esc_html( fs_fa_num( $fs_contact['value'] ) ); ?></span>
							<?php endif; ?>
						</span>
					</<?php echo esc_attr( $fs_tag ); ?>>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="fs-footer__grid">

			<div class="fs-footer__about">
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
									<a href="<?php echo esc_url( fs_url( $fs_link['url'] ) ); ?>">
										<?php fs_the_icon( 'chevron-prev', 12, array( 'stroke' => 'currentColor', 'width' => '2.4' ) ); ?>
										<span><?php echo esc_html( $fs_link['title'] ); ?></span>
									</a>
								<?php endforeach; ?>
							</nav>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $fs_newest || $fs_popular ) : ?>
				<div class="fs-fprods">

					<div class="fs-fprods__tabs" role="tablist">
						<?php if ( $fs_newest ) : ?>
							<button class="fs-fprods__tab is-active" type="button" role="tab" aria-selected="true">
								<?php echo esc_html( $fs_footer['newest_title'] ); ?>
							</button>
						<?php endif; ?>
						<?php if ( $fs_popular ) : ?>
							<button class="fs-fprods__tab<?php echo $fs_newest ? '' : ' is-active'; ?>" type="button" role="tab"
								aria-selected="<?php echo $fs_newest ? 'false' : 'true'; ?>">
								<?php echo esc_html( $fs_footer['popular_title'] ); ?>
							</button>
						<?php endif; ?>
					</div>

					<?php
					$fs_panes = array();

					if ( $fs_newest ) {
						$fs_panes[] = $fs_newest;
					}

					if ( $fs_popular ) {
						$fs_panes[] = $fs_popular;
					}

					foreach ( $fs_panes as $fs_pi => $fs_pane ) :
						?>
						<div class="fs-fprods__pane" role="tabpanel" <?php echo 0 === $fs_pi ? '' : 'hidden'; ?>>
							<?php foreach ( $fs_pane as $fs_card ) : ?>
								<a class="fs-fprod" href="<?php echo esc_url( fs_url( $fs_card['link'] ) ); ?>">
									<span class="fs-fprod__thumb">
										<?php
										if ( $fs_card['thumb'] ) {
											echo wp_kses_post( $fs_card['thumb'] );
										} else {
											fs_the_icon( 'file', 18, array( 'stroke' => 'rgba(255,255,255,.35)', 'width' => '1.5' ) );
										}
										?>
									</span>
									<span class="fs-fprod__body">
										<span class="fs-fprod__title"><?php echo esc_html( $fs_card['title'] ); ?></span>
										<span class="fs-fprod__price"><?php echo wp_kses_post( $fs_card['price'] ); ?></span>
									</span>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>

				</div>
			<?php endif; ?>

			<?php if ( $fs_footer['trust_html'] || $fs_banks ) : ?>
				<div class="fs-ftrust">

					<?php if ( $fs_footer['trust_title'] ) : ?>
						<div class="fs-footer__head"><?php echo esc_html( $fs_footer['trust_title'] ); ?></div>
					<?php endif; ?>

					<?php if ( $fs_footer['trust_html'] ) : ?>
						<div class="fs-ftrust__codes">
							<?php echo wp_kses( $fs_footer['trust_html'], fs_trust_allowed_html() ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $fs_banks ) : ?>
						<div class="fs-fbanks-row">
							<?php
							foreach ( $fs_banks as $fs_bank_id ) :
								$fs_bank_img = wp_get_attachment_image(
									$fs_bank_id,
									'thumbnail',
									false,
									array(
										'loading' => 'lazy',
										'alt'     => '',
									)
								);

								if ( ! $fs_bank_img ) {
									continue;
								}
								?>
								<span class="fs-fbank-logo"><?php echo wp_kses_post( $fs_bank_img ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $fs_footer['banks_note'] ) : ?>
						<p class="fs-ftrust__note"><?php echo esc_html( $fs_footer['banks_note'] ); ?></p>
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
