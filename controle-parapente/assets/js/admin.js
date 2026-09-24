/* Contrôle Parapente — fiche de contrôle (admin). */
( function () {
	'use strict';

	// wp_localize_script transmet les nombres sous forme de chaînes.
	var raw = window.cpAdmin || {};
	var cfg = {
		porosityUnit: raw.porosityUnit || 's',
		porosityFactor: parseFloat( raw.porosityFactor ) || 5400,
		porosityAlert: parseFloat( raw.porosityAlert ) || 0,
		porosityReform: parseFloat( raw.porosityReform ) || 0,
		tearReform: parseFloat( raw.tearReform ) || 0,
		tearGood: parseFloat( raw.tearGood ) || 0,
		inspectionTypes: raw.inspectionTypes || {},
		lineTypes: raw.lineTypes || {},
		lineCoeffAramid: parseFloat( raw.lineCoeffAramid ) || 0.45,
		lineCoeffDyneema: parseFloat( raw.lineCoeffDyneema ) || 0.65,
		lineSupplier: parseFloat( raw.lineSupplier ) || 1.05,
		validityMonths: parseInt( raw.validityMonths, 10 ) || 0,
		ajaxUrl: raw.ajaxUrl,
		searchNonce: raw.searchNonce
	};

	function num( value ) {
		if ( value === '' || value === null || value === undefined ) {
			return null;
		}
		var n = parseFloat( String( value ).replace( ',', '.' ) );
		return isNaN( n ) ? null : n;
	}

	function field( row, name ) {
		var input = row.querySelector( '[data-field="' + name + '"]' );
		return input ? num( input.value ) : null;
	}

	function setComputed( row, text, level ) {
		var cell = row.querySelector( '.cp-computed' );
		if ( ! cell ) {
			return;
		}
		cell.textContent = text;
		cell.className = 'cp-computed' + ( level ? ' lvl-' + level : '' );
	}

	// Seuils de la fiche (cases « Type d'inspection & normes »), sinon réglages.
	function threshold( id, fallback ) {
		var el = document.getElementById( id );
		var v = el ? num( el.value ) : null;
		return v === null ? fallback : v;
	}

	var evaluators = {
		porosity: function ( row ) {
			var v = field( row, 'value' );
			if ( v === null ) {
				return setComputed( row, '', '' );
			}
			var alert = threshold( 'cp-por_alert', cfg.porosityAlert );
			var reform = threshold( 'cp-por_reform', cfg.porosityReform );
			// Saisie en secondes : conversion en l/m²/min (constante ÷ secondes).
			var flow = cfg.porosityUnit === 's' ? ( v > 0 ? cfg.porosityFactor / v : null ) : v;
			if ( flow === null ) {
				return setComputed( row, '', '' );
			}
			var shown = cfg.porosityUnit === 's' ? '≈ ' + Math.round( flow ) + ' l/m²/min · ' : '';
			if ( flow >= reform ) {
				return setComputed( row, shown + 'Échec', 'bad' );
			}
			if ( flow >= alert ) {
				return setComputed( row, shown + 'Acceptable', 'warn' );
			}
			return setComputed( row, shown + 'Bon', 'ok' );
		},
		tear: function ( row ) {
			var v = field( row, 'value' );
			if ( v === null ) {
				return setComputed( row, '', '' );
			}
			var reform = threshold( 'cp-tear_reform', cfg.tearReform );
			if ( v < reform ) {
				return setComputed( row, 'Échec', 'bad' );
			}
			setComputed( row, v <= cfg.tearGood ? 'Acceptable' : 'Bon', v <= cfg.tearGood ? 'warn' : 'ok' );
		},
		lines: function ( row ) {
			var get = function ( name ) {
				return row.querySelector( '[data-field="' + name + '"]' );
			};
			var source = get( 'source' ) ? get( 'source' ).value : 'minimum';
			var minInput = get( 'minimum' );
			// PMA 5.4 : minimum = valeur à neuf × coefficient de source × coefficient de matière.
			if ( minInput ) {
				minInput.readOnly = source !== 'minimum';
				var nw = field( row, 'new' );
				if ( source !== 'minimum' && nw ) {
					var coeff = get( 'material' ) && get( 'material' ).value === 'dyneema' ? cfg.lineCoeffDyneema : cfg.lineCoeffAramid;
					var src = source === 'constructeur' ? 1 : cfg.lineSupplier;
					minInput.value = String( Math.round( nw * src * coeff * 10 ) / 10 );
				}
			}
			var m = field( row, 'measured' );
			var min = field( row, 'minimum' );
			var neu = field( row, 'new' );
			if ( m === null || min === null ) {
				return setComputed( row, '', '' );
			}
			var pct = neu ? ' · ' + Math.round( m / neu * 100 ) + ' % du neuf' : '';
			setComputed( row, ( m >= min ? 'Conforme' : 'Échec' ) + pct, m >= min ? 'ok' : 'bad' );
		}
	};

	function evaluate( table ) {
		var fn = evaluators[ table.getAttribute( 'data-key' ) ];
		if ( ! fn ) {
			return;
		}
		table.querySelectorAll( 'tbody tr' ).forEach( fn );
	}

	document.querySelectorAll( '.cp-repeat' ).forEach( function ( table ) {
		var box = table.closest( '.cp-repeat-wrap' ) || table.parentNode;
		var template = box.querySelector( '.cp-row-template' );
		var add = box.querySelector( '.cp-add-row' );
		var counter = table.querySelectorAll( 'tbody tr' ).length;

		if ( add && template ) {
			add.addEventListener( 'click', function () {
				var html = template.innerHTML.replace( /__i__/g, String( counter++ ) );
				table.querySelector( 'tbody' ).insertAdjacentHTML( 'beforeend', html );
				var inputs = table.querySelectorAll( 'tbody tr:last-child input' );
				if ( inputs.length ) {
					inputs[ 0 ].focus();
				}
			} );
		}

		table.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'cp-remove-row' ) ) {
				e.target.closest( 'tr' ).remove();
			}
		} );

		table.addEventListener( 'input', function () {
			evaluate( table );
		} );

		evaluate( table );
	} );

	// Type de suspente choisi : valeur à neuf reprise du catalogue.
	document.addEventListener( 'change', function ( e ) {
		var el = e.target;
		if ( el.matches && el.matches( '.cp-repeat[data-key="lines"] [data-field="type"]' ) ) {
			var t = cfg.lineTypes[ el.value ];
			var nw = el.closest( 'tr' ).querySelector( '[data-field="new"]' );
			if ( t && nw ) {
				nw.value = t.new;
			}
			var mat = el.closest( 'tr' ).querySelector( '[data-field="material"]' );
			if ( t && mat ) {
				mat.value = /dyneema/i.test( t.material + ' ' + t.label ) ? 'dyneema' : 'aramide';
			}
		}
		if ( el.closest && el.closest( '.cp-repeat' ) ) {
			evaluate( el.closest( '.cp-repeat' ) );
		}
	} );

	// Seuils modifiés : réévaluer les tableaux.
	[ 'cp-por_alert', 'cp-por_reform', 'cp-tear_reform' ].forEach( function ( id ) {
		var el = document.getElementById( id );
		if ( el ) {
			el.addEventListener( 'input', function () {
				document.querySelectorAll( '.cp-repeat' ).forEach( evaluate );
			} );
		}
	} );

	// Type d'inspection : coche les tests inclus.
	var typeSelect = document.getElementById( 'cp-inspection_type' );
	if ( typeSelect ) {
		typeSelect.addEventListener( 'change', function () {
			var tests = cfg.inspectionTypes[ typeSelect.value ] || [];
			document.querySelectorAll( '.cp-test-done' ).forEach( function ( box ) {
				box.checked = tests.indexOf( box.getAttribute( 'data-test' ) ) !== -1;
			} );
		} );
	}

	// Contrôle visuel : tout marquer « Bon état ».
	var allOk = document.querySelector( '.cp-all-ok' );
	if ( allOk ) {
		allOk.addEventListener( 'click', function () {
			document.querySelectorAll( '.cp-visual .cp-state' ).forEach( function ( select ) {
				if ( select.value === '' ) {
					select.value = 'bon';
				}
			} );
		} );
	}

	// Date du prochain contrôle proposée automatiquement.
	var checkDate = document.getElementById( 'cp-check_date' );
	var nextDate = document.getElementById( 'cp-next_date' );
	if ( checkDate && nextDate && cfg.validityMonths > 0 ) {
		checkDate.addEventListener( 'change', function () {
			if ( nextDate.value || ! checkDate.value ) {
				return;
			}
			var parts = checkDate.value.split( '-' ).map( Number );
			var d = new Date( Date.UTC( parts[ 0 ], parts[ 1 ] - 1 + cfg.validityMonths, parts[ 2 ] ) );
			nextDate.value = d.toISOString().slice( 0, 10 );
		} );
	}
	// Reprendre un client / une aile déjà venus : pré-remplit la fiche.
	var search = document.getElementById( 'cp-previous-search' );
	var results = document.querySelector( '.cp-previous-results' );
	if ( search && results && cfg.ajaxUrl ) {
		var timer = null;
		var found = [];
		var postId = document.getElementById( 'post_ID' );

		search.addEventListener( 'input', function () {
			clearTimeout( timer );
			var q = search.value.trim();
			if ( q.length < 2 ) {
				results.hidden = true;
				return;
			}
			timer = setTimeout( function () {
				var url = cfg.ajaxUrl + '?action=cp_search_previous&nonce=' + encodeURIComponent( cfg.searchNonce ) +
					'&q=' + encodeURIComponent( q ) + '&exclude=' + ( postId ? postId.value : 0 );
				fetch( url, { credentials: 'same-origin' } )
					.then( function ( r ) {
						return r.json();
					} )
					.then( function ( json ) {
						found = json && json.success ? json.data : [];
						results.innerHTML = '';
						if ( ! found.length ) {
							results.innerHTML = '<li class="cp-previous-empty">Aucun contrôle trouvé.</li>';
						}
						found.forEach( function ( item, i ) {
							var li = document.createElement( 'li' );
							var btn = document.createElement( 'button' );
							btn.type = 'button';
							btn.className = 'button-link';
							btn.textContent = item.label;
							btn.setAttribute( 'data-index', i );
							li.appendChild( btn );
							results.appendChild( li );
						} );
						results.hidden = false;
					} );
			}, 250 );
		} );

		results.addEventListener( 'click', function ( e ) {
			var index = e.target.getAttribute( 'data-index' );
			if ( index === null ) {
				return;
			}
			var item = found[ index ];
			Object.keys( item.fields ).forEach( function ( key ) {
				var el = document.getElementById( 'cp-' + key );
				if ( el && item.fields[ key ] !== null && item.fields[ key ] !== undefined ) {
					el.value = item.fields[ key ];
				}
			} );
			if ( window.cpTrimLoad && ( ! window.cpTrimHasFactory() || window.confirm( 'Remplacer la structure et les cotes usine du calage par celles de ce contrôle ?' ) ) ) {
				window.cpTrimLoad( item.trim );
			}
			results.hidden = true;
			search.value = '';
		} );
	}
} )();
