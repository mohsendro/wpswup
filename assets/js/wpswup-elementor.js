/**
 * WP Swup – Elementor page CSS / body-class helper
 * Complements HeadPlugin + main._syncStylesheets for Elementor post CSS.
 */
( function () {
	'use strict';

	var PAGE_CSS_RE = /elementor\/css\/post-|elementor-post-\d+|elementor-post-css|post-\d+\.css/i;
	var PRESERVE_BODY_RE = /^(wd-opened|wd-scroll-lock|mfp-ready|wrapper-locked|modal-open|admin-bar|logged-in|swup-enabled|wpswup-)/;

	function normUrl( u ) {
		try {
			var a = document.createElement( 'a' );
			a.href = u;
			return ( a.pathname || '' ) + ( a.search || '' );
		} catch ( e ) {
			return String( u || '' );
		}
	}

	function syncElementorAssets( visit ) {
		var doc = visit && visit.to && visit.to.document;
		if ( ! doc || ! doc.head ) return;

		Array.prototype.slice
			.call( document.head.querySelectorAll( 'link[rel="stylesheet"], style[id]' ) )
			.forEach( function ( el ) {
				var id = el.id || '';
				var href = el.getAttribute && ( el.getAttribute( 'href' ) || '' );
				if ( ! PAGE_CSS_RE.test( id + href ) ) return;
				var keep = false;
				if ( el.tagName === 'LINK' && href ) {
					var n = normUrl( href );
					Array.prototype.slice.call( doc.head.querySelectorAll( 'link[rel="stylesheet"]' ) ).some( function ( nl ) {
						if ( normUrl( nl.getAttribute( 'href' ) || '' ) === n ) {
							keep = true;
							return true;
						}
						return false;
					} );
				} else if ( id && doc.getElementById( id ) ) {
					keep = true;
				}
				if ( ! keep && el.parentNode ) el.parentNode.removeChild( el );
			} );

		Array.prototype.slice.call( doc.head.querySelectorAll( 'link[rel="stylesheet"]' ) ).forEach( function ( el ) {
			var href = el.getAttribute( 'href' );
			if ( ! href ) return;
			if ( ! PAGE_CSS_RE.test( href + ( el.id || '' ) ) && ! /elementor/i.test( href + ( el.id || '' ) ) ) return;
			var exists = false;
			var n = normUrl( href );
			Array.prototype.slice.call( document.head.querySelectorAll( 'link[rel="stylesheet"]' ) ).some( function ( cur ) {
				if ( normUrl( cur.getAttribute( 'href' ) || '' ) === n ) {
					exists = true;
					return true;
				}
				return false;
			} );
			if ( exists ) return;
			var link = document.createElement( 'link' );
			link.rel = 'stylesheet';
			link.href = href;
			if ( el.id ) link.id = el.id;
			document.head.appendChild( link );
		} );

		Array.prototype.slice.call( doc.head.querySelectorAll( 'style[id]' ) ).forEach( function ( ns ) {
			if ( ! ns.id ) return;
			var cur = document.getElementById( ns.id );
			if ( cur ) {
				cur.textContent = ns.textContent;
			} else {
				var style = document.createElement( 'style' );
				style.id = ns.id;
				style.textContent = ns.textContent;
				document.head.appendChild( style );
			}
		} );
	}

	function syncBodyClasses( visit ) {
		var doc = visit && visit.to && visit.to.document;
		if ( ! doc || ! doc.body ) return;
		var next = doc.body.className || '';
		var preserve = [];
		( document.body.className || '' ).split( /\s+/ ).forEach( function ( c ) {
			if ( c && PRESERVE_BODY_RE.test( c ) ) preserve.push( c );
		} );
		document.body.className = next;
		preserve.forEach( function ( c ) {
			document.body.classList.add( c );
		} );
	}

	function bind() {
		if ( ! window.WPSWUP || ! window.WPSWUP.swup || ! window.WPSWUP.swup.hooks ) return false;
		var swup = window.WPSWUP.swup;
		if ( swup._wpswupElementorBound ) return true;
		swup._wpswupElementorBound = true;
		swup.hooks.on( 'content:replace', function ( visit ) {
			syncElementorAssets( visit );
			syncBodyClasses( visit );
		} );
		swup.hooks.on( 'page:view', function ( visit ) {
			syncElementorAssets( visit );
			syncBodyClasses( visit );
		} );
		return true;
	}

	function boot() {
		if ( bind() ) return;
		document.addEventListener( 'wpswup:ready', bind );
		setTimeout( bind, 300 );
		setTimeout( bind, 1200 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
