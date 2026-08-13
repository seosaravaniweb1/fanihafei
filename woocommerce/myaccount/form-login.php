<?php
/**
 * ورود و ثبت‌نام — برگردان طرح «Auth Modal UI».
 *
 * هم با کد پیامکی و هم با رمز عبور کار می‌کند.
 *
 * @package FanniSoal
 */

defined( 'ABSPATH' ) || exit;

$fs_redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="fs-auth" data-auth data-redirect="<?php echo esc_attr( $fs_redirect ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fs_auth' ) ); ?>">

	<div class="fs-auth__wrap">

		<aside class="fs-auth__side">
			<div class="fs-auth__side-logo"><?php fs_the_logo( 'header' ); ?></div>

			<h2 class="fs-auth__side-title">با یک حساب، همه فایل‌هایتان همیشه در دسترس است</h2>

			<?php if ( get_bloginfo( 'description' ) ) : ?>
				<p class="fs-auth__side-desc"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
			<?php endif; ?>

			<ul class="fs-auth__side-list">
				<?php foreach ( array_slice( fs_data_trust(), 0, 4 ) as $fs_point ) : ?>
					<li>
						<span class="fs-auth__side-check"><?php fs_the_icon( 'check', 13, array( 'stroke' => '#6ee7b7', 'width' => '2.6' ) ); ?></span>
						<?php echo esc_html( $fs_point ); ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<p class="fs-auth__side-foot">با ادامه، <span>قوانین و حریم خصوصی</span> <?php bloginfo( 'name' ); ?> را می‌پذیرید.</p>
		</aside>

	<div class="fs-auth__card">

		<div class="fs-auth__brand"><?php fs_the_logo( 'header' ); ?></div>

		<div class="fs-auth__tabs" role="tablist">
			<button class="fs-auth__tab is-active" type="button" data-goto="entry">کد پیامکی</button>
			<button class="fs-auth__tab" type="button" data-goto="password">رمز عبور</button>
		</div>

		<p class="fs-auth__error" data-error hidden></p>

		<!-- گام ۱: شماره موبایل -->
		<div class="fs-auth__step" data-step="entry">
			<label class="fs-auth__label" for="fs-auth-login">شماره موبایل، ایمیل یا نام کاربری</label>
			<input class="fs-auth__input" id="fs-auth-login" type="text" inputmode="tel" autocomplete="username"
				placeholder="۰۹۱۲۱۲۳۴۵۶۷" data-field="login">

			<button class="fs-auth__submit" type="button" data-action="entry">
				<span class="fs-auth__submit-text">ادامه</span>
				<span class="fs-auth__spinner" hidden></span>
			</button>

			<p class="fs-auth__hint">ورود و ثبت‌نام یکی است؛ اگر شماره تازه باشد همین‌جا حساب ساخته می‌شود.</p>
		</div>

		<!-- گام ۲: کد پیامکی -->
		<div class="fs-auth__step" data-step="otp" hidden>
			<div class="fs-auth__phone">
				<span data-phone-label></span>
				<button class="fs-auth__link" type="button" data-goto="entry">تغییر شماره</button>
			</div>

			<label class="fs-auth__label">کد ۵ رقمی پیامک‌شده را وارد کنید</label>

			<div class="fs-auth__otp" data-otp dir="ltr">
				<input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code">
				<input type="text" inputmode="numeric" maxlength="1">
				<input type="text" inputmode="numeric" maxlength="1">
				<input type="text" inputmode="numeric" maxlength="1">
				<input type="text" inputmode="numeric" maxlength="1">
			</div>

			<div class="fs-auth__resend">
				<span data-timer hidden>ارسال دوباره تا <b data-countdown>۰۰:۰۰</b></span>
				<button class="fs-auth__link" type="button" data-action="resend" hidden data-resend>ارسال دوباره کد</button>
			</div>

			<button class="fs-auth__submit" type="button" data-action="otp">
				<span class="fs-auth__submit-text">تایید و ورود</span>
				<span class="fs-auth__spinner" hidden></span>
			</button>

			<button class="fs-auth__link fs-auth__link--block" type="button" data-goto="password">ورود با رمز عبور</button>
		</div>

		<!-- گام ۳: نام و نام خانوادگی (کاربر تازه) -->
		<div class="fs-auth__step" data-step="profile" hidden>
			<p class="fs-auth__welcome">خوش آمدید! برای تکمیل حساب، نام خود را وارد کنید.</p>

			<div class="fs-auth__row">
				<div>
					<label class="fs-auth__label" for="fs-auth-first">نام</label>
					<input class="fs-auth__input" id="fs-auth-first" type="text" autocomplete="given-name" data-field="first_name">
				</div>
				<div>
					<label class="fs-auth__label" for="fs-auth-last">نام خانوادگی</label>
					<input class="fs-auth__input" id="fs-auth-last" type="text" autocomplete="family-name" data-field="last_name">
				</div>
			</div>

			<label class="fs-auth__label" for="fs-auth-email">
				ایمیل <span class="fs-auth__optional">اختیاری</span>
			</label>
			<input class="fs-auth__input" id="fs-auth-email" type="email" autocomplete="email" data-field="email"
				placeholder="اگر خالی بماند، خودکار ساخته می‌شود">

			<button class="fs-auth__submit" type="button" data-action="profile">
				<span class="fs-auth__submit-text">ساخت حساب و ورود</span>
				<span class="fs-auth__spinner" hidden></span>
			</button>
		</div>

		<!-- ورود با رمز عبور -->
		<div class="fs-auth__step" data-step="password" hidden>
			<label class="fs-auth__label" for="fs-auth-user">شماره موبایل، ایمیل یا نام کاربری</label>
			<input class="fs-auth__input" id="fs-auth-user" type="text" autocomplete="username" data-field="login">

			<label class="fs-auth__label" for="fs-auth-pass">رمز عبور</label>
			<input class="fs-auth__input" id="fs-auth-pass" type="password" autocomplete="current-password" data-field="password">

			<div class="fs-auth__row fs-auth__row--between">
				<a class="fs-auth__link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">رمز را فراموش کرده‌ام</a>
			</div>

			<button class="fs-auth__submit" type="button" data-action="password">
				<span class="fs-auth__submit-text">ورود به حساب</span>
				<span class="fs-auth__spinner" hidden></span>
			</button>

			<button class="fs-auth__link fs-auth__link--block" type="button" data-goto="entry">ورود با کد پیامکی</button>
			<button class="fs-auth__link fs-auth__link--block" type="button" data-goto="register">ساخت حساب با رمز عبور</button>
		</div>

		<!-- ثبت‌نام با رمز عبور -->
		<div class="fs-auth__step" data-step="register" hidden>
			<label class="fs-auth__label" for="fs-reg-phone">شماره موبایل</label>
			<input class="fs-auth__input" id="fs-reg-phone" type="text" inputmode="tel" placeholder="۰۹۱۲۱۲۳۴۵۶۷" data-field="phone">

			<div class="fs-auth__row">
				<div>
					<label class="fs-auth__label" for="fs-reg-first">نام</label>
					<input class="fs-auth__input" id="fs-reg-first" type="text" data-field="first_name">
				</div>
				<div>
					<label class="fs-auth__label" for="fs-reg-last">نام خانوادگی</label>
					<input class="fs-auth__input" id="fs-reg-last" type="text" data-field="last_name">
				</div>
			</div>

			<label class="fs-auth__label" for="fs-reg-email">
				ایمیل <span class="fs-auth__optional">اختیاری</span>
			</label>
			<input class="fs-auth__input" id="fs-reg-email" type="email" data-field="email">

			<label class="fs-auth__label" for="fs-reg-pass">رمز عبور</label>
			<input class="fs-auth__input" id="fs-reg-pass" type="password" autocomplete="new-password" data-field="password">
			<p class="fs-auth__hint">رمز عبور باید دست‌کم ۸ نویسه باشد.</p>

			<button class="fs-auth__submit" type="button" data-action="register">
				<span class="fs-auth__submit-text">ساخت حساب و ورود</span>
				<span class="fs-auth__spinner" hidden></span>
			</button>

			<button class="fs-auth__link fs-auth__link--block" type="button" data-goto="entry">ثبت‌نام با پیامک</button>
		</div>

		<p class="fs-auth__terms">
			با ادامه، <a href="<?php echo esc_url( home_url( '/terms' ) ); ?>">قوانین و حریم خصوصی</a> <?php bloginfo( 'name' ); ?> را می‌پذیرید.
		</p>

	</div>

	</div>
</div>
