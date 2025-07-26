<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core;

use LABGENZ_CM\Subscriptions\SubscriptionHandler;
use LABGENZ_CM\Articles\WeeklyArticleHandler;

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
     * Initialize hooks
     *
     * @return void
     */
    public function init(): void {
        add_action( 'template_redirect', [ $this, 'check_page_access' ] );
        add_filter( 'the_content', [ $this, 'filter_restricted_content' ] );
        add_action( 'wp_footer', [ $this, 'show_access_denied_alert' ] );
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

        global $post;
        if ( ! $post ) {
            return;
        }

        $post_type = get_post_type( $post );
        if ( ! $this->is_restricted_post_type( $post_type ) ) {
            return;
        }

        // Check if this is the current weekly article - if so, allow access
        if ( $this->is_current_weekly_article( $post->ID ) ) {
            return;
        }

        $user_id           = get_current_user_id();
        $required_resource = $this->restricted_post_types[ $post_type ];

        if ( ! $this->user_has_access( $user_id, $required_resource ) ) {
            $this->redirect_to_upgrade_page();
        }
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

        // Check if this is the current weekly article - if so, allow access
        if ( $this->is_current_weekly_article( $post->ID ) ) {
            return $content;
        }

        $user_id           = get_current_user_id();
        $required_resource = $this->restricted_post_types[ $post_type ];

        if ( ! $this->user_has_access( $user_id, $required_resource ) ) {
            $this->redirect_to_upgrade_page();
        }

        return $content;
    }

    /**
     * Check if a given post ID is the current weekly article
     *
     * @param int $post_id Post ID to check
     * @return bool
     */
    private function is_current_weekly_article( int $post_id ): bool {
        // Get the weekly article handler instance
        $weekly_handler = new WeeklyArticleHandler();
        $weekly_data = $weekly_handler->get_weekly_article_admin_data();
        
        // If we can't get weekly data, assume it's not the weekly article
        if ( ! $weekly_data || ! isset( $weekly_data['article_id'] ) ) {
            return false;
        }
        
        return (int) $weekly_data['article_id'] === $post_id;
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
                    text: '<?php echo esc_js( __( 'You need an Organization subscription to access all MLM articles.', 'labgenz-community-management' ) ); ?>',
                    icon: 'error',
                    confirmButtonText: '<?php echo esc_js( __( 'Purchase Now', 'labgenz-community-management' ) ); ?>',
                    showCancelButton: true,
                    cancelButtonText: '<?php echo esc_js( __( 'Close', 'labgenz-community-management' ) ); ?>'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '<?php echo esc_url( $this->get_checkout_url_with_product() ); ?>';
                    }
                    
                    // Clean up URL
                    if (window.history.replaceState) {
                        window.history.replaceState({}, document.title, '<?php echo esc_url( home_url() ); ?>');
                    }
                });
            } else {
                // Fallback if SweetAlert is not available
                alert('<?php echo esc_js( __( 'You need an Organization subscription to access MLM articles.', 'labgenz-community-management' ) ); ?>');
                if (window.history.replaceState) {
                    window.history.replaceState({}, document.title, '<?php echo esc_url( home_url() ); ?>');
                }
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