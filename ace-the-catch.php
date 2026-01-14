<?php
/**
 * Plugin Name: Ace the Catch
 * Plugin URI: https://example.com
 * Description: Core scaffolding for the Ace the Catch plugin.
 * Version: 0.8.0
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

define( 'LOTTO_VERSION', '0.8.0' );
define( 'LOTTO_FILE', __FILE__ );
define( 'LOTTO_PATH', \plugin_dir_path( __FILE__ ) );
define( 'LOTTO_URL', \plugin_dir_url( __FILE__ ) );

require_once LOTTO_PATH . 'includes/class-activator.php';
require_once LOTTO_PATH . 'includes/class-deactivator.php';
require_once LOTTO_PATH . 'includes/interface-payment-processor.php';
require_once LOTTO_PATH . 'includes/class-payment-processor-factory.php';
require_once LOTTO_PATH . 'includes/class-payment-processor-dummy.php';
require_once LOTTO_PATH . 'includes/class-payment-processor-stripe.php';
require_once LOTTO_PATH . 'includes/interface-geo-locator.php';
require_once LOTTO_PATH . 'includes/class-geo-locator-factory.php';
require_once LOTTO_PATH . 'includes/class-geo-locator-dummy.php';
require_once LOTTO_PATH . 'includes/class-geo-locator-maxmind.php';
require_once LOTTO_PATH . 'includes/ontario-geo-locator/class-geo-locator-ontario-browser.php';
require_once LOTTO_PATH . 'includes/class-envelope-dealer.php';
require_once LOTTO_PATH . 'includes/class-catch-the-ace-session-shortcodes.php';
require_once LOTTO_PATH . 'includes/class-catch-the-ace-winners.php';
require_once LOTTO_PATH . 'includes/class-catch-the-ace-orders.php';
require_once LOTTO_PATH . 'includes/class-catch-the-ace-tickets.php';
require_once LOTTO_PATH . 'includes/class-catch-the-ace-error-logs.php';
require_once LOTTO_PATH . 'includes/class-catch-the-ace-logs.php';
require_once LOTTO_PATH . 'includes/interface-request.php';
require_once LOTTO_PATH . 'includes/class-request-update-cart.php';
require_once LOTTO_PATH . 'includes/class-request-place-order.php';
require_once LOTTO_PATH . 'includes/class-catch-the-ace-checkout.php';
require_once LOTTO_PATH . 'includes/interface-email-message.php';
require_once LOTTO_PATH . 'includes/class-simple-email-message.php';
require_once LOTTO_PATH . 'includes/class-email-dispatcher.php';
require_once LOTTO_PATH . 'includes/class-catch-the-ace-emails.php';
require_once LOTTO_PATH . 'includes/class-catch-the-ace-benefactors.php';
require_once LOTTO_PATH . 'includes/class-catch-the-ace-cpt.php';
require_once LOTTO_PATH . 'includes/class-catch-the-ace-acf.php';
require_once LOTTO_PATH . 'includes/class-catch-the-ace-settings.php';
require_once LOTTO_PATH . 'includes/class-template-manager.php';
require_once LOTTO_PATH . 'includes/class-plugin.php';

\register_activation_hook( LOTTO_FILE, array( __NAMESPACE__ . '\\Activator', 'activate' ) );
\register_deactivation_hook( LOTTO_FILE, array( __NAMESPACE__ . '\\Deactivator', 'deactivate' ) );

/**
 * Returns the core plugin instance.
 *
 * @return Plugin
 */
Plugin::instance();
