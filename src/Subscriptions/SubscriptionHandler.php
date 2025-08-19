<?php

declare(strict_types=1);

namespace LABGENZ_CM\Subscriptions;

use LABGENZ_CM\Core\UserAccountManager;
use LABGENZ_CM\Core\WooCommerceHelper;
use LABGENZ_CM\Subscriptions\Helpers\SubscriptionValidator;
use LABGENZ_CM\Subscriptions\Helpers\SubscriptionStorage;
use LABGENZ_CM\Subscriptions\Helpers\SubscriptionResources;

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
		'monthly-basic-subscription',
		'mlm-apprentice-yearly',
		'mlm-apprentice-monthly',
		'mlm-team-leader-monthly',
		'mlm-team-leader-yearly',
		'mlm-freedom-builder-monthly',
		'mlm-freedom-builder-yearly',
	];

	/**
	 * Subscription types mapping with optional durations
	 */
	const SUBSCRIPTION_TYPES = [
		'basic-subscription'          => [
			'type'     => 'basic',
			'duration' => '+1 year',
		],
		'monthly-basic-subscription'  => [
			'type'     => 'monthly-basic-subscription',
			'duration' => '+1 month',
		],
		'mlm-apprentice-yearly'       => [
			'type'     => 'apprentice-yearly',
			'duration' => '+1 year',
			'group_id' => [134],
		],
		'mlm-apprentice-monthly'      => [
			'type'     => 'apprentice-monthly',
			'duration' => '+1 month',
			'group_id' => [134],
		],
		'mlm-team-leader-yearly'      => [
			'type'     => 'team-leader-yearly',
			'duration' => '+1 year',
			'group_id' => [134, 135],
		],
		'mlm-team-leader-monthly'     => [
			'type'     => 'team-leader-monthly',
			'duration' => '+1 month',
			'group_id' => [134, 135],
		],
		'mlm-freedom-builder-yearly'  => [
			'type'     => 'freedom-builder-yearly',
			'duration' => '+1 year',
			'group_id' => [134, 135, 136],
		],
		'mlm-freedom-builder-monthly' => [
			'type'     => 'freedom-builder-monthly',
			'duration' => '+1 month',
			'group_id' => [134, 135, 136],
		],
	];

	/**
	 * Subscription meta keys
	 */
	const SUBSCRIPTION_TYPE_META      = '_labgenz_subscription_type';
	const SUBSCRIPTION_STATUS_META    = '_labgenz_subscription_status';
	const SUBSCRIPTION_EXPIRES_META   = '_labgenz_subscription_expires';
	const SUBSCRIPTION_RESOURCES_META = '_labgenz_subscription_resources';
	const SUBSCRIPTIONS_META          = '_labgenz_subscriptions';

	/**
	 * Default subscription duration (1 year)
	 */
	const DEFAULT_EXPIRY_DURATION = '+1 year';

	/**
	 * Log a debug message to the WordPress debug log
	 *
	 * @param string $message The message to log
	 * @return void
	 */
	public function log_debug( string $message ): void {
		// return; // Disable logging for nows
		error_log( 'started' );
		// Prepare log message with timestamp
		$timestamp   = date( '[Y-m-d H:i:s]' );
		$log_message = $timestamp . ' ' . $message;

		// Log to WordPress debug.log
		error_log( $log_message );
	}

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
		// Cart validation - use priority 35 to run AFTER cart clearing hook (which is 20)
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_subscription_cart' ], 35, 3 );
		add_action( 'wp', [ $this, 'validate_subscription_checkout' ], 10, 2 );
		add_action( 'woocommerce_checkout_process', [ $this, 'validate_subscription_checkout' ], 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'validate_subscription_checkout' ], 10, 2 );
		// Additional hooks to ensure notices display properly
		add_action( 'woocommerce_before_single_product_summary', [ $this, 'display_stored_validation_notices' ], 5 );
		add_action( 'woocommerce_before_shop_loop', [ $this, 'display_stored_validation_notices' ], 5 );

		// For AJAX add to cart validation (alternative approach)
		add_action( 'wp_ajax_woocommerce_add_to_cart', [ $this, 'ajax_add_to_cart_validation' ], 5 );
		add_action( 'wp_ajax_nopriv_woocommerce_add_to_cart', [ $this, 'ajax_add_to_cart_validation' ], 5 );

		// Ensure notices are preserved during redirects
		// add_action( 'woocommerce_add_to_cart_redirect', [ $this, 'preserve_notices_on_redirect' ] );

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

		// Cron job for subscription expiry checks
		add_action( 'labgenz_check_subscription_expiry', [ __CLASS__, 'check_subscription_expiry' ] );

		// Cron job for updating accessible articles
		add_action( 'labgenz_update_accessible_articles', [ __CLASS__, 'update_accessible_articles' ] );

		register_activation_hook( __FILE__, [ __CLASS__, 'schedule_cron_jobs' ] );
		register_deactivation_hook( __FILE__, [ __CLASS__, 'unschedule_cron_jobs' ] );
	}

	/**
	 * Validate subscription products in cart - allow multiple unrelated subscriptions
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
		$subscription_data = self::SUBSCRIPTION_TYPES[ $sku ] ?? null;
		if ( ! $subscription_data ) {
			return $passed;
		}
		$new_subscription_type = $subscription_data['type'];

		// Check if user already has a subscription
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$user_subscriptions = SubscriptionStorage::get_active_user_subscriptions( $user_id );

			if ( ! empty( $user_subscriptions ) ) {
				foreach ( $user_subscriptions as $subscription ) {
					$existing_subscription_type = $subscription['type'];

					// Check if it's the same subscription (exact match)
					if ( $existing_subscription_type === $new_subscription_type ) {
						wc_add_notice(
							sprintf(
								__( 'You already have a %s subscription active.', 'labgenz-community-management' ),
								ucfirst( str_replace( '-', ' ', $existing_subscription_type ) )
							),
							'error'
						);
						WC()->cart->empty_cart();
						WC()->session->set( 'cart', array() );
						return false;
					}

					// Check if subscriptions are related (same family)
					if ( SubscriptionValidator::are_subscriptions_related( $existing_subscription_type, $new_subscription_type ) ) {
						// Check if it's a downgrade within the same family
						if ( SubscriptionValidator::is_subscription_downgrade( $existing_subscription_type, $new_subscription_type ) ) {
							wc_add_notice(
								sprintf(
									__( 'You cannot downgrade from %1$s to %2$s. Downgrades within the same subscription family are not supported.', 'labgenz-community-management' ),
									ucfirst( str_replace( '-', ' ', $existing_subscription_type ) ),
									ucfirst( str_replace( '-', ' ', $new_subscription_type ) )
								),
								'error'
							);
							WC()->cart->empty_cart();
							WC()->session->set( 'cart', array() );
							return false;
						}

						// Check if it's a monthly to yearly upgrade
						if ( SubscriptionValidator::is_monthly_to_yearly_upgrade( $existing_subscription_type, $new_subscription_type ) ) {
							$remaining_days = SubscriptionStorage::calculate_remaining_days( $subscription );
							if ( $remaining_days > 0 ) {
								wc_add_notice(
									sprintf(
										__( 'You currently have %d days remaining on your monthly subscription. After purchase, these days will be added to your new yearly subscription.', 'labgenz-community-management' ),
										$remaining_days
									),
									'notice'
								);
							}
							// Allow the upgrade and return true
							return true;
						}

						// If we get here, subscriptions are related but not compatible
						wc_add_notice(
							sprintf(
								__( 'You cannot have both %1$s and %2$s subscriptions. Please contact support if you need assistance.', 'labgenz-community-management' ),
								ucfirst( str_replace( '-', ' ', $existing_subscription_type ) ),
								ucfirst( str_replace( '-', ' ', $new_subscription_type ) )
							),
							'error'
						);
						WC()->cart->empty_cart();
						WC()->session->set( 'cart', array() );
						return false;
					}
				}
			}
		}

		// Check if cart already has this exact subscription product (prevent duplicates)
		if ( ! WC()->cart->is_empty() ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$cart_product = $cart_item['data'];
				if ( $cart_product && method_exists( $cart_product, 'get_sku' ) ) {
					$cart_sku = $cart_product->get_sku();

					// Same product already exists - prevent duplicate
					if ( $cart_sku === $sku ) {
						wc_add_notice(
							__( 'This subscription is already in your cart.', 'labgenz-community-management' ),
							'notice'
						);
						return false;
					}

					// Check if cart already has a related subscription (from same family)
					if ( in_array( $cart_sku, self::SUBSCRIPTION_SKUS, true ) ) {
						$cart_subscription_data = self::SUBSCRIPTION_TYPES[ $cart_sku ] ?? null;
						if ( $cart_subscription_data ) {
							$cart_subscription_type = $cart_subscription_data['type'];

							if ( SubscriptionValidator::are_subscriptions_related( $cart_subscription_type, $new_subscription_type ) ) {
								wc_add_notice(
									__( 'You cannot purchase related subscription types in the same order. Please complete your current order first.', 'labgenz-community-management' ),
									'error'
								);
								WC()->cart->empty_cart();
								WC()->session->set( 'cart', array() );
								return false;
							}
						}
					}
				}
			}
		}

		return $passed;
	}

/**
 * Validate subscription products during checkout initialization
 *
 * @param WC_Checkout $checkout Checkout object
 * @return void
 */
public function validate_subscription_checkout( $checkout ): void {
	if(is_checkout()) {
		error_log( 'pppp -Checkout validation started' );
	
	// if ( ! WC()->cart || WC()->cart->is_empty() ) {
	// 	error_log( 'Cart is empty, skipping validation' );
	// 	return;
	// }

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		error_log( 'pppp Guest checkout detected, skipping validation' );
		return; // Skip validation for guest checkout
	}

	error_log( "pppp Validating checkout for user ID: $user_id" );
	$user_subscriptions = SubscriptionStorage::get_active_user_subscriptions( $user_id );
	error_log( 'pppp User has ' . count( $user_subscriptions ) . ' active subscriptions' );

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product = $cart_item['data'];
		if ( ! $product || ! method_exists( $product, 'get_sku' ) ) {
			continue;
		}

		$sku = $product->get_sku();
		error_log( "pppp Checking product SKU: $sku" );

		// Check if this is a subscription product
		if ( ! in_array( $sku, self::SUBSCRIPTION_SKUS, true ) ) {
			error_log( " pppp SKU $sku is not a subscription product, skipping" );
			continue;
		}

		error_log( "Processing subscription product: $sku" );

		// Get the subscription type being added
		$subscription_data = self::SUBSCRIPTION_TYPES[ $sku ] ?? null;
		if ( ! $subscription_data ) {
			error_log( "No subscription data found for SKU: $sku" );
			continue;
		}
		$new_subscription_type = $subscription_data['type'];
		error_log( "New subscription type: $new_subscription_type" );

		if ( ! empty( $user_subscriptions ) ) {
			error_log( "Checking against existing subscriptions" );
			foreach ( $user_subscriptions as $subscription ) {
				$existing_subscription_type = $subscription['type'];
				error_log( "Comparing new type '$new_subscription_type' with existing type '$existing_subscription_type'" );

				// Check if it's the same subscription (exact match)
				if ( $existing_subscription_type === $new_subscription_type ) {
					error_log( "VALIDATION FAILED: Duplicate subscription detected" );
					wc_add_notice( sprintf(
						__( 'You already have a %s subscription active.', 'labgenz-community-management' ),
						ucfirst( str_replace( '-', ' ', $existing_subscription_type ) )
					), 'error' );
					return;
				}

				// Check if subscriptions are related (same family)
				if ( SubscriptionValidator::are_subscriptions_related( $existing_subscription_type, $new_subscription_type ) ) {
					error_log( "Subscriptions are related, checking compatibility" );
					// Check if it's a downgrade within the same family
					if ( SubscriptionValidator::is_subscription_downgrade( $existing_subscription_type, $new_subscription_type ) ) {
						error_log( "VALIDATION FAILED: Downgrade detected" );
						wc_add_notice( sprintf(
							__( 'You cannot downgrade from %1$s to %2$s. Downgrades within the same subscription family are not supported.', 'labgenz-community-management' ),
							ucfirst( str_replace( '-', ' ', $existing_subscription_type ) ),
							ucfirst( str_replace( '-', ' ', $new_subscription_type ) )
						), 'error' );
						return;
					}

					// Skip monthly to yearly upgrade validation - allow it to proceed
					if ( SubscriptionValidator::is_monthly_to_yearly_upgrade( $existing_subscription_type, $new_subscription_type ) ) {
						error_log( "Monthly to yearly upgrade detected, allowing" );
							$remaining_days = SubscriptionStorage::calculate_remaining_days( $subscription );
							if ( $remaining_days > 0 ) {
								wc_add_notice(
									sprintf(
										__( 'You currently have %d days remaining on your monthly subscription. After purchase, these days will be added to your new yearly subscription.', 'labgenz-community-management' ),
										$remaining_days
									),
									'success'
								);
							}
							// Allow the upgrade and return true
							// return true;
						continue;
					}

					// If we get here, subscriptions are related but not compatible
					error_log( "VALIDATION FAILED: Incompatible related subscriptions" );
					wc_add_notice( sprintf(
						__( 'You cannot have both %1$s and %2$s subscriptions. Please contact support if you need assistance.', 'labgenz-community-management' ),
						ucfirst( str_replace( '-', ' ', $existing_subscription_type ) ),
						ucfirst( str_replace( '-', ' ', $new_subscription_type ) )
					), 'error' );
					return;
				} else {
					error_log( "Subscriptions are not related, allowing" );
				}
			}
		}
	}
	
	error_log( 'Checkout validation completed successfully' );
	}
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

		$expires   = $this->calculate_expiry_date( $subscription_type );
		$resources = SubscriptionResources::get_allowed_resources( $subscription_type );

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
			$expires   = $this->calculate_expiry_date( $subscription_type );
			$resources = SubscriptionResources::get_allowed_resources( $subscription_type );

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
	 * @param  int            $user_id           User ID
	 * @param  \WC_Order|null $order             Order object (optional)
	 * @param  array|null     $subscription_data Subscription data from session (optional)
	 * @return void
	 */
	public function apply_subscription_to_user_by_id( $user_id, $order = null, $subscription_data = null ): void {
		if ( $order ) {
			// Extract subscription data from order items instead of order meta
			$subscription_type = $this->wc_helper->get_subscription_type_from_order( $order );
			$status            = 'active';
			$expires           = $this->calculate_expiry_date( $subscription_type );
			$resources         = $subscription_type ? SubscriptionResources::get_allowed_resources( $subscription_type ) : [];

			// Store subscription data in order meta for future reference
			$order->update_meta_data( self::SUBSCRIPTION_TYPE_META, $subscription_type );
			$order->update_meta_data( self::SUBSCRIPTION_STATUS_META, $status );
			$order->update_meta_data( self::SUBSCRIPTION_EXPIRES_META, $expires );
			$order->update_meta_data( self::SUBSCRIPTION_RESOURCES_META, $resources );
			$order->save();

			$amount         = $order->get_total();
			$payment_method = $order->get_payment_method_title();
		} elseif ( $subscription_data ) {
			$subscription_type = $subscription_data['type'];
			$status            = $subscription_data['status'];
			$expires           = $subscription_data['expires'];
			$resources         = $subscription_data['resources'];
			$amount            = $subscription_data['amount'] ?? '';
			$payment_method    = $subscription_data['payment_method'] ?? '';
		} else {
			return;
		}

		// Ensure we have valid data before storing
		if ( ! $subscription_type ) {
			return;
		}

		// Check if user already has this type of subscription (prevent duplicates)
		$existing_subscription = SubscriptionStorage::get_subscription_by_type( $user_id, $subscription_type, true );
		if ( $existing_subscription ) {
			// User already has this subscription active - don't create another one
			return;
		}

		// Generate a unique subscription ID
		$subscription_id = 'sub_' . uniqid();

		// Check for monthly to yearly upgrade
		$active_subscriptions    = SubscriptionStorage::get_active_user_subscriptions( $user_id );
		$upgraded_from_monthly   = false;
		$monthly_subscription_id = null;

		foreach ( $active_subscriptions as $active_sub ) {
			if ( isset( $active_sub['type'] ) && SubscriptionValidator::is_monthly_to_yearly_upgrade( $active_sub['type'], $subscription_type ) ) {
				$upgraded_from_monthly   = true;
				$monthly_subscription_id = $active_sub['id'] ?? null;
				break;
			}
		}

		// Create the new subscription
		$new_subscription = [
			'id'             => $subscription_id,
			'type'           => $subscription_type,
			'status'         => $status,
			'expires'        => $expires,
			'resources'      => $resources,
			'created'        => current_time( 'mysql' ),
			'updated'        => current_time( 'mysql' ),
			'amount'         => $amount,
			'payment_method' => $payment_method,
		];

		// Save the subscription start date for basic subscriptions for article access
		$subscription_start_date = current_time( 'mysql' );
		update_user_meta( $user_id, '_subscription_start_date', $subscription_start_date );

		// Save accessible article IDs since subscription start for basic subscription
		if ( strpos( $subscription_type, 'basic' ) !== false ) {
			$this->store_accessible_articles( $user_id, $subscription_start_date );
		}

		// Save the new subscription
		SubscriptionStorage::save_subscription( $user_id, $new_subscription );

		// Add user to BuddyBoss group(s) if applicable
		$group_ids = [];
		foreach ( self::SUBSCRIPTION_TYPES as $sku => $data ) {
			if ( $data['type'] === $subscription_type && isset( $data['group_id'] ) ) {
				// Handle both single group_id and array of group_ids
				if ( is_array( $data['group_id'] ) ) {
					$group_ids = array_merge( $group_ids, $data['group_id'] );
				} else {
					$group_ids[] = $data['group_id'];
				}
				break;
			}
		}

		if ( ! empty( $group_ids ) && function_exists( 'groups_join_group' ) ) {
			foreach ( $group_ids as $group_id ) {
				groups_join_group( $group_id, $user_id );
			}
		}

		// If upgrading from monthly to yearly, add remaining days and deactivate monthly subscription
		if ( $upgraded_from_monthly && $monthly_subscription_id ) {
			SubscriptionStorage::add_remaining_days( $user_id, $monthly_subscription_id, $subscription_id );
		}
	}

	/**
	 * Calculate subscription expiry date dynamically based on type
	 *
	 * @param string $subscription_type Subscription type
	 * @return string
	 */
	private function calculate_expiry_date( string $subscription_type ): string {
		// Find the SKU that maps to this subscription type
		$duration = self::DEFAULT_EXPIRY_DURATION;

		foreach ( self::SUBSCRIPTION_TYPES as $sku => $data ) {
			if ( $data['type'] === $subscription_type ) {
				$duration = $data['duration'];
				break;
			}
		}

		return date( 'Y-m-d H:i:s', strtotime( $duration ) );
	}

	/**
	 * Check if user has active subscription
	 *
	 * @param  int $user_id User ID
	 * @return bool
	 */
	public static function user_has_active_subscription( $user_id ): bool {
		return SubscriptionStorage::user_has_active_subscription( $user_id );
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

		$active_subscriptions = SubscriptionStorage::get_active_user_subscriptions( $user_id );
		if ( empty( $active_subscriptions ) ) {
			return null;
		}

		// Get the primary subscription based on hierarchy
		$primary = SubscriptionValidator::get_primary_subscription( $active_subscriptions );
		return $primary ? $primary['type'] : null;
	}

	/**
	 * Get all user subscription types
	 *
	 * @param  int $user_id User ID
	 * @return array
	 */
	public static function get_user_subscription_types( $user_id ): array {
		return SubscriptionStorage::get_user_subscription_types( $user_id );
	}

	/**
	 * Get user subscription resources
	 *
	 * @param  int $user_id User ID
	 * @return array
	 */
	public static function get_user_subscription_resources( $user_id ): array {
		return SubscriptionResources::get_user_subscription_resources( $user_id );
	}

	/**
	 * Check if user has organization subscription
	 *
	 * @param  int $user_id User ID
	 * @return bool
	 */
	public static function user_has_organization_subscription( $user_id ): bool {
		return SubscriptionResources::user_has_organization_subscription( $user_id );
	}

	/**
	 * Check if user can create groups
	 *
	 * @param  int $user_id User ID
	 * @return bool
	 */
	public static function user_can_create_groups( $user_id ): bool {
		return SubscriptionResources::user_can_create_groups( $user_id );
	}

	/**
	 * Get user's allowed course categories
	 *
	 * @param  int $user_id User ID
	 * @return array
	 */
	public static function get_user_allowed_course_categories( $user_id ): array {
		return SubscriptionResources::get_user_allowed_course_categories( $user_id );
	}

	/**
	 * Check if user has access to specific resource
	 *
	 * @param  int    $user_id      User ID
	 * @param  string $resource_key Resource key to check (e.g., 'can_view_mlm_articles', 'group_creation')
	 * @return bool
	 */
	public static function user_has_resource_access( $user_id, string $resource_key ): bool {
		return SubscriptionResources::user_has_resource_access( $user_id, $resource_key );
	}

	/**
	 * Get the article upsell URL.
	 *
	 * @return string The URL of the upsell page.
	 */
	public static function get_article_upsell_url(): string {
		return SubscriptionResources::get_article_upsell_url();
	}

	/**
	 * Schedule daily cron job for subscription expiry checks
	 */
	public static function schedule_cron_jobs(): void {
		if ( ! wp_next_scheduled( 'labgenz_check_subscription_expiry' ) ) {
			wp_schedule_event( time(), 'daily', 'labgenz_check_subscription_expiry' );
		}

		if ( ! wp_next_scheduled( 'labgenz_update_accessible_articles' ) ) {
			wp_schedule_event( time(), 'daily', 'labgenz_update_accessible_articles' );
		}
	}

	/**
	 * Unschedule cron job on plugin deactivation
	 */
	public static function unschedule_cron_jobs(): void {
		$timestamp = wp_next_scheduled( 'labgenz_check_subscription_expiry' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'labgenz_check_subscription_expiry' );
		}

		$timestamp = wp_next_scheduled( 'labgenz_update_accessible_articles' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'labgenz_update_accessible_articles' );
		}
	}

	/**
	 * Check and deactivate expired subscriptions
	 */
	public static function check_subscription_expiry(): void {
		$users = get_users();

		foreach ( $users as $user ) {
			$user_id               = $user->ID;
			$subscriptions         = SubscriptionStorage::get_user_subscriptions( $user_id );
			$subscriptions_updated = false;

			if ( ! empty( $subscriptions ) ) {
				foreach ( $subscriptions as &$subscription ) {
					if ( isset( $subscription['expires'] ) &&
						strtotime( $subscription['expires'] ) <= time() &&
						isset( $subscription['status'] ) &&
						$subscription['status'] === 'active' ) {

						$subscription['status'] = 'inactive';
						$subscriptions_updated  = true;
					}
				}

				if ( $subscriptions_updated ) {
					update_user_meta( $user_id, SubscriptionStorage::SUBSCRIPTIONS_META, $subscriptions );
				}
			}
		}
	}

	/**
	 * Store accessible article IDs for a user since their subscription start date
	 *
	 * @param int    $user_id User ID
	 * @param string $start_date Subscription start date
	 * @return void
	 */
	private function store_accessible_articles( int $user_id, string $start_date ): void {
		// Get all articles published since the subscription start date
		$args = [
			'post_type'      => 'mlmmc_artiicle',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'date_query'     => [
				[
					'after'     => $start_date,
					'inclusive' => true,
				],
			],
			'fields'         => 'ids', // Only get post IDs
		];

		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			$article_ids = $query->posts;
			update_user_meta( $user_id, '_accessible_article_ids', $article_ids );
			$this->log_debug( 'Stored ' . count( $article_ids ) . " accessible article IDs for user $user_id" );
		}
	}

	/**
	 * Update accessible articles for all users with basic subscriptions
	 * Should be called via cron daily to add new articles
	 *
	 * @return void
	 */
	public static function update_accessible_articles(): void {
		$instance = self::get_instance();
		$users    = get_users();

		foreach ( $users as $user ) {
			$user_id            = $user->ID;
			$subscription_types = self::get_user_subscription_types( $user_id );

			// Check if user has a basic subscription
			$has_basic = false;
			foreach ( $subscription_types as $type ) {
				if ( strpos( $type, 'basic' ) !== false ) {
					$has_basic = true;
					break;
				}
			}

			if ( $has_basic ) {
				// Get subscription start date
				$start_date = get_user_meta( $user_id, '_subscription_start_date', true );
				if ( $start_date ) {
					$instance->store_accessible_articles( $user_id, $start_date );
				}
			}
		}
	}
}
