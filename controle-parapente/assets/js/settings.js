/* Contrôle Parapente — page de réglages : onglets, logo, signature et tampon. */
jQuery( function ( $ ) {
	var $tabs = $( '.cp-settings-tabs .nav-tab' );
	var $panels = $( '.cp-settings-panel' );

	function show( name ) {
		if ( ! $panels.filter( '[data-panel="' + name + '"]' ).length ) {
			name = $tabs.first().data( 'tab' );
		}
		$tabs.removeClass( 'nav-tab-active' ).filter( '[data-tab="' + name + '"]' ).addClass( 'nav-tab-active' );
		$panels.hide().filter( '[data-panel="' + name + '"]' ).show();
		// Revenir sur le même onglet après l'enregistrement.
		$( 'input[name="_wp_http_referer"]' ).val( function ( i, v ) {
			return v.split( '#' )[ 0 ] + '#' + name;
		} );
	}
	$tabs.on( 'click', function ( e ) {
		e.preventDefault();
		var name = $( this ).data( 'tab' );
		history.replaceState( null, '', '#' + name );
		show( name );
	} );
	show( window.location.hash.replace( '#', '' ) );

	var frame;
	$( '#cp-logo-pick' ).on( 'click', function ( e ) {
		e.preventDefault();
		if ( ! frame ) {
			frame = wp.media( { title: 'Logo', library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				$( '#cp-logo_id' ).val( a.id );
				$( '#cp-logo-preview' ).attr( 'src', a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url ).show();
				$( '#cp-logo-remove' ).show();
			} );
		}
		frame.open();
	} );
	$( '#cp-logo-remove' ).on( 'click', function ( e ) {
		e.preventDefault();
		$( '#cp-logo_id' ).val( '' );
		$( '#cp-logo-preview' ).hide();
		$( this ).hide();
	} );
	/* ---------- Signature et tampon (images enregistrées en PNG dans les réglages) ---------- */

	// Recadre le dessin sur la zone utile et l'exporte en PNG (null si vide).
	function exportTrimmed( canvas ) {
		var ctx = canvas.getContext( '2d' );
		var data = ctx.getImageData( 0, 0, canvas.width, canvas.height ).data;
		var minX = canvas.width, minY = canvas.height, maxX = -1, maxY = -1;
		for ( var y = 0; y < canvas.height; y++ ) {
			for ( var x = 0; x < canvas.width; x++ ) {
				if ( data[ ( y * canvas.width + x ) * 4 + 3 ] > 10 ) {
					if ( x < minX ) { minX = x; }
					if ( x > maxX ) { maxX = x; }
					if ( y < minY ) { minY = y; }
					if ( y > maxY ) { maxY = y; }
				}
			}
		}
		if ( maxX < 0 ) {
			return null;
		}
		var pad = 6;
		minX = Math.max( 0, minX - pad );
		minY = Math.max( 0, minY - pad );
		maxX = Math.min( canvas.width - 1, maxX + pad );
		maxY = Math.min( canvas.height - 1, maxY + pad );
		var out = document.createElement( 'canvas' );
		out.width = maxX - minX + 1;
		out.height = maxY - minY + 1;
		out.getContext( '2d' ).drawImage( canvas, minX, minY, out.width, out.height, 0, 0, out.width, out.height );
		return out.toDataURL( 'image/png' );
	}

	// Image importée : réduite, fond clair rendu transparent, puis recadrée.
	function importImage( file, maxSide, done ) {
		var reader = new FileReader();
		reader.onload = function () {
			var img = new Image();
			img.onload = function () {
				var scale = Math.min( 1, maxSide / Math.max( img.width, img.height ) );
				var c = document.createElement( 'canvas' );
				c.width = Math.max( 1, Math.round( img.width * scale ) );
				c.height = Math.max( 1, Math.round( img.height * scale ) );
				var ctx = c.getContext( '2d' );
				ctx.drawImage( img, 0, 0, c.width, c.height );
				var px = ctx.getImageData( 0, 0, c.width, c.height );
				for ( var i = 0; i < px.data.length; i += 4 ) {
					var light = ( px.data[ i ] + px.data[ i + 1 ] + px.data[ i + 2 ] ) / 3;
					if ( light > 235 ) {
						px.data[ i + 3 ] = 0;
					} else if ( light > 200 ) {
						px.data[ i + 3 ] = Math.round( px.data[ i + 3 ] * ( 235 - light ) / 35 );
					}
				}
				ctx.putImageData( px, 0, 0 );
				done( exportTrimmed( c ) );
			};
			img.src = reader.result;
		};
		reader.readAsDataURL( file );
	}

	document.querySelectorAll( '.cp-imgdata' ).forEach( function ( box ) {
		var input = box.querySelector( '.cp-imgdata-value' );
		var preview = box.querySelector( '.cp-imgdata-preview' );
		var canvas = box.querySelector( '.cp-pad' );
		var ctx = canvas ? canvas.getContext( '2d' ) : null;

		function showValue( value ) {
			input.value = value || '';
			if ( canvas ) {
				ctx.clearRect( 0, 0, canvas.width, canvas.height );
				if ( value ) {
					var img = new Image();
					img.onload = function () {
						// Image centrée dans le cadre, sans agrandissement.
						var scale = Math.min( 1, ( canvas.width - 20 ) / img.width, ( canvas.height - 20 ) / img.height );
						var w = img.width * scale, h = img.height * scale;
						ctx.drawImage( img, ( canvas.width - w ) / 2, ( canvas.height - h ) / 2, w, h );
					};
					img.src = value;
				}
			} else {
				preview.src = value || '';
				preview.hidden = ! value;
			}
		}
		showValue( input.value );

		if ( canvas ) {
			var drawing = false;
			var last = null;
			var point = function ( e ) {
				var r = canvas.getBoundingClientRect();
				return { x: ( e.clientX - r.left ) * canvas.width / r.width, y: ( e.clientY - r.top ) * canvas.height / r.height };
			};
			canvas.addEventListener( 'pointerdown', function ( e ) {
				e.preventDefault();
				drawing = true;
				last = point( e );
				canvas.setPointerCapture( e.pointerId );
				ctx.beginPath();
				ctx.arc( last.x, last.y, 1.8, 0, Math.PI * 2 );
				ctx.fillStyle = '#1b2a4a';
				ctx.fill();
			} );
			canvas.addEventListener( 'pointermove', function ( e ) {
				if ( ! drawing ) {
					return;
				}
				var p = point( e );
				ctx.strokeStyle = '#1b2a4a';
				ctx.lineWidth = e.pressure && e.pointerType === 'pen' ? 2 + e.pressure * 4 : 3.6;
				ctx.lineCap = 'round';
				ctx.lineJoin = 'round';
				ctx.beginPath();
				ctx.moveTo( last.x, last.y );
				ctx.lineTo( p.x, p.y );
				ctx.stroke();
				last = p;
			} );
			var end = function () {
				if ( drawing ) {
					drawing = false;
					input.value = exportTrimmed( canvas ) || '';
				}
			};
			canvas.addEventListener( 'pointerup', end );
			canvas.addEventListener( 'pointercancel', end );
		}

		box.querySelector( '.cp-imgdata-clear' ).addEventListener( 'click', function () {
			showValue( '' );
		} );
		box.querySelector( '.cp-imgdata-file' ).addEventListener( 'change', function ( e ) {
			var file = e.target.files && e.target.files[ 0 ];
			if ( file ) {
				importImage( file, 800, showValue );
			}
			e.target.value = '';
		} );
	} );
} );
