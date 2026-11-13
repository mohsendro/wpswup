/**
 * WP Swup – Elementor Pro CSS / body-class / frontend re-init
 *
 * Elementor loads per-page CSS (elementor-post-{id}.css) and body classes
 * (elementor-page-{id}). Swup only swaps content containers, so we must:
 *  1. Ensure page-specific CSS from the next page is in <head> and loaded
 *  2. Swap body/html classes that Elementor depends on
 *  3. Re-run Elementor frontend handlers after content is in the DOM
 *
 * HeadPlugin (awaitAssets + persistTags) does the main swap; this file is
 * the safety net for Elementor-specific edge cases.
 */
( function () {
	'use strict';

	var PAGE_CSS_RE = /elementor\/css\/post-|elementor-post-\d+|elementor-post-css|post-\d+\.css/i;
	var PRESERVE_BODY_RE = /^(wd-opened|wd-scroll-lock|mfp-ready|wrapper-locked|modal-open|admin-bar|logged-in|swup-enabled|sll-)/;

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
			return /elementor-post-\d+|elementor-frontend/.test( sid );
		}
		return false;
	}

	function cssEscapeAttr( s ) {
		return String( s ).replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' );
	}

	function normUrl( u ) {
		try {
			var a = document.createElement( 'a' );
			a.href = u;
			return ( a.pathname || '' ) + ( a.search || '' );
		} catch ( e ) {
			return String( u || '' );
		}
	}

	function findLinkByHref( href ) {
		if ( ! href ) return null;
		var n = normUrl( href );
		var links = document.head.querySelectorAll( 'link[rel="stylesheet"]' );
		for ( var i = 0; i < links.length; i++ ) {
			if ( normUrl( links[ i ].getAttribute( 'href' ) || '' ) === n ) return links[ i ];
		}
		return null;
	}

	function syncElementorAssets( visit ) {
		var doc = visit && visit.to && visit.to.document;
		if ( ! doc || ! doc.head ) return Promise.resolve();

		Array.prototype.slice
			.call( document.head.querySelectorAll( 'link[rel="stylesheet"], style[id]' ) )
			.forEach( function ( el ) {
				if ( ! isPageSpecificAsset( el ) ) return;
				var keep = false;
				if ( el.tagName === 'LINK' ) {
					var href = el.getAttribute( 'href' );
					if ( href ) {
						var n = normUrl( href );
						Array.prototype.slice.call( doc.head.querySelectorAll( 'link[rel="stylesheet"]' ) ).some( function ( nl ) {
							if ( normUrl( nl.getAttribute( 'href' ) || '' ) === n ) {
								keep = true;
								return true;
							}
							return false;
						} );
					}
				} else if ( el.id && doc.getElementById( el.id ) ) {
					keep = true;
				}
				if ( ! keep && el.parentNode ) {
					el.parentNode.removeChild( el );
				}
			} );

		var pending = [];

		Array.prototype.slice
			.call( doc.head.querySelectorAll( 'link[rel="stylesheet"], style' ) )
			.forEach( function ( el ) {
				if ( el.tagName === 'LINK' ) {
					var href = el.getAttribute( 'href' );
					if ( ! href ) return;
					if ( ! isPageSpecificAsset( el ) && ! /elementor/i.test( href + ( el.id || '' ) ) ) return;
					if ( findLinkByHref( href ) ) return;

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
							setTimeout( resolve, 3500 );
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
			if ( c && PRESERVE_BODY_RE.test( c ) ) preserve.push( c );
		} );

		document.body.className = next;
		preserve.forEach( function ( c ) {
			document.body.classList.add( c );
		} );

		if ( doc.documentElement ) {
			var keepHtml = [ 'is-changing', 'is-animating', 'is-leaving', 'is-rendering', 'swup-enabled', 'swup-native' ];
			keepHtml.forEach( function ( c ) {
				if ( document.documentElement.classList.contains( c ) ) {
					document.documentElement.classList.add( c );
				}
			} );
			( doc.documentElement.className || '' ).split( /\s+/ ).forEach( function ( c ) {
				if ( c && /elementor/i.test( c ) ) {
					document.documentElement.classList.add( c );
				}
			} );
		}
	}

	function reinitElementor( visit ) {
		if ( typeof elementorFrontend === 'undefined' ) return;

		try {
			var root =
				document.querySelector( '.elementor' ) ||
				document.querySelector( '[data-elementor-type]' ) ||
				document.body;

			var doc = visit && visit.to && visit.to.document;
			if ( doc && elementorFrontend.config ) {
				var body = doc.body;
				if ( body ) {
					var m = ( body.className || '' ).match( /elementor-page-(\d+)/ );
					if ( m && elementorFrontend.config.post ) {
						elementorFrontend.config.post.id = parseInt( m[ 1 ], 10 );
					}
				}
			}

			if ( elementorFrontend.elementsHandler && elementorFrontend.elementsHandler.runReadyTrigger ) {
				var scopes = document.querySelectorAll( '.elementor, [data-elementor-type], .elementor-element' );
				if ( scopes.length ) {
					Array.prototype.forEach.call( scopes, function ( el ) {
						try {
							elementorFrontend.elementsHandler.runReadyTrigger( el );
						} catch ( err ) {}
					} );
				} else {
					elementorFrontend.elementsHandler.runReadyTrigger( root );
				}
			}

			if ( typeof jQuery !== 'undefined' ) {
				jQuery( window ).trigger( 'elementor/frontend/init' );
				jQuery( document ).trigger( 'elementor/lazyload/observe' );
				jQuery( window ).trigger( 'resize' );
			}

			if ( elementorFrontend.modules && elementorFrontend.modules.stretch ) {
				try {
					elementorFrontend.modules.stretch.onInit();
				} catch ( e2 ) {}
			}
		} catch ( e ) {
			if ( window.sllConfig && window.sllConfig.debug ) {
				console.warn( '[SLL] Elementor reinit', e );
			}
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
			setTimeout( function () {
				reinitElementor( visit );
			}, 30 );
		} );

		if ( window.sllConfig && window.sllConfig.debug ) {
			console.log( '[SLL] Elementor CSS + reinit bound' );
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
