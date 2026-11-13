/**
 * WP Swup – Elementor Pro CSS / body-class fix
 *
 * Elementor loads per-page CSS (elementor-post-{id}.css) in <head>.
 * Swup only swaps content containers, so we must merge head styles
 * and update body classes on every navigation.
 */
( function () {
	'use strict';

	var PAGE_CSS_RE = /elementor\/css\/post-|elementor-post-\d+|post-\d+\.css|elementor-frontend-css/i;

	function isPageSpecificAsset( el ) {
		if ( ! el || ! el.tagName ) return false;
		var tag = el.tagName.toLowerCase();
		if ( tag === 'link' ) {
			var href = el.getAttribute( 'href' ) || '';
			var id = el.id || '';
			return PAGE_CSS_RE.test( href ) || PAGE_CSS_RE.test( id ) || /elementor-post-/.test( id );
		}
		if ( tag === 'style' ) {
			var sid = el.id || '';
			return /elementor-post-|elementor-frontend/.test( sid );
		}
		return false;
	}

	function cssEscapeAttr( s ) {
		return String( s ).replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' );
	}

	function syncElementorAssets( visit ) {
		var doc = visit && visit.to && visit.to.document;
		if ( ! doc || ! doc.head ) return;

		Array.prototype.slice.call( document.head.querySelectorAll( 'link[rel="stylesheet"], style[id]' ) ).forEach( function ( el ) {
			if ( isPageSpecificAsset( el ) ) {
				var keep = false;
				if ( el.tagName === 'LINK' ) {
					var href = el.getAttribute( 'href' );
					if ( href && doc.head.querySelector( 'link[href="' + cssEscapeAttr( href ) + '"]' ) ) keep = true;
				} else if ( el.id && doc.getElementById( el.id ) ) {
					keep = true;
				}
				if ( ! keep && el.parentNode ) el.parentNode.removeChild( el );
			}
		} );

		var pending = [];
		Array.prototype.slice.call( doc.head.querySelectorAll( 'link[rel="stylesheet"], style' ) ).forEach( function ( el ) {
			if ( el.tagName === 'LINK' ) {
				var href = el.getAttribute( 'href' );
				if ( ! href ) return;
				if ( document.head.querySelector( 'link[href="' + cssEscapeAttr( href ) + '"]' ) ) return;
				var link = document.createElement( 'link' );
				link.rel = 'stylesheet';
				link.href = href;
				if ( el.id ) link.id = el.id;
				if ( el.media ) link.media = el.media;
				document.head.appendChild( link );
				pending.push(
					new Promise( function ( resolve ) {
						link.onload = resolve;
						link.onerror = resolve;
						setTimeout( resolve, 3000 );
					} )
				);
			} else if ( el.tagName === 'STYLE' ) {
				var id = el.id;
				if ( id && document.getElementById( id ) ) {
					document.getElementById( id ).textContent = el.textContent;
					return;
				}
				if ( ! id && ! /elementor/i.test( el.textContent || '' ) ) return;
				var style = document.createElement( 'style' );
				if ( id ) style.id = id;
				style.textContent = el.textContent;
				document.head.appendChild( style );
			}
		} );

		return Promise.all( pending );
	}

	function syncBodyClasses( visit ) {
		var doc = visit && visit.to && visit.to.document;
		if ( ! doc || ! doc.body ) return;

		var next = doc.body.className || '';
		var current = document.body.className || '';
		var preserve = [];
		current.split( /\s+/ ).forEach( function ( c ) {
			if ( /^(wd-opened|wd-scroll-lock|mfp-ready|wrapper-locked|modal-open|admin-bar)/.test( c ) ) {
				preserve.push( c );
			}
		} );

		document.body.className = next;
		preserve.forEach( function ( c ) {
			document.body.classList.add( c );
		} );

		if ( doc.documentElement && doc.documentElement.className ) {
			[ 'is-changing', 'is-animating', 'is-leaving', 'is-rendering', 'swup-enabled', 'swup-native' ].forEach(
				function ( c ) {
					if ( document.documentElement.classList.contains( c ) ) {
						document.documentElement.classList.add( c );
					}
				}
			);
			doc.documentElement.className.split( /\s+/ ).forEach( function ( c ) {
				if ( c && /elementor/.test( c ) ) document.documentElement.classList.add( c );
			} );
		}
	}

	function bind() {
		if ( ! window.SLL || ! window.SLL.swup || ! window.SLL.swup.hooks ) return false;
		var hooks = window.SLL.swup.hooks;

		if ( window.SLL.swup._sllElementorBound ) return true;
		window.SLL.swup._sllElementorBound = true;

		hooks.before( 'content:replace', function ( visit ) {
			return syncElementorAssets( visit );
		} );

		hooks.on( 'content:replace', function ( visit ) {
			syncBodyClasses( visit );
		} );

		hooks.on( 'page:view', function ( visit ) {
			syncBodyClasses( visit );
			if ( typeof elementorFrontend !== 'undefined' ) {
				try {
					if ( elementorFrontend.elementsHandler && elementorFrontend.elementsHandler.runReadyTrigger ) {
						var root = document.querySelector( '.elementor' ) || document.body;
						elementorFrontend.elementsHandler.runReadyTrigger( root );
					}
					if ( typeof jQuery !== 'undefined' ) {
						jQuery( window ).trigger( 'elementor/frontend/init' );
						jQuery( document ).trigger( 'elementor/lazyload/observe' );
					}
				} catch ( e ) {
					if ( window.sllConfig && window.sllConfig.debug ) console.warn( '[SLL] Elementor reinit', e );
				}
			}
		} );

		if ( window.sllConfig && window.sllConfig.debug ) {
			console.log( '[SLL] Elementor CSS sync bound' );
		}
		return true;
	}

	function boot() {
		if ( bind() ) return;
		document.addEventListener( 'sll:ready', bind );
		setTimeout( bind, 400 );
		setTimeout( bind, 1500 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
