/**
 * Swup Lazy Loader – sll-main.js
 *
 * Swup 4 compatible + zero theme edits:
 *  - Auto-detects main content containers (WoodMart, Elementor, common WP themes)
 *  - Injects .transition-swup on detected containers (no template changes needed)
 *  - Hooks API, visit object, WooCommerce & Elementor re-init
 *  - Lazy [data-render] sections via REST / admin-ajax
 */
( function () {
	'use strict';

	const cfg = window.sllConfig || {};

	const log = ( ...args ) => {
		if ( cfg.debug ) console.log( '[SLL]', ...args );
	};

	/* ── Event Bus ───────────────────────────────────────────────────────── */

	class EventBus {
		constructor() {
			this._listeners = {};
		}
		on( event, fn ) {
			( this._listeners[ event ] = this._listeners[ event ] || [] ).push( fn );
			return this;
		}
		off( event, fn ) {
			if ( this._listeners[ event ] ) {
				this._listeners[ event ] = this._listeners[ event ].filter( ( f ) => f !== fn );
			}
		}
		emit( event, data ) {
			( this._listeners[ event ] || [] ).forEach( ( fn ) => fn( data ) );
		}
	}

	/* ── Container auto-detection (no theme edits) ───────────────────────── */

	/**
	 * Ordered candidates for popular themes / builders.
	 * Prefer unique IDs. First match that exists in the DOM wins (unless
	 * user configured explicit containers).
	 */
	const AUTO_CONTAINER_CANDIDATES = [
		// User / plugin explicit
		'#sll-swup',
		'#swup',
		// Common WordPress / block themes
		'#page',
		'#content',
		'#primary',
		'#main',
		'main#main',
		'main.site-main',
		'#site-content',
		'.site-content',
		'#main-content',
		// WoodMart
		'.main-page-wrapper',
		'.wd-page-content',
		'#main-page-wrapper',
		'.website-wrapper > .main-page-wrapper',
		// Elementor
		'.elementor-location-single',
		'.elementor-location-archive',
		'div[data-elementor-type="wp-page"]',
		'div[data-elementor-type="wp-post"]',
		'.elementor.elementor-page',
		// WooCommerce
		'#main.site-main',
		'.woocommerce-page #main',
		// Generic fallbacks
		'main',
		'article.post',
		'#wrapper',
		'.content-area',
	];

	/**
	 * Resolve which containers Swup should replace.
	 * - If settings has explicit selectors → use those (must exist).
	 * - Else auto-detect first matching candidate that exists in DOM.
	 * Always adds class "transition-swup" so animationSelector works.
	 *
	 * @returns {string[]} CSS selectors unique enough for Swup 4
	 */
	function resolveContainers() {
		const explicit = ( cfg.containers || [] ).filter( Boolean );
		const useAuto = cfg.containersAuto !== false && explicit.length === 0;

		let found = [];

		if ( ! useAuto && explicit.length ) {
			explicit.forEach( ( sel ) => {
				const el = document.querySelector( sel );
				if ( el ) {
					el.classList.add( 'transition-swup' );
					// Prefer id-based unique selector for Swup 4
					found.push( ensureUniqueSelector( el, sel ) );
				} else {
					log( 'Configured container not found:', sel );
				}
			} );
		}

		if ( ! found.length ) {
			for ( const sel of AUTO_CONTAINER_CANDIDATES ) {
				const el = document.querySelector( sel );
				if ( ! el ) continue;
				// Skip tiny / chrome elements
				if ( el === document.body || el === document.documentElement ) continue;
				if ( el.matches( 'header, footer, nav, .site-header, .site-footer' ) ) continue;

				el.classList.add( 'transition-swup' );
				const unique = ensureUniqueSelector( el, sel );
				found.push( unique );
				log( 'Auto-detected container:', unique, el );
				break; // one main content container is enough
			}
		}

		if ( ! found.length ) {
			// Last resort: wrap is not possible without theme edit;
			// use body children is unsafe. Disable Swup gracefully.
			console.warn(
				'[SLL] No suitable content container found. Swup disabled. ' +
					'Set “Swap Containers” in plugin settings or add id="sll-swup" to your main content wrapper.'
			);
		}

		return found;
	}

	/**
	 * Swup 4 replaces ONE element per selector and the SAME selector must
	 * exist in the server HTML of every page. Never invent ids/classes that
	 * only live in the current DOM — prefer selectors already in the theme
	 * (WoodMart .main-page-wrapper, #main, #content, etc.).
	 */
	function ensureUniqueSelector( el, fallbackSel ) {
		if ( el.id ) {
			return '#' + ( window.CSS && CSS.escape ? CSS.escape( el.id ) : el.id );
		}
		// Keep theme selectors as-is so the next page HTML still matches
		return fallbackSel;
	}

	/* ── Lazy Renderer ───────────────────────────────────────────────────── */

	class LazyRenderer {
		constructor( bus ) {
			this.bus = bus;
			this.observer = null;
			this._loaded = new Set();
		}

		init() {
			if ( ! cfg.lazyEnabled ) return;
			this._createObserver();
			this._observeAll();
		}

		refresh() {
			if ( ! cfg.lazyEnabled ) return;
			log( 'LazyRenderer.refresh()' );
			this._observeAll();
		}

		_createObserver() {
			this.observer = new IntersectionObserver(
				( entries ) => {
					entries.forEach( ( entry ) => {
						if ( entry.isIntersecting ) {
							this._load( entry.target );
						}
					} );
				},
				{
					threshold: cfg.lazyThreshold || 0.1,
					rootMargin: cfg.lazyRootMargin || '0px 0px 200px 0px',
				}
			);
		}

		_observeAll() {
			document.querySelectorAll( '[data-render]' ).forEach( ( el ) => {
				if ( el.classList.contains( 'sll-loaded' ) ) return;
				if ( el.classList.contains( 'sll-loading' ) ) return;
				const key = this._key( el );
				if ( ! this._loaded.has( key ) ) {
					this.observer.observe( el );
					log( 'Observing:', el.dataset.render );
				}
			} );
		}

		_key( el ) {
			return (
				el.dataset.render +
				'__' +
				( el.id ||
					Array.from( ( el.parentNode || document.body ).children ).indexOf( el ) )
			);
		}

		async _load( el ) {
			const template = el.dataset.render;
			const key = this._key( el );

			if ( this._loaded.has( key ) ) return;
			this._loaded.add( key );
			this.observer.unobserve( el );

			let args = {};
			try {
				if ( el.dataset.renderArgs ) args = JSON.parse( el.dataset.renderArgs );
			} catch ( e ) {}

			el.setAttribute( 'aria-busy', 'true' );
			el.classList.add( 'sll-loading' );

			this.bus.emit( 'lazyBeforeLoad', { el, template, args } );
			log( 'Loading section:', template, args );

			try {
				let html;

				if ( cfg.restUrl ) {
					const params = new URLSearchParams( args );
					const url =
						cfg.restUrl +
						encodeURIComponent( template ) +
						( params.toString() ? '?' + params : '' );
					const res = await fetch( url, {
						headers: { 'X-WP-Nonce': cfg.restNonce || '' },
					} );
					if ( res.ok ) {
						const json = await res.json();
						if ( json.success ) html = json.html;
					}
				}

				if ( html === undefined ) {
					const body = new URLSearchParams( {
						action: 'sll_render_section',
						nonce: cfg.nonce,
						template: template,
						args: JSON.stringify( args ),
					} );
					const res = await fetch( cfg.ajaxUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: body.toString(),
					} );
					if ( ! res.ok ) throw new Error( `HTTP ${ res.status }` );
					const json = await res.json();
					if ( ! json.success ) throw new Error( json.data?.message || 'Server error' );
					html = json.data.html;
				}

				el.classList.add( 'sll-entering' );
				el.innerHTML = html;
				el.removeAttribute( 'aria-busy' );
				el.classList.remove( 'sll-loading' );

				el.querySelectorAll( 'script' ).forEach( ( oldScript ) => {
					const s = document.createElement( 'script' );
					[ ...oldScript.attributes ].forEach( ( a ) => s.setAttribute( a.name, a.value ) );
					s.textContent = oldScript.textContent;
					oldScript.replaceWith( s );
				} );

				requestAnimationFrame( () => {
					el.classList.remove( 'sll-entering' );
					el.classList.add( 'sll-loaded' );
				} );

				this.bus.emit( 'lazyLoaded', { el, template, html } );
				log( 'Loaded section:', template );
			} catch ( err ) {
				console.error( '[SLL] Failed to load section:', template, err );
				el.classList.remove( 'sll-loading', 'sll-entering' );
				el.classList.add( 'sll-error' );
				el.removeAttribute( 'aria-busy' );
				this._loaded.delete( key );
				this.bus.emit( 'lazyError', { el, template, error: err } );
			}
		}
	}

	/* ── Swup Manager (Swup 4) ───────────────────────────────────────────── */

	class SwupManager {
		constructor( bus, lazyRenderer ) {
			this.bus = bus;
			this.lazyRenderer = lazyRenderer;
			this.swup = null;
			this.containers = [];
		}

		init() {
			if ( ! cfg.swupEnabled || typeof Swup === 'undefined' ) {
				log( 'Swup disabled or not loaded.' );
				return;
			}

			this.containers = resolveContainers();
			if ( ! this.containers.length ) {
				return;
			}

			const plugins = [];

			if ( typeof SwupHeadPlugin !== 'undefined' ) {
				plugins.push(
					new SwupHeadPlugin( {
						persistAssets: true,
						awaitAssets: true,
					} )
				);
			}

			if ( cfg.scrollPlugin && typeof SwupScrollPlugin !== 'undefined' ) {
				plugins.push(
					new SwupScrollPlugin( {
						doScrollingRightAway: false,
						animateScroll: true,
					} )
				);
			}

			if ( cfg.preload && typeof SwupPreloadPlugin !== 'undefined' ) {
				plugins.push( new SwupPreloadPlugin() );
			}

			if ( cfg.forms && typeof SwupFormsPlugin !== 'undefined' ) {
				plugins.push( new SwupFormsPlugin() );
			}

			const extraPlugins = [];
			this.bus.emit( 'swupPlugins', { plugins: extraPlugins } );
			plugins.push( ...extraPlugins );

			const options = {
				containers: this.containers,
				linkSelector: cfg.linkSelector,
				cache: cfg.cache !== false,
				plugins,
				animationSelector: '[class*="transition-"]',
				animationScope: cfg.animationScope || 'html',
				native: !! cfg.native,
				ignoreVisit: ( url, { el } = {} ) => {
					if ( ! el ) return false;
					if ( el.closest( '[data-no-swup]' ) ) return true;
					// WooCommerce actions that need full reload
					const href = el.getAttribute( 'href' ) || '';
					if ( href.includes( 'add-to-cart=' ) || href.includes( 'remove_item=' ) ) {
						return true;
					}
					if ( el.closest( '.add_to_cart_button, .ajax_add_to_cart' ) && el.tagName === 'A' ) {
						// Let WC AJAX handle; still allow navigation if it's a real product link
					}
					return false;
				},
			};

			this.swup = new Swup( options );
			this._bindEvents();
			log( 'Swup 4 initialized. Containers:', this.containers, 'plugins:', plugins.length );
		}

		_bindEvents() {
			const s = this.swup;
			if ( ! s || ! s.hooks ) return;

			s.hooks.on( 'visit:start', ( visit ) => {
				this.bus.emit( 'transitionStart', { swup: s, visit } );
				document.dispatchEvent(
					new CustomEvent( 'sll:transitionStart', { detail: { visit } } )
				);
			} );

			s.hooks.before( 'content:replace', ( visit ) => {
				this.bus.emit( 'beforeContentReplace', { swup: s, visit } );
			} );

			s.hooks.on( 'content:replace', ( visit ) => {
				// Next page HTML may not have our injected id/class — re-apply on new nodes
				this.containers.forEach( ( sel ) => {
					const el = document.querySelector( sel );
					if ( el ) el.classList.add( 'transition-swup' );
				} );
			} );

			s.hooks.on( 'page:view', ( visit ) => {
				log( 'Swup: page:view', visit?.to?.url );

				this.lazyRenderer._loaded.clear();
				this.lazyRenderer.refresh();
				this._reinitCompat( visit );

				this.bus.emit( 'pageView', { swup: s, visit } );
				document.dispatchEvent(
					new CustomEvent( 'sll:pageView', { detail: { visit } } )
				);
			} );

			s.hooks.on( 'visit:end', ( visit ) => {
				this.bus.emit( 'visitEnd', { swup: s, visit } );
				document.dispatchEvent(
					new CustomEvent( 'sll:visitEnd', { detail: { visit } } )
				);
			} );

			s.hooks.on( 'fetch:error', ( visit, args ) => {
				console.error( '[SLL] Swup fetch error', args?.error || args );
				this.bus.emit( 'fetchError', { swup: s, visit, error: args } );
			} );
		}

		_reinitCompat( visit ) {
			if ( cfg.compatWoo && typeof jQuery !== 'undefined' ) {
				try {
					jQuery( document.body ).trigger( 'wc_fragment_refresh' );
					jQuery( document.body ).trigger( 'updated_wc_div' );
					if ( typeof jQuery.fn.wc_product_gallery === 'function' ) {
						jQuery( '.woocommerce-product-gallery' ).each( function () {
							jQuery( this ).wc_product_gallery();
						} );
					}
				} catch ( e ) {
					log( 'WooCommerce reinit warning', e );
				}
			}

			if ( cfg.compatElementor && typeof elementorFrontend !== 'undefined' ) {
				try {
					if ( elementorFrontend.elementsHandler ) {
						this.containers
							.map( ( sel ) => document.querySelector( sel ) )
							.filter( Boolean )
							.forEach( ( container ) => {
								elementorFrontend.elementsHandler.runReadyTrigger( container );
							} );
					}
					if ( typeof jQuery !== 'undefined' ) {
						jQuery( window ).trigger( 'elementor/frontend/init' );
					}
				} catch ( e ) {
					log( 'Elementor reinit warning', e );
				}
			}

			// WoodMart theme hooks (if present)
			if ( typeof jQuery !== 'undefined' && jQuery.fn && window.woodmartThemeModule ) {
				try {
					if ( typeof woodmartThemeModule === 'object' ) {
						Object.keys( woodmartThemeModule ).forEach( ( key ) => {
							if ( typeof woodmartThemeModule[ key ] === 'function' ) {
								try {
									woodmartThemeModule[ key ]();
								} catch ( e ) {}
							}
						} );
					}
				} catch ( e ) {
					log( 'WoodMart reinit warning', e );
				}
			}

			document.dispatchEvent(
				new CustomEvent( 'sll:afterPageTransition', { detail: { visit } } )
			);
			if ( typeof jQuery !== 'undefined' ) {
				jQuery( document ).trigger( 'sll_after_page_transition' );
			}
		}
	}

	/* ── Public API ──────────────────────────────────────────────────────── */

	const bus = new EventBus();
	const lazyRenderer = new LazyRenderer( bus );
	const swupManager = new SwupManager( bus, lazyRenderer );

	window.SLL = {
		on: ( e, fn ) => bus.on( e, fn ),
		off: ( e, fn ) => bus.off( e, fn ),

		reload( selectorOrEl ) {
			const el =
				typeof selectorOrEl === 'string'
					? document.querySelector( selectorOrEl )
					: selectorOrEl;
			if ( ! el || ! el.dataset.render ) return;
			el.classList.remove( 'sll-loaded', 'sll-error' );
			lazyRenderer._loaded.delete( lazyRenderer._key( el ) );
			if ( lazyRenderer.observer ) {
				lazyRenderer.observer.observe( el );
			}
		},

		/** Force re-detect containers (e.g. after dynamic layout change) */
		redetectContainers() {
			return resolveContainers();
		},

		get swup() {
			return swupManager.swup;
		},
		get containers() {
			return swupManager.containers;
		},
		get lazy() {
			return lazyRenderer;
		},
		config: cfg,
	};

	/* ── Boot ────────────────────────────────────────────────────────────── */

	const boot = () => {
		lazyRenderer.init();
		swupManager.init();
		bus.emit( 'ready', { SLL: window.SLL } );
		document.dispatchEvent( new CustomEvent( 'sll:ready' ) );
		log( 'Ready (Swup 4, auto-container).' );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
