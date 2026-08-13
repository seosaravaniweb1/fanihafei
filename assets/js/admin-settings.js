/**
 * تعامل‌های صفحه «تنظیمات قالب»: ردیف‌های تکرارشونده و انتخابگر تصویر.
 *
 * همه‌ی ردیف‌ها فیلد فرم معمولی‌اند و با خود فرم ذخیره می‌شوند؛ اینجا فقط
 * افزودن، حذف و انتخاب تصویر انجام می‌شود.
 */
( function () {
	'use strict';

	var frame = null;

	/**
	 * افزودن یک ردیف تازه از روی قالب.
	 *
	 * @param {Element} button دکمه‌ی افزودن.
	 */
	function addRow( button ) {
		var wrap = document.getElementById( button.getAttribute( 'data-fs-add' ) );
		var tpl = document.getElementById( button.getAttribute( 'data-fs-tpl' ) );

		if ( ! wrap || ! tpl ) {
			return;
		}

		wrap.appendChild( tpl.content.cloneNode( true ) );
	}

	/**
	 * باز کردن کتابخانه‌ی رسانه برای یک ردیف تصویری.
	 *
	 * @param {Element} row ردیف.
	 */
	function pickImage( row ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		var input = row.querySelector( '[data-fs-img-id]' );
		var img = row.querySelector( '.fs-row__thumb img' );
		var placeholder = row.querySelector( '.fs-row__ph' );

		if ( ! frame ) {
			frame = window.wp.media( {
				title: 'انتخاب تصویر',
				button: { text: 'استفاده از این تصویر' },
				library: { type: 'image' },
				multiple: false
			} );
		}

		// هر بار شنونده‌ی قبلی برداشته می‌شود تا انتخاب روی ردیف درست بنشیند.
		frame.off( 'select' );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var url = attachment.sizes && attachment.sizes.medium
				? attachment.sizes.medium.url
				: attachment.url;

			if ( input ) {
				input.value = attachment.id;
			}

			if ( img ) {
				img.setAttribute( 'src', url );
				img.removeAttribute( 'hidden' );
			}

			if ( placeholder ) {
				placeholder.setAttribute( 'hidden', '' );
			}
		} );

		frame.open();
	}

	document.addEventListener( 'click', function ( e ) {
		var add = e.target.closest( '[data-fs-add]' );

		if ( add ) {
			e.preventDefault();
			addRow( add );

			return;
		}

		var del = e.target.closest( '[data-fs-del]' );

		if ( del ) {
			e.preventDefault();

			var row = del.closest( '[data-fs-row]' );

			if ( row ) {
				row.remove();
			}

			return;
		}

		var pick = e.target.closest( '[data-fs-pick]' );

		if ( pick ) {
			e.preventDefault();

			var target = pick.closest( '[data-fs-row]' );

			if ( target ) {
				pickImage( target );
			}
		}
	} );
}() );
