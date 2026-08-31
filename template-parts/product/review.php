<?php
/**
 * یک دیدگاه محصول، همراه با پاسخ‌هایش.
 *
 * بازگشتی است: هر پاسخ دوباره همین قالب را با یک سطح تورفتگی بیشتر صدا
 * می‌زند، تا گفت‌وگوی زیر یک دیدگاه به هم نریزد.
 *
 * @package SiFile
 *
 * @var array $args review، product_id و depth
 */

defined( 'ABSPATH' ) || exit;

$fs_r     = isset( $args['review'] ) ? $args['review'] : array();
$fs_pid   = isset( $args['product_id'] ) ? (int) $args['product_id'] : 0;
$fs_depth = isset( $args['depth'] ) ? (int) $args['depth'] : 0;

if ( ! $fs_r ) {
	return;
}

// تورفتگی سقف دارد: بدون آن، یک رشته پاسخِ طولانی روی موبایل به یک ستون
// باریک چند کاراکتری می‌رسید.
$fs_indent = min( 2, $fs_depth );
?>

<div class="fs-revcard<?php echo $fs_depth ? ' fs-revcard--reply' : ''; ?><?php echo ! empty( $fs_r['staff'] ) ? ' fs-revcard--staff' : ''; ?>"
	id="comment-<?php echo (int) $fs_r['id']; ?>"
	<?php echo $fs_indent ? 'style="--fs-rev-indent:' . (int) $fs_indent . '"' : ''; ?>>

	<div class="fs-revcard__top">
		<div class="fs-revcard__who">
			<span class="fs-revcard__avatar" style="background:<?php echo esc_attr( $fs_r['tint'] ); ?>">
				<?php echo esc_html( $fs_r['initial'] ); ?>
			</span>
			<span>
				<span class="fs-revcard__name">
					<?php echo esc_html( $fs_r['name'] ); ?>
					<?php if ( ! empty( $fs_r['staff'] ) ) : ?>
						<span class="fs-revcard__badge">پشتیبانی لوکسو</span>
					<?php endif; ?>
				</span>
				<span class="fs-revcard__date">
					<?php echo $fs_depth ? 'پاسخ' : 'خرید تاییدشده'; ?> · <?php echo esc_html( $fs_r['date'] ); ?>
				</span>
			</span>
		</div>

		<?php if ( ! empty( $fs_r['stars'] ) ) : ?>
			<span class="fs-revcard__stars" title="<?php echo esc_attr( fs_fa_num( $fs_r['rating'] ) ); ?> از ۵">
				<?php echo esc_html( $fs_r['stars'] ); ?>
			</span>
		<?php endif; ?>
	</div>

	<div class="fs-revcard__text"><?php echo esc_html( $fs_r['text'] ); ?></div>

	<?php if ( comments_open( $fs_pid ) ) : ?>
		<div class="fs-revcard__actions">
			<?php
			/*
			 * لینک پاسخ. اسکریپت comment-reply وردپرس فرم را زیر همین دیدگاه
			 * جابه‌جا می‌کند و comment_parent را پر می‌کند؛ بدون آن، پاسخ به
			 * شکل یک دیدگاه سطح‌اولِ تازه ثبت می‌شد.
			 */
			comment_reply_link(
				array(
					'depth'     => $fs_depth + 1,
					'max_depth' => 5,
					'reply_text' => 'پاسخ',
					'add_below' => 'comment',
					'respond_id' => 'respond',
				),
				$fs_r['id'],
				$fs_pid
			);
			?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $fs_r['replies'] ) ) : ?>
		<div class="fs-revcard__replies">
			<?php
			foreach ( $fs_r['replies'] as $fs_reply ) {
				get_template_part(
					'template-parts/product/review',
					null,
					array(
						'review'     => $fs_reply,
						'product_id' => $fs_pid,
						'depth'      => $fs_depth + 1,
					)
				);
			}
			?>
		</div>
	<?php endif; ?>

</div>
