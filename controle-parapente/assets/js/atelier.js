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
		document.body.setAttribute( 'data-tab', name );
		if ( name === 'rapport' ) {
			var frame = document.querySelector( '.cp-report-frame' );
			if ( frame && ( ! frame.getAttribute( 'src' ) || reportStale ) ) {
				// Aperçu rechargé après chaque enregistrement.
				reportStale = false;
				frame.setAttribute( 'src', frame.getAttribute( 'data-src' ) + '&t=' + Date.now() );
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

	// Enregistrement automatique : chaque modification est enregistrée en arrière-plan, sans recharger la page.
	var form = document.getElementById( 'cp-fiche-form' );
	var reportStale = false;
	if ( form ) {
		var info = document.querySelector( '.cp-autosave' );
		var saveBtn = form.querySelector( '.cp-save-btn' );
		var statusBefore = document.getElementById( 'cp-status-before' );
		var DELAY = 1500;
		var dirty = false;
		var saving = false;
		var again = false;
		var timer = null;
		var retry = null;

		var setInfo = function ( state, text ) {
			if ( info ) {
				info.setAttribute( 'data-state', state );
				info.textContent = text;
			}
		};

		// Changement de statut à notifier : seul le bouton envoie l'e-mail au client.
		var needsNotify = function () {
			var status = form.querySelector( '[name="cp[status]"]' );
			var notify = form.querySelector( '[name="cp_notify"]' );
			return !! ( status && statusBefore && notify && notify.checked && status.value !== statusBefore.value );
		};
		var updateButton = function () {
			if ( saveBtn ) {
				saveBtn.textContent = saveBtn.getAttribute( needsNotify() ? 'data-label-notify' : 'data-label' );
			}
		};

		var setField = function ( name, value ) {
			var field = form.querySelector( '[name="cp[' + name + ']"]' );
			if ( field && field.value === '' && value ) {
				field.value = value;
			}
		};

		var save = function () {
			clearTimeout( timer );
			clearTimeout( retry );
			if ( saving ) {
				again = true;
				return;
			}
			if ( ! dirty ) {
				return;
			}
			saving = true;
			dirty = false;
			setInfo( 'saving', 'Enregistrement…' );
			var body = new FormData( form );
			body.append( 'cp_ajax', '1' );
			fetch( form.getAttribute( 'action' ), { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( r ) {
					return r.json().catch( function () {
						throw new Error( 'Session expirée : rechargez la page.' );
					} ).then( function ( json ) {
						if ( ! r.ok || ! json.success ) {
							throw new Error( json && json.data && json.data.message ? json.data.message : 'Erreur ' + r.status );
						}
						return json.data;
					} );
				} )
				.then( function ( data ) {
					var nonce = form.querySelector( '[name="cp_nonce"]' );
					if ( nonce && data.nonce ) {
						nonce.value = data.nonce;
					}
					setField( 'next_date', data.next_date );
					setField( 'next_hours', data.next_hours );
					var pill = document.querySelector( '.cp-fiche-title .cp-pill' );
					if ( pill && data.status_label ) {
						pill.className = 'cp-pill cp-status-' + data.status;
						pill.textContent = data.status_label;
					}
					reportStale = true;
					setInfo( dirty ? 'pending' : 'saved', dirty ? 'Modifications en cours…' : '✓ Enregistré à ' + data.time );
				} )
				.catch( function ( err ) {
					dirty = true;
					setInfo( 'error', '⚠ ' + ( err && err.message ? err.message : 'Échec de l\'enregistrement' ) + ' — nouvel essai…' );
					retry = setTimeout( save, 8000 );
				} )
				.then( function () {
					saving = false;
					if ( again ) {
						again = false;
						save();
					}
				} );
		};

		var mark = function () {
			dirty = true;
			setInfo( 'pending', 'Modifications en cours…' );
			updateButton();
			clearTimeout( timer );
			timer = setTimeout( save, DELAY );
		};

		form.addEventListener( 'input', mark );
		form.addEventListener( 'change', mark );
		form.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.cp-add-row, .cp-remove-row, .cp-all-ok, .cp-trim-copy, .cp-previous-results button, .cp-trim-add-group, .cp-trim-chip-del, .cp-trim-palette button, .cp-trim-slot, .cp-trim-align-btn, .cp-stepper button, .cp-trim-suggest, .cp-trim-reset' ) ) {
				// Laisser le script du calage mettre à jour les données avant d'enregistrer.
				setTimeout( mark, 0 );
			}
		} );
		// La palette de couleurs du calage est hors du formulaire.
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.cp-trim-palette button' ) ) {
				setTimeout( mark, 0 );
			}
		} );

		// Bouton « Enregistrer » : enregistrement immédiat sans rechargement,
		// sauf s'il faut prévenir le client d'un changement de statut (envoi classique).
		form.addEventListener( 'submit', function ( e ) {
			if ( needsNotify() ) {
				dirty = false;
				return;
			}
			e.preventDefault();
			dirty = true;
			save();
		} );

		// Ctrl/Cmd + S : enregistrer tout de suite.
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && e.key === 's' ) {
				e.preventDefault();
				dirty = true;
				save();
			}
		} );

		// En quittant la page : dernier enregistrement envoyé en arrière-plan.
		var flush = function () {
			if ( dirty && ! saving && navigator.sendBeacon ) {
				var body = new FormData( form );
				body.append( 'cp_ajax', '1' );
				if ( navigator.sendBeacon( form.getAttribute( 'action' ), body ) ) {
					dirty = false;
				}
			}
		};
		window.addEventListener( 'pagehide', flush );
		document.addEventListener( 'visibilitychange', function () {
			if ( document.visibilityState === 'hidden' && dirty ) {
				save();
			}
		} );
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( saving || ( dirty && ! navigator.sendBeacon ) ) {
				e.preventDefault();
				e.returnValue = '';
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
