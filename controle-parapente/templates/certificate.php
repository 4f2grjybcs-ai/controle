<?php
/**
 * Gabarit du rapport de contrôle (structure du PMA Standard « Periodical Inspection of Paragliders »).
 *
 * Organisation : identification de l'atelier, synthèse (curseur d'état global et
 * interprétation des trois inspections), puis détail des inspections visuelle,
 * mécanique (porosité, déchirure, résistance des suspentes) et géométrique (calage).
 * Un test non réalisé est signalé clairement, avec la préconisation du constructeur.
 *
 * Variables disponibles : $post_id, $d (données), $settings, $is_admin.
 * Peut être surchargé en copiant ce fichier dans votre thème :
 * wp-content/themes/votre-theme/controle-parapente/certificate.php
 *
 * @package ControleParapente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$override = locate_template( 'controle-parapente/certificate.php' );
if ( $override && __FILE__ !== $override ) {
	include $override;
	return;
}

$logo        = CP_Settings::logo_url( 'medium' );
$types       = CP_Controle::equipment_types();
$certs       = CP_Controle::certifications();
$verdicts    = CP_Controle::verdicts();
$states      = CP_Controle::visual_states();
$services    = array_intersect_key( CP_Controle::services(), array_flip( (array) $d['services'] ) );
$itypes      = CP_Controle::inspection_types();
$itype       = isset( $itypes[ $d['inspection_type'] ] ) ? $itypes[ $d['inspection_type'] ]['label'] : '';
$done        = CP_Controle::tests_done( $d );
$t           = CP_Controle::thresholds( $d );
$state_now   = CP_Controle::global_state( $d );
$state_basis = CP_Controle::state_basis( $d );
$state_list  = CP_Controle::state_labels();

// Sur le rapport, on n'affiche que les lignes réellement mesurées.
$measured      = static function ( $rows, $field ) {
	return array_values(
		array_filter(
			(array) $rows,
			static function ( $row ) use ( $field ) {
				return isset( $row[ $field ] ) && '' !== $row[ $field ];
			}
		)
	);
};
$d['porosity'] = $measured( $d['porosity'], 'value' );
$d['tear']     = $measured( $d['tear'], 'value' );
$d['lines']    = $measured( $d['lines'], 'measured' );

$fmt = static function ( $n ) {
	return '' === $n || null === $n ? '—' : number_format_i18n( (float) $n, floor( (float) $n ) == (float) $n ? 0 : 1 );
};

$is_done  = static function ( $test ) use ( $done ) {
	return in_array( $test, $done, true );
};
$not_done = static function ( $test ) use ( $d, $settings ) {
	$note = isset( $d['not_done_notes'][ $test ] ) ? $d['not_done_notes'][ $test ] : '';
	echo '<div class="cp-notdone"><strong>' . esc_html( $settings['not_done_text'] ) . '</strong>';
	if ( $note ) {
		echo '<span>' . esc_html( $note ) . '</span>';
	}
	echo '</div>';
};

// État de chaque inspection pour la synthèse.
$sections = array(
	'visual'     => array( $settings['title_visual'], array( 'V' ), $d['interp_visual'] ),
	'mechanical' => array( $settings['title_mechanical'], array( 'P', 'T', 'L' ), $d['interp_mechanical'] ),
	'geometric'  => array( $settings['title_geometric'], array( 'G' ), $d['interp_geometric'] ),
);
$section_status = static function ( $tests ) use ( $done ) {
	$n = count( array_intersect( $tests, $done ) );
	return 0 === $n ? 'none' : ( count( $tests ) === $n ? 'full' : 'partial' );
};
$status_labels = array(
	'full'    => __( 'Réalisée', 'controle-parapente' ),
	'partial' => __( 'Partielle', 'controle-parapente' ),
	'none'    => $settings['not_done_text'],
);
$level_text = static function ( $level, $bad = null, $warn = null ) {
	$labels = array(
		'ok'   => __( 'Bon', 'controle-parapente' ),
		'warn' => null === $warn ? __( 'Acceptable', 'controle-parapente' ) : $warn,
		'bad'  => null === $bad ? __( 'Échec', 'controle-parapente' ) : $bad,
	);
	return isset( $labels[ $level ] ) ? $labels[ $level ] : '—';
};
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $settings['report_title'] . ' ' . $d['reference'] ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( CP_URL . 'assets/css/certificate.css?ver=' . CP_VERSION ); ?>" />
	<style>:root { --cp-accent: <?php echo esc_html( $settings['accent_color'] ); ?>; }</style>
</head>
<body>
	<div class="cp-toolbar">
		<button type="button" onclick="window.print()"><?php esc_html_e( 'Imprimer / enregistrer en PDF', 'controle-parapente' ); ?></button>
	</div>

	<main class="cp-sheet">
		<header class="cp-head">
			<div class="cp-identity">
				<?php if ( $logo ) : ?>
					<img class="cp-logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $settings['workshop_name'] ); ?>" />
				<?php else : ?>
					<span class="cp-mark" aria-hidden="true"><?php echo esc_html( mb_substr( $settings['workshop_name'], 0, 1 ) ); ?></span>
				<?php endif; ?>
				<div>
					<strong class="cp-workshop-name"><?php echo esc_html( $settings['workshop_name'] ); ?></strong>
					<?php if ( $settings['manager_name'] ) : ?>
						<span class="cp-small"><?php echo esc_html( sprintf( __( 'Responsable : %s', 'controle-parapente' ), $settings['manager_name'] ) ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<address class="cp-contact">
				<?php if ( $settings['workshop_address'] ) : ?>
					<span><?php echo nl2br( esc_html( $settings['workshop_address'] ) ); ?></span>
				<?php endif; ?>
				<?php foreach ( array( 'workshop_phone', 'workshop_email' ) as $key ) : ?>
					<?php if ( $settings[ $key ] ) : ?>
						<span><?php echo esc_html( $settings[ $key ] ); ?></span>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ( $settings['workshop_website'] ) : ?>
					<span><?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $settings['workshop_website'] ) ) ); ?></span>
				<?php endif; ?>
				<?php if ( $settings['workshop_approval'] ) : ?>
					<span><?php echo esc_html( $settings['workshop_approval'] ); ?></span>
				<?php endif; ?>
				<?php if ( $settings['insurance_policy'] ) : ?>
					<span><?php echo esc_html( trim( sprintf( __( 'Assurance RC pro %1$s n° %2$s', 'controle-parapente' ), $settings['insurer'], $settings['insurance_policy'] ) ) ); ?></span>
				<?php endif; ?>
			</address>
		</header>

		<section class="cp-hero">
			<p class="cp-eyebrow"><?php echo esc_html( $settings['report_title'] ); ?> · <?php echo esc_html( $d['reference'] ); ?></p>
			<h1><?php echo esc_html( CP_Controle::equipment_label( $d ) ); ?></h1>
			<p class="cp-for">
				<?php
				$line = array();
				if ( $itype ) {
					$line[] = '<span class="cp-type">' . esc_html( $itype ) . '</span>';
				}
				if ( $d['pilot_name'] ) {
					$line[] = esc_html( sprintf( __( 'Préparé pour %s', 'controle-parapente' ), $d['pilot_name'] ) );
				}
				echo implode( ' ', $line ); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé ci-dessus.
				?>
			</p>
			<?php if ( $settings['report_intro'] ) : ?>
				<p class="cp-intro"><?php echo nl2br( esc_html( $settings['report_intro'] ) ); ?></p>
			<?php endif; ?>
		</section>

		<section class="cp-verdict-box cp-verdict-<?php echo esc_attr( $d['verdict'] ? $d['verdict'] : 'none' ); ?>">
			<div class="cp-verdict-main">
				<span class="cp-small"><?php esc_html_e( 'Résultat', 'controle-parapente' ); ?></span>
				<strong><?php echo esc_html( $d['verdict'] ? $verdicts[ $d['verdict'] ] : __( 'Contrôle en cours', 'controle-parapente' ) ); ?></strong>
			</div>
			<div>
				<span class="cp-small"><?php esc_html_e( 'Date du contrôle', 'controle-parapente' ); ?></span>
				<strong><?php echo esc_html( CP_Controle::format_date( $d['check_date'] ) ); ?></strong>
			</div>
			<div>
				<span class="cp-small"><?php esc_html_e( 'Prochain contrôle conseillé', 'controle-parapente' ); ?></span>
				<strong><?php echo esc_html( CP_Controle::format_date( $d['next_date'] ) ); ?></strong>
				<?php if ( '' !== $d['next_hours'] ) : ?>
					<span class="cp-small"><?php echo esc_html( sprintf( __( 'ou à %s h de vol (le premier des deux termes)', 'controle-parapente' ), $d['next_hours'] ) ); ?></span>
				<?php endif; ?>
			</div>
		</section>

		<?php if ( CP_Controle::is_partial( $d ) ) : ?>
			<p class="cp-partial"><?php echo esc_html( $settings['partial_warning'] ); ?></p>
		<?php endif; ?>

		<!-- Synthèse -->
		<section class="cp-synthesis">
			<h2><?php echo esc_html( $settings['state_title'] ); ?></h2>
			<?php if ( null !== $state_now ) : ?>
				<?php $count = count( $state_list ); ?>
				<div class="cp-cursor" role="img" aria-label="<?php echo esc_attr( $state_list[ $state_now ] ); ?>">
					<?php foreach ( $state_list as $i => $label ) : ?>
						<?php $hue = $count > 1 ? round( 125 - ( 115 * $i / ( $count - 1 ) ) ) : 125; ?>
						<div class="cp-cursor-step<?php echo $state_now === $i ? ' is-current' : ''; ?>" style="--hue:<?php echo esc_attr( $hue ); ?>">
							<span class="cp-cursor-bar"></span>
							<span class="cp-cursor-label"><?php echo esc_html( $label ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
				<?php if ( $state_basis['labels'] ) : ?>
					<p class="cp-state-basis">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: liste des tests */
								$state_basis['complete'] ? __( 'Établi à partir de l\'ensemble des tests : %s.', 'controle-parapente' ) : __( 'Établi à partir des tests réalisés lors de ce contrôle : %s.', 'controle-parapente' ),
								implode( ', ', array_map( 'mb_strtolower', $state_basis['labels'] ) )
							)
						);
						?>
					</p>
				<?php endif; ?>
				<p class="cp-small"><?php echo esc_html( $settings['state_help'] ); ?></p>
			<?php else : ?>
				<p class="cp-state-na"><?php echo esc_html( $settings['state_unavailable'] ); ?></p>
			<?php endif; ?>

			<div class="cp-summary-cards">
				<?php foreach ( $sections as $key => $section ) : ?>
					<?php $status = $section_status( $section[1] ); ?>
					<div class="cp-summary-card cp-summary-card--<?php echo esc_attr( $status ); ?>">
						<div class="cp-summary-head">
							<strong><?php echo esc_html( $section[0] ); ?></strong>
							<span class="cp-pill"><?php echo esc_html( $status_labels[ $status ] ); ?></span>
						</div>
						<p><?php echo $section[2] ? nl2br( esc_html( $section[2] ) ) : '<span class="cp-small">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<?php if ( $d['comments'] ) : ?>
			<section class="cp-note">
				<h2><?php echo esc_html( $settings['note_title'] ); ?></h2>
				<p><?php echo nl2br( esc_html( $d['comments'] ) ); ?></p>
				<?php if ( $d['technician'] ) : ?>
					<p class="cp-sign">— <?php echo esc_html( $d['technician'] ); ?></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<section class="cp-cols">
			<div>
				<h2><?php esc_html_e( 'Équipement', 'controle-parapente' ); ?></h2>
				<table class="cp-kv">
					<?php
					$equipment = array(
						__( 'Type', 'controle-parapente' )            => $types[ $d['equipment_type'] ] ?? '',
						__( 'Marque / modèle', 'controle-parapente' ) => trim( $d['brand'] . ' ' . $d['model'] ),
						__( 'Taille', 'controle-parapente' )          => $d['size'],
						__( 'N° de série', 'controle-parapente' )     => $d['serial'],
						__( 'Année', 'controle-parapente' )           => $d['year'],
						__( 'Homologation', 'controle-parapente' )    => $d['certification'] ? ( $certs[ $d['certification'] ] ?? '' ) : '',
						__( 'PTV', 'controle-parapente' )             => $d['weight_range'] ? $d['weight_range'] . ' kg' : '',
						__( 'Couleurs', 'controle-parapente' )        => $d['color'],
						__( 'Heures de vol', 'controle-parapente' )   => $d['flight_hours'],
						__( 'Dernier contrôle', 'controle-parapente' ) => $d['last_check'] ? CP_Controle::format_date( $d['last_check'] ) : '',
						__( 'Contrôle de conformité constructeur', 'controle-parapente' ) => $d['conformity_date'] ? CP_Controle::format_date( $d['conformity_date'] ) : '',
						__( 'Consignes de sécurité', 'controle-parapente' ) => $d['safety_notice'] ? __( 'vérifiées', 'controle-parapente' ) : '',
					);
					foreach ( array_filter( $equipment, 'strlen' ) as $label => $value ) :
						?>
						<tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $value ); ?></td></tr>
					<?php endforeach; ?>
				</table>
			</div>
			<div>
				<h2><?php esc_html_e( 'Propriétaire', 'controle-parapente' ); ?></h2>
				<table class="cp-kv">
					<tr><th><?php esc_html_e( 'Nom', 'controle-parapente' ); ?></th><td><?php echo esc_html( $d['pilot_name'] ); ?></td></tr>
					<?php if ( $is_admin ) : ?>
						<tr><th><?php esc_html_e( 'E-mail', 'controle-parapente' ); ?></th><td><?php echo esc_html( $d['email'] ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Téléphone', 'controle-parapente' ); ?></th><td><?php echo esc_html( $d['phone'] ); ?></td></tr>
					<?php endif; ?>
				</table>
				<?php if ( $services ) : ?>
					<h2><?php esc_html_e( 'Prestations', 'controle-parapente' ); ?></h2>
					<p><?php echo esc_html( implode( ', ', $services ) ); ?></p>
				<?php endif; ?>
				<?php if ( $d['technician'] ) : ?>
					<h2><?php esc_html_e( 'Contrôleur', 'controle-parapente' ); ?></h2>
					<p><?php echo esc_html( $d['technician'] ); ?></p>
				<?php endif; ?>
			</div>
		</section>

		<!-- Inspection visuelle -->
		<section class="cp-inspection">
			<h2><?php echo esc_html( $settings['title_visual'] ); ?></h2>
			<?php
			$visual = array_filter(
				(array) $d['visual'],
				static function ( $v ) {
					return ! empty( $v['state'] ) || ! empty( $v['comment'] );
				}
			);
			?>
			<?php if ( ! $is_done( 'V' ) ) : ?>
				<?php $not_done( 'V' ); ?>
			<?php elseif ( $visual ) : ?>
				<table class="cp-table">
					<thead><tr><th><?php esc_html_e( 'Point contrôlé', 'controle-parapente' ); ?></th><th><?php esc_html_e( 'État', 'controle-parapente' ); ?></th><th><?php esc_html_e( 'Commentaire', 'controle-parapente' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( CP_Controle::visual_items() as $key => $label ) : ?>
						<?php
						if ( ! isset( $visual[ $key ] ) ) {
							continue;
						}
						$state = $visual[ $key ]['state'];
						?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td class="st-<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $states[ $state ] ?? '' ); ?></td>
							<td><?php echo esc_html( $visual[ $key ]['comment'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="cp-small">—</p>
			<?php endif; ?>
		</section>

		<!-- Inspection mécanique -->
		<section class="cp-inspection">
			<h2><?php echo esc_html( $settings['title_mechanical'] ); ?></h2>
			<p class="cp-small"><?php echo esc_html( sprintf( __( 'Seuils utilisés : %s.', 'controle-parapente' ), $t['source_label'] ) ); ?></p>

			<h3><?php esc_html_e( 'Porosité du tissu', 'controle-parapente' ); ?></h3>
			<?php if ( ! $is_done( 'P' ) ) : ?>
				<?php $not_done( 'P' ); ?>
			<?php elseif ( $d['porosity'] ) : ?>
				<?php
				// PMA 5.2 : moyenne de chaque zone en l/m²/min sous 20 mbar ; < 360 Bon, 360-540 Acceptable, > 540 Échec.
				$seconds = 's' === $t['porosity_unit'];
				$zones   = CP_Controle::porosity_zones( $d['porosity'], $t );
				$values  = ! empty( $settings['porosity_show_values'] );
				?>
				<table class="cp-table">
					<thead><tr>
						<th><?php esc_html_e( 'Zone', 'controle-parapente' ); ?></th>
						<th class="num"><?php esc_html_e( 'Mesures', 'controle-parapente' ); ?></th>
						<?php if ( $values ) : ?><th class="num"><?php esc_html_e( 'Moyenne (l/m²/min)', 'controle-parapente' ); ?></th><?php endif; ?>
						<th><?php esc_html_e( 'Évaluation', 'controle-parapente' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $zones as $zone ) : ?>
						<tr>
							<td><?php echo esc_html( $zone['zone'] ); ?></td>
							<td class="num"><?php echo esc_html( $zone['n'] ); ?></td>
							<?php if ( $values ) : ?><td class="num"><?php echo esc_html( $fmt( round( $zone['mean'] ) ) ); ?></td><?php endif; ?>
							<td class="lvl-<?php echo esc_attr( $zone['level'] ); ?>"><?php echo esc_html( $level_text( $zone['level'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="cp-small">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: seuil bon, 2: seuil échec */
							__( 'Extrados, entre 5 et 30 %% de la corde, sous 20 mbar, 4 zones sur l\'envergure. Bon : moins de %1$s l/m²/min ; Acceptable : de %1$s à %2$s ; Échec : plus de %2$s.', 'controle-parapente' ),
							$fmt( $t['porosity_alert'] ),
							$fmt( $t['porosity_reform'] )
						) . ( $seconds ? ' ' . sprintf( __( 'Conversion : l/m²/min = %s ÷ temps en secondes.', 'controle-parapente' ), $fmt( $t['porosity_factor'] ) ) : '' )
					);
					?>
				</p>
			<?php else : ?>
				<p class="cp-small">—</p>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Résistance à la déchirure (Bettsomètre)', 'controle-parapente' ); ?></h3>
			<?php if ( ! $is_done( 'T' ) ) : ?>
				<?php $not_done( 'T' ); ?>
			<?php elseif ( $d['tear'] ) : ?>
				<table class="cp-table">
					<thead><tr><th><?php esc_html_e( 'Position du point de mesure', 'controle-parapente' ); ?></th><th class="num"><?php esc_html_e( 'Force (daN)', 'controle-parapente' ); ?></th><th><?php esc_html_e( 'Évaluation', 'controle-parapente' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $d['tear'] as $row ) : ?>
						<?php $level = CP_Controle::tear_level( $row['value'], $t ); ?>
						<tr>
							<td><?php echo esc_html( $row['zone'] ); ?></td>
							<td class="num"><?php echo esc_html( number_format_i18n( (float) $row['value'], 2 ) ); ?></td>
							<td class="lvl-<?php echo esc_attr( $level ); ?>"><?php echo esc_html( $level_text( $level ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="cp-small"><?php echo esc_html( sprintf( __( 'Rupture du ripstop : Échec sous %1$s daN ; Acceptable de %1$s à %2$s daN ; Bon au-delà.', 'controle-parapente' ), number_format_i18n( $t['tear_reform'], 1 ), number_format_i18n( $t['tear_good'], 1 ) ) ); ?></p>
			<?php elseif ( $d['fabric_strength'] ) : ?>
				<p><?php echo esc_html( $d['fabric_strength'] ); ?></p>
			<?php else : ?>
				<p class="cp-small">—</p>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Résistance des suspentes', 'controle-parapente' ); ?></h3>
			<?php if ( ! $is_done( 'L' ) ) : ?>
				<?php $not_done( 'L' ); ?>
			<?php elseif ( $d['lines'] ) : ?>
				<?php
				$levels     = CP_Controle::line_levels();
				?>
				<table class="cp-table">
					<thead><tr>
						<th><?php esc_html_e( 'Suspente testée', 'controle-parapente' ); ?></th>
						<th class="num"><?php esc_html_e( 'À neuf (daN)', 'controle-parapente' ); ?></th>
						<th class="num"><?php esc_html_e( 'Rupture (daN)', 'controle-parapente' ); ?></th>
						<th class="num"><?php esc_html_e( 'Minimum (daN)', 'controle-parapente' ); ?></th>
						<th class="num"><?php esc_html_e( '% du neuf', 'controle-parapente' ); ?></th>
						<th><?php esc_html_e( 'Évaluation', 'controle-parapente' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $d['lines'] as $row ) : ?>
						<?php
						$level = CP_Controle::line_level( $row['measured'], $row['minimum'] );
						$new   = isset( $row['new'] ) && is_numeric( $row['new'] ) && (float) $row['new'] > 0 ? (float) $row['new'] : null;
						$lvl   = isset( $row['level'], $levels[ $row['level'] ] ) ? $levels[ $row['level'] ] : '';
						?>
						<tr>
							<td><?php echo esc_html( $row['line'] ); ?><?php if ( $lvl && ! preg_match( '/niveau/iu', $row['line'] ) ) : ?><span class="cp-small"><?php echo esc_html( $lvl ); ?></span><?php endif; ?></td>
							<td class="num"><?php echo esc_html( null === $new ? '—' : $fmt( $new ) ); ?></td>
							<td class="num"><?php echo esc_html( $fmt( $row['measured'] ) ); ?></td>
							<td class="num"><?php echo esc_html( $fmt( $row['minimum'] ) ); ?></td>
							<td class="num"><?php echo esc_html( null !== $new && is_numeric( $row['measured'] ) ? round( (float) $row['measured'] / $new * 100 ) . ' %' : '—' ); ?></td>
							<td class="lvl-<?php echo esc_attr( $level ); ?>"><?php echo esc_html( 'ok' === $level ? __( 'Conforme', 'controle-parapente' ) : ( 'bad' === $level ? __( 'Échec', 'controle-parapente' ) : '—' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="cp-small">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: coef fournisseur, 2: coef aramide, 3: coef dyneema */
							__( 'Minimum (PMA 5.4) = valeur à neuf × source (constructeur 1,00 ; fournisseur %1$s) × matière (aramide / Technora / Vectran %2$s ; Dyneema %3$s), sauf minimum spécifique donné par le constructeur. Les suspentes testées ont été remplacées à l\'identique.', 'controle-parapente' ),
							CP_Settings::get( 'line_source_supplier' ),
							CP_Settings::get( 'line_coeff_aramid' ),
							CP_Settings::get( 'line_coeff_dyneema' )
						)
					);
					?>
				</p>
			<?php else : ?>
				<p class="cp-small">—</p>
			<?php endif; ?>
		</section>

		<!-- Inspection géométrique -->
		<?php if ( ! $is_done( 'G' ) ) : ?>
			<section class="cp-inspection">
				<h2><?php echo esc_html( $settings['title_geometric'] ); ?></h2>
				<?php $not_done( 'G' ); ?>
			</section>
		<?php else : ?>
			<?php CP_Trim::render_certificate( $d['trim'], $d['trim_adjusted'], $settings['title_geometric'], $is_admin ); ?>
		<?php endif; ?>

		<?php if ( $d['repairs'] ) : ?>
			<section>
				<h2><?php esc_html_e( 'Réparations / pièces remplacées', 'controle-parapente' ); ?></h2>
				<p><?php echo nl2br( esc_html( $d['repairs'] ) ); ?></p>
			</section>
		<?php endif; ?>

		<?php if ( $is_admin && $d['internal_notes'] ) : ?>
			<section class="cp-internal">
				<h2><?php esc_html_e( 'Notes internes (non communiquées au client)', 'controle-parapente' ); ?></h2>
				<p><?php echo nl2br( esc_html( $d['internal_notes'] ) ); ?></p>
			</section>
		<?php endif; ?>

		<footer class="cp-foot">
			<div class="cp-signature">
				<span class="cp-small"><?php echo esc_html( $settings['signature_label'] ); ?></span>
				<?php if ( $d['technician'] || $d['check_date'] ) : ?>
					<span class="cp-signature-who"><?php echo esc_html( trim( $d['technician'] . ( $d['check_date'] ? ' — ' . CP_Controle::format_date( $d['check_date'] ) : '' ), ' —' ) ); ?></span>
				<?php endif; ?>
			</div>
			<div class="cp-foot-text">
				<p class="cp-thanks"><?php echo esc_html( $settings['thanks_text'] ); ?></p>
				<p class="cp-small"><?php echo nl2br( esc_html( $settings['certificate_footer'] ) ); ?></p>
				<p class="cp-small">
					<?php
					echo esc_html(
						implode(
							' · ',
							array_filter(
								array(
									$settings['workshop_name'],
									$settings['manager_name'],
									str_replace( array( "\r\n", "\n" ), ', ', $settings['workshop_address'] ),
									$settings['workshop_phone'],
									$settings['workshop_email'],
								)
							)
						)
					);
					?>
				</p>
			</div>
		</footer>
	</main>
</body>
</html>
