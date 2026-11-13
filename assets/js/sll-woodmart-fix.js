/**
 * WP Swup – WoodMart overlay / side-panel fix
 *
 * WoodMart keeps mobile nav, cart drawer, search fullscreen and scroll-lock
 * classes on html/body. After a Swup visit (especially when the animation
 * theme changes) those locks come back via woodmartThemeModule reinit.
 *
 * Strategy:
 *  - Close on visit:start (before swap)
 *  - Close on content:replace
 *  - Close on page:view, then again after short delays so we win over reinit
 *  - Close on visit:end
 *  - Also listen to sll:afterPageTransition (emitted after _reinitCompat)
 *  - Reset overflow / pointer-events / aria and remove Magnific Popup leftovers
 */
( function () {
	'use strict';

	var LOCK_CLASSES = [
		'wd-scroll-lock',
		'wd-opened',
		'global-scroll-lock',
		'wrapper-locked',
		'wp-admin-bar-hidden',
		'woodmart-scroll-lock',
		'mfp-zoom-out-cur',
		'mfp-ready',
		'wd-search-opened',
		'wd-sidebar-opened',
	];

	var PANEL_SELECTORS = [
		'.wd-close-side',
		'.woodmart-close-side',
		'.mobile-nav',
		'.wd-side-hidden',
		'.wd-off-canvas',
		'.cart-widget-side',
		'.wd-cart-widget',
		'.wd-search-full-screen',
		'.wd-search-opened',
		'.wd-popup',
		'.wd-dropdown-menu',
		'.wd-dropdown',
		'.wd-header-nav',
		'.wd-nav-mobile',
		'.wd-toolbar',
		'.mfp-wrap',
		'.mfp-bg',
		'.pswp',
		'.woodmart-woocommerce-popup',
	].join( ',' );

	var OPEN_CLASSES = [
		'wd-close-side-opened',
		'woodmart-close-side-opened',
		'wd-opened',
		'act-mobile-menu',
		'opened',
		'mfp-ready',
		'wd-search-opened',
		'pswp--open',
	];

	function removeClasses( el, list ) {
		if ( ! el || ! el.classList ) return;
		list.forEach( function ( c ) {
			el.classList.remove( c );
		} );
	}

	function resetInline( el ) {
		if ( ! el || ! el.style ) return;
		el.style.overflow = '';
		el.style.overflowX = '';
		el.style.overflowY = '';
		el.style.position = '';
		el.style.top = '';
		el.style.left = '';
		el.style.right = '';
		el.style.width = '';
		el.style.height = '';
		el.style.paddingRight = '';
		el.style.marginRight = '';
		el.style.pointerEvents = '';
	}

	function closeThemeOverlays() {
		var root = document.documentElement;
		var body = document.body;
		if ( ! body ) return;

		removeClasses( root, LOCK_CLASSES );
		removeClasses( body, LOCK_CLASSES );
		resetInline( root );
		resetInline( body );

		try {
			document.querySelectorAll( PANEL_SELECTORS ).forEach( function ( el ) {
				removeClasses( el, OPEN_CLASSES );
				removeClasses( el, LOCK_CLASSES );
				if ( el.classList && ( el.classList.contains( 'wd-side-hidden' ) || el.classList.contains( 'cart-widget-side' ) || el.classList.contains( 'wd-off-canvas' ) ) ) {
					el.style.transform = '';
					el.style.visibility = '';
					el.style.opacity = '';
					el.style.pointerEvents = '';
				}
			} );
		} catch ( e ) {}

		try {
			document.querySelectorAll( '.mfp-bg, .mfp-wrap, .mfp-container' ).forEach( function ( el ) {
				if ( el.parentNode ) el.parentNode.removeChild( el );
			} );
			document.querySelectorAll( '.pswp' ).forEach( function ( el ) {
				removeClasses( el, [ 'pswp--open', 'pswp--visible', 'pswp--animated-in' ] );
			} );
		} catch ( e ) {}

		if ( typeof jQuery !== 'undefined' ) {
			try {
				jQuery( 'body' ).removeClass( LOCK_CLASSES.join( ' ' ) );
				jQuery( 'html' ).removeClass( LOCK_CLASSES.join( ' ' ) );
				jQuery( '.wd-close-side.wd-close-side-opened, .woodmart-close-side.wd-close-side-opened' ).trigger( 'click' );
				jQuery( '.close-side-widget, .wd-close-side, .mobile-nav .wd-nav-close, .cart-widget-side .widget-close' ).trigger( 'click' );
				jQuery( '.wd-side-hidden, .cart-widget-side, .mobile-nav, .wd-search-full-screen' )
					.removeClass( OPEN_CLASSES.join( ' ' ) )
					.css( { transform: '', visibility: '', opacity: '', 'pointer-events': '' } );
			} catch ( e ) {}
		}

		try {
			document.querySelectorAll( '[aria-hidden="false"]' ).forEach( function ( el ) {
				if ( el.matches && el.matches( PANEL_SELECTORS ) ) {
					el.setAttribute( 'aria-hidden', 'true' );
				}
			} );
		} catch ( e ) {}
	}

	function scheduleClose() {
		closeThemeOverlays();
		setTimeout( closeThemeOverlays, 0 );
		setTimeout( closeThemeOverlays, 50 );
		setTimeout( closeThemeOverlays, 150 );
		setTimeout( closeThemeOverlays, 350 );
		setTimeout( closeThemeOverlays, 600 );
	}

	function bind() {
		if ( ! window.SLL || ! window.SLL.swup || ! window.SLL.swup.hooks ) {
			return false;
		}
		var swup = window.SLL.swup;
		if ( swup._sllWoodmartBound ) {
			return true;
		}
		swup._sllWoodmartBound = true;

		var hooks = swup.hooks;
		hooks.on( 'visit:start', closeThemeOverlays );
		hooks.on( 'content:replace', closeThemeOverlays );
		hooks.on( 'page:view', scheduleClose );
		hooks.on( 'visit:end', scheduleClose );

		document.addEventListener( 'sll:afterPageTransition', scheduleClose );
		document.addEventListener( 'sll:pageView', scheduleClose );

		if ( window.sllConfig && window.sllConfig.debug ) {
			console.log( '[SLL] WoodMart overlay fix bound (aggressive)' );
		}
		return true;
	}

	function boot() {
		if ( bind() ) return;
		document.addEventListener( 'sll:ready', bind );
		setTimeout( bind, 300 );
		setTimeout( bind, 1000 );
		setTimeout( bind, 2500 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	window.SLL = window.SLL || {};
	window.SLL.closeOverlays = closeThemeOverlays;
	window.SLL.scheduleCloseOverlays = scheduleClose;
} )();
