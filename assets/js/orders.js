/* global jQuery, Swal, aceTheCatchOrders */

( function ( $ ) {
	'use strict';

	const getSwal = () => {
		if ( window.Swal && typeof window.Swal.fire === 'function' ) {
			return window.Swal;
		}
		if ( typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function' ) {
			return Swal;
		}
		return null;
	};

	const upper = ( value ) => String( value || '' ).trim().toUpperCase();

	const getText = ( key, fallback ) => {
		return String( aceTheCatchOrders?.[ key ] || fallback || '' );
	};

	const initRefundButtons = () => {
		$( document ).on( 'click', '.cta-refund-button[data-cta-refund-url]', function ( e ) {
			e.preventDefault();

			const url = String( $( this ).data( 'cta-refund-url' ) || '' );
			if ( ! url ) {
				return;
			}

			const orderNumber = String( $( this ).data( 'cta-order-number' ) || '' );
			const amountDisplay = String( $( this ).data( 'cta-order-amount-display' ) || '' );

			const swal = getSwal();
			if ( ! swal ) {
				window.location.href = url;
				return;
			}

			const confirmToken = upper( getText( 'confirmToken', 'REFUND' ) );
			const title = getText( 'title', 'Refund order' );
			const confirmButton = getText( 'confirmButton', 'Refund' );
			const cancelButton = getText( 'cancelButton', 'Cancel' );
			const inputPlaceholder = getText( 'inputPlaceholder', `Type ${ confirmToken } to confirm` );
			const validationMessage = getText( 'validationMessage', `Please type ${ confirmToken } to confirm.` );

			const summaryLines = [];
			if ( orderNumber ) {
				summaryLines.push( `<strong>Order #${ orderNumber }</strong>` );
			}
			if ( amountDisplay ) {
				summaryLines.push( `Amount: <strong>${ amountDisplay }</strong>` );
			}
			summaryLines.push( 'This will refund the customer and cancel all tickets for this order.' );

			swal
				.fire( {
					title,
					html: `<div style="text-align:left;line-height:1.4;">${ summaryLines.join( '<br />' ) }</div>`,
					icon: 'warning',
					showCancelButton: true,
					confirmButtonText: confirmButton,
					cancelButtonText: cancelButton,
					confirmButtonColor: '#d63638',
					focusCancel: true,
					input: 'text',
					inputPlaceholder,
					inputAttributes: {
						autocomplete: 'off',
						autocapitalize: 'off',
					},
					preConfirm: ( value ) => {
						if ( upper( value ) !== confirmToken ) {
							swal.showValidationMessage( validationMessage );
							return false;
						}
						return true;
					},
				} )
				.then( ( result ) => {
					if ( result?.isConfirmed ) {
						window.location.href = url;
					}
				} );
		} );
	};

	$( initRefundButtons );
} )( jQuery );

