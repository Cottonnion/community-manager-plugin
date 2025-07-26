<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Helper utilities
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Core
 */
class WooCommerceHelper {

	/**
	 * Single instance of the class
	 *
	 * @var WooCommerceHelper|null
	 */
	private static $instance = null;

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		// Private constructor
	}

	/**
	 * Get singleton instance
	 *
	 * @return WooCommerceHelper
	 */
	public static function get_instance(): WooCommerceHelper {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize method for loader compatibility
	 *
	 * @return void
	 */
	public function init(): void {
		// Initialize any hooks if needed
	}

	/**
	 * Get subscription type from order
	 *
	 * @param \WC_Order $order Order object
	 * @return string|null
	 */
	public function get_subscription_type_from_order( $order ): ?string {
		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			if ( $product ) {
				$sku = $product->get_sku();

				if ( in_array( $sku, SubscriptionHandler::SUBSCRIPTION_SKUS, true ) ) {
					$subscription_type = SubscriptionHandler::SUBSCRIPTION_TYPES[ $sku ] ?? null;
					return $subscription_type;
				}
			}
		}

		return null;
	}

	/**
	 * Auto-complete subscription orders after payment
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function auto_complete_subscription_order( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if this order contains subscription products
		$subscription_type = $this->get_subscription_type_from_order( $order );
		if ( ! $subscription_type ) {
			return;
		}

		// Auto-complete the order if it's not already completed
		if ( $order->get_status() !== 'completed' ) {
			$order->update_status( 'completed', __( 'Subscription order auto-completed after payment.', 'labgenz-community-management' ) );
		}
	}

	/**
	 * Store subscription in WC session
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function store_subscription_in_wc_session( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Get subscription type from order or meta
		$subscription_type = $order->get_meta( SubscriptionHandler::SUBSCRIPTION_TYPE_META );
		if ( ! $subscription_type ) {
			$subscription_type = $this->get_subscription_type_from_order( $order );
		}

		if ( ! $subscription_type ) {
			return;
		}

		// Store subscription data in WC session
		if ( function_exists( 'WC' ) && WC()->session ) {
			$expires   = $order->get_meta( SubscriptionHandler::SUBSCRIPTION_EXPIRES_META ) ?: $this->calculate_expiry_date();
			$resources = $order->get_meta( SubscriptionHandler::SUBSCRIPTION_RESOURCES_META ) ?: $this->get_allowed_resources( $subscription_type );

			$session_data = [
				'type'      => $subscription_type,
				'status'    => 'active',
				'expires'   => $expires,
				'resources' => $resources,
				'order_id'  => $order_id,
				'user_id'   => $order->get_user_id(),
			];

			WC()->session->set( 'active_subscription', $session_data );
		}
	}

	/**
	 * Handle guest checkout processing
	 *
	 * @param int       $order_id Order ID
	 * @param array     $posted_data Posted checkout data
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public function handle_guest_checkout( $order_id, $posted_data, $order ): void {
		// Only process if this is a guest order (no user ID)
		if ( $order->get_user_id() > 0 ) {
			return;
		}

		// Check if this order contains subscription products
		$subscription_type = $this->get_subscription_type_from_order( $order );
		if ( ! $subscription_type ) {
			return;
		}

		// Store guest data in WC session for later user creation
		if ( function_exists( 'WC' ) && WC()->session ) {
			$guest_data = [
				'order_id'          => $order_id,
				'email'             => $order->get_billing_email(),
				'first_name'        => $order->get_billing_first_name(),
				'last_name'         => $order->get_billing_last_name(),
				'phone'             => $order->get_billing_phone(),
				'subscription_type' => $subscription_type,
				'created_at'        => current_time( 'mysql' ),
			];

			WC()->session->set( 'guest_subscription_data', $guest_data );
		}
	}

	/**
	 * Auto-create user from order if needed
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function auto_create_user_from_order( $order_id ): void {
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
		$subscription_type = $this->get_subscription_type_from_order( $order );
		if ( ! $subscription_type ) {
			return;
		}

		$email      = $order->get_billing_email();
		$first_name = $order->get_billing_first_name();
		$last_name  = $order->get_billing_last_name();

		// Check if user already exists
		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user ) {
			// Update order with existing user
			$order->set_customer_id( $existing_user->ID );
			$order->save();

			// Apply subscription to existing user
			$subscription_handler = SubscriptionHandler::get_instance();
			$subscription_handler->apply_subscription_to_user_by_id( $existing_user->ID, $order );          // Auto-login the existing user
			UserAccountManager::auto_login_user( $existing_user->ID );
			return;
		}

		// Create new user
		$user_id = UserAccountManager::create_user( $email, $first_name, $last_name );

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

	/**
	 * Filter thank you page text for subscription purchases
	 *
	 * @param string    $text The default thank you text
	 * @param \WC_Order $order The order object
	 * @return string Modified thank you text
	 */
	public function filter_subscription_thank_you_text( $text, $order ): string {
		if ( ! $order ) {
			return $text;
		}

		// Check if this order contains subscription products
		$subscription_type = $this->get_subscription_type_from_order( $order );
		if ( ! $subscription_type ) {
			return $text;
		}

		// Get subscription name
		$subscription_name = $subscription_type === 'organization' ? 'Organization' : 'Basic';

		// Get user info
		$user_id   = $order->get_user_id();
		$user_name = '';
		if ( $user_id ) {
			$user      = get_userdata( $user_id );
			$user_name = $user ? $user->display_name : '';
		}

		if ( ! $user_name ) {
			$user_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		}

		// Create custom thank you message
		$custom_text = sprintf(
			'<div class="subscription-thank-you" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;">
				<h3 style="color: #28a745; margin-top: 0;">🎉 Welcome to Labgenz Community, %s!</h3>
				<p style="font-size: 16px; line-height: 1.6;">Thank you for subscribing to our <strong>%s Subscription</strong>!</p>
				<div style="margin: 15px 0;">
					<h4 style="color: #333; margin-bottom: 10px;">Your subscription includes:</h4>
					<ul style="margin: 10px 0; padding-left: 20px;">',
			esc_html( trim( $user_name ) ),
			esc_html( $subscription_name )
		);

		// Add subscription benefits based on type
		if ( $subscription_type === 'organization' ) {
			$custom_text .= '
				<li>✅ Organization creation and management</li>
				<li>✅ Organization access features</li>
				<li>✅ MLM articles access</li>
				<li>✅ and some other things</li>';
		} else {
			$custom_text .= '
				<li>✅ Access to basic course categories</li>
				<li>✅ Community participation</li>
				<li>✅ Basic support</li>
				<li>✅ and some other things</li>';
		}

		$custom_text .= '
					</ul>
				</div>
				<p style="font-size: 14px; color: #666; margin-bottom: 0;">
					Your subscription is now active and expires on <strong>' . date( 'F j, Y', strtotime( $this->calculate_expiry_date() ) ) . '</strong>.
				</p>
				<p style="font-size: 14px; color: #666; margin-bottom: 0;">
					You have been automatically logged in and can start exploring your new features immediately!
				</p>
			</div>';

		return $custom_text . $text;
	}

	/**
	 * Calculate subscription expiry date (default 1 year from now)
	 *
	 * @return string
	 */
	private function calculate_expiry_date(): string {
		return date( 'Y-m-d H:i:s', strtotime( SubscriptionHandler::DEFAULT_EXPIRY_DURATION ) );
	}

	/**
	 * Get allowed resources for subscription type
	 *
	 * @param string $subscription_type Subscription type
	 * @return array
	 */
	private function get_allowed_resources( string $subscription_type ): array {
		$subscription_handler = SubscriptionHandler::get_instance();
		return $subscription_handler->get_allowed_resources( $subscription_type );
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {
		// Prevent cloning
	}

	/**
	 * Prevent unserialization
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
