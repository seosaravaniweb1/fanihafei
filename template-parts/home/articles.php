<?php
/**
 * بخش مقالات.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

$fs_articles = fs_get_articles( 3 );

if ( ! $fs_articles ) {
	return;
}

$fs_blog_url = get_permalink( get_option( 'page_for_posts' ) );

if ( ! $fs_blog_url ) {
	$fs_blog_url = home_url( '/' );
}
?>
<section class="fs-section fs-section--articles">

	<div class="fs-section__head">
		<div>
			<h2 class="fs-section__title">
				<span class="fs-only-desktop"><?php echo esc_html( fs_copy( 'articles_title' ) ); ?></span>
				<span class="fs-only-mobile"><?php echo esc_html( fs_copy( 'articles_title_m' ) ); ?></span>
			</h2>
			<div class="fs-section__sub"><?php echo esc_html( fs_copy( 'articles_sub' ) ); ?></div>
		</div>
		<a class="fs-section__link" href="<?php echo esc_url( $fs_blog_url ); ?>">همه مقالات ›</a>
	</div>

	<div class="fs-articles">
		<?php foreach ( $fs_articles as $fs_article ) : ?>
			<a class="fs-article" href="<?php echo esc_url( fs_url( $fs_article['link'] ) ); ?>">

				<span class="fs-article__thumb" style="background:<?php echo esc_attr( $fs_article['grad'] ); ?>">
					<?php echo wp_kses_post( $fs_article['thumb'] ); ?>
				</span>

				<span class="fs-article__body">
					<span class="fs-article__meta"><?php echo esc_html( $fs_article['meta'] ); ?></span>
					<span class="fs-article__title"><?php echo esc_html( $fs_article['t'] ); ?></span>
					<span class="fs-article__more">خواندن مقاله ›</span>
				</span>

			</a>
		<?php endforeach; ?>
	</div>

</section>
