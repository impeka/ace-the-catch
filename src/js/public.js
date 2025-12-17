import Swal from 'sweetalert2';
import { confetti } from '@tsparticles/confetti';

( function ( $ ) {
	'use strict';

	$( function () {
		ace_the_catch.init();
	} );
} )( jQuery );

const ace_the_catch = {
	init: function () {
		this.init_envelopes();
	},

	launch_confetti: function () {
		const count = 120;
		const defaults = {
			origin: { y: 0.6 },
			shapes: [ 'emoji' ],
			shapeOptions: {
				emoji: {
					value: [ '♥️', '♦️', '♠️', '♣️' ],
				},
			},
			scalar: 1.6, // scale up the emoji particles for visibility.
		};

		const fire = ( particleRatio, opts = {} ) => {
			confetti( {
				...defaults,
				...opts,
				particleCount: Math.floor( count * particleRatio ),
			} );
		};

		fire(0.25, {
			spread: 26,
			startVelocity: 55,
		});

		fire(0.2, {
			spread: 60,
		});

		fire(0.35, {
			spread: 100,
			decay: 0.91,
			scalar: 0.8,
		});

		fire(0.1, {
			spread: 120,
			startVelocity: 25,
			decay: 0.92,
			scalar: 1.2,
		});

		fire(0.1, {
			spread: 120,
			startVelocity: 45,
		});
	},

	init_envelopes: function () {
		const envelopes = document.querySelectorAll( '.envelope:not([data-card])' );
		if ( envelopes ) {
			envelopes.forEach( ( envelope ) => {
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
			}
		} );
	},
};
