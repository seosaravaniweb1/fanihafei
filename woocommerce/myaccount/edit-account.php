<?php
/**
 * ویرایش مشخصات — فقط نام، نام خانوادگی، تلفن و ایمیل؛ بدون آدرس پستی.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_edit_account_form' );
?>

<div class="fs-editaccount">
	<h2 class="fs-dash__block-title">ویرایش مشخصات</h2>
	<p class="fs-editaccount__sub">فقط نام، نام خانوادگی، تلفن و ایمیل — آدرس پستی لازم نیست.</p>

	<?php get_template_part( 'template-parts/account/profile-form', null, array( 'compact' => false ) ); ?>
</div>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
