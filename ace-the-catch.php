<?php
/**
 * Plugin Name: Ace the Catch
 * Plugin URI: https://example.com
 * Description: Core scaffolding for the Ace the Catch plugin.
 * Version: 0.1.0
 * Author: Impeka
 * Author URI: https://impeka.com
 * Text Domain: ace-the-catch
 * Domain Path: /languages
 *
 * @package Ace_The_Catch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ACE_THE_CATCH_VERSION', '0.1.0' );
define( 'ACE_THE_CATCH_FILE', __FILE__ );
define( 'ACE_THE_CATCH_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACE_THE_CATCH_URL', plugin_dir_url( __FILE__ ) );

require_once ACE_THE_CATCH_PATH . 'includes/class-ace-the-catch-activator.php';
require_once ACE_THE_CATCH_PATH . 'includes/class-ace-the-catch-deactivator.php';
require_once ACE_THE_CATCH_PATH . 'includes/class-ace-the-catch.php';

register_activation_hook( ACE_THE_CATCH_FILE, array( 'Ace_The_Catch_Activator', 'activate' ) );
register_deactivation_hook( ACE_THE_CATCH_FILE, array( 'Ace_The_Catch_Deactivator', 'deactivate' ) );

/**
 * Returns the core plugin instance.
 *
 * @return Ace_The_Catch
 */
function ace_the_catch() {
	return Ace_The_Catch::instance();
}

// Kick things off.
ace_the_catch();
