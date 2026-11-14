( function () {
	'use strict';

	// Prevent ScriptsPlugin (or any re-injection) from creating a second Swup instance.
	if ( window.__WPSWUP_BOOTED ) {
		return;
	}
	window.__WPSWUP_BOOTED = true;

	const cfg = window.wpswupConfig || {};
	const ASSET_TIMEOUT = Math.max( 1000, parseInt( cfg.assetTimeout, 10 ) || 8000 );

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

	function withTimeout( promise, ms ) {
		return Promise.race( [
			promise,
			new Promise( ( resolve ) => setTimeout( resolve, ms ) ),
		] );
	}

	function ensureStylesheets( doc, containers ) {
		if ( ! doc ) return Promise.resolve();

		const existing = new Map();
		document.querySelectorAll( 'link[rel="stylesheet"]' ).forEach( ( link ) => {
			const href = link.getAttribute( 'href' );
			if ( href ) existing.set( normUrl( href ), link );
		} );

		const pending = [];
		const nextKeys = new Set();
		const containerEls = ( containers || [] )
			.map( ( s ) => {
				try {
					return doc.querySelector( s );
				} catch ( e ) {
					return null;
				}
			} )
			.filter( Boolean );

		const isInsideContainer = ( el ) =>
			containerEls.some( ( c ) => c === el || ( c.contains && c.contains( el ) ) );

		doc.querySelectorAll( 'link[rel="stylesheet"]' ).forEach( ( nl ) => {
			const href = nl.getAttribute( 'href' );
			if ( ! href ) return;
			const key = normUrl( href );
			nextKeys.add( key );
			if ( existing.has( key ) ) return;

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
				setTimeout( finish, ASSET_TIMEOUT );
			} );
			pending.push( p );
			document.head.appendChild( link );
			log( 'Preload stylesheet', href );
		} );

		doc.querySelectorAll( 'style[id]' ).forEach( ( ns ) => {
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

		doc.querySelectorAll( 'style:not([id])' ).forEach( ( ns ) => {
			if ( isInsideContainer( ns ) ) return;
			const text = ( ns.textContent || '' ).trim();
			if ( ! text ) return;
			const already = Array.from( document.querySelectorAll( 'style:not([id])' ) ).some(
				( s ) => ( s.textContent || '' ).trim() === text
			);
			if ( already ) return;
			const style = document.createElement( 'style' );
			style.setAttribute( 'data-wpswup-anon', '1' );
			style.textContent = ns.textContent;
			document.head.appendChild( style );
			log( 'Injected anonymous style block' );
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

	function ensurePageScripts( doc ) {
		if ( ! doc ) return Promise.resolve();

		const have = new Set();
		document.querySelectorAll( 'script[src]' ).forEach( ( s ) => {
			const src = s.getAttribute( 'src' );
			if ( src ) have.add( normUrl( src ) );
		} );

		const pending = [];

		doc.querySelectorAll( 'script[id$="-js-extra"], script[id$="-js-before"], script[id$="-js-after"]' ).forEach( ( ns ) => {
			if ( ! ns.id ) return;
			const cur = document.getElementById( ns.id );
			if ( cur ) {
				if ( cur.textContent !== ns.textContent ) {
					cur.textContent = ns.textContent;
					log( 'Updated localized block', ns.id );
				}
			} else {
				const script = document.createElement( 'script' );
				script.id = ns.id;
				script.type = ns.type || 'text/javascript';
				script.textContent = ns.textContent;
				document.head.appendChild( script );
				log( 'Injected localized block', ns.id );
			}
		} );

		doc.querySelectorAll( 'script[src]' ).forEach( ( ns ) => {
			if ( ns.hasAttribute( 'data-swup-ignore-script' ) ) return;
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
			if ( ns.crossOrigin ) script.crossOrigin = ns.crossOrigin;
			const p = new Promise( ( resolve ) => {
				script.addEventListener( 'load', resolve );
				script.addEventListener( 'error', resolve );
				setTimeout( resolve, ASSET_TIMEOUT );
			} );
			pending.push( p );
			document.head.appendChild( script );
			log( 'Preload script', src );
		} );

		return Promise.all( pending );
	}

	function ensureMediaAndImages( doc, containers ) {
		if ( ! cfg.awaitMedia || ! doc ) return Promise.resolve();

		const roots = [];
		( containers || [] ).forEach( ( sel ) => {
			try {
				const el = doc.querySelector( sel );
				if ( el ) roots.push( el );
			} catch ( e ) {}
		} );
		if ( ! roots.length && doc.body ) roots.push( doc.body );

		const urls = new Set();
		const pending = [];

		const collectUrl = ( url ) => {
			if ( ! url || typeof url !== 'string' ) return;
			const clean = url.trim().replace( /^url\(["']?/, '' ).replace( /["']?\)$/, '' );
			if ( ! clean || clean.startsWith( 'data:' ) ) return;
			urls.add( clean );
		};

		roots.forEach( ( root ) => {
			root.querySelectorAll( 'img' ).forEach( ( img ) => {
				const ds = img.getAttribute( 'data-src' ) || img.getAttribute( 'data-lazy-src' ) || img.getAttribute( 'data-wood-src' );
				const dss = img.getAttribute( 'data-srcset' ) || img.getAttribute( 'data-lazy-srcset' );
				if ( ds && ! img.getAttribute( 'src' ) ) {
					img.setAttribute( 'src', ds );
				}
				if ( dss && ! img.getAttribute( 'srcset' ) ) {
					img.setAttribute( 'srcset', dss );
				}
				img.setAttribute( 'loading', 'eager' );
				img.setAttribute( 'decoding', 'sync' );
				if ( img.getAttribute( 'src' ) ) collectUrl( img.getAttribute( 'src' ) );
			} );

			root.querySelectorAll( 'picture source' ).forEach( ( source ) => {
				const ds = source.getAttribute( 'data-srcset' ) || source.getAttribute( 'data-lazy-srcset' );
				if ( ds && ! source.getAttribute( 'srcset' ) ) {
					source.setAttribute( 'srcset', ds );
				}
				if ( source.getAttribute( 'srcset' ) ) {
					source.getAttribute( 'srcset' ).split( ',' ).forEach( ( part ) => {
						const u = part.trim().split( /\s+/ )[ 0 ];
						collectUrl( u );
					} );
				}
			} );

			root.querySelectorAll( '[data-bg], [data-wood-bg], [style*="background"]' ).forEach( ( el ) => {
				const dbg = el.getAttribute( 'data-bg' ) || el.getAttribute( 'data-wood-bg' );
				if ( dbg ) {
					collectUrl( dbg );
					if ( ! el.style.backgroundImage ) {
						el.style.backgroundImage = 'url("' + dbg + '")';
					}
				}
				const style = el.getAttribute( 'style' ) || '';
				const m = style.match( /background(?:-image)?\s*:\s*url\(([^)]+)\)/i );
				if ( m ) collectUrl( m[ 1 ] );
			} );
		} );

		urls.forEach( ( url ) => {
			const p = new Promise( ( resolve ) => {
				const img = new Image();
				img.onload = () => {
					if ( typeof img.decode === 'function' ) {
						img.decode().then( resolve ).catch( resolve );
					} else {
						resolve();
					}
				};
				img.onerror = resolve;
				img.src = url;
				setTimeout( resolve, ASSET_TIMEOUT );
			} );
			pending.push( p );
		} );

		log( 'Awaiting media', urls.size );
		return Promise.all( pending );
	}

	function syncSvgSprites( doc ) {
		if ( ! doc ) return;

		let holder = document.getElementById( 'wpswup-svg-sprite-holder' );
		if ( ! holder ) {
			holder = document.createElement( 'div' );
			holder.id = 'wpswup-svg-sprite-holder';
			holder.setAttribute( 'aria-hidden', 'true' );
			holder.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden;visibility:hidden;pointer-events:none';
			document.body.appendChild( holder );
		}

		const known = new Set();
		holder.querySelectorAll( '[id]' ).forEach( ( el ) => {
			if ( el.id ) known.add( el.id );
		} );

		doc.querySelectorAll( 'svg symbol[id], svg defs > [id], symbol[id]' ).forEach( ( sym ) => {
			const id = sym.id;
			if ( ! id || known.has( id ) ) return;
			const clone = sym.cloneNode( true );
			if ( clone.tagName.toLowerCase() === 'symbol' || clone.closest( 'defs' ) ) {
				let svg = holder.querySelector( 'svg' );
				if ( ! svg ) {
					svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
					svg.setAttribute( 'xmlns', 'http://www.w3.org/2000/svg' );
					svg.style.display = 'none';
					holder.appendChild( svg );
				}
				let defs = svg.querySelector( 'defs' );
				if ( ! defs ) {
					defs = document.createElementNS( 'http://www.w3.org/2000/svg', 'defs' );
					svg.appendChild( defs );
				}
				defs.appendChild( document.importNode ? document.importNode( clone, true ) : clone );
			} else {
				holder.appendChild( document.importNode ? document.importNode( clone, true ) : clone );
			}
			known.add( id );
			log( 'Synced SVG symbol', id );
		} );
	}

	function refreshRenderedSvgs( containers ) {
		( containers || [] ).forEach( ( sel ) => {
			const root = document.querySelector( sel );
			if ( ! root ) return;
			root.querySelectorAll( 'use' ).forEach( ( use ) => {
				const href = use.getAttribute( 'href' ) || use.getAttribute( 'xlink:href' );
				if ( ! href || href.charAt( 0 ) !== '#' ) return;
				use.setAttribute( 'href', href );
				if ( use.hasAttribute( 'xlink:href' ) ) {
					use.setAttribute( 'xlink:href', href );
				}
			} );
		} );
	}

	function syncBodyClassesUniversal( doc ) {
		if ( ! doc || ! doc.body ) return;
		const next = doc.body.getAttribute( 'class' );
		if ( typeof next === 'string' ) {
			document.body.className = next;
			log( 'Synced body classes' );
		}
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
			// Never create a second instance (DOMContentLoaded re-fire / script re-eval).
			if ( this.swup ) {
				log( 'Swup already initialized — skip re-init' );
				return;
			}
			this.containers = resolveContainers();
			if ( ! this.containers.length ) return;

			const plugins = [];

			if ( typeof SwupHeadPlugin !== 'undefined' ) {
				plugins.push(
					new SwupHeadPlugin( {
						awaitAssets: true,
						timeout: ASSET_TIMEOUT,
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

			// optin:true = only scripts marked data-swup-reload-script are re-run.
			// optin:false was re-executing wpswup-main / jquery / theme bundles and
			// creating a second Swup instance after every transition.
			if ( cfg.scriptsPlugin && typeof SwupScriptsPlugin !== 'undefined' ) {
				plugins.push(
					new SwupScriptsPlugin( {
						head: true,
						body: true,
						optin: true,
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
				// Force off: native View Transitions + rapid re-init caused AbortError/TimeoutError loops.
				native: false,
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
			log( 'Swup 4 initialized. Containers:', this.containers, 'plugins:', plugins.length, 'scriptsPlugin:', !! cfg.scriptsPlugin, 'awaitMedia:', !! cfg.awaitMedia );
		}

		_bindEvents() {
			const s = this.swup;
			if ( ! s || ! s.hooks ) return;

			const runAssetPipeline = ( visit ) => {
				let doc = visit && visit.to && visit.to.document;
				if ( ! doc && visit && visit.to && visit.to.html ) {
					try {
						doc = new DOMParser().parseFromString( visit.to.html, 'text/html' );
						log( 'Parsed document from visit.to.html fallback' );
					} catch ( e ) {
						log( 'Failed to parse visit.to.html', e );
					}
				}
				if ( ! doc ) {
					log( 'Asset pipeline skipped: no document on visit.to' );
					return Promise.resolve();
				}
				const containers = this.containers;
				log( 'Asset pipeline start', 'containers=', containers );
				return Promise.all( [
					ensureStylesheets( doc, containers ),
					ensurePageScripts( doc ),
					ensureMediaAndImages( doc, containers ),
				] ).then( () => {
					syncSvgSprites( doc );
					log( 'Next page assets ready' );
				} ).catch( ( err ) => {
					log( 'Asset pipeline error', err );
				} );
			};

			// visit.to.document is assigned AFTER page:load resolves (Swup 4 source).
			// Running the pipeline only in before:content:replace guarantees a document.
			s.hooks.before( 'content:replace', async ( visit ) => {
				let doc = visit && visit.to && visit.to.document;
				if ( ! doc && visit && visit.to && visit.to.html ) {
					try {
						doc = new DOMParser().parseFromString( visit.to.html, 'text/html' );
						visit.to.document = doc;
						log( 'Parsed document from visit.to.html' );
					} catch ( e ) {
						log( 'Failed to parse visit.to.html', e );
					}
				}
				if ( doc ) {
					await withTimeout( runAssetPipeline( visit ), ASSET_TIMEOUT + 500 );
					syncBodyClassesUniversal( doc );
				} else {
					log( 'before:content:replace — no document available' );
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
				refreshRenderedSvgs( this.containers );
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
			// Do NOT re-dispatch DOMContentLoaded — our boot() listens for it and would
			// call init() again, creating a second Swup and AbortError/TimeoutError storms.
			try {
				window.dispatchEvent( new Event( 'resize' ) );
			} catch ( e ) {}

			if ( typeof jQuery !== 'undefined' ) {
				try {
					jQuery( document ).trigger( 'post-load' );
				} catch ( e ) {}
			}

			if ( cfg.compatWoo && typeof jQuery !== 'undefined' ) {
				try {
					jQuery( document.body ).trigger( 'wc_fragment_refresh' );
					jQuery( document.body ).trigger( 'updated_wc_div' );
					jQuery( document.body ).trigger( 'wc-product-gallery-after-init' );
					jQuery( '.variations_form' ).each( function () {
						if ( jQuery.fn.wc_variation_form ) {
							jQuery( this ).wc_variation_form();
						}
					} );
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
					if ( typeof woodmartThemeModule.wdShopPageInit === 'function' ) {
						woodmartThemeModule.wdShopPageInit();
					}
					if ( typeof woodmartThemeModule.woodImagesLoaded === 'function' ) {
						woodmartThemeModule.woodImagesLoaded();
					}
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

	let booted = false;
	const boot = () => {
		if ( booted ) return;
		booted = true;
		swupManager.init();
		bus.emit( 'ready', { WPSWUP: window.WPSWUP } );
		document.dispatchEvent( new CustomEvent( 'wpswup:ready' ) );
		log( 'Ready (Swup 4 / v1.5.2).' );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot, { once: true } );
	} else {
		boot();
	}
} )();
