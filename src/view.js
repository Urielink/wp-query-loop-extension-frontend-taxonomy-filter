/**
 * WordPress dependencies
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

// Track which elements have already run their initial callback, keyed per element
// to correctly support multiple filter blocks on the same page.
const initializedElements = new WeakSet();

const updateLiveRegion = ( element ) => {
	const liveRegion = element.querySelector( '.live-region' );

	// Screen readers often suppress announcements if the new text is identical to the old text.
	// We alternate by adding a non-breaking space at the end to force a perceived change.
	if ( liveRegion.textContent === 'Content updated.' ) {
		liveRegion.textContent = 'Content updated. ';
	} else {
		liveRegion.textContent = 'Content updated.';
	}
};

store( 'ctlt-query-tax-filter', {
	actions: {
		onChangeTerm: ( event ) => {
			event.preventDefault();

			const context = getContext();

			// Check the element tag, if it's a checkbox, get all the checked values.
			if ( event.target.tagName === 'INPUT' && event.target.type === 'checkbox' ) {
				const checkboxName  = event.target.name;
				const checkedValues = document.querySelectorAll( `input[name="${checkboxName}"]:checked` );
				context.selectedTerm = Array.from( checkedValues ).map( checkbox => checkbox.value );
			} else {
				context.selectedTerm = event.target.value;
			}
		},
	},
	callbacks: {
		*navigateToDestination() {
			const { ref } = getElement();
			// Reading context here (even on the skipped first run) is required so the
			// Interactivity API's reactivity system subscribes this callback to
			// selectedTerm/isGlobal changes. Reading it after the early return below
			// would mean the dependency is never registered and the callback would
			// never re-run when the filter value changes.
			const { selectedTerm, isGlobal } = getContext();

			// Skip the very first run triggered on element creation, tracked per element.
			if ( ! initializedElements.has( ref ) ) {
				initializedElements.add( ref );
				return;
			}

			if ( null === ref ) {
				return;
			}

			const filterId = ref.getAttribute( 'filter-id' );
			const value    = Array.isArray( selectedTerm ) ? selectedTerm.join( ',' ) : selectedTerm;

			const { actions } = yield import( '@wordpress/interactivity-router' );

			const navigateTo = new URL( window.location );

			if ( isGlobal ) {
				// Global mode: apply the filter to every query loop present on the page.
				const queryBlocks = document.querySelectorAll( '.wp-block-query[data-wp-router-region]' );
				queryBlocks.forEach( ( queryBlock ) => {
					const region = queryBlock.getAttribute( 'data-wp-router-region' );
					navigateTo.searchParams.set( `${region}-term-${filterId}`, value );
					navigateTo.searchParams.set( `${region}-page`, '1' );
				} );
			} else {
				// Default mode: apply the filter only to the closest ancestor query loop.
				const queryRef = ref.closest( '.wp-block-query[data-wp-router-region]' );
				if ( ! queryRef ) {
					return;
				}
				const region = queryRef.getAttribute( 'data-wp-router-region' );
				navigateTo.searchParams.set( `${region}-term-${filterId}`, value );
				navigateTo.searchParams.set( `${region}-page`, '1' );
			}

			yield actions.navigate( navigateTo );

			updateLiveRegion( ref );
		},
	},
} );
