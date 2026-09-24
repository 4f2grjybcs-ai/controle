<?php
/**
 * Réglages du plugin : page à onglets où l'atelier modifie ses informations,
 * les normes et seuils, les instruments, tous les textes, les listes et les e-mails.
 *
 * Chaque réglage est décrit une seule fois dans schema() : onglet, type, libellé,
 * aide et valeur par défaut. Le rendu et le nettoyage en découlent.
 *
 * @package ControleParapente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Settings {

	const OPTION = 'cp_settings';

	/** @var array|null Cache des réglages de la requête. */
	private static $cache = null;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
	}

	public static function flush() {
		self::$cache = null;
	}

	public static function tabs() {
		return array(
			'atelier'     => __( 'Atelier', 'controle-parapente' ),
			'normes'      => __( 'Normes & seuils', 'controle-parapente' ),
			'instruments' => __( 'Instruments', 'controle-parapente' ),
			'rapport'     => __( 'Textes du rapport', 'controle-parapente' ),
			'listes'      => __( 'Listes', 'controle-parapente' ),
			'emails'      => __( 'E-mails', 'controle-parapente' ),
			'formulaire'  => __( 'Formulaire public', 'controle-parapente' ),
		);
	}

	/**
	 * Description de tous les réglages.
	 *
	 * Types : text, textarea, email, url, number, color, logo, checkbox, select, date, lines, heading.
	 */
	public static function schema() {
		$placeholders = __( 'Variables : {client} {reference} {aile} {serie} {statut} {resultat} {prochain_controle} {lien} {atelier} {telephone}', 'controle-parapente' );

		return array(
			/* ---------------- Atelier ---------------- */
			'h_identity'          => array( 'atelier', 'heading', __( 'Identité de l\'atelier', 'controle-parapente' ) ),
			'workshop_name'       => array( 'atelier', 'text', __( 'Nom de l\'atelier', 'controle-parapente' ), get_bloginfo( 'name' ) ),
			'manager_name'        => array( 'atelier', 'text', __( 'Responsable (nom et prénom)', 'controle-parapente' ), '', __( 'Mention obligatoire sur le rapport ParachecK.', 'controle-parapente' ) ),
			'workshop_address'    => array( 'atelier', 'textarea', __( 'Adresse', 'controle-parapente' ), '' ),
			'workshop_phone'      => array( 'atelier', 'text', __( 'Téléphone', 'controle-parapente' ), '' ),
			'workshop_email'      => array( 'atelier', 'email', __( 'E-mail affiché sur le rapport', 'controle-parapente' ), '' ),
			'workshop_website'    => array( 'atelier', 'url', __( 'Site web', 'controle-parapente' ), '' ),
			'workshop_approval'   => array( 'atelier', 'text', __( 'SIRET / n° d\'agrément', 'controle-parapente' ), '' ),
			'insurer'             => array( 'atelier', 'text', __( 'Assureur RC professionnelle', 'controle-parapente' ), '' ),
			'insurance_policy'    => array( 'atelier', 'text', __( 'N° de police d\'assurance', 'controle-parapente' ), '', __( 'Mention obligatoire sur le rapport ParachecK.', 'controle-parapente' ) ),
			'paracheck_member'    => array( 'atelier', 'checkbox', __( 'Atelier signataire de la charte FFVL ParachecK®', 'controle-parapente' ), '1' ),
			'paracheck_number'    => array( 'atelier', 'text', __( 'Référence ParachecK de l\'atelier (facultatif)', 'controle-parapente' ), '' ),
			'h_look'              => array( 'atelier', 'heading', __( 'Apparence', 'controle-parapente' ) ),
			'logo_id'             => array( 'atelier', 'logo', __( 'Logo', 'controle-parapente' ), 0, __( 'Affiché en haut de l\'espace atelier et du rapport client.', 'controle-parapente' ) ),
			'accent_color'        => array( 'atelier', 'color', __( 'Couleur d\'accent', 'controle-parapente' ), '#c0643f' ),
			'h_admin'             => array( 'atelier', 'heading', __( 'Fonctionnement', 'controle-parapente' ) ),
			'notify_email'        => array( 'atelier', 'email', __( 'E-mail recevant les nouvelles demandes', 'controle-parapente' ), get_option( 'admin_email' ) ),
			'reference_prefix'    => array( 'atelier', 'text', __( 'Préfixe des références', 'controle-parapente' ), 'CP', __( 'Ex. CP → CP-2026-0001', 'controle-parapente' ) ),
			'validity_months'     => array( 'atelier', 'number', __( 'Prochain contrôle conseillé après (mois)', 'controle-parapente' ), 24 ),
			'reminder_days'       => array( 'atelier', 'number', __( 'Rappel client avant l\'échéance (jours, 0 = aucun)', 'controle-parapente' ), 30 ),

			/* ---------------- Normes & seuils ---------------- */
			'h_norms'             => array( 'normes', 'heading', __( 'Références', 'controle-parapente' ) ),
			'norms_reference'     => array( 'normes', 'textarea', __( 'Normes appliquées (texte du rapport)', 'controle-parapente' ), __( 'Contrôle réalisé selon le PMA Standard « Periodical Inspection of Paragliders » (V 2024.12.1) et la charte FFVL ParachecK® des ateliers de contrôle (V3.01). Valeurs de référence utilisées, par ordre de priorité : valeurs du constructeur, valeurs PMA, valeurs de la charte.', 'controle-parapente' ), __( 'Pensez à mettre à jour ce texte lors d\'une nouvelle version de la norme ou de la charte.', 'controle-parapente' ) ),
			'threshold_source'    => array( 'normes', 'select', __( 'Origine des seuils par défaut', 'controle-parapente' ), 'charte', __( 'Modifiable pour chaque contrôle (ex. seuils du manuel constructeur).', 'controle-parapente' ), self::threshold_sources() ),
			'h_porosity'          => array( 'normes', 'heading', __( 'Porosité', 'controle-parapente' ) ),
			'porosity_unit'       => array( 'normes', 'select', __( 'Unité de mesure', 'controle-parapente' ), 'lm2min', __( 'La charte ParachecK demande l\'expression en l/m²/min.', 'controle-parapente' ), self::porosity_units() ),
			'porosity_alert'      => array( 'normes', 'number', __( 'Valeur d\'alerte', 'controle-parapente' ), 490, __( 'En l/m²/min : alerte au-dessus. En secondes : alerte en dessous.', 'controle-parapente' ) ),
			'porosity_reform'     => array( 'normes', 'number', __( 'Valeur de réforme', 'controle-parapente' ), 540, __( 'En l/m²/min : réforme au-dessus. En secondes : réforme en dessous.', 'controle-parapente' ) ),
			'h_tear'              => array( 'normes', 'heading', __( 'Résistance à la déchirure (Bettsomètre)', 'controle-parapente' ) ),
			'tear_reform'         => array( 'normes', 'number', __( 'Valeur de réforme (g)', 'controle-parapente' ), 600 ),
			'tear_good'           => array( 'normes', 'number', __( 'Valeur « bonne » (g)', 'controle-parapente' ), 900, __( 'Entre la réforme et cette valeur : à surveiller.', 'controle-parapente' ) ),
			'h_geometry'          => array( 'normes', 'heading', __( 'Géométrie (calage)', 'controle-parapente' ) ),
			'trim_tolerance'      => array( 'normes', 'number', __( 'Tolérance (± mm)', 'controle-parapente' ), 15 ),
			'trim_load'           => array( 'normes', 'text', __( 'Tension de mesure des suspentes', 'controle-parapente' ), '5 daN', __( 'Affichée sur le rapport (le standard PMA prévoit une mesure sous 5 daN).', 'controle-parapente' ) ),

			/* ---------------- Instruments ---------------- */
			'h_instruments'       => array( 'instruments', 'heading', __( 'Instruments de mesure', 'controle-parapente' ), '', __( 'Affichés sur le rapport avec la date du dernier étalonnage / contrôle. Une alerte apparaît dans l\'atelier si la date dépasse le délai ci-dessous.', 'controle-parapente' ) ),
			'calibration_months'  => array( 'instruments', 'number', __( 'Délai maximal entre deux étalonnages (mois)', 'controle-parapente' ), 12 ),
			'inst_porosity'       => array( 'instruments', 'text', __( 'Porosimètre (modèle, n° de série)', 'controle-parapente' ), '' ),
			'inst_porosity_date'  => array( 'instruments', 'date', __( 'Porosimètre — dernier étalonnage', 'controle-parapente' ), '' ),
			'inst_tear'           => array( 'instruments', 'text', __( 'Bettsomètre (modèle, n° de série)', 'controle-parapente' ), '' ),
			'inst_tear_date'      => array( 'instruments', 'date', __( 'Bettsomètre — dernier étalonnage', 'controle-parapente' ), '' ),
			'inst_lines'          => array( 'instruments', 'text', __( 'Banc / dynamomètre de rupture', 'controle-parapente' ), '' ),
			'inst_lines_date'     => array( 'instruments', 'date', __( 'Banc de rupture — dernier étalonnage', 'controle-parapente' ), '' ),
			'inst_length'         => array( 'instruments', 'text', __( 'Banc de mesure des longueurs / laser', 'controle-parapente' ), '' ),
			'inst_length_date'    => array( 'instruments', 'date', __( 'Banc de mesure — dernier étalonnage', 'controle-parapente' ), '' ),

			/* ---------------- Textes du rapport ---------------- */
			'h_report'            => array( 'rapport', 'heading', __( 'En-tête et synthèse', 'controle-parapente' ) ),
			'report_title'        => array( 'rapport', 'text', __( 'Titre du rapport', 'controle-parapente' ), __( 'Rapport de contrôle', 'controle-parapente' ) ),
			'report_intro'        => array( 'rapport', 'textarea', __( 'Texte d\'introduction (sous le titre)', 'controle-parapente' ), '' ),
			'note_title'          => array( 'rapport', 'text', __( 'Titre du mot de l\'atelier', 'controle-parapente' ), __( 'Le mot de l\'atelier', 'controle-parapente' ) ),
			'state_title'         => array( 'rapport', 'text', __( 'Titre du curseur d\'état', 'controle-parapente' ), __( 'État général de l\'aile', 'controle-parapente' ) ),
			'state_labels'        => array( 'rapport', 'lines', __( 'Positions du curseur d\'état (une par ligne, de la meilleure à la pire)', 'controle-parapente' ), "Neuf\nTrès bon état\nBon état\nÉtat correct\nUsé — à surveiller\nRéforme" ),
			'state_help'          => array( 'rapport', 'textarea', __( 'Explication du curseur', 'controle-parapente' ), __( 'Curseur établi à l\'issue d\'une révision périodique complète. Il indique l\'état de l\'aile au jour du contrôle ; il ne constitue ni un pourcentage d\'usure, ni une durée de vie restante.', 'controle-parapente' ) ),
			'state_unavailable'   => array( 'rapport', 'textarea', __( 'Texte si l\'état global n\'est pas évaluable', 'controle-parapente' ), __( 'État global non évaluable : seule une révision périodique complète (tous les tests réalisés) permet d\'évaluer l\'état général de l\'aile.', 'controle-parapente' ) ),
			'h_sections'          => array( 'rapport', 'heading', __( 'Titres des inspections', 'controle-parapente' ) ),
			'title_visual'        => array( 'rapport', 'text', __( 'Inspection visuelle', 'controle-parapente' ), __( 'Inspection visuelle', 'controle-parapente' ) ),
			'title_mechanical'    => array( 'rapport', 'text', __( 'Inspection mécanique', 'controle-parapente' ), __( 'Inspection mécanique', 'controle-parapente' ) ),
			'title_geometric'     => array( 'rapport', 'text', __( 'Inspection géométrique', 'controle-parapente' ), __( 'Inspection géométrique (calage)', 'controle-parapente' ) ),
			'not_done_text'       => array( 'rapport', 'text', __( 'Mention d\'un test non réalisé', 'controle-parapente' ), __( 'Non réalisé', 'controle-parapente' ) ),
			'h_footer'            => array( 'rapport', 'heading', __( 'Pied du rapport', 'controle-parapente' ) ),
			'thanks_text'         => array( 'rapport', 'text', __( 'Phrase de remerciement', 'controle-parapente' ), __( 'Merci de votre confiance, et bons vols !', 'controle-parapente' ) ),
			'certificate_footer'  => array( 'rapport', 'textarea', __( 'Mention en bas du rapport', 'controle-parapente' ), __( 'Ce contrôle atteste de l\'état du matériel à la date indiquée. Il ne dispense pas le pilote d\'une visite pré-vol à chaque utilisation.', 'controle-parapente' ) ),
			'signature_label'     => array( 'rapport', 'text', __( 'Libellé de la zone de signature', 'controle-parapente' ), __( 'Signature et cachet de l\'atelier', 'controle-parapente' ) ),

			/* ---------------- Listes ---------------- */
			'inspection_types'    => array( 'listes', 'lines', __( 'Types d\'inspection', 'controle-parapente' ), "Révision périodique ParachecK® | V P T L G E\nInspection intermédiaire ParachecK® | V P T G\nInspection basique ParachecK® | V P T G\nInspection mécanique ParachecK® | P T L\nInspection géométrique ParachecK® | G\nInspection visuelle / après incident ParachecK® | V", __( 'Une par ligne : « Nom | lettres des tests inclus ». V = visuelle, P = porosité, T = déchirure, L = résistance des suspentes, G = calage, E = état global évaluable. Les tests absents apparaissent « Non réalisé » sur le rapport.', 'controle-parapente' ) ),
			'visual_items'        => array( 'listes', 'lines', __( 'Points de l\'inspection visuelle', 'controle-parapente' ), "Tissu extrados (déchirures, usure, UV)\nTissu intrados\nBord d'attaque, joncs, entrées d'air\nBord de fuite\nCloisons, diagonales, renforts\nCoutures\nPattes et points d'ancrage des suspentes\nSuspentes (gaine, nœuds, abrasion)\nÉlévateurs (sangles, coutures, marquage)\nMaillons / connecteurs\nSystème d'accélérateur (poulies, drisses)\nPoignées et drisses de frein\nÉtiquette et marquage d'homologation", __( 'Un point par ligne. Renommer un point efface son état sur les fiches existantes.', 'controle-parapente' ) ),
			'porosity_points'     => array( 'listes', 'lines', __( 'Points de mesure de porosité proposés', 'controle-parapente' ), "Extrados — centre gauche (20-30 cm du BA)\nExtrados — centre droit (20-30 cm du BA)\nExtrados — 1/4 envergure gauche\nExtrados — 1/4 envergure droite\nExtrados — 1/2 envergure gauche\nExtrados — 1/2 envergure droite\nIntrados — centre" ),
			'tear_points'         => array( 'listes', 'lines', __( 'Points de mesure de déchirure proposés', 'controle-parapente' ), "Extrados — bord d'attaque centre\nIntrados — centre\nCloison — centre" ),
			'line_points'         => array( 'listes', 'lines', __( 'Suspentes testées à la rupture proposées', 'controle-parapente' ), "A — étage bas (centrale)\nA — étage médian\nA — étage haut\nB — étage bas (centrale)\nC — étage bas (centrale)\nFrein — étage bas" ),
			'services'            => array( 'listes', 'lines', __( 'Prestations proposées', 'controle-parapente' ), "Révision complète\nRecalage des suspentes\nRéparation\nRepliage parachute de secours" ),

			/* ---------------- E-mails ---------------- */
			'h_mail_help'         => array( 'emails', 'heading', __( 'Modèles d\'e-mails envoyés aux clients', 'controle-parapente' ), '', $placeholders ),
			'mail_request_subject' => array( 'emails', 'text', __( 'Demande reçue — objet', 'controle-parapente' ), __( 'Votre demande de contrôle {reference}', 'controle-parapente' ) ),
			'mail_request_body'   => array( 'emails', 'textarea', __( 'Demande reçue — message', 'controle-parapente' ), __( "Bonjour {client},\n\nNous avons bien reçu votre demande de contrôle pour votre {aile}. Conservez votre référence {reference} : elle vous permet de suivre l'avancement sur notre site.\n\nÀ très bientôt à l'atelier !", 'controle-parapente' ) ),
			'mail_status_subject' => array( 'emails', 'text', __( 'Changement de statut — objet', 'controle-parapente' ), __( 'Contrôle {reference} : {statut}', 'controle-parapente' ) ),
			'mail_status_body'    => array( 'emails', 'textarea', __( 'Changement de statut — message', 'controle-parapente' ), __( "Bonjour {client},\n\nLe contrôle de votre {aile} ({reference}) est maintenant : {statut}.", 'controle-parapente' ) ),
			'mail_report_subject' => array( 'emails', 'text', __( 'Envoi du rapport — objet', 'controle-parapente' ), __( 'Votre rapport de contrôle {reference}', 'controle-parapente' ) ),
			'mail_report_body'    => array( 'emails', 'textarea', __( 'Envoi du rapport — message', 'controle-parapente' ), __( "Bonjour {client},\n\nLe contrôle de votre {aile} est terminé. Résultat : {resultat}.\nProchain contrôle conseillé : {prochain_controle}.\n\nVotre rapport complet : {lien}\n\nMerci de votre confiance et bons vols !", 'controle-parapente' ) ),
			'mail_reminder_subject' => array( 'emails', 'text', __( 'Rappel d\'échéance — objet', 'controle-parapente' ), __( 'Rappel : contrôle de votre {aile}', 'controle-parapente' ) ),
			'mail_reminder_body'  => array( 'emails', 'textarea', __( 'Rappel d\'échéance — message', 'controle-parapente' ), __( "Bonjour {client},\n\nLe prochain contrôle de votre {aile} est prévu pour le {prochain_controle}. Un contrôle régulier est indispensable pour voler en sécurité : n'hésitez pas à nous contacter.", 'controle-parapente' ) ),

			/* ---------------- Formulaire public ---------------- */
			'form_intro'          => array( 'formulaire', 'textarea', __( 'Texte d\'introduction du formulaire de demande', 'controle-parapente' ), '' ),
			'form_consent'        => array( 'formulaire', 'textarea', __( 'Texte de consentement (RGPD)', 'controle-parapente' ), __( 'J\'accepte que mes données soient utilisées pour traiter ma demande et me rappeler l\'échéance du prochain contrôle.', 'controle-parapente' ) ),
			'form_success'        => array( 'formulaire', 'textarea', __( 'Message après envoi', 'controle-parapente' ), __( 'Merci ! Votre demande a bien été enregistrée sous la référence {reference}. Un e-mail de confirmation vient de vous être envoyé.', 'controle-parapente' ) ),
		);
	}

	public static function threshold_sources() {
		return array(
			'constructeur' => __( 'Valeurs du constructeur', 'controle-parapente' ),
			'pma'          => __( 'Valeurs PMA', 'controle-parapente' ),
			'charte'       => __( 'Valeurs de la charte ParachecK', 'controle-parapente' ),
		);
	}

	public static function porosity_units() {
		return array(
			'lm2min' => __( 'l/m²/min (plus c\'est haut, plus le tissu est poreux)', 'controle-parapente' ),
			's'      => __( 'secondes (plus c\'est bas, plus le tissu est poreux)', 'controle-parapente' ),
		);
	}

	public static function defaults() {
		$defaults = array();
		foreach ( self::schema() as $key => $field ) {
			if ( 'heading' !== $field[1] ) {
				$defaults[ $key ] = $field[3];
			}
		}
		return $defaults;
	}

	/**
	 * Retourne un réglage (ou tous).
	 *
	 * @param string|null $key Clé.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		if ( null === self::$cache ) {
			self::$cache = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		}
		if ( null === $key ) {
			return self::$cache;
		}
		return isset( self::$cache[ $key ] ) ? self::$cache[ $key ] : null;
	}

	/**
	 * Réglage de type liste : une entrée par ligne.
	 */
	public static function lines( $key ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) self::get( $key ) ) ), 'strlen' ) );
	}

	/**
	 * Liste avec clés stables dérivées du libellé : [ 'cle' => 'Libellé' ].
	 */
	public static function keyed_list( $key ) {
		$out = array();
		foreach ( self::lines( $key ) as $label ) {
			$label = trim( explode( '|', $label )[0] );
			$slug  = sanitize_title( $label );
			if ( '' !== $slug && ! isset( $out[ $slug ] ) ) {
				$out[ $slug ] = $label;
			}
		}
		return $out;
	}

	/**
	 * Remplace les variables {xxx} d'un modèle de texte.
	 *
	 * @param string $key  Clé du réglage.
	 * @param array  $vars Variables.
	 */
	public static function tpl( $key, array $vars ) {
		$search = array();
		foreach ( array_keys( $vars ) as $name ) {
			$search[] = '{' . $name . '}';
		}
		return str_replace( $search, array_values( $vars ), (string) self::get( $key ) );
	}

	/**
	 * URL du logo de l'atelier (ou '').
	 *
	 * @param string $size Taille d'image.
	 */
	public static function logo_url( $size = 'medium' ) {
		$id = (int) self::get( 'logo_id' );
		if ( ! $id ) {
			return '';
		}
		$src = wp_get_attachment_image_src( $id, $size );
		return $src ? $src[0] : '';
	}

	/**
	 * Instruments renseignés : [ [ 'label', 'name', 'date', 'expired' ], … ].
	 */
	public static function instruments() {
		$months = (int) self::get( 'calibration_months' );
		$limit  = $months > 0 ? gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . $months . ' months' ) ) : '';
		$list   = array(
			'inst_porosity' => __( 'Porosimètre', 'controle-parapente' ),
			'inst_tear'     => __( 'Bettsomètre', 'controle-parapente' ),
			'inst_lines'    => __( 'Banc de rupture', 'controle-parapente' ),
			'inst_length'   => __( 'Banc de mesure', 'controle-parapente' ),
		);
		$out    = array();
		foreach ( $list as $key => $label ) {
			$name = (string) self::get( $key );
			$date = (string) self::get( $key . '_date' );
			if ( '' === $name && '' === $date ) {
				continue;
			}
			$out[] = array(
				'label'   => $label,
				'name'    => $name,
				'date'    => $date,
				'expired' => $limit && ( '' === $date || $date < $limit ),
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Page de réglages                                                    */
	/* ------------------------------------------------------------------ */

	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, 'cp-settings' ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'cp-settings', CP_URL . 'assets/css/settings.css', array(), CP_VERSION );
		wp_enqueue_script( 'cp-settings', CP_URL . 'assets/js/settings.js', array( 'jquery' ), CP_VERSION, true );
	}

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=' . CP_Post_Type::POST_TYPE,
			__( 'Réglages', 'controle-parapente' ),
			__( 'Réglages', 'controle-parapente' ),
			'manage_options',
			'cp-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register() {
		register_setting(
			'cp_settings_group',
			self::OPTION,
			array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) )
		);
	}

	public static function sanitize( $input ) {
		$input = (array) $input;
		$out   = self::defaults();

		foreach ( self::schema() as $key => $field ) {
			$type = $field[1];
			if ( 'heading' === $type ) {
				continue;
			}
			$value = isset( $input[ $key ] ) ? $input[ $key ] : null;
			switch ( $type ) {
				case 'checkbox':
					$out[ $key ] = empty( $value ) ? '' : '1';
					break;
				case 'textarea':
				case 'lines':
					if ( null !== $value ) {
						$out[ $key ] = sanitize_textarea_field( $value );
					}
					break;
				case 'email':
					$out[ $key ] = $value && is_email( $value ) ? sanitize_email( $value ) : ( 'notify_email' === $key ? $out[ $key ] : '' );
					break;
				case 'url':
					$out[ $key ] = $value ? esc_url_raw( $value ) : '';
					break;
				case 'number':
					if ( null !== $value && '' !== $value ) {
						$out[ $key ] = max( 0, (float) str_replace( ',', '.', $value ) );
						$out[ $key ] = floor( $out[ $key ] ) == $out[ $key ] ? (int) $out[ $key ] : $out[ $key ];
					}
					break;
				case 'color':
					$color       = sanitize_hex_color( (string) $value );
					$out[ $key ] = $color ? $color : $out[ $key ];
					break;
				case 'logo':
					$out[ $key ] = absint( $value );
					break;
				case 'date':
					$out[ $key ] = CP_Controle::sanitize_date( (string) $value );
					break;
				case 'select':
					$out[ $key ] = null !== $value && array_key_exists( $value, $field[5] ) ? $value : $out[ $key ];
					break;
				default:
					if ( null !== $value ) {
						$out[ $key ] = sanitize_text_field( $value );
					}
			}
		}

		$out['reference_prefix'] = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $out['reference_prefix'] ) );
		if ( '' === $out['reference_prefix'] ) {
			$out['reference_prefix'] = 'CP';
		}
		self::flush();
		return $out;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = self::get();
		$schema = self::schema();
		?>
		<div class="wrap cp-settings">
			<h1><?php esc_html_e( 'Contrôle Parapente — Réglages', 'controle-parapente' ); ?></h1>
			<p class="cp-settings-lead"><?php esc_html_e( 'Tout ce qui apparaît dans l\'atelier, sur les rapports et dans les e-mails se modifie ici. Les changements s\'appliquent immédiatement, y compris aux rapports déjà créés.', 'controle-parapente' ); ?></p>

			<nav class="nav-tab-wrapper cp-settings-tabs">
				<?php foreach ( self::tabs() as $tab => $label ) : ?>
					<a href="#<?php echo esc_attr( $tab ); ?>" class="nav-tab" data-tab="<?php echo esc_attr( $tab ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'cp_settings_group' ); ?>
				<?php foreach ( self::tabs() as $tab => $tab_label ) : ?>
					<div class="cp-settings-panel" data-panel="<?php echo esc_attr( $tab ); ?>">
						<table class="form-table" role="presentation">
						<?php
						foreach ( $schema as $key => $field ) {
							if ( $field[0] === $tab ) {
								self::render_field( $key, $field, $s );
							}
						}
						?>
						</table>
						<?php if ( 'formulaire' === $tab ) : ?>
							<h2><?php esc_html_e( 'Pages et shortcodes', 'controle-parapente' ); ?></h2>
							<p><code>[cp_atelier]</code> — <?php esc_html_e( 'espace atelier (réservé à l\'équipe connectée).', 'controle-parapente' ); ?></p>
							<p><code>[cp_demande_controle]</code> — <?php esc_html_e( 'formulaire de demande de contrôle.', 'controle-parapente' ); ?></p>
							<p><code>[cp_suivi_controle]</code> — <?php esc_html_e( 'suivi d\'un contrôle par le client (référence + e-mail).', 'controle-parapente' ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
				<div class="cp-settings-save"><?php submit_button( __( 'Enregistrer les réglages', 'controle-parapente' ), 'primary large', 'submit', false ); ?></div>
			</form>
		</div>
		<?php
	}

	private static function render_field( $key, $field, $s ) {
		list( , $type, $label ) = $field;
		$help  = isset( $field[4] ) ? $field[4] : '';
		$id    = 'cp-' . $key;
		$name  = self::OPTION . '[' . $key . ']';
		$value = isset( $s[ $key ] ) ? $s[ $key ] : '';

		if ( 'heading' === $type ) {
			echo '<tr class="cp-settings-heading"><th colspan="2"><h2>' . esc_html( $label ) . '</h2>';
			if ( $help ) {
				echo '<p class="description">' . esc_html( $help ) . '</p>';
			}
			echo '</th></tr>';
			return;
		}

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		switch ( $type ) {
			case 'textarea':
			case 'lines':
				$rows = max( 3, min( 14, substr_count( (string) $value, "\n" ) + 2 ) );
				printf( '<textarea id="%1$s" name="%2$s" rows="%3$d" class="large-text%4$s">%5$s</textarea>', esc_attr( $id ), esc_attr( $name ), (int) $rows, 'lines' === $type ? ' code' : '', esc_textarea( $value ) );
				break;
			case 'checkbox':
				printf( '<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>', esc_attr( $id ), esc_attr( $name ), checked( $value, '1', false ), esc_html__( 'Oui', 'controle-parapente' ) );
				break;
			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $field[5] as $opt => $opt_label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $opt_label ) );
				}
				echo '</select>';
				break;
			case 'logo':
				$url = self::logo_url();
				printf( '<input type="hidden" id="cp-logo_id" name="%s" value="%s" />', esc_attr( $name ), esc_attr( $value ) );
				printf( '<img id="cp-logo-preview" src="%s" alt="" style="max-height:80px;display:%s;margin-bottom:8px;" />', esc_url( $url ), $url ? 'block' : 'none' );
				printf( '<button type="button" class="button" id="cp-logo-pick">%s</button> ', esc_html__( 'Choisir le logo', 'controle-parapente' ) );
				printf( '<button type="button" class="button-link" id="cp-logo-remove" style="%s">%s</button>', $url ? '' : 'display:none', esc_html__( 'Retirer', 'controle-parapente' ) );
				break;
			default:
				$input_type = in_array( $type, array( 'email', 'url', 'number', 'color', 'date' ), true ) ? $type : 'text';
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="%5$s"%6$s />',
					esc_attr( $input_type ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					in_array( $type, array( 'number', 'color', 'date' ), true ) ? 'small-text' : ( false !== strpos( $key, 'subject' ) || 'text' === $type ? 'large-text' : 'regular-text' ),
					'number' === $type ? ' step="any" min="0"' : ''
				);
		}
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}
}
