<?php
/**
 * منوی پیشخوان کاربری.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_navigation' );
?>
<nav class="woocommerce-MyAccount-navigation fs-anav" aria-label="منوی پیشخوان">
	<div class="fs-anav__title">منوی پیشخوان</div>

	<ul>
		<?php foreach ( wc_get_account_menu_items() as $fs_key => $fs_label ) : ?>
			<li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $fs_key ) ); ?>">
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( $fs_key ) ); ?>">
					<span class="fs-anav__icon"><?php fs_the_icon( fs_account_icon( $fs_key ), 15, array( 'width' => '1.9' ) ); ?></span>
					<span class="fs-anav__label"><?php echo esc_html( $fs_label ); ?></span>
					<span class="fs-anav__dot" aria-hidden="true"></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
<?php
do_action( 'woocommerce_after_account_navigation' );
