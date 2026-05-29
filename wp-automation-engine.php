<?php
/**
 * Plugin Name:       WP Automation Engine
 * Plugin URI:        https://posterumsoft.com/
 * Description:       A WordPress automation plugin inspired by n8n.
 * Version:           1.0.0
 * Author:            PosterumSoft
 * Author URI:        https://posterumsoft.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-automation-engine
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_AUTOMATION_ENGINE_VERSION', '1.0.0' );

require plugin_dir_path( __FILE__ ) . 'includes/class-wp-automation-engine.php';

register_activation_hook( __FILE__, array( 'WP_Automation_Engine', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_Automation_Engine', 'deactivate' ) );

function run_wp_automation_engine() {
	$plugin = new WP_Automation_Engine();
	$plugin->run();
}

run_wp_automation_engine();
