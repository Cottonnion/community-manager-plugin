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
	private array $container = [];

	/**
	 * Plugin components that need initialization
	 *
	 * @var array<string, class-string>
	 */
	private array $components = [];

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
		$this->components = [
			'core.assets'                             => \LABGENZ_CM\Core\AssetsManager::class,
			'core.menu'                               => \LABGENZ_CM\Core\MenuManager::class,
			'core.settings'                           => \LABGENZ_CM\Core\Settings::class,
			'core.ajax'                               => \LABGENZ_CM\Core\AjaxHandler::class,
			'core.woo_helper'                         => \LABGENZ_CM\Core\WooCommerceHelper::class,
			'core.profile_location_handler'           => \LABGENZ_CM\Core\ProfileLocationHandler::class,
			'core.organization_access'                => \LABGENZ_CM\Core\OrganizationAccess::class,
			'core.page_access_controller'             => \LABGENZ_CM\Core\PageAccessController::class,
			'core.user_menu_handler'                  => \LABGENZ_CM\Core\UserMenuHandler::class,
			'core.gettext_overrides'                  => \LABGENZ_CM\Core\Localization\GettextOverrides::class,
			'core.authentication.multi_email_manager' => \LABGENZ_CM\Core\Authentication\MultiEmailManager::class,
			'core.authentication.email_authenticator' => \LABGENZ_CM\Core\Authentication\EmailAuthenticator::class,
			'core.authentication.account.wc_page' 	  => \LABGENZ_CM\Core\Authentication\Account\AccountPageHandler::class,
			'core.authentication.profile.wp_profile'  => \LABGENZ_CM\Core\Authentication\Profile\WpProfile::class,

			/**
			 * @deprecated components
			 * their functionality either been refactored or not needed anymore.
			*/
			// 'core.admin_hooks'           => \LABGENZ_CM\Admin\AdminHooks::class,
			// 'core.invite_handler'        => \LABGENZ_CM\Core\InviteHandler::class,
			// 'core.remove_handler'        => \LABGENZ_CM\Core\RemoveHandler::class,

			'database.database'                       => \LABGENZ_CM\Database\Database::class,

			'admin.organization_access'               => \LABGENZ_CM\Admin\OrganizationAccessAdmin::class,
			'admin.weekly_articles_admin'             => \LABGENZ_CM\Admin\DailyArticleAdmin::class,
			'admin.reviews'                           => \LABGENZ_CM\Admin\ReviewsAdmin::class,
			'admin.subscription_admin'                => \LABGENZ_CM\Admin\SubscriptionsAdmin::class,

			'public.organization_access'              => \LABGENZ_CM\Public\OrganizationAccessPublic::class,

			'xprofile.field_type_handler'             => \LABGENZ_CM\XProfile\XProfileFieldTypeHandler::class,

			'groups.create_handler'                   => \LABGENZ_CM\Groups\GroupCreationHandler::class,
			'groups.member_handler'                   => \LABGENZ_CM\Groups\GroupMembersHandler::class,
			'groups.manage_members_tab'               => \LABGENZ_CM\Groups\GroupTabs::class,
			'groups.members_map_handler'              => \LABGENZ_CM\Groups\MembersMapHandler::class,
			'groups.member_visibility'                => \LABGENZ_CM\Groups\GroupMemberVisibility::class,
			'groups.filters_handler'                  => \LABGENZ_CM\Groups\GroupFiltersHandler::class,

			'articles.articles_post_type'             => \LABGENZ_CM\Articles\ArticlesPostType::class,
			'articles.core_handler'                   => \LABGENZ_CM\Articles\ArticlesHandler::class,
			'articles.weekly_handler'                 => \LABGENZ_CM\Articles\DailyArticleHandler::class,
			'articles.reviews_handler'                => \LABGENZ_CM\Articles\ReviewsHandler::class,
			'articles.download_handler'               => \LABGENZ_CM\Articles\SingleArticleHandler::class,
			'articles.card_display'                   => \LABGENZ_CM\Articles\ArticleCardDisplayHandler::class,
			'articles.authors.Author_cpt'		      => \LABGENZ_CM\Articles\Authors\AuthorCPT::class,
			'articles.authors.Author_display'          => \LABGENZ_CM\Articles\Authors\AuthorDisplayHandler::class,

			'gamipress.header_integration'            => \LABGENZ_CM\Gamipress\GamiPressHeaderIntegration::class,
			'gamipress.currency_handler'              => \LABGENZ_CM\Gamipress\GamipressCurrencyHandler::class,

			'subscription.handler'                    => \LABGENZ_CM\Subscriptions\SubscriptionHandler::class,
			'subscription.shortcode'                  => \LABGENZ_CM\Subscriptions\Shortcodes\SubscriptionDetailsShortcode::class,
			'widgets.widgets.elementor'               => \LABGENZ_CM\Widgets\Elementor\ElementorIntegration::class,
		];
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Plugin activation/deactivation
		register_activation_hook( LABGENZ_CM_PATH . 'labgenz-community-management.php', [ self::class, 'activate' ] );
		register_deactivation_hook( LABGENZ_CM_PATH . 'labgenz-community-management.php', [ self::class, 'deactivate' ] );

		// Initialize components after plugins loaded
		add_action( 'plugins_loaded', [ $this, 'init_components' ], 20 );

		// Load textdomain
		add_action( 'init', [ $this, 'load_textdomain' ] );
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
					// error_log( 'LabgenzCmLoader: Called init() on component: ' . $key );
				} else {
					$this->container[ $key ] = new $class();
					// error_log( 'LabgenzCmLoader: Called init() on component: ' . $key );
				}

				// Call init method if it exists
				if ( method_exists( $this->container[ $key ], 'init' ) ) {
					$this->container[ $key ]->init();
					// error_log( 'LabgenzCmLoader: Called init() on component: ' . $key );
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
			LABGENZTEXTDOMAIN,
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
		// TODO: trigger component initialization here
	}
}
