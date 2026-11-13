/**
 * WP Swup – compatibility layer
 * Prevents Swup from intercepting lightboxes, media files, external links, WooCommerce AJAX, etc.
 */
( function () {
	'use strict';

	function shouldIgnoreVisit( url, el ) {
		if ( ! el ) return false;

		if ( el.closest( '[data-no-swup]' ) ) return true;
		if ( el.hasAttribute( 'download' ) ) return true;
		if ( el.getAttribute( 'target' ) === '_blank' ) return true;

		var href = ( el.getAttribute( 'href' ) || url || '' ).trim();
		if ( ! href || href === '#' || href.charAt( 0 ) === '#' ) return true;
		if ( href.indexOf( 'mailto:' ) === 0 || href.indexOf( 'tel:' ) === 0 || href.indexOf( 'javascript:' ) === 0 ) {
			return true;
		}

		var path = href.split( '?' )[ 0 ].split( '#' )[ 0 ].toLowerCase();
		if ( /\.(jpe?g|png|gif|webp|avif|svg|bmp|ico|pdf|zip|rar|7z|gz|mp4|webm|mp3|wav|ogg|doc|docx|xls|xlsx|ppt|pptx)$/i.test( path ) ) {
			return true;
		}

		if (
			el.closest(
				'[data-elementor-open-lightbox], [data-fancybox], [data-lightbox], [data-glightbox], [data-pswp], [data-pswp-src], [data-rel="lightbox"], [data-rel^="prettyPhoto"], [data-open-lightbox], [rel="lightbox"], [rel^="prettyPhoto"], [rel^="magnific"], [data-magnific], [data-toggle="lightbox"]'
			)
		) {
			return true;
		}

		if (
			el.matches &&
			el.matches(
				'.lightbox, .fancybox, .glightbox, .photoswipe, .zoom, .wd-zoom, .pswp-link, a.woocommerce-product-gallery__trigger'
			)
		) {
			return true;
		}

		var galleryParent = el.closest(
			'.woocommerce-product-gallery, .wd-gallery, .wd-product-gallery, .woodmart-product-gallery, .photoswipe, .pswp, .elementor-widget-image-gallery, .elementor-widget-gallery, .wp-block-gallery, .gallery, .tiled-gallery, .product-image-wrap'
		);
		if ( galleryParent && /\.(jpe?g|png|gif|webp|avif|svg|bmp)(\?|$)/i.test( href ) ) {
			return true;
		}

		if ( /add-to-cart=|remove_item=|undo_item|update_cart|apply_coupon/.test( href ) ) return true;
		if ( el.closest( '.ajax_add_to_cart, .add_to_cart_button, .remove_from_cart_button' ) ) return true;

		try {
			var u = new URL( href, window.location.origin );
			if ( u.pathname.indexOf( '/wp-admin' ) !== -1 || u.pathname.indexOf( '/wp-login' ) !== -1 ) return true;
			if ( u.origin !== window.location.origin ) return true;
		} catch ( e ) {
			return true;
		}

		return false;
	}

	function patchSwup() {
		if ( ! window.SLL || ! window.SLL.swup ) return false;
		var swup = window.SLL.swup;
		var prev = swup.options && swup.options.ignoreVisit;

		swup.options.ignoreVisit = function ( url, args ) {
			var el = args && args.el;
			if ( shouldIgnoreVisit( url, el ) ) return true;
			if ( typeof prev === 'function' && prev( url, args ) ) return true;
			return false;
		};

		window.SLL.shouldIgnoreVisit = shouldIgnoreVisit;
		if ( window.sllConfig && window.sllConfig.debug ) {
			console.log( '[SLL] Compat layer: lightbox/media ignoreVisit active' );
		}
		return true;
	}

	function boot() {
		if ( patchSwup() ) return;
		document.addEventListener( 'sll:ready', function () {
			patchSwup();
		} );
		setTimeout( patchSwup, 300 );
		setTimeout( patchSwup, 1200 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
