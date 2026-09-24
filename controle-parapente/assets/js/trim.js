/* Contrôle Parapente — calage : structure par couleurs et feuille de calage (sur le modèle du tableur de l'atelier). */
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
		final: data.final || {}
	};
	ROW_KEYS.forEach( function ( r ) {
		state.structure[ r ] = state.structure[ r ] || [];
	} );
	var view = { set: 'initial', side: 'G' };

	var $ = function ( sel ) {
		return root.querySelector( sel );
	};
	var structureBox = $( '.cp-trim-structure' );
	var sheetBox = $( '.cp-sheet' );
	var factoryBox = $( '.cp-sheet-factory' );
	var grids = [ factoryBox, sheetBox ].filter( Boolean );
	var tolInput = $( '#cp-trim-tol' );
	var summaryBox = $( '.cp-trim-summary' );
	var previewBox = $( '.cp-wing-preview' );
	var jsonInput = $( '.cp-trim-json' );
	var sidesSelect = $( '#cp-trim-sides' );
	var offsetInput = $( '#cp-trim-offset' );
	var riserInput = $( '#cp-trim-riser' );
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
		return v === null ? '' : String( Math.round( v * 10 ) / 10 ).replace( '.', ',' );
	}

	function signed( v ) {
		if ( v === null || v === undefined ) {
			return '';
		}
		v = Math.round( v * 10 ) / 10;
		if ( v === 0 ) {
			return '0';
		}
		return ( v > 0 ? '+' : '−' ) + fmt( Math.abs( v ) );
	}

	/** Tolérance de la fiche (case « Tolérance ± »), sinon valeur par défaut. */
	function tol() {
		var v = num( tolInput ? tolInput.value : '' );
		return v === null || v <= 0 ? tolerance : v;
	}

	function level( dev ) {
		if ( dev === null || dev === undefined ) {
			return '';
		}
		return Math.abs( dev ) <= tol() ? 'ok' : 'bad';
	}

	function today() {
		var d = new Date();
		return d.getFullYear() + '-' + String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( d.getDate() ).padStart( 2, '0' );
	}

	function bothSides() {
		return ! sidesSelect || sidesSelect.value !== 'one';
	}

	function sideKeys() {
		return bothSides() ? [ 'G', 'D' ] : [ 'G' ];
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

	function rowShort( row ) {
		return row === 'F' ? 'Freins' : row;
	}

	function count( g ) {
		return Math.max( 0, Math.min( 40, parseInt( g.count, 10 ) || 0 ) );
	}

	/** Suspentes d'une rangée : [ { id, n, group, color } ]. */
	function rowLines( row ) {
		var list = [];
		var n = 0;
		state.structure[ row ].forEach( function ( g, gi ) {
			for ( var k = 0; k < count( g ); k++ ) {
				n++;
				list.push( { id: row + n, n: n, group: gi + 1, color: g.color } );
			}
		} );
		return list;
	}

	/** Cases d'une rangée dans la feuille : une suspente, ou null pour une case vide. */
	function rowSlots( row ) {
		var slots = [];
		var n = 0;
		state.structure[ row ].forEach( function ( g, gi ) {
			for ( var k = 0; k < count( g ); k++ ) {
				n++;
				slots.push( { id: row + n, n: n, group: gi + 1, color: g.color } );
			}
			for ( var v = 0; v < gap( g ); v++ ) {
				slots.push( { id: null, group: gi + 1, color: g.color } );
			}
		} );
		return slots;
	}

	function gap( g ) {
		return Math.max( 0, Math.min( 40, parseInt( g.gap, 10 ) || 0 ) );
	}

	/**
	 * Aligne les groupes entre les rangées A à D : chaque groupe prend la hauteur
	 * de la rangée qui a le plus de suspentes dans ce groupe ; les autres reçoivent des cases vides.
	 */
	function alignGroups() {
		var rows = [ 'A', 'B', 'C', 'D' ].filter( function ( r ) {
			return state.structure[ r ] && state.structure[ r ].length;
		} );
		var groupsCount = Math.max.apply( null, rows.map( function ( r ) {
			return state.structure[ r ].length;
		} ).concat( [ 0 ] ) );
		for ( var gi = 0; gi < groupsCount; gi++ ) {
			var height = 0;
			rows.forEach( function ( r ) {
				var g = state.structure[ r ][ gi ];
				if ( g ) {
					height = Math.max( height, count( g ) );
				}
			} );
			rows.forEach( function ( r ) {
				var g = state.structure[ r ][ gi ];
				if ( g ) {
					g.gap = height - count( g );
				}
			} );
		}
		structureChanged();
	}

	function activeRows() {
		return ROW_KEYS.filter( function ( r ) {
			return rowLines( r ).length > 0;
		} );
	}

	function factory( id ) {
		return state.factory[ id ] !== undefined && state.factory[ id ] !== null ? state.factory[ id ] : '';
	}

	function measure( set, id, side ) {
		return state[ set ][ id ] && state[ set ][ id ][ side ] !== undefined ? state[ set ][ id ][ side ] : '';
	}

	/** Usine corrigée = usine + élévateur + offset. */
	function corrected( id ) {
		var f = num( factory( id ) );
		if ( f === null ) {
			return null;
		}
		return f + ( num( riserInput ? riserInput.value : '' ) || 0 ) + ( num( offsetInput ? offsetInput.value : '' ) || 0 );
	}

	/** Résultat = voile − usine corrigée. */
	function result( set, id, side ) {
		var m = num( measure( set, id, side ) );
		var c = corrected( id );
		return m === null || c === null ? null : m - c;
	}

	function syncJson() {
		jsonInput.value = JSON.stringify( { factory: state.factory, initial: state.initial, final: state.final } );
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
		if ( name === 'feuille' || name === 'usine' ) {
			renderSheet();
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
				return a + count( g );
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
					'<input type="number" min="1" max="40" class="cp-trim-count" data-row="' + row + '" data-i="' + i + '" name="' + base + '[count]" value="' + esc( g.count ) + '" aria-label="Nombre de suspentes" title="Nombre de suspentes" />' +
					'<label class="cp-trim-gap" title="Cases vides laissées après ce groupe dans la feuille">+<input type="number" min="0" max="40" class="cp-trim-gap-input" data-row="' + row + '" data-i="' + i + '" name="' + base + '[gap]" value="' + esc( gap( g ) ) + '" aria-label="Cases vides" /> vide</label>' +
					'<button type="button" class="cp-trim-chip-del" data-row="' + row + '" data-i="' + i + '" aria-label="Supprimer le groupe">×</button>' +
					'</div>';
			} );
			html += '<button type="button" class="cp-trim-add-group" data-row="' + row + '">＋ Groupe</button>';
			html += '</div></div>';
		} );
		html += '<p class="cp-trim-align"><button type="button" class="button cp-trim-align-btn">Aligner les groupes entre A, B, C, D</button> ' +
			'<span class="description">Ajoute automatiquement des cases vides quand un groupe n\'a pas le même nombre de suspentes sur chaque rangée (ex. 4 A, 4 B, 4 C mais 5 D).</span></p>';
		structureBox.innerHTML = html;
	}

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
		if ( e.target.closest( '.cp-trim-align-btn' ) ) {
			alignGroups();
			return;
		}
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
			groups.push( { count: groups.length ? groups[ groups.length - 1 ].count : 4, color: free[ 0 ] || COLOR_KEYS[ 0 ], gap: 0 } );
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
		if ( e.target.classList.contains( 'cp-trim-gap-input' ) ) {
			state.structure[ e.target.getAttribute( 'data-row' ) ][ +e.target.getAttribute( 'data-i' ) ].gap = e.target.value;
			schedulePreview();
			return;
		}
		if ( e.target.classList.contains( 'cp-trim-count' ) ) {
			state.structure[ e.target.getAttribute( 'data-row' ) ][ +e.target.getAttribute( 'data-i' ) ].count = e.target.value;
			var srow = e.target.closest( '.cp-trim-srow' );
			srow.querySelector( 'small' ).textContent = rowLines( srow.getAttribute( 'data-row' ) ).length + ' susp.';
			schedulePreview();
		}
	} );

	function structureChanged() {
		renderStructure();
		renderSheet();
	}

	/* ---------- 2. Feuille de calage ---------- */

	function cellStyle( line ) {
		return ' style="--chip:' + esc( hex( line.color ) ) + '"';
	}

	function renderSheet() {
		var rows = activeRows();
		if ( ! rows.length ) {
			grids.forEach( function ( g ) {
				g.innerHTML = '<p class="cp-trim-empty">Définissez d\'abord la structure du suspentage (étape 1).</p>';
			} );
			updateComputed();
			return;
		}
		var sideLabel = bothSides() ? ' — ' + ( view.side === 'G' ? 'gauche' : 'droite' ) : '';
		if ( factoryBox ) {
			renderGrid( factoryBox, rows, [
				{ key: 'usine', label: 'Mesures usine', cls: 'is-factory' },
				{ key: 'corr', label: 'Usine corrigée', cls: 'is-corr' }
			], false );
		}
		renderGrid( sheetBox, rows, [
			{ key: 'voile', label: ( view.set === 'initial' ? '1ère mesure voile' : '2e mesure voile' ) + sideLabel, cls: 'is-measure' },
			{ key: 'res', label: 'Résultat (écart à l\'usine corrigée)', cls: 'is-result' }
		], true );
		updateComputed();
	}

	/**
	 * Tableau par numéro de case : une colonne par rangée dans chaque bloc.
	 *
	 * @param {Element} box       Conteneur.
	 * @param {Array}   rows      Rangées actives.
	 * @param {Array}   blocks    Blocs de colonnes.
	 * @param {boolean} withStats Colonnes Max / Min / Diff / Moyenne et ligne des moyennes.
	 */
	function renderGrid( box, rows, blocks, withStats ) {
		var lines = {};
		var max = 0;
		rows.forEach( function ( r ) {
			lines[ r ] = rowSlots( r );
			max = Math.max( max, lines[ r ].length );
		} );
		var locked = view.set === 'initial' && lockInput && lockInput.checked;

		var html = '<table class="cp-sheet-table"><thead><tr><th class="cp-sheet-n" rowspan="2">N°</th>';
		blocks.forEach( function ( b ) {
			html += '<th colspan="' + rows.length + '" class="cp-sheet-block ' + b.cls + '">' + esc( b.label ) + '</th>';
		} );
		if ( withStats ) {
			html += '<th colspan="3" class="cp-sheet-block is-diff">Différence</th>';
		}
		html += '</tr><tr>';
		blocks.forEach( function ( b ) {
			rows.forEach( function ( r ) {
				html += '<th class="' + b.cls + '">' + rowShort( r ) + '</th>';
			} );
		} );
		if ( withStats ) {
			html += '<th class="is-diff">Max</th><th class="is-diff">Min</th><th class="is-diff">Diff</th>';
		}
		html += '</tr></thead><tbody>';

		for ( var i = 0; i < max; i++ ) {
			html += '<tr data-n="' + ( i + 1 ) + '"><th class="cp-sheet-n">' + ( i + 1 ) + '</th>';
			blocks.forEach( function ( b, bi ) {
				rows.forEach( function ( r, ri ) {
					var line = lines[ r ][ i ];
					var first = ri === 0 ? ' is-first' : '';
					if ( ! line ) {
						html += '<td class="cp-sheet-empty' + first + '"></td>';
						return;
					}
					if ( ! line.id ) {
						// Case laissée vide pour aligner les groupes.
						html += '<td class="cp-sheet-gap' + first + '"' + cellStyle( line ) + ( bi === 0 ? ' title="Case vide"' : '' ) + '></td>';
						return;
					}
					if ( b.key === 'usine' ) {
						html += '<td class="cp-sheet-cell' + first + '"' + cellStyle( line ) + '><span class="cp-sheet-lbl">' + line.id + '</span><input class="cp-sheet-input" data-kind="factory" data-id="' + line.id + '" data-col="' + ( bi * 10 + ri ) + '" value="' + esc( factory( line.id ) ) + '" inputmode="decimal" aria-label="Usine ' + line.id + '" /></td>';
					} else if ( b.key === 'voile' ) {
						html += '<td class="cp-sheet-cell' + first + '"' + cellStyle( line ) + ' data-voile="' + line.id + '"><span class="cp-sheet-lbl">' + line.id + '</span><input class="cp-sheet-input" data-kind="measure" data-id="' + line.id + '" data-col="' + ( bi * 10 + ri ) + '" value="' + esc( measure( view.set, line.id, view.side ) ) + '" inputmode="decimal" aria-label="Voile ' + line.id + '"' + ( locked ? ' readonly' : '' ) + ' /></td>';
					} else {
						html += '<td class="cp-sheet-calc ' + b.cls + first + '"' + cellStyle( line ) + ' data-calc="' + b.key + '" data-id="' + line.id + '"></td>';
					}
				} );
			} );
			if ( withStats ) {
				html += '<td class="cp-sheet-calc is-diff is-first" data-stat="max"></td><td class="cp-sheet-calc is-diff" data-stat="min"></td><td class="cp-sheet-calc is-diff is-last" data-stat="diff"></td>';
			}
			html += '</tr>';
		}
		html += '</tbody>';

		if ( withStats ) {
			// Moyennes par rangée (dernière ligne).
			html += '<tfoot><tr><th class="cp-sheet-n">Moy.</th>';
			blocks.forEach( function ( b ) {
				rows.forEach( function ( r, ri ) {
					html += '<td class="' + ( ri === 0 ? 'is-first ' : '' ) + ( b.key === 'res' ? 'cp-sheet-calc is-result" data-rowmean="' + r : '' ) + '"></td>';
				} );
			} );
			html += '<td colspan="3"></td></tr></tfoot>';
		}
		box.innerHTML = html + '</table>';
	}

	function updateComputed() {
		// Cellules calculées (usine corrigée et résultats).
		root.querySelectorAll( '[data-calc]' ).forEach( function ( td ) {
			var id = td.getAttribute( 'data-id' );
			var v;
			if ( td.getAttribute( 'data-calc' ) === 'corr' ) {
				v = corrected( id );
				td.textContent = fmt( v );
			} else {
				v = result( view.set, id, view.side );
				td.textContent = signed( v );
				td.classList.toggle( 'lvl-ok', level( v ) === 'ok' );
				td.classList.toggle( 'lvl-bad', level( v ) === 'bad' );
			}
		} );

		// La case de mesure voile change aussi de couleur hors tolérance.
		sheetBox.querySelectorAll( '[data-voile]' ).forEach( function ( td ) {
			var lv = level( result( view.set, td.getAttribute( 'data-voile' ), view.side ) );
			td.classList.toggle( 'is-ok', lv === 'ok' );
			td.classList.toggle( 'is-bad', lv === 'bad' );
		} );

		// Différence et moyenne par numéro de suspente (entre les rangées).
		sheetBox.querySelectorAll( 'tbody tr[data-n]' ).forEach( function ( tr ) {
			var values = [];
			tr.querySelectorAll( '[data-calc="res"]' ).forEach( function ( td ) {
				var v = result( view.set, td.getAttribute( 'data-id' ), view.side );
				if ( v !== null ) {
					values.push( v );
				}
			} );
			var stats = { max: '', min: '', diff: '' };
			if ( values.length ) {
				var mx = Math.max.apply( null, values );
				var mn = Math.min.apply( null, values );
				stats = {
					max: signed( mx ),
					min: signed( mn ),
					diff: fmt( mx - mn )
				};
			}
			Object.keys( stats ).forEach( function ( k ) {
				tr.querySelector( '[data-stat="' + k + '"]' ).textContent = stats[ k ];
			} );
		} );

		// Moyenne des résultats par rangée.
		sheetBox.querySelectorAll( '[data-rowmean]' ).forEach( function ( td ) {
			var values = rowLines( td.getAttribute( 'data-rowmean' ) ).map( function ( l ) {
				return result( view.set, l.id, view.side );
			} ).filter( function ( v ) {
				return v !== null;
			} );
			var m = values.length ? values.reduce( function ( a, b ) {
				return a + b;
			}, 0 ) / values.length : null;
			td.textContent = signed( m );
			td.classList.toggle( 'lvl-ok', level( m ) === 'ok' );
			td.classList.toggle( 'lvl-bad', level( m ) === 'bad' );
		} );

		renderSummary();
		syncJson();
		schedulePreview();
	}

	/* ---------- Écart moyen par groupe ---------- */

	function mean( values ) {
		return values.length ? values.reduce( function ( a, b ) {
			return a + b;
		}, 0 ) / values.length : null;
	}

	function renderSummary() {
		var sides = sideKeys();
		var both = sides.length > 1;
		var rows = activeRows();
		if ( ! rows.length ) {
			summaryBox.innerHTML = '';
			return;
		}
		var html = '<table class="cp-sheet-summary"><thead><tr><th>Groupe</th>';
		sides.forEach( function ( side ) {
			var label = both ? ( side === 'G' ? ' G' : ' D' ) : '';
			html += '<th>1ère' + label + '</th><th>2e' + label + '</th>';
		} );
		html += '</tr></thead><tbody>';
		var any = false;
		rows.forEach( function ( row ) {
			state.structure[ row ].forEach( function ( g, gi ) {
				var ids = rowLines( row ).filter( function ( l ) {
					return l.group === gi + 1;
				} ).map( function ( l ) {
					return l.id;
				} );
				if ( ! ids.length ) {
					return;
				}
				html += '<tr style="--chip:' + esc( hex( g.color ) ) + '"><td class="cp-sheet-gname">' + swatch( g.color ) + esc( rowShort( row ) + ' · ' + colorName( g.color ) ) + '</td>';
				sides.forEach( function ( side ) {
					[ 'initial', 'final' ].forEach( function ( set ) {
						var m = mean( ids.map( function ( id ) {
							return result( set, id, side );
						} ).filter( function ( v ) {
							return v !== null;
						} ) );
						if ( m !== null ) {
							any = true;
						}
						var current = set === view.set && side === view.side ? ' is-current' : '';
						html += '<td class="lvl-' + level( m ) + current + '">' + ( m === null ? '·' : signed( m ) ) + '</td>';
					} );
				} );
				html += '</tr>';
			} );
		} );
		html += '</tbody></table>';
		summaryBox.innerHTML = any ? html : '<p class="cp-trim-empty">Saisissez les mesures pour voir les écarts par groupe.</p>';
	}

	/* ---------- Aperçu du rapport client (dessin généré par le serveur) ---------- */

	var previewTimer = null;
	var previewSeq = 0;
	function schedulePreview() {
		clearTimeout( previewTimer );
		previewTimer = setTimeout( loadPreview, 600 );
	}

	function loadPreview() {
		var url = root.getAttribute( 'data-preview-url' );
		if ( ! url || ! window.fetch ) {
			return;
		}
		var seq = ++previewSeq;
		var body = new FormData();
		body.append( 'action', 'cp_wing_preview' );
		body.append( 'nonce', root.getAttribute( 'data-preview-nonce' ) );
		body.append( 'trim', JSON.stringify( {
			sides: sidesSelect ? sidesSelect.value : 'both',
			offset: offsetInput ? offsetInput.value : '',
			riser_length: riserInput ? riserInput.value : '',
			tolerance: tolInput ? tolInput.value : '',
			structure: state.structure,
			factory: state.factory,
			initial: state.initial,
			final: state.final
		} ) );
		fetch( url, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( json ) {
				if ( seq !== previewSeq || ! json || ! json.success ) {
					return;
				}
				previewBox.innerHTML = json.data || '<p class="cp-trim-empty">L\'aperçu apparaît dès les premières mesures.</p>';
			} )
			.catch( function () {} );
	}

	/* ---------- Saisie dans la feuille ---------- */

	grids.forEach( function ( grid ) { grid.addEventListener( 'input', function ( e ) {
		var el = e.target;
		if ( ! el.classList.contains( 'cp-sheet-input' ) ) {
			return;
		}
		setValue( el.getAttribute( 'data-kind' ), el.getAttribute( 'data-id' ), el.value );
		updateComputed();
	} ); } );

	function setValue( kind, id, v ) {
		if ( kind === 'factory' ) {
			state.factory[ id ] = v;
			return;
		}
		state[ view.set ][ id ] = state[ view.set ][ id ] || {};
		state[ view.set ][ id ][ view.side ] = v;
		var dateInput = $( view.set === 'initial' ? '#cp-trim-initial-date' : '#cp-trim-final-date' );
		if ( dateInput && ! dateInput.value && v !== '' ) {
			dateInput.value = today();
		}
	}

	function inputsByCol( col, grid ) {
		return Array.prototype.slice.call( grid.querySelectorAll( '.cp-sheet-input[data-col="' + col + '"]' ) );
	}

	function focusCell( el ) {
		if ( el ) {
			el.focus();
			el.select();
		}
	}

	// Navigation au clavier comme dans un tableur.
	grids.forEach( function ( grid ) { grid.addEventListener( 'keydown', function ( e ) {
		var el = e.target;
		if ( ! el.classList.contains( 'cp-sheet-input' ) ) {
			return;
		}
		var col = el.getAttribute( 'data-col' );
		var colCells = inputsByCol( col, grid );
		var idx = colCells.indexOf( el );
		var tr = el.closest( 'tr' );
		var rowCells = Array.prototype.slice.call( tr.querySelectorAll( '.cp-sheet-input' ) );
		var ri = rowCells.indexOf( el );

		if ( e.key === 'Enter' || e.key === 'ArrowDown' ) {
			e.preventDefault();
			focusCell( colCells[ idx + ( e.shiftKey && e.key === 'Enter' ? -1 : 1 ) ] );
		} else if ( e.key === 'ArrowUp' ) {
			e.preventDefault();
			focusCell( colCells[ idx - 1 ] );
		} else if ( e.key === 'ArrowRight' && el.selectionEnd === el.value.length ) {
			e.preventDefault();
			focusCell( rowCells[ ri + 1 ] );
		} else if ( e.key === 'ArrowLeft' && el.selectionStart === 0 ) {
			e.preventDefault();
			focusCell( rowCells[ ri - 1 ] );
		}
	} ); } );

	// Coller une colonne (ou un bloc) depuis Excel, Google Sheets ou le logiciel du laser.
	grids.forEach( function ( grid ) { grid.addEventListener( 'paste', function ( e ) {
		var el = e.target;
		if ( ! el.classList.contains( 'cp-sheet-input' ) || el.readOnly ) {
			return;
		}
		var text = ( e.clipboardData || window.clipboardData ).getData( 'text' );
		if ( ! /[\t\n]/.test( text.trim() ) ) {
			return;
		}
		e.preventDefault();
		var lines = text.replace( /\r/g, '' ).replace( /\n$/, '' ).split( '\n' );
		var col = el.getAttribute( 'data-col' );
		var trs = Array.prototype.slice.call( grid.querySelectorAll( 'tbody tr' ) );
		var t0 = trs.indexOf( el.closest( 'tr' ) );
		lines.forEach( function ( line, li ) {
			var tr = trs[ t0 + li ];
			var base = tr ? tr.querySelector( '.cp-sheet-input[data-col="' + col + '"]' ) : null;
			if ( ! base ) {
				return;
			}
			// Colonne de départ, puis cellules suivantes de la ligne pour les données sur plusieurs colonnes.
			var cells = Array.prototype.slice.call( tr.querySelectorAll( '.cp-sheet-input' ) );
			var start = cells.indexOf( base );
			line.split( '\t' ).forEach( function ( v, ci ) {
				var target = cells[ start + ci ];
				if ( target && ! target.readOnly ) {
					var clean = v.trim().replace( ',', '.' ).replace( /[^0-9.\-]/g, '' );
					target.value = clean;
					setValue( target.getAttribute( 'data-kind' ), target.getAttribute( 'data-id' ), clean );
				}
			} );
		} );
		updateComputed();
	} ); } );

	// Boutons 1ère / 2e mesure et gauche / droite.
	root.querySelectorAll( '.cp-seg' ).forEach( function ( seg ) {
		seg.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-value]' );
			if ( ! btn ) {
				return;
			}
			view[ seg.getAttribute( 'data-switch' ) ] = btn.getAttribute( 'data-value' );
			renderSwitches();
			renderSheet();
		} );
	} );

	function renderSwitches() {
		if ( ! bothSides() ) {
			view.side = 'G';
		}
		root.querySelectorAll( '.cp-seg' ).forEach( function ( seg ) {
			var key = seg.getAttribute( 'data-switch' );
			seg.hidden = key === 'side' && ! bothSides();
			seg.querySelectorAll( '[data-value]' ).forEach( function ( b ) {
				b.classList.toggle( 'is-active', b.getAttribute( 'data-value' ) === view[ key ] );
			} );
		} );
	}

	// Boutons − / + (élévateur et offset).
	root.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.cp-step-up, .cp-step-down' );
		if ( ! btn ) {
			return;
		}
		var field = btn.closest( '.cp-stepper' ).querySelector( 'input' );
		var step = e.shiftKey ? 5 : 1;
		var v = ( num( field.value ) || 0 ) + ( btn.classList.contains( 'cp-step-up' ) ? step : -step );
		v = Math.round( v * 10 ) / 10;
		field.value = v === 0 ? '' : String( v );
		updateComputed();
	} );
	[ offsetInput, riserInput, tolInput ].forEach( function ( el ) {
		if ( el ) {
			el.addEventListener( 'input', updateComputed );
			el.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' ) {
					e.preventDefault();
				}
			} );
		}
	} );

	if ( sidesSelect ) {
		sidesSelect.addEventListener( 'change', function () {
			renderSwitches();
			renderSheet();
		} );
	}
	if ( lockInput ) {
		lockInput.addEventListener( 'change', renderSheet );
	}

	$( '.cp-trim-copy' ).addEventListener( 'click', function () {
		ROW_KEYS.forEach( function ( row ) {
			rowLines( row ).forEach( function ( line ) {
				sideKeys().forEach( function ( side ) {
					var init = measure( 'initial', line.id, side );
					if ( init !== '' && measure( 'final', line.id, side ) === '' ) {
						state.final[ line.id ] = state.final[ line.id ] || {};
						state.final[ line.id ][ side ] = init;
					}
				} );
			} );
		} );
		var dateInput = $( '#cp-trim-final-date' );
		if ( dateInput && ! dateInput.value ) {
			dateInput.value = today();
		}
		view.set = 'final';
		renderSwitches();
		renderSheet();
	} );

	/**
	 * Charge la structure, les cotes usine, l'élévateur et l'offset d'un contrôle précédent (même modèle d'aile).
	 */
	window.cpTrimLoad = function ( trim ) {
		if ( ! trim ) {
			return;
		}
		if ( trim.sides && sidesSelect ) {
			sidesSelect.value = trim.sides;
		}
		if ( offsetInput && trim.offset !== undefined ) {
			offsetInput.value = trim.offset || '';
		}
		if ( riserInput && trim.riser_length !== undefined ) {
			riserInput.value = trim.riser_length || '';
		}
		ROW_KEYS.forEach( function ( row ) {
			state.structure[ row ] = ( ( trim.structure && trim.structure[ row ] ) || [] ).map( function ( g, i ) {
				return typeof g === 'object' ? { count: g.count, color: g.color, gap: g.gap || 0 } : { count: g, color: COLOR_KEYS[ i % COLOR_KEYS.length ], gap: 0 };
			} );
		} );
		state.factory = trim.factory || {};
		renderSwitches();
		structureChanged();
	};

	window.cpTrimHasFactory = function () {
		return Object.keys( state.factory ).some( function ( k ) {
			return state.factory[ k ] !== '';
		} );
	};

	// Dernière mesure saisie affichée par défaut.
	if ( Object.keys( state.final ).length ) {
		view.set = 'final';
	}
	syncJson();
	renderStructure();
	renderSwitches();
	showStep( root.getAttribute( 'data-step' ) || 'structure' );
	schedulePreview();
} )();
