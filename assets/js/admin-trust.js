/**
 * صفحه «نمادها و بانک‌ها» در پیشخوان: انتخاب تصویر، افزودن/حذف ردیف و ذخیره با اجاکس.
 */
( function ( $ ) {
	'use strict';

	var frame = null;

	/**
	 * باز کردن کتابخانه رسانه و نشاندن تصویر در ردیف.
	 *
	 * @param {jQuery} row ردیف.
	 */
	function pickImage( row ) {
		if ( frame ) {
			frame.off( 'select' );
		}

		frame = wp.media( {
			title: 'انتخاب تصویر',
			button: { text: 'استفاده از این تصویر' },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;

			row.find( '.fs-trow__id' ).val( att.id );
			row.find( '.fs-trow__img img' ).attr( 'src', url ).prop( 'hidden', false );
			row.find( '.fs-trow__ph' ).prop( 'hidden', true );
		} );

		frame.open();
	}

	$( document ).on( 'click', '.fs-trow__pick', function () {
		pickImage( $( this ).closest( '.fs-trow' ) );
	} );

	$( document ).on( 'click', '.fs-trow__del', function () {
		$( this ).closest( '.fs-trow' ).remove();
	} );

	$( document ).on( 'click', '.fs-add', function () {
		var group = $( this ).data( 'group' );
		var target = $( '#' + $( this ).data( 'target' ) );
		var tpl = document.getElementById( 'fs-row-' + group );

		if ( ! tpl ) {
			return;
		}

		target.append( document.importNode( tpl.content, true ) );
	} );

	/**
	 * جمع‌آوری ردیف‌های یک گروه.
	 *
	 * @param {string} id شناسه ظرف.
	 * @return {Array} ردیف‌ها.
	 */
	function collect( id ) {
		var out = [];

		$( '#' + id ).find( '.fs-trow' ).each( function () {
			var row = $( this );
			var attachment = parseInt( row.find( '.fs-trow__id' ).val(), 10 );

			if ( ! attachment ) {
				return;
			}

			out.push( {
				id: attachment,
				label: row.find( '.fs-trow__label' ).val() || '',
				link: row.find( '.fs-trow__link' ).val() || ''
			} );
		} );

		return out;
	}

	$( document ).on( 'click', '#fs-save', function () {
		var button = $( this );
		var msg = $( '#fs-save-msg' );

		button.prop( 'disabled', true );
		msg.removeClass( 'ok err' ).text( 'در حال ذخیره…' );

		$.post( fsTrust.ajaxUrl, {
			action: 'fs_trust_save',
			nonce: fsTrust.nonce,
			payload: JSON.stringify( {
				badges: collect( 'fs-badges' ),
				banks: collect( 'fs-banks' ),
				caption: $( '#fs-caption' ).val() || ''
			} )
		} ).done( function ( res ) {
			if ( res && res.success ) {
				msg.addClass( 'ok' ).text( res.data.message );
			} else {
				msg.addClass( 'err' ).text( ( res && res.data && res.data.message ) || 'ذخیره نشد.' );
			}
		} ).fail( function () {
			msg.addClass( 'err' ).text( 'ارتباط با سرور برقرار نشد.' );
		} ).always( function () {
			button.prop( 'disabled', false );
		} );
	} );
}( jQuery ) );
