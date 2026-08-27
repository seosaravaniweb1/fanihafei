/**
 * پنل تنظیمات پاورقی.
 *
 * سه کار: پیش‌نمایش زنده‌ی آیکون کارت‌های تماس، جست‌وجوی اجاکسی محصول برای
 * ستون پربازدیدترین‌ها، و بارگذاری تصویر بانک‌ها با کتابخانه‌ی رسانه‌ی وردپرس.
 * در پایان همه‌ی داده‌ها یک‌جا و به‌صورت JSON ذخیره می‌شوند.
 */
( function ( $ ) {
	'use strict';

	if ( 'undefined' === typeof window.fsFooter ) {
		return;
	}

	var cfg = window.fsFooter;

	/* ------------------------------------------------ کارت‌های تماس */
	var contactWrap = document.getElementById( 'fs-contact-rows' );
	var contactTpl = document.getElementById( 'fs-contact-tpl' );
	var contactAdd = document.getElementById( 'fs-contact-add' );

	function paintIcon( row ) {
		var select = row.querySelector( '[data-icon-select]' );
		var preview = row.querySelector( '[data-icon-preview]' );

		if ( select && preview && cfg.icons[ select.value ] ) {
			preview.innerHTML = cfg.icons[ select.value ];
		}
	}

	if ( contactWrap && contactTpl && contactAdd ) {
		contactAdd.addEventListener( 'click', function () {
			contactWrap.appendChild( contactTpl.content.cloneNode( true ) );
		} );

		contactWrap.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.hasAttribute( 'data-icon-select' ) ) {
				paintIcon( e.target.closest( '[data-contact-row]' ) );
			}
		} );

		contactWrap.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.classList.contains( 'fs-frow__del' ) ) {
				e.target.closest( '[data-contact-row]' ).remove();
			}
		} );
	}

	/* --------------------------------------------- جست‌وجوی محصول */
	var searchInput = document.getElementById( 'fs-product-search' );
	var results = document.getElementById( 'fs-product-results' );
	var chips = document.getElementById( 'fs-popular-chips' );
	var searchTimer = null;

	function chosenIds() {
		if ( ! chips ) {
			return [];
		}

		return Array.prototype.map.call(
			chips.querySelectorAll( '[data-product-id]' ),
			function ( el ) {
				return parseInt( el.getAttribute( 'data-product-id' ), 10 );
			}
		);
	}

	function addChip( item ) {
		if ( ! chips || chosenIds().indexOf( item.id ) !== -1 ) {
			return;
		}

		var chip = document.createElement( 'div' );
		chip.className = 'fs-fchip';
		chip.setAttribute( 'data-product-id', item.id );

		var html = '';

		if ( item.thumb ) {
			html += '<img src="" alt="">';
		}

		html += '<span class="fs-fchip__title"></span><button type="button" class="fs-fchip__del" aria-label="حذف">&times;</button>';
		chip.innerHTML = html;

		if ( item.thumb ) {
			chip.querySelector( 'img' ).src = item.thumb;
		}

		// متن از سرور می‌آید؛ با textContent می‌گذاریم تا هیچ HTML‌ای تفسیر نشود.
		chip.querySelector( '.fs-fchip__title' ).textContent = item.title;

		chips.appendChild( chip );
	}

	function renderResults( items ) {
		if ( ! results ) {
			return;
		}

		results.innerHTML = '';

		if ( ! items.length ) {
			results.innerHTML = '<div class="fs-fsearch__empty">محصولی پیدا نشد.</div>';
			results.hidden = false;

			return;
		}

		items.forEach( function ( item ) {
			var row = document.createElement( 'div' );
			row.className = 'fs-fsearch__item';

			if ( item.thumb ) {
				var img = document.createElement( 'img' );
				img.src = item.thumb;
				row.appendChild( img );
			}

			var span = document.createElement( 'span' );
			span.textContent = item.title;
			row.appendChild( span );

			row.addEventListener( 'click', function () {
				addChip( item );
				results.hidden = true;
				searchInput.value = '';
			} );

			results.appendChild( row );
		} );

		results.hidden = false;
	}

	if ( searchInput ) {
		// تایپ هر حرف یک درخواست نزند؛ فقط وقتی کاربر مکث کرد.
		searchInput.addEventListener( 'input', function () {
			window.clearTimeout( searchTimer );

			var term = searchInput.value.trim();

			if ( term.length < 2 ) {
				results.hidden = true;

				return;
			}

			searchTimer = window.setTimeout( function () {
				$.post( cfg.ajaxUrl, {
					action: 'fs_search_products',
					nonce: cfg.nonce,
					term: term
				} ).done( function ( res ) {
					if ( res && res.success ) {
						renderResults( res.data.items || [] );
					}
				} );
			}, 300 );
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( results && ! results.contains( e.target ) && e.target !== searchInput ) {
				results.hidden = true;
			}
		} );
	}

	if ( chips ) {
		chips.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.classList.contains( 'fs-fchip__del' ) ) {
				e.target.closest( '.fs-fchip' ).remove();
			}
		} );
	}

	/* ------------------------------------------- تصاویر بانک‌ها */
	var bankWrap = document.getElementById( 'fs-bank-rows' );
	var bankAdd = document.getElementById( 'fs-bank-add' );
	var frame = null;

	if ( bankAdd && bankWrap ) {
		bankAdd.addEventListener( 'click', function ( e ) {
			e.preventDefault();

			if ( ! frame ) {
				frame = wp.media( {
					title: 'انتخاب تصویر بانک',
					library: { type: 'image' },
					button: { text: 'افزودن' },
					multiple: true
				} );

				frame.on( 'select', function () {
					frame.state().get( 'selection' ).each( function ( item ) {
						var data = item.toJSON();
						var url = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url;

						var box = document.createElement( 'div' );
						box.className = 'fs-fbank';
						box.setAttribute( 'data-bank-id', data.id );
						box.innerHTML = '<img alt=""><button type="button" class="fs-fbank__del" aria-label="حذف">&times;</button>';
						box.querySelector( 'img' ).src = url;

						bankWrap.appendChild( box );
					} );
				} );
			}

			frame.open();
		} );

		bankWrap.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.classList.contains( 'fs-fbank__del' ) ) {
				e.target.closest( '.fs-fbank' ).remove();
			}
		} );
	}

	/* ------------------------------------------------------ ذخیره */
	var saveBtn = document.getElementById( 'fs-footer-save' );
	var msg = document.getElementById( 'fs-footer-msg' );

	function val( id ) {
		var el = document.getElementById( id );

		return el ? el.value : '';
	}

	function collect() {
		var contacts = [];

		if ( contactWrap ) {
			Array.prototype.forEach.call(
				contactWrap.querySelectorAll( '[data-contact-row]' ),
				function ( row ) {
					contacts.push( {
						icon: row.querySelector( '[data-icon-select]' ).value,
						title: row.querySelector( '.fs-frow__title' ).value,
						value: row.querySelector( '.fs-frow__value' ).value,
						link: row.querySelector( '.fs-frow__link' ).value
					} );
				}
			);
		}

		var banks = [];

		if ( bankWrap ) {
			Array.prototype.forEach.call(
				bankWrap.querySelectorAll( '[data-bank-id]' ),
				function ( el ) {
					banks.push( parseInt( el.getAttribute( 'data-bank-id' ), 10 ) );
				}
			);
		}

		return {
			desc: val( 'fs-footer-desc' ),
			contacts: contacts,
			newest_title: val( 'fs-newest-title' ),
			newest_count: val( 'fs-newest-count' ),
			popular_title: val( 'fs-popular-title' ),
			popular: chosenIds(),
			trust_title: val( 'fs-trust-title' ),
			trust_html: val( 'fs-trust-html' ),
			banks: banks,
			banks_note: val( 'fs-banks-note' )
		};
	}

	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', function () {
			saveBtn.disabled = true;
			msg.className = 'fs-footer-msg';
			msg.textContent = 'در حال ذخیره…';

			$.post( cfg.ajaxUrl, {
				action: 'fs_footer_save',
				nonce: cfg.nonce,
				payload: JSON.stringify( collect() )
			} ).done( function ( res ) {
				var ok = res && res.success;
				msg.className = 'fs-footer-msg ' + ( ok ? 'is-ok' : 'is-err' );
				msg.textContent = ok ? res.data.message : 'ذخیره نشد.';
			} ).fail( function () {
				msg.className = 'fs-footer-msg is-err';
				msg.textContent = 'ارتباط با سرور برقرار نشد.';
			} ).always( function () {
				saveBtn.disabled = false;
			} );
		} );
	}
}( jQuery ) );
