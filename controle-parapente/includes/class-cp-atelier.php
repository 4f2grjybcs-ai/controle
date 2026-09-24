<?php
/**
 * Espace atelier : page du site (shortcode [cp_atelier]) réservée à l'équipe connectée,
 * pour créer les contrôles, saisir les mesures et remettre le rapport au client.
 *
 * La page s'affiche en plein écran avec son propre style, indépendamment du thème.
 *
 * @package ControleParapente
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Atelier {

	const SHORTCODE   = 'cp_atelier';
	const PAGE_OPTION = 'cp_atelier_page_id';
	const PER_PAGE    = 25;

	/** @var int ID de la page atelier en cours d'affichage. */
	private static $page_id = 0;

	public static function init() {
		add_shortcode( self::SHORTCODE, '__return_empty_string' );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_page' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 80 );
	}

	/* ------------------------------------------------------------------ */
	/* Page                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Crée la page « Atelier » si elle n'existe pas encore.
	 */
	public static function ensure_page() {
		if ( ! current_user_can( 'publish_pages' ) ) {
			return;
		}
		$id = (int) get_option( self::PAGE_OPTION );
		if ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
			return;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Atelier', 'controle-parapente' ),
				'post_name'    => 'atelier',
				'post_content' => '[' . self::SHORTCODE . ']',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( self::PAGE_OPTION, $id, false );
		}
	}

	public static function url( $args = array() ) {
		$base = self::$page_id ? get_permalink( self::$page_id ) : get_permalink( (int) get_option( self::PAGE_OPTION ) );
		return $args ? add_query_arg( $args, $base ) : $base;
	}

	public static function admin_bar( $bar ) {
		if ( ! current_user_can( 'edit_posts' ) || ! get_option( self::PAGE_OPTION ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'cp-atelier',
				'title' => '<span class="ab-icon dashicons dashicons-clipboard" style="margin-top:2px"></span>' . esc_html__( 'Atelier', 'controle-parapente' ),
				'href'  => self::url(),
			)
		);
	}

	public static function maybe_render() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return;
		}
		self::$page_id = $post->ID;

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );

		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			self::render_shell( 'login' );
			exit;
		}

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! empty( $_POST['cp_atelier_action'] ) ) {
			self::handle_action( sanitize_key( wp_unslash( $_POST['cp_atelier_action'] ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$fiche = isset( $_GET['fiche'] ) ? absint( $_GET['fiche'] ) : 0;
		if ( $fiche && CP_Post_Type::POST_TYPE === get_post_type( $fiche ) && current_user_can( 'edit_post', $fiche ) ) {
			self::render_shell( 'fiche', $fiche );
		} else {
			self::render_shell( 'list' );
		}
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Actions                                                             */
	/* ------------------------------------------------------------------ */

	private static function handle_action( $action ) {
		// phpcs:disable WordPress.Security.NonceVerification -- nonces vérifiés ci-dessous.
		$post_id = isset( $_POST['cp_post_id'] ) ? absint( $_POST['cp_post_id'] ) : 0;

		switch ( $action ) {
			case 'create':
				check_admin_referer( 'cp_atelier_create' );
				$post_id = self::create();
				wp_safe_redirect( self::url( array( 'fiche' => $post_id, 'msg' => 'created' ) ) . '#client' );
				exit;

			case 'save':
				check_admin_referer( CP_Admin::NONCE, 'cp_nonce' );
				self::check_post( $post_id );
				$raw = isset( $_POST['cp'] ) && is_array( $_POST['cp'] ) ? wp_unslash( $_POST['cp'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nettoyé par CP_Controle::sanitize().
				CP_Admin::process( $post_id, $raw, ! empty( $_POST['cp_notify'] ) );
				$tab = isset( $_POST['cp_tab'] ) ? sanitize_key( wp_unslash( $_POST['cp_tab'] ) ) : 'client';
				wp_safe_redirect( self::url( array( 'fiche' => $post_id, 'msg' => 'saved' ) ) . '#' . $tab );
				exit;

			case 'send_report':
				check_admin_referer( 'cp_send_report_' . $post_id );
				self::check_post( $post_id );
				$d   = CP_Controle::get( $post_id );
				$msg = 'nomail';
				if ( CP_Controle::has_certificate( $d['status'] ) && is_email( $d['email'] ) ) {
					$msg = CP_Emails::report( $post_id ) ? 'sent' : 'sendfail';
				}
				wp_safe_redirect( self::url( array( 'fiche' => $post_id, 'msg' => $msg ) ) . '#rapport' );
				exit;
		}
		// phpcs:enable
	}

	private static function check_post( $post_id ) {
		if ( ! $post_id || CP_Post_Type::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'controle-parapente' ), 403 );
		}
	}

	/**
	 * Crée un contrôle vide : le numéro est attribué immédiatement par le système.
	 */
	private static function create() {
		$post_id = wp_insert_post(
			array(
				'post_type'   => CP_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => __( 'Nouveau contrôle', 'controle-parapente' ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			wp_die( esc_html( $post_id->get_error_message() ) );
		}
		$data               = CP_Controle::defaults();
		$data['status']     = 'recue';
		$data['check_date'] = current_time( 'Y-m-d' );
		$data['technician'] = wp_get_current_user()->display_name;
		CP_Controle::save( $post_id, $data );
		CP_Admin::sync_title( $post_id );
		return $post_id;
	}

	/* ------------------------------------------------------------------ */
	/* Rendu                                                               */
	/* ------------------------------------------------------------------ */

	private static function render_shell( $view, $post_id = 0 ) {
		$settings = CP_Settings::get();
		$logo     = CP_Settings::logo_url();

		if ( 'fiche' === $view ) {
			CP_Admin::enqueue_fiche_assets();
		}
		wp_enqueue_style( 'cp-atelier', CP_URL . 'assets/css/atelier.css', array(), CP_VERSION );
		wp_enqueue_script( 'cp-atelier', CP_URL . 'assets/js/atelier.js', array(), CP_VERSION, true );

		$titles = array(
			'login' => __( 'Espace atelier', 'controle-parapente' ),
			'list'  => __( 'Contrôles', 'controle-parapente' ),
			'fiche' => $post_id ? (string) get_post_meta( $post_id, CP_Controle::META_REFERENCE, true ) : '',
		);
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $titles[ $view ] . ' — ' . $settings['workshop_name'] ); ?></title>
	<?php wp_print_styles( array( 'cp-admin', 'cp-atelier' ) ); ?>
	<style>:root { --cp-accent: <?php echo esc_html( $settings['accent_color'] ); ?>; }</style>
</head>
<body class="cp-atelier cp-view-<?php echo esc_attr( $view ); ?>">
	<header class="cp-top">
		<a class="cp-brand" href="<?php echo esc_url( self::url() ); ?>">
			<?php if ( $logo ) : ?>
				<img src="<?php echo esc_url( $logo ); ?>" alt="" />
			<?php else : ?>
				<span class="cp-brand-mark" aria-hidden="true"><?php echo esc_html( mb_substr( $settings['workshop_name'], 0, 1 ) ); ?></span>
			<?php endif; ?>
			<span>
				<strong><?php echo esc_html( $settings['workshop_name'] ); ?></strong>
				<small><?php esc_html_e( 'Espace atelier', 'controle-parapente' ); ?></small>
			</span>
		</a>
		<?php if ( 'login' !== $view ) : ?>
			<nav class="cp-top-nav">
				<a href="<?php echo esc_url( self::url() ); ?>" class="<?php echo 'list' === $view ? 'is-active' : ''; ?>"><?php esc_html_e( 'Contrôles', 'controle-parapente' ); ?></a>
				<?php self::new_button(); ?>
			</nav>
			<div class="cp-top-user">
				<span><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CP_Post_Type::POST_TYPE ) ); ?>"><?php esc_html_e( 'Admin', 'controle-parapente' ); ?></a>
				<a href="<?php echo esc_url( wp_logout_url( self::url() ) ); ?>"><?php esc_html_e( 'Déconnexion', 'controle-parapente' ); ?></a>
			</div>
		<?php endif; ?>
	</header>

	<main class="cp-main">
		<?php
		if ( 'login' === $view ) {
			self::view_login();
		} elseif ( 'fiche' === $view ) {
			self::view_fiche( $post_id );
		} else {
			self::view_list();
		}
		?>
	</main>
	<?php wp_print_scripts( array( 'cp-admin', 'cp-trim', 'cp-atelier' ) ); ?>
</body>
</html>
		<?php
	}

	private static function new_button( $class = 'cp-btn cp-btn--primary' ) {
		?>
		<form method="post" action="<?php echo esc_url( self::url() ); ?>" class="cp-inline-form">
			<?php wp_nonce_field( 'cp_atelier_create' ); ?>
			<input type="hidden" name="cp_atelier_action" value="create" />
			<button type="submit" class="<?php echo esc_attr( $class ); ?>">＋ <?php esc_html_e( 'Nouveau contrôle', 'controle-parapente' ); ?></button>
		</form>
		<?php
	}

	private static function view_login() {
		?>
		<div class="cp-login cp-card">
			<h1><?php esc_html_e( 'Bienvenue à l\'atelier', 'controle-parapente' ); ?></h1>
			<?php if ( is_user_logged_in() ) : ?>
				<p><?php esc_html_e( 'Votre compte n\'a pas accès à l\'espace atelier.', 'controle-parapente' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Connectez-vous pour accéder aux contrôles.', 'controle-parapente' ); ?></p>
				<?php
				wp_login_form(
					array(
						'redirect'       => self::url(),
						'label_username' => __( 'Identifiant ou e-mail', 'controle-parapente' ),
						'label_log_in'   => __( 'Se connecter', 'controle-parapente' ),
					)
				);
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ---------- Liste ---------- */

	private static function view_list() {
		// phpcs:disable WordPress.Security.NonceVerification -- filtres de lecture.
		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$filter = isset( $_GET['filtre'] ) ? sanitize_key( wp_unslash( $_GET['filtre'] ) ) : 'encours';
		$paged  = isset( $_GET['p'] ) ? max( 1, absint( $_GET['p'] ) ) : 1;
		// phpcs:enable

		$filters = array(
			'encours'  => array( __( 'À l\'atelier', 'controle-parapente' ), array( 'demande', 'recue', 'en_cours', 'attente' ) ),
			'termines' => array( __( 'Terminés', 'controle-parapente' ), array( 'terminee' ) ),
			'rendus'   => array( __( 'Rendus', 'controle-parapente' ), array( 'rendue' ) ),
			'tous'     => array( __( 'Tous', 'controle-parapente' ), array() ),
		);
		if ( ! isset( $filters[ $filter ] ) ) {
			$filter = 'encours';
		}

		$args = array(
			'post_type'      => CP_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $search ) {
			$args['s'] = $search;
		}
		if ( $filters[ $filter ][1] ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'key' => CP_Controle::META_STATUS, 'value' => $filters[ $filter ][1], 'compare' => 'IN' ),
			);
		}
		$query = new WP_Query( $args );

		$statuses = CP_Controle::statuses();
		$verdicts = CP_Controle::verdicts();
		$user     = wp_get_current_user();
		?>
		<section class="cp-hello">
			<div>
				<h1><?php echo esc_html( sprintf( /* translators: %s: prénom */ __( 'Bonjour %s', 'controle-parapente' ), $user->first_name ? $user->first_name : $user->display_name ) ); ?></h1>
				<p><?php esc_html_e( 'Un client vous apporte son matériel ? Créez un contrôle : le numéro est attribué automatiquement.', 'controle-parapente' ); ?></p>
			</div>
			<?php self::new_button( 'cp-btn cp-btn--primary cp-btn--big' ); ?>
		</section>

		<section class="cp-card">
			<div class="cp-list-tools">
				<nav class="cp-chips">
					<?php foreach ( $filters as $key => $f ) : ?>
						<a class="cp-chip <?php echo $key === $filter ? 'is-active' : ''; ?>" href="<?php echo esc_url( self::url( array_filter( array( 'filtre' => $key, 'q' => $search ) ) ) ); ?>"><?php echo esc_html( $f[0] ); ?></a>
					<?php endforeach; ?>
				</nav>
				<form method="get" action="<?php echo esc_url( self::url() ); ?>" class="cp-search">
					<?php
					// Conserve l'ID de page quand les permaliens sont désactivés.
					$query_string = wp_parse_url( self::url(), PHP_URL_QUERY );
					if ( $query_string ) {
						parse_str( $query_string, $keep );
						foreach ( $keep as $k => $v ) {
							printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $k ), esc_attr( $v ) );
						}
					}
					?>
					<input type="hidden" name="filtre" value="tous" />
					<input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Rechercher : référence, pilote, aile, n° de série…', 'controle-parapente' ); ?>" />
					<button type="submit" class="cp-btn"><?php esc_html_e( 'Rechercher', 'controle-parapente' ); ?></button>
				</form>
			</div>

			<?php if ( ! $query->have_posts() ) : ?>
				<p class="cp-empty"><?php esc_html_e( 'Aucun contrôle ici pour le moment.', 'controle-parapente' ); ?></p>
			<?php else : ?>
				<table class="cp-list">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Référence', 'controle-parapente' ); ?></th>
							<th><?php esc_html_e( 'Reçu le', 'controle-parapente' ); ?></th>
							<th><?php esc_html_e( 'Pilote', 'controle-parapente' ); ?></th>
							<th><?php esc_html_e( 'Équipement', 'controle-parapente' ); ?></th>
							<th><?php esc_html_e( 'Statut', 'controle-parapente' ); ?></th>
							<th><?php esc_html_e( 'Verdict', 'controle-parapente' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ( $query->posts as $p ) :
						$d    = CP_Controle::get( $p->ID );
						$link = self::url( array( 'fiche' => $p->ID ) );
						?>
						<tr data-href="<?php echo esc_url( $link ); ?>">
							<td><a href="<?php echo esc_url( $link ); ?>"><strong><?php echo esc_html( $d['reference'] ); ?></strong></a></td>
							<td><?php echo esc_html( get_the_date( '', $p ) ); ?></td>
							<td><?php echo esc_html( $d['pilot_name'] ? $d['pilot_name'] : '—' ); ?></td>
							<td>
								<?php echo esc_html( '' !== trim( $d['brand'] . $d['model'] ) ? CP_Controle::equipment_label( $d ) : '—' ); ?>
								<?php if ( $d['serial'] ) : ?><small><?php echo esc_html( $d['serial'] ); ?></small><?php endif; ?>
							</td>
							<td><span class="cp-pill cp-status-<?php echo esc_attr( $d['status'] ); ?>"><?php echo esc_html( $statuses[ $d['status'] ] ?? '' ); ?></span></td>
							<td><?php if ( $d['verdict'] ) : ?><span class="cp-pill cp-verdict-<?php echo esc_attr( $d['verdict'] ); ?>"><?php echo esc_html( $verdicts[ $d['verdict'] ] ); ?></span><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $query->max_num_pages > 1 ) : ?>
					<nav class="cp-pages">
						<?php for ( $i = 1; $i <= $query->max_num_pages; $i++ ) : ?>
							<a class="cp-chip <?php echo $i === $paged ? 'is-active' : ''; ?>" href="<?php echo esc_url( self::url( array_filter( array( 'filtre' => $filter, 'q' => $search, 'p' => $i ) ) ) ); ?>"><?php echo esc_html( $i ); ?></a>
						<?php endfor; ?>
					</nav>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	/* ---------- Fiche ---------- */

	private static function card( $title, $callback, $post, $class = '' ) {
		echo '<section class="cp-card ' . esc_attr( $class ) . '"><h2>' . esc_html( $title ) . '</h2>';
		call_user_func( $callback, $post );
		echo '</section>';
	}

	private static function view_fiche( $post_id ) {
		$post     = get_post( $post_id );
		$d        = CP_Controle::get( $post_id );
		$statuses = CP_Controle::statuses();
		// phpcs:ignore WordPress.Security.NonceVerification
		$msg      = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
		$messages = array(
			'created'  => array( 'ok', sprintf( __( 'Contrôle %s créé. Renseignez le client et son aile.', 'controle-parapente' ), $d['reference'] ) ),
			'saved'    => array( 'ok', __( 'Fiche enregistrée.', 'controle-parapente' ) ),
			'sent'     => array( 'ok', __( 'Rapport envoyé au client par e-mail.', 'controle-parapente' ) ),
			'nomail'   => array( 'warn', __( 'Rapport non envoyé : le contrôle doit être terminé et l\'e-mail du client renseigné.', 'controle-parapente' ) ),
			'sendfail' => array( 'warn', __( 'L\'e-mail n\'a pas pu être envoyé (vérifiez la configuration e-mail du site).', 'controle-parapente' ) ),
		);
		$tabs     = array(
			'client'     => __( 'Client & aile', 'controle-parapente' ),
			'mesures'    => __( 'Atelier · mesures', 'controle-parapente' ),
			'conclusion' => __( 'Conclusion', 'controle-parapente' ),
			'rapport'    => __( 'Rapport client', 'controle-parapente' ),
		);
		$report_admin  = wp_nonce_url( admin_url( 'admin-post.php?action=cp_certificate&client=1&post=' . $post_id ), 'cp_certificate_' . $post_id );
		$can_share     = CP_Controle::has_certificate( $d['status'] );
		?>
		<div class="cp-fiche-head">
			<a class="cp-back" href="<?php echo esc_url( self::url() ); ?>">← <?php esc_html_e( 'Tous les contrôles', 'controle-parapente' ); ?></a>
			<div class="cp-fiche-title">
				<h1><?php echo esc_html( $d['reference'] ); ?></h1>
				<span class="cp-pill cp-status-<?php echo esc_attr( $d['status'] ); ?>"><?php echo esc_html( $statuses[ $d['status'] ] ?? '' ); ?></span>
			</div>
			<p class="cp-fiche-sub">
				<?php
				$sub = array_filter( array( '' !== trim( $d['brand'] . $d['model'] ) ? CP_Controle::equipment_label( $d ) : '', $d['serial'] ? '#' . $d['serial'] : '', $d['pilot_name'] ) );
				echo esc_html( $sub ? implode( ' · ', $sub ) : __( 'Nouvelle fiche', 'controle-parapente' ) );
				?>
			</p>
			<?php if ( isset( $messages[ $msg ] ) ) : ?>
				<div class="cp-toast cp-toast--<?php echo esc_attr( $messages[ $msg ][0] ); ?>" role="status"><?php echo esc_html( $messages[ $msg ][1] ); ?></div>
			<?php endif; ?>
		</div>

		<nav class="cp-tabs" role="tablist">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a href="#<?php echo esc_attr( $key ); ?>" role="tab" data-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<form method="post" action="<?php echo esc_url( self::url( array( 'fiche' => $post_id ) ) ); ?>" class="cp-fiche-form" id="cp-fiche-form">
			<input type="hidden" name="cp_atelier_action" value="save" />
			<input type="hidden" name="cp_post_id" id="post_ID" value="<?php echo esc_attr( $post_id ); ?>" />
			<input type="hidden" name="cp_tab" id="cp-tab" value="client" />

			<div class="cp-fiche-layout">
				<div class="cp-fiche-main">
					<div class="cp-panel" data-panel="client">
						<?php
						self::card( __( 'Client', 'controle-parapente' ), array( 'CP_Admin', 'box_pilot' ), $post );
						self::card( __( 'Aile / équipement', 'controle-parapente' ), array( 'CP_Admin', 'box_equipment' ), $post );
						self::card( __( 'Prestations', 'controle-parapente' ), array( 'CP_Admin', 'box_request' ), $post );
						?>
					</div>
					<div class="cp-panel" data-panel="mesures">
						<?php
						self::card( __( 'Type d\'inspection & normes', 'controle-parapente' ), array( 'CP_Admin', 'box_inspection' ), $post );
						self::card( __( 'Porosité du tissu', 'controle-parapente' ), array( 'CP_Admin', 'box_porosity' ), $post );
						self::card( __( 'Déchirure & résistance des suspentes', 'controle-parapente' ), array( 'CP_Admin', 'box_strength' ), $post );
						self::card( __( 'Calage', 'controle-parapente' ), array( 'CP_Admin', 'box_trim' ), $post, 'cp-card--wide' );
						self::card( CP_Settings::get( 'title_visual' ), array( 'CP_Admin', 'box_visual' ), $post );
						?>
					</div>
					<div class="cp-panel" data-panel="conclusion">
						<?php self::card( __( 'Conclusions', 'controle-parapente' ), array( 'CP_Admin', 'box_conclusion' ), $post ); ?>
					</div>
					<div class="cp-panel" data-panel="rapport">
						<section class="cp-card">
							<h2><?php esc_html_e( 'Rapport à remettre au client', 'controle-parapente' ); ?></h2>
							<p class="cp-muted"><?php esc_html_e( 'Aperçu du rapport tel qu\'il est enregistré (pensez à enregistrer vos dernières modifications). Il reste consultable à tout moment depuis la liste des contrôles.', 'controle-parapente' ); ?></p>
							<div class="cp-report-actions">
								<a class="cp-btn cp-btn--primary" target="_blank" rel="noopener" href="<?php echo esc_url( $report_admin ); ?>"><?php esc_html_e( 'Ouvrir / imprimer en PDF', 'controle-parapente' ); ?></a>
								<?php if ( $can_share ) : ?>
									<button type="submit" class="cp-btn" form="cp-send-report" <?php disabled( ! is_email( $d['email'] ) ); ?>><?php esc_html_e( 'Envoyer par e-mail au client', 'controle-parapente' ); ?></button>
									<button type="button" class="cp-btn cp-copy" data-copy="<?php echo esc_attr( CP_Controle::public_certificate_url( $post_id ) ); ?>"><?php esc_html_e( 'Copier le lien client', 'controle-parapente' ); ?></button>
								<?php endif; ?>
							</div>
							<?php if ( ! $can_share ) : ?>
								<p class="cp-hint"><?php esc_html_e( 'Passez le statut à « Contrôle terminé » pour partager le rapport avec le client (lien et e-mail).', 'controle-parapente' ); ?></p>
							<?php elseif ( ! is_email( $d['email'] ) ) : ?>
								<p class="cp-hint"><?php esc_html_e( 'Renseignez l\'e-mail du client pour pouvoir lui envoyer le rapport.', 'controle-parapente' ); ?></p>
							<?php endif; ?>
							<iframe class="cp-report-frame" title="<?php esc_attr_e( 'Aperçu du rapport', 'controle-parapente' ); ?>" data-src="<?php echo esc_url( $report_admin ); ?>" loading="lazy"></iframe>
						</section>
					</div>
				</div>

				<aside class="cp-fiche-side">
					<?php self::card( __( 'Suivi', 'controle-parapente' ), array( 'CP_Admin', 'box_result' ), $post ); ?>
				</aside>
			</div>

			<div class="cp-savebar">
				<span class="cp-dirty" hidden><?php esc_html_e( 'Modifications non enregistrées', 'controle-parapente' ); ?></span>
				<button type="submit" class="cp-btn cp-btn--primary"><?php esc_html_e( 'Enregistrer la fiche', 'controle-parapente' ); ?></button>
			</div>
		</form>

		<form method="post" id="cp-send-report" action="<?php echo esc_url( self::url( array( 'fiche' => $post_id ) ) ); ?>">
			<?php wp_nonce_field( 'cp_send_report_' . $post_id ); ?>
			<input type="hidden" name="cp_atelier_action" value="send_report" />
			<input type="hidden" name="cp_post_id" value="<?php echo esc_attr( $post_id ); ?>" />
		</form>
		<?php
	}
}
