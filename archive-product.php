<?php
/**
 * آرشیو محصولات و صفحه دسته — برگردان دقیق طرح «Category Listing UI».
 *
 * تمام‌عرض، بدون سایدبار، بدون صفحه‌بندی — با اسکرول محصولات بعدی بارگذاری می‌شوند.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

get_header();

global $wp_query;

$fs_term  = is_tax( array( 'product_cat', 'product_tag' ) ) ? get_queried_object() : null;
$fs_total = (int) $wp_query->found_posts;

// زیردسته‌ها فقط روی صفحه‌ی دسته‌ی مادر نمایش داده می‌شوند، نه در خود زیردسته.
$fs_subs = array();

if ( $fs_term && 'product_cat' === $fs_term->taxonomy && 0 === (int) $fs_term->parent ) {
	$fs_children = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => $fs_term->term_id,
			'hide_empty' => true,
		)
	);

	if ( ! is_wp_error( $fs_children ) ) {
		$fs_subs = $fs_children;
	}
}

$fs_paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$fs_next  = ( (int) $wp_query->max_num_pages > $fs_paged ) ? get_next_posts_page_link( (int) $wp_query->max_num_pages ) : '';

$fs_sorts = array(
	'date'       => 'جدیدترین',
	'price'      => 'ارزان‌ترین',
	'price-desc' => 'گران‌ترین',
);
$fs_current_sort = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'date'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! isset( $fs_sorts[ $fs_current_sort ] ) ) {
	$fs_current_sort = 'date';
}
?>

<?php
/*
 * قلاب‌های استاندارد آرشیو. کال‌بک‌های تکراری (شمارنده و مرتب‌سازی پیش‌فرض) در
 * fs_woo_unhook() برداشته شده‌اند؛ خود قلاب‌ها می‌مانند تا پیام‌های ووکامرس و
 * افزونه‌های فیلتر/نشان‌گذاری کار کنند.
 */
do_action( 'woocommerce_before_main_content' );
?>

<nav class="fs-crumbs" aria-label="مسیر">
	<?php woocommerce_breadcrumb( array( 'delimiter' => '' ) ); ?>
</nav>

<section class="fs-cathero">
	<div class="fs-cathero__head">
		<div>
			<h1 class="fs-cathero__title">
				<?php
				if ( $fs_term ) {
					echo esc_html( $fs_term->name );
				} else {
					woocommerce_page_title();
				}
				?>
			</h1>
			<p class="fs-cathero__desc"><?php echo esc_html( fs_copy( 'cathero_tagline' ) ); ?></p>
		</div>

		<div class="fs-cathero__stats">
			<div class="fs-cathero__stat">
				<div class="fs-cathero__stat-v"><?php echo esc_html( fs_fa_num( number_format_i18n( $fs_total ) ) ); ?></div>
				<div class="fs-cathero__stat-k">فایل</div>
			</div>
			<?php if ( $fs_subs ) : ?>
				<div class="fs-cathero__stat">
					<div class="fs-cathero__stat-v"><?php echo esc_html( fs_fa_num( count( $fs_subs ) ) ); ?></div>
					<div class="fs-cathero__stat-k">زیردسته</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php if ( $fs_subs ) : ?>
	<section class="fs-section fs-section--subs">
		<div class="fs-section__head">
			<h2 class="fs-section__title" style="font-size:20px">زیردسته‌ها</h2>
			<div class="fs-arrows">
				<button class="fs-arrow" type="button" data-rail="fs-subrail" data-dir="prev">
					<span class="fs-sr-only">قبلی</span><?php fs_the_icon( 'chevron-prev', 15 ); ?>
				</button>
				<button class="fs-arrow" type="button" data-rail="fs-subrail" data-dir="next">
					<span class="fs-sr-only">بعدی</span><?php fs_the_icon( 'chevron-next', 15 ); ?>
				</button>
			</div>
		</div>

		<div class="fs-subrail" id="fs-subrail">
			<?php foreach ( $fs_subs as $fs_i => $fs_sub ) : ?>
				<a class="fs-subtile" href="<?php echo esc_url( fs_url( get_term_link( $fs_sub ) ) ); ?>">
					<span class="fs-subtile__icon" style="<?php echo esc_attr( fs_tint_style( $fs_i ) ); ?>">
						<?php fs_the_icon( 'grid', 26, array( 'width' => '1.7' ) ); ?>
					</span>
					<span class="fs-subtile__name"><?php echo esc_html( $fs_sub->name ); ?></span>
					<span class="fs-subtile__n"><?php echo esc_html( fs_fa_num( fs_cat_product_count( $fs_sub ) ) ); ?> <?php echo esc_html( fs_copy( 'cats_unit' ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php if ( $fs_term && $fs_term->description ) : ?>
	<section class="fs-section fs-section--about-cat">
		<div class="fs-catabout">
			<h2 class="fs-catabout__title">
				<?php
				echo 0 === (int) $fs_term->parent
					? 'درباره فایل‌های ' . esc_html( $fs_term->name )
					: 'درباره این دسته';
				?>
			</h2>

			<div class="fs-collapse" data-collapse>
				<div class="fs-collapse__inner fs-rich"><?php echo wp_kses_post( wpautop( $fs_term->description ) ); ?></div>
				<button class="fs-collapse__btn fs-collapse__btn--pill" type="button" aria-expanded="false" hidden
					data-more="<?php echo esc_attr( fs_copy( 'read_more' ) ); ?>"
					data-less="<?php echo esc_attr( fs_copy( 'read_less' ) ); ?>">
					<?php echo esc_html( fs_copy( 'read_more' ) ); ?>
				</button>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( have_posts() ) : ?>

	<section class="fs-section fs-section--toolbar">
		<div class="fs-toolbar">
			<div class="fs-toolbar__sorts">
				<span class="fs-toolbar__label">
					<?php fs_the_icon( 'menu', 15, array( 'stroke' => '#94a3b8' ) ); ?>
					مرتب‌سازی
				</span>
				<?php foreach ( $fs_sorts as $fs_key => $fs_label ) : ?>
					<a class="fs-sortbtn<?php echo $fs_key === $fs_current_sort ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'orderby', $fs_key ) ); ?>">
						<?php echo esc_html( $fs_label ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="fs-toolbar__count">
				نمایش <span><?php echo esc_html( fs_fa_num( number_format_i18n( $fs_total ) ) ); ?></span> فایل
			</div>
		</div>
	</section>

	<section class="fs-section fs-section--grid">

		<?php
		do_action( 'woocommerce_archive_description' );
		do_action( 'woocommerce_before_shop_loop' );
		?>

		<div class="fs-cgrid" id="fs-archive-grid">
			<?php
			$fs_i = 0;

			while ( have_posts() ) :
				the_post();

				$fs_p = wc_get_product( get_the_ID() );

				if ( ! $fs_p ) {
					continue;
				}

				get_template_part(
					'template-parts/card/grid',
					null,
					array(
						'product' => $fs_p,
						'index'   => $fs_i,
					)
				);

				++$fs_i;
			endwhile;
			?>
		</div>

		<?php do_action( 'woocommerce_after_shop_loop' ); ?>

		<?php if ( $fs_next ) : ?>
			<div class="fs-infinite" data-infinite data-next="<?php echo esc_url( $fs_next ); ?>" data-target="fs-archive-grid">
				<span class="fs-infinite__spinner" aria-hidden="true"></span>
				<button class="fs-infinite__btn" type="button">نمایش محصولات بیشتر</button>
			</div>
		<?php endif; ?>

	</section>

<?php else : ?>

	<section class="fs-section">
		<p class="fs-empty">در این دسته هنوز فایلی ثبت نشده است.</p>
		<?php do_action( 'woocommerce_no_products_found' ); ?>
	</section>

<?php endif; ?>

<?php
do_action( 'woocommerce_after_main_content' );

get_footer();
