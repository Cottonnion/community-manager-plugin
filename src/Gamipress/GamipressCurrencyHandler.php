<?php
declare(strict_types=1);

namespace LABGENZ_CM\Gamipress;

use LABGENZ_CM\Gamipress\Helpers\GamiPressDataProvider;


class GamipressCurrencyHandler {


	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize all WordPress hooks
	 */
	private function init_hooks(): void {
		// Cart validation hooks
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'check_add_to_cart_price_with_user_credits' ], 10, 4 );
		add_filter( 'woocommerce_update_cart_validation', [ $this, 'update_cart_quantity_validation' ], 10, 4 );

		// Meta box hooks
		add_action( 'add_meta_boxes', [ $this, 'create_custom_meta_box' ] );
		add_action( 'save_post', [ $this, 'save_custom_content_meta_box' ], 10, 1 );

		// Currency hooks
		add_filter( 'woocommerce_currencies', [ $this, 'add_custom_currencies' ] );
		add_filter( 'woocommerce_currency_symbol', [ $this, 'add_custom_currency_symbols' ], 10, 2 );

		// Functionality moved to WooCommerceHelper -- this is kept for reference
		// add_filter('woocommerce_available_payment_gateways', [$this, 'filter_payment_gateways_by_currency']);

		// Order hooks
		add_action( 'woocommerce_checkout_create_order', [ $this, 'change_order_currency' ], 999, 1 );

		// Cart hooks
		add_filter( 'woocommerce_add_to_cart_redirect', [ $this, 'redirect_to_checkout' ], 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'clear_cart_before_add' ], 20, 3 );
		add_filter( 'woocommerce_is_sold_individually', [ $this, 'remove_quantity_fields' ], 10, 2 );

		// Session hooks
		add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'set_cart_session_currency' ] );
		add_action( 'woocommerce_before_checkout_form', [ $this, 'set_cart_session_currency' ] );

		// Remove default add to cart buttons
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart' );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

		// Add custom add to cart button
		add_action( 'woocommerce_after_shop_loop_item', [ $this, 'custom_add_to_cart_button' ] );
		add_action( 'woocommerce_single_product_summary', [ $this, 'custom_add_to_cart_button' ], 30 );

		// Admin scripts
		add_action( 'admin_footer', [ $this, 'admin_metabox_scripts' ] );
	}

	/**
	 * Get currency symbol for a product
	 */
	public function get_currency_symbol( int $product_id ): string {
		$currency_select = get_post_meta( $product_id, 'currency_select', true );

		if ( empty( $currency_select ) ) {
			return ' USD';
		}

		return match ( strtoupper( trim( $currency_select ) ) ) {
			'POINTS' => ' Points ',
			'CREDITS' => ' Credits ',
			'USD' => ' USD',
			default => ' USD'
		};
	}

	/**
	 * Get available currencies for this handler
	 *
	 * @return array
	 */
	public function get_available_currencies(): array {
		return [
			'USD'     => __( 'USD', 'woocommerce' ),
			'POINTS'  => __( 'Points', 'woocommerce' ),
			'credits' => __( 'Credits', 'woocommerce' ),
		];
	}

	/**
	 * Check if user has sufficient funds for product
	 */
	public function user_has_sufficient_funds( int $product_id, int $quantity = 1 ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		$currency_select = get_post_meta( $product_id, 'currency_select', true );
		$product_price   = floatval( $product->get_regular_price() ) * $quantity;
		$user_id         = get_current_user_id();
		$cart_total      = WC()->cart ? WC()->cart->subtotal : 0;
		$required_amount = $cart_total + $product_price;

		return match ( strtolower( trim( $currency_select ) ) ) {
			'credits' => gamipress_get_user_points( $user_id, 'credits' ) >= $required_amount,
			'points' => gamipress_get_user_points( $user_id, 'reward_points' ) >= $required_amount,
			'usd' => true,
			default => true
		};
	}

	/**
	 * Set cart session currency
	 */
	public function set_cart_session_currency(): void {
		if ( ! WC()->cart || ! WC()->cart->get_cart() ) {
			return;
		}

		$currency = 'USD';
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$currency_select = get_post_meta( $cart_item['product_id'], 'currency_select', true );
			if ( ! empty( $currency_select ) ) {
				$currency = strtoupper( trim( $currency_select ) );
				break;
			}
		}

		if ( function_exists( 'WC' ) ) {
			WC()->session->set( 'currency', $currency );
		}
	}

	/**
	 * Custom add to cart button with funds check
	 */
	public function custom_add_to_cart_button(): void {
		global $product;

		if ( ! $product ) {
			return;
		}

		$product_id           = $product->get_id();
		$has_sufficient_funds = $this->user_has_sufficient_funds( $product_id );
		$currency_select      = get_post_meta( $product_id, 'currency_select', true );

		if ( ! $has_sufficient_funds && in_array( strtolower( $currency_select ), [ 'credits', 'points' ] ) ) {
			$this->display_insufficient_funds_message( $product_id );
		} else {
			echo '<div class="custom-add-to-cart-wrapper">';
			woocommerce_template_loop_add_to_cart();
			echo '</div>';
		}
	}

	/**
	 * Display insufficient funds message
	 */
	private function display_insufficient_funds_message( int $product_id ): void {
		$product         = wc_get_product( $product_id );
		$currency_select = get_post_meta( $product_id, 'currency_select', true );
		$user_id         = get_current_user_id();

		if ( strtolower( $currency_select ) === 'credits' ) {
			$user_credits     = gamipress_get_user_points( $user_id, 'credits' );
			$required_credits = $product->get_regular_price();
			$needed_credits   = $required_credits - $user_credits;
			$buy_credits_url  = get_site_url( null, '/credits-purchase/' );

			echo '<div class="insufficient-funds-message credits-message">';
			echo '<p class="insufficient-funds-text">';
			printf(
				__( 'You need %1$u more credits. You have %2$u credits.', 'text_domain' ),
				$needed_credits,
				$user_credits
			);
			echo '</p>';
			echo '<a href="' . esc_url( $buy_credits_url ) . '" class="btn btn-warning buy-credits-btn" target="_blank">';
			echo __( 'Buy Credits', 'text_domain' );
			echo '</a>';
			echo '</div>';

		} elseif ( strtolower( $currency_select ) === 'points' ) {
			$user_points     = gamipress_get_user_points( $user_id, 'reward_points' );
			$required_points = $product->get_regular_price();
			$needed_points   = $required_points - $user_points;

			echo '<div class="insufficient-funds-message points-message">';
			echo '<p class="insufficient-funds-text">';
			printf(
				__( 'You need %1$u more activity points. You currently have %2$u. Earn points by completing activities.', 'text_domain' ),
				$needed_points,
				$user_points
			);
			echo '</p>';
			echo '</div>';
		}
	}

	/**
	 * Validate add to cart with user credits/points
	 */
	public function check_add_to_cart_price_with_user_credits( bool $passed, int $product_id, int $quantity, $variation_id = null ): bool {
		if ( ! is_user_logged_in() ) {
			return $passed;
		}

		$product         = wc_get_product( $product_id );
		$user_id         = get_current_user_id();
		$currency_select = get_post_meta( $product_id, 'currency_select', true );
		$cart_total      = WC()->cart->subtotal;
		$product_total   = $product->get_regular_price() * $quantity;
		$required_amount = $cart_total + $product_total;

		if ( strtolower( $currency_select ) === 'credits' ) {
			$user_credits = gamipress_get_user_points( $user_id, 'credits' );
			if ( $user_credits < $required_amount ) {
				$needed_credits = $required_amount - $user_credits;
				$url            = get_site_url( null, '/credits-purchase/' );

				wc_add_notice(
					sprintf(
						__( 'You need "%1$u" more credits to purchase. You have "%2$u" credits. <a href="%3$s" class="btn btn-warning" target="_blank">Buy Credits</a>', 'text_domain' ),
						$needed_credits,
						$user_credits,
						$url
					),
					'error'
				);
				return false;
			}
		} elseif ( strtoupper( $currency_select ) === 'POINTS' ) {
			$user_points = gamipress_get_user_points( $user_id, 'reward_points' );
			if ( $user_points < $required_amount ) {
				$needed_points = $required_amount - $user_points;

				wc_add_notice(
					sprintf(
						__( 'You need "%1$u" more points to purchase. You have "%2$u" points.', 'text_domain' ),
						$needed_points,
						$user_points
					),
					'error'
				);
				return false;
			}
		}

		return $passed;
	}

	/**
	 * Validate cart quantity updates
	 */
	public function update_cart_quantity_validation( bool $passed, string $cart_item_key, array $values, int $quantity ): bool {
		if ( empty( $values ) || ! is_user_logged_in() ) {
			return $passed;
		}

		$product      = wc_get_product( $values['product_id'] );
		$user_credits = gamipress_get_user_points( get_current_user_id(), 'credits' );
		$terms        = [ 'credits' ];

		if ( has_term( $terms, 'product_cat', $values['product_id'] ) ) {
			$required_amount = WC()->cart->subtotal + ( $product->get_regular_price() * $quantity );

			if ( $user_credits < $required_amount ) {
				$needed_credits = $required_amount - $user_credits;
				$url            = get_site_url( null, '/credits-purchase/' );

				wc_add_notice(
					sprintf(
						__( 'You need "%1$u" more credits to purchase. You have "%2$u" credits. <a href="%3$s" class="btn btn-warning" target="_blank">Buy Credits</a>', 'text_domain' ),
						$needed_credits,
						$user_credits,
						$url
					),
					'error'
				);
				return false;
			}
		}

		return $passed;
	}

	/**
	 * Create custom meta box for currency selection
	 */
	public function create_custom_meta_box(): void {
		add_meta_box(
			'currency_select_meta_box',
			__( 'Select a currency', 'cmb' ),
			[ $this, 'add_custom_content_meta_box' ],
			'product',
			'normal',
			'default'
		);
	}

	/**
	 * Add content to custom meta box
	 */
	public function add_custom_content_meta_box( $post ): void {
		echo '<div class="product_custom_field">';

		woocommerce_wp_select(
			[
				'id'                => 'currency_select',
				'label'             => __( 'Select a currency ', 'woocommerce' ),
				'custom_attributes' => [ 'required' => 'required' ],
				'options'           => [
					'USD'     => 'USD',
					'POINTS'  => 'Points',
					'credits' => 'Credits',
				],
			]
		);

		echo '</div>';
	}

	/**
	 * Save custom meta box data
	 */
	public function save_custom_content_meta_box( int $post_id ): void {
		if ( isset( $_POST['currency_select'] ) ) {
			update_post_meta( $post_id, 'currency_select', wp_kses_post( $_POST['currency_select'] ) );
		}
	}

	/**
	 * Add custom currencies to WooCommerce
	 */
	public function add_custom_currencies( array $currencies ): array {
		$currencies['POINTS']  = __( 'Points', 'woocommerce' );
		$currencies['CREDITS'] = __( 'Credits', 'woocommerce' );
		return $currencies;
	}

	/**
	 * Add custom currency symbols
	 */
	public function add_custom_currency_symbols( string $currency_symbol, string $currency ): string {
		global $post, $product;

		$currency_select = '';

		// Get currency from current product/post
		if ( $post ) {
			$currency_select = get_post_meta( $post->ID, 'currency_select', true );
		} elseif ( $product ) {
			$currency_select = get_post_meta( $product->get_id(), 'currency_select', true );
		}

		// Handle cart and checkout pages
		if ( ( is_cart() || is_checkout() ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$currency_select = get_post_meta( $cart_item['product_id'], 'currency_select', true );
				break; // Use first item's currency
			}
		}

		if ( ! empty( $currency_select ) ) {
			return $this->get_currency_symbol_by_type( $currency_select );
		}

		return $this->get_currency_symbol_by_type( $currency );
	}

	/**
	 * Get currency symbol by type
	 */
	private function get_currency_symbol_by_type( string $currency_type ): string {
		return match ( strtoupper( trim( $currency_type ) ) ) {
			'POINTS' => ' Points ',
			'CREDITS' => ' Credits ',
			'USD' => ' USD',
			default => ' USD'
		};
	}

	/**
	 * Change order currency on checkout
	 */
	public function change_order_currency( $order ): void {
		$currency = 'USD';

		foreach ( $order->get_items() as $item ) {
			$product    = $item->get_product();
			$product_id = $product->get_id();
			$parent_id  = $product->get_parent_id();

			if ( $product->is_type( 'subscription_variation' ) ) {
				$currency = get_post_meta( $parent_id, 'currency_select', true );
			} else {
				$currency = get_post_meta( $product_id, 'currency_select', true );
			}

			break; // Use first item's currency
		}

		$order->set_currency( $currency );
		$order->save();
	}


	/**
	 * Redirect to checkout after add to cart
	 */
	public function redirect_to_checkout( string $url, $adding_to_cart ): string {
		return wc_get_checkout_url();
	}

	/**
	 * Clear cart before adding new item
	 */
	public function clear_cart_before_add( bool $passed, int $product_id, int $quantity ): bool {
		WC()->session->__unset( 'chosen_payment_method' );
		WC()->cart->empty_cart();
		return $passed;
	}

	/**
	 * Remove quantity fields (sold individually)
	 */
	public function remove_quantity_fields( bool $return, $product ): bool {
		return true;
	}

	/**
	 * Add admin scripts for metabox handling
	 */
	public function admin_metabox_scripts(): void {
		?>
		<script>
		jQuery(window).load(function() {
			jQuery(document).on("click", ".handlediv", function() {
				jQuery("#currency_select_meta_box").toggleClass("closed");
			});
		});
		</script>
		
		<style>
		.insufficient-funds-message {
			padding: 15px;
			margin: 10px 0;
			border-radius: 5px;
			background-color: #f8d7da;
			border: 1px solid #f5c6cb;
			color: #721c24;
		}
		
		.insufficient-funds-text {
			margin-bottom: 10px;
			font-weight: bold;
		}
		
		.insufficient-points-notice {
			padding: 15px;
			margin: 15px 0;
			border-radius: 6px;
			background-color: #fff3cd;
			border: 1px solid #ffeeba;
			box-shadow: 0 2px 5px rgba(0,0,0,0.1);
			color: #856404;
			position: relative;
			overflow: hidden;
		}
		
		.insufficient-points-notice .points-notice-icon {
			display: inline-block;
			vertical-align: middle;
			margin-right: 10px;
			font-size: 20px;
			color: #e0a800;
		}
		
		.insufficient-points-notice .points-notice-content {
			display: inline-block;
			vertical-align: middle;
		}
		
		.insufficient-points-notice h4 {
			margin-top: 0;
			margin-bottom: 8px;
			color: #856404;
			font-size: 16px;
		}
		
		.insufficient-points-notice .points-info {
			margin-bottom: 10px;
			padding: 8px;
			background-color: rgba(255, 255, 255, 0.5);
			border-radius: 4px;
		}
		
		.insufficient-points-notice .points-earn-tip {
			font-style: italic;
			margin-bottom: 10px;
		}
		
		.earn-points-btn {
			display: inline-block;
			padding: 8px 16px;
			background-color: #28a745;
			color: #fff;
			text-decoration: none;
			border-radius: 4px;
			font-weight: bold;
			transition: background-color 0.2s ease;
		}
		
		.earn-points-btn:hover {
			background-color: #218838;
			color: #fff;
			text-decoration: none;
		}
		
		.buy-credits-btn {
			display: inline-block;
			padding: 8px 16px;
			background-color: #ffc107;
			color: #212529;
			text-decoration: none;
			border-radius: 4px;
			font-weight: bold;
		}
		
		.buy-credits-btn:hover {
			background-color: #e0a800;
			color: #212529;
			text-decoration: none;
		}
		</style>
		<?php
	}
}