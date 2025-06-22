<?php
/**
 * Assets Manager class for Labgenz Community Management
 *
 * Handles the enqueueing of CSS and JavaScript assets for the plugin.
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Core
 */

declare(strict_types=1);

namespace LABGENZ_CM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AssetsManager
 *
 * Handles registration and enqueueing of admin assets (JS/CSS).
 */
class AssetsManager {
	/**
	 * Holds the array of admin scripts and styles.
	 *
	 * @var array<string, array<string, array>>
	 */
	private array $admin_assets = array();

	/**
	 * Holds the array of frontend scripts and styles.
	 *
	 * @var array<string, array<string, array>>
	 */
	private array $frontend_assets = array();

	/**
	 * Holds the array of BuddyBoss-specific frontend scripts and styles.
	 *
	 * @var array<string, array<string, array>>
	 */
	private array $buddyboss_assets = array();

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 40, 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets_to_enqueue' ), 20, 0 );
		add_action( 'admin_init', array( $this, 'register_assets_to_enqueue' ), 20, 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 99, 0 );
	}

	/**
	 * Adds an admin script or style to the array of admin assets.
	 *
	 * @param string $handle
	 * @param array $pages
	 * @param string $file
	 * @param array $dependencies
	 * @param array $localization
	 * @param string $version
	 * @param bool $enqueue_in_footer
	 * @return void
	 */
	public function add_admin_asset(
		string $handle,
		array $pages,
		string $file,
		array $dependencies = array(),
		array $localization = array(),
		string $version = LABGENZ_CM_VERSION,
		bool $enqueue_in_footer = true
	): void {
		foreach ( $pages as $page ) {
			$this->admin_assets[ $page ][ $handle ] = array(
				'file'              => $file,
				'dependencies'      => $dependencies,
				'version'           => $version,
				'enqueue_in_footer' => $enqueue_in_footer,
				'localization'      => $localization,
			);
			// Directly enqueue if on the correct admin page
			if ( is_admin() && get_current_screen() && get_current_screen()->id === $page ) {
				$file_url = plugins_url(
					'src/admin/assets/' . ( 'css' === substr( $handle, -3 ) ? 'css/' : 'js/' ) . $file,
					dirname( __DIR__, 2 ) . '/labgenz-community-management.php'
				);
				if ( 'css' === substr( $handle, -3 ) ) {
					wp_enqueue_style(
						$handle,
						$file_url,
						$dependencies,
						$version
					);
				} else {
					wp_enqueue_script(
						$handle,
						$file_url,
						$dependencies,
						$version,
						$enqueue_in_footer
					);
					if ( ! empty( $localization ) ) {
						wp_localize_script(
							$handle,
							str_replace( '-', '_', $handle . '_data' ),
							$localization
						);
					}
				}
			}
		}
	}

	/**
	 * Adds a frontend script or style to the array of frontend assets.
	 *
	 * @param string $handle
	 * @param array $pages Array of page slugs or IDs where the asset should be loaded
	 * @param string $file
	 * @param array $dependencies
	 * @param string $version
	 * @param bool $enqueue_in_footer
	 * @param string $type 'css' or 'js'
	 * @return void
	 */
	public function add_frontend_asset(
		string $handle,
		array $pages,
		string $file,
		array $dependencies = array(),
		string $version = LABGENZ_CM_VERSION,
		bool $enqueue_in_footer = true,
		string $type = 'css'
	): void {
		foreach ( $pages as $page ) {
			$this->frontend_assets[ $page ][ $handle ] = array(
				'file'              => $file,
				'dependencies'      => $dependencies,
				'version'           => $version,
				'enqueue_in_footer' => $enqueue_in_footer,
				'type'              => $type,
			);
			// Directly enqueue if on the correct frontend page
			$is_target = false;
			if ( '' === $page && function_exists( 'bp_is_group' ) && bp_is_group() ) {
				$is_target = true;
			} elseif ( '' !== $page && is_page( $page ) ) {
				$is_target = true;
			}
			if ( $is_target ) {
				$file_url = plugins_url(
					'src/public/assets/' . ( 'css' === $type ? 'css/' : 'js/' ) . $file,
					dirname( __DIR__, 2 ) . '/labgenz-community-management.php'
				);
				if ( 'css' === $type ) {
					wp_enqueue_style(
						$handle,
						$file_url,
						$dependencies,
						$version
					);
				} else {
					wp_enqueue_script(
						$handle,
						$file_url,
						$dependencies,
						$version,
						$enqueue_in_footer
					);
				}
			}
		}
	}

	/**
	 * Adds a BuddyBoss-specific frontend asset.
	 *
	 * @param string $handle
	 * @param array $target_pages Array of BuddyBoss page types: group_single, groups_directory, members_directory, activity_directory, etc.
	 * @param string $file
	 * @param array $dependencies
	 * @param string $version
	 * @param bool $enqueue_in_footer
	 * @param string $type 'css' or 'js'
	 * @param array $localization Optional. Data to localize for JS assets.
	 * @return void
	 */
	public function add_buddyboss_asset(
		string $handle,
		array $target_pages,
		string $file,
		array $dependencies = array(),
		string $version = LABGENZ_CM_VERSION,
		bool $enqueue_in_footer = true,
		string $type = 'css',
		array $localization = array()
	): void {
		foreach ( $target_pages as $target ) {
			$this->buddyboss_assets[ $target ][ $handle ] = array(
				'file'              => $file,
				'dependencies'      => $dependencies,
				'version'           => $version,
				'enqueue_in_footer' => $enqueue_in_footer,
				'type'              => $type,
				'localization'      => $localization,
			);
			// Directly add to frontend assets if on the correct BuddyBoss page
			$is_target = false;
			if ( 'group_single' === $target && function_exists( 'bp_is_group' ) && bp_is_group() ) {
				$is_target = true;
			} elseif ( 'groups_directory' === $target && function_exists( 'bp_is_groups_directory' ) && bp_is_groups_directory() ) {
				$is_target = true;
			} elseif ( 'members_directory' === $target && function_exists( 'bp_is_members_directory' ) && bp_is_members_directory() ) {
				$is_target = true;
			} elseif ( 'activity_directory' === $target && function_exists( 'bp_is_activity_directory' ) && bp_is_activity_directory() ) {
				$is_target = true;
			}
			if ( $is_target ) {
				// Add to frontend assets array for later enqueueing
				$this->add_frontend_asset( $handle, array( $target ), $file, $dependencies, $version, $enqueue_in_footer, $type );
				// If localization is provided and it's a JS asset, localize it immediately
				if ( 'js' === $type && ! empty( $localization ) ) {
					add_action(
						'wp_enqueue_scripts',
						function () use ( $handle, $localization ) {
							wp_localize_script(
								$handle,
								str_replace( '-', '_', $handle . '_data' ),
								$localization
							);
						},
						100
					);
				}
			}
		}
	}

	/**
	 * Register and enqueue admin-specific scripts.
	 *
	 * @param string $hook
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Fallback: If appearance-admin.css is not registered for this hook, but is for another, register it here too
		if ( ! isset( $this->admin_assets[ $hook ]['labgenz-cm-appearance-css'] ) ) {
			// Search for appearance-admin.css in any other hook
			foreach ( $this->admin_assets as $registered_hook => $assets ) {
				if ( isset( $assets['labgenz-cm-appearance-css'] ) ) {
					$this->admin_assets[ $hook ]['labgenz-cm-appearance-css'] = $assets['labgenz-cm-appearance-css'];
					break;
				}
			}
		}
		if ( ! isset( $this->admin_assets[ $hook ] ) ) {
			return;
		}
		foreach ( $this->admin_assets[ $hook ] as $handle => $asset ) {
			$file_url = plugins_url(
				'src/admin/assets/' . ( 'css' === substr( $handle, -3 ) ? 'css/' : 'js/' ) . $asset['file'],
				dirname( __DIR__, 2 ) . '/labgenz-community-management.php'
			);
			if ( 'css' === substr( $handle, -3 ) ) {
				wp_enqueue_style(
					$handle,
					$file_url,
					$asset['dependencies'],
					$asset['version']
				);
			} else {
				wp_enqueue_script(
					$handle,
					$file_url,
					$asset['dependencies'],
					$asset['version'],
					$asset['enqueue_in_footer']
				);
				if ( ! empty( $asset['localization'] ) ) {
					wp_localize_script(
						$handle,
						str_replace( '-', '_', $handle . '_data' ),
						$asset['localization']
					);
				}
			}
		}
	}

	/**
	 * Registers assets to enqueue
	 *
	 * @return void
	 */
	public function register_assets_to_enqueue(): void {
		$hooks = \LABGENZ_CM\Core\Settings::get_admin_page_hooks();
		// Dynamically get the current screen hook for the Appearance page if available
		$appearance_hook = '';
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && strpos( $screen->id, 'appearance' ) !== false ) {
				$appearance_hook = $screen->id;
			}
		}
		$appearance_hooks = array_filter( array_merge( $hooks, array( $appearance_hook ) ) );
		$this->add_admin_asset(
			'labgenz-cm-appearance-css',
			$appearance_hooks,
			'appearance-admin.css',
			array(),
			array(),
			LABGENZ_CM_VERSION,
			false
		);
		$this->add_admin_asset(
			'labgenz-cm-settings-css',
			array( $hooks[0] ),
			'labgenz-cm-settings.css'
		);

		// Enqueue SweetAlert2 from CDN
		foreach ( $hooks as $hook ) {
			add_action(
				'admin_enqueue_scripts',
				function () use ( $hook ) {
					if ( get_current_screen() && get_current_screen()->id === $hook ) {
						wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.0/dist/sweetalert2.all.min.js', array( 'jquery' ), LABGENZ_CM_VERSION, true );
						wp_enqueue_style( 'sweetalert2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.0/dist/sweetalert2.min.css', array(), LABGENZ_CM_VERSION );
					}
				},
				50
			);
		}
		$this->add_admin_asset(
			'labgenz-cm-settings',
			$hooks,
			'labgenz-settings-handler.js',
			array( 'jquery', 'sweetalert2' ),
			array(
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'labgenz_cm_save_menu_settings_nonce' ),
				'saveSettings' => __( 'Settings saved.', 'labgenz-community-management' ),
				'saving'       => __( 'Saving...', 'labgenz-community-management' ),
				'errorMessage' => __( 'An error occurred while saving settings.', 'labgenz-community-management' ),
			),
			LABGENZ_CM_VERSION,
			true
		);

		$this->add_admin_asset(
			'labgenz-appearance-admin',
			$hooks,
			'labgenz-appearance-admin.js',
			array( 'jquery', 'sweetalert2' ),
			array(
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'labgenz_appearance_nonce' ),
				'saveSettings' => __( 'Settings saved.', 'labgenz-community-management' ),
				'saving'       => __( 'Saving...', 'labgenz-community-management' ),
				'errorMessage' => __( 'An error occurred while saving settings.', 'labgenz-community-management' ),
			),
			LABGENZ_CM_VERSION,
			true
		);

		$this->add_admin_asset(
			'labgenz-cm-settings-page-css',
			$hooks,
			'settings-page.css',
			array(),
			array(),
			LABGENZ_CM_VERSION,
			false
		);
		// Register single group page CSS for frontend using new method
		$this->add_buddyboss_asset(
			'lab-single-group-page-css',
			array( 'group_single' ),
			'lab-single-group-page.css',
			array(),
			LABGENZ_CM_VERSION,
			false,
			'css'
		);
		// Register group-management CSS and JS for BuddyBoss single group page only
		$this->add_buddyboss_asset(
			'lab-group-management-css',
			array( 'group_single' ),
			'lab-group-management.css',
			array(),
			LABGENZ_CM_VERSION,
			false,
			'css'
		);
		$this->add_buddyboss_asset(
			'lab-group-management-js',
			array( 'group_single' ),
			'lab-group-management.js',
			array( 'jquery' ),
			LABGENZ_CM_VERSION,
			true,
			'js'
		);
		// Register group-invite JS for BuddyBoss single group page only
		$this->add_buddyboss_asset(
			'lab-group-invite',
			array( 'group_single' ),
			'lab-group-invite-member.js',
			array( 'jquery' ),
			LABGENZ_CM_VERSION,
			true,
			'js',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'lab_group_management_nonce' ),
			)
		);
		$this->add_buddyboss_asset(
			'lab-group-remove',
			array( 'group_single' ),
			'lab-group-remove-member.js',
			array( 'jquery' ),
			LABGENZ_CM_VERSION,
			true,
			'js',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'labgenz_group_remove_nonce' ),
				'group_id' => bp_get_current_group_id(),
			)
		);
		$this->add_admin_asset(
			'labgenz-cm-appearance-css',
			array( $hooks[0], 'labgenz-cm-appearance' ),
			'appearance-admin.css',
			array(),
			array(),
			LABGENZ_CM_VERSION,
			false
		);
	}

	/**
	 * Enqueue all registered frontend assets.
	 */
	public function enqueue_frontend_assets() {
		foreach ( $this->frontend_assets as $page => $assets ) {
			foreach ( $assets as $handle => $asset ) {
				$file_url = plugins_url(
					'src/public/assets/' . ( 'css' === $asset['type'] ? 'css/' : 'js/' ) . $asset['file'],
					dirname( __DIR__, 2 ) . '/labgenz-community-management.php'
				);
				if ( 'css' === $asset['type'] ) {
					wp_enqueue_style(
						$handle,
						$file_url,
						$asset['dependencies'],
						$asset['version']
					);
				} else {
					wp_enqueue_script(
						$handle,
						$file_url,
						$asset['dependencies'],
						$asset['version'],
						$asset['enqueue_in_footer']
					);
					// Localize if data is present (for buddyboss assets)
					if ( ! empty( $this->buddyboss_assets[ $page ][ $handle ]['localization'] ) ) {
						wp_localize_script(
							$handle,
							str_replace( '-', '_', $handle . '_data' ),
							$this->buddyboss_assets[ $page ][ $handle ]['localization']
						);
					}
				}
			}
		}
	}
}
