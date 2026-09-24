/* Contrôle Parapente — page de réglages : onglets et choix du logo. */
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
} );
