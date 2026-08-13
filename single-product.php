<?php
/**
 * صفحه تک‌محصول — برگردان طرح «Product Page UI».
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	global $product;

	if ( ! $product instanceof WC_Product ) {
		$product = wc_get_product( get_the_ID() );
	}

	$fs_id         = $product->get_id();
	$fs_specs      = fs_get_product_specs( $fs_id );
	$fs_toc        = fs_get_product_toc( $fs_id );
	$fs_guarantees = fs_get_guarantees();
	$fs_trust      = fs_get_trust_settings();
	$fs_rev_summary = fs_get_review_summary( $product );
	$fs_reviews     = $fs_rev_summary ? fs_get_product_reviews( $fs_id ) : array();

	// کد محصول همیشه از شناسه‌ی خود محصول خوانده می‌شود.
	$fs_code     = $fs_id;
	$fs_code_pc  = fs_product_field( $fs_id, 'computer_code' );
	$fs_views    = fs_product_views( $fs_id );
	$fs_pages    = fs_product_field( $fs_id, 'page_count' );
	$fs_qcount   = fs_product_questions( $fs_id );
	$fs_created  = $product->get_date_created();
	$fs_is_fresh = $fs_created && ( time() - $fs_created->getTimestamp() ) < 14 * DAY_IN_SECONDS;
	$fs_sold     = (int) $product->get_total_sales();
	$fs_audio    = fs_product_field( $fs_id, 'audio_url' );
	$fs_video    = fs_video_source( fs_product_field( $fs_id, 'video_url' ) );
	$fs_free     = fs_product_field( $fs_id, 'free_download_url' );
	$fs_gallery  = $product->get_gallery_image_ids();
	$fs_year     = fs_fa_num( fs_jalali_year() );
	$fs_updated  = fs_jalali_month_year();
	?>

	<?php fs_the_breadcrumb(); ?>

	<div class="fs-product" id="product-<?php the_ID(); ?>">

		<div class="fs-product__media">
			<div class="fs-product__cover" style="background:<?php echo esc_attr( fs_grad( 0 ) ); ?>">
				<span class="fs-product__update">
					<?php fs_the_icon( 'clock', 13, array( 'stroke' => '#6ee7b7', 'width' => '2.2' ) ); ?>
					آخرین آپدیت: <?php echo esc_html( $fs_updated ); ?>
				</span>

				<?php
				if ( has_post_thumbnail() ) {
					the_post_thumbnail( 'large', array( 'loading' => 'eager' ) );
				} else {
					fs_the_icon( 'file-lines', 72, array( 'stroke' => 'rgba(15,23,42,.28)', 'width' => '1.3' ) );
				}
				?>
			</div>

			<?php if ( $fs_gallery ) : ?>
				<div class="fs-product__thumbs">
					<?php foreach ( array_slice( $fs_gallery, 0, 4 ) as $fs_gi => $fs_gid ) : ?>
						<button class="fs-product__thumb" type="button"
							data-full="<?php echo esc_url( wp_get_attachment_image_url( $fs_gid, 'large' ) ); ?>"
							style="background:<?php echo esc_attr( fs_grad( $fs_gi ) ); ?>">
							<?php echo wp_get_attachment_image( $fs_gid, 'thumbnail', false, array( 'loading' => 'lazy' ) ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $fs_free ) : ?>
				<a class="fs-product__sample" href="<?php echo esc_url( $fs_free ); ?>" target="_blank" rel="noopener">
					<span class="fs-product__sample-icon">
						<?php fs_the_icon( 'download', 17, array( 'stroke' => '#059669' ) ); ?>
					</span>
					<span>
						<span class="fs-product__sample-title">دانلود نمونه رایگان</span>
						<span class="fs-product__sample-sub">پیش‌نمایش PDF بدون خرید</span>
					</span>
				</a>
			<?php endif; ?>
		</div>

		<div class="fs-product__main">

			<div class="fs-product__tags">
				<span class="fs-ptag fs-ptag--accent">
					<?php fs_the_icon( 'zap', 12, array( 'stroke' => 'currentColor', 'width' => '2.3' ) ); ?>
					ویژه آزمون <?php echo esc_html( $fs_year ); ?>
				</span>

				<?php if ( $fs_is_fresh ) : ?>
					<span class="fs-ptag fs-ptag--new">تازه</span>
				<?php endif; ?>

				<?php if ( $fs_sold > 0 ) : ?>
					<span class="fs-ptag fs-ptag--sold">
						<?php fs_the_icon( 'check', 12, array( 'stroke' => 'currentColor', 'width' => '2.6' ) ); ?>
						<?php echo esc_html( fs_fa_num( $fs_sold ) ); ?> فروش موفق
					</span>
				<?php endif; ?>

				<?php if ( fs_product_flag( $fs_id, 'has_answers' ) ) : ?>
					<span class="fs-ptag fs-ptag--ok">
						<?php fs_the_icon( 'check', 12, array( 'stroke' => 'currentColor', 'width' => '2.6' ) ); ?>
						دارای پاسخنامه
					</span>
				<?php endif; ?>
			</div>

			<h1 class="fs-product__title"><?php the_title(); ?></h1>

			<div class="fs-pmeta">
				<div class="fs-pmeta__cell">
					<span class="fs-pmeta__icon"><?php fs_the_icon( 'hash', 15, array( 'width' => '2' ) ); ?></span>
					<span class="fs-pmeta__body">
						<span class="fs-pmeta__k">کد محصول</span>
						<span class="fs-pmeta__v">#<?php echo esc_html( fs_fa_num( $fs_code ) ); ?></span>
					</span>
				</div>

				<?php if ( $fs_code_pc ) : ?>
					<div class="fs-pmeta__cell">
						<span class="fs-pmeta__icon"><?php fs_the_icon( 'grid', 15, array( 'width' => '2' ) ); ?></span>
						<span class="fs-pmeta__body">
							<span class="fs-pmeta__k">کد رایانه‌کار</span>
							<span class="fs-pmeta__v"><?php echo esc_html( fs_fa_num( $fs_code_pc ) ); ?></span>
						</span>
					</div>
				<?php endif; ?>

				<div class="fs-pmeta__cell">
					<span class="fs-pmeta__icon"><?php fs_the_icon( 'file', 15, array( 'width' => '2' ) ); ?></span>
					<span class="fs-pmeta__body">
						<span class="fs-pmeta__k">فرمت فایل</span>
						<span class="fs-pmeta__v">PDF</span>
					</span>
				</div>

				<?php if ( $fs_qcount ) : ?>
					<div class="fs-pmeta__cell">
						<span class="fs-pmeta__icon"><?php fs_the_icon( 'file-lines', 15, array( 'width' => '2' ) ); ?></span>
						<span class="fs-pmeta__body">
							<span class="fs-pmeta__k">تعداد سوال</span>
							<span class="fs-pmeta__v"><?php echo esc_html( $fs_qcount ); ?></span>
						</span>
					</div>
				<?php endif; ?>

				<?php if ( $fs_pages ) : ?>
					<div class="fs-pmeta__cell">
						<span class="fs-pmeta__icon"><?php fs_the_icon( 'layers', 15, array( 'width' => '2' ) ); ?></span>
						<span class="fs-pmeta__body">
							<span class="fs-pmeta__k">تعداد صفحه</span>
							<span class="fs-pmeta__v"><?php echo esc_html( fs_fa_num( $fs_pages ) ); ?></span>
						</span>
					</div>
				<?php endif; ?>

				<?php if ( $fs_views ) : ?>
					<div class="fs-pmeta__cell">
						<span class="fs-pmeta__icon"><?php fs_the_icon( 'eye', 15, array( 'width' => '2' ) ); ?></span>
						<span class="fs-pmeta__body">
							<span class="fs-pmeta__k">بازدید</span>
							<span class="fs-pmeta__v"><?php echo esc_html( $fs_views ); ?></span>
						</span>
					</div>
				<?php endif; ?>

				<div class="fs-pmeta__cell">
					<span class="fs-pmeta__icon"><?php fs_the_icon( 'calendar', 15, array( 'width' => '2' ) ); ?></span>
					<span class="fs-pmeta__body">
						<span class="fs-pmeta__k">آخرین بروزرسانی</span>
						<span class="fs-pmeta__v"><?php echo esc_html( $fs_updated ); ?></span>
					</span>
				</div>
			</div>

			<?php if ( $fs_audio || $fs_video['src'] ) : ?>
				<div class="fs-product__players">

					<?php if ( $fs_audio ) : ?>
						<div class="fs-audio" data-audio>
							<button class="fs-audio__play" type="button" aria-label="پخش فایل صوتی">
								<span class="fs-audio__icon fs-audio__icon--play" aria-hidden="true"></span>
								<span class="fs-audio__icon fs-audio__icon--pause" aria-hidden="true" hidden></span>
							</button>

							<div class="fs-audio__body">
								<span class="fs-audio__label">فایل صوتی معرفی این نمونه سوال</span>

								<div class="fs-audio__wave" role="presentation">
									<?php
									// ۳۲ میله با ارتفاع نسبی — نوار پیشرفت پخش روی همین میله‌ها رنگ می‌گیرد.
									$fs_heights = array( 30, 52, 74, 96, 66, 40, 82, 58, 90, 44, 70, 96, 54, 34, 78, 92, 48, 66, 86, 38, 62, 96, 52, 74, 44, 30, 60, 88, 46, 70, 36, 56 );

									foreach ( $fs_heights as $fs_h ) {
										printf( '<span style="height:%d%%"></span>', (int) $fs_h );
									}
									?>
								</div>
							</div>

							<span class="fs-audio__time" dir="ltr"><span data-current>۰۰:۰۰</span> / <span data-duration>۰۰:۰۰</span></span>

							<audio preload="metadata" src="<?php echo esc_url( $fs_audio ); ?>"></audio>
						</div>
					<?php endif; ?>

					<?php if ( $fs_video['src'] ) : ?>
						<button class="fs-product__video" type="button"
							data-video="<?php echo esc_url( $fs_video['src'] ); ?>"
							data-video-type="<?php echo esc_attr( $fs_video['type'] ); ?>">
							<span class="fs-product__video-icon">
								<svg width="13" height="13" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M8 5v14l11-7z"></path></svg>
							</span>
							ویدیوی معرفی
						</button>
					<?php endif; ?>

				</div>
			<?php endif; ?>

			<?php if ( $product->get_short_description() ) : ?>
				<div class="fs-product__excerpt fs-rich"><?php echo wp_kses_post( wpautop( $product->get_short_description() ) ); ?></div>
			<?php endif; ?>

			<div class="fs-product__specs">
				<?php foreach ( $fs_specs as $fs_spec ) : ?>
					<div class="fs-spec">
						<span class="fs-spec__icon">
							<?php fs_the_icon( 'check', 16, array( 'stroke' => '#059669', 'width' => '2.6' ) ); ?>
						</span>
						<span>
							<span class="fs-spec__k"><?php echo esc_html( $fs_spec['k'] ); ?></span>
							<span class="fs-spec__v"><?php echo esc_html( $fs_spec['v'] ); ?></span>
						</span>
					</div>
				<?php endforeach; ?>
			</div>

		</div>

		<div class="fs-product__buy">
			<div class="fs-buybox">

				<div class="fs-buybox__head">
					<?php
					$fs_regular = (float) $product->get_regular_price();
					$fs_sale    = (float) $product->get_sale_price();
					$fs_on_sale = $product->is_on_sale() && $fs_regular > 0 && $fs_sale > 0 && $fs_sale < $fs_regular;
					$fs_off     = $fs_on_sale ? (int) round( ( ( $fs_regular - $fs_sale ) / $fs_regular ) * 100 ) : 0;
					?>

					<?php if ( $fs_on_sale ) : ?>
						<div class="fs-price">
							<div class="fs-price__old">
								<span class="fs-price__old-k">قیمت اصلی</span>
								<span class="fs-price__old-v"><?php echo wp_kses_post( fs_fa_num_html( wc_price( $fs_regular ) ) ); ?></span>
							</div>

							<div class="fs-price__now">
								<span class="fs-price__now-k">قیمت با تخفیف</span>
								<span class="fs-price__now-v"><?php echo wp_kses_post( fs_fa_num_html( wc_price( $fs_sale ) ) ); ?></span>
							</div>

							<div class="fs-price__off">
								<span class="fs-price__off-badge"><?php echo esc_html( fs_fa_num( $fs_off ) ); ?>٪</span>
								<span class="fs-price__off-text">
									<?php echo wp_kses_post( fs_fa_num_html( wc_price( $fs_regular - $fs_sale ) ) ); ?>
									سود شما از این خرید
								</span>
							</div>
						</div>
					<?php else : ?>
						<div class="fs-price fs-price--plain">
							<span class="fs-price__now-k">قیمت</span>
							<span class="fs-price__now-v"><?php echo wp_kses_post( fs_product_price( $product ) ); ?></span>
						</div>
					<?php endif; ?>
				</div>

				<div class="fs-buybox__body">
					<?php woocommerce_template_single_add_to_cart(); ?>

					<?php if ( $fs_guarantees ) : ?>
						<div class="fs-buybox__guarantees">
							<?php foreach ( $fs_guarantees as $fs_g ) : ?>
								<div class="fs-guarantee">
									<span class="fs-guarantee__icon">
										<?php fs_the_icon( 'check', 12, array( 'stroke' => '#059669', 'width' => '2.8' ) ); ?>
									</span>
									<span><?php echo esc_html( $fs_g ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $fs_trust['badges'] ) : ?>
					<div class="fs-buybox__trust">
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
					</div>
				<?php endif; ?>

			</div>
		</div>

	</div>

	<?php if ( $product->get_description() || $fs_toc || $fs_rev_summary || comments_open() ) : ?>
		<?php
		$fs_has_desc = (bool) ( $product->get_description() || $fs_toc );
		$fs_has_rev  = (bool) $fs_rev_summary || comments_open();
		?>
		<div class="fs-section fs-section--tabs">
			<div class="fs-ptabs">

				<div class="fs-ptabs__nav" role="tablist">
					<?php if ( $fs_has_desc ) : ?>
						<button class="fs-ptab is-active" type="button" role="tab" aria-controls="fs-ptab-desc" aria-selected="true">توضیحات</button>
					<?php endif; ?>
					<?php if ( $fs_has_rev ) : ?>
						<button class="fs-ptab<?php echo $fs_has_desc ? '' : ' is-active'; ?>" type="button" role="tab"
							aria-controls="fs-ptab-rev" aria-selected="<?php echo $fs_has_desc ? 'false' : 'true'; ?>">
							نظرات خریداران<?php echo $fs_rev_summary ? ' (' . esc_html( fs_fa_num( $fs_rev_summary['count'] ) ) . ')' : ''; ?>
						</button>
					<?php endif; ?>
				</div>

				<div class="fs-ptabs__body">

					<?php if ( $fs_has_desc ) : ?>
						<div class="fs-ptabs__pane" id="fs-ptab-desc" role="tabpanel">
							<div class="fs-ptabs__grid">
								<div>
									<?php if ( $product->get_description() ) : ?>
										<h2 class="fs-block__title">توضیحات کامل محصول</h2>
										<div class="fs-rich"><?php echo wp_kses_post( wpautop( $product->get_description() ) ); ?></div>
									<?php endif; ?>

									<?php if ( $fs_toc ) : ?>
										<h3 class="fs-toc__title">سرفصل سوالات</h3>
										<div class="fs-toc">
											<?php foreach ( $fs_toc as $fs_item ) : ?>
												<div class="fs-toc__item">
													<span class="fs-toc__icon">
														<?php fs_the_icon( 'check', 11, array( 'stroke' => '#059669', 'width' => '2.8' ) ); ?>
													</span>
													<span><?php echo esc_html( $fs_item ); ?></span>
												</div>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>

								<div class="fs-pside">
									<aside class="fs-glance">
										<div class="fs-glance__title">در یک نگاه</div>
										<?php foreach ( $fs_specs as $fs_spec ) : ?>
											<div class="fs-glance__row">
												<span><?php echo esc_html( $fs_spec['k'] ); ?></span>
												<b><?php echo esc_html( $fs_spec['v'] ); ?></b>
											</div>
										<?php endforeach; ?>
									</aside>

									<?php
									$fs_assurances = fs_get_assurances();

									if ( $fs_assurances || $fs_trust['banks'] || $fs_trust['caption'] ) :
										?>
										<aside class="fs-assure">
											<?php if ( $fs_assurances ) : ?>
												<div class="fs-assure__title"><?php echo esc_html( fs_get_assurance_title() ); ?></div>

												<ul class="fs-assure__list">
													<?php
													$fs_assure_icons = array( 'infinity', 'shield', 'star', 'zap', 'check' );

													foreach ( $fs_assurances as $fs_ai => $fs_assurance ) :
														?>
														<li class="fs-assure__item">
															<span class="fs-assure__icon fs-assure__icon--<?php echo (int) ( $fs_ai % 5 ); ?>">
																<?php fs_the_icon( $fs_assure_icons[ $fs_ai % 5 ], 14, array( 'width' => '2.1' ) ); ?>
															</span>
															<span><?php echo esc_html( $fs_assurance ); ?></span>
														</li>
													<?php endforeach; ?>
												</ul>
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
										</aside>
									<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( $fs_has_rev ) : ?>
						<div class="fs-ptabs__pane" id="fs-ptab-rev" role="tabpanel" <?php echo $fs_has_desc ? 'hidden' : ''; ?>>
							<?php if ( $fs_rev_summary ) : ?>
								<div class="fs-revs">

									<aside class="fs-revs__summary">
										<div class="fs-revs__avg"><?php echo esc_html( fs_fa_num( number_format_i18n( $fs_rev_summary['average'], 1 ) ) ); ?></div>
										<div class="fs-revs__count">از <?php echo esc_html( fs_fa_num( $fs_rev_summary['count'] ) ); ?> نظر خریداران</div>

										<div class="fs-revs__bars">
											<?php foreach ( $fs_rev_summary['bars'] as $fs_bar ) : ?>
												<div class="fs-revs__bar">
													<span class="fs-revs__bar-k"><?php echo esc_html( $fs_bar['k'] ); ?></span>
													<span class="fs-revs__bar-track"><span style="width:<?php echo (int) $fs_bar['percent']; ?>%"></span></span>
												</div>
											<?php endforeach; ?>
										</div>

										<a class="fs-revs__cta" href="#fs-review-form">ثبت نظر</a>
									</aside>

									<div class="fs-revs__list">
										<?php foreach ( $fs_reviews as $fs_r ) : ?>
											<div class="fs-revcard">
												<div class="fs-revcard__top">
													<div class="fs-revcard__who">
														<span class="fs-revcard__avatar" style="background:<?php echo esc_attr( $fs_r['tint'] ); ?>">
															<?php echo esc_html( $fs_r['initial'] ); ?>
														</span>
														<span>
															<span class="fs-revcard__name"><?php echo esc_html( $fs_r['name'] ); ?></span>
															<span class="fs-revcard__date">خرید تاییدشده · <?php echo esc_html( $fs_r['date'] ); ?></span>
														</span>
													</div>
													<span class="fs-revcard__stars"><?php echo esc_html( $fs_r['stars'] ); ?></span>
												</div>
												<div class="fs-revcard__text"><?php echo esc_html( $fs_r['text'] ); ?></div>
											</div>
										<?php endforeach; ?>
									</div>

								</div>
							<?php endif; ?>

							<?php if ( comments_open() ) : ?>
								<div class="fs-revform" id="fs-review-form">
									<?php
									comment_form(
										array(
											'title_reply'         => $fs_rev_summary ? 'دیدگاه خود را بنویسید' : 'اولین نفری باشید که نظر می‌دهد',
											'comment_notes_after' => '',
											'label_submit'        => 'ثبت دیدگاه',
											'class_submit'        => 'fs-revform__submit',
										)
									);
									?>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

				</div>

			</div>
		</div>
	<?php endif; ?>

	<?php
	$fs_related_ids = wc_get_related_products( $fs_id, 6 );

	if ( $fs_related_ids ) :
		?>
		<section class="fs-section">
			<div class="fs-section__head">
				<div>
					<h2 class="fs-section__title">نمونه سوالات مرتبط</h2>
					<div class="fs-section__sub">هم‌رشته و هم‌سطح با این فایل</div>
				</div>
				<div class="fs-arrows">
					<button class="fs-arrow" type="button" data-rail="fs-related" data-dir="prev">
						<span class="fs-sr-only">قبلی</span><?php fs_the_icon( 'chevron-prev', 16 ); ?>
					</button>
					<button class="fs-arrow" type="button" data-rail="fs-related" data-dir="next">
						<span class="fs-sr-only">بعدی</span><?php fs_the_icon( 'chevron-next', 16 ); ?>
					</button>
				</div>
			</div>

			<div class="fs-rail" id="fs-related">
				<?php
				foreach ( $fs_related_ids as $fs_i => $fs_rid ) :
					$fs_rel = wc_get_product( $fs_rid );

					if ( ! $fs_rel ) {
						continue;
					}

					get_template_part( 'template-parts/card/product', null, array( 'card' => fs_product_to_card( $fs_rel, $fs_i ) ) );
				endforeach;
				?>
			</div>
		</section>
	<?php endif; ?>

	<div class="fs-mobar" data-mobar>
		<span class="fs-mobar__price"><?php echo wp_kses_post( fs_product_price( $product ) ); ?></span>
		<?php
		echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی استاندارد ووکامرس.
			'woocommerce_loop_add_to_cart_link',
			sprintf(
				'<a href="%s" data-quantity="1" class="%s" %s>%s</a>',
				esc_url( $product->add_to_cart_url() ),
				esc_attr( implode( ' ', array_filter( array( 'fs-mobar__btn', 'add_to_cart_button', $product->supports( 'ajax_add_to_cart' ) && $product->is_purchasable() ? 'ajax_add_to_cart' : '' ) ) ) ),
				wc_implode_html_attributes(
					array(
						'data-product_id'  => $fs_id,
						'data-product_sku' => $product->get_sku(),
						'aria-label'       => $product->add_to_cart_description(),
						'rel'              => 'nofollow',
					)
				),
				esc_html( $product->add_to_cart_text() )
			),
			$product,
			array()
		);
		?>
	</div>

	<?php
endwhile;
?>

<div class="fs-modal" id="fs-video-modal" hidden>
	<div class="fs-modal__backdrop" data-close></div>
	<div class="fs-modal__box" role="dialog" aria-modal="true" aria-label="ویدیوی معرفی">
		<button class="fs-modal__close" type="button" data-close aria-label="بستن">&times;</button>
		<div class="fs-modal__player"></div>
	</div>
</div>

<?php
get_footer();
