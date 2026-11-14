( function () {
	'use strict';

	const cfg = window.wpswupConfig || {};

	const log = ( ...args ) => {
		if ( cfg.debug ) console.log( '[WPSWUP]', ...args );
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
		'#wpswup-swup',
		'#swup',
		'.main-page-wrapper',
		'#main',
		'#content',
		'main',
		'#primary',
		'.site-content',
		'#page',
	];

	function resolveContainers() {
		if ( Array.isArray( cfg.containers ) && cfg.containers.length && ! cfg.containersAuto ) {
			return cfg.containers.filter( ( s ) => document.querySelector( s ) );
		}
		for ( const sel of AUTO_CONTAINER_CANDIDATES ) {
			if ( document.querySelector( sel ) ) return [ sel ];
		}
		return [ 'body' ];
	}

	function normUrl( u ) {
		try {
			const a = document.createElement( 'a' );
			a.href = u;
			return ( a.pathname || '' ) + ( a.search || '' );
		} catch ( e ) {
			return String( u || '' );
		}
	}

	/**
	 * Inject every stylesheet from the next page into <head> and wait until
	 * each has loaded (including CSS @import chains). Mimics full page load.
	 */
	function ensureStylesheets( doc ) {
		if ( ! doc || ! doc.head ) return Promise.resolve();

		const existing = new Map();
		document.head.querySelectorAll( 'link[rel="stylesheet"]' ).forEach( ( link ) => {
			const href = link.getAttribute( 'href' );
			if ( href ) existing.set( normUrl( href ), link );
		} );

		const pending = [];
		const nextKeys = new Set();

		doc.head.querySelectorAll( 'link[rel="stylesheet"]' ).forEach( ( nl ) => {
			const href = nl.getAttribute( 'href' );
			if ( ! href ) return;
			const key = normUrl( href );
			nextKeys.add( key );

			if ( existing.has( key ) ) {
				return;
			}

			const link = document.createElement( 'link' );
			link.rel = 'stylesheet';
			link.href = href;
			if ( nl.id ) link.id = nl.id;
			if ( nl.media ) link.media = nl.media;
			if ( nl.getAttribute( 'crossorigin' ) != null ) {
				link.crossOrigin = nl.getAttribute( 'crossorigin' ) || '';
			}

			const p = new Promise( ( resolve ) => {
				let done = false;
				const finish = () => {
					if ( done ) return;
					done = true;
					resolve();
				};
				link.addEventListener( 'load', finish );
				link.addEventListener( 'error', finish );
				setTimeout( finish, 8000 );
			} );
			pending.push( p );
			document.head.appendChild( link );
			log( 'Preload stylesheet', href );
		} );

		doc.head.querySelectorAll( 'style[id]' ).forEach( ( ns ) => {
			if ( ! ns.id ) return;
			const cur = document.getElementById( ns.id );
			if ( cur ) {
				if ( cur.textContent !== ns.textContent ) cur.textContent = ns.textContent;
			} else {
				const style = document.createElement( 'style' );
				style.id = ns.id;
				style.textContent = ns.textContent;
				document.head.appendChild( style );
			}
		} );

		existing.forEach( ( link, key ) => {
			if ( nextKeys.has( key ) ) return;
			const id = ( link.id || '' ).toLowerCase();
			const href = ( link.getAttribute( 'href' ) || '' ).toLowerCase();
			if ( /elementor-post-\d+|elementor\/css\/post-|post-\d+\.css|page-id-\d+/i.test( id + href ) ) {
				if ( link.parentNode ) link.parentNode.removeChild( link );
				log( 'Removed stale page CSS', href || id );
			}
		} );

		return Promise.all( pending ).then( () => {
			return new Promise( ( r ) => setTimeout( r, 30 ) );
		} );
	}

	/**
	 * Preload external scripts from next <head> that are not yet on the page.
	 */
	function ensureHeadScripts( doc ) {
		if ( ! doc || ! doc.head ) return Promise.resolve();

		const have = new Set();
		document.querySelectorAll( 'script[src]' ).forEach( ( s ) => {
			const src = s.getAttribute( 'src' );
			if ( src ) have.add( normUrl( src ) );
		} );

		const pending = [];
		doc.head.querySelectorAll( 'script[src]' ).forEach( ( ns ) => {
			const src = ns.getAttribute( 'src' );
			if ( ! src ) return;
			const key = normUrl( src );
			if ( have.has( key ) ) return;
			if ( /wpswup|\/swup@|@swup\//i.test( src ) ) return;

			const script = document.createElement( 'script' );
			script.src = src;
			if ( ns.type ) script.type = ns.type;
			if ( ns.async ) script.async = true;
			if ( ns.defer ) script.defer = true;
			const p = new Promise( ( resolve ) => {
				script.addEventListener( 'load', resolve );
				script.addEventListener( 'error', resolve );
				setTimeout( resolve, 8000 );
			} );
			pending.push( p );
			document.head.appendChild( script );
			log( 'Preload script', src );
		} );

		return Promise.all( pending );
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
						timeout: 8000,
						persistTags: ( el ) => {
							if ( ! el || ! el.tagName ) return false;
							const tag = el.tagName.toLowerCase();
							const id = ( el.id || '' ).toLowerCase();
							const href = ( el.getAttribute && ( el.getAttribute( 'href' ) || '' ) ) || '';
							const src = ( el.getAttribute && ( el.getAttribute( 'src' ) || '' ) ) || '';
							const combined = id + ' ' + href + ' ' + src;
							if ( /elementor-post-\d+|elementor\/css\/post-|elementor-post-css|post-\d+\.css|page-id-\d+/i.test( combined ) ) {
								return false;
							}
							if ( tag === 'style' && /elementor-post-\d+|page-css/i.test( id ) ) return false;
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
			if ( cfg.preload && cfg.cache !== false && typeof SwupPreloadPlugin !== 'undefined' ) {
				plugins.push( new SwupPreloadPlugin() );
			}
			if ( cfg.forms && typeof SwupFormsPlugin !== 'undefined' ) {
				plugins.push( new SwupFormsPlugin() );
			}

			const anim = ( cfg.animation || 'fade' ).toString();
			const mainEl = this.containers[ 0 ] || '#swup';
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
			if ( window.WPSWUP ) window.WPSWUP.swup = this.swup;
			this._bindEvents();
			log( 'Swup 4 initialized. Containers:', this.containers, 'plugins:', plugins.length );
		}

		_bindEvents() {
			const s = this.swup;
			if ( ! s || ! s.hooks ) return;

			// As soon as next HTML is available — start loading its CSS/JS in the background
			s.hooks.on( 'page:load', ( visit ) => {
				const doc = visit && visit.to && visit.to.document;
				if ( ! doc ) return;
				visit._wpswupAssets = Promise.all( [
					ensureStylesheets( doc ),
					ensureHeadScripts( doc ),
				] ).then( () => {
					log( 'Next page assets ready' );
				} );
			} );

			// Do NOT replace DOM until assets for the next page are fully ready
			s.hooks.before( 'content:replace', async ( visit ) => {
				const doc = visit && visit.to && visit.to.document;
				if ( visit && visit._wpswupAssets ) {
					await visit._wpswupAssets;
				} else if ( doc ) {
					await Promise.all( [ ensureStylesheets( doc ), ensureHeadScripts( doc ) ] );
				}
			} );

			s.hooks.on( 'visit:start', ( visit ) => {
				this.bus.emit( 'transitionStart', { swup: s, visit } );
				document.dispatchEvent( new CustomEvent( 'wpswup:transitionStart', { detail: { visit } } ) );
			} );

			s.hooks.on( 'content:replace', ( visit ) => {
				this.containers.forEach( ( sel ) => {
					const el = document.querySelector( sel );
					if ( el ) el.classList.add( 'transition-swup' );
				} );
			} );

			s.hooks.on( 'page:view', ( visit ) => {
				log( 'Swup: page:view', visit && visit.to && visit.to.url );
				this._reinitCompat( visit );
				this.bus.emit( 'pageView', { swup: s, visit } );
				document.dispatchEvent( new CustomEvent( 'wpswup:pageView', { detail: { visit } } ) );
			} );

			s.hooks.on( 'visit:end', ( visit ) => {
				this.bus.emit( 'visitEnd', { swup: s, visit } );
				document.dispatchEvent( new CustomEvent( 'wpswup:visitEnd', { detail: { visit } } ) );
			} );
		}

		_reinitCompat( visit ) {
			if ( cfg.compatWoo && typeof jQuery !== 'undefined' ) {
				try {
					jQuery( document.body ).trigger( 'wc_fragment_refresh' );
					jQuery( document.body ).trigger( 'updated_wc_div' );
				} catch ( e ) {}
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
				} catch ( e ) {}
			}
			if ( typeof window.woodmartThemeModule === 'object' ) {
				try {
					Object.keys( woodmartThemeModule ).forEach( ( key ) => {
						if ( typeof woodmartThemeModule[ key ] === 'function' ) {
							try {
								woodmartThemeModule[ key ]();
							} catch ( e ) {}
						}
					} );
				} catch ( e ) {}
			}
			if ( window.WPSWUP && typeof window.WPSWUP.scheduleCloseOverlays === 'function' ) {
				window.WPSWUP.scheduleCloseOverlays();
			}
			document.dispatchEvent( new CustomEvent( 'wpswup:afterPageTransition', { detail: { visit } } ) );
			if ( typeof jQuery !== 'undefined' ) {
				jQuery( document ).trigger( 'wpswup_after_page_transition' );
			}
		}
	}

	const bus = new EventBus();
	const swupManager = new SwupManager( bus );

	window.WPSWUP = Object.assign( window.WPSWUP || {}, {
		on: ( e, fn ) => bus.on( e, fn ),
		off: ( e, fn ) => bus.off( e, fn ),
		get swup() {
			return swupManager.swup;
		},
		get containers() {
			return swupManager.containers;
		},
		config: cfg,
	} );

	const boot = () => {
		swupManager.init();
		bus.emit( 'ready', { WPSWUP: window.WPSWUP } );
		document.dispatchEvent( new CustomEvent( 'wpswup:ready' ) );
		log( 'Ready (Swup 4).' );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
