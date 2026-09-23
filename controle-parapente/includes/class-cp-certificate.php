<?php
/**
 * Fiche / certificat de contrôle imprimable.
 *
 * @package ControleParapente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Certificate {

	public static function init() {
		add_action( 'admin_post_cp_certificate', array( __CLASS__, 'admin_view' ) );
		add_action( 'template_redirect', array( __CLASS__, 'public_view' ) );
	}

	public static function admin_view() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'cp_certificate_' . $post_id );

		if ( ! $post_id || CP_Post_Type::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'controle-parapente' ), 403 );
		}
		// Avec &client=1 : aperçu exact de ce que reçoit le client (sans notes internes).
		self::render( $post_id, empty( $_GET['client'] ) );
	}

	public static function public_view() {
		// phpcs:disable WordPress.Security.NonceVerification -- accès par clé secrète.
		if ( empty( $_GET['cp_certificat'] ) || empty( $_GET['cle'] ) ) {
			return;
		}
		$reference = sanitize_text_field( wp_unslash( $_GET['cp_certificat'] ) );
		$key       = sanitize_text_field( wp_unslash( $_GET['cle'] ) );
		// phpcs:enable

		$ids = get_posts(
			array(
				'post_type'      => CP_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => CP_Controle::META_REFERENCE, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $reference, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$post_id = $ids ? (int) $ids[0] : 0;
		$token   = $post_id ? (string) get_post_meta( $post_id, CP_Controle::META_TOKEN, true ) : '';

		if ( ! $post_id || '' === $token || ! hash_equals( $token, $key ) || ! CP_Controle::has_certificate( get_post_meta( $post_id, CP_Controle::META_STATUS, true ) ) ) {
			wp_die( esc_html__( 'Fiche de contrôle introuvable ou non disponible.', 'controle-parapente' ), '', array( 'response' => 404 ) );
		}
		self::render( $post_id, false );
	}

	private static function render( $post_id, $is_admin ) {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: text/html; charset=UTF-8' );

		$d        = CP_Controle::get( $post_id );
		$settings = CP_Settings::get();

		include CP_DIR . 'templates/certificate.php';
		exit;
	}
}
