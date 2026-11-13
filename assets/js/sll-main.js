( function () {
	'use strict';

	const cfg = window.sllConfig || {};

	const log = ( ...args ) => {
		if ( cfg.debug ) console.log( '[SLL]', ...args );
	};

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

	const AUTO_CONTAINER_CANDIDATES = [
		'#sll-swup',
		'#swup',
		'#page',
		'#content',
		'#primary',
		'#main',
		'main#main',
		'main.site-main',
		'#site-content',
		'.site-content',
		'#main-content',
		'.main-page-wrapper',
		'.wd-page-content',
		'#main-page-wrapper',
		'.website-wrapper > .main-page-wrapper',
		'.elementor-location-single',
		'.elementor-location-archive',
		'div[data-elementor-type="wp-page"]',
		'div[data-elementor-type="wp-post"]',
		'.elementor.elementor-page',
		'#main.site-main',
		'.woocommerce-page #main',
		'main',
		'article.post',
		'#wrapper',
		'.content-area',
	];

	function resolveContainers() {
		const explicit = ( cfg.containers || [] ).filter( Boolean );
		const useAuto = cfg.containersAuto !== false && explicit.length === 0;
		let found = [];
		if ( ! useAuto && explicit.length ) {
			explicit.forEach( ( sel ) => {
				const el = document.querySelector( sel );
				if ( el ) {
					el.classList.add( 'transition-swup' );
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
				if ( el === document.body || el === document.documentElement ) continue;
				if ( el.matches( 'header, footer, nav, .site-header, .site-footer' ) ) continue;
				el.classList.add( 'transition-swup' );
				found.push( ensureUniqueSelector( el, sel ) );
				log( 'Auto-detected container:', found[ found.length - 1 ], el );
				break;
			}
		}
		if ( ! found.length ) {
			console.warn( '[SLL] No suitable content container found. Swup disabled. Set Swap Containers in settings or add id=sll-swup.' );
		}
		return found;
	}

	function ensureUniqueSelector( el, fallbackSel ) {
		if ( el.id ) {
			return '#' + ( window.CSS && CSS.escape ? CSS.escape( el.id ) : el.id );
		}
		return fallbackSel;
	}

	class SwupManager {
		constructor( bus ) {
			this.bus = bus;
			this.swup = null;
			this.containers = [];
		}
		init() {
			if ( ! cfg.swupEnabled || typeof Swup === 'undefined' ) {
				log( 'Swup disabled or not loaded.' );
				return;
			}
			this.containers = resolveContainers();
			if ( ! this.containers.length ) return;

			const plugins = [];

			if ( typeof SwupHeadPlugin !== 'undefined' ) {
				plugins.push(
					new SwupHeadPlugin( {
						awaitAssets: true,
						timeout: 4000,
						persistTags: ( el ) => {
							if ( ! el || ! el.tagName ) return false;
							const tag = el.tagName.toLowerCase();
							const id = ( el.id || '' ).toLowerCase();
							const href = ( el.getAttribute && ( el.getAttribute( 'href' ) || '' ) ) || '';
							const src = ( el.getAttribute && ( el.getAttribute( 'src' ) || '' ) ) || '';
							if ( /elementor-post-\d+|elementor\/css\/post-|elementor-post-css/i.test( id + href ) ) return false;
							if ( tag === 'style' && /elementor-post-\d+/i.test( id ) ) return false;
							if ( tag === 'link' && el.rel === 'stylesheet' ) return true;
							if ( tag === 'script' && ( src || el.src ) ) return true;
							if ( tag === 'style' ) return true;
							return false;
						},
					} )
				);
			}

			if ( typeof SwupProgressPlugin !== 'undefined' ) {
				plugins.push(
					new SwupProgressPlugin( {
						className: 'swup-progress-bar',
						transition: 300,
						delay: 0,
						initialValue: 0.2,
						minValue: 0.1,
						finishAnimation: true,
					} )
				);
			}

			if ( cfg.scrollPlugin && typeof SwupScrollPlugin !== 'undefined' ) {
				plugins.push( new SwupScrollPlugin( { doScrollingRightAway: false, animateScroll: true } ) );
			}
			if ( cfg.preload && typeof SwupPreloadPlugin !== 'undefined' ) {
				plugins.push( new SwupPreloadPlugin() );
			}
			if ( cfg.forms && typeof SwupFormsPlugin !== 'undefined' ) {
				plugins.push( new SwupFormsPlugin() );
			}

			// Official Swup themes (enqueued by PHP based on selected animation)
			const anim = ( cfg.animation || 'fade' ).toString();
			const mainEl = ( this.containers && this.containers[0] ) ? this.containers[0] : '#swup';
			if ( anim === 'fade' && typeof SwupFadeTheme !== 'undefined' ) {
				plugins.push( new SwupFadeTheme( { mainElement: mainEl } ) );
			} else if ( ( anim === 'slide' || anim === 'slide_reversed' ) && typeof SwupSlideTheme !== 'undefined' ) {
				plugins.push( new SwupSlideTheme( { mainElement: mainEl, reversed: anim === 'slide_reversed' } ) );
			} else if ( anim.indexOf( 'overlay' ) === 0 && typeof SwupOverlayTheme !== 'undefined' ) {
				let dir = 'to-right';
				if ( anim === 'overlay_left' ) dir = 'to-left';
				else if ( anim === 'overlay_top' ) dir = 'to-top';
				else if ( anim === 'overlay_bottom' ) dir = 'to-bottom';
				plugins.push( new SwupOverlayTheme( { direction: dir } ) );
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
					const href = el.getAttribute( 'href' ) || '';
					if ( href.includes( 'add-to-cart=' ) || href.includes( 'remove_item=' ) ) return true;
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
				document.dispatchEvent( new CustomEvent( 'sll:transitionStart', { detail: { visit } } ) );
			} );
			s.hooks.before( 'content:replace', ( visit ) => {
				this.bus.emit( 'beforeContentReplace', { swup: s, visit } );
			} );
			s.hooks.on( 'content:replace', () => {
				this.containers.forEach( ( sel ) => {
					const el = document.querySelector( sel );
					if ( el ) el.classList.add( 'transition-swup' );
				} );
			} );
			s.hooks.on( 'page:view', ( visit ) => {
				log( 'Swup: page:view', visit && visit.to && visit.to.url );
				this._reinitCompat( visit );
				this.bus.emit( 'pageView', { swup: s, visit } );
				document.dispatchEvent( new CustomEvent( 'sll:pageView', { detail: { visit } } ) );
			} );
			s.hooks.on( 'visit:end', ( visit ) => {
				this.bus.emit( 'visitEnd', { swup: s, visit } );
				document.dispatchEvent( new CustomEvent( 'sll:visitEnd', { detail: { visit } } ) );
			} );
			s.hooks.on( 'fetch:error', ( visit, args ) => {
				console.error( '[SLL] Swup fetch error', ( args && args.error ) || args );
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
			if ( typeof jQuery !== 'undefined' && jQuery.fn && window.woodmartThemeModule ) {
				try {
					if ( typeof woodmartThemeModule === 'object' ) {
						Object.keys( woodmartThemeModule ).forEach( ( key ) => {
							if ( typeof woodmartThemeModule[ key ] === 'function' ) {
								try { woodmartThemeModule[ key ](); } catch ( e ) {}
							}
						} );
					}
				} catch ( e ) {
					log( 'WoodMart reinit warning', e );
				}
			}
			// Close WoodMart overlays AFTER theme module reinit
			if ( window.SLL && typeof window.SLL.scheduleCloseOverlays === 'function' ) {
				window.SLL.scheduleCloseOverlays();
			} else if ( window.SLL && typeof window.SLL.closeOverlays === 'function' ) {
				window.SLL.closeOverlays();
				setTimeout( function () { window.SLL.closeOverlays(); }, 50 );
				setTimeout( function () { window.SLL.closeOverlays(); }, 200 );
			}
			document.dispatchEvent( new CustomEvent( 'sll:afterPageTransition', { detail: { visit } } ) );
			if ( typeof jQuery !== 'undefined' ) {
				jQuery( document ).trigger( 'sll_after_page_transition' );
			}
		}
	}

	const bus = new EventBus();
	const swupManager = new SwupManager( bus );

	window.SLL = {
		on: ( e, fn ) => bus.on( e, fn ),
		off: ( e, fn ) => bus.off( e, fn ),
		redetectContainers() {
			return resolveContainers();
		},
		get swup() {
			return swupManager.swup;
		},
		get containers() {
			return swupManager.containers;
		},
		config: cfg,
	};

	const boot = () => {
		swupManager.init();
		bus.emit( 'ready', { SLL: window.SLL } );
		document.dispatchEvent( new CustomEvent( 'sll:ready' ) );
		log( 'Ready (Swup 4).' );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
