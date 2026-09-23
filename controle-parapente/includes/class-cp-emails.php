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
		$body  = sprintf( __( 'Bonjour %s,', 'controle-parapente' ), $d['pilot_name'] ) . "\n\n";
		$body .= __( 'Nous avons bien reçu votre demande de contrôle. Conservez votre référence : elle vous permet de suivre l\'avancement sur notre site.', 'controle-parapente' ) . "\n\n";
		$body .= self::summary( $d ) . "\n";
		if ( 'atelier' === $d['drop_off'] && CP_Settings::get( 'workshop_address' ) ) {
			$body .= "\n" . __( 'Adresse de dépôt :', 'controle-parapente' ) . "\n" . CP_Settings::get( 'workshop_address' ) . "\n";
		} elseif ( 'poste' === $d['drop_off'] && CP_Settings::get( 'workshop_address' ) ) {
			$body .= "\n" . __( 'Adresse d\'envoi (indiquez la référence dans le colis) :', 'controle-parapente' ) . "\n" . CP_Settings::get( 'workshop_address' ) . "\n";
		}

		self::send(
			$d['email'],
			sprintf( __( 'Votre demande de contrôle %s', 'controle-parapente' ), $d['reference'] ),
			$body
		);
	}

	public static function status_changed( $post_id ) {
		$d        = CP_Controle::get( $post_id );
		$statuses = CP_Controle::statuses();
		$verdicts = CP_Controle::verdicts();

		$body  = sprintf( __( 'Bonjour %s,', 'controle-parapente' ), $d['pilot_name'] ) . "\n\n";
		$body .= sprintf( __( 'Le statut de votre contrôle %1$s est maintenant : %2$s.', 'controle-parapente' ), $d['reference'], $statuses[ $d['status'] ] ?? $d['status'] ) . "\n\n";
		$body .= self::summary( $d ) . "\n";

		if ( CP_Controle::has_certificate( $d['status'] ) ) {
			if ( $d['verdict'] ) {
				$body .= sprintf( __( 'Résultat : %s', 'controle-parapente' ), $verdicts[ $d['verdict'] ] ) . "\n";
			}
			if ( $d['next_date'] ) {
				$body .= sprintf( __( 'Prochain contrôle conseillé : %s', 'controle-parapente' ), CP_Controle::format_date( $d['next_date'] ) ) . "\n";
			}
			if ( $d['comments'] ) {
				$body .= "\n" . __( 'Observations :', 'controle-parapente' ) . "\n" . $d['comments'] . "\n";
			}
			$body .= "\n" . __( 'Votre fiche de contrôle :', 'controle-parapente' ) . "\n" . CP_Controle::public_certificate_url( $post_id ) . "\n";
		}

		self::send(
			$d['email'],
			sprintf( __( 'Contrôle %1$s : %2$s', 'controle-parapente' ), $d['reference'], $statuses[ $d['status'] ] ?? '' ),
			$body
		);
	}

	public static function reminder( $post_id ) {
		$d = CP_Controle::get( $post_id );

		$body  = sprintf( __( 'Bonjour %s,', 'controle-parapente' ), $d['pilot_name'] ) . "\n\n";
		$body .= sprintf(
			__( 'Le prochain contrôle de votre %1$s est prévu pour le %2$s.', 'controle-parapente' ),
			CP_Controle::equipment_label( $d ),
			CP_Controle::format_date( $d['next_date'] )
		) . "\n";
		$body .= __( 'Un contrôle régulier est indispensable pour voler en sécurité. N\'hésitez pas à nous contacter ou à faire une demande sur notre site.', 'controle-parapente' ) . "\n\n";
		$body .= self::summary( $d );

		return self::send(
			$d['email'],
			sprintf( __( 'Rappel : contrôle de votre %s', 'controle-parapente' ), CP_Controle::equipment_label( $d ) ),
			$body
		);
	}
}
