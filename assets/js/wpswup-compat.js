/**
 * WP Swup – compatibility layer
 *
 * 1) ignoreVisit: lightboxes, media, external, WP/Woo special URLs
 * 2) Disable Swup inside Elementor editor / Customizer
 * 3) After page:view — rebind CF7 / common widgets safely
 */
( function () {
	'use strict';

	var MEDIA_EXT =
		/\.(jpe?g|png|gif|webp|avif|svg|bmp|ico|pdf|zip|rar|7z|gz|tar|mp4|webm|mp3|wav|ogg|flac|doc|docx|xls|xlsx|ppt|pptx|txt|csv)$/i;

	var LIGHTBOX_SEL =
		'[data-elementor-open-lightbox], [data-fancybox], [data-lightbox], [data-glightbox], [data-pswp], [data-pswp-src], [data-rel="lightbox"], [data-rel^="prettyPhoto"], [data-open-lightbox], [rel="lightbox"], [rel^="prettyPhoto"], [rel^="magnific"], [data-magnific], [data-toggle="lightbox"], [data-uk-lightbox], [uk-lightbox]';

	var GALLERY_SEL =
		'.woocommerce-product-gallery, .wd-gallery, .wd-product-gallery, .woodmart-product-gallery, .photoswipe, .pswp, .elementor-widget-image-gallery, .elementor-widget-gallery, .elementor-widget-image, .wp-block-gallery, .gallery, .tiled-gallery, .product-image-wrap, .wd-carousel-wrap';

	var IGNORE_LINK_CLASS =
		'.lightbox, .fancybox, .glightbox, .photoswipe, .zoom, .wd-zoom, .pswp-link, a.woocommerce-product-gallery__trigger, .no-swup, .noswup';

	function pathOnly( href ) {
		return href.split( '?' )[ 0 ].split( '#' )[ 0 ].toLowerCase();
	}

	function shouldIgnoreVisit( url, el ) {
		if ( ! el ) return false;
		if ( el.closest( '[data-no-swup]' ) ) return true;
		if ( el.hasAttribute( 'download' ) ) return true;
		var target = ( el.getAttribute( 'target' ) || '' ).toLowerCase();
		if ( target && target !== '_self' ) return true;
		var href = ( el.getAttribute( 'href' ) || url || '' ).trim();
		if ( ! href || href === '#' || href.charAt( 0 ) === '#' ) return true;
		if ( /^(mailto:|tel:|sms:|javascript:|blob:|data:)/i.test( href ) ) return true;
		if ( MEDIA_EXT.test( pathOnly( href ) ) ) return true;
		if ( el.closest( LIGHTBOX_SEL ) ) return true;
		if ( el.matches && el.matches( IGNORE_LINK_CLASS ) ) return true;
		var gallery = el.closest( GALLERY_SEL );
		if ( gallery && MEDIA_EXT.test( pathOnly( href ) ) ) return true;
		if ( /add-to-cart=|remove_item=|undo_item|update_cart|apply_coupon|wc-ajax=/.test( href ) ) return true;
		if ( el.closest( '.ajax_add_to_cart, .add_to_cart_button, .remove_from_cart_button, .single_add_to_cart_button' ) ) return true;
		try {
			var u = new URL( href, window.location.origin );
			if ( u.origin !== window.location.origin ) return true;
			var p = u.pathname.toLowerCase();
			if ( p.indexOf( '/wp-admin' ) !== -1 ) return true;
			if ( p.indexOf( '/wp-login' ) !== -1 ) return true;
			if ( p.indexOf( '/wp-json' ) !== -1 ) return true;
			if ( p.indexOf( 'xmlrpc.php' ) !== -1 ) return true;
			if ( u.search.indexOf( 'customer-logout' ) !== -1 ) return true;
			if ( u.search.indexOf( 'action=logout' ) !== -1 ) return true;
			if ( u.search.indexOf( '_wpnonce=' ) !== -1 && /logout|log-out|log_out/.test( href ) ) return true;
			if ( /\/(checkout|order-pay|order-received)(\/|$)/i.test( p ) ) return true;
			if ( u.search.indexOf( 'pay_for_order' ) !== -1 ) return true;
			if ( u.search.indexOf( 'key=wc_order' ) !== -1 ) return true;
		} catch ( e ) {
			return true;
		}
		return false;
	}

	function isEditorContext() {
		try {
			if ( window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode() ) {
				return true;
			}
		} catch ( e ) {}
		if ( document.body && document.body.classList.contains( 'elementor-editor-active' ) ) return true;
		if ( document.body && document.body.classList.contains( 'wp-customizer' ) ) return true;
		if ( window.location.search.indexOf( 'elementor-preview=' ) !== -1 ) return true;
		if ( window.location.pathname.indexOf( '/wp-admin' ) !== -1 ) return true;
		return false;
	}

	function onPageView() {
		if ( typeof window.wpcf7 !== 'undefined' && typeof window.wpcf7.init === 'function' ) {
			try {
				document.querySelectorAll( '.wpcf7 > form' ).forEach( function ( form ) {
					window.wpcf7.init( form );
				} );
			} catch ( e ) {}
		}
		if ( typeof window.jQuery !== 'undefined' ) {
			try {
				window.jQuery( document ).trigger( 'gform_post_render' );
			} catch ( e ) {}
		}
	}

	function patchSwup() {
		if ( ! window.WPSWUP || ! window.WPSWUP.swup ) return false;
		var swup = window.WPSWUP.swup;
		if ( isEditorContext() ) {
			try { swup.destroy(); } catch ( e ) {}
			return true;
		}
		var prev = swup.options && swup.options.ignoreVisit;
		swup.options.ignoreVisit = function ( url, args ) {
			var el = args && args.el;
			if ( shouldIgnoreVisit( url, el ) ) return true;
			if ( typeof prev === 'function' && prev( url, args || {} ) ) return true;
			return false;
		};
		window.WPSWUP.shouldIgnoreVisit = shouldIgnoreVisit;
		if ( ! swup._wpswupCompatBound ) {
			swup._wpswupCompatBound = true;
			if ( swup.hooks ) swup.hooks.on( 'page:view', onPageView );
		}
		return true;
	}

	function boot() {
		if ( isEditorContext() ) return;
		if ( patchSwup() ) return;
		document.addEventListener( 'wpswup:ready', function () { patchSwup(); } );
		setTimeout( patchSwup, 300 );
		setTimeout( patchSwup, 1200 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
