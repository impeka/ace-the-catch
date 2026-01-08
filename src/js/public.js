import Swal from 'sweetalert2';
import { confetti } from '@tsparticles/confetti';
import * as SimplyCountdown from 'simplycountdown.js';

( function ( $ ) {
	'use strict';

	$( function () {
		ace_the_catch.init();
	} );
} )( jQuery );

const ace_the_catch = {
	cart: {},
	cartContainer: null,
	cartBody: null,
	cartVisible: false,
	ticketPrice: 0,

	init: function () {
		try {
			this.init_cart();
		} catch ( err ) {
			console.error( 'ACE: init_cart failed', err );
		}

		try {
			this.init_envelopes();
		} catch ( err ) {
			console.error( 'ACE: init_envelopes failed', err );
		}

		try {
			this.init_countdown();
		} catch ( err ) {
			console.error( 'ACE: init_countdown failed', err );
		}

		// Always reveal the table, even if an earlier init step errored.
		this.mark_ready();
	},

	init_countdown: function () {
		const containers = document.querySelectorAll( '.ace-countdown[data-countdown-iso][data-countdown-target]' );
		const countdownFn = SimplyCountdown.default || SimplyCountdown;
		if ( ! containers || ! countdownFn ) {
			return;
		}

		containers.forEach( ( container ) => {
			const iso = container.getAttribute( 'data-countdown-iso' );
			const selector = container.getAttribute( 'data-countdown-target' );
			if ( ! iso || ! selector ) {
				return;
			}

			const date = new Date( iso );
			if ( Number.isNaN( date.getTime() ) ) {
				return;
			}

			countdownFn( selector, {
				year: date.getFullYear(),
				month: date.getMonth() + 1,
				day: date.getDate(),
				hours: date.getHours(),
				minutes: date.getMinutes(),
				seconds: date.getSeconds(),
				enableUtc: false,
			} );
		} );
	},

	init_cart: function () {
		this.cartContainer = document.querySelector( '#ace-cart' );
		this.cartBody = document.querySelector( '.ace-cart__body' );
		this.cartFoot = document.querySelector( '.ace-cart__foot' );
		this.cartTotalValue = document.querySelector( '.ace-cart__total-value' );
		const priceAttr = this.cartContainer?.getAttribute( 'data-ticket-price' );
		const parsedPrice = parseFloat( priceAttr );
		this.ticketPrice = Number.isFinite( parsedPrice ) ? parsedPrice : 0;

		if ( this.cartContainer ) {
			this.cartContainer.addEventListener( 'input', ( event ) => {
				const target = event.target;
				if ( target?.classList?.contains( 'ace-cart__qty' ) ) {
					const envelope = target.getAttribute( 'data-envelope' );
					let val = parseInt( target.value, 10 );
					val = Number.isFinite( val ) && val > 0 ? val : 0;

					if ( val <= 0 ) {
						delete this.cart[ envelope ];
					} else {
						this.cart[ envelope ].entries = val;
					}

					this.render_cart();
				}
			} );

			this.cartContainer.addEventListener( 'click', ( event ) => {
				const target = event.target;
				if ( target?.classList?.contains( 'ace-cart__remove-btn' ) ) {
					const envelope = target.getAttribute( 'data-envelope' );
					if ( envelope && this.cart[ envelope ] ) {
						delete this.cart[ envelope ];
						this.render_cart();
					}
				}
			} );
		}
	},

	add_to_cart: function ( envelope, entries ) {
		const numEntries = Number.isFinite( entries ) ? entries : 0;
		if ( numEntries <= 0 || ! envelope ) {
			return;
		}

		if ( ! this.cart[ envelope ] ) {
			this.cart[ envelope ] = { entries: numEntries };
		} else {
			this.cart[ envelope ].entries += numEntries;
		}

		this.render_cart();
	},

	render_cart: function () {
		if ( ! this.cartBody || ! this.cartContainer ) {
			return;
		}

		const envelopes = Object.keys( this.cart );
		if ( envelopes.length === 0 ) {
			this.cartBody.innerHTML = '';
			this.cartContainer.setAttribute( 'hidden', 'hidden' );
			this.cartFoot?.setAttribute( 'hidden', 'hidden' );
			this.cartVisible = false;
			return;
		}

		let total = 0;

		const rows = envelopes
			.sort( ( a, b ) => parseInt( a, 10 ) - parseInt( b, 10 ) )
			.map( ( env ) => {
				const count = this.cart[ env ]?.entries ?? 0;
				const subtotal = count * this.ticketPrice;
				total += subtotal;
				return `
					<tr>
						<td>#${ env }</td>
						<td>
							<input
								type="number"
								min="0"
								step="1"
								value="${ count }"
								name="envelope[${ env }]"
								class="ace-cart__qty"
								data-envelope="${ env }"
							/>
						</td>
						<td class="ace-cart__amount">${ this.format_currency( subtotal ) }</td>
						<td class="ace-cart__remove">
							<button
								type="button"
								class="ace-cart__remove-btn"
								data-envelope="${ env }"
								title="Remove envelope #${ env }"
								aria-label="Remove envelope #${ env } from cart"
							>
								&times;
							</button>
						</td>
					</tr>
				`;
			} )
			.join( '' );

		this.cartBody.innerHTML = rows;
		if ( this.cartTotalValue ) {
			this.cartTotalValue.textContent = this.format_currency( total );
		}
		this.cartFoot?.removeAttribute( 'hidden' );

		if ( ! this.cartVisible ) {
			this.cartContainer.removeAttribute( 'hidden' );
			this.cartVisible = true;
			this.cartContainer.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	},

	format_currency: function ( amount ) {
		const val = Number.isFinite( amount ) ? amount : 0;
		return new Intl.NumberFormat( undefined, { style: 'currency', currency: 'CAD' } ).format( val );
	},

	launch_confetti: function () {
		const count = 120;
		const defaults = {
			origin: { y: 0.6 },
			shapes: [ 'emoji' ],
			shapeOptions: {
				emoji: {
					value: [
						'\u2665\uFE0F', // ♥️
						'\u2666\uFE0F', // ♦️
						'\u2660\uFE0F', // ♠️
						'\u2663\uFE0F', // ♣️
					],
				},
			},
			scalar: 1.6,
		};

		const fire = ( particleRatio, opts = {} ) => {
			confetti( {
				...defaults,
				...opts,
				particleCount: Math.floor( count * particleRatio ),
			} );
		};

		fire( 0.25, {
			spread: 26,
			startVelocity: 55,
		} );

		fire( 0.2, {
			spread: 60,
		} );

		fire( 0.35, {
			spread: 100,
			decay: 0.91,
			scalar: 0.8,
		} );

		fire( 0.1, {
			spread: 120,
			startVelocity: 25,
			decay: 0.92,
			scalar: 1.2,
		} );

		fire( 0.1, {
			spread: 120,
			startVelocity: 45,
		} );
	},

	init_envelopes: function () {
		const geoBlockedWrap = document.querySelector( '.card-table-wrap[data-geo-block="1"]' );
		this.geoBlocked = !! geoBlockedWrap;
		this.geoMessage = geoBlockedWrap?.dataset?.geoMessage || 'Ticket sales are not available in your region.';

		if ( this.geoBlocked ) {
			Swal.fire( {
				title: 'Not available in your region',
				html: this.geoMessage,
				icon: 'info',
				confirmButtonText: 'OK',
			} );
		}

		const envelopes = document.querySelectorAll( '.envelope:not([data-card])' );
		if ( envelopes ) {
			envelopes.forEach( ( envelope ) => {
				const state = this.get_sales_state( envelope );
				if ( this.geoBlocked || ! state.open ) {
					envelope.classList.add( 'envelope--disabled' );
				}

				envelope.addEventListener( 'click', () => {
					this.handle_envelope_click( envelope );
				} );

				envelope.addEventListener( 'keydown', ( e ) => {
					if ( e.key === 'Enter' || e.key === ' ' ) {
						e.preventDefault();
						this.handle_envelope_click( envelope );
					}
				} );
			} );
		}
	},

	handle_envelope_click: function ( envelopeEl ) {
		if ( this.geoBlocked ) {
			Swal.fire( {
				title: 'Not available in your region',
				html: this.geoMessage,
				icon: 'info',
				confirmButtonText: 'OK',
			} );
			return;
		}

		const state = this.get_sales_state( envelopeEl );

		if ( ! state.open ) {
			Swal.fire( {
				title: 'Ticket sales closed',
				text: state.message || 'Ticket sales are currently closed.',
				icon: 'info',
				confirmButtonText: 'OK',
			} );
			return;
		}

		const envelopeNumber = envelopeEl?.dataset?.envelope || '?';

		Swal.fire( {
			title: `Envelope #${ envelopeNumber }`,
			html: `
				<div class="etc-modal">
					<div class="etc-modal__copy">
						You have selected envelope <strong>#${ envelopeNumber }</strong>.<br/>
						How many entries would you like to add?
					</div>
					<div class="entry-qty">
						<button type="button" class="entry-qty__btn entry-qty__btn--minus">-</button>
						<input type="number" min="1" step="1" value="1" class="entry-qty__input" />
						<button type="button" class="entry-qty__btn entry-qty__btn--plus">+</button>
					</div>
				</div>
			`,
			showCancelButton: true,
			confirmButtonText: 'Add entries',
			cancelButtonText: 'Cancel',
			focusConfirm: false,
			willOpen: () => {
				const input = document.querySelector( '.entry-qty__input' );
				const btnMinus = document.querySelector( '.entry-qty__btn--minus' );
				const btnPlus = document.querySelector( '.entry-qty__btn--plus' );

				const clampValue = () => {
					const val = parseInt( input.value, 10 );
					input.value = Number.isFinite( val ) && val > 0 ? val : 1;
				};

				btnMinus?.addEventListener( 'click', () => {
					clampValue();
					const val = parseInt( input.value, 10 );
					input.value = Math.max( 1, val - 1 );
				} );

				btnPlus?.addEventListener( 'click', () => {
					clampValue();
					const val = parseInt( input.value, 10 );
					input.value = val + 1;
				} );

				input?.addEventListener( 'input', clampValue );
			},
			preConfirm: () => {
				const input = document.querySelector( '.entry-qty__input' );
				const val = parseInt( input?.value, 10 );
				if ( ! Number.isFinite( val ) || val < 1 ) {
					Swal.showValidationMessage( 'Please enter at least 1 entry.' );
					return false;
				}
				return { entries: val, envelope: envelopeNumber };
			},
		} ).then( ( result ) => {
			if ( result.isConfirmed ) {
				// TODO: hook into your submission flow with result.value.entries and result.value.envelope.
				console.log( 'Entries selected', result.value );
				this.launch_confetti();
				this.add_to_cart( result.value.envelope, result.value.entries );
			}
		} );
	},

	get_sales_state: function ( envelopeEl ) {
		const wrap = envelopeEl?.closest( '.card-table-wrap' );
		if ( ! wrap ) {
			return { open: false, message: 'Ticket sales are currently closed.' };
		}

		const salesMessage = wrap.dataset.salesMessage || 'Ticket sales are currently closed.';

		// If already closed, stay closed.
		if ( wrap.dataset.salesOpen !== '1' ) {
			return { open: false, message: salesMessage };
		}

		// One-way: if close time has passed, force close.
		const closeEpoch = parseInt( wrap.dataset.salesCloseEpoch ?? '0', 10 );
		if ( Number.isFinite( closeEpoch ) && closeEpoch > 0 && Date.now() >= closeEpoch * 1000 ) {
			this.force_close_sales( wrap );
			return { open: false, message: salesMessage };
		}

		return { open: true, message: salesMessage };
	},

	force_close_sales: function ( wrap ) {
		wrap.dataset.salesOpen = '0';
		const msg = wrap.dataset.salesMessage || 'Ticket sales are currently closed.';
		wrap.dataset.salesMessage = msg;
		wrap.classList.add( 'card-table-wrap--closed' );
		wrap.querySelectorAll( '.envelope' ).forEach( ( env ) => env.classList.add( 'envelope--disabled' ) );
	},

	mark_ready: function () {
		document.querySelectorAll( '.card-table-wrap' ).forEach( ( wrap ) => {
			wrap.classList.add( 'is-ready' );
		} );
	},
};
