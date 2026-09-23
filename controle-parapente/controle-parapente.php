<?php
/**
 * Plugin Name:       Contrôle Parapente
 * Description:       Gestion des contrôles (révisions) de parapentes : demandes en ligne, fiche de contrôle complète (porosité, suspentes, calage, visuel), certificat imprimable, suivi client et rappels automatiques.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Contrôle Parapente
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       controle-parapente
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CP_VERSION', '1.1.0' );
define( 'CP_FILE', __FILE__ );
define( 'CP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CP_URL', plugin_dir_url( __FILE__ ) );

require_once CP_DIR . 'includes/class-cp-settings.php';
require_once CP_DIR . 'includes/class-cp-controle.php';
require_once CP_DIR . 'includes/class-cp-trim.php';
require_once CP_DIR . 'includes/class-cp-post-type.php';
require_once CP_DIR . 'includes/class-cp-admin.php';
require_once CP_DIR . 'includes/class-cp-emails.php';
require_once CP_DIR . 'includes/class-cp-shortcodes.php';
require_once CP_DIR . 'includes/class-cp-certificate.php';
require_once CP_DIR . 'includes/class-cp-cron.php';
require_once CP_DIR . 'includes/class-cp-atelier.php';

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'controle-parapente', false, dirname( plugin_basename( CP_FILE ) ) . '/languages' );

		CP_Post_Type::init();
		CP_Settings::init();
		CP_Admin::init();
		CP_Shortcodes::init();
		CP_Certificate::init();
		CP_Cron::init();
		CP_Atelier::init();
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		CP_Post_Type::register();
		flush_rewrite_rules();
		CP_Cron::schedule();

		if ( false === get_option( CP_Settings::OPTION ) ) {
			add_option( CP_Settings::OPTION, CP_Settings::defaults() );
		}
		CP_Atelier::ensure_page();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		CP_Cron::unschedule();
		flush_rewrite_rules();
	}
);
