<?php
/**
 * صفحه‌ی «پیدا نشد».
 *
 * بدون این فایل، وردپرس به index.php برمی‌گشت و کاربر یک آرشیو خالی با پیام
 * «چیزی پیدا نشد» می‌دید — بن‌بستی که نه راه بازگشتی دارد و نه لینکی برای
 * ادامه دادن. اینجا جست‌وجو، دسته‌ها و تازه‌ترین فایل‌ها را می‌گذاریم تا هم
 * کاربر مسیر دیگری پیدا کند و هم خزنده از این صفحه به جای بن‌بست، لینک بگیرد.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

get_header();

$fs_cats  = array_slice( fs_get_categories(), 0, 8 );
$fs_newst = fs_has_woo() ? fs_get_newest( 4 ) : array();
?>

<section class="fs-section fs-404">

	<div class="fs-404__box">
		<div class="fs-404__code">۴۰۴</div>
		<h1 class="fs-404__title">این صفحه پیدا نشد</h1>
		<p class="fs-404__text">
			شاید نشانی را اشتباه وارد کرده‌اید، یا این فایل دیگر در دسترس نیست.
			از جست‌وجو یا دسته‌بندی‌های زیر ادامه دهید.
		</p>

		<form class="fs-404__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="fs-sr-only" for="fs-404-s">جست‌وجو</label>
			<input class="fs-404__input" id="fs-404-s" type="search" name="s"
				placeholder="<?php echo esc_attr( fs_copy( 'search_ph' ) ); ?>">
			<?php if ( fs_has_woo() ) : ?>
				<input type="hidden" name="post_type" value="product">
			<?php endif; ?>
			<button class="fs-404__go" type="submit">جست‌وجو</button>
		</form>

		<a class="fs-404__home" href="<?php echo esc_url( home_url( '/' ) ); ?>">بازگشت به صفحه اصلی</a>
	</div>

	<?php if ( $fs_cats ) : ?>
		<h2 class="fs-section__title" style="font-size:18px">دسته‌بندی فایل‌ها</h2>

		<div class="fs-404__cats">
			<?php foreach ( $fs_cats as $fs_i => $fs_cat ) : ?>
				<a class="fs-404__cat" href="<?php echo esc_url( fs_url( $fs_cat['link'] ) ); ?>">
					<span class="fs-404__cat-icon" style="<?php echo esc_attr( fs_tint_style( $fs_i ) ); ?>">
						<?php fs_the_icon( 'grid', 17, array( 'width' => '1.8' ) ); ?>
					</span>
					<span class="fs-404__cat-name"><?php echo esc_html( $fs_cat['name'] ); ?></span>
					<span class="fs-404__cat-n"><?php echo esc_html( $fs_cat['count'] ); ?> <?php echo esc_html( fs_copy( 'cats_unit' ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $fs_newst ) : ?>
		<h2 class="fs-section__title" style="font-size:18px;margin-top:28px">تازه‌ترین فایل‌ها</h2>

		<div class="fs-rail">
			<?php foreach ( $fs_newst as $fs_i => $fs_card ) : ?>
				<?php get_template_part( 'template-parts/card/product', null, array( 'card' => $fs_card ) ); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

</section>

<?php
get_footer();
