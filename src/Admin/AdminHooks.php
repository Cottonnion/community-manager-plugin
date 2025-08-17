<?php
/**
 * Admin Hooks
 *
 * Handles admin-specific hooks and actions for the plugin.
 *
 * @package LABGENZ_CM\Admin
 * @deprecated since 1.0.0
 */

namespace LABGENZ_CM\Admin;

use LABGENZ_CM\Core\AjaxHandler;
use LABGENZ_CM\Core\Settings;
use LABGENZ_CM\Core\AssetsManager;

class AdminHooks {

	/**
	 * Assets Manager instance.
	 *
	 * @var AssetsManager
	 */
	private static $assets_manager;

	/**
	 * Initialize the class and set its properties.
	 */
	public static function init() {
		self::$assets_manager = new AssetsManager();

		// Initialize the Subscriptions Admin.
		// new SubscriptionsAdmin(self::$assets_manager);
	}

	/**
	 * Register AJAX actions for the plugin.
	 *
	 * @return void
	 */
	public static function register_ajax(): void {
		$ajax_handler = new AjaxHandler();
		$ajax_handler->register_ajax_actions(
			[
				'labgenz_cm_save_menu_settings'     => function () use ( $ajax_handler ) {
					$ajax_handler->handle_request(
						function ( $data ) {
							$menu_page_name = $data['menu_page_name'] ?? '';
							if ( ! $menu_page_name ) {
								return new \WP_Error( 'missing_menu_name', __( 'Menu name is required.', 'labgenz-community-management' ) );
							}
							$settings = new Settings();
							$settings->set( 'menu_page_name', $menu_page_name );
							return [
								'message' => __( 'Menu name updated.', 'labgenz-community-management' ),
							];
						},
						'labgenz_cm_save_menu_settings_nonce'
					);
				},
				// Register appearance settings AJAX actions
				'labgenz_save_appearance_settings'  => [ \LABGENZ_CM\Core\AppearanceSettingsHandler::class, 'save_appearance_settings_ajax' ],
				'labgenz_reset_appearance_settings' => [ \LABGENZ_CM\Core\AppearanceSettingsHandler::class, 'reset_appearance_settings_ajax' ],
			]
		);
	}
}

// Initialize the admin hooks
add_action( 'plugins_loaded', [ AdminHooks::class, 'init' ] );

add_action( 'init', [ AdminHooks::class, 'register_ajax' ] );
