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

	function detectContainers() {
		if ( Array.isArray( cfg.containers ) && cfg.containers.length && ! cfg.containersAuto ) {
			return cfg.containers;
		}
		for ( const sel of AUTO_CONTAINER_CANDIDATES ) {
			if ( document.querySelector( sel ) ) {
				return [ sel ];
			}
		}
		return [ 'body' ];
	}

	class SwupManager {
		constructor( bus ) {
			this.bus = bus;
			this.swup = null;
		}

		init() {
			if ( ! cfg.swupEnabled || typeof Swup === 'undefined' ) {
				log( 'Swup disabled or not loaded' );
				return;
			}

			const containers = detectContainers();
			log( 'Containers', containers );

			const plugins = [];

			if ( typeof SwupHeadPlugin !== 'undefined' ) {
				plugins.push( new SwupHeadPlugin( { persistAssets: true } ) );
			}
			if ( typeof SwupProgressPlugin !== 'undefined' ) {
				plugins.push(
					new SwupProgressPlugin( {
						delay: 0,
						transition: Math.max( 100, ( cfg.duration || 400 ) * 0.6 ),
					} )
				);
			}

			const anim = cfg.animation || 'fade';
			if ( anim === 'fade' && typeof SwupFadeTheme !== 'undefined' ) {
				plugins.push( new SwupFadeTheme( { mainElement: containers[ 0 ] } ) );
			} else if ( ( anim === 'slide' || anim === 'slide_reversed' ) && typeof SwupSlideTheme !== 'undefined' ) {
				plugins.push(
					new SwupSlideTheme( {
						mainElement: containers[ 0 ],
						reversed: anim === 'slide_reversed',
					} )
				);
			} else if ( String( anim ).startsWith( 'overlay' ) && typeof SwupOverlayTheme !== 'undefined' ) {
				const dirMap = {
					overlay: 'to-right',
					overlay_left: 'to-left',
					overlay_top: 'to-top',
					overlay_bottom: 'to-bottom',
				};
				plugins.push(
					new SwupOverlayTheme( {
						direction: dirMap[ anim ] || 'to-right',
					} )
				);
			}

			if ( cfg.scrollPlugin && typeof SwupScrollPlugin !== 'undefined' ) {
				plugins.push( new SwupScrollPlugin() );
			}
			if ( cfg.preload && typeof SwupPreloadPlugin !== 'undefined' ) {
				plugins.push( new SwupPreloadPlugin() );
			}
			if ( cfg.forms && typeof SwupFormsPlugin !== 'undefined' ) {
				plugins.push( new SwupFormsPlugin() );
			}

			this.bus.emit( 'wpswup:swupPlugins', { plugins } );

			this.swup = new Swup( {
				containers,
				plugins,
				cache: !! cfg.cache,
				linkSelector: cfg.linkSelector || 'a[href]',
				animateHistoryBrowsing: true,
				native: !! cfg.native,
			} );

			window.WPSWUP.swup = this.swup;

			this.swup.hooks.on( 'visit:start', () => {
				this.bus.emit( 'wpswup:visitStart' );
			} );
			this.swup.hooks.on( 'content:replace', () => {
				this.bus.emit( 'wpswup:contentReplace' );
				this._reinitCompat();
			} );
			this.swup.hooks.on( 'page:view', () => {
				this.bus.emit( 'wpswup:pageView' );
			} );
			this.swup.hooks.on( 'visit:end', () => {
				this.bus.emit( 'wpswup:visitEnd' );
				this.bus.emit( 'wpswup:afterPageTransition' );
				if ( typeof jQuery !== 'undefined' ) {
					jQuery( document ).trigger( 'wpswup_after_page_transition' );
				}
			} );

			log( 'Swup initialized', anim );
		}

		_reinitCompat() {
			if ( window.woodmartThemeModule && typeof window.woodmartThemeModule.init === 'function' ) {
				try {
					window.woodmartThemeModule.init();
				} catch ( e ) {
					log( 'woodmart reinit error', e );
				}
			}
			if ( window.WPSWUP && typeof window.WPSWUP.scheduleCloseOverlays === 'function' ) {
				window.WPSWUP.scheduleCloseOverlays();
			}
			this.bus.emit( 'wpswup:reinit' );
		}
	}

	const bus = new EventBus();
	const manager = new SwupManager( bus );

	window.WPSWUP = window.WPSWUP || {};
	objectAssign( window.WPSWUP, {
		bus,
		manager,
		cfg,
		version: '1.4.0',
	} );

	function objectAssign( target, source ) {
		Object.keys( source ).forEach( ( k ) => {
			target[ k ] = source[ k ];
		} );
		return target;
	}

	function boot() {
		manager.init();
		bus.emit( 'wpswup:ready' );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
