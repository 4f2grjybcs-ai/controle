<?php
/**
 * Gabarit de la fiche de contrôle imprimable.
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

$types        = CP_Controle::equipment_types();
$certs        = CP_Controle::certifications();
$verdicts     = CP_Controle::verdicts();
$states       = CP_Controle::visual_states();
$services     = array_intersect_key( CP_Controle::services(), array_flip( (array) $d['services'] ) );
$level_labels = array(
	'ok'   => __( 'Conforme', 'controle-parapente' ),
	'warn' => __( 'À surveiller', 'controle-parapente' ),
	'bad'  => __( 'Non conforme', 'controle-parapente' ),
);
// Sur la fiche, on n'affiche que les lignes réellement mesurées.
$measured     = static function ( $rows, $field ) {
	return array_filter(
		(array) $rows,
		static function ( $row ) use ( $field ) {
			return isset( $row[ $field ] ) && '' !== $row[ $field ];
		}
	);
};
$d['porosity'] = $measured( $d['porosity'], 'value' );
$d['lines']    = $measured( $d['lines'], 'measured' );
$fmt          = static function ( $n ) {
	return '' === $n ? '—' : number_format_i18n( (float) $n, floor( (float) $n ) == (float) $n ? 0 : 1 );
};
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( sprintf( __( 'Fiche de contrôle %s', 'controle-parapente' ), $d['reference'] ) ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( CP_URL . 'assets/css/certificate.css?ver=' . CP_VERSION ); ?>" />
</head>
<body>
	<div class="cp-toolbar">
		<button type="button" onclick="window.print()"><?php esc_html_e( 'Imprimer / enregistrer en PDF', 'controle-parapente' ); ?></button>
	</div>

	<main class="cp-sheet">
		<header class="cp-head">
			<div>
				<h1><?php esc_html_e( 'Fiche de contrôle', 'controle-parapente' ); ?></h1>
				<p class="cp-ref"><?php echo esc_html( $d['reference'] ); ?></p>
			</div>
			<div class="cp-workshop">
				<strong><?php echo esc_html( $settings['workshop_name'] ); ?></strong><br />
				<?php echo nl2br( esc_html( $settings['workshop_address'] ) ); ?>
				<?php if ( $settings['workshop_phone'] ) : ?>
					<br /><?php echo esc_html( $settings['workshop_phone'] ); ?>
				<?php endif; ?>
				<?php if ( $settings['workshop_approval'] ) : ?>
					<br /><?php echo esc_html( sprintf( __( 'Agrément : %s', 'controle-parapente' ), $settings['workshop_approval'] ) ); ?>
				<?php endif; ?>
			</div>
		</header>

		<section class="cp-verdict-box cp-verdict-<?php echo esc_attr( $d['verdict'] ? $d['verdict'] : 'none' ); ?>">
			<div>
				<span class="cp-small"><?php esc_html_e( 'Résultat', 'controle-parapente' ); ?></span>
				<strong><?php echo esc_html( $d['verdict'] ? $verdicts[ $d['verdict'] ] : __( 'En cours', 'controle-parapente' ) ); ?></strong>
			</div>
			<div>
				<span class="cp-small"><?php esc_html_e( 'Date du contrôle', 'controle-parapente' ); ?></span>
				<strong><?php echo esc_html( CP_Controle::format_date( $d['check_date'] ) ); ?></strong>
			</div>
			<div>
				<span class="cp-small"><?php esc_html_e( 'Prochain contrôle', 'controle-parapente' ); ?></span>
				<strong><?php echo esc_html( CP_Controle::format_date( $d['next_date'] ) ); ?></strong>
			</div>
		</section>

		<section class="cp-cols">
			<div>
				<h2><?php esc_html_e( 'Équipement', 'controle-parapente' ); ?></h2>
				<table class="cp-kv">
					<tr><th><?php esc_html_e( 'Type', 'controle-parapente' ); ?></th><td><?php echo esc_html( $types[ $d['equipment_type'] ] ?? '' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Marque / modèle', 'controle-parapente' ); ?></th><td><?php echo esc_html( trim( $d['brand'] . ' ' . $d['model'] ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Taille', 'controle-parapente' ); ?></th><td><?php echo esc_html( $d['size'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'N° de série', 'controle-parapente' ); ?></th><td><?php echo esc_html( $d['serial'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Année', 'controle-parapente' ); ?></th><td><?php echo esc_html( $d['year'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Homologation', 'controle-parapente' ); ?></th><td><?php echo esc_html( $certs[ $d['certification'] ] ?? '' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'PTV', 'controle-parapente' ); ?></th><td><?php echo esc_html( $d['weight_range'] ? $d['weight_range'] . ' kg' : '' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Heures de vol', 'controle-parapente' ); ?></th><td><?php echo esc_html( $d['flight_hours'] ); ?></td></tr>
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
				<h2><?php esc_html_e( 'Prestations', 'controle-parapente' ); ?></h2>
				<p><?php echo esc_html( implode( ', ', $services ) ); ?></p>
				<?php if ( $d['technician'] ) : ?>
					<p><?php echo esc_html( sprintf( __( 'Contrôleur : %s', 'controle-parapente' ), $d['technician'] ) ); ?></p>
				<?php endif; ?>
			</div>
		</section>

		<?php if ( $d['porosity'] ) : ?>
			<section>
				<h2><?php esc_html_e( 'Porosité du tissu', 'controle-parapente' ); ?></h2>
				<table class="cp-table">
					<thead><tr><th><?php esc_html_e( 'Point de mesure', 'controle-parapente' ); ?></th><th class="num"><?php esc_html_e( 'Temps (s)', 'controle-parapente' ); ?></th><th><?php esc_html_e( 'Évaluation', 'controle-parapente' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $d['porosity'] as $row ) : ?>
						<?php $level = CP_Controle::porosity_level( $row['value'] ); ?>
						<tr>
							<td><?php echo esc_html( $row['zone'] ); ?></td>
							<td class="num"><?php echo esc_html( $fmt( $row['value'] ) ); ?></td>
							<td class="lvl-<?php echo esc_attr( $level ); ?>"><?php echo esc_html( $level_labels[ $level ] ?? '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>

		<?php if ( $d['fabric_strength'] || $d['fabric_result'] || $d['lines'] ) : ?>
		<section>
			<h2><?php esc_html_e( 'Résistance', 'controle-parapente' ); ?></h2>
			<?php if ( $d['fabric_strength'] || $d['fabric_result'] ) : ?>
				<p>
					<?php esc_html_e( 'Tissu (Bettsomètre) :', 'controle-parapente' ); ?>
					<?php echo esc_html( $d['fabric_strength'] ); ?>
					<?php if ( $d['fabric_result'] ) : ?>
						— <span class="lvl-<?php echo esc_attr( 'ok' === $d['fabric_result'] ? 'ok' : 'bad' ); ?>"><?php echo esc_html( 'ok' === $d['fabric_result'] ? $level_labels['ok'] : $level_labels['bad'] ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( $d['lines'] ) : ?>
				<table class="cp-table">
					<thead><tr><th><?php esc_html_e( 'Suspente', 'controle-parapente' ); ?></th><th class="num"><?php esc_html_e( 'Mesurée (daN)', 'controle-parapente' ); ?></th><th class="num"><?php esc_html_e( 'Minimum (daN)', 'controle-parapente' ); ?></th><th><?php esc_html_e( 'Évaluation', 'controle-parapente' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $d['lines'] as $row ) : ?>
						<?php $level = CP_Controle::line_level( $row['measured'], $row['minimum'] ); ?>
						<tr>
							<td><?php echo esc_html( $row['line'] ); ?></td>
							<td class="num"><?php echo esc_html( $fmt( $row['measured'] ) ); ?></td>
							<td class="num"><?php echo esc_html( $fmt( $row['minimum'] ) ); ?></td>
							<td class="lvl-<?php echo esc_attr( $level ); ?>"><?php echo esc_html( $level_labels[ $level ] ?? '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php endif; ?>

		<?php CP_Trim::render_certificate( $d['trim'], $d['trim_adjusted'] ); ?>

		<?php
		$visual = array_filter(
			(array) $d['visual'],
			static function ( $v ) {
				return ! empty( $v['state'] ) || ! empty( $v['comment'] );
			}
		);
		?>
		<?php if ( $visual ) : ?>
			<section>
				<h2><?php esc_html_e( 'Contrôle visuel', 'controle-parapente' ); ?></h2>
				<table class="cp-table">
					<thead><tr><th><?php esc_html_e( 'Élément', 'controle-parapente' ); ?></th><th><?php esc_html_e( 'État', 'controle-parapente' ); ?></th><th><?php esc_html_e( 'Commentaire', 'controle-parapente' ); ?></th></tr></thead>
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
			</section>
		<?php endif; ?>

		<?php if ( $d['repairs'] ) : ?>
			<section>
				<h2><?php esc_html_e( 'Réparations / pièces remplacées', 'controle-parapente' ); ?></h2>
				<p><?php echo nl2br( esc_html( $d['repairs'] ) ); ?></p>
			</section>
		<?php endif; ?>

		<?php if ( $d['comments'] ) : ?>
			<section>
				<h2><?php esc_html_e( 'Observations', 'controle-parapente' ); ?></h2>
				<p><?php echo nl2br( esc_html( $d['comments'] ) ); ?></p>
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
				<span class="cp-small"><?php esc_html_e( 'Signature et cachet de l\'atelier', 'controle-parapente' ); ?></span>
			</div>
			<p class="cp-small"><?php echo nl2br( esc_html( $settings['certificate_footer'] ) ); ?></p>
		</footer>
	</main>
</body>
</html>
