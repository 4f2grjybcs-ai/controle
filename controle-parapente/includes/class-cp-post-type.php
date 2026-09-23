<?php
/**
 * Type de contenu « Contrôle ».
 *
 * @package ControleParapente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Post_Type {

	const POST_TYPE = 'cp_controle';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Contrôles parapente', 'controle-parapente' ),
					'singular_name'      => __( 'Contrôle', 'controle-parapente' ),
					'menu_name'          => __( 'Contrôles', 'controle-parapente' ),
					'add_new'            => __( 'Nouveau contrôle', 'controle-parapente' ),
					'add_new_item'       => __( 'Nouveau contrôle', 'controle-parapente' ),
					'edit_item'          => __( 'Fiche de contrôle', 'controle-parapente' ),
					'new_item'           => __( 'Nouveau contrôle', 'controle-parapente' ),
					'search_items'       => __( 'Rechercher (référence, pilote, aile, n° de série)', 'controle-parapente' ),
					'not_found'          => __( 'Aucun contrôle.', 'controle-parapente' ),
					'not_found_in_trash' => __( 'Aucun contrôle dans la corbeille.', 'controle-parapente' ),
					'all_items'          => __( 'Tous les contrôles', 'controle-parapente' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-clipboard',
				'menu_position'   => 26,
				'supports'        => false,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}
}
