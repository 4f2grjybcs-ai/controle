<?php
/**
 * Calage : structure du suspentage (groupes par rangée), 1ère mesure, mesure finale,
 * offset de correction et analyse des décalages par groupe.
 *
 * Données stockées dans $data['trim'] :
 * - sides          : 'both' (gauche + droite) ou 'one' (un seul côté)
 * - offset         : correction en mm ajoutée à toutes les mesures
 * - structure      : [ 'A' => [ 4, 4, 2 ], ... ] nombre de suspentes par groupe
 * - factory        : [ 'A1' => 7000, ... ] cotes usine
 * - initial, final : [ 'A1' => [ 'G' => 7012, 'D' => 7008 ], ... ] mesures brutes
 * - initial_date, final_date, initial_locked
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

	public static function side_labels( $sides ) {
		return 'one' === $sides
			? array( 'G' => __( 'Mesure', 'controle-parapente' ) )
			: array(
				'G' => __( 'Gauche', 'controle-parapente' ),
				'D' => __( 'Droite', 'controle-parapente' ),
			);
	}

	public static function defaults() {
		return array(
			'sides'          => 'both',
			'offset'         => '',
			'structure'      => array(
				'A' => array( 4, 4, 2 ),
				'B' => array( 4, 4, 2 ),
				'C' => array( 4, 4, 2 ),
				'D' => array(),
				'F' => array( 4, 4 ),
			),
			'factory'        => array(),
			'initial'        => array(),
			'final'          => array(),
			'initial_date'   => '',
			'final_date'     => '',
			'initial_locked' => '',
		);
	}

	/**
	 * Complète des données de calage enregistrées (ou d'un ancien format).
	 *
	 * @param mixed $trim Données brutes.
	 * @return array
	 */
	public static function normalize( $trim ) {
		if ( ! is_array( $trim ) || ! isset( $trim['structure'] ) ) {
			return self::defaults();
		}
		$trim = wp_parse_args( $trim, self::defaults() );
		foreach ( array_keys( self::rows() ) as $row ) {
			if ( ! isset( $trim['structure'][ $row ] ) || ! is_array( $trim['structure'][ $row ] ) ) {
				$trim['structure'][ $row ] = array();
			}
		}
		return $trim;
	}

	/**
	 * Liste des suspentes à partir de la structure.
	 * Numérotation continue par rangée (A1, A2, …) comme dans les plans constructeur.
	 *
	 * @param array $structure Structure.
	 * @return array Liste de [ id, row, group ].
	 */
	public static function lines( array $structure ) {
		$lines = array();
		foreach ( array_keys( self::rows() ) as $row ) {
			$n = 0;
			foreach ( isset( $structure[ $row ] ) ? (array) $structure[ $row ] : array() as $gi => $count ) {
				for ( $k = 0; $k < (int) $count; $k++ ) {
					++$n;
					$lines[] = array(
						'id'    => $row . $n,
						'row'   => $row,
						'group' => $gi + 1,
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
	 * Nettoie les données de calage envoyées par l'admin.
	 *
	 * @param mixed $raw Données brutes.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$raw  = is_array( $raw ) ? $raw : array();
		$trim = self::defaults();

		$trim['sides']          = isset( $raw['sides'] ) && 'one' === $raw['sides'] ? 'one' : 'both';
		$trim['offset']         = isset( $raw['offset'] ) ? self::num( $raw['offset'] ) : '';
		$trim['initial_date']   = CP_Controle::sanitize_date( isset( $raw['initial_date'] ) ? $raw['initial_date'] : '' );
		$trim['final_date']     = CP_Controle::sanitize_date( isset( $raw['final_date'] ) ? $raw['final_date'] : '' );
		$trim['initial_locked'] = ! empty( $raw['initial_locked'] ) ? '1' : '';

		foreach ( array_keys( self::rows() ) as $row ) {
			$groups = array();
			if ( isset( $raw['structure'][ $row ] ) && is_array( $raw['structure'][ $row ] ) ) {
				foreach ( array_slice( array_values( $raw['structure'][ $row ] ), 0, self::MAX_GROUPS ) as $count ) {
					$count = absint( $count );
					if ( $count > 0 ) {
						$groups[] = min( self::MAX_LINES, $count );
					}
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
	 * Calcule les longueurs corrigées, les écarts par suspente et par groupe.
	 *
	 * Mesure corrigée = mesure brute + offset ; écart = mesure corrigée − cote usine.
	 *
	 * @param array $trim Données de calage.
	 * @return array [ 'lines' => …, 'groups' => …, 'has_data' => bool ]
	 */
	public static function analyze( array $trim ) {
		$trim      = self::normalize( $trim );
		$offset    = '' === $trim['offset'] ? 0.0 : (float) $trim['offset'];
		$tolerance = (float) CP_Settings::get( 'trim_tolerance' );
		$sides     = array_keys( self::side_labels( $trim['sides'] ) );
		$lines     = array();
		$groups    = array();
		$has_data  = false;

		foreach ( self::lines( $trim['structure'] ) as $line ) {
			$id      = $line['id'];
			$factory = isset( $trim['factory'][ $id ] ) && '' !== $trim['factory'][ $id ] ? (float) $trim['factory'][ $id ] : null;
			$line['factory'] = $factory;

			foreach ( $sides as $side ) {
				$gkey = $line['row'] . '|' . $line['group'] . '|' . $side;
				if ( ! isset( $groups[ $gkey ] ) ) {
					$groups[ $gkey ] = array(
						'row'     => $line['row'],
						'group'   => $line['group'],
						'side'    => $side,
						'count'   => 0,
						'initial' => array(),
						'final'   => array(),
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
					}
					if ( null !== $dev ) {
						$groups[ $gkey ][ $set ][] = $dev;
					}
				}
			}
			$lines[] = $line;
		}

		foreach ( $groups as $key => $g ) {
			foreach ( array( 'initial', 'final' ) as $set ) {
				$devs = $g[ $set ];
				$groups[ $key ][ $set ] = array(
					'measured' => count( $devs ),
					'mean'     => $devs ? array_sum( $devs ) / count( $devs ) : null,
					'max'      => $devs ? self::max_abs( $devs ) : null,
				);
			}
			$mi = $groups[ $key ]['initial']['mean'];
			$mf = $groups[ $key ]['final']['mean'];

			$groups[ $key ]['correction'] = null === $mi ? null : -$mi;
			$groups[ $key ]['adjustment'] = ( null === $mi || null === $mf ) ? null : $mf - $mi;

			// L'état se base sur la mesure finale si elle existe, sinon sur la 1ère mesure.
			$ref                     = null !== $mf ? $groups[ $key ]['final'] : $groups[ $key ]['initial'];
			$groups[ $key ]['basis'] = null !== $mf ? 'final' : ( null !== $mi ? 'initial' : '' );
			$groups[ $key ]['level'] = null === $ref['max'] ? '' : self::level( $ref['max'], $tolerance );
		}

		return array(
			'lines'    => $lines,
			'groups'   => array_values( $groups ),
			'has_data' => $has_data,
			'offset'   => $offset,
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

	public static function group_label( $row, $group ) {
		$rows = self::rows();
		/* translators: 1: rangée (Rangée A, Freins…), 2: numéro du groupe. */
		return sprintf( __( '%1$s — groupe %2$d', 'controle-parapente' ), $rows[ $row ], $group );
	}

	/* ------------------------------------------------------------------ */
	/* Admin                                                               */
	/* ------------------------------------------------------------------ */

	public static function render_admin_box( array $trim, $trim_adjusted ) {
		$trim = self::normalize( $trim );
		?>
		<div class="cp-trim" data-tolerance="<?php echo esc_attr( CP_Settings::get( 'trim_tolerance' ) ); ?>">

			<h4><?php esc_html_e( '1. Structure du suspentage', 'controle-parapente' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Indiquez pour chaque rangée le nombre de groupes, puis le nombre de suspentes dans chaque groupe (ex. A : 3 groupes → 4, 4, 2). Mettez 0 groupe pour une rangée absente. Les suspentes sont numérotées A1, A2… dans l\'ordre des groupes.', 'controle-parapente' ); ?></p>
			<table class="widefat cp-trim-structure">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Rangée', 'controle-parapente' ); ?></th>
						<th><?php esc_html_e( 'Nb de groupes', 'controle-parapente' ); ?></th>
						<th><?php esc_html_e( 'Suspentes par groupe (1er, 2e, 3e…)', 'controle-parapente' ); ?></th>
						<th><?php esc_html_e( 'Total', 'controle-parapente' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( self::rows() as $row => $label ) : ?>
					<?php $groups = $trim['structure'][ $row ]; ?>
					<tr class="<?php echo 'F' === $row ? 'cp-trim-brakes' : ''; ?>" data-row="<?php echo esc_attr( $row ); ?>">
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td><input type="number" class="small-text cp-trim-group-count" min="0" max="<?php echo esc_attr( self::MAX_GROUPS ); ?>" value="<?php echo esc_attr( count( $groups ) ); ?>" /></td>
						<td class="cp-trim-group-inputs">
							<?php foreach ( $groups as $i => $count ) : ?>
								<label class="cp-trim-group">
									<span><?php echo esc_html( sprintf( /* translators: %d: numéro du groupe */ __( 'Gr. %d', 'controle-parapente' ), $i + 1 ) ); ?></span>
									<input type="number" min="1" max="<?php echo esc_attr( self::MAX_LINES ); ?>" name="cp[trim][structure][<?php echo esc_attr( $row ); ?>][]" value="<?php echo esc_attr( $count ); ?>" />
								</label>
							<?php endforeach; ?>
						</td>
						<td class="cp-trim-total"><?php echo esc_html( array_sum( $groups ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<div class="cp-grid cp-trim-settings">
				<p class="cp-field">
					<label for="cp-trim-sides"><?php esc_html_e( 'Côtés mesurés', 'controle-parapente' ); ?></label>
					<select id="cp-trim-sides" name="cp[trim][sides]" class="widefat">
						<option value="both" <?php selected( $trim['sides'], 'both' ); ?>><?php esc_html_e( 'Gauche et droite', 'controle-parapente' ); ?></option>
						<option value="one" <?php selected( $trim['sides'], 'one' ); ?>><?php esc_html_e( 'Un seul côté', 'controle-parapente' ); ?></option>
					</select>
				</p>
				<p class="cp-field">
					<label for="cp-trim-offset"><?php esc_html_e( 'Offset de mesure (mm)', 'controle-parapente' ); ?></label>
					<input type="number" step="0.5" id="cp-trim-offset" name="cp[trim][offset]" value="<?php echo esc_attr( $trim['offset'] ); ?>" class="widefat" placeholder="0" />
					<span class="description"><?php esc_html_e( 'Ajouté à toutes les mesures (1ère et finale) : maillons, banc, tension… Ex. −5 si le banc mesure 5 mm trop long.', 'controle-parapente' ); ?></span>
				</p>
				<p class="cp-field">
					<span class="cp-label"><?php esc_html_e( 'Tolérance', 'controle-parapente' ); ?></span>
					<?php echo esc_html( sprintf( '± %s mm', CP_Settings::get( 'trim_tolerance' ) ) ); ?>
					<span class="description"><?php esc_html_e( '(modifiable dans les réglages)', 'controle-parapente' ); ?></span>
				</p>
			</div>

			<h4><?php esc_html_e( '2. Mesures', 'controle-parapente' ); ?></h4>
			<div class="cp-grid cp-trim-settings">
				<p class="cp-field">
					<label for="cp-trim-initial-date"><?php esc_html_e( 'Date de la 1ère mesure', 'controle-parapente' ); ?></label>
					<input type="date" id="cp-trim-initial-date" name="cp[trim][initial_date]" value="<?php echo esc_attr( $trim['initial_date'] ); ?>" class="widefat" />
					<label class="cp-check"><input type="checkbox" id="cp-trim-locked" name="cp[trim][initial_locked]" value="1" <?php checked( $trim['initial_locked'], '1' ); ?> /> <?php esc_html_e( '1ère mesure figée (non modifiable)', 'controle-parapente' ); ?></label>
				</p>
				<p class="cp-field">
					<label for="cp-trim-final-date"><?php esc_html_e( 'Date de la mesure finale', 'controle-parapente' ); ?></label>
					<input type="date" id="cp-trim-final-date" name="cp[trim][final_date]" value="<?php echo esc_attr( $trim['final_date'] ); ?>" class="widefat" />
					<button type="button" class="button cp-trim-copy"><?php esc_html_e( 'Copier la 1ère mesure dans les cases finales vides', 'controle-parapente' ); ?></button>
				</p>
			</div>
			<p class="description"><?php esc_html_e( 'Saisissez les longueurs brutes lues sur le banc : l\'offset est appliqué automatiquement. Touche Entrée = case suivante dans la même colonne.', 'controle-parapente' ); ?></p>
			<div class="cp-trim-lines"></div>

			<h4><?php esc_html_e( '3. Décalage par groupe', 'controle-parapente' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Écart moyen des suspentes du groupe par rapport aux cotes usine (mesures corrigées de l\'offset). Correction suggérée = valeur à appliquer au groupe d\'après la 1ère mesure.', 'controle-parapente' ); ?></p>
			<div class="cp-trim-summary"></div>

			<p>
				<label>
					<input type="checkbox" name="cp[trim_adjusted]" value="1" <?php checked( $trim_adjusted, '1' ); ?> />
					<?php esc_html_e( 'Aile recalée pendant le contrôle', 'controle-parapente' ); ?>
				</label>
			</p>

			<script type="application/json" class="cp-trim-data"><?php echo wp_json_encode( array(
				'factory' => (object) $trim['factory'],
				'initial' => (object) $trim['initial'],
				'final'   => (object) $trim['final'],
			) ); ?></script>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Fiche imprimable                                                    */
	/* ------------------------------------------------------------------ */

	public static function render_certificate( array $trim, $trim_adjusted ) {
		$trim     = self::normalize( $trim );
		$analysis = self::analyze( $trim );
		if ( ! $analysis['has_data'] ) {
			return;
		}
		$sides      = self::side_labels( $trim['sides'] );
		$both       = count( $sides ) > 1;
		$rows       = self::rows();
		$has_final  = false;
		foreach ( $analysis['lines'] as $line ) {
			foreach ( $sides as $side => $label ) {
				if ( null !== $line['final'][ $side ]['raw'] ) {
					$has_final = true;
				}
			}
		}
		$sets = $has_final
			? array( 'initial' => __( '1ère mesure', 'controle-parapente' ), 'final' => __( 'Mesure finale', 'controle-parapente' ) )
			: array( 'initial' => __( 'Mesure', 'controle-parapente' ) );
		$cell = static function ( $data ) {
			printf( '<td class="num">%s</td>', esc_html( CP_Trim::length( $data['corrected'] ) ) );
			printf( '<td class="num lvl-%s">%s</td>', esc_attr( $data['level'] ), esc_html( CP_Trim::signed( $data['deviation'] ) ) );
		};
		?>
		<section>
			<h2><?php esc_html_e( 'Calage', 'controle-parapente' ); ?></h2>
			<p class="cp-small">
				<?php
				$info = array( sprintf( __( 'Tolérance : ± %s mm', 'controle-parapente' ), CP_Settings::get( 'trim_tolerance' ) ) );
				if ( 0.0 !== $analysis['offset'] ) {
					$info[] = sprintf( __( 'offset de mesure appliqué : %s mm', 'controle-parapente' ), self::signed( $analysis['offset'] ) );
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

			<h3><?php esc_html_e( 'Décalage par groupe', 'controle-parapente' ); ?></h3>
			<table class="cp-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Groupe', 'controle-parapente' ); ?></th>
						<?php if ( $both ) : ?><th><?php esc_html_e( 'Côté', 'controle-parapente' ); ?></th><?php endif; ?>
						<th class="num"><?php esc_html_e( 'Susp.', 'controle-parapente' ); ?></th>
						<th class="num"><?php echo esc_html( $has_final ? __( 'Écart moy. 1ère', 'controle-parapente' ) : __( 'Écart moyen', 'controle-parapente' ) ); ?></th>
						<?php if ( $has_final ) : ?>
							<th class="num"><?php esc_html_e( 'Écart moy. finale', 'controle-parapente' ); ?></th>
							<th class="num"><?php esc_html_e( 'Ajustement', 'controle-parapente' ); ?></th>
						<?php endif; ?>
						<th class="num"><?php esc_html_e( 'Écart max', 'controle-parapente' ); ?></th>
						<th><?php esc_html_e( 'État', 'controle-parapente' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $analysis['groups'] as $g ) : ?>
					<?php
					if ( ! $g['initial']['measured'] && ! $g['final']['measured'] ) {
						continue;
					}
					$ref = 'final' === $g['basis'] ? $g['final'] : $g['initial'];
					?>
					<tr class="<?php echo 'F' === $g['row'] ? 'cp-brakes' : ''; ?>">
						<td><?php echo esc_html( self::group_label( $g['row'], $g['group'] ) ); ?></td>
						<?php if ( $both ) : ?><td><?php echo esc_html( $sides[ $g['side'] ] ); ?></td><?php endif; ?>
						<td class="num"><?php echo esc_html( $g['count'] ); ?></td>
						<td class="num"><?php echo esc_html( self::signed( $g['initial']['mean'] ) ); ?></td>
						<?php if ( $has_final ) : ?>
							<td class="num"><?php echo esc_html( self::signed( $g['final']['mean'] ) ); ?></td>
							<td class="num"><?php echo esc_html( self::signed( $g['adjustment'] ) ); ?></td>
						<?php endif; ?>
						<td class="num lvl-<?php echo esc_attr( $g['level'] ); ?>"><?php echo esc_html( self::signed( $ref['max'] ) ); ?></td>
						<td class="lvl-<?php echo esc_attr( $g['level'] ); ?>"><?php echo esc_html( 'ok' === $g['level'] ? __( 'Conforme', 'controle-parapente' ) : ( 'bad' === $g['level'] ? __( 'Hors tolérance', 'controle-parapente' ) : '—' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Détail par suspente (longueurs corrigées, mm)', 'controle-parapente' ); ?></h3>
			<table class="cp-table cp-trim-detail">
				<thead>
					<tr>
						<th rowspan="2"><?php esc_html_e( 'Susp.', 'controle-parapente' ); ?></th>
						<th rowspan="2" class="num"><?php esc_html_e( 'Usine', 'controle-parapente' ); ?></th>
						<?php foreach ( $sets as $label ) : ?>
							<?php foreach ( $sides as $side_label ) : ?>
								<th colspan="2" class="center"><?php echo esc_html( $both ? $label . ' ' . $side_label : $label ); ?></th>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</tr>
					<tr>
						<?php foreach ( $sets as $label ) : ?>
							<?php foreach ( $sides as $side_label ) : ?>
								<th class="num"><?php esc_html_e( 'Long.', 'controle-parapente' ); ?></th>
								<th class="num"><?php esc_html_e( 'Écart', 'controle-parapente' ); ?></th>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
				<?php
				$current = '';
				foreach ( $analysis['lines'] as $line ) :
					$any = false;
					foreach ( array_keys( $sets ) as $set ) {
						foreach ( $sides as $side => $side_label ) {
							$any = $any || null !== $line[ $set ][ $side ]['raw'];
						}
					}
					if ( ! $any ) {
						continue;
					}
					$gkey = $line['row'] . $line['group'];
					if ( $gkey !== $current ) :
						$current = $gkey;
						?>
						<tr class="cp-group-head"><td colspan="<?php echo esc_attr( 2 + 2 * count( $sets ) * count( $sides ) ); ?>"><?php echo esc_html( self::group_label( $line['row'], $line['group'] ) ); ?></td></tr>
					<?php endif; ?>
					<tr>
						<td><?php echo esc_html( $line['id'] ); ?></td>
						<td class="num"><?php echo esc_html( self::length( $line['factory'] ) ); ?></td>
						<?php
						foreach ( array_keys( $sets ) as $set ) {
							foreach ( $sides as $side => $side_label ) {
								$cell( $line[ $set ][ $side ] );
							}
						}
						?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<?php
	}
}
