<?php
/**
 * Désinstallation : supprime les réglages. Les fiches de contrôle sont conservées
 * (historique légal), sauf si la constante CP_DELETE_DATA est définie à true.
 *
 * @package ControleParapente
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cp_settings' );
wp_clear_scheduled_hook( 'cp_daily_reminders' );

if ( defined( 'CP_DELETE_DATA' ) && CP_DELETE_DATA ) {
	$ids = get_posts(
		array(
			'post_type'      => 'cp_controle',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'cp\\_counter\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
