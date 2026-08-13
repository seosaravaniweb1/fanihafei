<?php
/**
 * سربرگ سایت.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

$fs_cats = fs_get_categories();
$fs_menu = fs_menu_items( 'primary' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="fs-skip-link" href="#fs-main">پرش به محتوای اصلی</a>

<div class="fs-container">

	<header class="fs-header">

		<div class="fs-header__top">

			<div class="fs-header__start">
				<?php fs_the_logo( 'header' ); ?>
			</div>

			<form class="fs-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php fs_the_icon( 'search', 16, array( 'stroke' => '#94a3b8' ) ); ?>
				<label class="fs-sr-only" for="fs-search-field">جست‌وجو</label>
				<input class="fs-search__input" id="fs-search-field" type="search" name="s"
					value="<?php echo esc_attr( get_search_query() ); ?>"
					placeholder="<?php echo esc_attr( fs_copy( 'search_ph' ) ); ?>">
				<?php if ( fs_has_woo() ) : ?>
					<input type="hidden" name="post_type" value="product">
				<?php endif; ?>
			</form>

			<div class="fs-header__end">
				<a class="fs-btn-account" href="<?php echo esc_url( fs_account_url() ); ?>">
					<?php fs_the_icon( 'user', 16, array( 'stroke' => '#fff', 'width' => '1.8' ) ); ?>
					<?php echo esc_html( fs_account_label() ); ?>
				</a>

				<?php if ( $fs_menu ) : ?>
					<button class="fs-burger" type="button" aria-expanded="false" aria-controls="fs-mobile-menu">
						<span class="fs-sr-only">منوی سایت</span>
						<?php fs_the_icon( 'menu-mobile', 16, array( 'width' => '1.9' ) ); ?>
					</button>
				<?php endif; ?>
			</div>

			<?php if ( $fs_menu ) : ?>
				<div class="fs-mobile-menu" id="fs-mobile-menu" hidden>
					<div class="fs-mobile-menu__label">منوی اصلی سایت</div>

					<nav class="fs-mobile-menu__list" aria-label="منوی موبایل">
						<?php foreach ( $fs_menu as $fs_item ) : ?>
							<a href="<?php echo esc_url( fs_url( $fs_item['url'] ) ); ?>">
								<span><?php echo esc_html( $fs_item['title'] ); ?></span>
								<?php fs_the_icon( 'chevron-prev', 14, array( 'stroke' => '#94a3b8', 'width' => '2.2' ) ); ?>
							</a>
						<?php endforeach; ?>
					</nav>

					<div class="fs-mobile-menu__actions">
						<a href="<?php echo esc_url( fs_account_url() ); ?>">
							<?php fs_the_icon( 'user', 15, array( 'stroke' => '#fff', 'width' => '1.9' ) ); ?>
							<?php echo esc_html( fs_account_label() ); ?>
						</a>
						<?php if ( fs_has_woo() ) : ?>
							<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'downloads' ) ); ?>">
								<?php fs_the_icon( 'download', 15, array( 'width' => '1.9' ) ); ?>
								دانلودهای من
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

		</div><!-- .fs-header__top -->

		<?php if ( $fs_cats || $fs_menu ) : ?>
			<div class="fs-header__bottom">

				<?php if ( $fs_cats ) : ?>
					<button class="fs-mega-trigger" type="button" aria-expanded="false" aria-controls="fs-mega">
						<?php fs_the_icon( 'menu', 16 ); ?>
						<?php echo esc_html( fs_copy( 'cats_trigger' ) ); ?>
						<?php fs_the_icon( 'chevron-down', 14, array( 'width' => '2.2' ) ); ?>
					</button>

					<?php if ( $fs_menu ) : ?>
						<span class="fs-header__divider" aria-hidden="true"></span>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $fs_menu ) : ?>
					<nav class="fs-quicklinks" aria-label="منوی سربرگ">
						<?php foreach ( $fs_menu as $fs_item ) : ?>
							<a href="<?php echo esc_url( fs_url( $fs_item['url'] ) ); ?>"><?php echo esc_html( $fs_item['title'] ); ?></a>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>

				<?php if ( $fs_cats ) : ?>
					<button class="fs-drawer-trigger" type="button" aria-expanded="false" aria-controls="fs-drawer">
						<?php fs_the_icon( 'menu', 16, array( 'stroke' => '#0f172a' ) ); ?>
						<?php echo esc_html( fs_copy( 'cats_trigger' ) ); ?>
						<?php fs_the_icon( 'chevron-down', 14, array( 'stroke' => '#059669', 'width' => '2.2' ) ); ?>
					</button>

					<?php get_template_part( 'template-parts/header/mega', null, array( 'cats' => $fs_cats ) ); ?>
				<?php endif; ?>

			</div><!-- .fs-header__bottom -->
		<?php endif; ?>

	</header>

	<?php
	if ( $fs_cats ) {
		get_template_part( 'template-parts/header/drawer', null, array( 'cats' => $fs_cats ) );
	}
	?>

	<main id="fs-main">
