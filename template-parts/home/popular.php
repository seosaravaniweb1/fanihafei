<?php
/**
 * بخش «پربازدیدترین‌ها».
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

$fs_popular = fs_get_popular( 5, 8 );

if ( ! $fs_popular ) {
	return;
}
?>
<section class="fs-section fs-section--pop">

	<div class="fs-pop">

		<span class="fs-pop__glow" aria-hidden="true"></span>

		<div class="fs-pop__grid">

			<div>
				<span class="fs-pop__badge">
					<?php fs_the_icon( 'trending', 13, array( 'stroke' => '#6ee7b7', 'width' => '2.2' ) ); ?>
					<?php echo esc_html( fs_copy( 'pop_badge' ) ); ?>
				</span>

				<h2 class="fs-pop__title">
					<span class="fs-only-desktop"><?php echo esc_html( fs_copy( 'pop_title' ) ); ?></span>
					<span class="fs-only-mobile"><?php echo esc_html( fs_copy( 'pop_title_m' ) ); ?></span>
				</h2>

				<div class="fs-pop__tabs" role="tablist" aria-label="رشته‌های پربازدید">
					<?php foreach ( $fs_popular as $fs_i => $fs_tab ) : ?>
						<button class="fs-darktab<?php echo 0 === $fs_i ? ' is-active' : ''; ?>"
							type="button"
							role="tab"
							id="fs-pop-tab-<?php echo (int) $fs_i; ?>"
							aria-controls="fs-pop-pane-<?php echo (int) $fs_i; ?>"
							aria-selected="<?php echo 0 === $fs_i ? 'true' : 'false'; ?>"
							data-name="<?php echo esc_attr( $fs_tab['name'] ); ?>"
							data-link="<?php echo esc_url( fs_url( $fs_tab['link'] ) ); ?>">
							<?php echo esc_html( $fs_tab['name'] ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<div>
				<div class="fs-pop__head">
					<span class="fs-pop__active">پربازدیدترین‌های <?php echo esc_html( $fs_popular[0]['name'] ); ?></span>
					<a class="fs-pop__all" href="<?php echo esc_url( fs_url( $fs_popular[0]['link'] ) ); ?>">مشاهده همه ›</a>
				</div>

				<?php foreach ( $fs_popular as $fs_i => $fs_tab ) : ?>
					<div class="fs-pop__pane"
						id="fs-pop-pane-<?php echo (int) $fs_i; ?>"
						role="tabpanel"
						aria-labelledby="fs-pop-tab-<?php echo (int) $fs_i; ?>"
						<?php echo 0 === $fs_i ? '' : 'hidden'; ?>>

						<div class="fs-pop__items">
							<?php foreach ( $fs_tab['items'] as $fs_item ) : ?>
								<a class="fs-vcard" href="<?php echo esc_url( fs_url( $fs_item['link'] ) ); ?>">

									<span class="fs-vcard__top">
										<span class="fs-vcard__fmt">
											<?php fs_the_icon( 'file', 12, array( 'stroke' => '#94a3b8' ) ); ?>
											<span class="fs-only-desktop">PDF</span>
											<span class="fs-only-mobile">PDF · پاسخنامه دارد</span>
										</span>
										<?php if ( $fs_item['views'] ) : ?>
											<span class="fs-vcard__views">
												<span class="fs-only-desktop"><?php echo esc_html( $fs_item['views'] ); ?> بازدید</span>
												<span class="fs-only-mobile"><?php echo esc_html( $fs_item['views'] ); ?></span>
											</span>
										<?php endif; ?>
									</span>

									<span class="fs-vcard__title"><?php echo esc_html( $fs_item['title'] ); ?></span>

									<span class="fs-vcard__ans">
										<?php fs_the_icon( 'check', 11, array( 'stroke' => '#059669', 'width' => '2.6' ) ); ?>
										پاسخنامه دارد
									</span>

									<span class="fs-vcard__foot">
										<span class="fs-vcard__q"><?php echo esc_html( $fs_item['q'] ); ?> سوال</span>
										<span class="fs-vcard__price"><?php echo wp_kses_post( $fs_item['price'] ); ?></span>
									</span>

									<span class="fs-vcard__buy">خرید و دانلود</span>

								</a>
							<?php endforeach; ?>
						</div>

					</div>
				<?php endforeach; ?>
			</div>

		</div>

	</div>

</section>
