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
	sessionId: null,
	sessionWeek: null,
	checkoutUrl: null,
	geoBlocked: false,
	geoMessage: '',
	geoNeedsLocation: false,
	geoConfig: null,
	geoRequestInFlight: false,

	init: function () {
		try {
			this.init_geo_gate();
		} catch ( err ) {
			console.error( 'ACE: init_geo_gate failed', err );
		}

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

	init_geo_gate: function () {
		this.geoConfig = window.aceTheCatchGeo || null;
		const locator = this.geoConfig?.locator || '';
		const browserLocator = this.geoConfig?.browserLocator || '';
		const needsLocation = !! this.geoConfig?.needsLocation;
		this.geoNeedsLocation = ( needsLocation && locator && browserLocator && locator === browserLocator );

		const requestBtn = document.querySelector( '.ace-geo-request' );
		if ( requestBtn ) {
			requestBtn.addEventListener( 'click', () => {
				this.prompt_for_location();
			} );
		}

		// Checkout gate: if the page is blocking on geo, prompt on load (user still clicks to allow).
		if ( this.geoNeedsLocation && document.querySelector( '.ace-geo-gate' ) ) {
			this.prompt_for_location();
		}
	},

	prompt_for_location: function () {
		if ( this.geoRequestInFlight ) {
			return;
		}

		const cfg = this.geoConfig || window.aceTheCatchGeo || null;
		if ( ! cfg || cfg.locator !== cfg.browserLocator ) {
			return;
		}

		if ( ! navigator?.geolocation ) {
			Swal.fire( {
				title: cfg.errorTitle || 'Location error',
				text: 'Your browser does not support location services.',
				icon: 'error',
				confirmButtonText: 'OK',
			} );
			return;
		}

		const getPosition = () =>
			new Promise( ( resolve, reject ) => {
				navigator.geolocation.getCurrentPosition(
					resolve,
					reject,
					{
						enableHighAccuracy: true,
						timeout: 15000,
						maximumAge: 0,
					}
				);
			} );

		Swal.fire( {
			title: cfg.promptTitle || 'Location required',
			html: cfg.promptMessage || 'Please allow location access so we can verify eligibility.',
			icon: 'info',
			showCancelButton: true,
			confirmButtonText: cfg.promptButton || 'Allow location',
			cancelButtonText: cfg.promptCancel || 'Not now',
			showLoaderOnConfirm: true,
			allowOutsideClick: () => ! Swal.isLoading(),
			preConfirm: async () => {
				this.geoRequestInFlight = true;
				try {
					const pos = await getPosition();
					const lat = pos?.coords?.latitude;
					const lng = pos?.coords?.longitude;

					if ( ! Number.isFinite( lat ) || ! Number.isFinite( lng ) ) {
						throw new Error( 'Invalid coordinates received.' );
					}

					const result = await this.verify_location_with_server( lat, lng );
					return result;
				} catch ( err ) {
					const msg = err?.message || 'Unable to get your location.';
					Swal.showValidationMessage( msg );
					return false;
				} finally {
					this.geoRequestInFlight = false;
				}
			},
		} ).then( ( result ) => {
			if ( ! result?.isConfirmed || ! result.value ) {
				return;
			}

			const data = result.value;
			this.geoNeedsLocation = false;
			if ( cfg ) {
				cfg.needsLocation = false;
			}

			if ( data.in_ontario ) {
				window.location.reload();
				return;
			}

			const outsideHtml = data.message || this.geoMessage || 'Ticket sales are not available in your region.';
			this.geoBlocked = true;
			this.geoMessage = outsideHtml;

			Swal.fire( {
				title: cfg.outsideTitle || 'Not available in your region',
				html: outsideHtml,
				icon: 'info',
				confirmButtonText: 'OK',
			} );
		} );
	},

	verify_location_with_server: async function ( lat, lng ) {
		const cfg = this.geoConfig || window.aceTheCatchGeo || null;
		if ( ! cfg?.ajaxUrl || ! cfg?.nonce ) {
			throw new Error( 'Geo configuration missing.' );
		}

		const params = new URLSearchParams();
		params.set( 'action', 'ace_the_catch_geo_locate' );
		params.set( 'nonce', cfg.nonce );
		params.set( 'lat', String( lat ) );
		params.set( 'lng', String( lng ) );

		const res = await fetch( cfg.ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			credentials: 'same-origin',
			body: params.toString(),
		} );

		const json = await res.json();
		if ( ! json?.success ) {
			const msg = json?.data?.message || 'Unable to verify location.';
			throw new Error( msg );
		}

		return json.data || { in_ontario: false, message: '' };
	},

	init_cart: function () {
		this.cartContainer = document.querySelector( '#ace-cart' );
		this.cartBody = document.querySelector( '.ace-cart__body' );
		this.cartFoot = document.querySelector( '.ace-cart__foot' );
		this.cartTotalValue = document.querySelector( '.ace-cart__total-value' );
		const wrap = document.querySelector( '.card-table-wrap' );
		this.sessionId = wrap?.dataset?.sessionId || null;
		this.sessionWeek = wrap?.dataset?.sessionWeek || null;
		this.checkoutUrl = wrap?.dataset?.checkoutUrl || null;
		const priceAttr = this.cartContainer?.getAttribute( 'data-ticket-price' );
		const parsedPrice = parseFloat( priceAttr );
		this.ticketPrice = Number.isFinite( parsedPrice ) ? parsedPrice : 0;

		this.load_cart_from_storage();

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

		const checkoutBtn = document.querySelector( '.ace-checkout-btn' );
		if ( checkoutBtn ) {
			checkoutBtn.addEventListener( 'click', () => {
				this.submit_checkout();
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

	render_cart: function ( skipSave = false ) {
		if ( ! this.cartBody || ! this.cartContainer ) {
			return;
		}

		const envelopes = Object.keys( this.cart );
		if ( envelopes.length === 0 ) {
			this.cartBody.innerHTML = '';
			this.cartContainer.setAttribute( 'hidden', 'hidden' );
			this.cartFoot?.setAttribute( 'hidden', 'hidden' );
			this.cartVisible = false;
			if ( ! skipSave ) {
				this.save_cart_to_storage();
			}
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

		if ( ! skipSave ) {
			this.save_cart_to_storage();
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
		const wrap = document.querySelector( '.card-table-wrap' );
		this.geoBlocked = wrap?.dataset?.geoBlock === '1';
		this.geoMessage = wrap?.dataset?.geoMessage || 'Ticket sales are not available in your region.';

		if ( this.geoBlocked ) {
			if ( this.geoNeedsLocation ) {
				this.prompt_for_location();
			} else {
				Swal.fire( {
					title: this.geoConfig?.outsideTitle || 'Not available in your region',
					html: this.geoMessage,
					icon: 'info',
					confirmButtonText: 'OK',
				} );
			}
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
			if ( this.geoNeedsLocation ) {
				this.prompt_for_location();
				return;
			}

			Swal.fire( {
				title: this.geoConfig?.outsideTitle || 'Not available in your region',
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

	load_cart_from_storage: function () {
		if ( ! this.sessionId || ! window.localStorage ) {
			return;
		}

		try {
			const raw = window.localStorage.getItem( 'ace_cart_state' );
			if ( ! raw ) {
				return;
			}
			const parsed = JSON.parse( raw );
			if ( parsed.sessionId !== this.sessionId || parsed.sessionWeek !== this.sessionWeek ) {
				window.localStorage.removeItem( 'ace_cart_state' );
				return;
			}
			if ( parsed.cart && typeof parsed.cart === 'object' ) {
				this.cart = parsed.cart;
				this.render_cart( true );
			}
		} catch ( err ) {
			console.error( 'ACE: load_cart_from_storage failed', err );
		}
	},

	save_cart_to_storage: function () {
		if ( ! this.sessionId || ! window.localStorage ) {
			return;
		}
		try {
			const payload = {
				sessionId: this.sessionId,
				sessionWeek: this.sessionWeek,
				cart: this.cart,
			};
			const json = JSON.stringify( payload );
			window.localStorage.setItem( 'ace_cart_state', json );
			// Mirror to a cookie so checkout can be visited directly.
			document.cookie = `ace_cart_state=${ encodeURIComponent( json ) };path=/;max-age=86400`;
		} catch ( err ) {
			console.error( 'ACE: save_cart_to_storage failed', err );
		}
	},

	submit_checkout: function () {
		if ( this.geoBlocked ) {
			if ( this.geoNeedsLocation ) {
				this.prompt_for_location();
				return;
			}

			Swal.fire( {
				title: this.geoConfig?.outsideTitle || 'Not available in your region',
				html: this.geoMessage,
				icon: 'info',
				confirmButtonText: 'OK',
			} );
			return;
		}

		const envelopes = Object.keys( this.cart );
		if ( envelopes.length === 0 ) {
			Swal.fire( {
				title: 'Cart is empty',
				text: 'Please add at least one envelope before checking out.',
				icon: 'info',
				confirmButtonText: 'OK',
			} );
			return;
		}

		// Ensure the latest cart state is persisted for direct /checkout visits.
		this.save_cart_to_storage();

		const action = this.checkoutUrl || window.location.pathname.replace(/\/?$/, '/checkout');
		const form = document.createElement( 'form' );
		form.method = 'post';
		form.action = action;

		// Flag this as a checkout submission so the server stores the cart then redirects (PRG).
		const hiddenFlag = document.createElement( 'input' );
		hiddenFlag.type = 'hidden';
		hiddenFlag.name = 'ace_checkout_cart';
		hiddenFlag.value = '1';
		form.appendChild( hiddenFlag );

		envelopes.forEach( ( env ) => {
			const qty = this.cart[ env ]?.entries ?? 0;
			if ( qty > 0 ) {
				const input = document.createElement( 'input' );
				input.type = 'hidden';
				input.name = `envelope[${ env }]`;
				input.value = qty;
				form.appendChild( input );
			}
		} );

		document.body.appendChild( form );
		form.submit();
	},
};
