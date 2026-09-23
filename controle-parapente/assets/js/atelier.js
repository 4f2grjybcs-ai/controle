/* Contrôle Parapente — espace atelier. */
( function () {
	'use strict';

	// Liste : toute la ligne est cliquable.
	document.querySelectorAll( '.cp-list tr[data-href]' ).forEach( function ( tr ) {
		tr.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( 'a' ) ) {
				return;
			}
			window.location.href = tr.getAttribute( 'data-href' );
		} );
	} );

	// Onglets de la fiche (mémorisés dans l'URL : #client, #mesures…).
	var tabs = document.querySelectorAll( '.cp-tabs [data-tab]' );
	var panels = document.querySelectorAll( '.cp-panel' );
	var tabInput = document.getElementById( 'cp-tab' );

	function show( name ) {
		var exists = Array.prototype.some.call( panels, function ( p ) {
			return p.getAttribute( 'data-panel' ) === name;
		} );
		if ( ! exists ) {
			name = 'client';
		}
		tabs.forEach( function ( t ) {
			var on = t.getAttribute( 'data-tab' ) === name;
			t.classList.toggle( 'is-active', on );
			t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
		} );
		panels.forEach( function ( p ) {
			p.hidden = p.getAttribute( 'data-panel' ) !== name;
		} );
		if ( tabInput ) {
			tabInput.value = name;
		}
		if ( name === 'rapport' ) {
			var frame = document.querySelector( '.cp-report-frame' );
			if ( frame && ! frame.getAttribute( 'src' ) ) {
				frame.setAttribute( 'src', frame.getAttribute( 'data-src' ) );
			}
		}
	}

	if ( tabs.length ) {
		tabs.forEach( function ( t ) {
			t.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var name = t.getAttribute( 'data-tab' );
				history.replaceState( null, '', '#' + name );
				show( name );
			} );
		} );
		show( window.location.hash.replace( '#', '' ) || 'client' );
	}

	// Modifications non enregistrées.
	var form = document.getElementById( 'cp-fiche-form' );
	var dirty = false;
	var badge = document.querySelector( '.cp-dirty' );
	if ( form ) {
		var mark = function () {
			dirty = true;
			if ( badge ) {
				badge.hidden = false;
			}
		};
		form.addEventListener( 'input', mark );
		form.addEventListener( 'change', mark );
		form.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.cp-add-row, .cp-remove-row, .cp-all-ok, .cp-trim-copy, .cp-previous-results button' ) ) {
				mark();
			}
		} );
		form.addEventListener( 'submit', function () {
			dirty = false;
		} );
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		} );
		// Raccourci Ctrl/Cmd + S pour enregistrer.
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && e.key === 's' ) {
				e.preventDefault();
				dirty = false;
				form.requestSubmit ? form.requestSubmit() : form.submit();
			}
		} );
	}

	// Copier le lien client.
	document.querySelectorAll( '.cp-copy' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var text = btn.getAttribute( 'data-copy' );
			var done = function () {
				var label = btn.textContent;
				btn.textContent = 'Lien copié ✓';
				setTimeout( function () {
					btn.textContent = label;
				}, 1800 );
			};
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( text ).then( done, function () {
					window.prompt( 'Lien du rapport :', text );
				} );
			} else {
				window.prompt( 'Lien du rapport :', text );
			}
		} );
	} );

	// Le message de confirmation disparaît doucement.
	var toast = document.querySelector( '.cp-toast--ok' );
	if ( toast ) {
		setTimeout( function () {
			toast.classList.add( 'is-hiding' );
		}, 4000 );
	}
} )();
