/* Contrôle Parapente — téléchargement du rapport en PDF (A4), sans en-tête ni lien du navigateur. */
( function () {
	'use strict';

	var button = document.querySelector( '.cp-pdf-download' );
	var sheet = document.querySelector( '.cp-sheet' );
	if ( ! button || ! sheet ) {
		return;
	}

	var PAGE_W = 210; // mm
	var PAGE_H = 297;
	var MARGIN_X = 12;
	var MARGIN_TOP = 12;
	var MARGIN_BOTTOM = 16; // place pour le numéro de page
	var CONTENT_W = PAGE_W - 2 * MARGIN_X;
	var CONTENT_H = PAGE_H - MARGIN_TOP - MARGIN_BOTTOM;

	// Blocs à ne jamais couper entre deux pages (mêmes règles que l'impression).
	var ATOMIC = '.cp-head, .cp-hero, .cp-verdict-box, .cp-partial, .cp-note, .cp-cursor, .cp-state-basis, .cp-summary-card, .cp-metrics, ' +
		'.cp-cols > div, .cp-notdone, .cp-photos-report figure, .cp-wing-figure, .cp-wing-legend, .cp-foot, .cp-internal, tr, img, svg, p, li, dt, dd';
	var KEEP_WITH_NEXT = 'h1, h2, h3, .cp-eyebrow, figcaption';

	function lib() {
		var h2c = window.html2canvas && ( window.html2canvas.default || window.html2canvas.html2canvas || window.html2canvas );
		var JsPDF = window.jspdf && window.jspdf.jsPDF;
		return typeof h2c === 'function' && JsPDF ? { h2c: h2c, JsPDF: JsPDF } : null;
	}

	// Les dessins SVG utilisent des classes CSS : on recopie leur style calculé pour la capture.
	function inlineSvgStyles() {
		var props = [ 'fill', 'stroke', 'stroke-width', 'opacity', 'font-family', 'font-size', 'font-weight', 'font-style', 'letter-spacing' ];
		var saved = [];
		sheet.querySelectorAll( 'svg *' ).forEach( function ( el ) {
			if ( el.tagName === 'defs' || el.closest( 'defs' ) ) {
				return;
			}
			var cs = window.getComputedStyle( el );
			saved.push( [ el, el.getAttribute( 'style' ) ] );
			var style = props.map( function ( p ) {
				return p + ':' + cs.getPropertyValue( p );
			} ).join( ';' );
			el.setAttribute( 'style', style + ';' + ( el.getAttribute( 'style' ) || '' ) );
		} );
		return function () {
			saved.forEach( function ( s ) {
				if ( s[ 1 ] === null ) {
					s[ 0 ].removeAttribute( 'style' );
				} else {
					s[ 0 ].setAttribute( 'style', s[ 1 ] );
				}
			} );
		};
	}

	// Zones interdites à la coupe, en px depuis le haut de la feuille.
	function forbiddenZones( pagePx ) {
		var top0 = sheet.getBoundingClientRect().top;
		var zones = [];
		var add = function ( top, bottom ) {
			if ( bottom - top > 1 && bottom - top < pagePx ) {
				zones.push( [ top - top0, bottom - top0 ] );
			}
		};
		sheet.querySelectorAll( ATOMIC ).forEach( function ( el ) {
			var r = el.getBoundingClientRect();
			add( r.top, r.bottom );
		} );
		// Petits tableaux et paire de dessins du calage : entiers si possible.
		sheet.querySelectorAll( '.cp-table, .cp-wing-pair, .cp-summary-cards' ).forEach( function ( el ) {
			var r = el.getBoundingClientRect();
			if ( r.height < pagePx * 0.6 ) {
				add( r.top, r.bottom );
			}
		} );
		// Un titre reste avec le début de ce qui le suit.
		sheet.querySelectorAll( KEEP_WITH_NEXT ).forEach( function ( el ) {
			var next = el.nextElementSibling;
			var r = el.getBoundingClientRect();
			if ( next ) {
				var n = next.getBoundingClientRect();
				add( r.top, Math.min( n.bottom, n.top + 90 ) );
			}
		} );
		return zones;
	}

	function bestCut( start, limit, zones ) {
		var candidates = [ limit ];
		zones.forEach( function ( z ) {
			[ z[ 0 ], z[ 1 ] ].forEach( function ( y ) {
				if ( y > start + 40 && y <= limit ) {
					candidates.push( y );
				}
			} );
		} );
		candidates.sort( function ( a, b ) {
			return b - a;
		} );
		for ( var i = 0; i < candidates.length; i++ ) {
			var y = candidates[ i ];
			var inside = zones.some( function ( z ) {
				return y > z[ 0 ] + 0.5 && y < z[ 1 ] - 0.5;
			} );
			if ( ! inside ) {
				return y;
			}
		}
		return limit;
	}

	function filename() {
		var ref = ( button.getAttribute( 'data-reference' ) || 'rapport' ).replace( /[^A-Za-z0-9_-]+/g, '-' );
		return 'Rapport-' + ref + '.pdf';
	}

	function build() {
		var l = lib();
		if ( ! l ) {
			window.print();
			return Promise.resolve();
		}
		var label = button.textContent;
		button.disabled = true;
		button.textContent = button.getAttribute( 'data-busy' ) || '…';
		document.documentElement.classList.add( 'cp-pdf-mode' );
		var restoreSvg = inlineSvgStyles();
		var fontsReady = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();

		return fontsReady.then( function () {
			var widthPx = sheet.getBoundingClientRect().width;
			var pxPerMm = widthPx / CONTENT_W;
			var pagePx = CONTENT_H * pxPerMm;
			var zones = forbiddenZones( pagePx );
			var totalPx = sheet.scrollHeight;
			return l.h2c( sheet, { scale: 2, backgroundColor: '#ffffff', useCORS: true, logging: false } ).then( function ( canvas ) {
				var scale = canvas.width / widthPx;
				var pdf = new l.JsPDF( { unit: 'mm', format: 'a4', orientation: 'portrait', compress: true } );
				var cuts = [];
				var start = 0;
				while ( start < totalPx - 2 ) {
					var end = start + pagePx >= totalPx ? totalPx : bestCut( start, start + pagePx, zones );
					cuts.push( [ start, end ] );
					start = end;
				}
				var footer = button.getAttribute( 'data-footer' ) || '';
				cuts.forEach( function ( c, i ) {
					var slice = document.createElement( 'canvas' );
					slice.width = canvas.width;
					slice.height = Math.max( 1, Math.round( ( c[ 1 ] - c[ 0 ] ) * scale ) );
					var ctx = slice.getContext( '2d' );
					ctx.fillStyle = '#ffffff';
					ctx.fillRect( 0, 0, slice.width, slice.height );
					ctx.drawImage( canvas, 0, Math.round( c[ 0 ] * scale ), canvas.width, slice.height, 0, 0, slice.width, slice.height );
					if ( i > 0 ) {
						pdf.addPage();
					}
					pdf.addImage( slice.toDataURL( 'image/jpeg', 0.92 ), 'JPEG', MARGIN_X, MARGIN_TOP, CONTENT_W, ( c[ 1 ] - c[ 0 ] ) / pxPerMm );
					pdf.setFontSize( 8 );
					pdf.setTextColor( 139, 119, 106 );
					pdf.text( ( footer ? footer + '  -  ' : '' ) + ( i + 1 ) + ' / ' + cuts.length, PAGE_W / 2, PAGE_H - 8, { align: 'center' } );
				} );
				pdf.save( filename() );
			} );
		} ).catch( function ( err ) {
			window.console && console.error( err );
			window.alert( button.getAttribute( 'data-error' ) || 'PDF error' );
		} ).then( function () {
			restoreSvg();
			document.documentElement.classList.remove( 'cp-pdf-mode' );
			button.disabled = false;
			button.textContent = label;
		} );
	}

	button.addEventListener( 'click', build );

	// Lien « Télécharger le PDF » depuis l'espace atelier : téléchargement automatique.
	if ( /[?&]pdf=1(&|$)/.test( window.location.search ) ) {
		window.addEventListener( 'load', function () {
			build();
		} );
	}
} )();
