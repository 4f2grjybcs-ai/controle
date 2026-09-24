/* Contrôle Parapente — calage : structure par couleurs, mesures usine, mesures voile, résultat (offset + élévateurs). */
( function () {
	'use strict';

	var root = document.querySelector( '.cp-trim' );
	if ( ! root ) {
		return;
	}

	var data = {};
	try {
		data = JSON.parse( root.querySelector( '.cp-trim-data' ).textContent ) || {};
	} catch ( e ) {}

	var COLORS = data.colors || {};
	var COLOR_KEYS = Object.keys( COLORS );
	var ROWS = data.rows || { A: 'Rangée A', B: 'Rangée B', C: 'Rangée C', D: 'Rangée D', F: 'Freins' };
	var ROW_KEYS = Object.keys( ROWS );
	var tolerance = parseFloat( root.getAttribute( 'data-tolerance' ) ) || 0;

	var state = {
		structure: data.structure || {},
		factory: data.factory || {},
		initial: data.initial || {},
		final: data.final || {},
		riser: data.riser || {}
	};
	ROW_KEYS.forEach( function ( r ) {
		state.structure[ r ] = state.structure[ r ] || [];
	} );

	var $ = function ( sel ) {
		return root.querySelector( sel );
	};
	var structureBox = $( '.cp-trim-structure' );
	var factoryBox = $( '.cp-trim-factory' );
	var measuresBox = $( '.cp-trim-measures' );
	var risersBox = $( '.cp-trim-risers' );
	var summaryBox = $( '.cp-trim-summary' );
	var resultBox = $( '.cp-trim-result' );
	var sidesSelect = $( '#cp-trim-sides' );
	var offsetInput = $( '#cp-trim-offset' );
	var lockInput = $( '#cp-trim-locked' );

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

	function fmt( v ) {
		return String( Math.round( v * 10 ) / 10 ).replace( '.', ',' );
	}

	function signed( v ) {
		if ( v === null || v === undefined ) {
			return '—';
		}
		v = Math.round( v * 10 ) / 10;
		if ( v === 0 ) {
			return '0';
		}
		return ( v > 0 ? '+' : '−' ) + fmt( Math.abs( v ) );
	}

	function level( dev ) {
		if ( dev === null || dev === undefined ) {
			return '';
		}
		return Math.abs( dev ) <= tolerance ? 'ok' : 'bad';
	}

	function today() {
		var d = new Date();
		return d.getFullYear() + '-' + String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( d.getDate() ).padStart( 2, '0' );
	}

	function sides() {
		return sidesSelect && sidesSelect.value === 'one' ? { G: 'Mesure' } : { G: 'Gauche', D: 'Droite' };
	}

	function offset() {
		return num( offsetInput ? offsetInput.value : '' ) || 0;
	}

	function hex( color ) {
		return COLORS[ color ] ? COLORS[ color ].hex : '#ccc';
	}

	function colorName( color ) {
		return COLORS[ color ] ? COLORS[ color ].label : color;
	}

	function swatch( color ) {
		return '<span class="cp-swatch" style="background:' + esc( hex( color ) ) + '"></span>';
	}

	function groupLabel( row, color ) {
		return ( row === 'F' ? 'Freins' : row ) + ' · ' + colorName( color );
	}

	function lines() {
		var list = [];
		ROW_KEYS.forEach( function ( row ) {
			var n = 0;
			state.structure[ row ].forEach( function ( g, gi ) {
				var count = Math.max( 0, Math.min( 40, parseInt( g.count, 10 ) || 0 ) );
				for ( var k = 0; k < count; k++ ) {
					n++;
					list.push( { id: row + n, row: row, group: gi + 1, color: g.color } );
				}
			} );
		} );
		return list;
	}

	function value( set, id, side ) {
		if ( set === 'factory' ) {
			return state.factory[ id ] !== undefined && state.factory[ id ] !== null ? state.factory[ id ] : '';
		}
		return state[ set ][ id ] && state[ set ][ id ][ side ] !== undefined ? state[ set ][ id ][ side ] : '';
	}

	function riser( row, side ) {
		return state.riser[ row ] && state.riser[ row ][ side ] !== undefined ? state.riser[ row ][ side ] : '';
	}

	function input( name, val, col, extra ) {
		return '<input type="text" inputmode="decimal" class="cp-trim-input" name="' + name + '" value="' + esc( val ) + '" data-col="' + col + '"' + ( extra || '' ) + ' />';
	}

	/* ---------- Étapes ---------- */

	var stepButtons = root.querySelectorAll( '.cp-trim-steps [data-step]' );
	var stepPanels = root.querySelectorAll( '[data-step-panel]' );

	function showStep( name ) {
		stepButtons.forEach( function ( b ) {
			b.classList.toggle( 'is-active', b.getAttribute( 'data-step' ) === name );
		} );
		stepPanels.forEach( function ( p ) {
			p.hidden = p.getAttribute( 'data-step-panel' ) !== name;
		} );
		if ( name === 'resultat' ) {
			renderRisers();
			updateResult();
		}
	}
	stepButtons.forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			showStep( b.getAttribute( 'data-step' ) );
		} );
	} );

	/* ---------- 1. Structure & couleurs ---------- */

	function renderStructure() {
		var html = '';
		ROW_KEYS.forEach( function ( row ) {
			var groups = state.structure[ row ];
			var total = groups.reduce( function ( a, g ) {
				return a + ( parseInt( g.count, 10 ) || 0 );
			}, 0 );
			html += '<div class="cp-trim-srow' + ( row === 'F' ? ' is-brakes' : '' ) + '" data-row="' + row + '">';
			html += '<div class="cp-trim-srow-label"><strong>' + esc( ROWS[ row ] ) + '</strong><small>' + total + ' susp.</small></div>';
			html += '<div class="cp-trim-chips">';
			groups.forEach( function ( g, i ) {
				var base = 'cp[trim][structure][' + row + '][' + i + ']';
				html += '<div class="cp-trim-chip" style="--chip:' + esc( hex( g.color ) ) + '">' +
					'<button type="button" class="cp-trim-color" data-row="' + row + '" data-i="' + i + '" title="Changer la couleur">' +
					swatch( g.color ) + '<span>' + esc( colorName( g.color ) ) + '</span></button>' +
					'<input type="hidden" name="' + base + '[color]" value="' + esc( g.color ) + '" />' +
					'<input type="number" min="1" max="40" class="cp-trim-count" data-row="' + row + '" data-i="' + i + '" name="' + base + '[count]" value="' + esc( g.count ) + '" aria-label="Nombre de suspentes" />' +
					'<button type="button" class="cp-trim-chip-del" data-row="' + row + '" data-i="' + i + '" aria-label="Supprimer le groupe">×</button>' +
					'</div>';
			} );
			html += '<button type="button" class="cp-trim-add-group" data-row="' + row + '">＋ Groupe</button>';
			html += '</div></div>';
		} );
		structureBox.innerHTML = html;
	}

	// Palette de couleurs flottante.
	var palette = document.createElement( 'div' );
	palette.className = 'cp-trim-palette';
	palette.hidden = true;
	palette.innerHTML = COLOR_KEYS.map( function ( key ) {
		return '<button type="button" data-color="' + key + '" title="' + esc( COLORS[ key ].label ) + '">' + swatch( key ) + '<span>' + esc( COLORS[ key ].label ) + '</span></button>';
	} ).join( '' );
	root.appendChild( palette );
	var paletteTarget = null;

	structureBox.addEventListener( 'click', function ( e ) {
		var colorBtn = e.target.closest( '.cp-trim-color' );
		var del = e.target.closest( '.cp-trim-chip-del' );
		var add = e.target.closest( '.cp-trim-add-group' );
		if ( colorBtn ) {
			paletteTarget = { row: colorBtn.getAttribute( 'data-row' ), i: +colorBtn.getAttribute( 'data-i' ) };
			var r = colorBtn.getBoundingClientRect();
			var rr = root.getBoundingClientRect();
			palette.style.left = ( r.left - rr.left ) + 'px';
			palette.style.top = ( r.bottom - rr.top + 6 ) + 'px';
			palette.hidden = false;
			e.stopPropagation();
		} else if ( del ) {
			state.structure[ del.getAttribute( 'data-row' ) ].splice( +del.getAttribute( 'data-i' ), 1 );
			structureChanged();
		} else if ( add ) {
			var row = add.getAttribute( 'data-row' );
			var groups = state.structure[ row ];
			var used = groups.map( function ( g ) {
				return g.color;
			} );
			var free = COLOR_KEYS.filter( function ( k ) {
				return used.indexOf( k ) === -1;
			} );
			groups.push( { count: groups.length ? groups[ groups.length - 1 ].count : 4, color: free[ 0 ] || COLOR_KEYS[ 0 ] } );
			structureChanged();
			var counts = structureBox.querySelectorAll( '[data-row="' + row + '"] .cp-trim-count' );
			if ( counts.length ) {
				counts[ counts.length - 1 ].select();
			}
		}
	} );

	palette.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-color]' );
		if ( btn && paletteTarget ) {
			state.structure[ paletteTarget.row ][ paletteTarget.i ].color = btn.getAttribute( 'data-color' );
			palette.hidden = true;
			structureChanged();
		}
	} );
	document.addEventListener( 'click', function ( e ) {
		if ( ! palette.hidden && ! palette.contains( e.target ) ) {
			palette.hidden = true;
		}
	} );

	structureBox.addEventListener( 'input', function ( e ) {
		if ( e.target.classList.contains( 'cp-trim-count' ) ) {
			state.structure[ e.target.getAttribute( 'data-row' ) ][ +e.target.getAttribute( 'data-i' ) ].count = e.target.value;
			// On ne redessine pas la structure pendant la saisie (garde le curseur).
			var srow = e.target.closest( '.cp-trim-srow' );
			var total = state.structure[ srow.getAttribute( 'data-row' ) ].reduce( function ( a, g ) {
				return a + ( parseInt( g.count, 10 ) || 0 );
			}, 0 );
			srow.querySelector( 'small' ).textContent = total + ' susp.';
			renderTables();
		}
	} );

	function structureChanged() {
		renderStructure();
		renderTables();
	}

	/* ---------- 2. Mesures usine ---------- */

	function renderFactory( list ) {
		if ( ! list.length ) {
			factoryBox.innerHTML = '<p class="cp-trim-empty">Définissez d\'abord la structure du suspentage.</p>';
			return;
		}
		var html = '';
		var currentRow = '';
		var currentGroup = '';
		list.forEach( function ( line ) {
			if ( line.row !== currentRow ) {
				if ( currentRow ) {
					html += '</div></div></div>';
				}
				currentRow = line.row;
				currentGroup = '';
				html += '<div class="cp-trim-fblock' + ( line.row === 'F' ? ' is-brakes' : '' ) + '"><h5>' + esc( ROWS[ line.row ] ) + '</h5><div>';
			}
			if ( line.row + line.group !== currentGroup ) {
				if ( currentGroup ) {
					html += '</div>';
				}
				currentGroup = line.row + line.group;
				html += '<div class="cp-trim-fgroup" style="--chip:' + esc( hex( line.color ) ) + '"><span class="cp-trim-fgroup-name">' + swatch( line.color ) + esc( colorName( line.color ) ) + '</span>';
			}
			html += '<label class="cp-trim-fcell"><span>' + line.id + '</span>' + input( 'cp[trim][factory][' + line.id + ']', value( 'factory', line.id ), 'factory' ) + '</label>';
		} );
		html += '</div></div></div>';
		factoryBox.innerHTML = html;
	}

	/* ---------- 3. Mesures voile ---------- */

	function renderMeasures( list ) {
		if ( ! list.length ) {
			measuresBox.innerHTML = '<p class="cp-trim-empty">Définissez d\'abord la structure du suspentage.</p>';
			return;
		}
		var sd = sides();
		var sideKeys = Object.keys( sd );
		var locked = lockInput && lockInput.checked;
		var cols = 2 + sideKeys.length * 2;
		var html = '<table class="widefat cp-trim-table"><thead><tr><th rowspan="2">Susp.</th><th rowspan="2">Usine</th>' +
			'<th colspan="' + sideKeys.length + '" class="cp-trim-set--initial">1ère mesure</th>' +
			'<th colspan="' + sideKeys.length + '" class="cp-trim-set--final">Mesure finale</th></tr><tr>';
		[ 'initial', 'final' ].forEach( function ( set ) {
			sideKeys.forEach( function ( side ) {
				html += '<th class="cp-trim-set--' + set + '">' + ( sideKeys.length > 1 ? sd[ side ] : 'Mesurée' ) + '</th>';
			} );
		} );
		html += '</tr></thead><tbody>';
		var currentRow = '';
		var currentGroup = '';
		list.forEach( function ( line ) {
			if ( line.row !== currentRow ) {
				currentRow = line.row;
				html += '<tr class="cp-trim-row-head' + ( line.row === 'F' ? ' is-brakes' : '' ) + '"><th colspan="' + cols + '">' + esc( ROWS[ line.row ] ) + '</th></tr>';
			}
			if ( line.row + line.group !== currentGroup ) {
				currentGroup = line.row + line.group;
				html += '<tr class="cp-trim-group-head" style="--chip:' + esc( hex( line.color ) ) + '"><td colspan="' + cols + '">' + swatch( line.color ) + esc( colorName( line.color ) ) + '</td></tr>';
			}
			var f = value( 'factory', line.id );
			html += '<tr data-id="' + line.id + '"><th scope="row">' + swatch( line.color ) + line.id + '</th><td class="cp-trim-ref">' + ( f === '' ? '—' : esc( f ) ) + '</td>';
			[ 'initial', 'final' ].forEach( function ( set ) {
				sideKeys.forEach( function ( side ) {
					var ro = set === 'initial' && locked ? ' readonly' : '';
					html += '<td class="cp-trim-set--' + set + '">' + input( 'cp[trim][' + set + '][' + line.id + '][' + side + ']', value( set, line.id, side ), set + side, ro ) + '</td>';
				} );
			} );
			html += '</tr>';
		} );
		html += '</tbody></table>';
		measuresBox.innerHTML = html;
	}

	/* ---------- 4. Résultat ---------- */

	function activeRows() {
		return ROW_KEYS.filter( function ( r ) {
			return state.structure[ r ].some( function ( g ) {
				return ( parseInt( g.count, 10 ) || 0 ) > 0;
			} );
		} );
	}

	function renderRisers() {
		var sd = sides();
		var sideKeys = Object.keys( sd );
		var rows = activeRows();
		if ( ! rows.length ) {
			risersBox.innerHTML = '';
			return;
		}
		var html = '<table class="cp-trim-riser-table"><thead><tr><th></th>';
		sideKeys.forEach( function ( side ) {
			html += '<th>' + ( sideKeys.length > 1 ? sd[ side ] : 'Réglage' ) + '</th>';
		} );
		html += '</tr></thead><tbody>';
		rows.forEach( function ( row ) {
			html += '<tr' + ( row === 'F' ? ' class="is-brakes"' : '' ) + '><th>' + esc( row === 'F' ? 'Freins' : 'Élévateur ' + row ) + '</th>';
			sideKeys.forEach( function ( side ) {
				html += '<td><div class="cp-stepper" data-step="1">' +
					'<button type="button" class="cp-step-down" aria-label="−1 mm">−</button>' +
					'<input type="text" inputmode="decimal" class="cp-trim-riser" data-row="' + row + '" data-side="' + side + '" name="cp[trim][riser][' + row + '][' + side + ']" value="' + esc( riser( row, side ) ) + '" placeholder="0" />' +
					'<button type="button" class="cp-step-up" aria-label="+1 mm">+</button><span class="cp-unit">mm</span></div></td>';
			} );
			html += '</tr>';
		} );
		html += '</tbody></table>';
		risersBox.innerHTML = html;
	}

	function analyze() {
		var sideKeys = Object.keys( sides() );
		var off = offset();
		var groups = {};
		var order = [];
		var perLine = [];

		lines().forEach( function ( line ) {
			var factory = num( value( 'factory', line.id ) );
			var entry = { line: line, factory: factory, sides: {} };
			sideKeys.forEach( function ( side ) {
				var rs = num( riser( line.row, side ) ) || 0;
				var key = line.row + '|' + line.group + '|' + side;
				if ( ! groups[ key ] ) {
					groups[ key ] = { row: line.row, group: line.group, color: line.color, side: side, count: 0, riser: rs, initial: [], final: [], result: [], base: [] };
					order.push( key );
				}
				groups[ key ].count++;
				var s = {};
				[ 'initial', 'final' ].forEach( function ( set ) {
					var raw = num( value( set, line.id, side ) );
					s[ set ] = raw === null || factory === null ? null : raw + off - factory;
					if ( s[ set ] !== null ) {
						groups[ key ][ set ].push( s[ set ] );
					}
				} );
				var base = s.final !== null ? s.final : s.initial;
				s.measured = base === null ? null : base + factory;
				s.base = base;
				s.result = base === null ? null : base + rs;
				if ( base !== null ) {
					groups[ key ].base.push( base );
					groups[ key ].result.push( s.result );
				}
				entry.sides[ side ] = s;
			} );
			perLine.push( entry );
		} );

		function stats( devs ) {
			if ( ! devs.length ) {
				return { n: 0, mean: null, max: null };
			}
			return {
				n: devs.length,
				mean: devs.reduce( function ( a, b ) {
					return a + b;
				}, 0 ) / devs.length,
				max: devs.reduce( function ( a, b ) {
					return Math.abs( b ) > Math.abs( a ) ? b : a;
				} )
			};
		}

		return {
			lines: perLine,
			groups: order.map( function ( key ) {
				var g = groups[ key ];
				[ 'initial', 'final', 'result', 'base' ].forEach( function ( set ) {
					g[ set ] = stats( g[ set ] );
				} );
				g.correction = g.result.mean === null ? null : -g.result.mean;
				g.level = level( g.result.max );
				return g;
			} )
		};
	}

	function updateResult() {
		var result = analyze();
		var sd = sides();
		var sideKeys = Object.keys( sd );
		var both = sideKeys.length > 1;

		if ( ! result.groups.some( function ( g ) {
			return g.result.n;
		} ) ) {
			summaryBox.innerHTML = '<p class="cp-trim-empty">Saisissez les mesures usine et les mesures voile pour voir le résultat.</p>';
			resultBox.innerHTML = '';
			return;
		}

		// Décalage par groupe.
		var html = '<table class="widefat cp-trim-summary-table"><thead><tr><th>Groupe</th>' + ( both ? '<th>Côté</th>' : '' ) +
			'<th>Susp.</th><th>Écart moy. 1ère</th><th>Écart moy. finale</th><th>Élévateur</th><th>Résultat moyen</th><th>Écart max</th><th>Reste à corriger</th><th>État</th></tr></thead><tbody>';
		result.groups.forEach( function ( g ) {
			html += '<tr class="' + ( g.row === 'F' ? 'is-brakes' : '' ) + '" style="--chip:' + esc( hex( g.color ) ) + '">' +
				'<td class="cp-trim-gname">' + swatch( g.color ) + esc( groupLabel( g.row, g.color ) ) + '</td>' +
				( both ? '<td>' + sd[ g.side ] + '</td>' : '' ) +
				'<td>' + g.result.n + ' / ' + g.count + '</td>' +
				'<td class="lvl-' + level( g.initial.mean ) + '">' + signed( g.initial.mean ) + '</td>' +
				'<td class="lvl-' + level( g.final.mean ) + '">' + signed( g.final.mean ) + '</td>' +
				'<td>' + ( g.riser ? signed( g.riser ) : '0' ) + '</td>' +
				'<td class="cp-trim-strong lvl-' + level( g.result.mean ) + '">' + signed( g.result.mean ) + '</td>' +
				'<td class="lvl-' + g.level + '">' + signed( g.result.max ) + '</td>' +
				'<td><strong>' + signed( g.correction ) + '</strong></td>' +
				'<td><span class="cp-trim-state cp-trim-state--' + ( g.level || 'none' ) + '">' + ( g.level === 'ok' ? 'Conforme' : ( g.level === 'bad' ? 'Hors tolérance' : '—' ) ) + '</span></td></tr>';
		} );
		html += '</tbody></table>';
		summaryBox.innerHTML = html;

		// Détail par suspente.
		var cols = 2 + sideKeys.length * 3;
		html = '<table class="widefat cp-trim-table cp-trim-result-table"><thead><tr><th rowspan="2">Susp.</th><th rowspan="2">Usine</th>';
		sideKeys.forEach( function ( side ) {
			html += '<th colspan="3">' + ( both ? sd[ side ] : 'Mesure' ) + '</th>';
		} );
		html += '</tr><tr>';
		sideKeys.forEach( function () {
			html += '<th>Mesurée + offset</th><th>Écart</th><th>Avec élévateur</th>';
		} );
		html += '</tr></thead><tbody>';
		var currentRow = '';
		var currentGroup = '';
		result.lines.forEach( function ( entry ) {
			var line = entry.line;
			if ( line.row !== currentRow ) {
				currentRow = line.row;
				html += '<tr class="cp-trim-row-head' + ( line.row === 'F' ? ' is-brakes' : '' ) + '"><th colspan="' + cols + '">' + esc( ROWS[ line.row ] ) + '</th></tr>';
			}
			if ( line.row + line.group !== currentGroup ) {
				currentGroup = line.row + line.group;
				html += '<tr class="cp-trim-group-head" style="--chip:' + esc( hex( line.color ) ) + '"><td colspan="' + cols + '">' + swatch( line.color ) + esc( colorName( line.color ) ) + '</td></tr>';
			}
			html += '<tr><th scope="row">' + swatch( line.color ) + line.id + '</th><td class="cp-trim-ref">' + ( entry.factory === null ? '—' : fmt( entry.factory ) ) + '</td>';
			sideKeys.forEach( function ( side ) {
				var s = entry.sides[ side ];
				html += '<td>' + ( s.measured === null ? '—' : fmt( s.measured ) ) + '</td>' +
					'<td class="lvl-' + level( s.base ) + '">' + signed( s.base ) + '</td>' +
					'<td class="cp-trim-strong lvl-' + level( s.result ) + '">' + signed( s.result ) + '</td>';
			} );
			html += '</tr>';
		} );
		html += '</tbody></table>';
		resultBox.innerHTML = html;
	}

	function renderTables() {
		var list = lines();
		renderFactory( list );
		renderMeasures( list );
		renderRisers();
		updateResult();
	}

	/* ---------- Saisie ---------- */

	function onValueInput( e ) {
		var el = e.target;
		if ( el === offsetInput ) {
			updateResult();
			return;
		}
		if ( el.classList.contains( 'cp-trim-riser' ) ) {
			var row = el.getAttribute( 'data-row' );
			state.riser[ row ] = state.riser[ row ] || {};
			state.riser[ row ][ el.getAttribute( 'data-side' ) ] = el.value;
			updateResult();
			return;
		}
		if ( ! el.classList.contains( 'cp-trim-input' ) ) {
			return;
		}
		var col = el.getAttribute( 'data-col' );
		if ( col === 'factory' ) {
			state.factory[ el.name.match( /\[factory\]\[([A-Z]\d+)\]/ )[ 1 ] ] = el.value;
			renderMeasuresRefs();
		} else {
			var id = el.closest( 'tr' ).getAttribute( 'data-id' );
			var set = col.indexOf( 'initial' ) === 0 ? 'initial' : 'final';
			var side = col.slice( set.length );
			state[ set ][ id ] = state[ set ][ id ] || {};
			state[ set ][ id ][ side ] = el.value;
			var dateInput = $( set === 'initial' ? '#cp-trim-initial-date' : '#cp-trim-final-date' );
			if ( dateInput && ! dateInput.value && el.value !== '' ) {
				dateInput.value = today();
			}
		}
		updateResult();
	}

	// Met à jour la colonne « Usine » des mesures voile sans redessiner (garde le curseur).
	function renderMeasuresRefs() {
		measuresBox.querySelectorAll( 'tr[data-id]' ).forEach( function ( tr ) {
			var f = value( 'factory', tr.getAttribute( 'data-id' ) );
			tr.querySelector( '.cp-trim-ref' ).textContent = f === '' ? '—' : f;
		} );
	}

	root.addEventListener( 'input', onValueInput );

	// Entrée : case suivante dans la même colonne (et empêche l'envoi du formulaire).
	root.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== 'Enter' || ! e.target.matches( '.cp-trim-input, .cp-trim-riser, .cp-trim-count, #cp-trim-offset' ) ) {
			return;
		}
		e.preventDefault();
		if ( ! e.target.classList.contains( 'cp-trim-input' ) ) {
			return;
		}
		var col = e.target.getAttribute( 'data-col' );
		var all = Array.prototype.slice.call( e.target.closest( '[data-step-panel]' ).querySelectorAll( '.cp-trim-input[data-col="' + col + '"]' ) );
		var next = all[ all.indexOf( e.target ) + ( e.shiftKey ? -1 : 1 ) ];
		if ( next ) {
			next.focus();
			next.select();
		}
	} );

	// Boutons − / + (offset et élévateurs).
	root.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.cp-step-up, .cp-step-down' );
		if ( ! btn ) {
			return;
		}
		var stepper = btn.closest( '.cp-stepper' );
		var field = stepper.querySelector( 'input' );
		var step = parseFloat( stepper.getAttribute( 'data-step' ) ) || 1;
		if ( e.shiftKey ) {
			step *= 5;
		}
		var v = ( num( field.value ) || 0 ) + ( btn.classList.contains( 'cp-step-up' ) ? step : -step );
		v = Math.round( v * 10 ) / 10;
		field.value = v === 0 ? '' : String( v );
		field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	} );

	// Proposer le réglage : ramène l'écart moyen de chaque rangée (et côté) à 0.
	$( '.cp-trim-suggest' ).addEventListener( 'click', function () {
		var result = analyze();
		var sums = {};
		result.lines.forEach( function ( entry ) {
			Object.keys( entry.sides ).forEach( function ( side ) {
				var base = entry.sides[ side ].base;
				if ( base === null ) {
					return;
				}
				var key = entry.line.row + '|' + side;
				sums[ key ] = sums[ key ] || { total: 0, n: 0 };
				sums[ key ].total += base;
				sums[ key ].n++;
			} );
		} );
		Object.keys( sums ).forEach( function ( key ) {
			var parts = key.split( '|' );
			var v = -Math.round( sums[ key ].total / sums[ key ].n );
			state.riser[ parts[ 0 ] ] = state.riser[ parts[ 0 ] ] || {};
			state.riser[ parts[ 0 ] ][ parts[ 1 ] ] = v === 0 ? '' : String( v );
		} );
		renderRisers();
		updateResult();
	} );

	$( '.cp-trim-reset' ).addEventListener( 'click', function () {
		state.riser = {};
		renderRisers();
		updateResult();
	} );

	if ( sidesSelect ) {
		sidesSelect.addEventListener( 'change', renderTables );
	}
	if ( lockInput ) {
		lockInput.addEventListener( 'change', function () {
			renderMeasures( lines() );
		} );
	}

	$( '.cp-trim-copy' ).addEventListener( 'click', function () {
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
		var dateInput = $( '#cp-trim-final-date' );
		if ( dateInput && ! dateInput.value ) {
			dateInput.value = today();
		}
		renderMeasures( lines() );
		updateResult();
	} );

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
		ROW_KEYS.forEach( function ( row ) {
			state.structure[ row ] = ( ( trim.structure && trim.structure[ row ] ) || [] ).map( function ( g, i ) {
				return typeof g === 'object' ? { count: g.count, color: g.color } : { count: g, color: COLOR_KEYS[ i % COLOR_KEYS.length ] };
			} );
		} );
		state.factory = trim.factory || {};
		structureChanged();
	};

	window.cpTrimHasFactory = function () {
		return Object.keys( state.factory ).some( function ( k ) {
			return state.factory[ k ] !== '';
		} );
	};

	renderStructure();
	renderTables();
	showStep( root.getAttribute( 'data-step' ) || 'structure' );
} )();
