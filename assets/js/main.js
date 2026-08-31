/**
 * تعاملات قالب سی‌فایل.
 *
 * معادل وضعیت‌های DCLogic در فایل طرح:
 * mega / drawer / menu / ci / pi / di / allCats
 *
 * بدون وابستگی؛ همه‌ی پنل‌ها سمت سرور رندر شده‌اند و اینجا فقط
 * نمایش‌شان جابه‌جا می‌شود تا تعویض تب بدون درخواست شبکه و آنی باشد.
 */
( function () {
	'use strict';

	var mq = window.matchMedia( '(max-width: 1023px)' );

	/**
	 * نمایش/پنهان کردن یک عنصر با اتریبیوت hidden.
	 *
	 * @param {Element} el   عنصر.
	 * @param {boolean} show نمایش داده شود؟
	 */
	function toggle( el, show ) {
		if ( ! el ) {
			return;
		}

		if ( show ) {
			el.removeAttribute( 'hidden' );
		} else {
			el.setAttribute( 'hidden', '' );
		}
	}

	/**
	 * فقط یک بار در هر بازه‌ی زمانی اجرا شود.
	 *
	 * چرا: دکمه‌ی خرید و دکمه‌ی حذف، هر کدام یک درخواست شبکه راه می‌اندازند.
	 * دابل‌کلیک یا کلیک عصبی کاربر روی موبایل، چند درخواست هم‌زمان می‌سازد که
	 * نخ اصلی را قفل می‌کند و INP را بالا می‌برد. با این محافظ، کلیک‌های پشت‌سرهم
	 * در همان بازه نادیده گرفته می‌شوند.
	 *
	 * @param {Function} fn   تابع اصلی.
	 * @param {number}   wait بازه بر حسب میلی‌ثانیه.
	 * @return {Function} تابع محافظت‌شده.
	 */
	function throttle( fn, wait ) {
		var last = 0;

		return function () {
			var now = Date.now();

			if ( now - last < wait ) {
				return;
			}

			last = now;

			return fn.apply( this, arguments );
		};
	}

	/**
	 * فعال‌سازی یک تب از میان مجموعه‌ای از تب‌ها و پنل‌ها.
	 *
	 * @param {NodeList} tabs     دکمه‌های تب.
	 * @param {NodeList} panes    پنل‌ها.
	 * @param {number}   index    اندیس فعال.
	 * @param {string}   cls      کلاس حالت فعال.
	 * @param {Function} [after]  کال‌بک بعد از تعویض.
	 */
	function activate( tabs, panes, index, cls, after ) {
		Array.prototype.forEach.call( tabs, function ( tab, i ) {
			var on = i === index;
			tab.classList.toggle( cls, on );
			tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
		} );

		Array.prototype.forEach.call( panes, function ( pane, i ) {
			toggle( pane, i === index );
		} );

		if ( after ) {
			after( tabs[ index ], index );
		}
	}

	/* ---------------------------------------------------------------- مگامنو */
	function initMega() {
		var trigger = document.querySelector( '.fs-mega-trigger' );
		var mega = document.getElementById( 'fs-mega' );

		if ( ! trigger || ! mega ) {
			return;
		}

		var rows = mega.querySelectorAll( '.fs-mega__row' );
		var panes = mega.querySelectorAll( '.fs-mega__pane' );

		function open( state ) {
			toggle( mega, state );
			trigger.setAttribute( 'aria-expanded', state ? 'true' : 'false' );
		}

		trigger.addEventListener( 'click', function () {
			open( mega.hasAttribute( 'hidden' ) );
		} );

		trigger.addEventListener( 'mouseenter', function () {
			if ( ! mq.matches ) {
				open( true );
			}
		} );

		mega.addEventListener( 'mouseleave', function () {
			if ( ! mq.matches ) {
				open( false );
			}
		} );

		Array.prototype.forEach.call( rows, function ( row, i ) {
			function select() {
				activate( rows, panes, i, 'is-active' );
			}

			row.addEventListener( 'mouseenter', select );
			row.addEventListener( 'click', select );
			row.addEventListener( 'focus', select );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! mega.hasAttribute( 'hidden' ) ) {
				open( false );
				trigger.focus();
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! mega.hasAttribute( 'hidden' ) && ! mega.contains( e.target ) && ! trigger.contains( e.target ) ) {
				open( false );
			}
		} );
	}

	/* ---------------------------------------------------- منوی کشویی موبایل */
	function initMobileMenu() {
		var burger = document.querySelector( '.fs-burger' );
		var menu = document.getElementById( 'fs-mobile-menu' );

		if ( ! burger || ! menu ) {
			return;
		}

		burger.addEventListener( 'click', function () {
			var show = menu.hasAttribute( 'hidden' );
			toggle( menu, show );
			burger.setAttribute( 'aria-expanded', show ? 'true' : 'false' );
		} );
	}

	/* --------------------------------------------------- کشوی دسته‌بندی موبایل */
	function initDrawer() {
		var trigger = document.querySelector( '.fs-drawer-trigger' );
		var drawer = document.getElementById( 'fs-drawer' );

		if ( ! trigger || ! drawer ) {
			return;
		}

		trigger.addEventListener( 'click', function () {
			var show = drawer.hasAttribute( 'hidden' );
			toggle( drawer, show );
			trigger.setAttribute( 'aria-expanded', show ? 'true' : 'false' );
		} );

		drawer.addEventListener( 'click', function ( e ) {
			var caret = e.target.closest( '.fs-drawer__caret' );

			if ( ! caret ) {
				return;
			}

			var item = caret.closest( '.fs-drawer__item' );
			var panel = document.getElementById( caret.getAttribute( 'aria-controls' ) );
			var show = panel.hasAttribute( 'hidden' );

			// آکاردئون: هربار فقط یک دسته باز می‌ماند.
			Array.prototype.forEach.call( drawer.querySelectorAll( '.fs-drawer__item' ), function ( other ) {
				if ( other === item ) {
					return;
				}

				other.classList.remove( 'is-open' );
				var otherCaret = other.querySelector( '.fs-drawer__caret' );
				var otherPanel = other.querySelector( '.fs-drawer__panel' );
				toggle( otherPanel, false );

				if ( otherCaret ) {
					otherCaret.setAttribute( 'aria-expanded', 'false' );
				}
			} );

			item.classList.toggle( 'is-open', show );
			toggle( panel, show );
			caret.setAttribute( 'aria-expanded', show ? 'true' : 'false' );
		} );
	}

	/* ------------------------------------------------------------ تب زیردسته‌ها */
	function initSubTabs() {
		var tabs = document.querySelectorAll( '.fs-tabs .fs-chip' );
		var panes = document.querySelectorAll( '.fs-subcats__pane' );

		if ( ! tabs.length ) {
			return;
		}

		var linkName = document.querySelector( '.fs-subcats__linkname' );
		var link = document.querySelector( '.fs-subcats__link' );
		var mName = document.querySelector( '.fs-subcats__mname' );
		var mLink = document.querySelector( '.fs-subcats__mlink' );

		function sync( tab ) {
			var name = tab.getAttribute( 'data-name' );
			var href = tab.getAttribute( 'data-link' );

			if ( linkName ) {
				linkName.textContent = name;
			}

			if ( mName ) {
				mName.textContent = name;
			}

			if ( link ) {
				link.setAttribute( 'href', href );
			}

			if ( mLink ) {
				mLink.setAttribute( 'href', href );
			}
		}

		Array.prototype.forEach.call( tabs, function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				activate( tabs, panes, i, 'is-active', sync );
			} );
		} );
	}

	/* -------------------------------------------------------- تب پربازدیدترین‌ها */
	function initPopTabs() {
		var tabs = document.querySelectorAll( '.fs-pop__tabs .fs-darktab' );
		var panes = document.querySelectorAll( '.fs-pop__pane' );

		if ( ! tabs.length ) {
			return;
		}

		var active = document.querySelector( '.fs-pop__active' );
		var all = document.querySelector( '.fs-pop__all' );

		function sync( tab ) {
			if ( active ) {
				active.textContent = 'پربازدیدترین‌های ' + tab.getAttribute( 'data-name' );
			}

			if ( all ) {
				all.setAttribute( 'href', tab.getAttribute( 'data-link' ) );
			}
		}

		Array.prototype.forEach.call( tabs, function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				activate( tabs, panes, i, 'is-active', sync );
			} );
		} );
	}

	/* ----------------------------------------------- باز و بسته کردن همه دسته‌ها */
	function initCatsToggle() {
		var button = document.querySelector( '.fs-cats__toggle' );
		var grid = document.getElementById( 'fs-cats' );
		var count = document.querySelector( '.fs-cats__count' );

		if ( ! button || ! grid ) {
			return;
		}

		button.addEventListener( 'click', function () {
			var expanded = grid.classList.toggle( 'is-expanded' );

			button.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
			button.textContent = button.getAttribute( expanded ? 'data-expanded' : 'data-collapsed' );

			if ( count ) {
				count.textContent = count.getAttribute( expanded ? 'data-expanded' : 'data-collapsed' );
			}
		} );
	}

	/* ------------------------------------------------------- فلش‌های ریل افقی */
	function initRails() {
		var arrows = document.querySelectorAll( '.fs-arrow[data-rail]' );

		Array.prototype.forEach.call( arrows, function ( arrow ) {
			arrow.addEventListener( 'click', function () {
				var rail = document.getElementById( arrow.getAttribute( 'data-rail' ) );

				if ( ! rail ) {
					return;
				}

				var card = rail.firstElementChild;
				var step = card ? card.getBoundingClientRect().width + 16 : 240;

				// در چیدمان راست‌به‌چپ، حرکت به «بعدی» یعنی کاهش مقدار افقی.
				rail.scrollBy( {
					left: 'next' === arrow.getAttribute( 'data-dir' ) ? -step : step,
					behavior: 'smooth'
				} );
			} );
		} );
	}

	/* ------------------------------------------------------- تب‌های صفحه محصول */
	function initProductTabs() {
		var tabs = document.querySelectorAll( '.fs-ptabs__nav .fs-ptab' );

		if ( ! tabs.length ) {
			return;
		}

		var panes = document.querySelectorAll( '.fs-ptabs__pane' );

		Array.prototype.forEach.call( tabs, function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				activate( tabs, panes, i, 'is-active' );
			} );
		} );
	}

	/* ------------------------------------------------- گالری تصاویر محصول */
	function initGallery() {
		var thumbs = document.querySelectorAll( '.fs-product__thumb[data-full]' );
		var cover = document.querySelector( '.fs-product__cover img' );

		if ( ! thumbs.length || ! cover ) {
			return;
		}

		Array.prototype.forEach.call( thumbs, function ( thumb ) {
			thumb.addEventListener( 'click', function () {
				cover.setAttribute( 'src', thumb.getAttribute( 'data-full' ) );
				cover.removeAttribute( 'srcset' );

				Array.prototype.forEach.call( thumbs, function ( other ) {
					other.classList.toggle( 'is-active', other === thumb );
				} );
			} );
		} );
	}

	/* ---------------------------------------------------- پرسش‌های متداول */
	function initFaq() {
		var items = document.querySelectorAll( '.fs-faq__q[aria-controls]' );

		Array.prototype.forEach.call( items, function ( button ) {
			var panel = document.getElementById( button.getAttribute( 'aria-controls' ) );

			if ( ! panel ) {
				return;
			}

			button.addEventListener( 'click', function () {
				var show = panel.hasAttribute( 'hidden' );
				toggle( panel, show );
				button.setAttribute( 'aria-expanded', show ? 'true' : 'false' );
			} );
		} );
	}

	/* --------------------------------- متن بلند: محو شدن و «مشاهده بیشتر» */
	function initCollapse() {
		var blocks = document.querySelectorAll( '[data-collapse]' );

		Array.prototype.forEach.call( blocks, function ( block ) {
			var inner = block.querySelector( '.fs-collapse__inner' );
			var button = block.querySelector( '.fs-collapse__btn' );

			if ( ! inner || ! button ) {
				return;
			}

			var limit = parseInt( window.getComputedStyle( block ).getPropertyValue( '--fs-collapse-max' ), 10 ) || 190;

			// فقط وقتی متن واقعاً بلند است، جمع می‌شود.
			if ( inner.scrollHeight <= limit + 40 ) {
				return;
			}

			block.classList.add( 'is-clamped' );
			button.removeAttribute( 'hidden' );

			button.addEventListener( 'click', function () {
				var open = block.classList.toggle( 'is-open' );

				button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				button.firstChild.nodeValue = open
					? button.getAttribute( 'data-less' )
					: button.getAttribute( 'data-more' );
			} );
		} );
	}

	/* ------------------------------------------------------ پخش‌کننده صوت */
	function faNum( value ) {
		var fa = '۰۱۲۳۴۵۶۷۸۹';

		return String( value ).replace( /\d/g, function ( d ) {
			return fa[ d ];
		} );
	}

	function clock( seconds ) {
		if ( ! isFinite( seconds ) || seconds < 0 ) {
			seconds = 0;
		}

		var m = Math.floor( seconds / 60 );
		var s = Math.floor( seconds % 60 );

		return faNum( ( m < 10 ? '0' : '' ) + m ) + ':' + faNum( ( s < 10 ? '0' : '' ) + s );
	}

	function initAudio() {
		var players = document.querySelectorAll( '[data-audio]' );

		Array.prototype.forEach.call( players, function ( player ) {
			var audio = player.querySelector( 'audio' );
			var button = player.querySelector( '.fs-audio__play' );
			var playIcon = player.querySelector( '.fs-audio__icon--play' );
			var pauseIcon = player.querySelector( '.fs-audio__icon--pause' );
			var bars = player.querySelectorAll( '.fs-audio__wave span' );
			var current = player.querySelector( '[data-current]' );
			var duration = player.querySelector( '[data-duration]' );

			if ( ! audio || ! button ) {
				return;
			}

			function paint() {
				var ratio = audio.duration ? audio.currentTime / audio.duration : 0;
				var upTo = Math.round( ratio * bars.length );

				Array.prototype.forEach.call( bars, function ( bar, i ) {
					bar.classList.toggle( 'is-played', i < upTo );
				} );

				if ( current ) {
					current.textContent = clock( audio.currentTime );
				}
			}

			/*
			 * آیکون‌ها باید از خودِ عنصر صدا خبر بگیرند، نه از کلیک.
			 *
			 * پیش از این، کلیک بی‌قیدوشرط آیکون را به حالت «در حال پخش»
			 * می‌برد و نتیجه‌ی audio.play() هرگز بررسی نمی‌شد. این متد یک
			 * Promise برمی‌گرداند که می‌تواند reject شود — فایل پیدا نشود،
			 * نشانی http روی سایت https باشد (مرورگر بی‌صدا مسدود می‌کند)،
			 * یا فرمت پشتیبانی نشود. در همه‌ی این حالت‌ها ظاهر می‌گفت در حال
			 * پخش است ولی هیچ صدایی نبود و هیچ خطایی هم دیده نمی‌شد.
			 */
			var message = player.querySelector( '[data-audio-error]' );

			function paintState( playing ) {
				toggle( playIcon, ! playing );
				toggle( pauseIcon, playing );
			}

			function fail() {
				paintState( false );
				player.classList.add( 'is-broken' );
				toggle( message, true );
			}

			audio.addEventListener( 'play', function () {
				paintState( true );
			} );

			audio.addEventListener( 'pause', function () {
				paintState( false );
			} );

			audio.addEventListener( 'loadedmetadata', function () {
				if ( duration ) {
					duration.textContent = clock( audio.duration );
				}
			} );

			audio.addEventListener( 'timeupdate', paint );

			audio.addEventListener( 'ended', function () {
				paintState( false );
				paint();
			} );

			// خطای بارگذاری منبع؛ روی خود <audio> بابل نمی‌کند ولی اینجا
			// مستقیم به همان عنصر بسته شده است.
			audio.addEventListener( 'error', fail );

			button.addEventListener( 'click', function () {
				if ( ! audio.paused ) {
					audio.pause();

					return;
				}

				toggle( message, false );
				player.classList.remove( 'is-broken' );

				var started = audio.play();

				// مرورگرهای قدیمی چیزی برنمی‌گردانند؛ آن‌جا رویداد play
				// وضعیت را درست می‌کند.
				if ( started && 'function' === typeof started.catch ) {
					started.catch( fail );
				}
			} );

			// پرش به نقطه‌ای از موج.
			var wave = player.querySelector( '.fs-audio__wave' );

			if ( wave ) {
				wave.addEventListener( 'click', function ( e ) {
					if ( ! audio.duration ) {
						return;
					}

					var box = wave.getBoundingClientRect();
					// چیدمان راست‌به‌چپ: ابتدای موج سمت راست است.
					var ratio = ( box.right - e.clientX ) / box.width;

					audio.currentTime = Math.min( Math.max( ratio, 0 ), 1 ) * audio.duration;
					paint();
				} );
			}
		} );
	}

	/* ------------------------------------------------------ پاپ‌آپ ویدیو */
	function initVideoModal() {
		var modal = document.getElementById( 'fs-video-modal' );
		var triggers = document.querySelectorAll( '[data-video]' );

		if ( ! modal || ! triggers.length ) {
			return;
		}

		var stage = modal.querySelector( '.fs-modal__player' );

		function close() {
			toggle( modal, false );
			stage.innerHTML = '';
			document.body.style.overflow = '';
		}

		Array.prototype.forEach.call( triggers, function ( trigger ) {
			trigger.addEventListener( 'click', function () {
				var src = trigger.getAttribute( 'data-video' );
				var type = trigger.getAttribute( 'data-video-type' );

				if ( 'file' === type ) {
					stage.innerHTML = '<video controls autoplay playsinline src="' + encodeURI( src ) + '"></video>';
				} else {
					stage.innerHTML = '<iframe src="' + encodeURI( src ) +
						'" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
				}

				toggle( modal, true );
				document.body.style.overflow = 'hidden';
			} );
		} );

		Array.prototype.forEach.call( modal.querySelectorAll( '[data-close]' ), function ( el ) {
			el.addEventListener( 'click', close );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! modal.hasAttribute( 'hidden' ) ) {
				close();
			}
		} );
	}

	/* ------------------------------------------------------- علاقه‌مندی‌ها */
	function initWishlist() {
		// روی document تفویض می‌شود تا کارت‌های بارگذاری‌شده با اسکرول هم کار کنند.
		document.addEventListener( 'click', function ( e ) {
			var button = e.target.closest( '[data-wishlist]' );

			if ( ! button ) {
				return;
			}

			e.preventDefault();

			if ( button.dataset.busy ) {
				return;
			}

			button.dataset.busy = '1';

			var body = new URLSearchParams();
			body.append( 'action', 'fs_wishlist_toggle' );
			body.append( 'nonce', window.fsData ? fsData.nonce : '' );
			body.append( 'product_id', button.getAttribute( 'data-id' ) );

			fetch( window.fsData ? fsData.ajaxUrl : '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} ).then( function ( r ) {
				return r.json();
			} ).then( function ( res ) {
				if ( res.success ) {
					button.classList.toggle( 'is-on', res.data.saved );
					button.setAttribute( 'aria-pressed', res.data.saved ? 'true' : 'false' );
				} else if ( res.data && res.data.needsLogin ) {
					window.location.href = window.fsData && fsData.loginUrl ? fsData.loginUrl : '/my-account/';
				}
			} ).finally( function () {
				delete button.dataset.busy;
			} );
		} );
	}

	/* ------------------------------------------------- ورود و ثبت‌نام */
	function initProfileForm() {
		var forms = document.querySelectorAll( '[data-profile-form]' );

		Array.prototype.forEach.call( forms, function ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();

				var button = form.querySelector( '.fs-profile-form__submit' );
				var msg = form.querySelector( '[data-profile-msg]' );
				var data = new FormData( form );
				var body = new URLSearchParams();

				body.append( 'action', 'fs_account_update_profile' );
				body.append( 'nonce', window.fsData ? fsData.nonce : '' );

				data.forEach( function ( value, key ) {
					body.append( key, value );
				} );

				button.disabled = true;
				msg.hidden = true;

				fetch( window.fsData ? fsData.ajaxUrl : '/wp-admin/admin-ajax.php', {
					method: 'POST',
					credentials: 'same-origin',
					body: body
				} ).then( function ( r ) {
					return r.json();
				} ).then( function ( res ) {
					msg.hidden = false;
					msg.classList.toggle( 'is-ok', !! res.success );
					msg.classList.toggle( 'is-err', ! res.success );
					msg.textContent = ( res.data && res.data.message ) || ( res.success ? 'ذخیره شد.' : 'ذخیره نشد.' );

					if ( res.success ) {
						setTimeout( function () {
							window.location.reload();
						}, 700 );
					}
				} ).catch( function () {
					msg.hidden = false;
					msg.classList.add( 'is-err' );
					msg.textContent = 'ارتباط با سرور برقرار نشد.';
				} ).finally( function () {
					button.disabled = false;
				} );
			} );
		} );
	}

	/*
	 * توکن کپچا.
	 *
	 * v3 نامرئی است و برای هر اقدام یک توکن تازه می‌سازد (توکن‌ها دوبار مصرف
	 * نمی‌شوند و دو دقیقه بیشتر اعتبار ندارند، پس نمی‌شود یکی را نگه داشت).
	 * v2 و hCaptcha ویجت دارند و توکن را در فیلد پنهان خودشان می‌گذارند.
	 */
	function captchaToken() {
		var cfg = window.fsData && fsData.captcha;

		if ( ! cfg ) {
			return Promise.resolve( '' );
		}

		if ( 'recaptcha_v3' === cfg.provider ) {
			if ( ! window.grecaptcha || ! window.grecaptcha.execute ) {
				return Promise.resolve( '' );
			}

			return new Promise( function ( resolve ) {
				window.grecaptcha.ready( function () {
					window.grecaptcha.execute( cfg.sitekey, { action: 'login' } )
						.then( resolve )
						.catch( function () {
							resolve( '' );
						} );
				} );
			} );
		}

		var field = document.querySelector( '[name="' + cfg.field + '"]' );

		return Promise.resolve( field ? field.value : '' );
	}

	function post( action, payload ) {
		var body = new URLSearchParams();

		body.append( 'action', action );
		body.append( 'nonce', window.fsData ? fsData.nonce : '' );

		Object.keys( payload ).forEach( function ( key ) {
			body.append( key, payload[ key ] );
		} );

		return fetch( window.fsData ? fsData.ajaxUrl : '/wp-admin/admin-ajax.php', {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	/*
	 * حذف یک قلم از داخل صفحه‌ی پرداخت.
	 *
	 * از همان نقطه‌ی سبد خرید استفاده می‌کند، ولی برخلاف کشوی سبد که فقط یک
	 * تکه HTML را عوض می‌کند، اینجا کل صفحه بازخوانی می‌شود: مبلغ قابل پرداخت،
	 * لیست فایل‌ها و فرم درگاه همه به سبد وابسته‌اند و به‌روزکردن تکه‌تکه‌شان
	 * راهی است برای اینکه یکی‌شان از قلم بیفتد و کاربر مبلغ اشتباه ببیند.
	 */
	function initCheckoutRemove() {
		var list = document.querySelector( '.fs-checkout__main' );

		if ( ! list ) {
			return;
		}

		list.addEventListener( 'click', function ( e ) {
			var button = e.target.closest ? e.target.closest( '[data-checkout-remove]' ) : null;

			if ( ! button || button.disabled ) {
				return;
			}

			e.preventDefault();

			var row = button.closest( '.fs-citem' );

			button.disabled = true;

			if ( row ) {
				row.classList.add( 'is-removing' );
			}

			var body = new URLSearchParams();

			body.append( 'action', 'fs_cart_remove' );
			body.append( 'nonce', window.fsData ? fsData.cartNonce : '' );
			body.append( 'key', button.getAttribute( 'data-checkout-remove' ) );

			fetch( window.fsData ? fsData.ajaxUrl : '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} ).then( function ( r ) {
				return r.json();
			} ).then( function ( res ) {
				if ( ! res.success ) {
					throw new Error( 'failed' );
				}

				// سبد خالی شد: ماندن روی صفحه‌ی پرداختِ بی‌محصول بی‌معنی است.
				window.location.href = ( 0 === res.data.count && window.fsData && fsData.shopUrl )
					? fsData.shopUrl
					: window.location.href;
			} ).catch( function () {
				button.disabled = false;

				if ( row ) {
					row.classList.remove( 'is-removing' );
				}
			} );
		} );
	}

	/*
	 * حالت انتظار دکمه‌های دانلود.
	 *
	 * کلیک روی لینک دانلود از نظر مرورگر یک ناوبری معمولی است و هیچ رویدادی
	 * برای «شروع شد» نمی‌دهد؛ صفحه فقط بی‌حرکت می‌ماند. برای فایل‌های بزرگ که
	 * سرور باید اول کاملشان را بخواند، این سکوت ده‌ها ثانیه طول می‌کشد و کاربر
	 * صفحه را می‌بندد.
	 *
	 * راه استانداردِ فهمیدنِ شروع دانلود همین است: سرور همراه پاسخِ فایل یک
	 * کوکی با همان نشانه می‌فرستد و ما دنبالش می‌گردیم. تا وقتی پیدا نشده،
	 * دکمه «در حال آماده‌سازی» می‌ماند.
	 */
	function initDownloads() {
		var buttons = document.querySelectorAll( '[data-download]' );

		if ( ! buttons.length ) {
			return;
		}

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				var token = button.getAttribute( 'data-download' );

				if ( button.classList.contains( 'is-waiting' ) ) {
					return;
				}

				button.classList.add( 'is-waiting' );
				setBusy( button, true );

				var started = Date.now();
				var timer   = setInterval( function () {
					var done    = document.cookie.indexOf( 'fs_dl_' + token + '=' ) > -1;
					var expired = Date.now() - started > 120000;

					if ( ! done && ! expired ) {
						return;
					}

					clearInterval( timer );
					button.classList.remove( 'is-waiting' );
					setBusy( button, false );

					// کوکی مصرف شد؛ پاکش می‌کنیم تا کلیک بعدی روی همان دکمه
					// بلافاصله «تمام‌شده» به نظر نرسد.
					document.cookie = 'fs_dl_' + token + '=; Max-Age=0; path=/';

					if ( done ) {
						showHint( button );
					}
				}, 500 );
			} );
		} );

		function setBusy( button, busy ) {
			var idle = button.querySelector( '.fs-dlbtn__idle' );
			var wait = button.querySelector( '.fs-dlbtn__wait' );

			if ( idle ) {
				idle.hidden = busy;
			}

			if ( wait ) {
				wait.hidden = ! busy;
			}
		}

		function showHint( button ) {
			var row = button.closest( '.fs-ditem' ) || button.parentNode;

			if ( ! row || row.querySelector( '.fs-dlhint' ) ) {
				return;
			}

			var hint = document.createElement( 'div' );

			hint.className   = 'fs-dlhint';
			hint.textContent = 'دانلود شروع شد. اگر چیزی ذخیره نشد، پوشه‌ی دانلودهای مرورگر را ببینید.';
			row.appendChild( hint );

			setTimeout( function () {
				hint.remove();
			}, 8000 );
		}
	}

	function initAuth() {
		var root = document.querySelector( '[data-auth]' );

		if ( ! root ) {
			return;
		}

		var steps = root.querySelectorAll( '[data-step]' );
		var error = root.querySelector( '[data-error]' );
		var redirect = root.getAttribute( 'data-redirect' ) || '';
		var state = { phone: '', ticket: '', timer: null, otpAbort: null };

		function show( name ) {
			Array.prototype.forEach.call( steps, function ( step ) {
				toggle( step, step.getAttribute( 'data-step' ) === name );
			} );

			toggle( error, false );

			var first = root.querySelector( '[data-step="' + name + '"] input' );

			if ( first ) {
				first.focus();
			}
		}

		function fail( message ) {
			error.textContent = message || 'خطایی رخ داد. دوباره تلاش کنید.';
			toggle( error, true );
		}

		function busy( button, on ) {
			button.disabled = on;
			toggle( button.querySelector( '.fs-auth__spinner' ), on );
			toggle( button.querySelector( '.fs-auth__submit-text' ), ! on );
		}

		function value( step, field ) {
			var el = root.querySelector( '[data-step="' + step + '"] [data-field="' + field + '"]' );

			return el ? el.value.trim() : '';
		}

		function startTimer( seconds ) {
			var box = root.querySelector( '[data-timer]' );
			var out = root.querySelector( '[data-countdown]' );
			var resend = root.querySelector( '[data-resend]' );

			clearInterval( state.timer );
			toggle( box, true );
			toggle( resend, false );

			var left = seconds;

			function tick() {
				if ( left <= 0 ) {
					clearInterval( state.timer );
					toggle( box, false );
					toggle( resend, true );

					return;
				}

				out.textContent = clock( left );
				--left;
			}

			tick();
			state.timer = setInterval( tick, 1000 );
		}

		// جابه‌جایی بین گام‌ها.
		Array.prototype.forEach.call( root.querySelectorAll( '[data-goto]' ), function ( el ) {
			el.addEventListener( 'click', function () {
				show( el.getAttribute( 'data-goto' ) );
			} );
		} );

		// خانه‌های کد پیامکی.
		var otpBoxes = root.querySelectorAll( '[data-otp] input' );

		Array.prototype.forEach.call( otpBoxes, function ( box, i ) {
			box.addEventListener( 'input', function () {
				box.value = box.value.replace( /\D/g, '' ).slice( 0, 1 );

				if ( box.value && otpBoxes[ i + 1 ] ) {
					otpBoxes[ i + 1 ].focus();
				}
			} );

			box.addEventListener( 'keydown', function ( e ) {
				if ( 'Backspace' === e.key && ! box.value && otpBoxes[ i - 1 ] ) {
					otpBoxes[ i - 1 ].focus();
				}
			} );

			box.addEventListener( 'paste', function ( e ) {
				var text = ( e.clipboardData || window.clipboardData ).getData( 'text' ).replace( /\D/g, '' );

				if ( ! text ) {
					return;
				}

				e.preventDefault();
				fillOtp( text );
			} );
		} );

		function otpValue() {
			return Array.prototype.map.call( otpBoxes, function ( box ) {
				return box.value;
			} ).join( '' );
		}

		function fillOtp( digits ) {
			Array.prototype.forEach.call( otpBoxes, function ( box, k ) {
				box.value = digits[ k ] || '';
			} );

			otpBoxes[ Math.min( digits.length, otpBoxes.length ) - 1 ].focus();
		}

		/*
		 * پرکردن خودکار کد از روی پیامک (WebOTP).
		 *
		 * شرط‌های مرورگر سخت‌گیرانه است و همه باید با هم برقرار باشند: صفحه
		 * روی HTTPS، و متن پیامک با یک خط جدا به شکل «@دامنه #کد» تمام شود.
		 * اگر هر کدام نباشد، مرورگر بی‌صدا چیزی نمی‌دهد و کاربر مثل قبل دستی
		 * وارد می‌کند — پس این فقط یک راحتی اضافه است، نه مسیر اصلی.
		 *
		 * فعلاً کروم اندروید پشتیبانی می‌کند.
		 */
		function listenForSms() {
			if ( ! ( 'OTPCredential' in window ) || ! navigator.credentials ) {
				return;
			}

			// درخواست قبلی باید لغو شود، وگرنه «ارسال دوباره» دو شنونده‌ی
			// هم‌زمان می‌ساخت و مرورگر دومی را رد می‌کرد.
			if ( state.otpAbort ) {
				state.otpAbort.abort();
			}

			state.otpAbort = new AbortController();

			navigator.credentials.get( {
				otp: { transport: [ 'sms' ] },
				signal: state.otpAbort.signal
			} ).then( function ( otp ) {
				if ( ! otp || ! otp.code ) {
					return;
				}

				var digits = String( otp.code ).replace( /\D/g, '' );

				if ( ! digits ) {
					return;
				}

				fillOtp( digits );

				// کد کامل است و از خود پیامک آمده؛ دیگر دلیلی برای منتظرماندن
				// نیست. تایید را خودمان می‌زنیم.
				if ( digits.length === otpBoxes.length ) {
					var submit = root.querySelector( '[data-action="otp"]' );

					if ( submit ) {
						submit.click();
					}
				}
			} ).catch( function () {
				// کاربر رد کرد، مهلت تمام شد، یا مرورگر پشتیبانی نکرد.
			} );
		}

		function done( data ) {
			window.location.href = data.redirect || window.location.href;
		}

		var actions = {
			entry: function ( button ) {
				var login = value( 'entry', 'login' );

				if ( ! login ) {
					return fail( 'شماره موبایل را وارد کنید.' );
				}

				busy( button, true );

				// توکن کپچا تازه گرفته می‌شود، نه در بارگذاری صفحه: توکن v3
				// دو دقیقه بیشتر اعتبار ندارد و کاربری که فرم را باز گذاشته
				// با توکن کهنه رد می‌شد.
				captchaToken().then( function ( token ) {
					return post( 'fs_auth_entry', { login: login, redirect: redirect, captcha_token: token } );
				} ).then( function ( res ) {
					busy( button, false );

					if ( ! res.success ) {
						return fail( res.data && res.data.message );
					}

					if ( 'password' === res.data.step ) {
						root.querySelector( '[data-step="password"] [data-field="login"]' ).value = login;
						show( 'password' );

						if ( res.data.notice ) {
							fail( res.data.notice );
						}

						return;
					}

					state.phone = res.data.phone;
					root.querySelector( '[data-phone-label]' ).textContent = 'کد به ' + faNum( res.data.phone ) + ' پیامک شد';
					show( 'otp' );
					startTimer( res.data.resendIn );
					listenForSms();
				} ).catch( function () {
					busy( button, false );
					fail();
				} );
			},

			resend: function ( button ) {
				button.disabled = true;

				captchaToken().then( function ( token ) {
					return post( 'fs_auth_resend', { phone: state.phone, captcha_token: token } );
				} ).then( function ( res ) {
					button.disabled = false;

					if ( ! res.success ) {
						return fail( res.data && res.data.message );
					}

					startTimer( res.data.resendIn );
				} ).catch( function () {
					button.disabled = false;
					fail();
				} );
			},

			otp: function ( button ) {
				var code = otpValue();

				if ( otpBoxes.length !== code.length ) {
					return fail( 'کد ' + faNum( otpBoxes.length ) + ' رقمی را کامل وارد کنید.' );
				}

				busy( button, true );

				post( 'fs_auth_otp', { phone: state.phone, code: code, redirect: redirect } ).then( function ( res ) {
					busy( button, false );

					if ( ! res.success ) {
						return fail( res.data && res.data.message );
					}

					if ( 'profile' === res.data.step ) {
						state.ticket = res.data.ticket;
						clearInterval( state.timer );
						show( 'profile' );

						return;
					}

					done( res.data );
				} ).catch( function () {
					busy( button, false );
					fail();
				} );
			},

			profile: function ( button ) {
				var first = value( 'profile', 'first_name' );

				if ( ! first ) {
					return fail( 'نام را وارد کنید.' );
				}

				busy( button, true );

				post( 'fs_auth_profile', {
					ticket: state.ticket,
					first_name: first,
					last_name: value( 'profile', 'last_name' ),
					email: value( 'profile', 'email' ),
					redirect: redirect
				} ).then( function ( res ) {
					busy( button, false );

					if ( ! res.success ) {
						return fail( res.data && res.data.message );
					}

					done( res.data );
				} ).catch( function () {
					busy( button, false );
					fail();
				} );
			},

			password: function ( button ) {
				busy( button, true );

				post( 'fs_auth_password', {
					login: value( 'password', 'login' ),
					password: value( 'password', 'password' ),
					redirect: redirect
				} ).then( function ( res ) {
					busy( button, false );

					if ( ! res.success ) {
						return fail( res.data && res.data.message );
					}

					done( res.data );
				} ).catch( function () {
					busy( button, false );
					fail();
				} );
			},

			register: function ( button ) {
				busy( button, true );

				post( 'fs_auth_register', {
					phone: value( 'register', 'phone' ),
					first_name: value( 'register', 'first_name' ),
					last_name: value( 'register', 'last_name' ),
					email: value( 'register', 'email' ),
					password: value( 'register', 'password' ),
					redirect: redirect
				} ).then( function ( res ) {
					busy( button, false );

					if ( ! res.success ) {
						return fail( res.data && res.data.message );
					}

					done( res.data );
				} ).catch( function () {
					busy( button, false );
					fail();
				} );
			}
		};

		Array.prototype.forEach.call( root.querySelectorAll( '[data-action]' ), function ( button ) {
			button.addEventListener( 'click', function () {
				var name = button.getAttribute( 'data-action' );

				if ( actions[ name ] ) {
					actions[ name ]( button );
				}
			} );
		} );

		root.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' !== e.key ) {
				return;
			}

			var step = e.target.closest( '[data-step]' );
			var button = step && step.querySelector( '[data-action]' );

			if ( button ) {
				e.preventDefault();
				button.click();
			}
		} );
	}

	/* ------------------------------------------ بارگذاری با اسکرول */
	function initInfinite() {
		var box = document.querySelector( '[data-infinite]' );

		if ( ! box ) {
			return;
		}

		var grid = document.getElementById( box.getAttribute( 'data-target' ) );
		var button = box.querySelector( '.fs-infinite__btn' );
		var spinner = box.querySelector( '.fs-infinite__spinner' );
		var loading = false;

		function load() {
			var next = box.getAttribute( 'data-next' );

			if ( loading || ! next || ! grid ) {
				return;
			}

			loading = true;
			box.classList.add( 'is-loading' );
			toggle( button, false );

			fetch( next, { credentials: 'same-origin' } )
				.then( function ( r ) {
					return r.text();
				} )
				.then( function ( html ) {
					var doc = new DOMParser().parseFromString( html, 'text/html' );
					var cards = doc.querySelectorAll( '#' + grid.id + ' > *' );

					Array.prototype.forEach.call( cards, function ( card ) {
						grid.appendChild( document.importNode( card, true ) );
					} );

					var more = doc.querySelector( '[data-infinite]' );
					var url = more ? more.getAttribute( 'data-next' ) : '';

					if ( url ) {
						box.setAttribute( 'data-next', url );

						if ( button ) {
							button.setAttribute( 'href', url );
						}

						toggle( button, true );
					} else {
						box.remove();
					}
				} )
				.catch( function () {
					toggle( button, true );
				} )
				.finally( function () {
					loading = false;
					box.classList.remove( 'is-loading' );

					if ( spinner ) {
						spinner.hidden = true;
					}
				} );
		}

		if ( button ) {
			// دکمه حالا یک <a> با نشانی واقعی صفحه‌ی بعد است تا خزنده بتواند
			// دنبالش کند؛ برای کاربر باید همان بارگذاری درجا بماند.
			button.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				load();
			} );
		}

		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( entries ) {
				if ( entries[0].isIntersecting ) {
					load();
				}
			}, { rootMargin: '600px 0px' } ).observe( box );
		}
	}


	/* ------------------------------------------------------- سبد خرید کشویی */
	function initCart() {
		var drawer = document.getElementById( 'fs-cart' );

		if ( ! drawer || 'undefined' === typeof window.fsData ) {
			return;
		}

		var body = drawer.querySelector( '[data-cart-body]' );
		var toast = drawer.querySelector( '[data-cart-toast]' );
		var openers = document.querySelectorAll( '[data-cart-open]' );
		var busy = false;
		var toastTimer = null;

		function open( state ) {
			toggle( drawer, state );
			drawer.classList.toggle( 'is-open', state );
			document.body.classList.toggle( 'fs-noscroll', state );

			Array.prototype.forEach.call( openers, function ( el ) {
				el.setAttribute( 'aria-expanded', state ? 'true' : 'false' );
			} );
		}

		function say( message, isError ) {
			if ( ! toast || ! message ) {
				return;
			}

			toast.textContent = message;
			toast.classList.toggle( 'is-error', !! isError );
			toggle( toast, true );

			window.clearTimeout( toastTimer );
			toastTimer = window.setTimeout( function () {
				toggle( toast, false );
			}, 3200 );
		}

		function paint( data ) {
			if ( ! data ) {
				return;
			}

			if ( body && 'string' === typeof data.body ) {
				body.innerHTML = data.body;
			}

			Array.prototype.forEach.call( document.querySelectorAll( '[data-cart-count]' ), function ( el ) {
				el.textContent = faNum( data.count );
				toggle( el, data.count > 0 );
			} );
		}

		function request( action, payload ) {
			if ( busy ) {
				return Promise.resolve( null );
			}

			busy = true;
			drawer.classList.add( 'is-busy' );

			var form = new FormData();
			form.append( 'action', action );
			form.append( 'nonce', window.fsData.cartNonce );

			Object.keys( payload || {} ).forEach( function ( key ) {
				form.append( key, payload[ key ] );
			} );

			return window.fetch( window.fsData.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: form
			} ).then( function ( r ) {
				return r.json();
			} ).then( function ( res ) {
				var data = res && res.data ? res.data : null;

				paint( data );
				say( data && data.message, ! ( res && res.success ) );

				return data;
			} ).catch( function () {
				say( 'ارتباط با سرور برقرار نشد. دوباره تلاش کنید.', true );

				return null;
			} ).finally( function () {
				busy = false;
				drawer.classList.remove( 'is-busy' );
			} );
		}

		/* ----------------------------------- برگه‌ی انتخاب پس از افزودن */
		var sheet = document.getElementById( 'fs-buysheet' );
		var sheetName = sheet && sheet.querySelector( '[data-sheet-name]' );
		var pending = null; // { button, thumb } تا لحظه‌ی تصمیم کاربر.

		function sheetOpen( state ) {
			if ( ! sheet ) {
				return;
			}

			toggle( sheet, state );
			sheet.classList.toggle( 'is-open', state );
			document.body.classList.toggle( 'fs-noscroll', state );

			if ( state ) {
				var first = sheet.querySelector( '[data-sheet-checkout]' );

				if ( first ) {
					first.focus();
				}
			}
		}

		/*
		 * پرواز فایل به سبد.
		 *
		 * یک تصویر کوچک از همان جایی که کاربر کلیک کرده ساخته می‌شود و تا
		 * آیکون سبد در سربرگ حرکت می‌کند. هدفش این است که کاربر ببیند فایلش
		 * کجا رفت — بدون آن، شمارنده‌ی سربرگ بی‌مقدمه عوض می‌شود.
		 *
		 * روی position: fixed کار می‌کند تا اسکرول صفحه مسیر را خراب نکند، و
		 * اگر کاربر «کاهش حرکت» را روشن کرده باشد اصلاً اجرا نمی‌شود.
		 */
		function flyToCart( from, thumbUrl ) {
			var target = document.querySelector( '.fs-btn-cart' );
			var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

			if ( ! from || ! target || reduce ) {
				return Promise.resolve();
			}

            var a = from.getBoundingClientRect();
            var b = target.getBoundingClientRect();
            var chip = document.createElement( 'span' );

			chip.className = 'fs-flychip';
			chip.style.top = ( a.top + a.height / 2 - 26 ) + 'px';
			chip.style.left = ( a.left + a.width / 2 - 26 ) + 'px';

			if ( thumbUrl ) {
				var img = document.createElement( 'img' );
				img.src = thumbUrl;
				img.alt = '';
				chip.appendChild( img );
			}

			document.body.appendChild( chip );

			var dx = ( b.left + b.width / 2 ) - ( a.left + a.width / 2 );
			var dy = ( b.top + b.height / 2 ) - ( a.top + a.height / 2 );

			return new Promise( function ( done ) {
				// یک فریم صبر تا مرورگر حالت اولیه را ثبت کند، وگرنه گذار رخ نمی‌دهد.
				window.requestAnimationFrame( function () {
					chip.classList.add( 'is-flying' );
					chip.style.transform = 'translate(' + dx + 'px,' + dy + 'px) scale(.25)';
					chip.style.opacity = '.2';
				} );

				window.setTimeout( function () {
					chip.remove();
					target.classList.add( 'is-bumped' );
					window.setTimeout( function () {
						target.classList.remove( 'is-bumped' );
					}, 420 );
					done();
				}, 640 );
			} );
		}

		if ( sheet ) {
			sheet.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '[data-sheet-checkout]' ) ) {
					window.location.href = ( pending && pending.checkoutUrl ) || window.fsData.checkoutUrl;

					return;
				}

				if ( e.target.closest( '[data-sheet-more]' ) ) {
					sheetOpen( false );

					// انیمیشن اول، بعد کشو: اگر کشو زودتر باز شود، پرواز دیده نمی‌شود.
					flyToCart( pending && pending.button, pending && pending.thumb ).then( function () {
						open( true );
					} );

					return;
				}

				if ( e.target.closest( '[data-sheet-close]' ) ) {
					sheetOpen( false );
				}
			} );
		}

		// فقط مسیر شبکه محدود می‌شود؛ باز و بسته‌کردن کشو باید آنی بماند.
		var buyThrottled = throttle( function ( button, productId ) {
			button.classList.add( 'is-loading' );

			request( 'fs_cart_add', { product_id: productId } ).then( function ( data ) {
				if ( ! data ) {
					return;
				}

				pending = {
					button: button,
					thumb: data.thumb || '',
					checkoutUrl: data.checkoutUrl || ''
				};

				if ( sheetName ) {
					sheetName.textContent = data.title || '';
				}

				if ( sheet ) {
					sheetOpen( true );
				} else {
					// بدون برگه (نسخه‌ی قدیمی مارک‌آپ) دست‌کم کشو را نشان بده.
					open( true );
				}
			} ).finally( function () {
				button.classList.remove( 'is-loading' );
			} );
		}, 400 );

		Array.prototype.forEach.call( openers, function ( el ) {
			el.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				open( true );
			} );
		} );

		document.addEventListener( 'click', function ( e ) {
			// بستن کشو.
			if ( e.target.closest && e.target.closest( '[data-cart-close]' ) ) {
				e.preventDefault();
				open( false );

				return;
			}

			// حذف یک قلم.
			var del = e.target.closest ? e.target.closest( '[data-cart-remove]' ) : null;

			if ( del ) {
				e.preventDefault();
				request( 'fs_cart_remove', { key: del.getAttribute( 'data-cart-remove' ) } );

				return;
			}

			// افزودن به سبد از روی کارت‌ها یا نوار پایین موبایل.
			var add = e.target.closest ? e.target.closest( '.add_to_cart_button, .fs-mobar__btn' ) : null;

			if ( ! add || add.classList.contains( 'single_add_to_cart_button' ) ) {
				return;
			}

			var id = add.getAttribute( 'data-product_id' );

			if ( ! id ) {
				return;
			}

			e.preventDefault();
			buyThrottled( add, id );
		} );

		// دکمه‌ی خرید صفحه‌ی محصول — فرم استاندارد ووکامرس.
		Array.prototype.forEach.call( document.querySelectorAll( 'form.cart' ), function ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				var button = form.querySelector( '.single_add_to_cart_button' );
				var id = form.querySelector( '[name="add-to-cart"]' );
				var productId = id ? id.value : ( button ? button.value : '' );

				// محصولات متغیر و هرچیزی که شناسه‌ی ساده ندارد، مسیر عادی ووکامرس را می‌روند.
				if ( ! productId || form.querySelector( '.variations' ) ) {
					return;
				}

				e.preventDefault();

				buyThrottled( button || form, productId );
			} );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' !== e.key ) {
				return;
			}

			if ( sheet && ! sheet.hasAttribute( 'hidden' ) ) {
				sheetOpen( false );

				return;
			}

			if ( ! drawer.hasAttribute( 'hidden' ) ) {
				open( false );
			}
		} );

		// بازگشت با دکمه‌ی back مرورگر: صفحه از کش می‌آید و شمارنده کهنه است.
		window.addEventListener( 'pageshow', function ( e ) {
			if ( e.persisted ) {
				request( 'fs_cart_fragment', {} );
			}
		} );
	}

	/* ------------------------------------------- تب‌های محصولات پاورقی */
	function initFooterTabs() {
		var wrap = document.querySelector( '.fs-fprods' );

		if ( ! wrap ) {
			return;
		}

		var tabs = wrap.querySelectorAll( '.fs-fprods__tab' );
		var panes = wrap.querySelectorAll( '.fs-fprods__pane' );

		if ( tabs.length < 2 ) {
			return;
		}

		Array.prototype.forEach.call( tabs, function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				activate( tabs, panes, i, 'is-active' );
			} );
		} );
	}

	function init() {
		initAuth();
		initFooterTabs();
		initCart();
		initCheckoutRemove();
		initDownloads();
		initWishlist();
		initProfileForm();
		initInfinite();
		initAudio();
		initVideoModal();
		initMega();
		initMobileMenu();
		initDrawer();
		initSubTabs();
		initPopTabs();
		initCatsToggle();
		initRails();
		initProductTabs();
		initGallery();
		initFaq();
		initCollapse();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
