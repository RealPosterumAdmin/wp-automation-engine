<?php
/**
 * Plugin Name: WP Automation Engine
 * Description: Система автоматизации внутри WordPress.
 * Version: 1.0.0
 * Author: RealPosterumAdmin
 * Text Domain: wp-automation-engine
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

define( 'WPAE_VERSION', '1.0.0' );
define( 'WPAE_PLUGIN_FILE', __FILE__ );
define( 'WPAE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPAE_PLUGIN_DIR . 'includes/core/class-wpae-autoloader.php';

WPAE_Autoloader::register( WPAE_PLUGIN_DIR );

register_activation_hook( WPAE_PLUGIN_FILE, array( 'WPAE_Bootstrap', 'activate' ) );
register_deactivation_hook( WPAE_PLUGIN_FILE, array( 'WPAE_Bootstrap', 'deactivate' ) );

function wpae_run_plugin() {
$bootstrap = new WPAE_Bootstrap();
$bootstrap->run();
}

wpae_run_plugin();
