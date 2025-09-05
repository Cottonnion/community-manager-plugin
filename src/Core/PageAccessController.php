<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;
use LABGENZ_CM\Articles\DailyArticleHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles page access control based on user subscriptions
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Core
 */
class PageAccessController {

	/**
	 * Post types that require subscription access
	 *
	 * @var array
	 */
	private array $restricted_post_types = [
		'mlmmc_artiicle' => 'can_view_mlm_articles',
	];

	/**
	 * Pages that require specific access control
	 *
	 * @var array
	 */

	private array $restricted_bp_pages = [
		'messages' => 'can_private_message',
	];

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'template_redirect', [ $this, 'check_page_access' ] );
		add_action( 'template_redirect', [ $this, 'check_buddypress_page_access' ] );

		add_filter( 'the_content', [ $this, 'filter_restricted_content' ] );
		add_action( 'wp_footer', [ $this, 'show_access_denied_alert' ] );

		// BuddyBoss profile header notice
		$this->add_buddypress_access_notice();
	}

	/**
	 * Check if current page requires subscription access
	 *
	 * @return void
	 */
	public function check_page_access(): void {
		if ( is_admin() || current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check for archive page of restricted post types
		if ( is_post_type_archive( array_keys( $this->restricted_post_types ) ) ) {
			$post_type         = get_query_var( 'post_type' );
			$required_resource = $this->restricted_post_types[ $post_type ];
			$user_id           = get_current_user_id();

			// Only premium subscribers can access the archive page
			if ( ! $this->user_has_access( $user_id, $required_resource ) ) {
				$this->redirect_to_upgrade_page();
				return;
			}
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		$post_type = get_post_type( $post );
		if ( ! $this->is_restricted_post_type( $post_type ) ) {
			return;
		}

		$user_id = get_current_user_id();

		// Check if this is the current daily article - if so, allow access
		if ( $this->is_current_daily_article( $post->ID ) ) {
			return;
		}

		// Check if it's in the user's accessible articles list (for basic subscription users)
		if ( $user_id && $this->is_in_user_accessible_articles( $post->ID, $user_id ) ) {
			return;
		}

		$required_resource = $this->restricted_post_types[ $post_type ];

		if ( ! $this->user_has_access( $user_id, $required_resource ) ) {
			$this->redirect_to_upgrade_page();
		}
	}


	/**
	 * Restrict access to BuddyBoss pages based on user resources
	 *
	 * @return void
	 */
	public function check_buddypress_page_access(): void {
		if ( is_admin() || current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! is_user_logged_in() || empty( $this->restricted_bp_pages ) ) {
			return;
		}

		$user_id           = get_current_user_id();
		$current_component = bp_current_component(); // e.g., 'messages'

		if ( isset( $this->restricted_bp_pages[ $current_component ] ) ) {
			$required_resource = $this->restricted_bp_pages[ $current_component ];

			if ( ! $this->user_has_access( $user_id, $required_resource ) ) {
				// Redirect with notice param
				wp_safe_redirect( add_query_arg( 'blocked_msg', '1', bp_loggedin_user_domain() ) );
				exit;
			}
		}
	}

	/**
	 * Hook to display access denied notice in BuddyBoss profile header
	 *
	 * @return void
	 */
	public function add_buddypress_access_notice(): void {
		add_action(
			'bp_mlm_profile_header_notice',
			function ( $user_id ) {
				if ( isset( $_GET['blocked_msg'] ) && $_GET['blocked_msg'] === '1' ) {

					// Inline WooCommerce-style notice
					echo '<div class="disabled-access-info" style="margin:15px 0; padding:12px 20px; border-left:4px solid #f39c12; background:#fff8e1; color:#555; font-size:14px;">';
					echo 'Private messaging is disabled for your membership. View other plans <a href="' . esc_url( home_url( '/memberships/' ) ) . '">View Plans</a>';
					echo '</div>';

					// Clear the param so it doesn't show again
					if ( isset( $_GET['blocked_msg'] ) ) {
						$url = remove_query_arg( 'blocked_msg' );
						echo "<script>window.history.replaceState({}, document.title, '" . esc_url( $url ) . "');</script>";
					}
				}
			}
		);
	}

	/**
	 * Filter content for restricted posts
	 *
	 * @param string $content Post content
	 * @return string
	 */
	public function filter_restricted_content( string $content ): string {
		if ( is_admin() || current_user_can( 'manage_options' ) ) {
			return $content;
		}

		global $post;
		if ( ! $post ) {
			return $content;
		}

		$post_type = get_post_type( $post );
		if ( ! $this->is_restricted_post_type( $post_type ) ) {
			return $content;
		}

		$user_id = get_current_user_id();

		// Check if this is the current daily article - if so, allow access
		if ( $this->is_current_daily_article( $post->ID ) ) {
			return $content;
		}

		// Check if it's in the user's accessible articles list (for basic subscription users)
		if ( $user_id && $this->is_in_user_accessible_articles( $post->ID, $user_id ) ) {
			return $content;
		}

		$required_resource = $this->restricted_post_types[ $post_type ];

		if ( ! $this->user_has_access( $user_id, $required_resource ) ) {
			$this->redirect_to_upgrade_page();
		}

		return $content;
	}

	/**
	 * Check if a given post ID is the current daily article
	 *
	 * @param int $post_id Post ID to check
	 * @return bool
	 */
	private function is_current_daily_article( int $post_id ): bool {
		// Get the daily article handler instance
		$daily_handler = new DailyArticleHandler();
		$daily_data    = $daily_handler->get_daily_article_admin_data();

		// If we can't get daily data, assume it's not the daily article
		if ( ! $daily_data || ! isset( $daily_data['article_id'] ) ) {
			return false;
		}

		return (int) $daily_data['article_id'] === $post_id;
	}

	/**
	 * Check if an article is in the user's accessible articles list
	 *
	 * @param int $post_id Post ID to check
	 * @param int $user_id User ID
	 * @return bool Whether the article is accessible to the user
	 */
	private function is_in_user_accessible_articles( int $post_id, int $user_id ): bool {
		if ( ! $user_id ) {
			return false;
		}

		// Get user's accessible article IDs
		$accessible_article_ids = get_user_meta( $user_id, '_accessible_article_ids', true );

		// Check if article ID is in the list
		if ( is_array( $accessible_article_ids ) && in_array( $post_id, $accessible_article_ids ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if user has access to specific resource
	 *
	 * @param int    $user_id  User ID
	 * @param string $resource Resource key to check
	 * @return bool
	 */
	private function user_has_access( $user_id, string $resource ): bool {
		if ( ! $user_id ) {
			return false;
		}

		return SubscriptionHandler::user_has_resource_access( $user_id, $resource );
	}

	/**
	 * Check if post type is restricted
	 *
	 * @param string $post_type Post type to check
	 * @return bool
	 */
	private function is_restricted_post_type( string $post_type ): bool {
		return array_key_exists( $post_type, $this->restricted_post_types );
	}

	/**
	 * Redirect to home page with access denied message
	 *
	 * @return void
	 */
	private function redirect_to_upgrade_page(): void {
		$redirect_url = add_query_arg( 'access_denied', '1', home_url() );
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Show access denied alert using SweetAlert
	 *
	 * @return void
	 */
	public function show_access_denied_alert(): void {
		if ( ! isset( $_GET['access_denied'] ) || $_GET['access_denied'] !== '1' ) {
			return;
		}

		// Only show on home page
		if ( ! is_home() && ! is_front_page() ) {
			return;
		}

		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			if (typeof Swal !== 'undefined') {
				Swal.fire({
					title: '<?php echo esc_js( __( 'Access Denied', 'labgenz-community-management' ) ); ?>',
					text: '<?php echo esc_js( __( 'Articles are restricted for other memberships. You can view details by clicking View Plans', 'labgenz-community-management' ) ); ?>',
					icon: 'error',
					confirmButtonText: '<?php echo esc_js( __( 'View Plans', 'labgenz-community-management' ) ); ?>',
					showCancelButton: true,
					cancelButtonText: '<?php echo esc_js( __( 'View daily Article', 'labgenz-community-management' ) ); ?>',
					showDenyButton: true,
					denyButtonText: '<?php echo esc_js( __( 'Cancel', 'labgenz-community-management' ) ); ?>',
					allowOutsideClick: false,
					allowEscapeKey: true
				}).then((result) => {
					if (result.isConfirmed) {
						// User clicked "Purchase Now" - scroll to pricing section
						const currentUrl = window.location.href;
						const homeUrl = '<?php echo esc_url( home_url() ); ?>';
						
						// Check if we're already on the home page
						if (currentUrl.includes(homeUrl) || window.location.pathname === '/') {
							// We're on the home page, delay scroll until SweetAlert fully closes
							setTimeout(() => {
								scrollToPricingSection();
							}, 200); // Wait for SweetAlert close animation
						} else {
							// We're on a different page, navigate to home with anchor
							window.location.href = homeUrl + '#pricing';
						}
					} else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
						// User clicked "View daily Article" (Cancel button)
						window.location.href = '<?php echo esc_url( home_url( '/daily-article/' ) ); ?>';
					} else if (result.isDenied) {
						// User clicked "Cancel" (Deny button) - just close and clean URL
						// Will fall through to URL cleanup below
					}
					
					// Clean up URL for any dismissal (except for confirmed action)
					if (!result.isConfirmed && window.history.replaceState) {
						window.history.replaceState({}, document.title, '<?php echo esc_url( home_url() ); ?>');
					}
				});
			} else {
				// Fallback if SweetAlert is not available
				alert('<?php echo esc_js( __( 'You need an Organization subscription to access Success Library Articles.', 'labgenz-community-management' ) ); ?>');
				if (window.history.replaceState) {
					window.history.replaceState({}, document.title, '<?php echo esc_url( home_url() ); ?>');
				}
			}
			
			// Function to handle smooth scrolling to pricing section
			function scrollToPricingSection() {
				// Additional delay to ensure DOM is ready
				requestAnimationFrame(() => {
					const pricingElement = document.getElementById('pricing');
					
					if (pricingElement) {
						// Use smooth scrolling if supported
						pricingElement.scrollIntoView({
							behavior: 'smooth',
							block: 'start',
							inline: 'nearest'
						});
						
						// Update URL with anchor (without page refresh) after a small delay
						setTimeout(() => {
							if (window.history.pushState) {
								const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '#pricing';
								window.history.pushState({path: newUrl}, '', newUrl);
							}
						}, 100);
						
					} else {
						// Fallback: try to find pricing section by class or data attribute
						const pricingByClass = document.querySelector('.pricing-section, .pricing, [data-section="pricing"]');
						
						if (pricingByClass) {
							pricingByClass.scrollIntoView({
								behavior: 'smooth',
								block: 'start',
								inline: 'nearest'
							});
							
							setTimeout(() => {
								if (window.history.pushState) {
									const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '#pricing';
									window.history.pushState({path: newUrl}, '', newUrl);
								}
							}, 100);
						} else {
							// If pricing section not found, navigate to home with anchor as fallback
							console.warn('Pricing section not found, redirecting to home page');
							window.location.href = '<?php echo esc_url( home_url() ); ?>#pricing';
						}
					}
				});
			}
			
			// Handle case where user lands on page with #pricing anchor
			if (window.location.hash === '#pricing') {
				// Small delay to ensure page is fully loaded
				setTimeout(() => {
					scrollToPricingSection();
				}, 100);
			}
		});
		</script>
		<?php
	}

	/**
	 * Get checkout URL with organization subscription product added
	 *
	 * @return string
	 */
	private function get_checkout_url_with_product(): string {
		// Get the organization subscription product by SKU
		$products = wc_get_products(
			[
				'sku'    => 'organization-subscription',
				'limit'  => 1,
				'status' => 'publish',
			]
		);

		if ( empty( $products ) ) {
			// Fallback to regular checkout if product not found
			return wc_get_checkout_url();
		}

		$product    = $products[0];
		$product_id = $product->get_id();

		// Create add to cart URL that will redirect to checkout
		$add_to_cart_url = add_query_arg(
			[
				'add-to-cart' => $product_id,
				'quantity'    => 1,
			],
			wc_get_cart_url()
		);

		// Add redirect to checkout parameter
		$checkout_url = add_query_arg(
			[
				'add-to-cart' => $product_id,
				'quantity'    => 1,
			],
			wc_get_checkout_url()
		);

		return $checkout_url;
	}
}