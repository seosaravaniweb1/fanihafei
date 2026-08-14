<?php
/**
 * فرم فشرده‌ی مشخصات — در پیشخوان («تکمیل سریع») و صفحه‌ی «ویرایش مشخصات» استفاده می‌شود.
 *
 * @package FanniSoal
 *
 * $args['compact'] bool  حالت فشرده (پیشخوان) یا کامل (صفحه‌ی ویرایش مشخصات).
 */

defined( 'ABSPATH' ) || exit;

$fs_user   = wp_get_current_user();
$fs_phone  = get_user_meta( $fs_user->ID, FS_PHONE_META, true );
$fs_compact = ! empty( $args['compact'] );
?>
<form class="fs-profile-form<?php echo $fs_compact ? ' fs-profile-form--compact' : ''; ?>" data-profile-form>

	<div class="fs-profile-form__grid">
		<label class="fs-pfield">
			<span class="fs-pfield__label">نام</span>
			<input class="fs-pfield__input" type="text" name="first_name" value="<?php echo esc_attr( $fs_user->first_name ); ?>" placeholder="نام شما" required>
		</label>

		<label class="fs-pfield">
			<span class="fs-pfield__label">نام خانوادگی</span>
			<input class="fs-pfield__input" type="text" name="last_name" value="<?php echo esc_attr( $fs_user->last_name ); ?>" placeholder="نام خانوادگی">
		</label>

		<label class="fs-pfield">
			<span class="fs-pfield__label">تلفن همراه</span>
			<input class="fs-pfield__input" type="tel" name="phone" value="<?php echo esc_attr( fs_fa_num( $fs_phone ) ); ?>" placeholder="۰۹۱۲۳۴۵۶۷۸۹" dir="ltr">
		</label>

		<label class="fs-pfield">
			<span class="fs-pfield__label">ایمیل <span class="fs-auth__optional">اختیاری</span></span>
			<input class="fs-pfield__input" type="email" name="email" value="<?php echo esc_attr( fs_real_email( $fs_user ) ); ?>" placeholder="اگر ایمیل ندارید خالی بگذارید" dir="ltr">
		</label>
	</div>

	<p class="fs-profile-form__msg" data-profile-msg hidden></p>

	<div class="fs-profile-form__actions">
		<button class="fs-profile-form__submit" type="submit">
			<?php fs_the_icon( 'check', 16, array( 'stroke' => '#fff', 'width' => '2.2' ) ); ?>
			<?php echo $fs_compact ? 'ثبت و ذخیره' : 'ذخیره تغییرات'; ?>
		</button>

		<?php
		// بازیابی رمز از راه ایمیل انجام می‌شود؛ برای کاربری که ایمیل ندارد این
		// لینک بن‌بست است، پس به‌جایش راه ورود واقعی‌اش یادآوری می‌شود.
		if ( ! $fs_compact ) :
			if ( fs_real_email( $fs_user ) ) :
				?>
				<a class="fs-profile-form__pass" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">تغییر رمز عبور</a>
			<?php else : ?>
				<span class="fs-profile-form__hint">ورود شما با کد پیامکی انجام می‌شود و به رمز عبور نیازی ندارید.</span>
				<?php
			endif;
		endif;
		?>
	</div>

</form>
