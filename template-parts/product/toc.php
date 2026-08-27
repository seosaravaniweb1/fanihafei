<?php
/**
 * فهرست سرفصل‌های محصول — بالای متن توضیحات.
 *
 * هر آیتمی که شناسه داشته باشد به‌صورت لینک لنگری رندر می‌شود و کاربر را
 * دقیقاً به همان تیتر در متن می‌برد؛ آیتم‌های بدون شناسه (سرفصل‌های دستی)
 * فقط متن ساده‌اند.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

$fs_items = isset( $args['items'] ) ? (array) $args['items'] : array();

if ( ! $fs_items ) {
	return;
}
?>
<nav class="fs-toc" aria-label="سرفصل‌های این فایل">

	<div class="fs-toc__title">
		<?php fs_the_icon( 'menu', 15, array( 'stroke' => 'currentColor', 'width' => '2.2' ) ); ?>
		سرفصل‌های این فایل
	</div>

	<ol class="fs-toc__list">
		<?php foreach ( $fs_items as $fs_item ) : ?>
			<li class="fs-toc__item fs-toc__item--l<?php echo (int) $fs_item['level']; ?>">
				<?php if ( $fs_item['id'] ) : ?>
					<a href="#<?php echo esc_attr( $fs_item['id'] ); ?>"><?php echo esc_html( $fs_item['text'] ); ?></a>
				<?php else : ?>
					<span><?php echo esc_html( $fs_item['text'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>

</nav>
