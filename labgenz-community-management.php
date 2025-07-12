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
 * requires plugin: BuddyPress 6.0.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Plugin constants
define( 'LABGENZ_CM_VERSION', '1.0.0' );
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


// add_filter(
// 	'bp_located_template',
// 	function ( $located, $templates ) {
// 		// labgenz_log_debug('bp_located_template called with: ' . print_r($templates, true));
// 		foreach ( $templates as $template ) {
// 			$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/buddypress/' . $template;
// 			// labgenz_log_debug('Checking for: ' . $plugin_template);
// 			if ( file_exists( $plugin_template ) ) {
// 				// labgenz_log_debug('Override found: ' . $plugin_template);
// 				return $plugin_template;
// 			}
// 		}
// 		return $located;
// 	},
// 	10,
// 	2
// );

// add_filter( 'groups_create_group_steps', function ( $steps ) {
//     $template_path = LABGENZ_CM_PATH . 'templates/buddypress/groups/create/group-details.php';
//     unset( $steps['group-settings'] );
//     unset( $steps['group-forum'] );
//     $steps['group-details-009'] = array(
//         'name' => __( 'Group Details Upper', LABGENZTEXTDOMAIN ),
//         'view' => $template_path,
//         'handler' => function ( $group_id ) {
//             error_log( 'Handler called!', 3, LABGENZ_LOGS_DIR . '/debug.log' );
//         },
//         'position' => 40,
//     );
    
//     error_log( 'Modified steps: ' . print_r( array_keys( $steps ), true ), 3, LABGENZ_LOGS_DIR . '/debug.log' );
//     return $steps;
// } );
