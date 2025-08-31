<?php

namespace LABGENZ_CM\Core;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;
use LABGENZ_CM\Subscriptions\Helpers\SubscriptionTypeHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UserMenuHandler {

	/**
	 * Initialize the custom menu handler
	 */
	public function init(): void {
		add_filter( 'wp_nav_menu_items', [ $this, 'add_custom_menu_items' ], 10, 2 );

		// Add My Subscriptions to user profile dropdown menu
		add_action( 'buddyboss_theme_header_user_menu_items', [ $this, 'add_subscription_menu_item' ] );
	}

	public function add_subscription_menu_item(): void {
		if ( is_user_logged_in() && SubscriptionHandler::user_has_active_subscription( get_current_user_id() ) ) {
			echo '<li>
				<a href="' . esc_url( site_url( '/my-subscriptions/' ) ) . '">
					<i class="bb-icon-l bb-icon-credit-card"></i> My Subscription
				</a>
			</li>';
		}
	}

	/**
	 * Add custom navigation items based on user subscription or role
	 *
	 * @param string $items Existing menu items
	 * @param object $args  Menu arguments
	 * @return string Modified menu items
	 */
	public function add_custom_menu_items( $items, $args ): string {
		if ( $args->theme_location === 'header-menu' || $args->theme_location === 'primary' ) {
			$user_id = get_current_user_id();

			$has_basic            = SubscriptionTypeHelper::user_has_only_basic_subscription( $user_id );
			$has_other            = ! $has_basic && SubscriptionHandler::user_has_active_subscription( $user_id );
			$highest_subscription = SubscriptionTypeHelper::get_highest_subscription_type( $user_id );

			// Basic subscription link
			if ( $has_basic && ! $has_other ) {
				$custom_item = '<li id="menu-item-other" class="menu-item menu-item-type-custom menu-item-object-custom">
					<a href="' . esc_url( home_url( '/memberships/' ) ) . '" class="bb-nav-menu-link">
						<span class="link-text">Memberships</span>
					</a>
				</li>';
				$items      .= $custom_item;
			}

			// Other subscriptions or admin
			if ( $has_other || current_user_can( 'manage_options' ) ) {
				$custom_item = '<li id="menu-item-basic" class="menu-item menu-item-type-custom menu-item-object-custom">
					<a href="' . esc_url( home_url( '/courses/' ) ) . '" class="bb-nav-menu-link">
						<span class="link-text">Courses</span>
					</a>
				</li>';
				$items      .= $custom_item;
			}

			// Add Organization Access button
			if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) && SubscriptionHandler::user_has_resource_access( $user_id, 'organization_access' ) ) {
				$org_button = '<li>
					<button id="labgenz-org-access-btn"><span></span></button>
				</li>';
				$items     .= $org_button;
			}
		}

		return $items;
	}
}
