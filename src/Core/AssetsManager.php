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
            if (is_admin() && get_current_screen() && get_current_screen()->id === $page) {
                $file_url = plugins_url(
                    'src/admin/assets/' . ( 'css' === substr( $handle, -3 ) ? 'css/' : 'js/' ) . $file,
                    dirname(__DIR__, 2) . '/labgenz-community-management.php'
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
        foreach ($pages as $page) {
            $this->frontend_assets[$page][$handle] = array(
                'file' => $file,
                'dependencies' => $dependencies,
                'version' => $version,
                'enqueue_in_footer' => $enqueue_in_footer,
                'type' => $type,
            );
            // Directly enqueue if on the correct frontend page
            $is_target = false;
            if ($page === '' && function_exists('bp_is_group') && bp_is_group()) {
                $is_target = true;
            } elseif ($page !== '' && is_page($page)) {
                $is_target = true;
            }
            if ($is_target) {
                $file_url = plugins_url(
                    'src/public/assets/' . ($type === 'css' ? 'css/' : 'js/') . $file,
                    dirname(__DIR__, 2) . '/labgenz-community-management.php'
                );
                if ($type === 'css') {
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
     * @return void
     */
    public function add_buddyboss_asset(
        string $handle,
        array $target_pages,
        string $file,
        array $dependencies = array(),
        string $version = LABGENZ_CM_VERSION,
        bool $enqueue_in_footer = true,
        string $type = 'css'
    ): void {
        foreach ($target_pages as $target) {
            $this->buddyboss_assets[$target][$handle] = array(
                'file' => $file,
                'dependencies' => $dependencies,
                'version' => $version,
                'enqueue_in_footer' => $enqueue_in_footer,
                'type' => $type,
            );
            // Directly add to frontend assets if on the correct BuddyBoss page
            $is_target = false;
            if ($target === 'group_single' && function_exists('bp_is_group') && bp_is_group()) {
                $is_target = true;
            } elseif ($target === 'groups_directory' && function_exists('bp_is_groups_directory') && bp_is_groups_directory()) {
                $is_target = true;
            } elseif ($target === 'members_directory' && function_exists('bp_is_members_directory') && bp_is_members_directory()) {
                $is_target = true;
            } elseif ($target === 'activity_directory' && function_exists('bp_is_activity_directory') && bp_is_activity_directory()) {
                $is_target = true;
            }
            if ($is_target) {
                // Add to frontend assets array for later enqueueing
                $this->add_frontend_asset($handle, [$target], $file, $dependencies, $version, $enqueue_in_footer, $type);
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
        if ( ! isset( $this->admin_assets[ $hook ] ) ) {
            return;
        }

        foreach ( $this->admin_assets[ $hook ] as $handle => $asset ) {
            $file_url = plugins_url(
                'src/admin/assets/' . ( 'css' === substr( $handle, -3 ) ? 'css/' : 'js/' ) . $asset['file'],
                dirname(__DIR__, 2) . '/labgenz-community-management.php'
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
        $this->add_admin_asset(
            'labgenz-cm-settings-css',
            array($hooks[0]),
            'labgenz-cm-settings.css'
        );

        // Enqueue SweetAlert2 from CDN
        foreach ($hooks as $hook) {
            add_action('admin_enqueue_scripts', function() use ($hook) {
                if (get_current_screen() && get_current_screen()->id === $hook) {
                    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.0/dist/sweetalert2.all.min.js', array('jquery'), LABGENZ_CM_VERSION, true);
                    wp_enqueue_style('sweetalert2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.0/dist/sweetalert2.min.css', array(), LABGENZ_CM_VERSION);
                }
            }, 50);
        }
        $this->add_admin_asset(
            'labgenz-cm-settings',
            $hooks,
            'labgenz-settings-handler.js',
            array('jquery', 'sweetalert2'),
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('labgenz_cm_save_menu_settings_nonce'),
                'saveSettings' => __('Settings saved.', LABGENZ_CM_TEXTDOMAIN),
                'saving'       => __('Saving...', LABGENZ_CM_TEXTDOMAIN),
                'errorMessage' => __('An error occurred while saving settings.', LABGENZ_CM_TEXTDOMAIN),
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
        $this->add_frontend_asset(
            'labgenz-cm-single-group-page',
            array(), // No specific page, will check in maybe_enqueue_frontend_assets
            'single-group-page.css',
            array(),
            LABGENZ_CM_VERSION,
            false,
            'css'
        );
        // Register group-management CSS and JS for BuddyBoss single group page only
        $this->add_buddyboss_asset(
            'labgenz-group-management-css',
            array('group_single'),
            'group-management.css',
            array(),
            LABGENZ_CM_VERSION,
            false,
            'css'
        );
        $this->add_buddyboss_asset(
            'labgenz-group-management-js',
            array('group_single'),
            'group-management.js',
            array('jquery'),
            LABGENZ_CM_VERSION,
            true,
            'js'
        );
    }

    /**
     * Enqueue all registered frontend assets.
     */
    public function enqueue_frontend_assets() {
        foreach ($this->frontend_assets as $page => $assets) {
            foreach ($assets as $handle => $asset) {
                $file_url = plugins_url(
                    'src/public/assets/' . ($asset['type'] === 'css' ? 'css/' : 'js/') . $asset['file'],
                    dirname(__DIR__, 2) . '/labgenz-community-management.php'
                );
                if ($asset['type'] === 'css') {
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
                }
            }
        }
    }
}
