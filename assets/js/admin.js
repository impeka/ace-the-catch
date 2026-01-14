/* global jQuery, ajaxurl */

( function ( $ ) {
	'use strict';

	const ACTION = 'ace_the_catch_export_tickets';

	const ticketPositions = [
		{ x: 100, y: 120 },
		{ x: 950, y: 120 },
		{ x: 1800, y: 120 },
		{ x: 100, y: 840 },
		{ x: 950, y: 840 },
		{ x: 1800, y: 840 },
		{ x: 100, y: 1560 },
		{ x: 950, y: 1560 },
		{ x: 1800, y: 1560 },
		{ x: 100, y: 2280 },
		{ x: 950, y: 2280 },
		{ x: 1800, y: 2280 },
		{ x: 100, y: 3020 },
		{ x: 950, y: 3020 },
		{ x: 1800, y: 3020 },
	];

	const getJsPDF = () => {
		const root = window.jspdf;
		return root && root.jsPDF ? root.jsPDF : null;
	};

	const setStatus = ( $wrap, html, type = 'info' ) => {
		const $status = $wrap.find( '.cta-ticket-export__status' );
		if ( ! $status.length ) {
			return;
		}

		$status
			.removeClass( 'notice notice-error notice-success notice-info' )
			.addClass( `notice notice-${ type }` )
			.html( html )
			.show();
	};

	const clearStatus = ( $wrap ) => {
		const $status = $wrap.find( '.cta-ticket-export__status' );
		if ( $status.length ) {
			$status.hide().empty();
		}
	};

	const sanitizeFilenamePart = ( value ) => {
		return String( value || '' ).replace( /[^a-z0-9_-]+/gi, '_' ).replace( /^_+|_+$/g, '' );
	};

	const escapeCsv = ( value ) => {
		let str = value === null || value === undefined ? '' : String( value );
		str = str.replace( /\r\n/g, '\n' ).replace( /\r/g, '\n' );
		str = str.replace( /"/g, '""' );
		return `"${ str }"`;
	};

	const downloadBlob = ( filename, blob ) => {
		const url = window.URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = filename;
		link.style.display = 'none';
		document.body.appendChild( link );
		link.click();
		link.remove();
		window.URL.revokeObjectURL( url );
	};

	const buildCsv = ( tickets ) => {
		const columns = [
			'ticket_number',
			'ticket_created_at',
			'envelope_number',
			'order_number',
			'order_id',
			'order_status',
			'order_created_at',
			'first_name',
			'last_name',
			'email',
			'telephone',
			'location',
			'benefactor',
			'terms_accepted_at',
			'payment_reference',
			'total',
			'currency',
		];

		const lines = [];
		lines.push( columns.map( escapeCsv ).join( ',' ) );

		tickets.forEach( ( ticket ) => {
			const row = columns.map( ( key ) => escapeCsv( ticket?.[ key ] ?? '' ) );
			lines.push( row.join( ',' ) );
		} );

		return lines.join( '\r\n' ) + '\r\n';
	};

	const wrapEvery = ( text, width ) => {
		const str = String( text || '' );
		if ( ! str ) {
			return '';
		}
		const re = new RegExp( `(.{1,${ width }})`, 'g' );
		return str.match( re )?.join( '\n' ) ?? str;
	};

	const buildTicketText = ( ticket ) => {
		const maxCharsPerLine = 29;
		const maxLength = 11 * maxCharsPerLine;

		const name = `${ ticket?.first_name || '' } ${ ticket?.last_name || '' }`.trim();

		let text = wrapEvery( `Ticket: ${ ticket?.ticket_number ?? '' }`, maxCharsPerLine );
		text += '\n' + wrapEvery( `Envelope: ${ ticket?.envelope_number ?? '' }`, maxCharsPerLine );
		text += '\n' + wrapEvery( `Name: ${ name }`, maxCharsPerLine );
		text += '\n' + wrapEvery( `Tel: ${ ticket?.telephone ?? '' }`, maxCharsPerLine );
		text += '\n' + wrapEvery( `Email: ${ ticket?.email ?? '' }`, maxCharsPerLine );

		return text.substring( 0, maxLength );
	};

	const buildPdf = ( tickets, filename ) => {
		const JsPDF = getJsPDF();
		if ( ! JsPDF ) {
			throw new Error( 'jsPDF is not available.' );
		}

		const doc = new JsPDF( { orientation: 'p', unit: 'mm', format: 'a4' } );

		const pageWidth = doc.internal.pageSize.getWidth();
		const pageHeight = doc.internal.pageSize.getHeight();

		// These coordinates come from a legacy implementation that was placing text over a full-page ticket sheet
		// background image. The coordinate system matches a "virtual sheet" in pixels (typically ~300dpi).
		// Using the full sheet dimensions avoids tickets being clipped near the page edges.
		const sheetWidth = window.aceTheCatchAdmin?.ticketSheetWidth || 2550;
		const sheetHeight = window.aceTheCatchAdmin?.ticketSheetHeight || 3300;
		const ratio = Math.max( sheetWidth / pageWidth, sheetHeight / pageHeight );

		const ticketsPerPage = ticketPositions.length;

		doc.setFont( 'courier', 'normal' );
		doc.setFontSize( 9 );

		tickets.forEach( ( ticket, index ) => {
			if ( index > 0 && index % ticketsPerPage === 0 ) {
				doc.addPage();
			}

			const pos = ticketPositions[ index % ticketsPerPage ];
			const x = pos.x / ratio;
			const y = pos.y / ratio;
			doc.text( buildTicketText( ticket ), x, y );
		} );

		doc.save( filename );
	};

	const fetchTickets = async ( payload ) => {
		const url = window.aceTheCatchAdmin?.ajaxUrl || window.ajaxurl || ajaxurl;
		const response = await $.ajax( {
			url,
			method: 'POST',
			dataType: 'json',
			data: payload,
		} );

		if ( ! response || ! response.success ) {
			const message = response?.data?.message || response?.message || 'Request failed.';
			throw new Error( message );
		}

		return response.data?.tickets || [];
	};

	const initTicketExportBox = () => {
		const $wrap = $( '.cta-ticket-export' );
		if ( ! $wrap.length ) {
			return;
		}

		$wrap.on( 'click', '[data-cta-ticket-export]', async function ( e ) {
			e.preventDefault();

			clearStatus( $wrap );

			const type = String( $( this ).data( 'cta-ticket-export' ) || '' );
			const nonce = String( $wrap.data( 'nonce' ) || '' );
			const sessionId = parseInt( $wrap.data( 'session-id' ) || '0', 10 );
			const from = String( $( '#cta_ticket_export_from' ).val() || '' );
			const to = String( $( '#cta_ticket_export_to' ).val() || '' );

			if ( ! sessionId || ! nonce ) {
				setStatus( $wrap, 'Missing session context.', 'error' );
				return;
			}

			if ( ! from || ! to ) {
				setStatus( $wrap, 'Please enter both a "from" and "to" date.', 'error' );
				return;
			}

			const $buttons = $wrap.find( 'button[data-cta-ticket-export]' );
			$buttons.prop( 'disabled', true );

			try {
				setStatus( $wrap, 'Fetching tickets...', 'info' );

				const tickets = await fetchTickets( {
					action: ACTION,
					nonce,
					sessionId,
					from,
					to,
				} );

				if ( ! tickets.length ) {
					setStatus( $wrap, 'No tickets found for that range.', 'info' );
					return;
				}

				const fromPart = sanitizeFilenamePart( from );
				const toPart = sanitizeFilenamePart( to );

				if ( type === 'csv' ) {
					const csv = buildCsv( tickets );
					const filename = `tickets-${ sessionId }-${ fromPart }-${ toPart }.csv`;
					downloadBlob( filename, new Blob( [ csv ], { type: 'text/csv;charset=utf-8;' } ) );
					setStatus( $wrap, `Exported ${ tickets.length } tickets to CSV.`, 'success' );
					return;
				}

				if ( type === 'pdf' ) {
					const filename = `tickets-${ sessionId }-${ fromPart }-${ toPart }.pdf`;
					buildPdf( tickets, filename );
					setStatus( $wrap, `Generated PDF with ${ tickets.length } tickets.`, 'success' );
					return;
				}

				setStatus( $wrap, 'Unknown export type.', 'error' );
			} catch ( err ) {
				// eslint-disable-next-line no-console
				console.error( 'Catch the Ace export failed', err );
				setStatus( $wrap, `Export failed: ${ err?.message || err }`, 'error' );
			} finally {
				$buttons.prop( 'disabled', false );
			}
		} );
	};

	$( initTicketExportBox );
} )( jQuery );
