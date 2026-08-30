<?php
/**
 * Plugin Name:       RoleChat Messenger
 * Plugin URI:        https://github.com/ildrm/wordpress-rolechat-messenger
 * Description:       Secure Telegram-inspired internal and frontend messaging for WordPress with directional role permissions, direct/group chat, contacts, attachments, moderation, and a frontend support widget.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Shahin Ilderemi
 * Author URI:        https://ildrm.com
 * License:           MIT
 * Text Domain:       rolechat-messenger
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'RCM_VERSION', '1.0.0' );
define( 'RCM_DB_VERSION', '1.0.0' );
define( 'RCM_FILE', __FILE__ );
define( 'RCM_DIR', plugin_dir_path( __FILE__ ) );
define( 'RCM_URL', plugin_dir_url( __FILE__ ) );

require_once RCM_DIR . 'includes/class-rcm-db.php';
require_once RCM_DIR . 'includes/class-rcm-attachments.php';
require_once RCM_DIR . 'includes/class-rcm-activator.php';
require_once RCM_DIR . 'includes/class-rcm-permissions.php';
require_once RCM_DIR . 'includes/class-rcm-rest.php';
require_once RCM_DIR . 'includes/class-rcm-admin.php';
require_once RCM_DIR . 'includes/class-rcm-frontend.php';
require_once RCM_DIR . 'includes/class-rcm-cron.php';

register_activation_hook( __FILE__, array( 'RCM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RCM_Activator', 'deactivate' ) );

final class RCM_Plugin {
	private static ?RCM_Plugin $instance = null;

	public static function instance(): RCM_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'maybe_upgrade' ) );

		RCM_REST::init();
		RCM_Admin::init();
		RCM_Frontend::init();
		RCM_Cron::init();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'rolechat-messenger', false, dirname( plugin_basename( RCM_FILE ) ) . '/languages' );
	}

	public function maybe_upgrade(): void {
		RCM_Activator::ensure_attachment_secret();
		if ( get_option( 'rcm_db_version' ) !== RCM_DB_VERSION ) {
			RCM_Activator::install_schema();
			update_option( 'rcm_db_version', RCM_DB_VERSION, false );
		}
	}
}

RCM_Plugin::instance();
