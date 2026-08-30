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
			<button class="fs-cart__close" type="button" data-cart-close aria-label="بستن">&times;</button>
		</header>

		<div class="fs-cart__body" data-cart-body>
			<?php get_template_part( 'template-parts/header/cart-body' ); ?>
		</div>

		<div class="fs-cart__toast" data-cart-toast hidden></div>

	</aside>

</div>

<?php
/*
 * برگه‌ی انتخاب پس از افزودن.
 *
 * کاربر تازه یک فایل به سبد اضافه کرده و دقیقاً همین‌جا باید تصمیم بگیرد:
 * همین یکی را بپردازد یا فایل دیگری هم بردارد. پیش از این، هر افزودن
 * مستقیم به تسویه‌حساب می‌رفت و راهی برای خرید چندتایی نبود.
 *
 * بیرون از کشو است چون خودش یک لایه‌ی مستقل روی صفحه است و نباید با
 * باز و بسته شدن کشو گره بخورد.
 */
?>
<div class="fs-buysheet" id="fs-buysheet" hidden>

	<div class="fs-buysheet__backdrop" data-sheet-close></div>

	<div class="fs-buysheet__panel" role="dialog" aria-modal="true" aria-labelledby="fs-buysheet-title">

		<span class="fs-buysheet__tick">
			<?php fs_the_icon( 'check', 22, array( 'stroke' => '#fff', 'width' => '3' ) ); ?>
		</span>

		<h2 class="fs-buysheet__title" id="fs-buysheet-title">به سبد خرید اضافه شد</h2>
		<p class="fs-buysheet__name" data-sheet-name></p>

		<button class="fs-buysheet__go" type="button" data-sheet-checkout>
			پرداخت و دانلود همین فایل
		</button>

		<button class="fs-buysheet__more" type="button" data-sheet-more>
			<?php fs_the_icon( 'plus', 15, array( 'width' => '2.2' ) ); ?>
			افزودن فایل بیشتر
		</button>

	</div>

</div>
