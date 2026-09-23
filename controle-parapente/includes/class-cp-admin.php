<?php
/**
 * Interface d'administration : fiche de contrôle, liste, filtres.
 *
 * @package ControleParapente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Admin {

	const NONCE = 'cp_save_controle';

	public static function init() {
		add_action( 'add_meta_boxes_' . CP_Post_Type::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . CP_Post_Type::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_filter( 'manage_' . CP_Post_Type::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . CP_Post_Type::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . CP_Post_Type::POST_TYPE . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_filters' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'wp_ajax_cp_search_previous', array( __CLASS__, 'ajax_search_previous' ) );
	}

	public static function is_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && CP_Post_Type::POST_TYPE === $screen->post_type;
	}

	public static function assets() {
		if ( ! self::is_screen() ) {
			return;
		}
		wp_enqueue_style( 'cp-admin', CP_URL . 'assets/css/admin.css', array(), CP_VERSION );
		wp_enqueue_script( 'cp-admin', CP_URL . 'assets/js/admin.js', array(), CP_VERSION, true );
		wp_enqueue_script( 'cp-trim', CP_URL . 'assets/js/trim.js', array(), CP_VERSION, true );
		wp_localize_script(
			'cp-admin',
			'cpAdmin',
			array(
				'porosityMin'    => (float) CP_Settings::get( 'porosity_min' ),
				'porosityWarn'   => (float) CP_Settings::get( 'porosity_warn' ),
				'trimTolerance'  => (float) CP_Settings::get( 'trim_tolerance' ),
				'validityMonths' => (int) CP_Settings::get( 'validity_months' ),
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'searchNonce'    => wp_create_nonce( 'cp_search' ),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Meta boxes                                                          */
	/* ------------------------------------------------------------------ */

	public static function meta_boxes( $post ) {
		$pt = CP_Post_Type::POST_TYPE;
		add_meta_box( 'cp-result', __( 'Statut & résultat', 'controle-parapente' ), array( __CLASS__, 'box_result' ), $pt, 'side', 'high' );
		add_meta_box( 'cp-pilot', __( 'Pilote / client', 'controle-parapente' ), array( __CLASS__, 'box_pilot' ), $pt, 'normal', 'high' );
		add_meta_box( 'cp-equipment', __( 'Équipement', 'controle-parapente' ), array( __CLASS__, 'box_equipment' ), $pt, 'normal', 'high' );
		add_meta_box( 'cp-request', __( 'Prestations demandées', 'controle-parapente' ), array( __CLASS__, 'box_request' ), $pt, 'normal', 'default' );
		add_meta_box( 'cp-porosity', __( 'Porosité du tissu', 'controle-parapente' ), array( __CLASS__, 'box_porosity' ), $pt, 'normal', 'default' );
		add_meta_box( 'cp-strength', __( 'Résistance tissu & suspentes', 'controle-parapente' ), array( __CLASS__, 'box_strength' ), $pt, 'normal', 'default' );
		add_meta_box( 'cp-trim', __( 'Calage (longueurs de suspentage)', 'controle-parapente' ), array( __CLASS__, 'box_trim' ), $pt, 'normal', 'default' );
		add_meta_box( 'cp-visual', __( 'Contrôle visuel', 'controle-parapente' ), array( __CLASS__, 'box_visual' ), $pt, 'normal', 'default' );
		add_meta_box( 'cp-conclusion', __( 'Travaux & conclusions', 'controle-parapente' ), array( __CLASS__, 'box_conclusion' ), $pt, 'normal', 'default' );
	}

	private static function data( $post ) {
		static $cache = array();
		if ( ! isset( $cache[ $post->ID ] ) ) {
			$cache[ $post->ID ] = CP_Controle::get( $post->ID );
		}
		return $cache[ $post->ID ];
	}

	private static function field( $name, $label, $value, $type = 'text', $attrs = '' ) {
		printf(
			'<p class="cp-field"><label for="cp-%1$s">%2$s</label><input type="%3$s" id="cp-%1$s" name="cp[%1$s]" value="%4$s" class="widefat" %5$s /></p>',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $value ),
			$attrs // phpcs:ignore WordPress.Security.EscapeOutput -- attributs statiques.
		);
	}

	private static function select( $name, $label, $value, $options ) {
		printf( '<p class="cp-field"><label for="cp-%1$s">%2$s</label><select id="cp-%1$s" name="cp[%1$s]" class="widefat">', esc_attr( $name ), esc_html( $label ) );
		foreach ( $options as $key => $text ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $value, $key, false ), esc_html( $text ) );
		}
		echo '</select></p>';
	}

	private static function textarea( $name, $label, $value, $rows = 3 ) {
		printf(
			'<p class="cp-field cp-field--wide"><label for="cp-%1$s">%2$s</label><textarea id="cp-%1$s" name="cp[%1$s]" rows="%3$d" class="widefat">%4$s</textarea></p>',
			esc_attr( $name ),
			esc_html( $label ),
			(int) $rows,
			esc_textarea( $value )
		);
	}

	public static function box_result( $post ) {
		$d = self::data( $post );
		wp_nonce_field( self::NONCE, 'cp_nonce' );

		// Nouvelle fiche créée à l'atelier : le client vient d'apporter son matériel.
		if ( 'auto-draft' === $post->post_status ) {
			$d['status']     = 'recue';
			$d['check_date'] = current_time( 'Y-m-d' );
		}

		if ( $d['reference'] ) {
			printf( '<p class="cp-reference">%s <strong>%s</strong></p>', esc_html__( 'Référence :', 'controle-parapente' ), esc_html( $d['reference'] ) );
		}

		self::select( 'status', __( 'Statut', 'controle-parapente' ), $d['status'], CP_Controle::statuses() );
		self::select( 'verdict', __( 'Verdict', 'controle-parapente' ), $d['verdict'], CP_Controle::verdicts() );
		self::field( 'check_date', __( 'Date du contrôle', 'controle-parapente' ), $d['check_date'], 'date' );
		self::field( 'next_date', __( 'Prochain contrôle', 'controle-parapente' ), $d['next_date'], 'date' );
		self::field( 'technician', __( 'Contrôleur', 'controle-parapente' ), $d['technician'] ? $d['technician'] : wp_get_current_user()->display_name );
		?>
		<p>
			<label>
				<input type="checkbox" name="cp_notify" value="1" checked="checked" />
				<?php esc_html_e( 'Prévenir le client par e-mail si le statut change', 'controle-parapente' ); ?>
			</label>
		</p>
		<?php
		if ( 'auto-draft' !== $post->post_status && $d['reference'] ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=cp_certificate&post=' . $post->ID ), 'cp_certificate_' . $post->ID );
			printf( '<p><a class="button" target="_blank" href="%s">%s</a></p>', esc_url( $url ), esc_html__( 'Imprimer la fiche / le certificat', 'controle-parapente' ) );
			if ( CP_Controle::has_certificate( $d['status'] ) ) {
				printf(
					'<p class="description">%s<br /><input type="text" readonly class="widefat" onclick="this.select()" value="%s" /></p>',
					esc_html__( 'Lien public du certificat (envoyé au client) :', 'controle-parapente' ),
					esc_attr( CP_Controle::public_certificate_url( $post->ID ) )
				);
			}
		}
	}

	public static function box_pilot( $post ) {
		$d = self::data( $post );
		?>
		<div class="cp-previous">
			<label for="cp-previous-search"><strong><?php esc_html_e( 'Client ou aile déjà venus ?', 'controle-parapente' ); ?></strong></label>
			<input type="search" id="cp-previous-search" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Nom, e-mail, n° de série, modèle, référence…', 'controle-parapente' ); ?>" />
			<span class="description"><?php esc_html_e( 'Reprend les coordonnées, l\'équipement et, pour la même aile, la structure et les cotes usine du calage.', 'controle-parapente' ); ?></span>
			<ul class="cp-previous-results" hidden></ul>
		</div>
		<?php
		echo '<div class="cp-grid">';
		self::field( 'pilot_name', __( 'Nom et prénom', 'controle-parapente' ), $d['pilot_name'] );
		self::field( 'email', __( 'E-mail', 'controle-parapente' ), $d['email'], 'email' );
		self::field( 'phone', __( 'Téléphone', 'controle-parapente' ), $d['phone'], 'tel' );
		self::textarea( 'address', __( 'Adresse (retour postal)', 'controle-parapente' ), $d['address'], 2 );
		echo '</div>';
	}

	public static function box_equipment( $post ) {
		$d = self::data( $post );
		echo '<div class="cp-grid">';
		self::select( 'equipment_type', __( 'Type', 'controle-parapente' ), $d['equipment_type'], CP_Controle::equipment_types() );
		self::field( 'brand', __( 'Marque', 'controle-parapente' ), $d['brand'] );
		self::field( 'model', __( 'Modèle', 'controle-parapente' ), $d['model'] );
		self::field( 'size', __( 'Taille', 'controle-parapente' ), $d['size'] );
		self::field( 'serial', __( 'N° de série', 'controle-parapente' ), $d['serial'] );
		self::field( 'year', __( 'Année de fabrication', 'controle-parapente' ), $d['year'], 'number', 'min="1980" max="2100"' );
		self::select( 'certification', __( 'Homologation', 'controle-parapente' ), $d['certification'], CP_Controle::certifications() );
		self::field( 'weight_range', __( 'PTV (kg)', 'controle-parapente' ), $d['weight_range'], 'text', 'placeholder="75-95"' );
		self::field( 'color', __( 'Couleurs', 'controle-parapente' ), $d['color'] );
		self::field( 'flight_hours', __( 'Heures de vol (approx.)', 'controle-parapente' ), $d['flight_hours'], 'number', 'min="0"' );
		self::field( 'last_check', __( 'Dernier contrôle', 'controle-parapente' ), $d['last_check'], 'date' );
		echo '</div>';
	}

	public static function box_request( $post ) {
		$d = self::data( $post );
		echo '<div class="cp-grid">';
		echo '<div class="cp-field"><span class="cp-label">' . esc_html__( 'Prestations', 'controle-parapente' ) . '</span>';
		foreach ( CP_Controle::services() as $key => $label ) {
			printf(
				'<label class="cp-check"><input type="checkbox" name="cp[services][]" value="%s"%s /> %s</label>',
				esc_attr( $key ),
				checked( in_array( $key, (array) $d['services'], true ), true, false ),
				esc_html( $label )
			);
		}
		echo '</div>';
		self::select( 'drop_off', __( 'Mode de dépôt', 'controle-parapente' ), $d['drop_off'], CP_Controle::drop_off_modes() );
		self::textarea( 'client_notes', __( 'Remarques du client (incidents, dommages connus…)', 'controle-parapente' ), $d['client_notes'] );
		echo '</div>';
	}

	/**
	 * Tableau de lignes répétables.
	 *
	 * @param string $key     Clé des données.
	 * @param array  $columns Colonne => [libellé, type, attributs].
	 * @param array  $rows    Lignes existantes.
	 * @param string $extra   Libellé d'une colonne calculée (JS) ou ''.
	 */
	private static function repeatable( $key, $columns, $rows, $extra = '' ) {
		printf( '<table class="widefat striped cp-repeat" data-key="%s"><thead><tr>', esc_attr( $key ) );
		foreach ( $columns as $col ) {
			printf( '<th>%s</th>', esc_html( $col[0] ) );
		}
		if ( $extra ) {
			printf( '<th>%s</th>', esc_html( $extra ) );
		}
		echo '<th class="cp-col-action"></th></tr></thead><tbody>';

		$render_row = static function ( $index, $row ) use ( $key, $columns, $extra ) {
			echo '<tr>';
			foreach ( $columns as $field => $col ) {
				printf(
					'<td><input type="%1$s" name="cp[%2$s][%3$s][%4$s]" value="%5$s" data-field="%4$s" class="widefat" %6$s /></td>',
					esc_attr( $col[1] ),
					esc_attr( $key ),
					esc_attr( $index ),
					esc_attr( $field ),
					esc_attr( isset( $row[ $field ] ) ? $row[ $field ] : '' ),
					isset( $col[2] ) ? $col[2] : '' // phpcs:ignore WordPress.Security.EscapeOutput -- attributs statiques.
				);
			}
			if ( $extra ) {
				echo '<td class="cp-computed"></td>';
			}
			printf( '<td class="cp-col-action"><button type="button" class="button-link cp-remove-row" aria-label="%s">✕</button></td>', esc_attr__( 'Supprimer la ligne', 'controle-parapente' ) );
			echo '</tr>';
		};

		foreach ( array_values( $rows ) as $i => $row ) {
			$render_row( $i, $row );
		}
		echo '</tbody></table>';

		echo '<template class="cp-row-template">';
		$render_row( '__i__', array() );
		echo '</template>';
		printf( '<p><button type="button" class="button cp-add-row">%s</button></p>', esc_html__( '+ Ajouter une ligne', 'controle-parapente' ) );
	}

	public static function box_porosity( $post ) {
		$d = self::data( $post );
		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: seuil non navigable, 2: seuil de vigilance. */
					__( 'Temps mesuré au porosimètre (secondes). Non navigable sous %1$s s, vigilance sous %2$s s.', 'controle-parapente' ),
					CP_Settings::get( 'porosity_min' ),
					CP_Settings::get( 'porosity_warn' )
				)
			)
		);
		self::repeatable(
			'porosity',
			array(
				'zone'  => array( __( 'Zone / point de mesure', 'controle-parapente' ), 'text' ),
				'value' => array( __( 'Temps (s)', 'controle-parapente' ), 'number', 'step="0.1" min="0"' ),
			),
			$d['porosity'],
			__( 'Évaluation', 'controle-parapente' )
		);
	}

	public static function box_strength( $post ) {
		$d = self::data( $post );
		echo '<h4>' . esc_html__( 'Résistance du tissu (Bettsomètre)', 'controle-parapente' ) . '</h4><div class="cp-grid">';
		self::field( 'fabric_strength', __( 'Valeur / remarque (ex. 600 g, 5 mm)', 'controle-parapente' ), $d['fabric_strength'] );
		self::select(
			'fabric_result',
			__( 'Résultat', 'controle-parapente' ),
			$d['fabric_result'],
			array(
				''    => '—',
				'ok'  => __( 'Conforme', 'controle-parapente' ),
				'nok' => __( 'Non conforme', 'controle-parapente' ),
			)
		);
		echo '</div><h4>' . esc_html__( 'Résistance des suspentes (test de rupture)', 'controle-parapente' ) . '</h4>';
		self::repeatable(
			'lines',
			array(
				'line'     => array( __( 'Suspente', 'controle-parapente' ), 'text' ),
				'measured' => array( __( 'Rupture mesurée (daN)', 'controle-parapente' ), 'number', 'step="0.1" min="0"' ),
				'minimum'  => array( __( 'Minimum requis (daN)', 'controle-parapente' ), 'number', 'step="0.1" min="0"' ),
			),
			$d['lines'],
			__( 'Évaluation', 'controle-parapente' )
		);
	}

	public static function box_trim( $post ) {
		$d = self::data( $post );
		CP_Trim::render_admin_box( $d['trim'], $d['trim_adjusted'] );
	}

	public static function box_visual( $post ) {
		$d      = self::data( $post );
		$states = CP_Controle::visual_states();
		echo '<table class="widefat striped cp-visual"><thead><tr><th>' . esc_html__( 'Élément', 'controle-parapente' ) . '</th><th>' . esc_html__( 'État', 'controle-parapente' ) . '</th><th>' . esc_html__( 'Commentaire', 'controle-parapente' ) . '</th></tr></thead><tbody>';
		foreach ( CP_Controle::visual_items() as $key => $label ) {
			$state   = isset( $d['visual'][ $key ]['state'] ) ? $d['visual'][ $key ]['state'] : '';
			$comment = isset( $d['visual'][ $key ]['comment'] ) ? $d['visual'][ $key ]['comment'] : '';
			echo '<tr><td>' . esc_html( $label ) . '</td><td>';
			printf( '<select name="cp[visual][%s][state]" class="cp-state">', esc_attr( $key ) );
			foreach ( $states as $value => $text ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $state, $value, false ), esc_html( $text ) );
			}
			echo '</select></td>';
			printf( '<td><input type="text" class="widefat" name="cp[visual][%s][comment]" value="%s" /></td></tr>', esc_attr( $key ), esc_attr( $comment ) );
		}
		echo '</tbody></table>';
		printf( '<p><button type="button" class="button cp-all-ok">%s</button></p>', esc_html__( 'Tout marquer « Bon état » (champs vides)', 'controle-parapente' ) );
	}

	public static function box_conclusion( $post ) {
		$d = self::data( $post );
		self::textarea( 'repairs', __( 'Réparations / pièces remplacées', 'controle-parapente' ), $d['repairs'] );
		self::textarea( 'comments', __( 'Observations (visibles par le client et sur le certificat)', 'controle-parapente' ), $d['comments'], 4 );
		self::textarea( 'internal_notes', __( 'Notes internes (non communiquées)', 'controle-parapente' ), $d['internal_notes'] );
	}

	/* ------------------------------------------------------------------ */
	/* Enregistrement                                                      */
	/* ------------------------------------------------------------------ */

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['cp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cp_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw        = isset( $_POST['cp'] ) && is_array( $_POST['cp'] ) ? wp_unslash( $_POST['cp'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nettoyé par CP_Controle::sanitize().
		$data       = CP_Controle::sanitize( $raw, true );
		$old_status = (string) get_post_meta( $post_id, CP_Controle::META_STATUS, true );

		if ( '' === $data['next_date'] && '' !== $data['check_date'] && '' !== $data['verdict'] && 'non_navigable' !== $data['verdict'] ) {
			$data['next_date'] = CP_Controle::suggested_next_date( $data['check_date'] );
		}

		CP_Controle::save( $post_id, $data );
		self::sync_title( $post_id );

		if ( $old_status && $old_status !== $data['status'] && ! empty( $_POST['cp_notify'] ) ) {
			CP_Emails::status_changed( $post_id );
		}
	}

	/**
	 * Le titre sert à la recherche native : référence, équipement, n° série, pilote.
	 */
	public static function sync_title( $post_id ) {
		$d     = CP_Controle::get( $post_id );
		$parts = array_filter(
			array(
				$d['reference'],
				CP_Controle::equipment_label( $d ),
				$d['serial'] ? '#' . $d['serial'] : '',
				$d['pilot_name'],
			)
		);
		$title = implode( ' — ', $parts );

		remove_action( 'save_post_' . CP_Post_Type::POST_TYPE, array( __CLASS__, 'save' ), 10 );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_title'  => $title,
				'post_name'   => sanitize_title( $d['reference'] ),
				'post_status' => in_array( get_post_status( $post_id ), array( 'auto-draft', 'draft' ), true ) ? 'publish' : get_post_status( $post_id ),
			)
		);
		add_action( 'save_post_' . CP_Post_Type::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Recherche d'un contrôle précédent pour pré-remplir une nouvelle fiche.
	 */
	public static function ajax_search_previous() {
		check_ajax_referer( 'cp_search', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}
		$term    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$exclude = isset( $_GET['exclude'] ) ? absint( $_GET['exclude'] ) : 0;
		if ( mb_strlen( $term ) < 2 ) {
			wp_send_json_success( array() );
		}

		// Le titre contient référence, équipement, n° de série et pilote ; l'e-mail est en méta.
		$ids = array_unique(
			array_merge(
				get_posts(
					array(
						'post_type'      => CP_Post_Type::POST_TYPE,
						'post_status'    => 'publish',
						's'              => $term,
						'posts_per_page' => 10,
						'fields'         => 'ids',
						'post__not_in'   => array( $exclude ),
					)
				),
				get_posts(
					array(
						'post_type'      => CP_Post_Type::POST_TYPE,
						'post_status'    => 'publish',
						'posts_per_page' => 10,
						'fields'         => 'ids',
						'post__not_in'   => array( $exclude ),
						'meta_query'     => array( array( 'key' => CP_Controle::META_EMAIL, 'value' => strtolower( $term ), 'compare' => 'LIKE' ) ),
					)
				)
			)
		);
		rsort( $ids );

		$keys    = array( 'pilot_name', 'email', 'phone', 'address', 'equipment_type', 'brand', 'model', 'size', 'serial', 'year', 'certification', 'weight_range', 'color', 'flight_hours' );
		$results = array();
		foreach ( array_slice( $ids, 0, 10 ) as $id ) {
			$d      = CP_Controle::get( $id );
			$fields = array_intersect_key( $d, array_flip( $keys ) );
			$fields['last_check'] = $d['check_date'];
			$results[] = array(
				'label'  => get_the_title( $id ) . ( $d['check_date'] ? ' (' . CP_Controle::format_date( $d['check_date'] ) . ')' : '' ),
				'fields' => $fields,
				'trim'   => array(
					'sides'     => $d['trim']['sides'],
					'offset'    => $d['trim']['offset'],
					'structure' => $d['trim']['structure'],
					'factory'   => (object) $d['trim']['factory'],
				),
			);
		}
		wp_send_json_success( $results );
	}

	/* ------------------------------------------------------------------ */
	/* Liste                                                               */
	/* ------------------------------------------------------------------ */

	public static function columns( $columns ) {
		return array(
			'cb'           => $columns['cb'],
			'title'        => __( 'Contrôle', 'controle-parapente' ),
			'cp_pilot'     => __( 'Pilote', 'controle-parapente' ),
			'cp_equipment' => __( 'Équipement', 'controle-parapente' ),
			'cp_status'    => __( 'Statut', 'controle-parapente' ),
			'cp_verdict'   => __( 'Verdict', 'controle-parapente' ),
			'cp_next'      => __( 'Prochain contrôle', 'controle-parapente' ),
			'date'         => __( 'Créé le', 'controle-parapente' ),
		);
	}

	public static function column_content( $column, $post_id ) {
		$d = CP_Controle::get( $post_id );
		switch ( $column ) {
			case 'cp_pilot':
				echo esc_html( $d['pilot_name'] );
				if ( $d['email'] ) {
					printf( '<br /><a href="mailto:%1$s">%1$s</a>', esc_attr( $d['email'] ) );
				}
				break;
			case 'cp_equipment':
				echo esc_html( CP_Controle::equipment_label( $d ) );
				if ( $d['serial'] ) {
					echo '<br /><small>' . esc_html( $d['serial'] ) . '</small>';
				}
				break;
			case 'cp_status':
				$statuses = CP_Controle::statuses();
				printf( '<span class="cp-badge cp-status-%s">%s</span>', esc_attr( $d['status'] ), esc_html( $statuses[ $d['status'] ] ?? $d['status'] ) );
				break;
			case 'cp_verdict':
				if ( $d['verdict'] ) {
					$verdicts = CP_Controle::verdicts();
					printf( '<span class="cp-badge cp-verdict-%s">%s</span>', esc_attr( $d['verdict'] ), esc_html( $verdicts[ $d['verdict'] ] ?? '' ) );
				}
				break;
			case 'cp_next':
				if ( $d['next_date'] ) {
					$overdue = $d['next_date'] < current_time( 'Y-m-d' );
					printf( '<span class="%s">%s</span>', $overdue ? 'cp-overdue' : '', esc_html( CP_Controle::format_date( $d['next_date'] ) ) );
				}
				break;
		}
	}

	public static function sortable_columns( $columns ) {
		$columns['cp_next']   = 'cp_next';
		$columns['cp_status'] = 'cp_status';
		return $columns;
	}

	public static function filters( $post_type ) {
		if ( CP_Post_Type::POST_TYPE !== $post_type ) {
			return;
		}
		$current = isset( $_GET['cp_status'] ) ? sanitize_key( wp_unslash( $_GET['cp_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<select name="cp_status"><option value="">' . esc_html__( 'Tous les statuts', 'controle-parapente' ) . '</option>';
		foreach ( CP_Controle::statuses() as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $label ) );
		}
		echo '</select>';

		$due = isset( $_GET['cp_due'] ) ? sanitize_key( wp_unslash( $_GET['cp_due'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<select name="cp_due"><option value="">' . esc_html__( 'Toutes les échéances', 'controle-parapente' ) . '</option>';
		foreach ( array(
			'overdue' => __( 'Échéance dépassée', 'controle-parapente' ),
			'soon'    => __( 'Échéance dans les 60 jours', 'controle-parapente' ),
		) as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $due, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	public static function apply_filters( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || CP_Post_Type::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$meta_query = (array) $query->get( 'meta_query' );

		if ( ! empty( $_GET['cp_status'] ) ) {
			$meta_query[] = array(
				'key'   => CP_Controle::META_STATUS,
				'value' => sanitize_key( wp_unslash( $_GET['cp_status'] ) ),
			);
		}
		if ( ! empty( $_GET['cp_due'] ) ) {
			$today = current_time( 'Y-m-d' );
			if ( 'overdue' === $_GET['cp_due'] ) {
				$meta_query[] = array( 'key' => CP_Controle::META_NEXT_DATE, 'value' => $today, 'compare' => '<', 'type' => 'DATE' );
			} elseif ( 'soon' === $_GET['cp_due'] ) {
				$meta_query[] = array( 'key' => CP_Controle::META_NEXT_DATE, 'value' => array( $today, gmdate( 'Y-m-d', strtotime( $today . ' +60 days' ) ) ), 'compare' => 'BETWEEN', 'type' => 'DATE' );
			}
		}
		// phpcs:enable

		if ( $meta_query ) {
			$query->set( 'meta_query', $meta_query );
		}

		$orderby = $query->get( 'orderby' );
		if ( 'cp_next' === $orderby ) {
			$query->set( 'meta_key', CP_Controle::META_NEXT_DATE );
			$query->set( 'orderby', 'meta_value' );
		} elseif ( 'cp_status' === $orderby ) {
			$query->set( 'meta_key', CP_Controle::META_STATUS );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( CP_Post_Type::POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'] );
		$url                   = wp_nonce_url( admin_url( 'admin-post.php?action=cp_certificate&post=' . $post->ID ), 'cp_certificate_' . $post->ID );
		$actions['cp_certif'] = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $url ), esc_html__( 'Imprimer', 'controle-parapente' ) );
		return $actions;
	}
}
