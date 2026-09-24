<?php
/**
 * Modèle d'un contrôle : champs, statuts, verdicts, lecture/écriture des données.
 *
 * @package ControleParapente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Controle {

	const META_DATA      = '_cp_data';
	const META_REFERENCE = '_cp_reference';
	const META_STATUS    = '_cp_status';
	const META_VERDICT   = '_cp_verdict';
	const META_EMAIL     = '_cp_email';
	const META_SERIAL    = '_cp_serial';
	const META_NEXT_DATE = '_cp_next_date';
	const META_TOKEN     = '_cp_token';
	const META_REMINDED  = '_cp_reminder_sent';

	public static function statuses() {
		return array(
			'demande'  => __( 'Demande reçue', 'controle-parapente' ),
			'recue'    => __( 'Aile réceptionnée', 'controle-parapente' ),
			'en_cours' => __( 'Contrôle en cours', 'controle-parapente' ),
			'attente'  => __( 'En attente (pièces / accord client)', 'controle-parapente' ),
			'terminee' => __( 'Contrôle terminé', 'controle-parapente' ),
			'rendue'   => __( 'Aile rendue au client', 'controle-parapente' ),
			'annulee'  => __( 'Annulée', 'controle-parapente' ),
		);
	}

	public static function verdicts() {
		return array(
			''                  => __( '— Non défini —', 'controle-parapente' ),
			'navigable'         => __( 'Navigable', 'controle-parapente' ),
			'navigable_reserve' => __( 'Navigable avec réserves', 'controle-parapente' ),
			'non_navigable'     => __( 'Non navigable', 'controle-parapente' ),
		);
	}

	public static function equipment_types() {
		return array(
			'parapente' => __( 'Parapente solo', 'controle-parapente' ),
			'biplace'   => __( 'Parapente biplace', 'controle-parapente' ),
			'speed'     => __( 'Speed-riding / mini-voile', 'controle-parapente' ),
			'secours'   => __( 'Parachute de secours', 'controle-parapente' ),
			'sellette'  => __( 'Sellette', 'controle-parapente' ),
		);
	}

	public static function certifications() {
		return array(
			''       => '—',
			'EN-A'   => 'EN / LTF A',
			'EN-B'   => 'EN / LTF B',
			'EN-C'   => 'EN / LTF C',
			'EN-D'   => 'EN / LTF D',
			'CCC'    => 'CCC',
			'EN-926' => 'EN 12491 (secours)',
			'autre'  => __( 'Autre / non homologuée', 'controle-parapente' ),
		);
	}

	public static function services() {
		return CP_Settings::keyed_list( 'services' );
	}

	public static function drop_off_modes() {
		return array(
			'atelier' => __( 'Dépôt à l\'atelier', 'controle-parapente' ),
			'poste'   => __( 'Envoi postal / transporteur', 'controle-parapente' ),
		);
	}

	/**
	 * Points de l'inspection visuelle (modifiables dans les réglages).
	 */
	public static function visual_items() {
		return CP_Settings::keyed_list( 'visual_items' );
	}

	/**
	 * Tests d'une inspection, regroupés en trois inspections (visuelle, mécanique, géométrique).
	 */
	public static function tests() {
		return array(
			'V' => __( 'Inspection visuelle', 'controle-parapente' ),
			'P' => __( 'Porosité du tissu', 'controle-parapente' ),
			'T' => __( 'Résistance à la déchirure', 'controle-parapente' ),
			'L' => __( 'Résistance des suspentes', 'controle-parapente' ),
			'G' => __( 'Calage (géométrie)', 'controle-parapente' ),
		);
	}

	/**
	 * Types d'inspection : [ 'cle' => [ 'label' => …, 'tests' => [ 'V', 'P', … ], 'state' => bool ] ].
	 */
	public static function inspection_types() {
		$types = array();
		foreach ( CP_Settings::lines( 'inspection_types' ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$slug  = sanitize_title( $parts[0] );
			if ( '' === $slug ) {
				continue;
			}
			$flags          = isset( $parts[1] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', $parts[1] ) ) : 'VPTLG';
			$types[ $slug ] = array(
				'label' => $parts[0],
				'tests' => array_values( array_intersect( array_keys( self::tests() ), str_split( $flags ) ) ),
				'state' => false !== strpos( $flags, 'E' ),
			);
		}
		return $types;
	}

	/**
	 * Tests réalisés pour ce contrôle.
	 */
	public static function tests_done( array $d ) {
		if ( is_array( $d['tests_done'] ) && $d['tests_done'] ) {
			return array_keys( array_filter( $d['tests_done'] ) );
		}
		$types = self::inspection_types();
		return isset( $types[ $d['inspection_type'] ] ) ? $types[ $d['inspection_type'] ]['tests'] : array_keys( self::tests() );
	}

	/**
	 * Tests sur lesquels repose l'état général : [ 'labels' => [...], 'complete' => bool ].
	 * L'état général peut être donné quel que soit le nombre de tests réalisés ;
	 * le rapport indique simplement sur quoi il s'appuie.
	 */
	public static function state_basis( array $d ) {
		$tests = self::tests();
		$done  = array_values( array_intersect( array_keys( $tests ), self::tests_done( $d ) ) );
		return array(
			'labels'   => array_map(
				static function ( $t ) use ( $tests ) {
					return $tests[ $t ];
				},
				$done
			),
			'complete' => count( $done ) === count( $tests ),
		);
	}

	/**
	 * Position choisie sur le curseur d'état, ou null si non renseignée / hors de la liste actuelle.
	 */
	public static function global_state( array $d ) {
		if ( '' === (string) $d['global_state'] ) {
			return null;
		}
		$i = (int) $d['global_state'];
		return isset( self::state_labels()[ $i ] ) ? $i : null;
	}

	public static function state_labels() {
		return CP_Settings::lines( 'state_labels' );
	}

	/**
	 * Seuils applicables à ce contrôle (valeurs saisies sur la fiche, sinon réglages).
	 */
	public static function thresholds( array $d ) {
		$pick = static function ( $value, $key ) {
			return '' !== (string) $value ? (float) $value : (float) CP_Settings::get( $key );
		};
		$sources = CP_Settings::threshold_sources();
		$source  = isset( $sources[ $d['threshold_source'] ] ) ? $d['threshold_source'] : CP_Settings::get( 'threshold_source' );
		return array(
			'source'          => $source,
			'source_label'    => isset( $sources[ $source ] ) ? $sources[ $source ] : '',
			'porosity_unit'   => CP_Settings::get( 'porosity_unit' ),
			'porosity_factor' => (float) CP_Settings::get( 'porosity_factor' ),
			'porosity_alert'  => $pick( $d['por_alert'], 'porosity_alert' ),
			'porosity_reform' => $pick( $d['por_reform'], 'porosity_reform' ),
			'tear_reform'     => $pick( $d['tear_reform'], 'tear_reform' ),
			'tear_good'       => (float) CP_Settings::get( 'tear_good' ),
		);
	}

	/**
	 * Unité de saisie de la porosité (s ou l/m²/min). Le rapport est toujours en l/m²/min.
	 */
	public static function porosity_unit_label( $unit = null ) {
		$unit = null === $unit ? CP_Settings::get( 'porosity_unit' ) : $unit;
		return 's' === $unit ? 's' : 'l/m²/min';
	}

	/**
	 * Convertit une mesure saisie en l/m²/min (temps en secondes : constante ÷ secondes).
	 *
	 * @return float|null
	 */
	public static function porosity_lm2min( $value, array $t ) {
		if ( '' === $value || null === $value || ! is_numeric( $value ) ) {
			return null;
		}
		$value = (float) $value;
		if ( 's' !== $t['porosity_unit'] ) {
			return $value;
		}
		return $value > 0 ? $t['porosity_factor'] / $value : null;
	}

	public static function visual_states() {
		return array(
			''          => '—',
			'ok'        => __( 'Bon état', 'controle-parapente' ),
			'surveille' => __( 'À surveiller', 'controle-parapente' ),
			'repare'    => __( 'Réparé / remplacé', 'controle-parapente' ),
			'nok'       => __( 'Non conforme', 'controle-parapente' ),
			'na'        => __( 'Non applicable', 'controle-parapente' ),
		);
	}

	private static function rows_from( $key, $field, array $empty ) {
		$rows = array();
		foreach ( CP_Settings::lines( $key ) as $label ) {
			$rows[] = array_merge( array( $field => $label ), $empty );
		}
		return $rows;
	}

	public static function default_porosity_rows() {
		return self::rows_from( 'porosity_points', 'zone', array( 'value' => '' ) );
	}

	public static function default_tear_rows() {
		return self::rows_from( 'tear_points', 'zone', array( 'value' => '' ) );
	}

	public static function default_line_rows() {
		$rows = array();
		foreach ( CP_Settings::lines( 'line_points' ) as $label ) {
			// Groupe et étage devinés d'après le libellé (« A — étage bas », « C — étage haut »…).
			$l      = strtolower( remove_accents( $label ) );
			$rows[] = array(
				'line'     => $label,
				'type'     => '',
				'new'      => '',
				'group'    => preg_match( '/^\s*(a|b)\b/', $l ) ? 'ab' : ( preg_match( '/^\s*(c|d|e)\b/', $l ) ? 'cde' : 'manuel' ),
				'level'    => false !== strpos( $l, 'haut' ) ? 'haut' : ( false !== strpos( $l, 'median' ) ? 'median' : 'bas' ),
				'count'    => '',
				'measured' => '',
				'minimum'  => '',
			);
		}
		return $rows;
	}

	public static function line_groups() {
		return array(
			'ab'     => __( 'A / B', 'controle-parapente' ),
			'cde'    => __( 'C / D / E', 'controle-parapente' ),
			'manuel' => __( 'Manuel', 'controle-parapente' ),
		);
	}

	public static function line_levels() {
		return array(
			'bas'    => __( 'Bas', 'controle-parapente' ),
			'median' => __( 'Médian', 'controle-parapente' ),
			'haut'   => __( 'Haut', 'controle-parapente' ),
		);
	}

	/**
	 * PTV max (kg) : valeur saisie, sinon plus grand nombre de la plage de poids (« 75-95 » → 95).
	 */
	public static function ptv_max( array $d ) {
		if ( is_numeric( $d['ptv_max'] ) && (float) $d['ptv_max'] > 0 ) {
			return (float) $d['ptv_max'];
		}
		preg_match_all( '/\d+(?:[.,]\d+)?/', (string) $d['weight_range'], $m );
		$values = array_map(
			static function ( $v ) {
				return (float) str_replace( ',', '.', $v );
			},
			$m[0]
		);
		return $values ? max( $values ) : 0.0;
	}

	/**
	 * Résistance minimale d'une suspente (daN), calcul type PMA :
	 * A/B : PTV × facteur A/B ÷ n ; C/D/E : PTV × facteur C/D/E ÷ n ; suspentes hautes : au moins le minimum réglé.
	 *
	 * @return float|null Null si le calcul n'est pas possible (groupe manuel, n ou PTV manquant).
	 */
	public static function line_minimum( array $row, $ptv ) {
		$group = isset( $row['group'] ) ? $row['group'] : 'manuel';
		$n     = isset( $row['count'] ) ? (float) $row['count'] : 0;
		if ( 'manuel' === $group || $n <= 0 || $ptv <= 0 ) {
			return null;
		}
		$factor = (float) CP_Settings::get( 'ab' === $group ? 'line_factor_ab' : 'line_factor_cde' );
		$kg     = $ptv * $factor / $n;
		if ( isset( $row['level'] ) && 'haut' === $row['level'] ) {
			$kg = max( $kg, (float) CP_Settings::get( 'line_upper_min' ) );
		}
		return round( $kg * 0.980665, 1 );
	}

	/**
	 * Structure vide d'un contrôle.
	 */
	public static function defaults() {
		return array(
			// Pilote.
			'pilot_name'        => '',
			'email'             => '',
			'phone'             => '',
			'address'           => '',
			// Équipement.
			'equipment_type'    => 'parapente',
			'brand'             => '',
			'model'             => '',
			'size'              => '',
			'serial'            => '',
			'year'              => '',
			'certification'     => '',
			'weight_range'      => '',
			'color'             => '',
			'flight_hours'      => '',
			'last_check'        => '',
			// Demande.
			'services'          => array_slice( array_keys( self::services() ), 0, 1 ),
			'drop_off'          => 'atelier',
			'client_notes'      => '',
			// Contrôle.
			'check_date'        => '',
			'technician'        => '',
			'inspection_type'   => (string) key( self::inspection_types() ),
			'tests_done'        => array(),
			'not_done_notes'    => array(),
			'threshold_source'  => '',
			'por_alert'         => '',
			'por_reform'        => '',
			'tear_reform'       => '',
			'porosity'          => self::default_porosity_rows(),
			'tear'              => self::default_tear_rows(),
			'fabric_strength'   => '',
			'fabric_result'     => '',
			'lines'             => self::default_line_rows(),
			'ptv_max'           => '',
			'trim'              => CP_Trim::defaults(),
			'trim_adjusted'     => '',
			'visual'            => array(),
			'repairs'           => '',
			'interp_visual'     => '',
			'interp_mechanical' => '',
			'interp_geometric'  => '',
			'global_state'      => '',
			'comments'          => '',
			'internal_notes'    => '',
		);
	}

	/**
	 * Données complètes d'un contrôle.
	 *
	 * @param int $post_id ID.
	 * @return array
	 */
	public static function get( $post_id ) {
		$data = get_post_meta( $post_id, self::META_DATA, true );
		$data = wp_parse_args( is_array( $data ) ? $data : array(), self::defaults() );
		$data['trim'] = CP_Trim::normalize( $data['trim'] );
		// Anciennes clés de type d'inspection (nom de label retiré).
		$data['inspection_type'] = preg_replace( '/-paracheck$/', '', (string) $data['inspection_type'] );

		$data['reference'] = (string) get_post_meta( $post_id, self::META_REFERENCE, true );
		$data['status']    = (string) get_post_meta( $post_id, self::META_STATUS, true );
		$data['verdict']   = (string) get_post_meta( $post_id, self::META_VERDICT, true );
		$data['next_date'] = (string) get_post_meta( $post_id, self::META_NEXT_DATE, true );

		if ( '' === $data['status'] ) {
			$data['status'] = 'demande';
		}
		return $data;
	}

	/**
	 * Enregistre les données (déjà nettoyées) d'un contrôle.
	 *
	 * @param int   $post_id ID.
	 * @param array $data    Données.
	 */
	public static function save( $post_id, array $data ) {
		$core = array( 'reference', 'status', 'verdict', 'next_date' );
		$meta = array_diff_key( $data, array_flip( $core ) );
		$meta = array_intersect_key( $meta, self::defaults() );

		update_post_meta( $post_id, self::META_DATA, $meta );
		update_post_meta( $post_id, self::META_EMAIL, strtolower( $data['email'] ) );
		update_post_meta( $post_id, self::META_SERIAL, $data['serial'] );

		if ( isset( $data['status'] ) && array_key_exists( $data['status'], self::statuses() ) ) {
			update_post_meta( $post_id, self::META_STATUS, $data['status'] );
		}
		if ( isset( $data['verdict'] ) && array_key_exists( $data['verdict'], self::verdicts() ) ) {
			update_post_meta( $post_id, self::META_VERDICT, $data['verdict'] );
		}
		if ( isset( $data['next_date'] ) ) {
			update_post_meta( $post_id, self::META_NEXT_DATE, $data['next_date'] );
		}
		if ( ! get_post_meta( $post_id, self::META_TOKEN, true ) ) {
			update_post_meta( $post_id, self::META_TOKEN, wp_generate_password( 32, false, false ) );
		}
		if ( ! get_post_meta( $post_id, self::META_REFERENCE, true ) ) {
			update_post_meta( $post_id, self::META_REFERENCE, self::next_reference() );
		}
	}

	/**
	 * Nettoie les données envoyées par un formulaire (admin ou public).
	 *
	 * @param array $raw   Données brutes (déjà wp_unslash).
	 * @param bool  $admin Champs techniques autorisés.
	 * @return array
	 */
	public static function sanitize( array $raw, $admin = false ) {
		$d = array();

		foreach ( array( 'pilot_name', 'phone', 'brand', 'model', 'size', 'serial', 'weight_range', 'color' ) as $key ) {
			$d[ $key ] = isset( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : '';
		}
		$d['email']        = isset( $raw['email'] ) ? sanitize_email( $raw['email'] ) : '';
		$d['address']      = isset( $raw['address'] ) ? sanitize_textarea_field( $raw['address'] ) : '';
		$d['client_notes'] = isset( $raw['client_notes'] ) ? sanitize_textarea_field( $raw['client_notes'] ) : '';
		$d['year']         = isset( $raw['year'] ) && '' !== $raw['year'] ? (string) min( 2100, max( 1980, absint( $raw['year'] ) ) ) : '';
		$d['flight_hours'] = isset( $raw['flight_hours'] ) && '' !== $raw['flight_hours'] ? (string) absint( $raw['flight_hours'] ) : '';
		$d['last_check']   = self::sanitize_date( isset( $raw['last_check'] ) ? $raw['last_check'] : '' );

		$d['equipment_type'] = self::sanitize_choice( $raw, 'equipment_type', self::equipment_types(), 'parapente' );
		$d['certification']  = self::sanitize_choice( $raw, 'certification', self::certifications(), '' );
		$d['drop_off']       = self::sanitize_choice( $raw, 'drop_off', self::drop_off_modes(), 'atelier' );

		$d['services'] = array();
		if ( isset( $raw['services'] ) && is_array( $raw['services'] ) ) {
			$d['services'] = array_values( array_intersect( array_map( 'sanitize_key', $raw['services'] ), array_keys( self::services() ) ) );
		}

		if ( ! $admin ) {
			return $d;
		}

		$d['check_date']     = self::sanitize_date( isset( $raw['check_date'] ) ? $raw['check_date'] : '' );
		$d['next_date']      = self::sanitize_date( isset( $raw['next_date'] ) ? $raw['next_date'] : '' );
		$d['technician']     = isset( $raw['technician'] ) ? sanitize_text_field( $raw['technician'] ) : '';
		$d['fabric_strength'] = isset( $raw['fabric_strength'] ) ? sanitize_text_field( $raw['fabric_strength'] ) : '';
		$d['fabric_result']  = self::sanitize_choice( $raw, 'fabric_result', array( '' => '', 'ok' => '', 'nok' => '' ), '' );

		// Type d'inspection, tests réalisés, seuils.
		$d['inspection_type']  = self::sanitize_choice( $raw, 'inspection_type', self::inspection_types(), (string) key( self::inspection_types() ) );
		$d['tests_done']       = array();
		$d['not_done_notes']   = array();
		foreach ( array_keys( self::tests() ) as $test ) {
			$d['tests_done'][ $test ] = ! empty( $raw['tests_done'][ $test ] ) ? '1' : '';
			if ( ! empty( $raw['not_done_notes'][ $test ] ) ) {
				$d['not_done_notes'][ $test ] = sanitize_text_field( $raw['not_done_notes'][ $test ] );
			}
		}
		if ( ! array_filter( $d['tests_done'] ) ) {
			$d['tests_done'] = array();
		}
		$d['threshold_source'] = self::sanitize_choice( $raw, 'threshold_source', CP_Settings::threshold_sources(), '' );
		foreach ( array( 'por_alert', 'por_reform', 'tear_reform' ) as $key ) {
			$value     = isset( $raw[ $key ] ) ? str_replace( ',', '.', trim( (string) $raw[ $key ] ) ) : '';
			$d[ $key ] = is_numeric( $value ) ? (string) ( 0 + $value ) : '';
		}
		foreach ( array( 'interp_visual', 'interp_mechanical', 'interp_geometric' ) as $key ) {
			$d[ $key ] = isset( $raw[ $key ] ) ? sanitize_textarea_field( $raw[ $key ] ) : '';
		}
		$d['global_state'] = isset( $raw['global_state'] ) && '' !== $raw['global_state'] && isset( self::state_labels()[ (int) $raw['global_state'] ] ) ? (string) (int) $raw['global_state'] : '';
		$d['trim_adjusted']  = ! empty( $raw['trim_adjusted'] ) ? '1' : '';
		$d['repairs']        = isset( $raw['repairs'] ) ? sanitize_textarea_field( $raw['repairs'] ) : '';
		$d['comments']       = isset( $raw['comments'] ) ? sanitize_textarea_field( $raw['comments'] ) : '';
		$d['internal_notes'] = isset( $raw['internal_notes'] ) ? sanitize_textarea_field( $raw['internal_notes'] ) : '';
		$d['status']         = self::sanitize_choice( $raw, 'status', self::statuses(), 'demande' );
		$d['verdict']        = self::sanitize_choice( $raw, 'verdict', self::verdicts(), '' );

		$d['porosity'] = self::sanitize_rows( $raw, 'porosity', array( 'zone' => 'text', 'value' => 'number' ) );
		$d['tear']     = self::sanitize_rows( $raw, 'tear', array( 'zone' => 'text', 'value' => 'number' ) );
		$d['ptv_max']  = isset( $raw['ptv_max'] ) && is_numeric( str_replace( ',', '.', $raw['ptv_max'] ) ) ? (string) ( 0 + str_replace( ',', '.', $raw['ptv_max'] ) ) : '';
		$d['lines']    = self::sanitize_rows( $raw, 'lines', array( 'line' => 'text', 'type' => 'text', 'new' => 'number', 'group' => 'text', 'level' => 'text', 'count' => 'number', 'measured' => 'number', 'minimum' => 'number' ) );
		$types         = CP_Settings::line_types();
		$ptv           = self::ptv_max( array_merge( $d, array( 'weight_range' => isset( $d['weight_range'] ) ? $d['weight_range'] : '' ) ) );
		foreach ( $d['lines'] as $i => $row ) {
			$row['type']  = isset( $types[ $row['type'] ] ) ? $row['type'] : '';
			$row['group'] = array_key_exists( $row['group'], self::line_groups() ) ? $row['group'] : 'manuel';
			$row['level'] = array_key_exists( $row['level'], self::line_levels() ) ? $row['level'] : 'bas';
			if ( $row['type'] && '' === $row['new'] ) {
				$row['new'] = (string) $types[ $row['type'] ]['new'];
			}
			// Minimum recalculé côté serveur (même règle que dans la fiche).
			$min = self::line_minimum( $row, $ptv );
			if ( null !== $min ) {
				$row['minimum'] = (string) $min;
			}
			$d['lines'][ $i ] = $row;
		}
		$d['trim']     = CP_Trim::sanitize( isset( $raw['trim'] ) ? $raw['trim'] : array() );

		$d['visual'] = array();
		$states      = self::visual_states();
		foreach ( array_keys( self::visual_items() ) as $item ) {
			$state   = isset( $raw['visual'][ $item ]['state'] ) ? sanitize_key( $raw['visual'][ $item ]['state'] ) : '';
			$comment = isset( $raw['visual'][ $item ]['comment'] ) ? sanitize_text_field( $raw['visual'][ $item ]['comment'] ) : '';
			$d['visual'][ $item ] = array(
				'state'   => array_key_exists( $state, $states ) ? $state : '',
				'comment' => $comment,
			);
		}

		return $d;
	}

	private static function sanitize_choice( $raw, $key, $choices, $default ) {
		$value = isset( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : $default;
		return array_key_exists( $value, $choices ) ? $value : $default;
	}

	public static function sanitize_date( $value ) {
		$value = sanitize_text_field( (string) $value );
		$date  = DateTime::createFromFormat( '!Y-m-d', $value );
		return ( $date && $date->format( 'Y-m-d' ) === $value ) ? $value : '';
	}

	private static function sanitize_rows( $raw, $key, $schema ) {
		$rows = array();
		if ( empty( $raw[ $key ] ) || ! is_array( $raw[ $key ] ) ) {
			return $rows;
		}
		foreach ( $raw[ $key ] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean = array();
			$empty = true;
			foreach ( $schema as $field => $type ) {
				$value = isset( $row[ $field ] ) ? trim( (string) $row[ $field ] ) : '';
				if ( 'number' === $type ) {
					$value = str_replace( ',', '.', $value );
					$value = is_numeric( $value ) ? (string) ( 0 + $value ) : '';
				} else {
					$value = sanitize_text_field( $value );
				}
				if ( '' !== $value ) {
					$empty = false;
				}
				$clean[ $field ] = $value;
			}
			if ( ! $empty ) {
				$rows[] = $clean;
			}
		}
		return $rows;
	}

	/**
	 * Génère la prochaine référence : PREFIXE-AAAA-0001.
	 */
	public static function next_reference() {
		$year   = gmdate( 'Y' );
		$option = 'cp_counter_' . $year;
		$count  = (int) get_option( $option, 0 ) + 1;
		update_option( $option, $count, false );

		return sprintf( '%s-%s-%04d', CP_Settings::get( 'reference_prefix' ), $year, $count );
	}

	/**
	 * Date du prochain contrôle proposée à partir de la date de contrôle.
	 *
	 * @param string $check_date Date Y-m-d.
	 * @return string
	 */
	public static function suggested_next_date( $check_date ) {
		$months = (int) CP_Settings::get( 'validity_months' );
		if ( '' === $check_date || $months <= 0 ) {
			return '';
		}
		$date = DateTime::createFromFormat( '!Y-m-d', $check_date );
		if ( ! $date ) {
			return '';
		}
		$date->modify( '+' . $months . ' months' );
		return $date->format( 'Y-m-d' );
	}

	/**
	 * Évaluation d'une mesure de porosité : ok / warn (alerte) / bad (réforme) / ''.
	 * La mesure est d'abord convertie en l/m²/min ; plus la valeur est haute, plus le tissu est poreux.
	 *
	 * @param string $value Mesure saisie.
	 * @param array  $t     Seuils (CP_Controle::thresholds()).
	 */
	public static function porosity_level( $value, array $t ) {
		$flow = self::porosity_lm2min( $value, $t );
		if ( null === $flow ) {
			return '';
		}
		return $flow >= $t['porosity_reform'] ? 'bad' : ( $flow >= $t['porosity_alert'] ? 'warn' : 'ok' );
	}

	public static function tear_level( $value, array $t ) {
		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}
		$value = (float) $value;
		return $value < $t['tear_reform'] ? 'bad' : ( $value < $t['tear_good'] ? 'warn' : 'ok' );
	}

	/**
	 * Statistiques simples d'une série de mesures : min, max, moyenne.
	 */
	public static function stats( array $rows, $field = 'value' ) {
		$values = array();
		foreach ( $rows as $row ) {
			if ( isset( $row[ $field ] ) && is_numeric( $row[ $field ] ) ) {
				$values[] = (float) $row[ $field ];
			}
		}
		if ( ! $values ) {
			return null;
		}
		return array(
			'min'  => min( $values ),
			'max'  => max( $values ),
			'mean' => array_sum( $values ) / count( $values ),
			'n'    => count( $values ),
		);
	}

	public static function line_level( $measured, $minimum ) {
		if ( ! is_numeric( $measured ) || ! is_numeric( $minimum ) ) {
			return '';
		}
		return (float) $measured >= (float) $minimum ? 'ok' : 'bad';
	}

	public static function equipment_label( array $data ) {
		$label = trim( $data['brand'] . ' ' . $data['model'] . ' ' . $data['size'] );
		return '' !== $label ? $label : __( 'Équipement', 'controle-parapente' );
	}

	/**
	 * Recherche un contrôle par référence + e-mail (suivi client).
	 *
	 * @return int|0
	 */
	public static function find_by_reference( $reference, $email ) {
		$posts = get_posts(
			array(
				'post_type'      => CP_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => self::META_REFERENCE, 'value' => $reference ),
					array( 'key' => self::META_EMAIL, 'value' => strtolower( $email ) ),
				),
			)
		);
		return $posts ? (int) $posts[0] : 0;
	}

	public static function public_certificate_url( $post_id ) {
		return add_query_arg(
			array(
				'cp_certificat' => rawurlencode( get_post_meta( $post_id, self::META_REFERENCE, true ) ),
				'cle'           => get_post_meta( $post_id, self::META_TOKEN, true ),
			),
			home_url( '/' )
		);
	}

	public static function has_certificate( $status ) {
		return in_array( $status, array( 'terminee', 'rendue' ), true );
	}

	public static function format_date( $date ) {
		if ( '' === $date ) {
			return '—';
		}
		$ts = strtotime( $date . ' 12:00:00' );
		return $ts ? date_i18n( get_option( 'date_format' ), $ts ) : '—';
	}
}
