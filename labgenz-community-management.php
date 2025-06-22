<?php
/**
 * Plugin Name:       Labgenz Community Management
 * Plugin URI:        https://labgenz.com/plugins/labgenz-community-management
 * Description:       Community management tools for WordPress.
 * Version:           1.0.0
 * Author:            Labgenz
 * Author URI:        https://Labgenz.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       labgenz-community-management
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// Plugin constants
define( 'LABGENZ_CM_VERSION', '1.0.0' );
define( 'LABGENZ_CM_PATH', plugin_dir_path( __FILE__ ) );
define( 'LABGENZ_CM_URL', plugin_dir_url( __FILE__ ) );
define( 'LABGENZ_CM_TEXTDOMAIN', 'labgenz-community-management' );
define( 'LABGENZ_LOGS_DIR', plugin_dir_path( __FILE__ ) . 'src/logs' );

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

add_filter('bp_located_template', function($located, $templates, $template_dir = '') {
    foreach ($templates as $template) {
        // Ensure it checks inside templates/buddypress/...
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/buddypress/' . $template;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $located;
}, 10, 3);