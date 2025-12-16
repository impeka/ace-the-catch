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
 * @package Impeka\Lotto
 */

namespace Impeka\Lotto;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LOTTO_VERSION', '0.1.0' );
define( 'LOTTO_FILE', __FILE__ );
define( 'LOTTO_PATH', \plugin_dir_path( __FILE__ ) );
define( 'LOTTO_URL', \plugin_dir_url( __FILE__ ) );

require_once LOTTO_PATH . 'includes/class-activator.php';
require_once LOTTO_PATH . 'includes/class-deactivator.php';
require_once LOTTO_PATH . 'includes/interface-payment-processor.php';
require_once LOTTO_PATH . 'includes/class-payment-processor-factory.php';
require_once LOTTO_PATH . 'includes/interface-geo-locator.php';
require_once LOTTO_PATH . 'includes/class-geo-locator-factory.php';
require_once LOTTO_PATH . 'includes/class-envelope-dealer.php';
require_once LOTTO_PATH . 'includes/class-plugin.php';

\register_activation_hook( LOTTO_FILE, array( __NAMESPACE__ . '\\Activator', 'activate' ) );
\register_deactivation_hook( LOTTO_FILE, array( __NAMESPACE__ . '\\Deactivator', 'deactivate' ) );

/**
 * Returns the core plugin instance.
 *
 * @return Plugin
 */
Plugin::instance();
