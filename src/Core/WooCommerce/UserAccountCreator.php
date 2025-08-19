<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core\WooCommerce;

use LABGENZ_CM\Core\UserAccountManager;
use LABGENZ_CM\Subscriptions\SubscriptionHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles user account creation from WooCommerce orders
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Core/WooCommerce
 */
class UserAccountCreator {

/**
	 * Auto-create user from order if needed
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public static function auto_create_user_from_order( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Skip if order already has a user
		$existing_user_id = $order->get_user_id();
		if ( $existing_user_id > 0 ) {
			return;
		}

		// Check if this order contains subscription products
		$subscription_type = SubscriptionProcessor::get_subscription_type_from_order( $order );
		if ( ! $subscription_type ) {
			return;
		}

		$email      = $order->get_billing_email();
		$first_name = $order->get_billing_first_name();
		$last_name  = $order->get_billing_last_name();

		// Prepare billing data from order
		$billing_data = [
			'billing_first_name' => $order->get_billing_first_name(),
			'billing_last_name'  => $order->get_billing_last_name(),
			'billing_company'    => $order->get_billing_company(),
			'billing_address_1'  => $order->get_billing_address_1(),
			'billing_address_2'  => $order->get_billing_address_2(),
			'billing_city'       => $order->get_billing_city(),
			'billing_state'      => $order->get_billing_state(),
			'billing_postcode'   => $order->get_billing_postcode(),
			'billing_country'    => $order->get_billing_country(),
			'billing_email'      => $order->get_billing_email(),
			'billing_phone'      => $order->get_billing_phone(),
		];

		// Check if user already exists
		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user ) {
			// Update billing data for existing user
			UserAccountManager::update_billing_data( $existing_user->ID, $billing_data );
			
			// Update order with existing user
			$order->set_customer_id( $existing_user->ID );
			$order->save();

			// Apply subscription to existing user
			$subscription_handler = SubscriptionHandler::get_instance();
			$subscription_handler->apply_subscription_to_user_by_id( $existing_user->ID, $order );
			
			// Auto-login the existing user
			UserAccountManager::auto_login_user( $existing_user->ID );
			return;
		}

		// Create new user with billing data
		$user_id = UserAccountManager::create_user( $email, $first_name, $last_name, $billing_data );

		if ( is_wp_error( $user_id ) ) {
			return;
		}

		// Update order with new user
		$order->set_customer_id( $user_id );
		$order->save();

		// Apply subscription to new user
		$subscription_handler = SubscriptionHandler::get_instance();
		$subscription_handler->apply_subscription_to_user_by_id( $user_id, $order );

		// Auto-login the new user
		UserAccountManager::auto_login_user( $user_id );

		// Send welcome email with subscription details
		$subscription_name = $subscription_type === 'organization' ? 'Organization' : 'Basic';

		UserAccountManager::send_welcome_email(
			$user_id,
			'subscription',
			[
				'subscription_name' => $subscription_name,
				'order'             => $order,
			]
		);

		// Trigger user created action
		$password  = UserAccountManager::get_temp_password( $user_id );
		$user_data = [
			'user_login'   => get_userdata( $user_id )->user_login,
			'user_email'   => $email,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( $first_name . ' ' . $last_name ),
			'role'         => 'subscriber',
		];
		do_action( 'woocommerce_created_customer', $user_id, $user_data, $password );
	}
}
