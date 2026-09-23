/* Contrôle Parapente — calage : structure, 1ère mesure / mesure finale, offset, décalage par groupe. */
( function () {
	'use strict';

	var root = document.querySelector( '.cp-trim' );
	if ( ! root ) {
		return;
	}

	var ROWS = { A: 'Rangée A', B: 'Rangée B', C: 'Rangée C', D: 'Rangée D', F: 'Freins' };
	var SETS = { initial: '1ère mesure', final: 'Mesure finale' };
	var tolerance = parseFloat( root.getAttribute( 'data-tolerance' ) ) || 0;

	var data = {};
	try {
		data = JSON.parse( root.querySelector( '.cp-trim-data' ).textContent ) || {};
	} catch ( e ) {}
	var state = {
		factory: data.factory || {},
		initial: data.initial || {},
		final: data.final || {}
	};

	var linesBox = root.querySelector( '.cp-trim-lines' );
	var summaryBox = root.querySelector( '.cp-trim-summary' );
	var sidesSelect = root.querySelector( '#cp-trim-sides' );
	var offsetInput = root.querySelector( '#cp-trim-offset' );
	var lockInput = root.querySelector( '#cp-trim-locked' );

	/* ---------- Utilitaires ---------- */

	function num( value ) {
		if ( value === '' || value === null || value === undefined ) {
			return null;
		}
		var n = parseFloat( String( value ).replace( ',', '.' ) );
		return isNaN( n ) ? null : n;
	}

	function esc( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function signed( v ) {
		if ( v === null ) {
			return '—';
		}
		v = Math.round( v * 10 ) / 10;
		if ( v === 0 ) {
			return '0';
		}
		return ( v > 0 ? '+' : '−' ) + String( Math.abs( v ) ).replace( '.', ',' );
	}

	function level( dev ) {
		if ( dev === null ) {
			return '';
		}
		return Math.abs( dev ) <= tolerance ? 'ok' : 'bad';
	}

	function today() {
		var d = new Date();
		return d.getFullYear() + '-' + String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( d.getDate() ).padStart( 2, '0' );
	}

	function sides() {
		return sidesSelect && sidesSelect.value === 'one' ? { G: 'Mesure' } : { G: 'G', D: 'D' };
	}

	function offset() {
		return num( offsetInput ? offsetInput.value : '' ) || 0;
	}

	/* ---------- Structure ---------- */

	function structure() {
		var out = {};
		root.querySelectorAll( '.cp-trim-structure tbody tr' ).forEach( function ( tr ) {
			var row = tr.getAttribute( 'data-row' );
			out[ row ] = [];
			tr.querySelectorAll( '.cp-trim-group-inputs input' ).forEach( function ( input ) {
				var n = parseInt( input.value, 10 );
				out[ row ].push( n > 0 ? Math.min( n, 40 ) : 0 );
			} );
			var total = out[ row ].reduce( function ( a, b ) {
				return a + b;
			}, 0 );
			tr.querySelector( '.cp-trim-total' ).textContent = total;
		} );
		return out;
	}

	function lines() {
		var s = structure();
		var list = [];
		Object.keys( ROWS ).forEach( function ( row ) {
			var n = 0;
			( s[ row ] || [] ).forEach( function ( count, gi ) {
				for ( var k = 0; k < count; k++ ) {
					n++;
					list.push( { id: row + n, row: row, group: gi + 1 } );
				}
			} );
		} );
		return list;
	}

	function groupInput( row, index, value ) {
		return '<label class="cp-trim-group"><span>Gr. ' + index + '</span>' +
			'<input type="number" min="1" max="40" name="cp[trim][structure][' + row + '][]" value="' + esc( value ) + '" /></label>';
	}

	function setGroupCount( tr, count ) {
		var box = tr.querySelector( '.cp-trim-group-inputs' );
		var inputs = box.querySelectorAll( 'label.cp-trim-group' );
		var current = inputs.length;
		count = Math.max( 0, Math.min( 12, count ) );
		for ( var i = current; i < count; i++ ) {
			var last = box.querySelector( 'label.cp-trim-group:last-child input' );
			box.insertAdjacentHTML( 'beforeend', groupInput( tr.getAttribute( 'data-row' ), i + 1, last ? last.value : 4 ) );
		}
		for ( var j = current; j > count; j-- ) {
			box.removeChild( box.lastElementChild );
		}
	}

	root.querySelectorAll( '.cp-trim-structure tbody tr' ).forEach( function ( tr ) {
		var countInput = tr.querySelector( '.cp-trim-group-count' );
		countInput.addEventListener( 'input', function () {
			setGroupCount( tr, parseInt( countInput.value, 10 ) || 0 );
			render();
		} );
		tr.querySelector( '.cp-trim-group-inputs' ).addEventListener( 'input', render );
	} );

	/* ---------- Tableau des mesures ---------- */

	function value( set, id, side ) {
		if ( set === 'factory' ) {
			return state.factory[ id ] !== undefined ? state.factory[ id ] : '';
		}
		return state[ set ][ id ] && state[ set ][ id ][ side ] !== undefined ? state[ set ][ id ][ side ] : '';
	}

	function input( name, val, col, extra ) {
		return '<input type="text" inputmode="decimal" class="cp-trim-input" name="' + name + '" value="' + esc( val ) + '" data-col="' + col + '"' + ( extra || '' ) + ' />';
	}

	function render() {
		var list = lines();
		var sd = sides();
		var sideKeys = Object.keys( sd );
		var locked = lockInput && lockInput.checked;
		var cols = 2 + Object.keys( SETS ).length * sideKeys.length * 2;

		if ( ! list.length ) {
			linesBox.innerHTML = '<p><em>Définissez la structure du suspentage ci-dessus.</em></p>';
			update();
			return;
		}

		var html = '<table class="widefat cp-trim-table"><thead><tr><th rowspan="2">Susp.</th><th rowspan="2">Usine (mm)</th>';
		Object.keys( SETS ).forEach( function ( set ) {
			html += '<th colspan="' + ( sideKeys.length * 2 ) + '" class="cp-trim-set cp-trim-set--' + set + '">' + SETS[ set ] + '</th>';
		} );
		html += '</tr><tr>';
		Object.keys( SETS ).forEach( function ( set ) {
			sideKeys.forEach( function ( side ) {
				html += '<th class="cp-trim-set--' + set + '">' + ( sideKeys.length > 1 ? sd[ side ] + ' (mm)' : 'Mesurée (mm)' ) + '</th><th class="cp-trim-set--' + set + '">Écart</th>';
			} );
		} );
		html += '</tr></thead><tbody>';

		var currentRow = '';
		var currentGroup = '';
		list.forEach( function ( line ) {
			if ( line.row !== currentRow ) {
				currentRow = line.row;
				html += '<tr class="cp-trim-row-head' + ( line.row === 'F' ? ' is-brakes' : '' ) + '"><th colspan="' + cols + '">' + ROWS[ line.row ] + '</th></tr>';
			}
			if ( line.row + line.group !== currentGroup ) {
				currentGroup = line.row + line.group;
				html += '<tr class="cp-trim-group-head"><td colspan="' + cols + '">Groupe ' + line.group + '</td></tr>';
			}
			html += '<tr data-id="' + line.id + '"><th scope="row">' + line.id + '</th>';
			html += '<td>' + input( 'cp[trim][factory][' + line.id + ']', value( 'factory', line.id ), 'factory' ) + '</td>';
			Object.keys( SETS ).forEach( function ( set ) {
				sideKeys.forEach( function ( side ) {
					var ro = set === 'initial' && locked ? ' readonly' : '';
					html += '<td class="cp-trim-set--' + set + '">' + input( 'cp[trim][' + set + '][' + line.id + '][' + side + ']', value( set, line.id, side ), set + side, ro ) + '</td>';
					html += '<td class="cp-trim-dev cp-trim-set--' + set + '" data-dev="' + set + side + '"></td>';
				} );
			} );
			html += '</tr>';
		} );
		html += '</tbody></table>';
		linesBox.innerHTML = html;
		update();
	}

	/* ---------- Calculs ---------- */

	function analyze() {
		var sd = Object.keys( sides() );
		var off = offset();
		var groups = {};
		var order = [];
		var perLine = {};

		lines().forEach( function ( line ) {
			var factory = num( value( 'factory', line.id ) );
			perLine[ line.id ] = {};
			sd.forEach( function ( side ) {
				var key = line.row + '|' + line.group + '|' + side;
				if ( ! groups[ key ] ) {
					groups[ key ] = { row: line.row, group: line.group, side: side, count: 0, initial: [], final: [] };
					order.push( key );
				}
				groups[ key ].count++;
				[ 'initial', 'final' ].forEach( function ( set ) {
					var raw = num( value( set, line.id, side ) );
					var dev = raw === null || factory === null ? null : raw + off - factory;
					perLine[ line.id ][ set + side ] = dev;
					if ( dev !== null ) {
						groups[ key ][ set ].push( dev );
					}
				} );
			} );
		} );

		function stats( devs ) {
			if ( ! devs.length ) {
				return { n: 0, mean: null, max: null };
			}
			var max = devs.reduce( function ( a, b ) {
				return Math.abs( b ) > Math.abs( a ) ? b : a;
			} );
			return { n: devs.length, mean: devs.reduce( function ( a, b ) {
				return a + b;
			}, 0 ) / devs.length, max: max };
		}

		return {
			perLine: perLine,
			groups: order.map( function ( key ) {
				var g = groups[ key ];
				g.initial = stats( g.initial );
				g.final = stats( g.final );
				g.correction = g.initial.mean === null ? null : -g.initial.mean;
				g.adjustment = g.initial.mean === null || g.final.mean === null ? null : g.final.mean - g.initial.mean;
				var ref = g.final.mean !== null ? g.final : g.initial;
				g.basis = g.final.mean !== null ? 'finale' : ( g.initial.mean !== null ? '1ère' : '' );
				g.max = ref.max;
				g.level = level( ref.max );
				return g;
			} )
		};
	}

	function update() {
		var result = analyze();

		linesBox.querySelectorAll( 'tr[data-id]' ).forEach( function ( tr ) {
			var devs = result.perLine[ tr.getAttribute( 'data-id' ) ] || {};
			tr.querySelectorAll( '.cp-trim-dev' ).forEach( function ( cell ) {
				var dev = devs[ cell.getAttribute( 'data-dev' ) ];
				dev = dev === undefined ? null : dev;
				cell.textContent = dev === null ? '' : signed( dev );
				cell.className = cell.className.replace( / ?lvl-\w+/g, '' ) + ( dev === null ? '' : ' lvl-' + level( dev ) );
			} );
		} );

		var both = Object.keys( sides() ).length > 1;
		var sideLabel = { G: 'Gauche', D: 'Droite' };
		var groups = result.groups;
		if ( ! groups.length ) {
			summaryBox.innerHTML = '';
			return;
		}
		var html = '<table class="widefat striped cp-trim-summary-table"><thead><tr><th>Groupe</th>' + ( both ? '<th>Côté</th>' : '' ) +
			'<th>Susp.</th><th>Écart moy. 1ère</th><th>Correction suggérée</th><th>Écart moy. finale</th><th>Ajustement réalisé</th><th>Écart max</th><th>État</th></tr></thead><tbody>';
		groups.forEach( function ( g ) {
			var lvl = g.level;
			html += '<tr class="' + ( g.row === 'F' ? 'is-brakes' : '' ) + '"><td>' + ROWS[ g.row ] + ' — groupe ' + g.group + '</td>' +
				( both ? '<td>' + sideLabel[ g.side ] + '</td>' : '' ) +
				'<td>' + g.initial.n + ' / ' + g.count + '</td>' +
				'<td class="lvl-' + level( g.initial.mean ) + '">' + signed( g.initial.mean ) + '</td>' +
				'<td><strong>' + signed( g.correction ) + '</strong></td>' +
				'<td class="lvl-' + level( g.final.mean ) + '">' + signed( g.final.mean ) + '</td>' +
				'<td>' + signed( g.adjustment ) + '</td>' +
				'<td class="lvl-' + lvl + '">' + signed( g.max ) + ( g.basis ? ' <small>(' + g.basis + ')</small>' : '' ) + '</td>' +
				'<td class="lvl-' + lvl + '">' + ( lvl === 'ok' ? 'Conforme' : ( lvl === 'bad' ? 'Hors tolérance' : '—' ) ) + '</td></tr>';
		} );
		html += '</tbody></table>';
		summaryBox.innerHTML = html;
	}

	/* ---------- Saisie ---------- */

	linesBox.addEventListener( 'input', function ( e ) {
		var el = e.target;
		if ( ! el.classList.contains( 'cp-trim-input' ) ) {
			return;
		}
		var id = el.closest( 'tr' ).getAttribute( 'data-id' );
		var col = el.getAttribute( 'data-col' );
		if ( col === 'factory' ) {
			state.factory[ id ] = el.value;
		} else {
			var set = col.indexOf( 'initial' ) === 0 ? 'initial' : 'final';
			var side = col.slice( set.length );
			state[ set ][ id ] = state[ set ][ id ] || {};
			state[ set ][ id ][ side ] = el.value;
			// Date de la mesure renseignée automatiquement à la première saisie.
			var dateInput = root.querySelector( set === 'initial' ? '#cp-trim-initial-date' : '#cp-trim-final-date' );
			if ( dateInput && ! dateInput.value && el.value !== '' ) {
				dateInput.value = today();
			}
		}
		update();
	} );

	// Entrée : case suivante dans la même colonne (et empêche l'envoi du formulaire).
	linesBox.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== 'Enter' || ! e.target.classList.contains( 'cp-trim-input' ) ) {
			return;
		}
		e.preventDefault();
		var col = e.target.getAttribute( 'data-col' );
		var all = Array.prototype.slice.call( linesBox.querySelectorAll( '.cp-trim-input[data-col="' + col + '"]' ) );
		var next = all[ all.indexOf( e.target ) + ( e.shiftKey ? -1 : 1 ) ];
		if ( next ) {
			next.focus();
			next.select();
		}
	} );

	if ( sidesSelect ) {
		sidesSelect.addEventListener( 'change', render );
	}
	if ( offsetInput ) {
		offsetInput.addEventListener( 'input', update );
	}
	if ( lockInput ) {
		lockInput.addEventListener( 'change', render );
	}

	var copy = root.querySelector( '.cp-trim-copy' );
	if ( copy ) {
		copy.addEventListener( 'click', function () {
			var sideKeys = Object.keys( sides() );
			lines().forEach( function ( line ) {
				sideKeys.forEach( function ( side ) {
					var init = value( 'initial', line.id, side );
					if ( init !== '' && value( 'final', line.id, side ) === '' ) {
						state.final[ line.id ] = state.final[ line.id ] || {};
						state.final[ line.id ][ side ] = init;
					}
				} );
			} );
			var dateInput = root.querySelector( '#cp-trim-final-date' );
			if ( dateInput && ! dateInput.value ) {
				dateInput.value = today();
			}
			render();
		} );
	}

	/**
	 * Charge la structure et les cotes usine d'un contrôle précédent (même modèle d'aile).
	 */
	window.cpTrimLoad = function ( trim ) {
		if ( ! trim ) {
			return;
		}
		if ( trim.sides && sidesSelect ) {
			sidesSelect.value = trim.sides;
		}
		if ( offsetInput && trim.offset !== undefined && trim.offset !== '' ) {
			offsetInput.value = trim.offset;
		}
		root.querySelectorAll( '.cp-trim-structure tbody tr' ).forEach( function ( tr ) {
			var row = tr.getAttribute( 'data-row' );
			var groups = ( trim.structure && trim.structure[ row ] ) || [];
			tr.querySelector( '.cp-trim-group-count' ).value = groups.length;
			tr.querySelector( '.cp-trim-group-inputs' ).innerHTML = groups.map( function ( n, i ) {
				return groupInput( row, i + 1, n );
			} ).join( '' );
		} );
		state.factory = trim.factory || {};
		render();
	};

	window.cpTrimHasFactory = function () {
		return Object.keys( state.factory ).some( function ( k ) {
			return state.factory[ k ] !== '';
		} );
	};

	render();
} )();
