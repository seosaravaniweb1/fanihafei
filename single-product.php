<?php
/**
 * صفحه تک‌محصول — برگردان طرح «Product Page UI».
 *
 * @package SiFile
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
	$fs_trust      = fs_get_trust_settings();
	$fs_rev_summary = fs_get_review_summary( $product );
	$fs_reviews     = $fs_rev_summary ? fs_get_product_reviews( $fs_id ) : array();


	$fs_sku      = $product->get_sku();
	$fs_code     = $fs_sku ? $fs_sku : $fs_id;
	$fs_audio    = fs_product_field( $fs_id, 'audio_url' );
	$fs_video    = fs_video_source( fs_product_field( $fs_id, 'video_url' ) );
	$fs_free     = fs_product_field( $fs_id, 'free_download_url' );
	$fs_gallery  = $product->get_gallery_image_ids();
	$fs_dates    = fs_product_dates( $fs_id );
	$fs_views    = fs_product_views( $fs_id );
	$fs_sales    = fs_product_sales( $fs_id );
	$fs_satisf   = fs_product_satisfaction();
	$fs_authors  = fs_get_product_authors( $fs_id );
	$fs_why      = fs_get_why_box();
	$fs_updated  = $fs_dates['updated'] ? $fs_dates['updated'] : $fs_dates['published'];
	?>

	<?php
	/*
	 * قلاب‌های استاندارد ووکامرس. کال‌بک‌های پیش‌فرضی که خروجی‌شان با چیدمان این
	 * قالب تکراری می‌شد در fs_woo_unhook() برداشته شده‌اند، ولی خود قلاب‌ها
	 * اجرا می‌شوند تا افزونه‌ها (پیام‌های ووکامرس، نشان‌ها، لیست علاقه‌مندی و…)
	 * جای همیشگی‌شان را داشته باشند.
	 */
	do_action( 'woocommerce_before_main_content' );
	?>

	<nav class="fs-crumbs" aria-label="مسیر">
		<?php woocommerce_breadcrumb( array( 'delimiter' => '' ) ); ?>
	</nav>

	<?php do_action( 'woocommerce_before_single_product' ); ?>

	<div class="fs-product" id="product-<?php the_ID(); ?>">

		<div class="fs-product__media">
			<div class="fs-product__cover" style="background:<?php echo esc_attr( fs_grad( 0 ) ); ?>">
				<?php if ( $fs_updated ) : ?>
					<span class="fs-product__update">
						<?php fs_the_icon( 'clock', 13, array( 'stroke' => '#c4b5fd', 'width' => '2.2' ) ); ?>
						آخرین آپدیت: <?php echo esc_html( $fs_updated ); ?>
					</span>
				<?php endif; ?>

				<?php
				if ( has_post_thumbnail() ) {
					// تصویر شاخص محصول تقریباً همیشه همان عنصر LCP صفحه است.
					// fetchpriority=high به مرورگر می‌گوید پیش از بقیه‌ی تصاویر
					// سراغش برود؛ بدون آن در صف با تصاویر تنبل رقابت می‌کند.
					the_post_thumbnail(
						'large',
						array(
							'loading'       => 'eager',
							'fetchpriority' => 'high',
							'decoding'      => 'sync',
						)
					);
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
						<?php fs_the_icon( 'download', 17, array( 'stroke' => '#6d28d9' ) ); ?>
					</span>
					<span>
						<span class="fs-product__sample-title">دانلود پیش‌نمایش رایگان</span>
						<span class="fs-product__sample-sub">بخشی از فایل، بدون خرید</span>
					</span>
				</a>
			<?php endif; ?>
		</div>

		<div class="fs-product__main">

			<?php if ( $fs_sales || $fs_satisf ) : ?>
				<div class="fs-product__flags">
					<?php if ( $fs_sales ) : ?>
						<span class="fs-flag fs-flag--sales">
							<?php fs_the_icon( 'trending', 13, array( 'stroke' => 'currentColor', 'width' => '2.2' ) ); ?>
							<?php echo esc_html( fs_fa_num( number_format_i18n( $fs_sales ) ) ); ?> فروش موفق
						</span>
					<?php endif; ?>

					<?php if ( $fs_satisf ) : ?>
						<span class="fs-flag fs-flag--satisf">
							<?php fs_the_icon( 'check', 13, array( 'stroke' => 'currentColor', 'width' => '2.6' ) ); ?>
							<?php echo esc_html( fs_fa_num( $fs_satisf ) ); ?> رضایت مشتریان
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<h1 class="fs-product__title"><?php the_title(); ?></h1>

			<div class="fs-product__codes">
				<span class="fs-product__code">کد محصول: <b><?php echo esc_html( fs_fa_num( $fs_code ) ); ?></b></span>

				<?php if ( $fs_dates['published'] ) : ?>
					<span class="fs-product__code">
						<?php fs_the_icon( 'clock', 13, array( 'stroke' => '#94a3b8', 'width' => '2' ) ); ?>
						درج: <b><?php echo esc_html( $fs_dates['published'] ); ?></b>
						<?php if ( $fs_dates['updated'] ) : ?>
							· بروزرسانی: <b><?php echo esc_html( $fs_dates['updated'] ); ?></b>
						<?php endif; ?>
					</span>
				<?php endif; ?>

				<?php if ( $fs_views ) : ?>
					<span class="fs-product__code">بازدید: <b><?php echo esc_html( $fs_views ); ?></b></span>
				<?php endif; ?>
			</div>

			<?php if ( $fs_audio || $fs_video['src'] ) : ?>
				<div class="fs-product__players">

					<?php if ( $fs_audio ) : ?>
						<div class="fs-audio" data-audio>
							<button class="fs-audio__play" type="button" aria-label="پخش فایل صوتی">
								<span class="fs-audio__icon fs-audio__icon--play"></span>
								<span class="fs-audio__icon fs-audio__icon--pause" hidden></span>
							</button>

							<div class="fs-audio__wave" role="presentation">
								<?php
								// ۲۶ میله با ارتفاع ثابت و تکرارشونده — همان موج طرح.
								$fs_heights = array( 8, 14, 20, 26, 18, 11, 22, 16, 24, 12, 19, 26, 15, 9, 21, 25, 13, 18, 23, 10, 17, 26, 14, 20, 12, 8 );

								foreach ( $fs_heights as $fs_h ) {
									printf( '<span style="height:%dpx"></span>', (int) $fs_h );
								}
								?>
							</div>

							<span class="fs-audio__time"><span data-current>۰۰:۰۰</span> / <span data-duration>۰۰:۰۰</span></span>

							<span class="fs-audio__error" data-audio-error hidden>فایل صوتی در دسترس نیست.</span>

							<?php
							/*
							 * نشانی http روی صفحه‌ی https «محتوای مختلط» است و
							 * مرورگر آن را بی‌صدا و بی‌خطا مسدود می‌کند: ظاهر
							 * پخش‌کننده می‌آید ولی صدا هرگز پخش نمی‌شود.
							 * برای فایل‌های همین دامنه بی‌خطر می‌شود ارتقا داد.
							 */
							?>
							<audio preload="metadata" src="<?php echo esc_url( fs_safe_media_url( $fs_audio ) ); ?>"></audio>
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
				<?php if ( $fs_authors ) : ?>
					<div class="fs-spec">
						<span class="fs-spec__k">نویسنده</span>
						<span class="fs-spec__v">
							<?php
							$fs_author_html = array();

							foreach ( $fs_authors as $fs_author ) {
								$fs_author_html[] = $fs_author['link']
									? '<a href="' . esc_url( $fs_author['link'] ) . '">' . esc_html( $fs_author['name'] ) . '</a>'
									: esc_html( $fs_author['name'] );
							}

							echo wp_kses_post( implode( '، ', $fs_author_html ) );
							?>
						</span>
					</div>
				<?php endif; ?>

				<?php foreach ( $fs_specs as $fs_spec ) : ?>
					<div class="fs-spec">
						<span class="fs-spec__k"><?php echo esc_html( $fs_spec['k'] ); ?></span>
						<span class="fs-spec__v"><?php echo esc_html( $fs_spec['v'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<?php do_action( 'woocommerce_single_product_summary' ); ?>

		</div>

		<div class="fs-product__buy">
			<div class="fs-buybox">

				<div class="fs-buybox__head">
					<?php
					$fs_regular = (float) $product->get_regular_price();
					$fs_sale    = (float) $product->get_sale_price();
					$fs_on_sale = $product->is_on_sale() && $fs_regular > 0 && $fs_sale > 0;

					if ( $fs_on_sale ) :
						$fs_off = (int) round( ( ( $fs_regular - $fs_sale ) / $fs_regular ) * 100 );
						?>
						<div class="fs-buybox__top">
							<span class="fs-buybox__was"><?php echo wp_kses_post( fs_fa_num_html( wc_price( $fs_regular ) ) ); ?></span>
							<span class="fs-buybox__off"><?php echo esc_html( fs_fa_num( $fs_off ) ); ?>٪ تخفیف</span>
						</div>
					<?php endif; ?>

					<div class="fs-buybox__price"><?php echo wp_kses_post( fs_product_price( $product ) ); ?></div>

					<?php if ( $fs_on_sale ) : ?>
						<span class="fs-buybox__saved">
							<?php fs_the_icon( 'zap', 13, array( 'stroke' => '#5b21b6', 'width' => '2.2' ) ); ?>
							<?php echo wp_kses_post( fs_fa_num_html( wc_price( $fs_regular - $fs_sale ) ) ); ?> تخفیف شما
						</span>
					<?php endif; ?>
				</div>

				<div class="fs-buybox__body">
					<?php woocommerce_template_single_add_to_cart(); ?>
				</div>

			</div>
		</div>

	</div>

	<?php do_action( 'woocommerce_after_single_product_summary' ); ?>

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
									<?php
									// سرفصل‌ها خودکار از تیترهای خود متن ساخته می‌شوند و بالای محتوا
									// می‌نشینند؛ هر سرفصل یک لینک لنگری به همان تیتر است. سرفصل‌های
									// دستی فقط وقتی استفاده می‌شوند که متن هیچ تیتری نداشته باشد.
									$fs_body      = fs_toc_from_content( wpautop( $product->get_description() ) );
									$fs_toc_items = $fs_body['items'] ? $fs_body['items'] : fs_toc_lines_to_items( $fs_toc );
									?>

									<?php if ( $product->get_description() ) : ?>
										<h2 class="fs-block__title">توضیحات کامل محصول</h2>
									<?php endif; ?>

									<?php get_template_part( 'template-parts/product/toc', null, array( 'items' => $fs_toc_items ) ); ?>

									<?php if ( $product->get_description() ) : ?>
										<div class="fs-rich"><?php echo wp_kses_post( $fs_body['html'] ); ?></div>
									<?php endif; ?>
								</div>

								<aside class="fs-pside">

									<div class="fs-glance">
										<?php
										// سایدبار نباید با H2 های محتوای اصلی
										// رقابت کند. h3 (نه h4) چون بالادستش
										// H2 «توضیحات کامل محصول» است و پرش
										// سطح خودش اخطار ساختاری می‌سازد.
										?>
										<h3 class="fs-glance__title">در یک نگاه</h3>
										<?php foreach ( $fs_specs as $fs_spec ) : ?>
											<div class="fs-glance__row">
												<span><?php echo esc_html( $fs_spec['k'] ); ?></span>
												<b><?php echo esc_html( $fs_spec['v'] ); ?></b>
											</div>
										<?php endforeach; ?>
									</div>

									<?php if ( $fs_why ) : ?>
										<div class="fs-why">
											<?php
											// متن این جعبه روی همه‌ی محصولات
											// یکسان است؛ هدینگِ تکراری روی
											// هزاران صفحه سیگنال سئویی ندارد،
											// پس عنوانِ صرفاً بصری می‌ماند.
											?>
											<div class="fs-why__title"><?php echo esc_html( $fs_why['title'] ); ?></div>
											<?php foreach ( $fs_why['items'] as $fs_item ) : ?>
												<div class="fs-why__row">
													<span class="fs-why__icon">
														<?php fs_the_icon( $fs_item['icon'], 14, array( 'stroke' => 'currentColor', 'width' => '2' ) ); ?>
													</span>
													<span><?php echo esc_html( $fs_item['text'] ); ?></span>
												</div>
											<?php endforeach; ?>
											<?php if ( $fs_why['foot'] ) : ?>
												<div class="fs-why__foot"><?php echo esc_html( $fs_why['foot'] ); ?></div>
											<?php endif; ?>
										</div>
									<?php endif; ?>

									<?php get_template_part( 'template-parts/product/trust', null, array( 'trust' => $fs_trust ) ); ?>

								</aside>
							</div>
						</div>
					<?php endif; ?>

					<?php if ( $fs_has_rev ) : ?>
						<div class="fs-ptabs__pane" id="fs-ptab-rev" role="tabpanel" <?php echo $fs_has_desc ? 'hidden' : ''; ?>>

							<?php
							/*
							 * این بخش تا امروز هیچ هدینگی نداشت: تنها چیزی که
							 * اسمش را می‌گفت، دکمه‌ی تب بود و دکمه برای خزنده
							 * عنوان بخش نیست.
							 *
							 * چون دکمه‌ی تب همین متن را به کاربر نشان می‌دهد،
							 * هدینگ فقط برای خزنده و صفحه‌خوان است تا روی صفحه
							 * عنوان تکراری دیده نشود.
							 */
							?>
							<h2 class="fs-sr-only">نظرات و پرسش‌های خریداران درباره <?php the_title(); ?></h2>

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
					<h2 class="fs-section__title"><?php echo esc_html( fs_copy( 'related_title' ) ); ?></h2>
					<div class="fs-section__sub"><?php echo esc_html( fs_copy( 'related_sub' ) ); ?></div>
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
	do_action( 'woocommerce_after_single_product' );

endwhile;

do_action( 'woocommerce_after_main_content' );
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
