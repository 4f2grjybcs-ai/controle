<?php
/**
 * Réglages du plugin (atelier, seuils techniques, e-mails).
 *
 * @package ControleParapente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Settings {

	const OPTION = 'cp_settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function defaults() {
		return array(
			'workshop_name'      => get_bloginfo( 'name' ),
			'workshop_address'   => '',
			'workshop_phone'     => '',
			'workshop_approval'  => '',
			'notify_email'       => get_option( 'admin_email' ),
			'reference_prefix'   => 'CP',
			'validity_months'    => 24,
			'reminder_days'      => 30,
			'porosity_min'       => 20,
			'porosity_warn'      => 60,
			'trim_tolerance'     => 15,
			'form_intro'         => '',
			'certificate_footer' => __( 'Ce contrôle atteste de l\'état du matériel à la date indiquée. Il ne dispense pas le pilote d\'une visite pré-vol à chaque utilisation.', 'controle-parapente' ),
		);
	}

	/**
	 * Retourne un réglage (ou tous).
	 *
	 * @param string|null $key Clé.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		if ( null === $key ) {
			return $settings;
		}
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
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

		foreach ( array( 'workshop_name', 'workshop_phone', 'workshop_approval', 'reference_prefix' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}
		foreach ( array( 'workshop_address', 'form_intro', 'certificate_footer' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_textarea_field( $input[ $key ] );
			}
		}
		if ( isset( $input['notify_email'] ) && is_email( $input['notify_email'] ) ) {
			$out['notify_email'] = sanitize_email( $input['notify_email'] );
		}
		foreach ( array( 'validity_months', 'reminder_days', 'porosity_min', 'porosity_warn', 'trim_tolerance' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = max( 0, absint( $input[ $key ] ) );
			}
		}
		$out['reference_prefix'] = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $out['reference_prefix'] ) );
		if ( '' === $out['reference_prefix'] ) {
			$out['reference_prefix'] = 'CP';
		}

		return $out;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s    = self::get();
		$name = self::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Contrôle Parapente — Réglages', 'controle-parapente' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'cp_settings_group' ); ?>

				<h2><?php esc_html_e( 'Atelier', 'controle-parapente' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::text_row( $name, 'workshop_name', __( 'Nom de l\'atelier', 'controle-parapente' ), $s );
					self::textarea_row( $name, 'workshop_address', __( 'Adresse', 'controle-parapente' ), $s );
					self::text_row( $name, 'workshop_phone', __( 'Téléphone', 'controle-parapente' ), $s );
					self::text_row( $name, 'workshop_approval', __( 'N° d\'agrément / habilitation', 'controle-parapente' ), $s );
					self::text_row( $name, 'notify_email', __( 'E-mail de notification', 'controle-parapente' ), $s, 'email' );
					self::text_row( $name, 'reference_prefix', __( 'Préfixe des références', 'controle-parapente' ), $s );
					?>
				</table>

				<h2><?php esc_html_e( 'Paramètres techniques', 'controle-parapente' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::text_row( $name, 'validity_months', __( 'Validité d\'un contrôle (mois)', 'controle-parapente' ), $s, 'number' );
					self::text_row( $name, 'reminder_days', __( 'Rappel client avant échéance (jours, 0 = désactivé)', 'controle-parapente' ), $s, 'number' );
					self::text_row( $name, 'porosity_min', __( 'Porosité : seuil non navigable (secondes, en dessous)', 'controle-parapente' ), $s, 'number' );
					self::text_row( $name, 'porosity_warn', __( 'Porosité : seuil de vigilance (secondes, en dessous)', 'controle-parapente' ), $s, 'number' );
					self::text_row( $name, 'trim_tolerance', __( 'Calage : tolérance (± mm)', 'controle-parapente' ), $s, 'number' );
					?>
				</table>

				<h2><?php esc_html_e( 'Textes', 'controle-parapente' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::textarea_row( $name, 'form_intro', __( 'Texte d\'introduction du formulaire de demande', 'controle-parapente' ), $s );
					self::textarea_row( $name, 'certificate_footer', __( 'Mention en bas du certificat', 'controle-parapente' ), $s );
					?>
				</table>

				<h2><?php esc_html_e( 'Shortcodes', 'controle-parapente' ); ?></h2>
				<p><code>[cp_demande_controle]</code> — <?php esc_html_e( 'formulaire de demande de contrôle.', 'controle-parapente' ); ?></p>
				<p><code>[cp_suivi_controle]</code> — <?php esc_html_e( 'suivi d\'un contrôle par le client (référence + e-mail).', 'controle-parapente' ); ?></p>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private static function text_row( $name, $key, $label, $s, $type = 'text' ) {
		$id = 'cp-' . $key;
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="%3$s" id="%1$s" name="%4$s[%5$s]" value="%6$s" class="%7$s" /></td></tr>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( $key ),
			esc_attr( $s[ $key ] ),
			'number' === $type ? 'small-text' : 'regular-text'
		);
	}

	private static function textarea_row( $name, $key, $label, $s ) {
		$id = 'cp-' . $key;
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><textarea id="%1$s" name="%3$s[%4$s]" rows="4" class="large-text">%5$s</textarea></td></tr>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $name ),
			esc_attr( $key ),
			esc_textarea( $s[ $key ] )
		);
	}
}
