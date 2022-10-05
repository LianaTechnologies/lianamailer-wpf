<?php
/**
 * Plugin Name:       LianaMailer - WPForms
 * Description:       LianaMailer plugin for WPForms.
 * Version:           1.14
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Liana Technologies
 * Author URI:        https://www.lianatech.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0-standalone.html
 * Text Domain:       lianamailer
 * Domain Path:       /languages
 *
 * PHP Version 7.4
 *
 * @package LianaMailer
 * @license https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link    https://www.lianatech.com
 */

namespace WPForms_LianaMailer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LMWP_FORMS_VERSION', '1.14' );

add_action( 'plugins_loaded', '\WPForms_LianaMailer\plugins_loaded' );

/**
 * Load plugin.
 */
function plugins_loaded() {
	// if WPForms is installed.
	if ( defined( 'WPFORMS_VERSION' ) ) {

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
		require_once dirname( __FILE__ ) . '/admin/class-lianamailerwpforms.php';
	}
}
