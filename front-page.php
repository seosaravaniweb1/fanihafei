<?php
/**
 * صفحه اصلی — برگردان دقیق «Homepage UI».
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

get_header();

get_template_part( 'template-parts/home/hero' );
get_template_part( 'template-parts/home/categories' );
get_template_part( 'template-parts/home/subcategories' );
get_template_part( 'template-parts/home/newest' );
get_template_part( 'template-parts/home/popular' );
get_template_part( 'template-parts/home/recently-viewed' );
get_template_part( 'template-parts/home/marquee' );
get_template_part( 'template-parts/home/articles' );
get_template_part( 'template-parts/home/about-faq' );

get_footer();
