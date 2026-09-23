<?php
/**
 * Rappels automatiques avant l'échéance du prochain contrôle.
 *
 * @package ControleParapente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Cron {

	const HOOK = 'cp_daily_reminders';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		// Sécurité si l'événement a été perdu (ex. mise à jour sans réactivation).
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function run() {
		$days = (int) CP_Settings::get( 'reminder_days' );
		if ( $days <= 0 ) {
			return;
		}
		$today = current_time( 'Y-m-d' );
		$limit = gmdate( 'Y-m-d', strtotime( $today . ' +' . $days . ' days' ) );

		$ids = get_posts(
			array(
				'post_type'      => CP_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => CP_Controle::META_NEXT_DATE,
						'value'   => array( $today, $limit ),
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
					array(
						'key'     => CP_Controle::META_STATUS,
						'value'   => array( 'terminee', 'rendue' ),
						'compare' => 'IN',
					),
				),
			)
		);

		foreach ( $ids as $id ) {
			$next = get_post_meta( $id, CP_Controle::META_NEXT_DATE, true );
			// Un seul rappel par échéance.
			if ( get_post_meta( $id, CP_Controle::META_REMINDED, true ) === $next ) {
				continue;
			}
			if ( self::has_newer_check( $id ) ) {
				update_post_meta( $id, CP_Controle::META_REMINDED, $next );
				continue;
			}
			if ( CP_Emails::reminder( $id ) ) {
				update_post_meta( $id, CP_Controle::META_REMINDED, $next );
			}
		}
	}

	/**
	 * Vrai si la même aile (n° de série) a déjà un contrôle plus récent.
	 */
	private static function has_newer_check( $post_id ) {
		$serial = get_post_meta( $post_id, CP_Controle::META_SERIAL, true );
		if ( '' === $serial ) {
			return false;
		}
		$newer = get_posts(
			array(
				'post_type'      => CP_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post__not_in'   => array( $post_id ),
				'date_query'     => array( array( 'after' => get_post_field( 'post_date', $post_id ) ) ),
				'meta_query'     => array(
					array( 'key' => CP_Controle::META_SERIAL, 'value' => $serial ),
					array( 'key' => CP_Controle::META_STATUS, 'value' => 'annulee', 'compare' => '!=' ),
				),
			)
		);
		return ! empty( $newer );
	}
}
