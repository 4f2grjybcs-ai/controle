<?php
/**
 * Notifications e-mail.
 *
 * @package ControleParapente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Emails {

	private static function send( $to, $subject, $body ) {
		if ( ! is_email( $to ) ) {
			return false;
		}
		$name    = CP_Settings::get( 'workshop_name' );
		$from    = CP_Settings::get( 'notify_email' );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( is_email( $from ) ) {
			$headers[] = sprintf( 'Reply-To: %s <%s>', $name, $from );
		}
		$body .= "\n\n--\n" . $name;
		if ( CP_Settings::get( 'workshop_phone' ) ) {
			$body .= "\n" . CP_Settings::get( 'workshop_phone' );
		}

		/**
		 * Permet de modifier un e-mail avant envoi.
		 *
		 * @param array $mail to, subject, body, headers.
		 */
		$mail = apply_filters( 'cp_email', compact( 'to', 'subject', 'body', 'headers' ) );

		return wp_mail( $mail['to'], $mail['subject'], $mail['body'], $mail['headers'] );
	}

	private static function summary( array $d ) {
		$types = CP_Controle::equipment_types();
		$lines = array(
			sprintf( __( 'Référence : %s', 'controle-parapente' ), $d['reference'] ),
			sprintf( __( 'Pilote : %s', 'controle-parapente' ), $d['pilot_name'] ),
			sprintf( __( 'Équipement : %s (%s)', 'controle-parapente' ), CP_Controle::equipment_label( $d ), $types[ $d['equipment_type'] ] ?? '' ),
		);
		if ( $d['serial'] ) {
			$lines[] = sprintf( __( 'N° de série : %s', 'controle-parapente' ), $d['serial'] );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Variables disponibles dans les modèles d'e-mails (Réglages → E-mails).
	 */
	public static function vars( $post_id, array $d ) {
		$statuses = CP_Controle::statuses();
		$verdicts = CP_Controle::verdicts();
		return array(
			'client'            => $d['pilot_name'],
			'reference'         => $d['reference'],
			'aile'              => CP_Controle::equipment_label( $d ),
			'serie'             => $d['serial'],
			'statut'            => $statuses[ $d['status'] ] ?? '',
			'resultat'          => $d['verdict'] ? $verdicts[ $d['verdict'] ] : '—',
			'prochain_controle' => CP_Controle::format_date( $d['next_date'] ),
			'lien'              => CP_Controle::has_certificate( $d['status'] ) ? CP_Controle::public_certificate_url( $post_id ) : '',
			'atelier'           => CP_Settings::get( 'workshop_name' ),
			'telephone'         => CP_Settings::get( 'workshop_phone' ),
		);
	}

	/**
	 * Envoie au client l'e-mail correspondant à un modèle (mail_xxx_subject / mail_xxx_body).
	 */
	private static function send_template( $post_id, $template, $extra = '' ) {
		$d    = CP_Controle::get( $post_id );
		$vars = self::vars( $post_id, $d );
		$body = CP_Settings::tpl( 'mail_' . $template . '_body', $vars );
		if ( $extra ) {
			$body .= "\n\n" . $extra;
		}
		return self::send( $d['email'], CP_Settings::tpl( 'mail_' . $template . '_subject', $vars ), $body );
	}

	public static function new_request( $post_id ) {
		$d        = CP_Controle::get( $post_id );
		$services = array_intersect_key( CP_Controle::services(), array_flip( (array) $d['services'] ) );
		$modes    = CP_Controle::drop_off_modes();

		// Atelier.
		$body  = __( 'Nouvelle demande de contrôle reçue depuis le site.', 'controle-parapente' ) . "\n\n";
		$body .= self::summary( $d ) . "\n";
		$body .= sprintf( __( 'E-mail : %s', 'controle-parapente' ), $d['email'] ) . "\n";
		$body .= sprintf( __( 'Téléphone : %s', 'controle-parapente' ), $d['phone'] ) . "\n";
		$body .= sprintf( __( 'Prestations : %s', 'controle-parapente' ), implode( ', ', $services ) ) . "\n";
		$body .= sprintf( __( 'Dépôt : %s', 'controle-parapente' ), $modes[ $d['drop_off'] ] ?? '' ) . "\n";
		if ( $d['client_notes'] ) {
			$body .= "\n" . __( 'Remarques :', 'controle-parapente' ) . "\n" . $d['client_notes'] . "\n";
		}
		$body .= "\n" . admin_url( 'post.php?post=' . $post_id . '&action=edit' );

		self::send(
			CP_Settings::get( 'notify_email' ),
			sprintf( __( '[Contrôle] Nouvelle demande %1$s — %2$s', 'controle-parapente' ), $d['reference'], CP_Controle::equipment_label( $d ) ),
			$body
		);

		// Client.
		$extra = self::summary( $d );
		if ( CP_Settings::get( 'workshop_address' ) ) {
			$extra .= "

" . ( 'poste' === $d['drop_off'] ? __( 'Adresse d\'envoi (indiquez la référence dans le colis) :', 'controle-parapente' ) : __( 'Adresse de dépôt :', 'controle-parapente' ) ) . "
" . CP_Settings::get( 'workshop_address' );
		}
		self::send_template( $post_id, 'request', $extra );
	}

	public static function status_changed( $post_id ) {
		$d     = CP_Controle::get( $post_id );
		$extra = self::summary( $d );
		if ( CP_Controle::has_certificate( $d['status'] ) ) {
			$vars   = self::vars( $post_id, $d );
			$extra .= "

" . sprintf( __( 'Résultat : %s', 'controle-parapente' ), $vars['resultat'] );
			if ( $d['next_date'] ) {
				$extra .= "
" . sprintf( __( 'Prochain contrôle conseillé : %s', 'controle-parapente' ), $vars['prochain_controle'] );
			}
			$extra .= "

" . __( 'Votre rapport de contrôle :', 'controle-parapente' ) . "
" . $vars['lien'];
		}
		return self::send_template( $post_id, 'status', $extra );
	}

	/**
	 * Envoie au client le lien vers son rapport de contrôle.
	 *
	 * @return bool
	 */
	public static function report( $post_id ) {
		return self::send_template( $post_id, 'report' );
	}

	public static function reminder( $post_id ) {
		return self::send_template( $post_id, 'reminder', self::summary( CP_Controle::get( $post_id ) ) );
	}
}
