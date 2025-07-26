<?php
/**
 * Admin Hooks
 *
 * Handles admin-specific hooks and actions for the plugin.
 *
 * @package LABGENZ_CM\Admin
 */

namespace LABGENZ_CM\Admin;

use LABGENZ_CM\Core\AjaxHandler;
use LABGENZ_CM\Core\Settings;

class AdminHooks {

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

add_action( 'init', [ AdminHooks::class, 'register_ajax' ] );
