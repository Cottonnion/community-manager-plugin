<?php

declare(strict_types=1);

namespace LABGENZ_CM\Subscriptions;

use LABGENZ_CM\Core\UserAccountManager;
use LABGENZ_CM\Core\WooCommerceHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles subscription management and cart validation
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Subscriptions
 */
class SubscriptionHandler {


	/**
	 * Single instance of the class
	 *
	 * @var SubscriptionHandler|null
	 */
	private static $instance = null;

	/**
	 * WooCommerce helper instance
	 *
	 * @var WooCommerceHelper
	 */
	private $wc_helper;

	/**
	 * Subscription product SKUs
	 */
	const SUBSCRIPTION_SKUS = [
		'basic-subscription',
		'organization-subscription',
	];

	/**
	 * Subscription types mapping
	 */
	const SUBSCRIPTION_TYPES = [
		'basic-subscription'        => 'basic',
		'organization-subscription' => 'organization',
	];

	/**
	 * Subscription meta keys
	 */
	const SUBSCRIPTION_TYPE_META      = '_labgenz_subscription_type';
	const SUBSCRIPTION_STATUS_META    = '_labgenz_subscription_status';
	const SUBSCRIPTION_EXPIRES_META   = '_labgenz_subscription_expires';
	const SUBSCRIPTION_RESOURCES_META = '_labgenz_subscription_resources';

	/**
	 * Default subscription duration (1 year)
	 */
	const DEFAULT_EXPIRY_DURATION = '+1 year';

	/**
	 * Subscription resources configuration
	 *
	 * @var array
	 */
	private static $subscription_resources = [
		'basic'        => [
			'course_categories'     => [ 'basic-courses' ],
			'group_creation'        => false,
			'organization_access'   => false,
			'advanced_features'     => false,
			'support_level'         => 'basic',
			'max_groups'            => 0,
			'max_members_per_group' => 0,
			'can_view_mlm_articles' => false,
		],
		'organization' => [
			'course_categories'     => [ 'basic-courses', 'organization-courses', 'advanced-courses' ],
			'group_creation'        => true,
			'organization_access'   => true,
			'advanced_features'     => true,
			'support_level'         => 'premium',
			'max_groups'            => 10,
			'max_members_per_group' => 100,
			'can_view_mlm_articles' => true,
		],
	];

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		$this->wc_helper = WooCommerceHelper::get_instance();
		$this->init_hooks();
	}

	/**
	 * Get singleton instance
	 *
	 * @return SubscriptionHandler
	 */
	public static function get_instance(): SubscriptionHandler {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
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

	/**
	 * Initialize method for loader compatibility (optional)
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Cart validation
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_subscription_cart' ], 10, 3 );

		// Auto-complete subscription orders (runs first)
		add_action( 'woocommerce_payment_complete', [ $this->wc_helper, 'auto_complete_subscription_order' ], 1 );

		// Process subscription after payment
		add_action( 'woocommerce_payment_complete', [ $this, 'process_subscription' ], 5 );
		add_action( 'woocommerce_order_status_completed', [ $this, 'activate_subscription' ] );
		add_action( 'woocommerce_order_status_processing', [ $this, 'activate_subscription' ] );

		// Auto-create user from order if needed
		add_action( 'woocommerce_thankyou', [ $this->wc_helper, 'auto_create_user_from_order' ], 5 );

		// Store subscription in WC session
		add_action( 'woocommerce_thankyou', [ $this->wc_helper, 'store_subscription_in_wc_session' ], 10 );

		// Apply subscription to user after creation
		add_action( 'woocommerce_created_customer', [ $this, 'apply_subscription_to_user' ] );

		// Handle guest checkout completion
		add_action( 'woocommerce_checkout_order_processed', [ $this->wc_helper, 'handle_guest_checkout' ], 10, 3 );

		// Filter thank you page for subscription purchases
		add_filter( 'woocommerce_thankyou_order_received_text', [ $this->wc_helper, 'filter_subscription_thank_you_text' ], 10, 2 );
	}

	/**
	 * Validate subscription products in cart - prevent multiple subscriptions
	 *
	 * @param  bool $passed     Whether validation passed
	 * @param  int  $product_id Product ID being added
	 * @param  int  $quantity   Quantity being added
	 * @return bool
	 */
	public function validate_subscription_cart( $passed, $product_id, $quantity ): bool {
		if ( ! $passed ) {
			return $passed;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return $passed;
		}

		$sku = $product->get_sku();

		// Check if this is a subscription product
		if ( ! in_array( $sku, self::SUBSCRIPTION_SKUS, true ) ) {
			return $passed;
		}

		// Get the subscription type being added
		$new_subscription_type = self::SUBSCRIPTION_TYPES[ $sku ] ?? null;
		if ( ! $new_subscription_type ) {
			return $passed;
		}

		// Check if user already has a subscription
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$existing_subscription = self::get_user_subscription_type( $user_id );

			if ( $existing_subscription ) {
				// Check if it's the same subscription
				if ( $existing_subscription === $new_subscription_type ) {
					wc_add_notice(
						__( 'You already have this subscription active.', 'labgenz-community-management' ),
						'error'
					);
						return false;
				}

				// Check if it's a downgrade
				if ( ! $this->is_subscription_upgrade( $existing_subscription, $new_subscription_type ) ) {
					$existing_name = ucfirst( $existing_subscription );
					$new_name      = ucfirst( $new_subscription_type );

					wc_add_notice(
						sprintf(
							__( 'You cannot downgrade from %1$s to %2$s subscription. Downgrades are not supported.', 'labgenz-community-management' ),
							$existing_name,
							$new_name
						),
						'error'
					);
					return false;
				}
			}
		}

		// Check if cart already has subscription products
		if ( ! WC()->cart->is_empty() ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$cart_product = $cart_item['data'];
				if ( $cart_product && method_exists( $cart_product, 'get_sku' ) ) {
					$cart_sku = $cart_product->get_sku();

					if ( in_array( $cart_sku, self::SUBSCRIPTION_SKUS, true ) ) {
						// If trying to add a different subscription product
						if ( $cart_sku !== $sku ) {
							wc_add_notice(
								__( 'You can only have one subscription product in your cart at a time.', 'labgenz-community-management' ),
								'error'
							);
									return false;
						}

						// Same product already exists - prevent duplicate
						if ( $cart_sku === $sku ) {
							wc_add_notice(
								__( 'This subscription is already in your cart.', 'labgenz-community-management' ),
								'notice'
							);
							return false;
						}
					}
				}
			}
		}

		return $passed;
	}

	/**
	 * Process subscription after payment completion
	 *
	 * @param  int $order_id Order ID
	 * @return void
	 */
	public function process_subscription( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$subscription_type = $this->wc_helper->get_subscription_type_from_order( $order );
		if ( ! $subscription_type ) {
			return;
		}

		$expires   = $this->calculate_expiry_date();
		$resources = $this->get_allowed_resources( $subscription_type );

		// Store subscription metadata in order
		$order->add_meta_data( self::SUBSCRIPTION_TYPE_META, $subscription_type, true );
		$order->add_meta_data( self::SUBSCRIPTION_STATUS_META, 'pending', true );
		$order->add_meta_data( self::SUBSCRIPTION_EXPIRES_META, $expires, true );
		$order->add_meta_data( self::SUBSCRIPTION_RESOURCES_META, $resources, true );
		$order->save();

		// Store in session for user creation
		if ( function_exists( 'WC' ) && WC()->session ) {
			$session_data = [
				'type'      => $subscription_type,
				'status'    => 'pending',
				'expires'   => $expires,
				'resources' => $resources,
				'order_id'  => $order_id,
			];

			WC()->session->set( 'pending_subscription', $session_data );
		}
	}

	/**
	 * Activate subscription when order is completed
	 *
	 * @param  int $order_id Order ID
	 * @return void
	 */
	public function activate_subscription( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$subscription_type = $order->get_meta( self::SUBSCRIPTION_TYPE_META );

		// If no subscription type in meta, try to get it from order items
		if ( ! $subscription_type ) {
			$subscription_type = $this->wc_helper->get_subscription_type_from_order( $order );
		}

		if ( ! $subscription_type ) {
			return;
		}

		// Update order status
		$order->update_meta_data( self::SUBSCRIPTION_STATUS_META, 'active' );

		// If subscription metadata is not in order, store it now
		if ( ! $order->get_meta( self::SUBSCRIPTION_TYPE_META ) ) {
			$expires   = $this->calculate_expiry_date();
			$resources = $this->get_allowed_resources( $subscription_type );

			$order->update_meta_data( self::SUBSCRIPTION_TYPE_META, $subscription_type );
			$order->update_meta_data( self::SUBSCRIPTION_EXPIRES_META, $expires );
			$order->update_meta_data( self::SUBSCRIPTION_RESOURCES_META, $resources );
		}

		$order->save();

		// Apply to user if exists
		$user_id = $order->get_user_id();
		if ( $user_id ) {
			$this->apply_subscription_to_user_by_id( $user_id, $order );
		}
	}

	/**
	 * Apply subscription to newly created user (legacy method for compatibility)
	 *
	 * @param  int $user_id User ID
	 * @return void
	 */
	public function apply_subscription_to_user( $user_id ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$subscription_data = WC()->session->get( 'active_subscription' );
		if ( ! $subscription_data ) {
			return;
		}

		$this->apply_subscription_to_user_by_id( $user_id, null, $subscription_data );

		// Clear session
		WC()->session->set( 'active_subscription', null );
	}

	/**
	 * Apply subscription to user by ID
	 *
	 * @param  int           $user_id           User ID
	 * @param  WC_Order|null $order             Order object (optional)
	 * @param  array|null    $subscription_data Subscription data from session (optional)
	 * @return void
	 */
	public function apply_subscription_to_user_by_id( $user_id, $order = null, $subscription_data = null ): void {
		if ( $order ) {
			// Extract subscription data from order items instead of order meta
			$subscription_type = $this->wc_helper->get_subscription_type_from_order( $order );
			$status            = 'active';
			$expires           = $this->calculate_expiry_date();
			$resources         = $subscription_type ? $this->get_allowed_resources( $subscription_type ) : [];

			// Store subscription data in order meta for future reference
			$order->update_meta_data( self::SUBSCRIPTION_TYPE_META, $subscription_type );
			$order->update_meta_data( self::SUBSCRIPTION_STATUS_META, $status );
			$order->update_meta_data( self::SUBSCRIPTION_EXPIRES_META, $expires );
			$order->update_meta_data( self::SUBSCRIPTION_RESOURCES_META, $resources );
			$order->save();
		} elseif ( $subscription_data ) {
			$subscription_type = $subscription_data['type'];
			$status            = $subscription_data['status'];
			$expires           = $subscription_data['expires'];
			$resources         = $subscription_data['resources'];
		} else {
			return;
		}

		// Ensure we have valid data before storing
		if ( ! $subscription_type ) {
			return;
		}

		// Check if user already has a subscription (upgrade scenario only)
		$existing_subscription = get_user_meta( $user_id, self::SUBSCRIPTION_TYPE_META, true );

		if ( $existing_subscription && $existing_subscription !== $subscription_type ) {
			// This should only be upgrades since downgrades are blocked at cart level
			if ( $this->is_subscription_upgrade( $existing_subscription, $subscription_type ) ) {
				// Store previous subscription for reference
				update_user_meta( $user_id, '_labgenz_previous_subscription_type', $existing_subscription );
				update_user_meta( $user_id, '_labgenz_subscription_upgrade_date', current_time( 'mysql' ) );
			}
		}

		// Store subscription in user meta (this will overwrite existing subscription)
		update_user_meta( $user_id, self::SUBSCRIPTION_TYPE_META, $subscription_type );
		update_user_meta( $user_id, self::SUBSCRIPTION_STATUS_META, $status );
		update_user_meta( $user_id, self::SUBSCRIPTION_EXPIRES_META, $expires );
		update_user_meta( $user_id, self::SUBSCRIPTION_RESOURCES_META, $resources );
	}

	/**
	 * Get allowed resources for subscription type
	 *
	 * @param  string $subscription_type Subscription type
	 * @return array
	 */
	public function get_allowed_resources( string $subscription_type ): array {
		return self::$subscription_resources[ $subscription_type ] ?? [];
	}

	/**
	 * Calculate subscription expiry date (default 1 year from now)
	 *
	 * @return string
	 */
	private function calculate_expiry_date(): string {
		return date( 'Y-m-d H:i:s', strtotime( self::DEFAULT_EXPIRY_DURATION ) );
	}

	/**
	 * Check if user has active subscription
	 *
	 * @param  int $user_id User ID
	 * @return bool
	 */
	public static function user_has_active_subscription( $user_id ): bool {
		$status  = get_user_meta( $user_id, self::SUBSCRIPTION_STATUS_META, true );
		$expires = get_user_meta( $user_id, self::SUBSCRIPTION_EXPIRES_META, true );

		return $status === 'active' && $expires && strtotime( $expires ) > time();
	}

	/**
	 * Get user subscription type
	 *
	 * @param  int $user_id User ID
	 * @return string|null
	 */
	public static function get_user_subscription_type( $user_id ): ?string {
		if ( ! self::user_has_active_subscription( $user_id ) ) {
			return null;
		}

		return get_user_meta( $user_id, self::SUBSCRIPTION_TYPE_META, true ) ?: null;
	}

	/**
	 * Get user subscription resources
	 *
	 * @param  int $user_id User ID
	 * @return array
	 */
	public static function get_user_subscription_resources( $user_id ): array {
		if ( ! self::user_has_active_subscription( $user_id ) ) {
			return [];
		}

		return get_user_meta( $user_id, self::SUBSCRIPTION_RESOURCES_META, true ) ?: [];
	}

	/**
	 * Check if user has organization subscription
	 *
	 * @param  int $user_id User ID
	 * @return bool
	 */
	public static function user_has_organization_subscription( $user_id ): bool {
		$subscription_type = self::get_user_subscription_type( $user_id );
		return $subscription_type === 'organization';
	}

	/**
	 * Check if user can create groups
	 *
	 * @param  int $user_id User ID
	 * @return bool
	 */
	public static function user_can_create_groups( $user_id ): bool {
		$resources = self::get_user_subscription_resources( $user_id );
		return $resources['group_creation'] ?? false;
	}

	/**
	 * Get user's allowed course categories
	 *
	 * @param  int $user_id User ID
	 * @return array
	 */
	public static function get_user_allowed_course_categories( $user_id ): array {
		$resources = self::get_user_subscription_resources( $user_id );
		return $resources['course_categories'] ?? [];
	}

	/**
	 * Check if user has access to specific resource
	 *
	 * @param  int    $user_id      User ID
	 * @param  string $resource_key Resource key to check (e.g., 'can_view_mlm_articles', 'group_creation')
	 * @return bool
	 */
	public static function user_has_resource_access( $user_id, string $resource_key ): bool {
		$resources = self::get_user_subscription_resources( $user_id );
		return $resources[ $resource_key ] ?? false;
	}

	/**
	 * Check if new subscription is an upgrade from existing subscription
	 *
	 * @param  string $existing_subscription Current subscription type
	 * @param  string $new_subscription      New subscription type
	 * @return bool
	 */
	private function is_subscription_upgrade( string $existing_subscription, string $new_subscription ): bool {
		// Define subscription hierarchy (higher number = better subscription).
		$subscription_hierarchy = [
			'basic'        => 1,
			'organization' => 2,
		];

		$existing_level = $subscription_hierarchy[ $existing_subscription ] ?? 0;
		$new_level      = $subscription_hierarchy[ $new_subscription ] ?? 0;

		return $new_level > $existing_level;
	}
}
