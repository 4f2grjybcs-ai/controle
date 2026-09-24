<?php
/**
 * Calage : structure du suspentage (groupes identifiés par couleur), mesures usine,
 * mesures voile (1ère et finale), offset, réglage des élévateurs et résultat.
 *
 * Données stockées dans $data['trim'] :
 * - sides          : 'both' (gauche + droite) ou 'one' (un seul côté)
 * - offset         : correction en mm ajoutée à toutes les mesures voile
 * - structure      : [ 'A' => [ [ 'count' => 4, 'color' => 'rouge' ], … ], … ]
 * - factory        : [ 'A1' => 7000, … ] cotes usine
 * - initial, final : [ 'A1' => [ 'G' => 7012, 'D' => 7008 ], … ] mesures brutes
 * - riser          : [ 'A' => [ 'G' => -10, 'D' => -8 ], … ] réglage élévateur (mm)
 * - initial_date, final_date, initial_locked
 *
 * Résultat = mesure de référence (finale si saisie, sinon 1ère) + offset + élévateur − cote usine.
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
			'riser'          => array(),
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
		foreach ( array( 'factory', 'initial', 'final', 'riser' ) as $key ) {
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
		$raw    = is_array( $raw ) ? $raw : array();
		$trim   = self::defaults();
		$colors = self::colors();

		$trim['sides']          = isset( $raw['sides'] ) && 'one' === $raw['sides'] ? 'one' : 'both';
		$trim['offset']         = isset( $raw['offset'] ) ? self::num( $raw['offset'] ) : '';
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

		foreach ( array_keys( self::rows() ) as $row ) {
			foreach ( $sides as $side ) {
				if ( $trim['structure'][ $row ] && isset( $raw['riser'][ $row ][ $side ] ) ) {
					$value = self::num( $raw['riser'][ $row ][ $side ] );
					if ( '' !== $value && 0.0 !== (float) $value ) {
						$trim['riser'][ $row ][ $side ] = $value;
					}
				}
			}
		}

		return $trim;
	}

	/**
	 * Calcule les écarts par suspente et par groupe.
	 *
	 * @param array $trim Données de calage.
	 * @return array
	 */
	public static function analyze( array $trim ) {
		$trim      = self::normalize( $trim );
		$offset    = '' === $trim['offset'] ? 0.0 : (float) $trim['offset'];
		$tolerance = (float) CP_Settings::get( 'trim_tolerance' );
		$sides     = array_keys( self::side_labels( $trim['sides'] ) );
		$lines     = array();
		$groups    = array();
		$has_data  = false;
		$has_final = false;
		$has_riser = false;

		foreach ( self::lines( $trim['structure'] ) as $line ) {
			$id              = $line['id'];
			$factory         = isset( $trim['factory'][ $id ] ) && '' !== $trim['factory'][ $id ] ? (float) $trim['factory'][ $id ] : null;
			$line['factory'] = $factory;

			foreach ( $sides as $side ) {
				$riser = isset( $trim['riser'][ $line['row'] ][ $side ] ) ? (float) $trim['riser'][ $line['row'] ][ $side ] : 0.0;
				if ( 0.0 !== $riser ) {
					$has_riser = true;
				}
				$gkey = $line['row'] . '|' . $line['group'] . '|' . $side;
				if ( ! isset( $groups[ $gkey ] ) ) {
					$groups[ $gkey ] = array(
						'row'     => $line['row'],
						'group'   => $line['group'],
						'color'   => $line['color'],
						'side'    => $side,
						'count'   => 0,
						'riser'   => $riser,
						'initial' => array(),
						'final'   => array(),
						'result'  => array(),
					);
				}
				++$groups[ $gkey ]['count'];

				foreach ( array( 'initial', 'final' ) as $set ) {
					$raw = isset( $trim[ $set ][ $id ][ $side ] ) && '' !== $trim[ $set ][ $id ][ $side ] ? (float) $trim[ $set ][ $id ][ $side ] : null;
					$cor = null === $raw ? null : $raw + $offset;
					$dev = ( null === $cor || null === $factory ) ? null : $cor - $factory;

					$line[ $set ][ $side ] = array(
						'raw'       => $raw,
						'corrected' => $cor,
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

				// Résultat : mesure de référence + réglage élévateur.
				$ref    = null !== $line['final'][ $side ]['deviation'] ? $line['final'][ $side ] : $line['initial'][ $side ];
				$result = null === $ref['deviation'] ? null : $ref['deviation'] + $riser;

				$line['result'][ $side ] = array(
					'deviation' => $result,
					'level'     => self::level( $result, $tolerance ),
				);
				if ( null !== $result ) {
					$groups[ $gkey ]['result'][] = $result;
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
			$mr = $groups[ $key ]['result']['mean'];

			$groups[ $key ]['adjustment'] = ( null === $mi || null === $mf ) ? null : $mf - $mi;
			$groups[ $key ]['correction'] = null === $mr ? null : -$mr;
			$groups[ $key ]['level']      = null === $groups[ $key ]['result']['max'] ? '' : self::level( $groups[ $key ]['result']['max'], $tolerance );
		}

		return array(
			'lines'     => $lines,
			'groups'    => array_values( $groups ),
			'has_data'  => $has_data,
			'has_final' => $has_final,
			'has_riser' => $has_riser,
			'offset'    => $offset,
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
		$analysis = self::analyze( $trim );
		$has_fact = (bool) array_filter( $trim['factory'], 'strlen' );
		$step     = ! $has_fact ? 'usine' : ( $analysis['has_data'] ? 'resultat' : 'voile' );
		$colors   = array();
		foreach ( self::colors() as $key => $c ) {
			$colors[ $key ] = array( 'label' => $c[0], 'hex' => $c[1] );
		}
		?>
		<div class="cp-trim" data-tolerance="<?php echo esc_attr( CP_Settings::get( 'trim_tolerance' ) ); ?>" data-step="<?php echo esc_attr( $step ); ?>">

			<nav class="cp-trim-steps" role="tablist">
				<button type="button" data-step="structure"><span>1</span> <?php esc_html_e( 'Structure & couleurs', 'controle-parapente' ); ?></button>
				<button type="button" data-step="usine"><span>2</span> <?php esc_html_e( 'Mesures usine', 'controle-parapente' ); ?></button>
				<button type="button" data-step="voile"><span>3</span> <?php esc_html_e( 'Mesures voile', 'controle-parapente' ); ?></button>
				<button type="button" data-step="resultat"><span>4</span> <?php esc_html_e( 'Résultat', 'controle-parapente' ); ?></button>
			</nav>

			<!-- 1. Structure -->
			<div class="cp-trim-step" data-step-panel="structure">
				<p class="description"><?php esc_html_e( 'Pour chaque rangée, ajoutez les groupes en choisissant leur couleur, puis le nombre de suspentes du groupe. Les suspentes sont numérotées A1, A2… dans l\'ordre des groupes.', 'controle-parapente' ); ?></p>
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

			<!-- 2. Mesures usine -->
			<div class="cp-trim-step" data-step-panel="usine">
				<p class="description"><?php esc_html_e( 'Cotes du manuel constructeur (mm). Touche Entrée = suspente suivante.', 'controle-parapente' ); ?></p>
				<div class="cp-trim-factory"></div>
			</div>

			<!-- 3. Mesures voile -->
			<div class="cp-trim-step" data-step-panel="voile">
				<div class="cp-grid cp-trim-settings">
					<p class="cp-field">
						<label for="cp-trim-initial-date"><?php esc_html_e( 'Date de la 1ère mesure', 'controle-parapente' ); ?></label>
						<input type="date" id="cp-trim-initial-date" name="cp[trim][initial_date]" value="<?php echo esc_attr( $trim['initial_date'] ); ?>" class="widefat" />
						<label class="cp-check"><input type="checkbox" id="cp-trim-locked" name="cp[trim][initial_locked]" value="1" <?php checked( $trim['initial_locked'], '1' ); ?> /> <?php esc_html_e( '1ère mesure figée', 'controle-parapente' ); ?></label>
					</p>
					<p class="cp-field">
						<label for="cp-trim-final-date"><?php esc_html_e( 'Date de la mesure finale', 'controle-parapente' ); ?></label>
						<input type="date" id="cp-trim-final-date" name="cp[trim][final_date]" value="<?php echo esc_attr( $trim['final_date'] ); ?>" class="widefat" />
						<button type="button" class="button cp-trim-copy"><?php esc_html_e( 'Copier la 1ère mesure dans les cases finales vides', 'controle-parapente' ); ?></button>
					</p>
				</div>
				<p class="description"><?php esc_html_e( 'Longueurs brutes lues sur le banc (mm), sans correction : l\'offset et les élévateurs s\'appliquent dans le résultat. Touche Entrée = case suivante dans la colonne.', 'controle-parapente' ); ?></p>
				<div class="cp-trim-measures"></div>
			</div>

			<!-- 4. Résultat -->
			<div class="cp-trim-step" data-step-panel="resultat">
				<div class="cp-trim-controls">
					<div class="cp-trim-control">
						<label for="cp-trim-offset"><?php esc_html_e( 'Offset de mesure', 'controle-parapente' ); ?></label>
						<div class="cp-stepper" data-step="1">
							<button type="button" class="cp-step-down" aria-label="−1 mm">−</button>
							<input type="text" inputmode="decimal" id="cp-trim-offset" name="cp[trim][offset]" value="<?php echo esc_attr( $trim['offset'] ); ?>" placeholder="0" />
							<button type="button" class="cp-step-up" aria-label="+1 mm">+</button>
							<span class="cp-unit">mm</span>
						</div>
						<span class="description"><?php esc_html_e( 'Ajouté à toutes les mesures voile (banc, maillons, tension…).', 'controle-parapente' ); ?></span>
					</div>
					<div class="cp-trim-control cp-trim-control--risers">
						<span class="cp-label"><?php esc_html_e( 'Réglage des élévateurs', 'controle-parapente' ); ?></span>
						<div class="cp-trim-risers"></div>
						<p>
							<button type="button" class="button cp-trim-suggest"><?php esc_html_e( 'Proposer le réglage', 'controle-parapente' ); ?></button>
							<button type="button" class="button-link cp-trim-reset"><?php esc_html_e( 'Remettre à 0', 'controle-parapente' ); ?></button>
						</p>
						<span class="description"><?php esc_html_e( 'Valeur en mm ajoutée à toute la rangée (+ = allonger, − = raccourcir). « Proposer » calcule le réglage qui ramène l\'écart moyen de chaque rangée à 0.', 'controle-parapente' ); ?></span>
					</div>
				</div>
				<p class="cp-trim-legend description">
					<?php echo esc_html( sprintf( __( 'Tolérance : ± %s mm. Résultat = mesure finale (ou 1ère si pas de finale) + offset + élévateur − cote usine.', 'controle-parapente' ), CP_Settings::get( 'trim_tolerance' ) ) ); ?>
				</p>
				<h4><?php esc_html_e( 'Décalage par groupe', 'controle-parapente' ); ?></h4>
				<div class="cp-trim-summary"></div>
				<h4><?php esc_html_e( 'Détail par suspente', 'controle-parapente' ); ?></h4>
				<div class="cp-trim-result"></div>
				<p>
					<label>
						<input type="checkbox" name="cp[trim_adjusted]" value="1" <?php checked( $trim_adjusted, '1' ); ?> />
						<?php esc_html_e( 'Aile recalée pendant le contrôle', 'controle-parapente' ); ?>
					</label>
				</p>
			</div>

			<script type="application/json" class="cp-trim-data"><?php
			echo wp_json_encode(
				array(
					'colors'    => $colors,
					'rows'      => self::rows(),
					'structure' => $trim['structure'],
					'factory'   => (object) $trim['factory'],
					'initial'   => (object) $trim['initial'],
					'final'     => (object) $trim['final'],
					'riser'     => (object) $trim['riser'],
				)
			);
			?></script>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Rapport                                                             */
	/* ------------------------------------------------------------------ */

	public static function render_certificate( array $trim, $trim_adjusted, $title = null ) {
		$trim     = self::normalize( $trim );
		$analysis = self::analyze( $trim );
		if ( ! $analysis['has_data'] ) {
			return;
		}
		$sides     = self::side_labels( $trim['sides'] );
		$both      = count( $sides ) > 1;
		$rows      = self::rows();
		$has_final = $analysis['has_final'];
		$has_riser = $analysis['has_riser'];
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
				if ( 0.0 !== $analysis['offset'] ) {
					$info[] = sprintf( __( 'offset de mesure : %s mm', 'controle-parapente' ), self::signed( $analysis['offset'] ) );
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

			<?php if ( $has_riser ) : ?>
				<h3><?php esc_html_e( 'Réglage des élévateurs', 'controle-parapente' ); ?></h3>
				<table class="cp-table cp-trim-risers-table">
					<thead><tr><th><?php esc_html_e( 'Élévateur', 'controle-parapente' ); ?></th><?php foreach ( $sides as $label ) : ?><th class="num"><?php echo esc_html( $both ? $label : __( 'Réglage', 'controle-parapente' ) ); ?></th><?php endforeach; ?></tr></thead>
					<tbody>
					<?php foreach ( $rows as $row => $label ) : ?>
						<?php
						if ( ! $trim['structure'][ $row ] ) {
							continue;
						}
						?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<?php foreach ( array_keys( $sides ) as $side ) : ?>
								<td class="num"><?php echo esc_html( isset( $trim['riser'][ $row ][ $side ] ) ? self::signed( (float) $trim['riser'][ $row ][ $side ] ) . ' mm' : '0' ); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Décalage par groupe', 'controle-parapente' ); ?></h3>
			<table class="cp-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Groupe', 'controle-parapente' ); ?></th>
						<?php if ( $both ) : ?><th><?php esc_html_e( 'Côté', 'controle-parapente' ); ?></th><?php endif; ?>
						<th class="num"><?php esc_html_e( 'Susp.', 'controle-parapente' ); ?></th>
						<th class="num"><?php echo esc_html( $has_final ? __( 'Écart moy. 1ère', 'controle-parapente' ) : __( 'Écart moyen', 'controle-parapente' ) ); ?></th>
						<?php if ( $has_final ) : ?><th class="num"><?php esc_html_e( 'Écart moy. finale', 'controle-parapente' ); ?></th><?php endif; ?>
						<?php if ( $has_riser ) : ?>
							<th class="num"><?php esc_html_e( 'Élévateur', 'controle-parapente' ); ?></th>
							<th class="num"><?php esc_html_e( 'Résultat', 'controle-parapente' ); ?></th>
						<?php endif; ?>
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
						<?php if ( $has_riser ) : ?>
							<td class="num"><?php echo esc_html( self::signed( $g['riser'] ) ); ?></td>
							<td class="num"><?php echo esc_html( self::signed( $g['result']['mean'] ) ); ?></td>
						<?php endif; ?>
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
							<th colspan="<?php echo esc_attr( count( $sets ) + ( $has_riser ? 1 : 0 ) ); ?>" class="center"><?php echo esc_html( $both ? $side_label : __( 'Écarts', 'controle-parapente' ) ); ?></th>
						<?php endforeach; ?>
					</tr>
					<tr>
						<?php foreach ( $sides as $side_label ) : ?>
							<?php foreach ( $sets as $label ) : ?><th class="num"><?php echo esc_html( $label ); ?></th><?php endforeach; ?>
							<?php if ( $has_riser ) : ?><th class="num"><?php esc_html_e( 'Résultat', 'controle-parapente' ); ?></th><?php endif; ?>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
				<?php
				$current = '';
				$cols    = 2 + count( $sides ) * ( count( $sets ) + ( $has_riser ? 1 : 0 ) );
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
							<?php if ( $has_riser ) : ?>
								<td class="num lvl-<?php echo esc_attr( $line['result'][ $side ]['level'] ); ?>"><strong><?php echo esc_html( self::signed( $line['result'][ $side ]['deviation'] ) ); ?></strong></td>
							<?php endif; ?>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<?php
	}
}
