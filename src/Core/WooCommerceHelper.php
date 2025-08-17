<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;
use LABGENZ_CM\Core\WooCommerce\SubscriptionProcessor;
use LABGENZ_CM\Core\WooCommerce\OrderStatusManager;
use LABGENZ_CM\Core\WooCommerce\OrderStatusLogger;
use LABGENZ_CM\Core\WooCommerce\CheckoutHandler;
use LABGENZ_CM\Core\WooCommerce\PaymentGatewayManager;
use LABGENZ_CM\Core\WooCommerce\UserAccountCreator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Helper utilities
 *
 * This class serves as a facade for the various WooCommerce helper classes.
 * It delegates functionality to specialized classes while maintaining backward compatibility.
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
		// Payment gateway filters
		add_filter( 'woocommerce_available_payment_gateways', [ PaymentGatewayManager::class, 'filter_payment_gateways_by_currency' ] );
		// add_filter('woocommerce_available_payment_gateways', [PaymentGatewayManager::class, 'filter_gamipress_gateways_for_subscription'], 15);

		// Order status management
		add_filter( 'woocommerce_payment_complete_order_status', [ OrderStatusManager::class, 'auto_complete_virtual_orders' ], 10, 3 );
		add_action( 'woocommerce_thankyou', [ OrderStatusManager::class, 'force_complete_order' ], 5 );
		// Also catch orders that might be set to "on-hold" through other payment methods
		add_action( 'woocommerce_order_status_on-hold', [ OrderStatusManager::class, 'change_on_hold_to_complete' ], 10, 1 );

		// Order status tracking
		add_action( 'woocommerce_new_order', [ OrderStatusManager::class, 'track_new_order' ], 10, 1 );
		add_action( 'woocommerce_order_status_changed', [ OrderStatusManager::class, 'track_order_status_changed' ], 10, 4 );
		add_action( 'woocommerce_payment_complete', [ OrderStatusManager::class, 'track_payment_complete' ], 10, 1 );

		// Direct checkout functionality
		add_filter( 'woocommerce_add_to_cart_redirect', [ CheckoutHandler::class, 'redirect_to_checkout' ] );
		add_action( 'woocommerce_add_to_cart', [ CheckoutHandler::class, 'add_to_cart_message' ], 10, 6 );

		// Subscription processing
		add_action( 'woocommerce_payment_complete', [ SubscriptionProcessor::class, 'auto_complete_subscription_order' ], 1 );
		add_action( 'woocommerce_thankyou', [ SubscriptionProcessor::class, 'store_subscription_in_wc_session' ], 10 );

		// User account handling
		add_action( 'woocommerce_thankyou', [ UserAccountCreator::class, 'auto_create_user_from_order' ], 5 );
		add_action( 'woocommerce_thankyou', [ CheckoutHandler::class, 'reload_thank_you_page' ], 10 );
		add_action( 'woocommerce_checkout_order_processed', [ CheckoutHandler::class, 'handle_guest_checkout' ], 10, 3 );

		// Thank you page customization
		add_filter( 'woocommerce_thankyou_order_received_text', [ SubscriptionProcessor::class, 'filter_subscription_thank_you_text' ], 10, 2 );

		// Clear session when cart is emptied
		add_action( 'woocommerce_cart_emptied', [ $this, 'clear_session_on_cart_empty' ] );
	}

	/**
	 * Get subscription type from order
	 *
	 * @param \WC_Order $order Order object
	 * @return string|null
	 */
	public function get_subscription_type_from_order( $order ): ?string {
		return SubscriptionProcessor::get_subscription_type_from_order( $order );
	}

	/**
	 * Auto-complete subscription orders after payment
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function auto_complete_subscription_order( $order_id ): void {
		SubscriptionProcessor::auto_complete_subscription_order( $order_id );
	}

	/**
	 * Store subscription in WC session
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function store_subscription_in_wc_session( $order_id ): void {
		SubscriptionProcessor::store_subscription_in_wc_session( $order_id );
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
		CheckoutHandler::handle_guest_checkout( $order_id, $posted_data, $order );
	}

	/**
	 * Auto-create user from order if needed
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function auto_create_user_from_order( $order_id ): void {
		UserAccountCreator::auto_create_user_from_order( $order_id );
	}

	/**
	 * Filter thank you page text for subscription purchases
	 *
	 * @param string    $text The default thank you text
	 * @param \WC_Order $order The order object
	 * @return string Modified thank you text
	 */
	public function filter_subscription_thank_you_text( $text, $order ): string {
		return SubscriptionProcessor::filter_subscription_thank_you_text( $text, $order );
	}

	/**
	 * Filter payment gateways based on product currency
	 *
	 * @param array $available_gateways Available payment gateways
	 * @return array Filtered payment gateways
	 */
	public function filter_payment_gateways_by_currency( array $available_gateways ): array {
		return PaymentGatewayManager::filter_payment_gateways_by_currency( $available_gateways );
	}

	/**
	 * Auto-complete all orders regardless of product type
	 */
	public function auto_complete_virtual_orders( string $payment_complete_status, int $order_id, $order ): string {
		return OrderStatusManager::auto_complete_virtual_orders( $payment_complete_status, $order_id, $order );
	}

	/**
	 * Force complete order status on thank you page
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function force_complete_order( int $order_id ): void {
		OrderStatusManager::force_complete_order( $order_id );
	}

	/**
	 * Change order status from on-hold to complete
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function change_on_hold_to_complete( int $order_id ): void {
		OrderStatusManager::change_on_hold_to_complete( $order_id );
	}

	/**
	 * Track new order creation
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function track_new_order( int $order_id ): void {
		OrderStatusManager::track_new_order( $order_id );
	}

	/**
	 * Track order status changes
	 *
	 * @param int    $order_id Order ID
	 * @param string $status_from Old status
	 * @param string $status_to New status
	 * @param object $order Order object
	 * @return void
	 */
	public function track_order_status_changed( int $order_id, string $status_from, string $status_to, $order ): void {
		OrderStatusManager::track_order_status_changed( $order_id, $status_from, $status_to, $order );
	}

	/**
	 * Track payment completion
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function track_payment_complete( int $order_id ): void {
		OrderStatusManager::track_payment_complete( $order_id );
	}

	/**
	 * Calculate subscription expiry date (default 1 year from now)
	 *
	 * @return string
	 */
	private function calculate_expiry_date(): string {
		return SubscriptionProcessor::calculate_expiry_date();
	}

	/**
	 * Get allowed resources for subscription type
	 *
	 * @param string $subscription_type Subscription type
	 * @return array
	 */
	private function get_allowed_resources( string $subscription_type ): array {
		return SubscriptionProcessor::get_allowed_resources( $subscription_type );
	}

	/**
	 * Get the subscription processor helper
	 *
	 * @return string The namespace of the SubscriptionProcessor class
	 */
	public function get_subscription_processor() {
		return SubscriptionProcessor::class;
	}

	/**
	 * Get the order status manager helper
	 *
	 * @return string The namespace of the OrderStatusManager class
	 */
	public function get_order_status_manager() {
		return OrderStatusManager::class;
	}

	/**
	 * Get the checkout handler helper
	 *
	 * @return string The namespace of the CheckoutHandler class
	 */
	public function get_checkout_handler() {
		return CheckoutHandler::class;
	}

	/**
	 * Get the payment gateway manager helper
	 *
	 * @return string The namespace of the PaymentGatewayManager class
	 */
	public function get_payment_gateway_manager() {
		return PaymentGatewayManager::class;
	}

	/**
	 * Get the user account creator helper
	 *
	 * @return string The namespace of the UserAccountCreator class
	 */
	public function get_user_account_creator() {
		return UserAccountCreator::class;
	}

	/**
	 * Redirect to checkout page after adding item to cart
	 *
	 * @param string $url Default redirect URL
	 * @return string Checkout URL
	 */
	public function redirect_to_checkout( $url ): string {
		return CheckoutHandler::redirect_to_checkout( $url );
	}

	/**
	 * Customize add to cart message
	 *
	 * @param string $cart_item_key Cart item key
	 * @param int    $product_id Product ID
	 * @param int    $quantity Quantity
	 * @param int    $variation_id Variation ID
	 * @param array  $variation Variation attributes
	 * @param array  $cart_item_data Cart item data
	 * @return void
	 */
	public function add_to_cart_message( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ): void {
		CheckoutHandler::add_to_cart_message( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data );
	}

	/**
	 * Log order status change with backtrace
	 *
	 * @param int    $order_id Order ID
	 * @param string $old_status Old order status
	 * @param string $new_status New order status
	 * @param string $context Context of the status change
	 * @return void
	 */
	public function log_order_status_change( int $order_id, string $old_status = '', string $new_status = '', string $context = '' ): void {
		OrderStatusLogger::log_order_status_change( $order_id, $old_status, $new_status, $context );
	}

	/**
	 * Filter out GamiPress payment gateways for users with subscriptions
	 * or when subscription products are in the cart
	 *
	 * @param array $available_gateways List of available payment gateways
	 * @return array Filtered list of payment gateways
	 */
	public function filter_gamipress_gateways_for_subscription( array $available_gateways ): array {
		return PaymentGatewayManager::filter_gamipress_gateways_for_subscription( $available_gateways );
	}

	/**
	 * Clear session when the cart is emptied
	 */
	public function clear_session_on_cart_empty() {
		return CheckoutHandler::clear_session_on_cart_empty();
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
