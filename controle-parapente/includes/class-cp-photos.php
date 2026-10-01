<?php
/**
 * Photos prises pendant le contrôle : envoyées dans la médiathèque, rattachées à la fiche,
 * avec une légende et le choix de les afficher sur le rapport client.
 *
 * @package Controle_Parapente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Photos {

	const NONCE = 'cp_photo';

	public static function init() {
		add_action( 'wp_ajax_cp_photo_upload', array( __CLASS__, 'ajax_upload' ) );
		add_action( 'wp_ajax_cp_photo_delete', array( __CLASS__, 'ajax_delete' ) );
	}

	/**
	 * Photos valides d'une fiche : [ [ 'id', 'caption', 'report' ], … ].
	 */
	public static function sanitize( $raw ) {
		$photos = array();
		foreach ( is_array( $raw ) ? $raw : array() as $row ) {
			$id = is_array( $row ) && isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( ! $id || ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			$photos[] = array(
				'id'      => $id,
				'caption' => isset( $row['caption'] ) ? sanitize_text_field( $row['caption'] ) : '',
				'report'  => ! empty( $row['report'] ) ? '1' : '',
			);
		}
		return $photos;
	}

	/**
	 * Photos à montrer sur le rapport client.
	 */
	public static function for_report( array $d ) {
		return array_values(
			array_filter(
				isset( $d['photos'] ) && is_array( $d['photos'] ) ? $d['photos'] : array(),
				static function ( $p ) {
					return '1' === $p['report'] && wp_attachment_is_image( $p['id'] );
				}
			)
		);
	}

	/**
	 * Vignette d'une photo dans la fiche (aussi utilisée comme modèle côté JS).
	 */
	private static function item( $index, $photo ) {
		$thumb = $photo['id'] ? wp_get_attachment_image_url( $photo['id'], 'medium' ) : '';
		$full  = $photo['id'] ? wp_get_attachment_image_url( $photo['id'], 'full' ) : '';
		$base  = 'cp[photos][' . $index . ']';
		ob_start();
		?>
		<figure class="cp-photo" data-id="<?php echo esc_attr( $photo['id'] ); ?>">
			<a href="<?php echo esc_url( $full ); ?>" target="_blank" rel="noopener" class="cp-photo-img"><img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" /></a>
			<input type="hidden" name="<?php echo esc_attr( $base ); ?>[id]" value="<?php echo esc_attr( $photo['id'] ); ?>" class="cp-photo-id" />
			<input type="text" name="<?php echo esc_attr( $base ); ?>[caption]" value="<?php echo esc_attr( $photo['caption'] ); ?>" placeholder="<?php esc_attr_e( 'Légende (ex. accroc extrados, caisson 12)', 'controle-parapente' ); ?>" class="cp-photo-caption" />
			<span class="cp-photo-actions">
				<label><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[report]" value="1" <?php checked( $photo['report'], '1' ); ?> /> <?php esc_html_e( 'Sur le rapport', 'controle-parapente' ); ?></label>
				<button type="button" class="cp-photo-del" aria-label="<?php esc_attr_e( 'Supprimer la photo', 'controle-parapente' ); ?>">×</button>
			</span>
		</figure>
		<?php
		return ob_get_clean();
	}

	public static function render_box( $post ) {
		$d      = CP_Controle::get( $post->ID );
		$photos = isset( $d['photos'] ) && is_array( $d['photos'] ) ? $d['photos'] : array();
		?>
		<div class="cp-photos" data-post="<?php echo esc_attr( $post->ID ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-confirm="<?php esc_attr_e( 'Supprimer définitivement cette photo ?', 'controle-parapente' ); ?>"
			data-error="<?php esc_attr_e( 'Envoi impossible', 'controle-parapente' ); ?>">
			<input type="hidden" name="cp[photos_field]" value="1" />
			<p class="cp-photo-add">
				<label class="cp-btn cp-btn--primary button button-primary">📷 <?php esc_html_e( 'Prendre une photo', 'controle-parapente' ); ?><input type="file" accept="image/*" capture="environment" class="cp-photo-input" hidden /></label>
				<label class="cp-btn button"><?php esc_html_e( 'Ajouter depuis la galerie', 'controle-parapente' ); ?><input type="file" accept="image/*" multiple class="cp-photo-input" hidden /></label>
			</p>
			<p class="description"><?php esc_html_e( 'Les photos sont réduites puis enregistrées en ligne dans la médiathèque du site, rattachées à ce contrôle. Décochez « Sur le rapport » pour une photo réservée à l\'atelier.', 'controle-parapente' ); ?></p>
			<div class="cp-photo-grid">
				<?php
				foreach ( $photos as $i => $photo ) {
					echo self::item( $i, $photo ); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans item().
				}
				?>
			</div>
			<template class="cp-photo-template"><?php echo self::item( '__i__', array( 'id' => 0, 'caption' => '', 'report' => '1' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
		</div>
		<?php
	}

	private static function check( $post_id ) {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! $post_id || CP_Post_Type::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'controle-parapente' ) ), 403 );
		}
	}

	public static function ajax_upload() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- vérifié dans check().
		self::check( $post_id );
		if ( empty( $_FILES['photo'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Aucune image reçue.', 'controle-parapente' ) ), 400 );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$reference = (string) get_post_meta( $post_id, CP_Controle::META_REFERENCE, true );
		$id        = media_handle_upload(
			'photo',
			$post_id,
			array( 'post_title' => trim( $reference . ' — ' . __( 'photo', 'controle-parapente' ) . ' ' . wp_date( 'd/m/Y H:i' ) ) ),
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'png'          => 'image/png',
					'webp'         => 'image/webp',
				),
			)
		);
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ), 400 );
		}
		wp_send_json_success(
			array(
				'id'    => $id,
				'thumb' => wp_get_attachment_image_url( $id, 'medium' ),
				'full'  => wp_get_attachment_image_url( $id, 'full' ),
			)
		);
	}

	public static function ajax_delete() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- vérifié dans check().
		$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		self::check( $post_id );
		if ( ! $id || 'attachment' !== get_post_type( $id ) || (int) wp_get_post_parent_id( $id ) !== $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Photo introuvable.', 'controle-parapente' ) ), 404 );
		}
		wp_delete_attachment( $id, true );
		wp_send_json_success();
	}
}
