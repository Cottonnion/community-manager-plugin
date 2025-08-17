<?php
/**
 * Plugin Name:       Labgenz Community Management
 * Description:       Organization management tools.
 * Version:           1.0.1
 * Author:            Labgenz
 * Author URI:        https://Labgenz.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       labgenz-community-management
 * Domain Path:       /languages
 * requires plugin: BuddyPress 6.0.0
 * 
 * Developed by: Yahya Eddaqqaq
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Plugin constants
define( 'LABGENZ_CM_VERSION', '1.0.1' );
define( 'LABGENZ_CM_PATH', plugin_dir_path( __FILE__ ) );
define( 'LABGENZ_CM_URL', plugin_dir_url( __FILE__ ) );
define( 'LABGENZTEXTDOMAIN', 'labgenz-community-management' );
define( 'LABGENZ_LOGS_DIR', plugin_dir_path( __FILE__ ) . 'src/logs' );
define( 'LABGENZ_CM_TEMPLATES_DIR', plugin_dir_path( __FILE__ ) . 'templates' );
define( 'LABGENZ_CM_TEMPLATES_URL', plugin_dir_url( __FILE__ ) . 'templates' );

// Load Composer autoloader if available
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Manually require the loader if not loaded by Composer (since it's now in src/Core/)
if ( ! class_exists( '\LABGENZ_CM\Core\LabgenzCmLoader' ) ) {
	require_once LABGENZ_CM_PATH . 'src/Core/LabgenzCmLoader.php';
}

// Start the plugin
$loader = \LABGENZ_CM\Core\LabgenzCmLoader::get_instance();
$loader->run();

// Include testing helpers (remove for production)
if ( file_exists( LABGENZ_CM_PATH . 'testing-helpers.php' ) ) {
	require_once LABGENZ_CM_PATH . 'testing-helpers.php';
}