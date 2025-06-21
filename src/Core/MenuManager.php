<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MenuManager {
	private string $textdomain;

	public function __construct() {
		$this->textdomain = defined('LABGENZ_CM_TEXTDOMAIN') ? LABGENZ_CM_TEXTDOMAIN : 'labgenz-community-management';
		add_action( 'admin_menu', array( $this, 'register_admin_menus' ) );
	}

	private function get_menu_page_name(): string {
		if ( class_exists( '\LABGENZ_CM\Core\Settings' ) ) {
			$settings = new \LABGENZ_CM\Core\Settings();
			return $settings->get('menu_page_name', 'Labgenz Community');
		}
		return 'Labgenz Community';
	}

	public function register_admin_menus(): void {
		$menu_page_name = $this->get_menu_page_name();
		add_menu_page(
			__( $menu_page_name, $this->textdomain ),
			__( $menu_page_name, $this->textdomain ),
			'manage_options',
			'labgenz-cm',
			array( $this, 'render_main_menu_page' ),
			'dashicons-groups',
			56
		);
		add_submenu_page(
			'labgenz-cm',
			__( 'Settings', $this->textdomain ),
			__( 'Settings', $this->textdomain ),
			'manage_options',
			'labgenz-cm-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function render_main_menu_page(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'Labgenz Community Dashboard', $this->textdomain ) . '</h1></div>';
	}

	public function render_settings_page(): void {
		include_once dirname(__DIR__) . '/admin/partials/settings-page.php';
	}
}
