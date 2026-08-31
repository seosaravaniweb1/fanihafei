<?php
/**
 * ورود و ثبت‌نام — برگردان طرح «Auth Modal UI».
 *
 * هم با کد پیامکی و هم با رمز عبور کار می‌کند.
 *
 * @package SiFile
 */

defined( 'ABSPATH' ) || exit;

$fs_redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="fs-auth" data-auth data-redirect="<?php echo esc_attr( $fs_redirect ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'fs_auth' ) ); ?>">

	<div class="fs-auth__wrap">
	<div class="fs-auth__card">

		<div class="fs-auth__brand"><?php fs_the_logo( 'header' ); ?></div>

		<p class="fs-auth__error" data-error hidden></p>

		<!-- گام ۱: شماره موبایل -->
		<div class="fs-auth__step" data-step="entry">
			<a class="fs-auth__back" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="بازگشت به فروشگاه">
				<?php fs_the_icon( 'arrow-next', 19, array( 'width' => '2' ) ); ?>
			</a>

			<h2 class="fs-auth__title">ورود | ثبت‌نام</h2>
			<p class="fs-auth__sub">سلام! لطفاً شماره موبایل خود را وارد کنید.</p>

			<label class="fs-sr-only" for="fs-auth-login">شماره موبایل، ایمیل یا نام کاربری</label>
			<input class="fs-auth__input" id="fs-auth-login" type="text" inputmode="tel" autocomplete="username"
				placeholder="۰۹۱۲۱۲۳۴۵۶۷" data-field="login">

			<?php
			/*
			 * ویجت کپچا فقط برای v2 و hCaptcha لازم است؛ reCAPTCHA v3 نامرئی
			 * است و توکنش را جاوااسکریپت موقع ارسال می‌گیرد.
			 */
			$fs_captcha = fs_captcha_config();

			if ( $fs_captcha && 'recaptcha_v3' !== $fs_captcha['provider'] ) :
				$fs_widget = 'hcaptcha' === $fs_captcha['provider'] ? 'h-captcha' : 'g-recaptcha';
				?>
				<div class="fs-auth__captcha <?php echo esc_attr( $fs_widget ); ?>"
					data-sitekey="<?php echo esc_attr( $fs_captcha['sitekey'] ); ?>"></div>
			<?php endif; ?>

			<button class="fs-auth__submit" type="button" data-action="entry">
				<span class="fs-auth__submit-text">ادامه</span>
				<?php fs_the_icon( 'chevron-prev', 15, array( 'stroke' => '#fff', 'width' => '2.4' ) ); ?>
				<span class="fs-auth__spinner" hidden></span>
			</button>

			<div class="fs-auth__alts">
				<button class="fs-auth__alt" type="button" data-goto="password">
					<?php fs_the_icon( 'user', 16, array( 'width' => '1.9' ) ); ?>
					ورود بدون احراز پیامکی
				</button>
				<button class="fs-auth__alt" type="button" data-goto="register">
					<?php fs_the_icon( 'plus', 16, array( 'width' => '2.2' ) ); ?>
					ثبت‌نام بدون احراز پیامکی
				</button>
			</div>
		</div>

		<!-- گام ۲: کد پیامکی -->
		<div class="fs-auth__step" data-step="otp" hidden>
			<button class="fs-auth__back" type="button" data-goto="entry" aria-label="بازگشت">
				<?php fs_the_icon( 'arrow-next', 19, array( 'width' => '2' ) ); ?>
			</button>

			<h2 class="fs-auth__title">تایید شماره</h2>
			<p class="fs-auth__sub">کد <?php echo esc_html( fs_fa_num( fs_otp_length() ) ); ?> رقمی پیامک‌شده را وارد کنید.</p>

			<div class="fs-auth__phone">
				<span data-phone-label></span>
				<button class="fs-auth__link" type="button" data-goto="entry">تغییر شماره</button>
			</div>

			<?php
			/*
			 * تعداد خانه‌ها از همان تنظیمی می‌آید که طول کد ساخته‌شده را تعیین
			 * می‌کند، تا هیچ‌وقت فرم و پیامک با هم نخوانند.
			 *
			 * autocomplete="one-time-code" فقط روی خانه‌ی اول است: سافاری کل کد
			 * را در همان اولی می‌ریزد و جاوااسکریپت پخشش می‌کند.
			 */
			$fs_otp_len = fs_otp_length();
			?>
			<div class="fs-auth__otp" data-otp dir="ltr">
				<?php for ( $fs_i = 0; $fs_i < $fs_otp_len; $fs_i++ ) : ?>
					<input type="text" inputmode="numeric" maxlength="1"
						<?php echo 0 === $fs_i ? 'autocomplete="one-time-code"' : ''; ?>>
				<?php endfor; ?>
			</div>

			<div class="fs-auth__resend">
				<span data-timer hidden>ارسال دوباره تا <b data-countdown>۰۰:۰۰</b></span>
				<button class="fs-auth__link" type="button" data-action="resend" hidden data-resend>ارسال دوباره کد</button>
			</div>

			<button class="fs-auth__submit" type="button" data-action="otp">
				<span class="fs-auth__submit-text">تایید و ورود</span>
				<span class="fs-auth__spinner" hidden></span>
			</button>

			<button class="fs-auth__alt" type="button" data-goto="password"><?php fs_the_icon( 'user', 16, array( 'width' => '1.9' ) ); ?>ورود بدون احراز پیامکی</button>
		</div>

		<!-- گام ۳: نام و نام خانوادگی (کاربر تازه) -->
		<div class="fs-auth__step" data-step="profile" hidden>
			<button class="fs-auth__back" type="button" data-goto="entry" aria-label="بازگشت">
				<?php fs_the_icon( 'arrow-next', 19, array( 'width' => '2' ) ); ?>
			</button>

			<h2 class="fs-auth__title">تکمیل حساب</h2>
			<p class="fs-auth__sub">خوش آمدید! برای ساخت حساب، نام خود را وارد کنید.</p>

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
			<button class="fs-auth__back" type="button" data-goto="entry" aria-label="بازگشت">
				<?php fs_the_icon( 'arrow-next', 19, array( 'width' => '2' ) ); ?>
			</button>

			<h2 class="fs-auth__title">ورود با رمز عبور</h2>
			<p class="fs-auth__sub">شماره موبایل یا ایمیل و رمز عبورتان را وارد کنید.</p>

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

			<div class="fs-auth__alts">
				<button class="fs-auth__alt" type="button" data-goto="entry"><?php fs_the_icon( 'chat', 16, array( 'width' => '1.9' ) ); ?>ورود با کد پیامکی</button>
				<button class="fs-auth__alt" type="button" data-goto="register"><?php fs_the_icon( 'plus', 16, array( 'width' => '2.2' ) ); ?>ثبت‌نام بدون احراز پیامکی</button>
			</div>
		</div>

		<!-- ثبت‌نام با رمز عبور -->
		<div class="fs-auth__step" data-step="register" hidden>
			<button class="fs-auth__back" type="button" data-goto="entry" aria-label="بازگشت">
				<?php fs_the_icon( 'arrow-next', 19, array( 'width' => '2' ) ); ?>
			</button>

			<h2 class="fs-auth__title">ثبت‌نام با رمز عبور</h2>
			<p class="fs-auth__sub">حساب تازه بسازید؛ بدون نیاز به کد پیامکی.</p>

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

			<div class="fs-auth__alts">
				<button class="fs-auth__alt" type="button" data-goto="entry"><?php fs_the_icon( 'chat', 16, array( 'width' => '1.9' ) ); ?>ثبت‌نام با کد پیامکی</button>
			</div>
		</div>

		<p class="fs-auth__terms">
			با ادامه، <a href="<?php echo esc_url( home_url( '/terms' ) ); ?>">قوانین و حریم خصوصی</a> <?php bloginfo( 'name' ); ?> را می‌پذیرید.
		</p>

	</div>

	</div>
</div>
