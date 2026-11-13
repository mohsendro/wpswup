/**
 * WP Swup – WoodMart overlay fix
 * Closes .wd-close-side-opened and related locks on every Swup navigation.
 */
( function () {
	'use strict';

	function closeThemeOverlays() {
		document.querySelectorAll( '.wd-close-side, .woodmart-close-side' ).forEach( function ( el ) {
			el.classList.remove( 'wd-close-side-opened', 'woodmart-close-side-opened' );
		} );

		document.querySelectorAll(
			'.mobile-nav, .wd-side-hidden, .wd-off-canvas, .cart-widget-side, .wd-search-full-screen, .wd-popup, .mfp-wrap'
		).forEach( function ( el ) {
			el.classList.remove( 'wd-opened', 'act-mobile-menu', 'opened', 'mfp-ready' );
		} );

		var root = document.documentElement;
		var body = document.body;
		[ 'wd-scroll-lock', 'wd-opened', 'global-scroll-lock', 'wrapper-locked', 'wp-admin-bar-hidden' ].forEach( function ( cls ) {
			root.classList.remove( cls );
			body.classList.remove( cls );
		} );

		document.querySelectorAll( '.mfp-bg, .mfp-wrap' ).forEach( function ( el ) {
			el.remove();
		} );
		body.classList.remove( 'mfp-zoom-out-cur', 'mfp-ready' );

		if ( typeof jQuery !== 'undefined' ) {
			try {
				jQuery( 'body' ).removeClass( 'wrapper-locked wd-scroll-lock wd-opened' );
				jQuery( '.wd-close-side.wd-close-side-opened' ).trigger( 'click' );
			} catch ( e ) {}
		}
	}

	function bind() {
		if ( ! window.SLL || ! window.SLL.swup || ! window.SLL.swup.hooks ) {
			return false;
		}
		var hooks = window.SLL.swup.hooks;
		hooks.on( 'visit:start', closeThemeOverlays );
		hooks.on( 'page:view', closeThemeOverlays );
		hooks.on( 'visit:end', closeThemeOverlays );
		if ( window.sllConfig && window.sllConfig.debug ) {
			console.log( '[SLL] WoodMart overlay fix bound' );
		}
		return true;
	}

	// SLL boots on DOMContentLoaded; this file may load before or after.
	if ( ! bind() ) {
		document.addEventListener( 'sll:ready', bind );
		setTimeout( bind, 500 );
		setTimeout( bind, 1500 );
	}

	window.SLL = window.SLL || {};
	window.SLL.closeOverlays = closeThemeOverlays;
} )();
