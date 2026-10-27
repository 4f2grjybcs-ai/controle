<?php
/**
 * Shortcodes publics : demande de contrôle et suivi.
 *
 * @package ControleParapente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Shortcodes {

	const NONCE      = 'cp_public_request';
	const RATE_LIMIT = 5; // Demandes par heure et par adresse IP.
	const RATE_LIMIT_DEPOT = 40; // Dépôts sur place : tous les clients partagent souvent le Wi-Fi de l'atelier.

	/** @var array Erreurs du formulaire de demande. */
	private static $errors = array();

	/** @var array Valeurs saisies (réaffichées en cas d'erreur). */
	private static $values = array();

	/** Réponses saisies aux questions supplémentaires (réaffichées en cas d'erreur). */
	private static $answers = array();

	public static function init() {
		add_shortcode( 'cp_demande_controle', array( __CLASS__, 'request_form' ) );
		add_shortcode( 'cp_depot', array( __CLASS__, 'depot_form' ) );
		add_shortcode( 'cp_suivi_controle', array( __CLASS__, 'tracking' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_request' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'cp-public', CP_URL . 'assets/css/public.css', array(), cp_asset_ver( 'assets/css/public.css' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Demande de contrôle                                                 */
	/* ------------------------------------------------------------------ */

	public static function handle_request() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['cp_action'] ) || 'demande' !== $_POST['cp_action'] ) {
			return;
		}

		$nonce = isset( $_POST['cp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			self::$errors[] = __( 'La session a expiré, merci de renvoyer le formulaire.', 'controle-parapente' );
			return;
		}

		// Pot de miel anti-robots : champ caché qui doit rester vide.
		if ( ! empty( $_POST['cp_website'] ) ) {
			return;
		}

		$depot        = isset( $_POST['cp_mode'] ) && 'depot' === $_POST['cp_mode'];
		$raw          = isset( $_POST['cp'] ) && is_array( $_POST['cp'] ) ? wp_unslash( $_POST['cp'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nettoyé ci-dessous.
		$data         = CP_Controle::sanitize( $raw, false );
		self::$values = $data;

		// Questions supplémentaires réglables.
		$answers_raw = isset( $_POST['cp_q'] ) && is_array( $_POST['cp_q'] ) ? wp_unslash( $_POST['cp_q'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nettoyé ci-dessous.
		$data['form_answers'] = array();
		foreach ( self::questions() as $i => $q ) {
			$answer = isset( $answers_raw[ $i ] ) ? sanitize_text_field( $answers_raw[ $i ] ) : '';
			if ( $q['options'] && '' !== $answer && ! in_array( $answer, $q['options'], true ) ) {
				$answer = '';
			}
			if ( 'date' === $q['type'] ) {
				$answer = CP_Controle::sanitize_date( $answer );
				$answer = $answer ? CP_Controle::format_date( $answer ) : '';
			}
			if ( $q['required'] && '' === $answer ) {
				/* translators: %s: question */
				self::$errors[] = sprintf( __( 'Merci de répondre à la question : %s', 'controle-parapente' ), $q['label'] );
			}
			$data['form_answers'][] = array( 'q' => $q['label'], 'a' => $answer );
			self::$answers[ $i ]   = isset( $answers_raw[ $i ] ) ? sanitize_text_field( $answers_raw[ $i ] ) : '';
		}

		if ( '' === $data['pilot_name'] ) {
			self::$errors[] = __( 'Merci d\'indiquer votre nom.', 'controle-parapente' );
		}
		if ( ! is_email( $data['email'] ) ) {
			self::$errors[] = __( 'Merci d\'indiquer une adresse e-mail valide.', 'controle-parapente' );
		}
		if ( '' === $data['brand'] || '' === $data['model'] ) {
			self::$errors[] = __( 'Merci d\'indiquer la marque et le modèle de l\'équipement.', 'controle-parapente' );
		}
		if ( CP_Settings::get( 'form_show_services' ) && empty( $data['services'] ) ) {
			self::$errors[] = __( 'Merci de choisir au moins une prestation.', 'controle-parapente' );
		}
		if ( empty( $_POST['cp_consent'] ) ) {
			self::$errors[] = __( 'Merci d\'accepter l\'utilisation de vos données pour le traitement de la demande.', 'controle-parapente' );
		}

		$rate_key = 'cp_rate_' . ( $depot ? 'depot_' : '' ) . md5( self::client_ip() );
		$count    = (int) get_transient( $rate_key );
		if ( $count >= ( $depot ? self::RATE_LIMIT_DEPOT : self::RATE_LIMIT ) ) {
			self::$errors[] = __( 'Trop de demandes envoyées. Merci de réessayer plus tard ou de nous contacter directement.', 'controle-parapente' );
		}

		if ( self::$errors ) {
			return;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => CP_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => __( 'Demande en ligne', 'controle-parapente' ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			self::$errors[] = __( 'Une erreur est survenue, merci de réessayer.', 'controle-parapente' );
			return;
		}

		// Dépôt sur place (QR code) : l'aile est déjà à l'atelier.
		$data['status'] = $depot ? 'recue' : 'demande';
		$data['source'] = $depot ? 'qr' : 'web';
		if ( $depot ) {
			$data['drop_off'] = 'atelier';
		}
		CP_Controle::save( $post_id, $data );
		CP_Admin::sync_title( $post_id );
		if ( $depot ) {
			update_post_meta( $post_id, CP_Atelier::META_NEW, '1' );
		}
		set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

		CP_Emails::new_request( $post_id );

		$reference = get_post_meta( $post_id, CP_Controle::META_REFERENCE, true );
		$back      = is_singular() ? get_permalink( get_queried_object_id() ) : wp_get_referer();
		wp_safe_redirect( add_query_arg( 'cp_envoye', rawurlencode( $reference ), $back ? $back : home_url( '/' ) ) );
		exit;
	}

	/**
	 * Questions supplémentaires (réglages) : [ [ label, type, options, required ] ].
	 */
	public static function questions() {
		$out = array();
		foreach ( CP_Settings::lines( 'form_questions' ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( '' === $parts[0] ) {
				continue;
			}
			$type    = isset( $parts[1] ) ? $parts[1] : 'texte';
			$options = array();
			if ( preg_match( '/^choix\s*:?\s*(.*)$/iu', $type, $m ) ) {
				$options = array_values( array_filter( array_map( 'trim', preg_split( '#/|,#', $m[1] ) ), 'strlen' ) );
				$type    = 'choix';
			} elseif ( preg_match( '/^oui/i', $type ) ) {
				$options = array( __( 'Oui', 'controle-parapente' ), __( 'Non', 'controle-parapente' ) );
				$type    = 'oui-non';
			} elseif ( ! in_array( $type, array( 'nombre', 'date' ), true ) ) {
				$type = 'texte';
			}
			$out[] = array(
				'label'    => $parts[0],
				'type'     => $type,
				'options'  => $options,
				'required' => isset( $parts[2] ) && 0 === stripos( $parts[2], 'oblig' ),
			);
		}
		return $out;
	}

	private static function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	private static function value( $key ) {
		return isset( self::$values[ $key ] ) ? self::$values[ $key ] : '';
	}

	private static function input( $key, $label, $type = 'text', $required = false, $attrs = '' ) {
		printf(
			'<p class="cp-form__field"><label for="cp-f-%1$s">%2$s%3$s</label><input type="%4$s" id="cp-f-%1$s" name="cp[%1$s]" value="%5$s"%6$s %7$s /></p>',
			esc_attr( $key ),
			esc_html( $label ),
			$required ? ' <span class="cp-required">*</span>' : '',
			esc_attr( $type ),
			esc_attr( self::value( $key ) ),
			$required ? ' required' : '',
			$attrs // phpcs:ignore WordPress.Security.EscapeOutput -- attributs statiques.
		);
	}

	private static function select( $key, $label, $options, $default = '' ) {
		$current = self::value( $key ) ? self::value( $key ) : $default;
		printf( '<p class="cp-form__field"><label for="cp-f-%1$s">%2$s</label><select id="cp-f-%1$s" name="cp[%1$s]">', esc_attr( $key ), esc_html( $label ) );
		foreach ( $options as $value => $text ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $text ) );
		}
		echo '</select></p>';
	}

	/**
	 * Formulaire de dépôt sur place (page ouverte par le QR code de l'atelier).
	 */
	public static function depot_form() {
		return self::request_form( array( 'mode' => 'depot' ) );
	}

	public static function request_form( $atts = array() ) {
		$depot = is_array( $atts ) && isset( $atts['mode'] ) && 'depot' === $atts['mode'];
		wp_enqueue_style( 'cp-public' );
		ob_start();

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! empty( $_GET['cp_envoye'] ) ) {
			$reference = sanitize_text_field( wp_unslash( $_GET['cp_envoye'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="cp-notice cp-notice--success"><p>';
			echo wp_kses( str_replace( '{reference}', '<strong>' . esc_html( $reference ) . '</strong>', esc_html( CP_Settings::get( $depot ? 'depot_success' : 'form_success' ) ) ), array( 'strong' => array() ) );
			echo '</p></div>';
			return ob_get_clean();
		}

		if ( self::$errors ) {
			echo '<div class="cp-notice cp-notice--error"><ul>';
			foreach ( self::$errors as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul></div>';
		}

		$intro = CP_Settings::get( $depot ? 'depot_intro' : 'form_intro' );
		if ( $intro ) {
			echo '<div class="cp-intro">' . wp_kses_post( wpautop( $intro ) ) . '</div>';
		}

		$services = self::$values ? (array) self::value( 'services' ) : array_slice( array_keys( CP_Controle::services() ), 0, 1 );
		?>
		<form class="cp-form" method="post" action="">
			<?php wp_nonce_field( self::NONCE, 'cp_nonce' ); ?>
			<input type="hidden" name="cp_action" value="demande" />
			<input type="hidden" name="cp_mode" value="<?php echo esc_attr( $depot ? 'depot' : 'demande' ); ?>" />
			<p class="cp-hp" aria-hidden="true"><label>Website <input type="text" name="cp_website" tabindex="-1" autocomplete="off" /></label></p>

			<fieldset>
				<legend><?php echo esc_html( CP_Settings::get( 'form_title_contact' ) ); ?></legend>
				<div class="cp-form__grid">
					<?php
					self::input( 'pilot_name', __( 'Nom et prénom', 'controle-parapente' ), 'text', true, 'autocomplete="name"' );
					self::input( 'email', __( 'E-mail', 'controle-parapente' ), 'email', true, 'autocomplete="email"' );
					self::input( 'phone', __( 'Téléphone', 'controle-parapente' ), 'tel', false, 'autocomplete="tel"' );
					?>
				</div>
				<?php if ( CP_Settings::get( 'form_show_address' ) ) : ?>
				<p class="cp-form__field">
					<label for="cp-f-address"><?php esc_html_e( 'Adresse (pour un retour par la poste)', 'controle-parapente' ); ?></label>
					<textarea id="cp-f-address" name="cp[address]" rows="2"><?php echo esc_textarea( self::value( 'address' ) ); ?></textarea>
				</p>
				<?php endif; ?>
			</fieldset>

			<fieldset>
				<legend><?php echo esc_html( CP_Settings::get( 'form_title_equipment' ) ); ?></legend>
				<div class="cp-form__grid">
					<?php
					self::select( 'equipment_type', __( 'Type', 'controle-parapente' ), CP_Controle::equipment_types(), 'parapente' );
					self::input( 'brand', __( 'Marque', 'controle-parapente' ), 'text', true );
					self::input( 'model', __( 'Modèle', 'controle-parapente' ), 'text', true );
					self::input( 'size', __( 'Taille', 'controle-parapente' ) );
					self::input( 'serial', __( 'N° de série', 'controle-parapente' ) );
					self::input( 'color', __( 'Couleurs', 'controle-parapente' ) );
					if ( CP_Settings::get( 'form_show_history' ) ) {
						self::input( 'year', __( 'Année de fabrication', 'controle-parapente' ), 'number', false, 'min="1980" max="2100"' );
						self::select( 'certification', __( 'Homologation', 'controle-parapente' ), CP_Controle::certifications() );
						self::input( 'flight_hours', __( 'Heures de vol (approx.)', 'controle-parapente' ), 'number', false, 'min="0" inputmode="numeric"' );
						self::input( 'last_check', __( 'Date du dernier contrôle', 'controle-parapente' ), 'date' );
					}
					?>
				</div>
			</fieldset>

			<fieldset>
				<legend><?php echo esc_html( CP_Settings::get( 'form_title_services' ) ); ?></legend>
				<?php if ( CP_Settings::get( 'form_show_services' ) ) : ?>
					<?php foreach ( CP_Controle::services() as $key => $label ) : ?>
						<label class="cp-form__check">
							<input type="checkbox" name="cp[services][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $services, true ) ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				<?php endif; ?>
				<?php if ( ! $depot ) : ?>
					<?php self::select( 'drop_off', __( 'Comment nous confiez-vous le matériel ?', 'controle-parapente' ), CP_Controle::drop_off_modes(), 'atelier' ); ?>
				<?php endif; ?>
				<p class="cp-form__field">
					<label for="cp-f-notes"><?php echo esc_html( CP_Settings::get( 'form_notes_label' ) ); ?></label>
					<textarea id="cp-f-notes" name="cp[client_notes]" rows="4"><?php echo esc_textarea( self::value( 'client_notes' ) ); ?></textarea>
				</p>
			</fieldset>

			<?php $questions = self::questions(); ?>
			<?php if ( $questions ) : ?>
				<fieldset>
					<legend><?php echo esc_html( CP_Settings::get( 'form_title_questions' ) ); ?></legend>
					<?php foreach ( $questions as $i => $q ) : ?>
						<?php
						$current = isset( self::$answers[ $i ] ) ? self::$answers[ $i ] : '';
						$label   = esc_html( $q['label'] ) . ( $q['required'] ? ' <span class="cp-required">*</span>' : '' );
						?>
						<div class="cp-form__field cp-question">
							<?php if ( $q['options'] ) : ?>
								<span class="cp-question__label"><?php echo $label; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
								<span class="cp-choices">
									<?php foreach ( $q['options'] as $opt ) : ?>
										<label class="cp-choice"><input type="radio" name="cp_q[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $opt ); ?>" <?php checked( $current, $opt ); ?> <?php echo $q['required'] ? 'required' : ''; ?> /> <span><?php echo esc_html( $opt ); ?></span></label>
									<?php endforeach; ?>
								</span>
							<?php else : ?>
								<label for="cp-q-<?php echo (int) $i; ?>"><?php echo $label; // phpcs:ignore WordPress.Security.EscapeOutput ?></label>
								<input id="cp-q-<?php echo (int) $i; ?>" name="cp_q[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $current ); ?>" type="<?php echo esc_attr( 'nombre' === $q['type'] ? 'number' : ( 'date' === $q['type'] ? 'date' : 'text' ) ); ?>" <?php echo $q['required'] ? 'required' : ''; ?> />
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>

			<p class="cp-form__check">
				<label>
					<input type="checkbox" name="cp_consent" value="1" required />
					<?php echo esc_html( CP_Settings::get( 'form_consent' ) ); ?>
				</label>
			</p>

			<p><button type="submit" class="cp-button"><?php echo esc_html( $depot ? __( 'Enregistrer ma voile', 'controle-parapente' ) : __( 'Envoyer la demande', 'controle-parapente' ) ); ?></button></p>
		</form>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* Suivi                                                               */
	/* ------------------------------------------------------------------ */

	public static function tracking() {
		wp_enqueue_style( 'cp-public' );
		ob_start();

		// phpcs:disable WordPress.Security.NonceVerification -- lecture seule, protégée par référence + e-mail.
		$reference = isset( $_POST['cp_ref'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['cp_ref'] ) ) ) : '';
		$email     = isset( $_POST['cp_email'] ) ? sanitize_email( wp_unslash( $_POST['cp_email'] ) ) : '';
		$submitted = isset( $_POST['cp_action'] ) && 'suivi' === $_POST['cp_action'];
		// phpcs:enable
		?>
		<form class="cp-form cp-form--inline" method="post" action="">
			<input type="hidden" name="cp_action" value="suivi" />
			<p class="cp-form__field">
				<label for="cp-t-ref"><?php esc_html_e( 'Référence', 'controle-parapente' ); ?></label>
				<input type="text" id="cp-t-ref" name="cp_ref" value="<?php echo esc_attr( $reference ); ?>" placeholder="CP-2026-0001" required />
			</p>
			<p class="cp-form__field">
				<label for="cp-t-email"><?php esc_html_e( 'E-mail', 'controle-parapente' ); ?></label>
				<input type="email" id="cp-t-email" name="cp_email" value="<?php echo esc_attr( $email ); ?>" required />
			</p>
			<p><button type="submit" class="cp-button"><?php esc_html_e( 'Suivre mon contrôle', 'controle-parapente' ); ?></button></p>
		</form>
		<?php
		if ( $submitted ) {
			$post_id = ( $reference && is_email( $email ) ) ? CP_Controle::find_by_reference( $reference, $email ) : 0;
			if ( ! $post_id ) {
				echo '<div class="cp-notice cp-notice--error"><p>' . esc_html__( 'Aucun contrôle ne correspond à cette référence et cette adresse e-mail.', 'controle-parapente' ) . '</p></div>';
			} else {
				self::render_tracking( $post_id );
			}
		}
		return ob_get_clean();
	}

	private static function render_tracking( $post_id ) {
		$d        = CP_Controle::get( $post_id );
		$statuses = CP_Controle::statuses();
		$verdicts = CP_Controle::verdicts();
		$steps    = array( 'demande', 'recue', 'en_cours', 'terminee', 'rendue' );
		$current  = array_search( $d['status'], $steps, true );
		?>
		<div class="cp-tracking">
			<h3><?php echo esc_html( $d['reference'] . ' — ' . CP_Controle::equipment_label( $d ) ); ?></h3>

			<?php if ( 'annulee' === $d['status'] || 'attente' === $d['status'] ) : ?>
				<p class="cp-badge cp-status-<?php echo esc_attr( $d['status'] ); ?>"><?php echo esc_html( $statuses[ $d['status'] ] ); ?></p>
			<?php endif; ?>

			<?php if ( 'annulee' !== $d['status'] ) : ?>
				<ol class="cp-steps">
					<?php foreach ( $steps as $i => $step ) : ?>
						<?php
						$class = '';
						if ( false !== $current ) {
							$class = $i < $current ? 'is-done' : ( $i === $current ? 'is-current' : '' );
						} elseif ( 'attente' === $d['status'] ) {
							$class = $i <= 2 ? ( 2 === $i ? 'is-current' : 'is-done' ) : '';
						}
						?>
						<li class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $statuses[ $step ] ); ?></li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>

			<dl class="cp-details">
				<?php if ( $d['serial'] ) : ?>
					<dt><?php esc_html_e( 'N° de série', 'controle-parapente' ); ?></dt><dd><?php echo esc_html( $d['serial'] ); ?></dd>
				<?php endif; ?>
				<?php if ( CP_Controle::has_certificate( $d['status'] ) ) : ?>
					<dt><?php esc_html_e( 'Date du contrôle', 'controle-parapente' ); ?></dt><dd><?php echo esc_html( CP_Controle::format_date( $d['check_date'] ) ); ?></dd>
					<?php if ( $d['verdict'] ) : ?>
						<dt><?php esc_html_e( 'Résultat', 'controle-parapente' ); ?></dt>
						<dd><span class="cp-badge cp-verdict-<?php echo esc_attr( $d['verdict'] ); ?>"><?php echo esc_html( $verdicts[ $d['verdict'] ] ); ?></span></dd>
					<?php endif; ?>
					<?php if ( $d['next_date'] ) : ?>
						<dt><?php esc_html_e( 'Prochain contrôle', 'controle-parapente' ); ?></dt><dd><?php echo esc_html( CP_Controle::format_date( $d['next_date'] ) ); ?></dd>
					<?php endif; ?>
				<?php endif; ?>
			</dl>

			<?php if ( CP_Controle::has_certificate( $d['status'] ) ) : ?>
				<?php if ( $d['comments'] ) : ?>
					<div class="cp-comments"><?php echo wp_kses_post( wpautop( esc_html( $d['comments'] ) ) ); ?></div>
				<?php endif; ?>
				<p><a class="cp-button" target="_blank" rel="noopener" href="<?php echo esc_url( CP_Controle::public_certificate_url( $post_id ) ); ?>"><?php esc_html_e( 'Voir la fiche de contrôle', 'controle-parapente' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
