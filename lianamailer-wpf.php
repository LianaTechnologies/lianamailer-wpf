<?php
/**
 * Plugin Name:       LianaMailer for WPForms
 * Description:       LianaMailer plugin for WPForms.
 * Version:           1.20.5
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Liana Technologies Oy
 * Author URI:        https://www.lianatech.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0-standalone.html
 * Text Domain:       lianamailer-wpf
 * Domain Path:       /languages
 *
 * @package LianaMailer
 * @license https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link    https://www.lianatech.com
 */

namespace WPF_LianaMailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LMWP_FORMS_VERSION', '1.20.5' );

add_action( 'plugins_loaded', '\WPF_LianaMailer\plugins_loaded' );

/**
 * Load plugin.
 */
function plugins_loaded() {
	// if WPForms is installed.
	if ( defined( 'WPFORMS_VERSION' ) ) {

		if ( \is_admin() ) {
			// Load admin notices.
			require_once dirname( __FILE__ ) . '/admin/class-admin-notices.php';
			new \WPF_LianaMailer\Admin_Notices();
		}

		require_once dirname( __FILE__ ) . '/includes/Mailer/class-rest.php';
		require_once dirname( __FILE__ ) . '/includes/Mailer/class-lianamailerconnection.php';

		// Plugin for WPForms.
		require_once dirname( __FILE__ ) . '/includes/class-lianamailerplugin.php';

		try {
			$lm_plugin = new LianaMailerPlugin();
		} catch ( \Exception $e ) {
			$error_messages[] = 'Error: ' . $e->getMessage();
		}

		/**
		 * Include admin menu & panel code
		 */
		require_once dirname( __FILE__ ) . '/admin/class-lianamailer-wpf.php';
	}
}
