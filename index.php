<?php
/**
 * قالب پیش‌فرض (fallback).
 *
 * فایل طرح فقط صفحه اصلی را تعریف کرده است؛ این قالب حداقلی است که وردپرس
 * برای هر نمای دیگری لازم دارد و از همان اجزای صفحه اصلی استفاده می‌کند.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

get_header();

/*
 * مسیر راهنما برای برگه‌ها و بایگانی‌های غیرمحصولی.
 *
 * تا امروز فقط صفحه‌ی محصول و آرشیو محصول بردکرامب داشتند؛ برگه‌ها، نتایج
 * جست‌وجو و بایگانی نوشته‌ها/برچسب‌ها بدون مسیر بودند — هم برای کاربر و هم
 * برای اسکیمای BreadcrumbList که ووکامرس روی همین اکشن می‌سازد.
 */
if ( fs_has_woo() && ! is_front_page() ) {
	echo '<nav class="fs-crumbs" aria-label="مسیر">';
	woocommerce_breadcrumb( array( 'delimiter' => '' ) );
	echo '</nav>';
}
?>
<section class="fs-section fs-section--cats">

	<div class="fs-section__head">
		<div>
			<h1 class="fs-section__title">
				<?php
				if ( is_home() ) {
					echo esc_html( fs_copy( 'articles_title' ) );
				} elseif ( is_search() ) {
					printf( 'نتایج جست‌وجو برای «%s»', esc_html( get_search_query() ) );
				} elseif ( is_archive() ) {
					the_archive_title();
				} else {
					the_title();
				}
				?>
			</h1>
		</div>
	</div>

	<?php if ( have_posts() ) : ?>

		<?php if ( is_singular() ) : ?>

			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article class="fs-subcats">
					<?php the_content(); ?>
				</article>
				<?php
			endwhile;
			?>

		<?php else : ?>

			<div class="fs-articles">
				<?php
				$fs_i = 0;

				while ( have_posts() ) :
					the_post();
					?>
					<a class="fs-article" href="<?php the_permalink(); ?>">
						<span class="fs-article__thumb" style="background:<?php echo esc_attr( fs_grad( $fs_i ) ); ?>">
							<?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
						</span>
						<span class="fs-article__body">
							<span class="fs-article__meta"><?php echo esc_html( fs_fa_num( get_the_date() ) ); ?></span>
							<span class="fs-article__title"><?php the_title(); ?></span>
							<span class="fs-article__more">خواندن مقاله ›</span>
						</span>
					</a>
					<?php
					++$fs_i;
				endwhile;
				?>
			</div>

			<div class="fs-tabs" style="border-bottom:0;padding-top:20px">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'prev_text' => '‹ قبلی',
							'next_text' => 'بعدی ›',
						)
					)
				);
				?>
			</div>

		<?php endif; ?>

	<?php else : ?>

		<p class="fs-about__text">چیزی پیدا نشد.</p>

	<?php endif; ?>

</section>
<?php
get_footer();
