/* Contrôle Parapente — photos du contrôle : prise de vue, réduction, envoi dans la médiathèque. */
( function () {
	'use strict';

	var MAX_SIDE = 1600;
	var QUALITY = 0.85;

	// Réduit la photo (orientation de l'appareil respectée par le navigateur) en JPEG.
	function shrink( file ) {
		return new Promise( function ( resolve ) {
			var url = URL.createObjectURL( file );
			var img = new Image();
			img.onload = function () {
				var scale = Math.min( 1, MAX_SIDE / Math.max( img.naturalWidth, img.naturalHeight ) );
				var c = document.createElement( 'canvas' );
				c.width = Math.round( img.naturalWidth * scale );
				c.height = Math.round( img.naturalHeight * scale );
				c.getContext( '2d' ).drawImage( img, 0, 0, c.width, c.height );
				URL.revokeObjectURL( url );
				c.toBlob( function ( blob ) {
					resolve( blob || file );
				}, 'image/jpeg', QUALITY );
			};
			img.onerror = function () {
				URL.revokeObjectURL( url );
				resolve( file ); // format non lisible par le navigateur : envoyé tel quel
			};
			img.src = url;
		} );
	}

	function changed( box ) {
		// Prévient l'enregistrement automatique de l'espace atelier.
		box.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	document.querySelectorAll( '.cp-photos' ).forEach( function ( box ) {
		var grid = box.querySelector( '.cp-photo-grid' );
		var template = box.querySelector( '.cp-photo-template' );
		var counter = Date.now();

		function addItem() {
			var html = template.innerHTML.replace( /__i__/g, String( counter++ ) );
			var wrap = document.createElement( 'div' );
			wrap.innerHTML = html.trim();
			var fig = wrap.firstElementChild;
			fig.classList.add( 'is-uploading' );
			grid.appendChild( fig );
			return fig;
		}

		function upload( file ) {
			var fig = addItem();
			var preview = URL.createObjectURL( file );
			fig.querySelector( 'img' ).src = preview;
			return shrink( file ).then( function ( blob ) {
				var body = new FormData();
				body.append( 'action', 'cp_photo_upload' );
				body.append( 'nonce', box.getAttribute( 'data-nonce' ) );
				body.append( 'post_id', box.getAttribute( 'data-post' ) );
				body.append( 'photo', blob, ( file.name || 'photo' ).replace( /\.[^.]+$/, '' ) + '.jpg' );
				return fetch( box.getAttribute( 'data-ajax' ), { method: 'POST', body: body, credentials: 'same-origin' } );
			} ).then( function ( r ) {
				return r.json();
			} ).then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( json && json.data && json.data.message ? json.data.message : '' );
				}
				fig.setAttribute( 'data-id', json.data.id );
				fig.querySelector( '.cp-photo-id' ).value = json.data.id;
				fig.querySelector( 'img' ).src = json.data.thumb;
				fig.querySelector( 'a' ).href = json.data.full;
				fig.classList.remove( 'is-uploading' );
				URL.revokeObjectURL( preview );
				changed( box );
			} ).catch( function ( err ) {
				fig.classList.remove( 'is-uploading' );
				fig.classList.add( 'is-error' );
				fig.querySelector( '.cp-photo-caption' ).value = '';
				fig.querySelector( '.cp-photo-caption' ).placeholder = box.getAttribute( 'data-error' ) + ( err && err.message ? ' : ' + err.message : '' );
				fig.querySelector( '.cp-photo-id' ).value = '';
			} );
		}

		box.querySelectorAll( '.cp-photo-input' ).forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				var files = Array.prototype.slice.call( input.files || [] );
				input.value = '';
				// Une photo après l'autre pour ne pas saturer la connexion mobile.
				files.reduce( function ( chain, file ) {
					return chain.then( function () {
						return upload( file );
					} );
				}, Promise.resolve() );
			} );
		} );

		grid.addEventListener( 'click', function ( e ) {
			var del = e.target.closest( '.cp-photo-del' );
			if ( ! del ) {
				return;
			}
			var fig = del.closest( '.cp-photo' );
			var id = fig.querySelector( '.cp-photo-id' ).value;
			if ( ! id ) {
				fig.remove();
				return;
			}
			if ( ! window.confirm( box.getAttribute( 'data-confirm' ) ) ) {
				return;
			}
			var body = new FormData();
			body.append( 'action', 'cp_photo_delete' );
			body.append( 'nonce', box.getAttribute( 'data-nonce' ) );
			body.append( 'post_id', box.getAttribute( 'data-post' ) );
			body.append( 'id', id );
			fig.classList.add( 'is-uploading' );
			fetch( box.getAttribute( 'data-ajax' ), { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function () {
					fig.remove();
					changed( box );
				} );
		} );
	} );
} )();
