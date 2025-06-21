<?php
declare(strict_types=1);

namespace LABGENZ_CM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main loader class for Labgenz Community Management
 *
 * Handles initialization, activation, deactivation, and component management.
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Core
 */
class LabgenzCmLoader {
	/**
	 * Instance of this class
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Container for storing plugin components
	 *
	 * @var array<string, object>
	 */
	private array $container = array();

	/**
	 * Plugin components that need initialization
	 *
	 * @var array<string, class-string>
	 */
	private array $components = array();

	/**
	 * Get class instance | singleton pattern
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct creation
	 */
	private function __construct() {
		$this->define_components();
		$this->init_hooks();
	}

	/**
	 * Define plugin components
	 *
	 * @return void
	 */
	private function define_components(): void {
		$this->components = array(
			'core.assets'    => \LABGENZ_CM\Core\AssetsManager::class,
			'core.menu'      => \LABGENZ_CM\Core\MenuManager::class,
			'core.settings'  => \LABGENZ_CM\Core\Settings::class,
			'core.ajax'      => \LABGENZ_CM\Core\AjaxHandler::class,
			'core.admin_hooks' => \LABGENZ_CM\Admin\AdminHooks::class,
		);
		// Manually require classes if not loaded (for non-PSR-4 autoloaded files)
		foreach ( $this->components as $class ) {
			if ( ! class_exists( $class ) ) {
				// PSR-4: convert namespace to path
				$relative_path = str_replace('\\', '/', str_replace('LABGENZ_CM\\', '', $class)) . '.php';
				$base_dirs = [
					__DIR__ . '/../', // for Core, Admin, etc.
					__DIR__ . '/../../admin/', // fallback for admin
				];
				$found = false;
				foreach ($base_dirs as $base) {
					$full_path = $base . $relative_path;
					if (file_exists($full_path)) {
						require_once $full_path;
						$found = true;
						break;
					}
				}
				if (!$found) {
					// Optionally log missing class file
				}
			}
		}
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Plugin activation/deactivation
		register_activation_hook( LABGENZ_CM_PATH . 'labgenz-community-management.php', array( self::class, 'activate' ) );
		register_deactivation_hook( LABGENZ_CM_PATH . 'labgenz-community-management.php', array( self::class, 'deactivate' ) );

		// Initialize components after plugins loaded
		add_action( 'plugins_loaded', array( $this, 'init_components' ), 20 );

		// Load textdomain
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Initialize plugin components
	 *
	 * @return void
	 */
	public function init_components(): void {
		foreach ( $this->components as $key => $class ) {
			if ( ! isset( $this->container[ $key ] ) ) {
				if ( method_exists( $class, 'get_instance' ) ) {
					$this->container[ $key ] = $class::get_instance();
				} else {
					$this->container[ $key ] = new $class();
				}
			}
		}
		do_action( 'labgenz_cm_loaded' );
	}

	/**
	 * Plugin activation handler
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Activation logic here
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation handler
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Deactivation logic here
		flush_rewrite_rules();
	}

	/**
	 * Load plugin textdomain
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			LABGENZ_CM_TEXTDOMAIN,
			false,
			dirname( plugin_basename( LABGENZ_CM_PATH . 'labgenz-community-management.php' ) ) . '/languages'
		);
	}

	/**
	 * Get a component from container
	 *
	 * @param string $key The component key.
	 * @return object|null The component instance or null if not found.
	 */
	public function get_component( string $key ): ?object {
		if ( ! isset( $this->container[ $key ] ) && isset( $this->components[ $key ] ) ) {
			$this->container[ $key ] = new $this->components[ $key ]();
		}
		return $this->container[ $key ] ?? null;
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing
	 *
	 * @throws \Exception When attempting to unserialize.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Run the plugin (for compatibility with main file)
	 */
	public function run(): void {
		// Optionally, trigger component initialization here
	}
}