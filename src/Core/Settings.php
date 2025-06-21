<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Manager class for Labgenz Community Management
 *
 * Handles plugin options: defaults, retrieval, update, and registration.
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Core
 */
class Settings {
	/**
	 * Option name for storing plugin settings in wp_options.
	 *
	 * @var string
	 */
	private string $option_name = 'labgenz_cm_settings';

	/**
	 * Default settings for the plugin.
	 *
	 * @var array
	 */
	private array $defaults = [
		'enable_feature_x' => true,
		'default_role'     => 'member',
		'community_label'  => 'Community',
		'items_per_page'   => 20,
		'allow_registration' => true,
	];

	/**
	 * Register settings with WordPress.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register the plugin settings in WordPress.
	 */
	public function register_settings(): void {
		register_setting( 'labgenz_cm_settings_group', $this->option_name );
	}

	/**
	 * Get all plugin settings, merged with defaults.
	 *
	 * @return array
	 */
	public function get_settings(): array {
		$options = get_option( $this->option_name, [] );
		return array_merge( $this->defaults, is_array($options) ? $options : [] );
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$settings = $this->get_settings();
		return $settings[$key] ?? $default;
	}

	/**
	 * Update a single setting value.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return bool
	 */
	public function set( string $key, $value ): bool {
		$settings = $this->get_settings();
		$settings[$key] = $value;
		return update_option( $this->option_name, $settings );
	}

	/**
	 * Reset all settings to defaults.
	 */
	public function reset(): void {
		update_option( $this->option_name, $this->defaults );
	}

	/**
	 * Get the correct admin page hooks based on the current menu name.
	 *
	 * @return array
	 */
	public static function get_admin_page_hooks(): array {
		$settings = new self();
		$menu_page_name = $settings->get('menu_page_name', 'Labgenz Community');
		$slug = sanitize_title($menu_page_name);
		return array(
			$slug . '_page_labgenz-cm-settings',
			'toplevel_page_labgenz-cm',
		);
	}
}
