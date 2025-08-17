<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles user account creation, login, and email operations
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Core
 */
class UserAccountManager {

	/**
	 * Generate unique username from email and names
	 *
	 * @param string $email Email address
	 * @param string $first_name First name
	 * @param string $last_name Last name
	 * @return string
	 */
	public static function generate_username( string $email, string $first_name, string $last_name ): string {
		// Try different username formats
		$username_options = [
			strtolower( $first_name . $last_name ),
			strtolower( $first_name . '.' . $last_name ),
			strtolower( $first_name . '_' . $last_name ),
			explode( '@', $email )[0],
			strtolower( $first_name ) . rand( 100, 999 ),
		];

		foreach ( $username_options as $username ) {
			$username = sanitize_user( $username );
			if ( ! username_exists( $username ) && $username ) {
				return $username;
			}
		}

		// Fallback: use email prefix with random number
		$base_username = explode( '@', $email )[0];
		$base_username = sanitize_user( $base_username );

		do {
			$username = $base_username . rand( 1000, 9999 );
		} while ( username_exists( $username ) );

		return $username;
	}


	/**
	 * Create new user account with WooCommerce billing data
	 *
	 * @param string $email Email address
	 * @param string $first_name First name
	 * @param string $last_name Last name
	 * @param array  $billing_data Billing information array
	 * @param string $role User role (default: customer)
	 * @return int|\WP_Error User ID on success, WP_Error on failure
	 */
	public static function create_user( string $email, string $first_name, string $last_name, array $billing_data = [], string $role = 'customer' ) {
		// Check if user already exists
		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user ) {
			// Update billing data if user exists
			if ( ! empty( $billing_data ) ) {
				self::update_billing_data( $existing_user->ID, $billing_data );
			}
			return $existing_user->ID;
		}

		// Generate username and password
		$username = self::generate_username( $email, $first_name, $last_name );
		$password = wp_generate_password( 12, false );

		$user_data = [
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => $password,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( $first_name . ' ' . $last_name ),
			'role'         => $role,
		];

		$user_id = wp_insert_user( $user_data );

		if ( ! is_wp_error( $user_id ) ) {
			// Store password in user meta for welcome email
			update_user_meta( $user_id, '_labgenz_temp_password', $password );

			// Set WooCommerce billing data
			if ( ! empty( $billing_data ) ) {
				self::update_billing_data( $user_id, $billing_data );
			}

			// Set default WooCommerce customer data
			// self::set_default_customer_data( $user_id );
		}

		return $user_id;
	}

	/**
	 * Update user billing data with all WooCommerce meta fields
	 *
	 * @param int   $user_id User ID
	 * @param array $billing_data Billing information array
	 * @return void
	 */
	private static function update_billing_data( int $user_id, array $billing_data ) {
		// Get user email as fallback
		$user_email = get_user_by( 'ID', $user_id )->user_email ?? '';

		// Define all standard WooCommerce billing fields with defaults
		$billing_fields = [
			'billing_first_name' => $billing_data['first_name'] ?? '',
			'billing_last_name'  => $billing_data['last_name'] ?? '',
			'billing_company'    => $billing_data['company'] ?? '',
			'billing_address_1'  => $billing_data['address_1'] ?? '',
			'billing_address_2'  => $billing_data['address_2'] ?? '',
			'billing_city'       => $billing_data['city'] ?? '',
			'billing_postcode'   => $billing_data['postcode'] ?? '',
			'billing_country'    => $billing_data['country'] ?? '',
			'billing_state'      => $billing_data['state'] ?? '',
			'billing_phone'      => $billing_data['phone'] ?? '',
			'billing_email'      => $billing_data['email'] ?? $user_email,
		];

		// Define all standard WooCommerce shipping fields with defaults
		$shipping_fields = [
			'shipping_first_name' => $billing_data['shipping_first_name'] ?? $billing_data['first_name'] ?? '',
			'shipping_last_name'  => $billing_data['shipping_last_name'] ?? $billing_data['last_name'] ?? '',
			'shipping_company'    => $billing_data['shipping_company'] ?? $billing_data['company'] ?? '',
			'shipping_address_1'  => $billing_data['shipping_address_1'] ?? $billing_data['address_1'] ?? '',
			'shipping_address_2'  => $billing_data['shipping_address_2'] ?? $billing_data['address_2'] ?? '',
			'shipping_city'       => $billing_data['shipping_city'] ?? $billing_data['city'] ?? '',
			'shipping_postcode'   => $billing_data['shipping_postcode'] ?? $billing_data['postcode'] ?? '',
			'shipping_country'    => $billing_data['shipping_country'] ?? $billing_data['country'] ?? '',
			'shipping_state'      => $billing_data['shipping_state'] ?? $billing_data['state'] ?? '',
		];

		// Additional WooCommerce customer meta fields
		$additional_wc_fields = [
			// Customer preferences
			'_woocommerce_persistent_cart'             => '',
			'wc_last_active'                           => time(),
			'paying_customer'                          => 0,
			'_money_spent_excluding_taxes'             => 0,

			// Customer session data
			'_woocommerce_load_saved_cart_after_login' => 0,

			// Marketing preferences (if you use these)
			'marketing_emails_consent'                 => '',
			'newsletter_subscribe'                     => '',

			// Customer notes/preferences
			'customer_notes'                           => '',
			'preferred_payment_method'                 => '',

			// Account creation source tracking
			'account_created_via'                      => 'labgenz_system',
			'account_created_date'                     => current_time( 'mysql' ),
		];

		// Update all billing fields (including empty ones for consistency)
		foreach ( $billing_fields as $key => $value ) {
			update_user_meta( $user_id, $key, sanitize_text_field( $value ) );
		}

		// Update all shipping fields (including empty ones for consistency)
		foreach ( $shipping_fields as $key => $value ) {
			update_user_meta( $user_id, $key, sanitize_text_field( $value ) );
		}

		// Update additional WooCommerce fields
		foreach ( $additional_wc_fields as $key => $value ) {
			if ( is_numeric( $value ) ) {
				update_user_meta( $user_id, $key, $value );
			} else {
				update_user_meta( $user_id, $key, sanitize_text_field( $value ) );
			}
		}

		// Set default country if none provided (useful for tax calculations)
		if ( empty( $billing_fields['billing_country'] ) ) {
			$default_country = get_option( 'woocommerce_default_country', 'US' );
			// Extract country code if it includes state (e.g., "US:CA" -> "US")
			$country_code = strpos( $default_country, ':' ) !== false ?
				explode( ':', $default_country )[0] : $default_country;

			update_user_meta( $user_id, 'billing_country', $country_code );
			update_user_meta( $user_id, 'shipping_country', $country_code );
		}

		// Initialize WooCommerce customer object if available
		if ( function_exists( 'WC' ) && WC()->customer ) {
			// Clear any existing customer data cache
			wp_cache_delete( $user_id, 'user_meta' );

			// If this is the current user, update WC customer object
			if ( get_current_user_id() === $user_id ) {
				WC()->customer->read( $user_id );
			}
		}
	}

	/**
	 * Auto-login user after account creation
	 *
	 * @param int $user_id User ID
	 * @return void
	 */
	public static function auto_login_user( int $user_id ): void {
		// Set authentication cookies
		wp_set_auth_cookie( $user_id, true );
		wp_set_current_user( $user_id );

		// Update WooCommerce session if available
		if ( function_exists( 'WC' ) && WC()->session ) {
			// Method 1: Initialize customer session properly
			WC()->session->set_customer_session_cookie( true );

			// Method 2: Alternative approach using customer object
			if ( WC()->customer ) {
				WC()->customer->set_id( $user_id );
			}

			// Method 3: Reinitialize the session
			WC()->initialize_session();
		}

		// Optional: Clear any existing cart/session data
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}
	}

	/**
	 * Send welcome email to new user
	 *
	 * @param int    $user_id User ID
	 * @param string $context Context for the email (e.g., 'subscription', 'registration')
	 * @param array  $extra_data Additional data for email customization
	 * @return bool Whether email was sent successfully
	 */
	public static function send_welcome_email( int $user_id, string $context = 'registration', array $extra_data = [] ): bool {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return false;
		}

		$password = get_user_meta( $user_id, '_labgenz_temp_password', true );
		if ( ! $password ) {
			$password = '[Password set during registration]';
		}

		$subject = sprintf( 'Welcome to %s', get_bloginfo( 'name' ) );
		$message = self::get_welcome_email_message( $user, $password, $context, $extra_data );

		$result = wp_mail( $user->user_email, $subject, $message );

		// Clean up temporary password
		delete_user_meta( $user_id, '_labgenz_temp_password' );

		return $result;
	}

	/**
	 * Get welcome email message based on context
	 *
	 * @param \WP_User $user User object
	 * @param string   $password User password
	 * @param string   $context Email context
	 * @param array    $extra_data Additional data
	 * @return string
	 */
	private static function get_welcome_email_message( \WP_User $user, string $password, string $context, array $extra_data ): string {
		$base_message = sprintf(
			"Hi %s,\n\n" .
			"Welcome to %s! Your account has been created successfully.\n\n" .
			"Your login credentials:\n" .
			"Username: %s\n" .
			"Password: %s\n" .
			"Login URL: %s\n\n",
			$user->display_name,
			get_bloginfo( 'name' ),
			$user->user_login,
			$password,
			wp_login_url()
		);

		// Add context-specific content
		$context_message = '';
		switch ( $context ) {
			case 'subscription':
				$subscription_name = $extra_data['subscription_name'] ?? 'Subscription';
				$context_message   = sprintf(
					"Your %s subscription has been activated and you can now access your subscription benefits.\n\n",
					$subscription_name
				);

				if ( isset( $extra_data['order'] ) && $extra_data['order'] instanceof \WC_Order ) {
					$order            = $extra_data['order'];
					$context_message .= sprintf(
						"Order Details:\n" .
						"Order ID: %s\n" .
						"Total: %s\n\n",
						$order->get_id(),
						$order->get_formatted_order_total()
					);
				}
				break;

			case 'group_invitation':
				$group_name      = $extra_data['group_name'] ?? 'Group';
				$context_message = sprintf(
					"You have been invited to join the group '%s'.\n\n",
					$group_name
				);
				break;

			default:
				$context_message = "You can now start using our platform and access all available features.\n\n";
				break;
		}

		$footer = sprintf(
			"Best regards,\n" .
			'The %s Team',
			get_bloginfo( 'name' )
		);

		return $base_message . $context_message . $footer;
	}

	/**
	 * Send password reset email
	 *
	 * @param int $user_id User ID
	 * @return bool Whether email was sent successfully
	 */
	public static function send_password_reset_email( int $user_id ): bool {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return false;
		}

		$reset_key = get_password_reset_key( $user );
		if ( is_wp_error( $reset_key ) ) {
			return false;
		}

		$reset_url = network_site_url( "wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode( $user->user_login ), 'login' );

		$subject = sprintf( 'Password Reset Request for %s', get_bloginfo( 'name' ) );
		$message = sprintf(
			"Hi %s,\n\n" .
			"You recently requested to reset your password for your account on %s.\n\n" .
			"Click the link below to reset your password:\n" .
			"%s\n\n" .
			"If you did not request this password reset, please ignore this email.\n\n" .
			"Best regards,\n" .
			'The %s Team',
			$user->display_name,
			get_bloginfo( 'name' ),
			$reset_url,
			get_bloginfo( 'name' )
		);

		return wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Get user's temporary password (for welcome emails)
	 *
	 * @param int $user_id User ID
	 * @return string|null
	 */
	public static function get_temp_password( int $user_id ): ?string {
		return get_user_meta( $user_id, '_labgenz_temp_password', true ) ?: null;
	}
}
