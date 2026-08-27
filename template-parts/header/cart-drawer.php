<?php
/**
 * کشوی سبد خرید — از سمت راست باز می‌شود و محتوایش با اجاکس تازه می‌شود.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="fs-cart" id="fs-cart" hidden>

	<div class="fs-cart__backdrop" data-cart-close></div>

	<aside class="fs-cart__panel" role="dialog" aria-modal="true" aria-label="سبد خرید">

		<header class="fs-cart__head">
			<span class="fs-cart__head-title">
				<?php fs_the_icon( 'cart', 17, array( 'stroke' => 'currentColor', 'width' => '1.9' ) ); ?>
				سبد خرید
			</span>
			<button class="fs-cart__close" type="button" data-cart-close aria-label="بستن سبد خرید">&times;</button>
		</header>

		<div class="fs-cart__body" data-cart-body>
			<?php get_template_part( 'template-parts/header/cart-body' ); ?>
		</div>

		<div class="fs-cart__toast" data-cart-toast hidden></div>

	</aside>

</div>
