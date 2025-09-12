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
	private array $admin_assets = [];

	/**
	 * Holds the array of frontend scripts and styles.
	 *
	 * @var array<string, array<string, array>>
	 */
	private array $frontend_assets = [];

	/**
	 * Holds the array of BuddyBoss-specific frontend scripts and styles.
	 *
	 * @var array<string, array<string, array>>
	 */
	private array $buddyboss_assets = [];

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		// Enqueue SweetAlert2 for both frontend and admin
		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], '11.0.0', true );
			},
			5
		);

		// Article filtering scripts are handled in ArticleCardDisplayHandler.php

		add_action(
			'admin_enqueue_scripts',
			function () {
				wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], '11.0.0', true );
			},
			5
		);

		// Add SheetJS for Excel file support
		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_enqueue_script( 'xlsx-js', 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js', [], '0.18.5', true );
			},
			20
		);

		// Add leaderboard styles and scripts
		add_action(
			'wp_enqueue_scripts',
			[ $this, 'enqueue_leaderboard_assets' ],
			25
		);

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ], 40, 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets_to_enqueue' ], 20, 0 );
		add_action( 'admin_init', [ $this, 'register_assets_to_enqueue' ], 20, 0 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ], 99, 0 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_location_field_scripts' ] );

		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_enqueue_style(
					'buddyboss-buddypanel-css',
					LABGENZ_CM_URL . 'src/Public/assets/css/lab-buddypanel.css',
					[],
					'4.6.1'
				);
			},
			1
		);

		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_enqueue_style(
					'single-author-css',
					LABGENZ_CM_URL . 'src/Public/assets/css/single-author.css',
					[],
					'1.0.4'
				);
			},
			1
		);

		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_enqueue_style(
					'labgenz-cm-authors-archive',
					LABGENZ_CM_URL . 'src/Public/assets/css/authors-archive.css',
					[],
					'1.2.4'
				);

				// Enqueue font awesome if needed for author cards
				if ( is_post_type_archive( 'mlmmc_author' ) || is_singular( 'mlmmc_author' ) || is_page() || is_single() ) {
					wp_enqueue_style(
						'font-awesome',
						'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
						[],
						'5.15.4'
					);
				}
			},
			1
		);

		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_enqueue_script(
					'buddypanel-controller-js-file',
					get_stylesheet_directory_uri() . '/template-parts/buddypanel/buddypanel-controller.js',
					[ 'jquery' ],
					'1.4.2',
					true
				);

				wp_enqueue_script(
					'lab-commons',
					LABGENZ_CM_URL . 'src/Public/assets/js/commons.js',
					[ 'jquery' ],
					'1.0.8',
					true
				);

				wp_enqueue_script(
					'lab-toolip-global',
					LABGENZ_CM_URL . 'src/Public/assets/js/global-toolip.js',
					[ 'jquery' ],
					'1.0.1',
					true
				);
			},
			20
		);
		// Enqueue map assets
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_map_assets' ], 30 );
	}

	/**
	 * Adds an admin script or style to the array of admin assets.
	 *
	 * @param string $handle
	 * @param array  $pages
	 * @param string $file
	 * @param array  $dependencies
	 * @param array  $localization
	 * @param string $version
	 * @param bool   $enqueue_in_footer
	 * @return void
	 */
	public function add_admin_asset(
		string $handle,
		array $pages,
		string $file,
		array $dependencies = [],
		array $localization = [],
		string $version = LABGENZ_CM_VERSION,
		bool $enqueue_in_footer = true
	): void {
		foreach ( $pages as $page ) {
			$this->admin_assets[ $page ][ $handle ] = [
				'file'              => $file,
				'dependencies'      => $dependencies,
				'version'           => $version,
				'enqueue_in_footer' => $enqueue_in_footer,
				'localization'      => $localization,
			];
			// Directly enqueue if on the correct admin page
			if ( is_admin() && get_current_screen() && get_current_screen()->id === $page ) {
				$file_url = LABGENZ_CM_URL . 'src/Admin/assets/' . ( 'css' === substr( $handle, -3 ) ? 'css/' : 'js/' ) . $file;
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
	 * @param array  $pages Array of page slugs or IDs where the asset should be loaded
	 * @param string $file
	 * @param array  $dependencies
	 * @param string $version
	 * @param bool   $enqueue_in_footer
	 * @param string $type 'css' or 'js'
	 * @param array  $localization Optional. Data to localize for JS assets.
	 * @return void
	 */
	public function add_frontend_asset(
		string $handle,
		array $pages,
		string $file,
		array $dependencies = [],
		string $version = LABGENZ_CM_VERSION,
		bool $enqueue_in_footer = true,
		string $type = 'css',
		array $localization = []
	): void {
		foreach ( $pages as $page ) {
			$this->frontend_assets[ $page ][ $handle ] = [
				'file'              => $file,
				'dependencies'      => $dependencies,
				'version'           => $version,
				'enqueue_in_footer' => $enqueue_in_footer,
				'type'              => $type,
				'localization'      => $localization,
			];
			// Directly enqueue if on the correct frontend page
			$is_target = false;
			if ( '' === $page && function_exists( 'bp_is_group' ) && bp_is_group() ) {
				$is_target = true;
			} elseif ( '' !== $page && is_page( $page ) ) {
				$is_target = true;
			}
			if ( $is_target ) {
				$file_url = LABGENZ_CM_URL . 'src/Public/assets/' . ( 'css' === $type ? 'css/' : 'js/' ) . $file;
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
					// Localize script if localization data is provided
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
	 * Adds a BuddyBoss-specific frontend asset.
	 *
	 * @param string $handle
	 * @param array  $target_pages Array of BuddyBoss page types: group_single, groups_directory, members_directory, activity_directory, etc.
	 * @param string $file
	 * @param array  $dependencies
	 * @param string $version
	 * @param bool   $enqueue_in_footer
	 * @param string $type 'css' or 'js'
	 * @param array  $localization Optional. Data to localize for JS assets.
	 * @return void
	 */
	public function add_buddyboss_asset(
		string $handle,
		array $target_pages,
		string $file,
		array $dependencies = [],
		string $version = LABGENZ_CM_VERSION,
		bool $enqueue_in_footer = true,
		string $type = 'css',
		array $localization = []
	): void {
		foreach ( $target_pages as $target ) {
			$this->buddyboss_assets[ $target ][ $handle ] = [
				'file'              => $file,
				'dependencies'      => $dependencies,
				'version'           => $version,
				'enqueue_in_footer' => $enqueue_in_footer,
				'type'              => $type,
				'localization'      => $localization,
			];
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
			} elseif ( 'group_create' === $target && function_exists( 'bp_is_group_create' ) && bp_is_group_create() ) {
				$is_target = true;
			}
			if ( $is_target ) {
				// Add to frontend assets array for later enqueueing
				$this->add_frontend_asset( $handle, [ $target ], $file, $dependencies, $version, $enqueue_in_footer, $type );
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
			$file_url = LABGENZ_CM_URL . 'src/Admin/assets/' . ( 'css' === substr( $handle, -3 ) ? 'css/' : 'js/' ) . $asset['file'];
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

		$this->add_admin_asset(
			'labgenz-cm-reviews-admin-css',
			[ 'mlm-mastery-communities_page_article-reviews' ],
			'reviews-admin.css',
			[],
			[],
			'1.0.3' // Bumped version
		);

		// Add the new table sorting and search styles
		$this->add_admin_asset(
			'labgenz-cm-reviews-table-css',
			[ 'mlm-mastery-communities_page_article-reviews' ],
			'reviews-table.css',
			[],
			[],
			'1.0.0'
		);
		$this->add_admin_asset(
			'labgenz-cm-reviews-admin-js',
			[ 'mlm-mastery-communities_page_article-reviews' ],
			'article-reviews.js',
			[ 'jquery', 'sweetalert2' ],
			[
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'mlmmc_reviews_action' ),
				'confirmDelete'     => __( 'Are you sure you want to delete this review?', 'labgenz-cm' ),
				'confirmDeleteText' => __( 'This action cannot be undone.', 'labgenz-cm' ),
				'confirmBulkDelete' => __( 'Are you sure you want to delete the selected reviews?', 'labgenz-cm' ),
				'messages'          => [
					'submitting' => __( 'Submitting...', 'labgenz-cm' ),
					'success'    => __( 'Rating submitted successfully!', 'labgenz-cm' ),
					'error'      => __( 'Error submitting rating. Please try again.', 'labgenz-cm' ),
				],
			],
			'1.0.4'
		);

		// Add the new table sorting and search functionality
		$this->add_admin_asset(
			'labgenz-cm-reviews-table-js',
			[ 'mlm-mastery-communities_page_article-reviews' ],
			'reviews-table.js',
			[ 'jquery' ],
			[
				'noResults' => __( 'No reviews found matching your search criteria.', 'labgenz-cm' ),
			],
			'1.0.0'
		);

		// Add frontend assets for article reviews
		$this->add_frontend_asset(
			'mlmmc-article-reviews',
			[ '' ],  // Empty array means it will be loaded on all pages
			'reviews.js',
			[ 'jquery' ],
			'1.6.0', // Bumped version
			true,
			'js',
			[
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mlmmc_article_review' ),
				'messages' => [
					'submitting' => __( 'Submitting...', 'labgenz-cm' ),
					'success'    => __( 'Thank you for your rating!', 'labgenz-cm' ),
					'error'      => __( 'Error submitting rating. Please try again.', 'labgenz-cm' ),
				],
			]
		);
		$appearance_hooks = array_filter( array_merge( $hooks, [ $appearance_hook ] ) );
		$this->add_admin_asset(
			'labgenz-cm-settings-css',
			[ $hooks[0] ],
			'labgenz-cm-settings.css'
		);

		// Enqueue SweetAlert2 from CDN
		foreach ( $hooks as $hook ) {
			add_action(
				'admin_enqueue_scripts',
				function () use ( $hook ) {
					if ( get_current_screen() && get_current_screen()->id === $hook ) {
						wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.0/dist/sweetalert2.all.min.js', [ 'jquery' ], LABGENZ_CM_VERSION, true );
						wp_enqueue_style( 'sweetalert2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11.22.0/dist/sweetalert2.min.css', [], LABGENZ_CM_VERSION );
					}
				},
				50
			);
		}
		$this->add_admin_asset(
			'labgenz-cm-settings',
			$hooks,
			'labgenz-settings-handler.js',
			[ 'jquery', 'sweetalert2' ],
			[
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'labgenz_cm_save_menu_settings_nonce' ),
				'saveSettings' => __( 'Settings saved.', 'labgenz-community-management' ),
				'saving'       => __( 'Saving...', 'labgenz-community-management' ),
				'errorMessage' => __( 'An error occurred while saving settings.', 'labgenz-community-management' ),
			],
			LABGENZ_CM_VERSION,
			true
		);

		$this->add_admin_asset(
			'labgenz-appearance-admin',
			$hooks,
			'labgenz-appearance-admin.js',
			[ 'jquery', 'sweetalert2' ],
			[
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'labgenz_appearance_nonce' ),
				'saveSettings' => __( 'Settings saved.', 'labgenz-community-management' ),
				'saving'       => __( 'Saving...', 'labgenz-community-management' ),
				'errorMessage' => __( 'An error occurred while saving settings.', 'labgenz-community-management' ),
			],
			LABGENZ_CM_VERSION,
			true
		);

		$this->add_admin_asset(
			'labgenz-cm-settings-page-css',
			$hooks,
			'settings-page.css',
			[],
			[],
			LABGENZ_CM_VERSION,
			false
		);
		// Register single group page CSS for frontend using new method
		$this->add_buddyboss_asset(
			'lab-single-group-page-css',
			[ 'group_single' ],
			'lab-single-group-page.css',
			[],
			'1.6',
			false,
			'css'
		);
		// Register group-management CSS and JS for BuddyBoss single group page only
		$this->add_buddyboss_asset(
			'lab-group-management-css',
			[ 'group_single' ],
			'lab-group-management.css',
			[],
			'1.7.32',
			false,
			'css'
		);

		$this->add_buddyboss_asset(
			'lab-group-management-js',
			[ 'group_single' ],
			'lab-group-management.js',
			[ 'jquery', 'xlsx-js' ],
			'2.7.4',
			true,
			'js',
			[
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'lab_group_management_nonce' ),
				'group_id'   => bp_is_group() ? bp_get_current_group_id() : 1,
				'group_name' => bp_is_group() ? bp_get_current_group_name() : 'This Group',
			]
		);
		// Register group-invite JS for BuddyBoss single group page only
		$this->add_buddyboss_asset(
			'lab-group-invite',
			[ 'group_single' ],
			'lab-group-invite-member.js',
			[ 'jquery' ],
			'2.6.3',
			true,
			'js',
			[
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'lab_group_management_nonce' ),
				'group_id'   => bp_is_group() ? bp_get_current_group_id() : 1,
				'group_name' => bp_is_group() ? bp_get_current_group_name() : 'This Group',
			]
		);
		$this->add_buddyboss_asset(
			'group-remove-member',
			[ 'group_single' ],
			'lab-group-remove-member.js',
			[ 'jquery' ],
			'1.9',
			true,
			'js',
			[
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'lab_group_management_nonce' ),
				'group_id'   => bp_is_group() ? bp_get_current_group_id() : 1,
				'group_name' => bp_is_group() ? bp_get_current_group_name() : 'This Group',
			]
		);
		// Enqueue lab-group-create-form.css for BuddyBoss group creation page
		$this->add_buddyboss_asset(
			'lab-group-create-form-css',
			[ 'group_create' ],
			'lab-group-create-form.css',
			[],
			'1.0.9',
			false,
			'css'
		);
		// Enqueue lab-group-create-form.jquery.js for BuddyBoss group creation page
		$this->add_buddyboss_asset(
			'lab-group-create-form',
			[ 'group_create' ],
			'lab-group-create-form.js',
			[ 'jquery' ],
			'2.3.1',
			true,
			'js',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			]
		);
		$this->add_buddyboss_asset(
			'lab-group-members-map-css',
			[ 'group_single' ],
			'lab-group-members-map.css',
			[],
			'1.2.6',
			false,
			'css'
		);
		$this->add_admin_asset(
			'labgenz-cm-appearance-css',
			[ $hooks[0], 'labgenz-cm-appearance' ],
			'appearance-admin.css',
			[],
			[],
			LABGENZ_CM_VERSION,
			false
		);

		$this->add_frontend_asset(
			'labgenz-ajax-articles-search',
			[ '' ],
			'lab-ajax-articles-search.js',
			[ 'jquery' ],
			'3.7',
			true,
			'js',
			[
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'mlmmc_search_nonce' ),
				'posts_per_page' => 20,
				'post_type'      => 'mlmmc_artiicle',
				'template_id'    => 42495,
			]
		);

		$this->add_frontend_asset(
			'gamipress-header',
			[ '' ],
			'gamipress-header.js',
			[ 'jquery' ],
			'1.2.7',
			true,
			'js',
			[]
		);

		// Only load alias emails script on the account edit page
		add_action(
			'wp_enqueue_scripts',
			function () {
				// Check if we're on the my-account/edit-account page
				if ( function_exists( 'is_account_page' ) && is_account_page() && is_wc_endpoint_url( 'edit-account' ) ) {
					wp_enqueue_script(
						'aliase-emails',
						LABGENZ_CM_URL . 'src/Public/assets/js/alias-emails.js',
						[ 'jquery' ],
						'1.1.4',
						true
					);
					wp_localize_script(
						'aliase-emails',
						'aliase_emails_data',
						[
							'ajax_url' => admin_url( 'admin-ajax.php' ),
							'nonce'    => wp_create_nonce( 'email_alias_nonce' ),
						]
					);
				}
			},
			30
		);

		$this->add_frontend_asset(
			'single-article-js',
			[ '' ],
			'single-article.js',
			[ 'jquery' ],
			'1.0.1',
			true,
			'js',
			[]
		);

		$this->add_frontend_asset(
			'members-profile-widget',
			[ '' ],
			'members-widget.js',
			[ 'jquery' ],
			'1.0.8',
			true,
			'js',
			[]
		);

		$this->add_frontend_asset(
			'memebrs-profile-widget-css',
			[ '' ],
			'members-widget.css',
			[],
			'1.1.9',
			false,
			'css'
		);

		// Only load alias emails CSS on the account edit page
		add_action(
			'wp_enqueue_scripts',
			function () {
				// Check if we're on the my-account/edit-account page
				if ( function_exists( 'is_account_page' ) && is_account_page() && is_wc_endpoint_url( 'edit-account' ) ) {
					wp_enqueue_style(
						'alias-emails-css',
						LABGENZ_CM_URL . 'src/Public/assets/css/alias-emails.css',
						[],
						'1.1.9',
						false
					);
				}
			},
			30
		);

		$this->add_frontend_asset(
			'labgenz-gamipress-header-css',
			[ '' ],
			'lab-gamipress-header.css',
			[],
			'2.6.2',
			false,
			'css'
		);

		$this->add_frontend_asset(
			'mlmmc-checkout-css',
			[ '' ],
			'checkout.css',
			[],
			'1.0.1',
			false,
			'css'
		);

		$this->add_frontend_asset(
			'articles-review-css',
			[ '' ],
			'article-reviews.css',
			[],
			'2.6.5', // Bumped version
			false,
			'css'
		);

		$this->add_frontend_asset(
			'location-common-css',
			[ '' ],
			'location-field.css',
			[],
			'1.0.1',
			false,
			'css'
		);

		$this->add_frontend_asset(
			'labgenz-weekly-article-css',
			[ '' ],
			'lab-weekly-article-page.css',
			[],
			'1.0.2',
			false,
			'css'
		);

		// Register debug dropdown CSS
		$this->add_frontend_asset(
			'single-article-css',
			[ ' ' ],
			'single-article.css',
			[],
			'1.0.9',
			false,
			'css'
		);
	}

	/**
	 * Enqueue all registered frontend assets.
	 */
	public function enqueue_frontend_assets() {
		foreach ( $this->frontend_assets as $page => $assets ) {
			foreach ( $assets as $handle => $asset ) {
				$file_url = LABGENZ_CM_URL . 'src/Public/assets/' . ( 'css' === $asset['type'] ? 'css/' : 'js/' ) . $asset['file'];
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
					// Localize script if localization data is provided
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
	}

	/**
	 * Enqueue map-specific assets (Leaflet, MarkerCluster, and custom map scripts)
	 *
	 * @return void
	 */
	public function enqueue_map_assets(): void {
		// Only enqueue on the members map page
		if ( bp_is_group() && bp_is_current_action( 'members-map' ) ) {
			// Enqueue Leaflet CSS and JS (free, open-source map library)
			wp_enqueue_style(
				'leaflet-css',
				'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
				[],
				'1.9.4'
			);

			wp_enqueue_script(
				'leaflet-js',
				'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
				[],
				'1.9.4',
				true
			);

			// Enqueue MarkerCluster CSS and JS for clustering
			wp_enqueue_style(
				'leaflet-markercluster-css',
				'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
				[ 'leaflet-css' ],
				'1.5.3'
			);

			wp_enqueue_style(
				'leaflet-markercluster-default-css',
				'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css',
				[ 'leaflet-markercluster-css' ],
				'1.5.3'
			);

			wp_enqueue_script(
				'leaflet-markercluster-js',
				'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
				[ 'leaflet-js' ],
				'1.5.3',
				true
			);

			// Enqueue our custom map CSS
			// wp_enqueue_style(
			// 'lab-group-members-map-css',
			// LABGENZ_CM_URL . 'src/Public/assets/css/lab-group-members-map.css',
			// [ 'leaflet-markercluster-default-css' ],
			// '7.1.0'
			// );

			// Enqueue our modular JS files
			// Core map module
			wp_enqueue_script(
				'map-core-js',
				LABGENZ_CM_URL . 'src/Public/assets/js/map-modules/map-core.js',
				[ 'jquery', 'leaflet-js' ],
				'7.0.8',
				true
			);

			// Marker manager module
			wp_enqueue_script(
				'marker-manager-js',
				LABGENZ_CM_URL . 'src/Public/assets/js/map-modules/marker-manager.js',
				[ 'jquery', 'leaflet-js', 'leaflet-markercluster-js', 'map-core-js' ],
				'7.2.7',
				true
			);

			// Data handler module
			wp_enqueue_script(
				'data-handler-js',
				LABGENZ_CM_URL . 'src/Public/assets/js/map-modules/data-handler.js',
				[ 'jquery' ],
				'7.0.8',
				true
			);

			// Map utilities module
			wp_enqueue_script(
				'map-utils-js',
				LABGENZ_CM_URL . 'src/Public/assets/js/map-modules/map-utils.js',
				[ 'jquery', 'leaflet-js' ],
				'7.0.8',
				true
			);

			// Main map JS
			wp_enqueue_script(
				'members-map-js',
				LABGENZ_CM_URL . 'src/Public/assets/js/members-map-new.js',
				[ 'jquery', 'leaflet-js', 'leaflet-markercluster-js', 'map-core-js', 'marker-manager-js', 'data-handler-js', 'map-utils-js' ],
				'7.0.7',
				true
			);

			// Pass data to script
			wp_localize_script(
				'members-map-js',
				'MembersMapData',
				[
					'ajaxurl'         => admin_url( 'admin-ajax.php' ),
					'group_id'        => bp_get_current_group_id(),
					'nonce'           => wp_create_nonce( 'members-map-nonce' ),
					'current_user_id' => get_current_user_id(),
				]
			);
		}
	}

	/**
	 * Adds a frontend script or style for specific post types.
	 *
	 * @param string $handle
	 * @param array  $post_types Array of post types where the asset should be loaded
	 * @param string $file
	 * @param array  $dependencies
	 * @param string $version
	 * @param bool   $enqueue_in_footer
	 * @param string $type 'css' or 'js'
	 * @param array  $localization Optional. Data to localize for JS assets.
	 * @return void
	 */
	public function add_frontend_asset_for_post_types(
		string $handle,
		array $post_types,
		string $file,
		array $dependencies = [],
		string $version = LABGENZ_CM_VERSION,
		bool $enqueue_in_footer = true,
		string $type = 'css',
		array $localization = []
	): void {
		add_action(
			'wp_enqueue_scripts',
			function () use (
				$handle,
				$post_types,
				$file,
				$dependencies,
				$version,
				$enqueue_in_footer,
				$type,
				$localization
			) {
				if ( is_singular( $post_types ) ) {
					$file_url = LABGENZ_CM_URL . 'src/Public/assets/' . ( 'css' === $type ? 'css/' : 'js/' ) . $file;
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
						// Localize script if localization data is provided
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
		);
	}
	/**
	 * Enqueue scripts and styles for this field type
	 */
	public static function enqueue_location_field_scripts() {
		// Only enqueue on profile edit pages
		if ( ! function_exists( 'bp_is_user_profile_edit' ) ||
			( ! bp_is_user_profile_edit() && ! bp_is_register_page() ) ) {
			return;
		}

		// Enqueue Leaflet for map display
		wp_enqueue_style(
			'leaflet-css',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
			[],
			'1.9.4'
		);

		wp_enqueue_script(
			'leaflet-js',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
			[],
			'1.9.4',
			true
		);

		// Enqueue our custom script
		wp_enqueue_script(
			'location-field-js',
			LABGENZ_CM_URL . 'src/Public/assets/js/location-field.js',
			[ 'jquery', 'leaflet-js' ],
			LABGENZ_CM_VERSION,
			true
		);

		// Enqueue visibility fix script
		wp_enqueue_script(
			'location-field-visibility-fix-js',
			LABGENZ_CM_URL . 'src/Public/assets/js/location-field-visibility-fix.js',
			[ 'jquery', 'location-field-js' ],
			'1.0.2',
			true
		);

		// Add script to apply custom classes to visibility options
		wp_add_inline_script(
			'location-field-visibility-fix-js',
			'
			jQuery(document).ready(function($) {
				// Add classes and info icons to visibility options
				function addClassesToVisibilityOptions() {
					$(".field-visibility-settings .bp-radio-wrap label").each(function() {
						// Get the input associated with this label
						var input = $(this).prev("input");
						var inputValue = input.val();
						
						if (inputValue) {
							// Add class to the label
							$(this).addClass("visibility-option-" + inputValue);
							
							// Don\'t add the icon twice
							if ($(this).find(".visibility-info-icon").length === 0) {
								// Add info icon with different tooltip for each option
								var tooltipText = "";
								
								if (inputValue === "exact_location") {
									tooltipText = "Shows your exact location coordinates on the map";
								} else if (inputValue === "privacy_offset") {
									tooltipText = "Shows your location with a random offset for privacy (500m-2km)";
								} else if (inputValue === "hidden") {
									tooltipText = "Your location will not be visible to other members";
								}
								
								if (tooltipText) {
									// Append to the field-visibility-text span
									$(this).find(".field-visibility-text").after(\'<span class="visibility-info-icon" data-this-mean="\' + tooltipText + \'">ⓘ</span>\');
								}
							}
						}
					});
				}
				
				// Call initially
				addClassesToVisibilityOptions();
				
				// Also call when visibility settings are toggled
				$(document).on("click", ".visibility-toggle-link", function() {
					setTimeout(addClassesToVisibilityOptions, 100);
				});
			});
			'
		);

		// Localize script with AJAX data
		wp_localize_script(
			'location-field-js',
			'LocationFieldData',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'location_field_nonce' ),
				'strings' => [
					'detecting'      => __( 'Detecting...', 'buddypress' ),
					'detect_failed'  => __( 'Could not detect your location. Please enter manually.', 'buddypress' ),
					'geocode_failed' => __( 'Could not find location. Please try a different search.', 'buddypress' ),
					'searching'      => __( 'Searching...', 'buddypress' ),
				],
			]
		);
	}

	/**
	 * Enqueue leaderboard assets if we're on a group leaderboard page
	 *
	 * @return void
	 */
	public function enqueue_leaderboard_assets() {
		// Only enqueue if we're on a group leaderboard page
		if ( bp_is_group() && bp_is_current_action( 'leaderboard' ) ) {
			wp_enqueue_style(
				'leaderboard-css',
				LABGENZ_CM_URL . 'src/Public/assets/css/leaderboard/leaderboard.css',
				[],
				'1.1.4'
			);

			wp_enqueue_script(
				'leaderboard-js',
				LABGENZ_CM_URL . 'src/Public/assets/js/leaderboard/leaderboard.js',
				[ 'jquery' ],
				'1.0.1',
				true
			);

			// Localize script data for inline JS and AJAX functionality
			wp_localize_script(
				'jquery',
				'labgenz_leaderboard',
				[
					'ajax_url'        => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'mlmmc_leaderboard_nonce' ),
					'group_id'        => bp_get_current_group_id(),
					'current_user_id' => get_current_user_id(),
				]
			);

			// Localize i18n strings
			wp_localize_script(
				'jquery',
				'labgenz_i18n',
				[
					'loading_message' => esc_html__( 'Loading leaderboard data...', 'labgenz-cm' ),
					'error_message'   => esc_html__( 'Could not load leaderboard data. Please try again.', 'labgenz-cm' ),
				]
			);
		}
	}
}
