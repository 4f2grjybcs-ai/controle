<?php
/**
 * Calage : structure du suspentage (groupes identifiés par couleur), mesures usine,
 * mesures voile (1ère et finale), élévateur, offset et résultat.
 *
 * Données stockées dans $data['trim'] :
 * - sides          : 'both' (gauche + droite) ou 'one' (un seul côté)
 * - riser_length   : longueur d'élévateur (mm) déduite des cotes usine
 * - offset         : correction de mesure (mm) ajoutée aux cotes usine
 * - structure      : [ 'A' => [ [ 'count' => 4, 'color' => 'rouge' ], … ], … ]
 * - factory        : [ 'A1' => 7000, … ] cotes usine
 * - initial, final : [ 'A1' => [ 'G' => 7012, 'D' => 7008 ], … ] mesures brutes
 * - initial_date, final_date, initial_locked
 *
 * Comme dans la feuille de calage de l'atelier :
 *   usine corrigée = cote usine − élévateur + offset
 *   résultat       = mesure voile − usine corrigée
 * Le résultat retenu pour le rapport est celui de la mesure finale (sinon de la 1ère).
 *
 * @package ControleParapente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Trim {

	const MAX_GROUPS = 12;
	const MAX_LINES  = 40;

	/**
	 * Rangées du suspentage. Les freins sont traités à part.
	 */
	public static function rows() {
		return array(
			'A' => __( 'Rangée A', 'controle-parapente' ),
			'B' => __( 'Rangée B', 'controle-parapente' ),
			'C' => __( 'Rangée C', 'controle-parapente' ),
			'D' => __( 'Rangée D', 'controle-parapente' ),
			'F' => __( 'Freins', 'controle-parapente' ),
		);
	}

	/**
	 * Couleurs disponibles pour identifier les groupes.
	 */
	public static function colors() {
		return array(
			'rouge'  => array( __( 'Rouge', 'controle-parapente' ), '#d64545' ),
			'orange' => array( __( 'Orange', 'controle-parapente' ), '#e8833a' ),
			'jaune'  => array( __( 'Jaune', 'controle-parapente' ), '#e3bd2b' ),
			'vert'   => array( __( 'Vert', 'controle-parapente' ), '#4e9a5b' ),
			'bleu'   => array( __( 'Bleu', 'controle-parapente' ), '#3d74c5' ),
			'violet' => array( __( 'Violet', 'controle-parapente' ), '#8a5cc2' ),
			'rose'   => array( __( 'Rose', 'controle-parapente' ), '#e07aa8' ),
			'marron' => array( __( 'Marron', 'controle-parapente' ), '#8a5a3c' ),
			'gris'   => array( __( 'Gris', 'controle-parapente' ), '#9a9a9a' ),
			'noir'   => array( __( 'Noir', 'controle-parapente' ), '#2b2b2b' ),
			'blanc'  => array( __( 'Blanc', 'controle-parapente' ), '#f4f1ec' ),
		);
	}

	/**
	 * Couleur proposée par défaut pour le n-ième groupe d'une rangée.
	 */
	public static function default_color( $index ) {
		$order = array( 'rouge', 'jaune', 'bleu', 'vert', 'orange', 'violet', 'rose', 'marron', 'gris', 'noir', 'blanc' );
		return $order[ $index % count( $order ) ];
	}

	public static function side_labels( $sides ) {
		return 'one' === $sides
			? array( 'G' => __( 'Mesure', 'controle-parapente' ) )
			: array(
				'G' => __( 'Gauche', 'controle-parapente' ),
				'D' => __( 'Droite', 'controle-parapente' ),
			);
	}

	private static function groups( array $counts ) {
		$groups = array();
		foreach ( $counts as $i => $count ) {
			$groups[] = array(
				'count' => $count,
				'color' => self::default_color( $i ),
			);
		}
		return $groups;
	}

	public static function defaults() {
		return array(
			'sides'          => 'both',
			'offset'         => '',
			'structure'      => array(
				'A' => self::groups( array( 4, 4, 2 ) ),
				'B' => self::groups( array( 4, 4, 2 ) ),
				'C' => self::groups( array( 4, 4, 2 ) ),
				'D' => array(),
				'F' => self::groups( array( 4, 4 ) ),
			),
			'factory'        => array(),
			'initial'        => array(),
			'final'          => array(),
			'riser_length'   => '',
			'initial_date'   => '',
			'final_date'     => '',
			'initial_locked' => '',
		);
	}

	/**
	 * Complète des données de calage enregistrées (y compris l'ancien format
	 * où les groupes n'étaient que des nombres de suspentes).
	 *
	 * @param mixed $trim Données brutes.
	 * @return array
	 */
	public static function normalize( $trim ) {
		if ( ! is_array( $trim ) || ! isset( $trim['structure'] ) ) {
			return self::defaults();
		}
		$trim   = wp_parse_args( $trim, self::defaults() );
		$colors = self::colors();
		foreach ( array_keys( self::rows() ) as $row ) {
			$groups = isset( $trim['structure'][ $row ] ) && is_array( $trim['structure'][ $row ] ) ? array_values( $trim['structure'][ $row ] ) : array();
			foreach ( $groups as $i => $group ) {
				if ( ! is_array( $group ) ) {
					$group = array( 'count' => (int) $group );
				}
				$groups[ $i ] = array(
					'count' => isset( $group['count'] ) ? (int) $group['count'] : 0,
					'color' => isset( $group['color'], $colors[ $group['color'] ] ) ? $group['color'] : self::default_color( $i ),
				);
			}
			$trim['structure'][ $row ] = $groups;
		}
		foreach ( array( 'factory', 'initial', 'final' ) as $key ) {
			if ( ! is_array( $trim[ $key ] ) ) {
				$trim[ $key ] = array();
			}
		}
		return $trim;
	}

	/**
	 * Liste des suspentes à partir de la structure.
	 * Numérotation continue par rangée (A1, A2, …) comme dans les plans constructeur.
	 *
	 * @param array $structure Structure normalisée.
	 * @return array Liste de [ id, row, group, color ].
	 */
	public static function lines( array $structure ) {
		$lines = array();
		foreach ( array_keys( self::rows() ) as $row ) {
			$n = 0;
			foreach ( isset( $structure[ $row ] ) ? (array) $structure[ $row ] : array() as $gi => $group ) {
				for ( $k = 0; $k < (int) $group['count']; $k++ ) {
					++$n;
					$lines[] = array(
						'id'    => $row . $n,
						'row'   => $row,
						'group' => $gi + 1,
						'color' => $group['color'],
					);
				}
			}
		}
		return $lines;
	}

	public static function num( $value ) {
		$value = str_replace( ',', '.', trim( (string) $value ) );
		return is_numeric( $value ) ? (string) round( (float) $value, 1 ) : '';
	}

	/**
	 * Nettoie les données de calage envoyées par le formulaire.
	 *
	 * @param mixed $raw Données brutes.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		// La feuille de calage envoie les longueurs en JSON (un seul champ, quel que soit le nombre de suspentes).
		if ( isset( $raw['data'] ) && is_string( $raw['data'] ) ) {
			$json = json_decode( $raw['data'], true );
			if ( is_array( $json ) ) {
				foreach ( array( 'factory', 'initial', 'final' ) as $key ) {
					if ( isset( $json[ $key ] ) && is_array( $json[ $key ] ) ) {
						$raw[ $key ] = $json[ $key ];
					}
				}
			}
		}
		$trim   = self::defaults();
		$colors = self::colors();

		$trim['sides']          = isset( $raw['sides'] ) && 'one' === $raw['sides'] ? 'one' : 'both';
		$trim['offset']         = isset( $raw['offset'] ) ? self::num( $raw['offset'] ) : '';
		$trim['riser_length']   = isset( $raw['riser_length'] ) ? self::num( $raw['riser_length'] ) : '';
		$trim['initial_date']   = CP_Controle::sanitize_date( isset( $raw['initial_date'] ) ? $raw['initial_date'] : '' );
		$trim['final_date']     = CP_Controle::sanitize_date( isset( $raw['final_date'] ) ? $raw['final_date'] : '' );
		$trim['initial_locked'] = ! empty( $raw['initial_locked'] ) ? '1' : '';

		foreach ( array_keys( self::rows() ) as $row ) {
			$groups = array();
			if ( isset( $raw['structure'][ $row ] ) && is_array( $raw['structure'][ $row ] ) ) {
				foreach ( array_slice( array_values( $raw['structure'][ $row ] ), 0, self::MAX_GROUPS ) as $i => $group ) {
					$count = is_array( $group ) && isset( $group['count'] ) ? absint( $group['count'] ) : absint( $group );
					if ( $count <= 0 ) {
						continue;
					}
					$color    = is_array( $group ) && isset( $group['color'] ) ? sanitize_key( $group['color'] ) : '';
					$groups[] = array(
						'count' => min( self::MAX_LINES, $count ),
						'color' => isset( $colors[ $color ] ) ? $color : self::default_color( count( $groups ) ),
					);
				}
			}
			$trim['structure'][ $row ] = $groups;
		}

		$sides = array_keys( self::side_labels( $trim['sides'] ) );
		foreach ( self::lines( $trim['structure'] ) as $line ) {
			$id = $line['id'];
			if ( isset( $raw['factory'][ $id ] ) && '' !== self::num( $raw['factory'][ $id ] ) ) {
				$trim['factory'][ $id ] = self::num( $raw['factory'][ $id ] );
			}
			foreach ( array( 'initial', 'final' ) as $set ) {
				foreach ( $sides as $side ) {
					if ( isset( $raw[ $set ][ $id ][ $side ] ) && '' !== self::num( $raw[ $set ][ $id ][ $side ] ) ) {
						$trim[ $set ][ $id ][ $side ] = self::num( $raw[ $set ][ $id ][ $side ] );
					}
				}
			}
		}


		return $trim;
	}

	/**
	 * Cote usine corrigée (usine − élévateur + offset), ou null.
	 */
	public static function corrected_factory( array $trim, $id ) {
		if ( ! isset( $trim['factory'][ $id ] ) || '' === $trim['factory'][ $id ] ) {
			return null;
		}
		return (float) $trim['factory'][ $id ] - (float) $trim['riser_length'] + (float) $trim['offset'];
	}

	/**
	 * Calcule les écarts par suspente et par groupe.
	 *
	 * @param array $trim Données de calage.
	 * @return array
	 */
	public static function analyze( array $trim ) {
		$trim      = self::normalize( $trim );
		$tolerance = (float) CP_Settings::get( 'trim_tolerance' );
		$sides     = array_keys( self::side_labels( $trim['sides'] ) );
		$lines     = array();
		$groups    = array();
		$has_data  = false;
		$has_final = false;

		foreach ( self::lines( $trim['structure'] ) as $line ) {
			$id                = $line['id'];
			$line['factory']   = isset( $trim['factory'][ $id ] ) && '' !== $trim['factory'][ $id ] ? (float) $trim['factory'][ $id ] : null;
			$line['corrected'] = self::corrected_factory( $trim, $id );

			foreach ( $sides as $side ) {
				$gkey = $line['row'] . '|' . $line['group'] . '|' . $side;
				if ( ! isset( $groups[ $gkey ] ) ) {
					$groups[ $gkey ] = array(
						'row'     => $line['row'],
						'group'   => $line['group'],
						'color'   => $line['color'],
						'side'    => $side,
						'count'   => 0,
						'initial' => array(),
						'final'   => array(),
						'result'  => array(),
					);
				}
				++$groups[ $gkey ]['count'];

				foreach ( array( 'initial', 'final' ) as $set ) {
					$raw = isset( $trim[ $set ][ $id ][ $side ] ) && '' !== $trim[ $set ][ $id ][ $side ] ? (float) $trim[ $set ][ $id ][ $side ] : null;
					$dev = ( null === $raw || null === $line['corrected'] ) ? null : $raw - $line['corrected'];

					$line[ $set ][ $side ] = array(
						'raw'       => $raw,
						'deviation' => $dev,
						'level'     => self::level( $dev, $tolerance ),
					);
					if ( null !== $raw ) {
						$has_data = true;
						if ( 'final' === $set ) {
							$has_final = true;
						}
					}
					if ( null !== $dev ) {
						$groups[ $gkey ][ $set ][] = $dev;
					}
				}

				// Résultat retenu : mesure finale si saisie, sinon 1ère mesure.
				$ref                     = null !== $line['final'][ $side ]['deviation'] ? $line['final'][ $side ] : $line['initial'][ $side ];
				$line['result'][ $side ] = array(
					'deviation' => $ref['deviation'],
					'level'     => $ref['level'],
				);
				if ( null !== $ref['deviation'] ) {
					$groups[ $gkey ]['result'][] = $ref['deviation'];
				}
			}
			$lines[] = $line;
		}

		foreach ( $groups as $key => $g ) {
			foreach ( array( 'initial', 'final', 'result' ) as $set ) {
				$devs                   = $g[ $set ];
				$groups[ $key ][ $set ] = array(
					'measured' => count( $devs ),
					'mean'     => $devs ? array_sum( $devs ) / count( $devs ) : null,
					'max'      => $devs ? self::max_abs( $devs ) : null,
				);
			}
			$mi = $groups[ $key ]['initial']['mean'];
			$mf = $groups[ $key ]['final']['mean'];

			$groups[ $key ]['adjustment'] = ( null === $mi || null === $mf ) ? null : $mf - $mi;
			$groups[ $key ]['level']      = null === $groups[ $key ]['result']['max'] ? '' : self::level( $groups[ $key ]['result']['max'], $tolerance );
		}

		return array(
			'lines'     => $lines,
			'groups'    => array_values( $groups ),
			'has_data'  => $has_data,
			'has_final' => $has_final,
		);
	}

	private static function max_abs( array $values ) {
		$max = $values[0];
		foreach ( $values as $v ) {
			if ( abs( $v ) > abs( $max ) ) {
				$max = $v;
			}
		}
		return $max;
	}

	public static function level( $deviation, $tolerance ) {
		if ( null === $deviation ) {
			return '';
		}
		return abs( $deviation ) <= $tolerance ? 'ok' : 'bad';
	}

	/**
	 * Formate un écart signé : +12 / −3,5.
	 */
	public static function signed( $value ) {
		if ( null === $value ) {
			return '—';
		}
		$value = round( $value, 1 );
		if ( 0.0 === (float) $value ) {
			return '0';
		}
		return ( $value > 0 ? '+' : '−' ) . number_format_i18n( abs( $value ), floor( abs( $value ) ) == abs( $value ) ? 0 : 1 );
	}

	public static function length( $value ) {
		if ( null === $value ) {
			return '—';
		}
		return number_format_i18n( $value, floor( $value ) == $value ? 0 : 1 );
	}

	public static function swatch( $color ) {
		$colors = self::colors();
		$hex    = isset( $colors[ $color ] ) ? $colors[ $color ][1] : '#ccc';
		return '<span class="cp-swatch" style="background:' . esc_attr( $hex ) . '"></span>';
	}

	public static function group_label( $row, $color ) {
		$colors = self::colors();
		$name   = isset( $colors[ $color ] ) ? $colors[ $color ][0] : '';
		return ( 'F' === $row ? __( 'Freins', 'controle-parapente' ) : $row ) . ' · ' . $name;
	}

	/* ------------------------------------------------------------------ */
	/* Saisie (admin et espace atelier)                                    */
	/* ------------------------------------------------------------------ */

	public static function render_admin_box( array $trim, $trim_adjusted ) {
		$trim     = self::normalize( $trim );
		$has_fact = (bool) array_filter( $trim['factory'], 'strlen' );
		$colors   = array();
		foreach ( self::colors() as $key => $c ) {
			$colors[ $key ] = array( 'label' => $c[0], 'hex' => $c[1] );
		}
		$stepper = static function ( $id, $name, $value, $label, $help ) {
			?>
			<div class="cp-sheet-control">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
				<div class="cp-stepper" data-step="1">
					<button type="button" class="cp-step-down" aria-label="−1 mm">−</button>
					<input type="text" inputmode="decimal" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="0" />
					<button type="button" class="cp-step-up" aria-label="+1 mm">+</button>
					<span class="cp-unit">mm</span>
				</div>
				<span class="description"><?php echo esc_html( $help ); ?></span>
			</div>
			<?php
		};
		?>
		<div class="cp-trim" data-tolerance="<?php echo esc_attr( CP_Settings::get( 'trim_tolerance' ) ); ?>" data-step="<?php echo esc_attr( $has_fact ? 'feuille' : 'structure' ); ?>"
			data-preview-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-preview-nonce="<?php echo esc_attr( wp_create_nonce( 'cp_wing_preview' ) ); ?>">

			<nav class="cp-trim-steps" role="tablist">
				<button type="button" data-step="structure"><span>1</span> <?php esc_html_e( 'Structure & couleurs', 'controle-parapente' ); ?></button>
				<button type="button" data-step="feuille"><span>2</span> <?php esc_html_e( 'Feuille de calage', 'controle-parapente' ); ?></button>
			</nav>

			<!-- 1. Structure -->
			<div class="cp-trim-step" data-step-panel="structure">
				<p class="description"><?php esc_html_e( 'Pour chaque rangée, ajoutez les groupes en choisissant leur couleur, puis le nombre de suspentes du groupe (du centre vers le bout d\'aile). Les suspentes sont numérotées 1, 2, 3… dans l\'ordre des groupes.', 'controle-parapente' ); ?></p>
				<div class="cp-trim-structure"></div>
				<div class="cp-grid cp-trim-settings">
					<p class="cp-field">
						<label for="cp-trim-sides"><?php esc_html_e( 'Côtés mesurés', 'controle-parapente' ); ?></label>
						<select id="cp-trim-sides" name="cp[trim][sides]" class="widefat">
							<option value="both" <?php selected( $trim['sides'], 'both' ); ?>><?php esc_html_e( 'Gauche et droite', 'controle-parapente' ); ?></option>
							<option value="one" <?php selected( $trim['sides'], 'one' ); ?>><?php esc_html_e( 'Un seul côté', 'controle-parapente' ); ?></option>
						</select>
					</p>
				</div>
			</div>

			<!-- 2. Feuille de calage -->
			<div class="cp-trim-step" data-step-panel="feuille">
				<div class="cp-sheet-toolbar">
					<div class="cp-sheet-switches">
						<div class="cp-seg" data-switch="set" role="group" aria-label="<?php esc_attr_e( 'Mesure', 'controle-parapente' ); ?>">
							<button type="button" data-value="initial"><?php esc_html_e( '1ère mesure', 'controle-parapente' ); ?></button>
							<button type="button" data-value="final"><?php esc_html_e( '2e mesure', 'controle-parapente' ); ?></button>
						</div>
						<div class="cp-seg" data-switch="side" role="group" aria-label="<?php esc_attr_e( 'Côté', 'controle-parapente' ); ?>">
							<button type="button" data-value="G"><?php esc_html_e( 'Gauche', 'controle-parapente' ); ?></button>
							<button type="button" data-value="D"><?php esc_html_e( 'Droite', 'controle-parapente' ); ?></button>
						</div>
					</div>
					<?php
					$stepper( 'cp-trim-riser', 'cp[trim][riser_length]', $trim['riser_length'], __( 'Élévateur', 'controle-parapente' ), __( 'Déduit des cotes usine.', 'controle-parapente' ) );
					$stepper( 'cp-trim-offset', 'cp[trim][offset]', $trim['offset'], __( 'Offset', 'controle-parapente' ), __( 'Ajouté aux cotes usine.', 'controle-parapente' ) );
					?>
					<div class="cp-sheet-control cp-sheet-dates">
						<label><?php esc_html_e( 'Dates', 'controle-parapente' ); ?></label>
						<span><?php esc_html_e( '1ère', 'controle-parapente' ); ?> <input type="date" id="cp-trim-initial-date" name="cp[trim][initial_date]" value="<?php echo esc_attr( $trim['initial_date'] ); ?>" /></span>
						<span><?php esc_html_e( '2e', 'controle-parapente' ); ?> <input type="date" id="cp-trim-final-date" name="cp[trim][final_date]" value="<?php echo esc_attr( $trim['final_date'] ); ?>" /></span>
						<label class="cp-check"><input type="checkbox" id="cp-trim-locked" name="cp[trim][initial_locked]" value="1" <?php checked( $trim['initial_locked'], '1' ); ?> /> <?php esc_html_e( '1ère mesure figée', 'controle-parapente' ); ?></label>
					</div>
				</div>
				<p class="cp-sheet-formula">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: tolérance */
							__( 'Usine corrigée = usine − élévateur + offset · Résultat = voile − usine corrigée · Tolérance ± %s mm. Flèches / Entrée pour se déplacer ; collez une colonne depuis Excel ou le laser.', 'controle-parapente' ),
							CP_Settings::get( 'trim_tolerance' )
						)
					);
					?>
				</p>
				<div class="cp-sheet-wrap"><div class="cp-sheet"></div></div>
				<p class="cp-sheet-actions">
					<button type="button" class="button cp-trim-copy"><?php esc_html_e( 'Copier la 1ère mesure dans la 2e (cases vides)', 'controle-parapente' ); ?></button>
				</p>

				<div class="cp-sheet-bottom">
					<div>
						<h4><?php esc_html_e( 'Écart moyen par groupe', 'controle-parapente' ); ?></h4>
						<div class="cp-trim-summary"></div>
					</div>
					<div>
						<h4><?php esc_html_e( 'Aperçu du rapport client', 'controle-parapente' ); ?></h4>
						<div class="cp-wing-preview"><p class="cp-trim-empty"><?php esc_html_e( 'L\'aperçu apparaît dès les premières mesures.', 'controle-parapente' ); ?></p></div>
					</div>
				</div>
				<p>
					<label>
						<input type="checkbox" name="cp[trim_adjusted]" value="1" <?php checked( $trim_adjusted, '1' ); ?> />
						<?php esc_html_e( 'Aile recalée pendant le contrôle', 'controle-parapente' ); ?>
					</label>
				</p>
			</div>

			<input type="hidden" name="cp[trim][data]" class="cp-trim-json" value="" />
			<script type="application/json" class="cp-trim-data"><?php
			echo wp_json_encode(
				array(
					'colors'    => $colors,
					'rows'      => self::rows(),
					'structure' => $trim['structure'],
					'factory'   => (object) $trim['factory'],
					'initial'   => (object) $trim['initial'],
					'final'     => (object) $trim['final'],
				)
			);
			?></script>
		</div>
		<?php
	}

	/**
	 * Aperçu AJAX du dessin client pendant la saisie.
	 */
	public static function ajax_preview() {
		check_ajax_referer( 'cp_wing_preview', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}
		$raw  = isset( $_POST['trim'] ) ? json_decode( wp_unslash( $_POST['trim'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nettoyé par sanitize().
		$trim = self::normalize( self::sanitize( is_array( $raw ) ? $raw : array() ) );
		$data = self::analyze( $trim );
		wp_send_json_success( $data['has_data'] ? self::wing_svg( $trim, $data ) : '' );
	}

	/* ------------------------------------------------------------------ */
	/* Rapport                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Section calage du rapport.
	 *
	 * @param array       $trim          Données de calage.
	 * @param string      $trim_adjusted Aile recalée.
	 * @param string|null $title         Titre de la section.
	 * @param bool        $detailed      Tableaux détaillés (fiche atelier) ; sinon seulement le dessin (rapport client).
	 */
	public static function render_certificate( array $trim, $trim_adjusted, $title = null, $detailed = true ) {
		$trim     = self::normalize( $trim );
		$analysis = self::analyze( $trim );
		if ( ! $analysis['has_data'] ) {
			return;
		}
		$sides     = self::side_labels( $trim['sides'] );
		$both      = count( $sides ) > 1;
		$rows      = self::rows();
		$has_final = $analysis['has_final'];
		$sets      = $has_final
			? array( 'initial' => __( '1ère mesure', 'controle-parapente' ), 'final' => __( 'Mesure finale', 'controle-parapente' ) )
			: array( 'initial' => __( 'Mesure', 'controle-parapente' ) );
		?>
		<section class="cp-inspection">
			<h2><?php echo esc_html( null === $title ? __( 'Calage', 'controle-parapente' ) : $title ); ?></h2>
			<p class="cp-small">
				<?php
				$info = array( sprintf( __( 'Tolérance : ± %s mm', 'controle-parapente' ), CP_Settings::get( 'trim_tolerance' ) ) );
				if ( CP_Settings::get( 'trim_load' ) ) {
					$info[] = sprintf( __( 'longueurs mesurées sous %s', 'controle-parapente' ), CP_Settings::get( 'trim_load' ) );
				}
				if ( $detailed && '' !== $trim['riser_length'] ) {
					$info[] = sprintf( __( 'élévateur : %s mm', 'controle-parapente' ), self::length( (float) $trim['riser_length'] ) );
				}
				if ( $detailed && '' !== $trim['offset'] ) {
					$info[] = sprintf( __( 'offset : %s mm', 'controle-parapente' ), self::signed( (float) $trim['offset'] ) );
				}
				if ( $trim['initial_date'] ) {
					$info[] = sprintf( __( '1ère mesure le %s', 'controle-parapente' ), CP_Controle::format_date( $trim['initial_date'] ) );
				}
				if ( $has_final && $trim['final_date'] ) {
					$info[] = sprintf( __( 'mesure finale le %s', 'controle-parapente' ), CP_Controle::format_date( $trim['final_date'] ) );
				}
				echo esc_html( implode( ' — ', $info ) . '.' );
				if ( $trim_adjusted ) {
					echo ' ' . esc_html__( 'L\'aile a été recalée lors du contrôle.', 'controle-parapente' );
				}
				?>
			</p>

			<?php echo self::wing_svg( $trim, $analysis ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG construit et échappé dans wing_svg(). ?>
			<p class="cp-small cp-wing-legend">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: tolérance */
						__( 'Aile vue de dessus, bord d\'attaque en haut. Chaque étiquette donne l\'écart moyen du groupe de suspentes par rapport aux cotes du constructeur (mm)%1$s. Vert : dans la tolérance de ± %2$s mm ; rouge : hors tolérance.', 'controle-parapente' ),
						$has_final ? __( ' après intervention, avec la valeur avant intervention en petit', 'controle-parapente' ) : '',
						CP_Settings::get( 'trim_tolerance' )
					)
				);
				?>
			</p>

			<?php if ( $detailed ) : ?>
			<h3 class="cp-workshop-only"><?php esc_html_e( 'Détail atelier', 'controle-parapente' ); ?></h3>

			<h3><?php esc_html_e( 'Décalage par groupe', 'controle-parapente' ); ?></h3>
			<table class="cp-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Groupe', 'controle-parapente' ); ?></th>
						<?php if ( $both ) : ?><th><?php esc_html_e( 'Côté', 'controle-parapente' ); ?></th><?php endif; ?>
						<th class="num"><?php esc_html_e( 'Susp.', 'controle-parapente' ); ?></th>
						<th class="num"><?php echo esc_html( $has_final ? __( 'Écart moy. 1ère', 'controle-parapente' ) : __( 'Écart moyen', 'controle-parapente' ) ); ?></th>
						<?php if ( $has_final ) : ?><th class="num"><?php esc_html_e( 'Écart moy. finale', 'controle-parapente' ); ?></th><?php endif; ?>
						<th class="num"><?php esc_html_e( 'Écart max', 'controle-parapente' ); ?></th>
						<th><?php esc_html_e( 'État', 'controle-parapente' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $analysis['groups'] as $g ) : ?>
					<?php
					if ( ! $g['result']['measured'] ) {
						continue;
					}
					?>
					<tr class="<?php echo 'F' === $g['row'] ? 'cp-brakes' : ''; ?>">
						<td><?php echo self::swatch( $g['color'] ) . esc_html( self::group_label( $g['row'], $g['color'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<?php if ( $both ) : ?><td><?php echo esc_html( $sides[ $g['side'] ] ); ?></td><?php endif; ?>
						<td class="num"><?php echo esc_html( $g['count'] ); ?></td>
						<td class="num"><?php echo esc_html( self::signed( $g['initial']['mean'] ) ); ?></td>
						<?php if ( $has_final ) : ?><td class="num"><?php echo esc_html( self::signed( $g['final']['mean'] ) ); ?></td><?php endif; ?>
						<td class="num lvl-<?php echo esc_attr( $g['level'] ); ?>"><?php echo esc_html( self::signed( $g['result']['max'] ) ); ?></td>
						<td class="lvl-<?php echo esc_attr( $g['level'] ); ?>"><?php echo esc_html( 'ok' === $g['level'] ? __( 'Conforme', 'controle-parapente' ) : ( 'bad' === $g['level'] ? __( 'Hors tolérance', 'controle-parapente' ) : '—' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Détail par suspente (écarts à la cote usine, mm)', 'controle-parapente' ); ?></h3>
			<table class="cp-table cp-trim-detail">
				<thead>
					<tr>
						<th rowspan="2"><?php esc_html_e( 'Susp.', 'controle-parapente' ); ?></th>
						<th rowspan="2" class="num"><?php esc_html_e( 'Usine', 'controle-parapente' ); ?></th>
						<?php foreach ( $sides as $side_label ) : ?>
							<th colspan="<?php echo esc_attr( count( $sets ) ); ?>" class="center"><?php echo esc_html( $both ? $side_label : __( 'Écarts', 'controle-parapente' ) ); ?></th>
						<?php endforeach; ?>
					</tr>
					<tr>
						<?php foreach ( $sides as $side_label ) : ?>
							<?php foreach ( $sets as $label ) : ?><th class="num"><?php echo esc_html( $label ); ?></th><?php endforeach; ?>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
				<?php
				$current = '';
				$cols    = 2 + count( $sides ) * count( $sets );
				foreach ( $analysis['lines'] as $line ) :
					$any = false;
					foreach ( array_keys( $sides ) as $side ) {
						$any = $any || null !== $line['result'][ $side ]['deviation'];
					}
					if ( ! $any ) {
						continue;
					}
					$gkey = $line['row'] . $line['group'];
					if ( $gkey !== $current ) :
						$current = $gkey;
						?>
						<tr class="cp-group-head"><td colspan="<?php echo esc_attr( $cols ); ?>"><?php echo self::swatch( $line['color'] ) . esc_html( self::group_label( $line['row'], $line['color'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td></tr>
					<?php endif; ?>
					<tr>
						<td><?php echo esc_html( $line['id'] ); ?></td>
						<td class="num"><?php echo esc_html( self::length( $line['factory'] ) ); ?></td>
						<?php foreach ( array_keys( $sides ) as $side ) : ?>
							<?php foreach ( array_keys( $sets ) as $set ) : ?>
								<td class="num lvl-<?php echo esc_attr( $line[ $set ][ $side ]['level'] ); ?>"><?php echo esc_html( self::signed( $line[ $set ][ $side ]['deviation'] ) ); ?></td>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Dessin de l'aile vue de dessus avec l'écart moyen de chaque groupe (SVG).
	 *
	 * Forme en plan elliptique, bord d'attaque et bouts d'aile reculés. Pour chaque
	 * rangée, les points d'accroche des suspentes sont répartis du centre vers le bout
	 * d'aile (A1 au centre), à la position de la rangée sur la corde (A près du bord
	 * d'attaque, freins au bord de fuite). L'étiquette d'un groupe est centrée sur ses
	 * propres points d'accroche. Moitié gauche = côté gauche, moitié droite = côté droit.
	 *
	 * @param array $trim     Données normalisées.
	 * @param array $analysis Résultat de analyze().
	 * @return string
	 */
	public static function wing_svg( array $trim, array $analysis ) {
		$w      = 900;
		$h      = 420;
		$cx     = $w / 2;
		$half   = 420;   // Demi-envergure projetée (px).
		$top    = 44;    // Bord d'attaque au centre.
		$chord  = 270;   // Corde centrale (px).
		$sweep  = 34;    // Recul du bord d'attaque en bout d'aile.
		$colors = self::colors();
		$tol    = (float) CP_Settings::get( 'trim_tolerance' );
		$both   = 'one' !== $trim['sides'];

		// Position des rangées sur la corde (fraction depuis le bord d'attaque).
		$row_pos = array( 'A' => 0.12, 'B' => 0.34, 'C' => 0.55, 'D' => 0.74, 'F' => 0.97 );

		// Bord d'attaque : reculé vers les bouts, coins avant arrondis.
		// Bord de fuite : presque droit, coins arrière carrés.
		$round  = 0.84;          // Début de l'arrondi avant (fraction de demi-envergure).
		$radius = $chord * 0.38; // Profondeur de l'arrondi avant.
		$le_at  = static function ( $u ) use ( $top, $sweep, $round, $radius ) {
			$u = min( 1, abs( $u ) );
			$y = $top + $sweep * pow( $u, 2.2 );
			if ( $u > $round ) {
				$t  = ( $u - $round ) / ( 1 - $round );
				$y += $radius * ( 1 - sqrt( max( 0, 1 - $t * $t ) ) );
			}
			return $y;
		};
		$te_at  = static function ( $u ) use ( $top, $chord ) {
			$u = min( 1, abs( $u ) );
			return $top + $chord + 16 * $u * $u;
		};
		$chord_at = static function ( $u ) use ( $le_at, $te_at ) {
			return $te_at( $u ) - $le_at( $u );
		};
		$point    = static function ( $u, $pos ) use ( $cx, $half, $le_at, $chord_at ) {
			return array( $cx + $u * $half, $le_at( $u ) + $pos * $chord_at( $u ) );
		};

		$le = array();
		$te = array();
		for ( $i = -120; $i <= 120; $i++ ) {
			$u    = $i / 120;
			$x    = round( $cx + $u * $half, 1 );
			$le[] = $x . ',' . round( $le_at( $u ), 1 );
			$te[] = $x . ',' . round( $te_at( $u ), 1 );
		}
		$outline = 'M' . implode( ' L', $le ) . ' L' . implode( ' L', array_reverse( $te ) ) . ' Z';

		$svg  = '<svg class="cp-wing" viewBox="0 0 ' . $w . ' ' . $h . '" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . esc_attr__( 'Écarts de calage par groupe, aile vue de dessus', 'controle-parapente' ) . '">';
		$svg .= '<defs>'
			. '<linearGradient id="cpWingFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fdf3ea"/><stop offset="0.35" stop-color="#f8e6d8"/><stop offset="1" stop-color="#efd9c8"/></linearGradient>'
			. '<filter id="cpWingShadow" x="-5%" y="-5%" width="110%" height="120%"><feDropShadow dx="0" dy="6" stdDeviation="7" flood-color="#8a5a3c" flood-opacity="0.18"/></filter>'
			. '</defs>';
		$svg .= '<path d="' . esc_attr( $outline ) . '" fill="url(#cpWingFill)" stroke="#c49a80" stroke-width="1.6" filter="url(#cpWingShadow)"/>';

		// Caissons (nervures) et entrées d'air au bord d'attaque.
		$cells = 46;
		for ( $i = 1; $i < $cells; $i++ ) {
			$u    = -1 + 2 * $i / $cells;
			$c    = $chord_at( $u );
			$y1   = $le_at( $u );
			$x    = round( $cx + $u * $half, 1 );
			$svg .= '<line x1="' . $x . '" y1="' . round( $y1 + 2, 1 ) . '" x2="' . $x . '" y2="' . round( $y1 + $c - 2, 1 ) . '" stroke="#e7cbb7" stroke-width="' . ( 0 === $i % 2 ? '1' : '0.5' ) . '"/>';
		}
		$intake = array();
		for ( $i = -76; $i <= 76; $i++ ) {
			$u        = $i / 80;
			$intake[] = round( $cx + $u * $half, 1 ) . ',' . round( $le_at( $u ) + 0.05 * $chord_at( $u ), 1 );
		}
		$svg .= '<polyline points="' . implode( ' ', $intake ) . '" fill="none" stroke="#d9b49b" stroke-width="1" stroke-dasharray="6 5"/>';

		// Axe central et libellés des rangées.
		$svg .= '<line x1="' . $cx . '" y1="' . ( $top - 12 ) . '" x2="' . $cx . '" y2="' . ( $top + $chord + 12 ) . '" stroke="#b69580" stroke-dasharray="3 4"/>';
		foreach ( $row_pos as $row => $pos ) {
			if ( empty( $trim['structure'][ $row ] ) ) {
				continue;
			}
			$y    = round( $le_at( 0 ) + $pos * $chord_at( 0 ), 1 );
			$svg .= '<rect x="' . ( $cx - 13 ) . '" y="' . ( $y - 10 ) . '" width="26" height="20" rx="10" fill="#fff8f2" stroke="#d9b49b"/>';
			$svg .= '<text x="' . $cx . '" y="' . ( $y + 4.5 ) . '" text-anchor="middle" class="cp-wing-row">' . esc_html( 'F' === $row ? __( 'Fr', 'controle-parapente' ) : $row ) . '</text>';
		}
		$svg .= '<text x="' . $cx . '" y="22" text-anchor="middle" class="cp-wing-caption">▲ ' . esc_html__( 'Bord d\'attaque — sens de vol', 'controle-parapente' ) . '</text>';
		$svg .= '<text x="' . $cx . '" y="' . ( $h - 12 ) . '" text-anchor="middle" class="cp-wing-caption">' . esc_html__( 'Bord de fuite', 'controle-parapente' ) . '</text>';
		$svg .= '<text x="18" y="' . ( $h - 12 ) . '" class="cp-wing-caption">' . esc_html( $both ? __( 'Côté gauche', 'controle-parapente' ) : __( 'Mesure (un côté)', 'controle-parapente' ) ) . '</text>';
		if ( $both ) {
			$svg .= '<text x="' . ( $w - 18 ) . '" y="' . ( $h - 12 ) . '" text-anchor="end" class="cp-wing-caption">' . esc_html__( 'Côté droit', 'controle-parapente' ) . '</text>';
		}

		$groups = array();
		foreach ( $analysis['groups'] as $g ) {
			$groups[ $g['row'] . '|' . $g['group'] . '|' . $g['side'] ] = $g;
		}

		$dots = '';
		$tags = '';
		foreach ( $trim['structure'] as $row => $row_groups ) {
			if ( ! $row_groups || ! isset( $row_pos[ $row ] ) ) {
				continue;
			}
			$total = 0;
			foreach ( $row_groups as $grp ) {
				$total += (int) $grp['count'];
			}
			if ( ! $total ) {
				continue;
			}
			// Points d'accroche répartis régulièrement du centre vers le bout d'aile.
			$u_start = 0.05;
			$u_end   = 'F' === $row ? 0.88 : 0.9;
			$step    = $total > 1 ? ( $u_end - $u_start ) / ( $total - 1 ) : 0;
			$n       = 0;
			foreach ( $row_groups as $gi => $grp ) {
				$hex = isset( $colors[ $grp['color'] ] ) ? $colors[ $grp['color'] ][1] : '#999';
				$us  = array();
				for ( $k = 0; $k < (int) $grp['count']; $k++, $n++ ) {
					$us[] = $u_start + $n * $step;
				}
				if ( ! $us ) {
					continue;
				}
				$u_mid = array_sum( $us ) / count( $us );
				foreach ( array( -1, 1 ) as $dir ) {
					foreach ( $us as $u ) {
						list( $x, $y ) = $point( $dir * $u, $row_pos[ $row ] );
						$dots         .= '<circle cx="' . round( $x, 1 ) . '" cy="' . round( $y, 1 ) . '" r="3.4" fill="' . esc_attr( $hex ) . '" stroke="#fff" stroke-width="1"/>';
					}
					// Trait reliant les points du groupe.
					list( $x1, $y1 ) = $point( $dir * min( $us ), $row_pos[ $row ] );
					list( $x2, $y2 ) = $point( $dir * max( $us ), $row_pos[ $row ] );
					$dots           .= '<line x1="' . round( $x1, 1 ) . '" y1="' . round( $y1, 1 ) . '" x2="' . round( $x2, 1 ) . '" y2="' . round( $y2, 1 ) . '" stroke="' . esc_attr( $hex ) . '" stroke-width="2" stroke-opacity="0.45"/>';

					$side = ( -1 === $dir || ! $both ) ? 'G' : 'D';
					$key  = $row . '|' . ( $gi + 1 ) . '|' . $side;
					if ( ! isset( $groups[ $key ] ) || null === $groups[ $key ]['result']['mean'] ) {
						continue;
					}
					$g      = $groups[ $key ];
					$value  = $g['result']['mean'];
					$before = $analysis['has_final'] ? $g['initial']['mean'] : null;
					$show_b = null !== $before && abs( $before - $value ) >= 1;
					$ok     = abs( $value ) <= $tol;
					list( $x, $y ) = $point( $dir * $u_mid, $row_pos[ $row ] );
					$bw     = 48;
					$bh     = $show_b ? 30 : 22;
					$x      = round( $x, 1 );
					$y      = round( $y, 1 );

					$tags .= '<g class="cp-wing-tag ' . ( $ok ? 'is-ok' : 'is-bad' ) . '">';
					$tags .= '<rect x="' . ( $x - $bw / 2 ) . '" y="' . ( $y - $bh / 2 ) . '" width="' . $bw . '" height="' . $bh . '" rx="8" fill="#fff" stroke="' . esc_attr( $hex ) . '" stroke-width="2.6"/>';
					$tags .= '<text x="' . $x . '" y="' . ( $y + ( $show_b ? -1.5 : 4.5 ) ) . '" text-anchor="middle" class="cp-wing-val" fill="' . ( $ok ? '#3e7a45' : '#b4533c' ) . '">' . esc_html( self::signed( $value ) ) . '</text>';
					if ( $show_b ) {
						/* translators: %s: écart avant intervention */
						$tags .= '<text x="' . $x . '" y="' . ( $y + 10.5 ) . '" text-anchor="middle" class="cp-wing-before">' . esc_html( sprintf( __( 'avant %s', 'controle-parapente' ), self::signed( $before ) ) ) . '</text>';
					}
					$tags .= '</g>';
				}
			}
		}
		$svg .= $dots . $tags . '</svg>';

		// Légende des couleurs de groupes utilisées.
		$used = array();
		foreach ( $trim['structure'] as $row_groups ) {
			foreach ( $row_groups as $grp ) {
				$used[ $grp['color'] ] = true;
			}
		}
		$legend = '<div class="cp-wing-colors">';
		foreach ( array_keys( $used ) as $color ) {
			if ( isset( $colors[ $color ] ) ) {
				$legend .= '<span>' . self::swatch( $color ) . esc_html( $colors[ $color ][0] ) . '</span>';
			}
		}
		$legend .= '</div>';

		return '<figure class="cp-wing-figure">' . $svg . $legend . '</figure>';
	}
}
