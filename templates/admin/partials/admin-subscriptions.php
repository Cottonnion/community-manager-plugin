<?php
/**
 * Admin Subscriptions Page Template
 *
 * @package LabgenzCommunityManagement
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get the current user data
$current_user = wp_get_current_user();

// Get subscriptions with pagination
$paged = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
$per_page = 90;

// Process filters
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$date_range = isset( $_GET['date_range'] ) ? sanitize_text_field( $_GET['date_range'] ) : '';
$search_query = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';

// Get subscriptions data
$subscriptions = $this->get_subscriptions(
    array(
        'paged'      => $paged,
        'per_page'   => $per_page,
        'status'     => $status_filter,
        'date_range' => $date_range,
        'search'     => $search_query,
    )
);

$total_subscriptions = $subscriptions['total'];
$max_pages = ceil( $total_subscriptions / $per_page );
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <div class="admin-subscriptions-container">
        <div class="admin-subscriptions-header">
            <div class="admin-subscriptions-title">Manage Subscriptions</div>
            <div class="admin-subscriptions-counter">
                <span id="total-subscriptions">Total: <?php echo esc_html( $total_subscriptions ); ?></span>
            </div>
        </div>
        
        <div class="admin-subscriptions-filters">
            <select id="status-filter" name="status">
                <option value="">All Statuses</option>
                <option value="active" <?php selected( $status_filter, 'active' ); ?>>Active</option>
                <option value="expired" <?php selected( $status_filter, 'expired' ); ?>>Expired</option>
                <option value="deleted" <?php selected( $status_filter, 'deleted' ); ?>>Upgraded Subscriptions</option>
            </select>
            
            <select id="date-filter" name="date_range">
                <option value="">All Time</option>
                <option value="today" <?php selected( $date_range, 'today' ); ?>>Today</option>
                <option value="this_week" <?php selected( $date_range, 'this_week' ); ?>>This Week</option>
                <option value="this_month" <?php selected( $date_range, 'this_month' ); ?>>This Month</option>
                <option value="last_month" <?php selected( $date_range, 'last_month' ); ?>>Last Month</option>
                <option value="this_year" <?php selected( $date_range, 'this_year' ); ?>>This Year</option>
            </select>
            
            <input type="text" id="search-subscriptions" placeholder="Search by user or plan..." value="<?php echo esc_attr( $search_query ); ?>">
        </div>
        
        <table class="admin-subscriptions-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>Expiry Date</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $subscriptions['items'] ) ) : ?>
                    <?php foreach ( $subscriptions['items'] as $subscription ) : ?>
                        <tr data-subscription="<?php echo esc_attr( $subscription->id ); ?>">
                            <td>
                                <?php if ( ! empty( $subscription->user_id ) ) : ?>
                                    <a href="<?php echo esc_url( get_edit_user_link( $subscription->user_id ) ); ?>" target="_blank">
                                        <?php echo esc_html( $subscription->user_name ); ?>
                                    </a>
                                    <?php echo '</br><small>' . esc_html( $subscription->user_email ) . '</small>'; ?>
                                <?php else : ?>
                                    <?php echo esc_html( $subscription->user_name ); ?>
                                    <?php echo '</br><small>' . esc_html( $subscription->user_email ) . '</small>'; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $subscription->plan_name ); ?></td>
                            <td>
                                <span class="subscription-status status-<?php echo esc_attr( strtolower( $subscription->status ) ); ?>">
                                    <?php 
                                    if($subscription->status === 'Deleted') {
                                            echo esc_html( 'Upgraded Subscription' );
                                        } else {
                                            echo esc_html( ucfirst( $subscription->status ) );
                                        }
                                    ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $subscription->start_date ); ?></td>
                            <td><?php echo esc_html( $subscription->expiry_date ); ?></td>
                            <td><?php echo esc_html( $subscription->amount ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7" class="subscription-empty-state">
                            <p>No subscriptions found</p>
                            <?php if ( ! empty( $status_filter ) || ! empty( $date_range ) || ! empty( $search_query ) ) : ?>
                                <p>Try adjusting your search filters</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ( $max_pages > 1 ) : ?>
            <div class="admin-subscriptions-pagination">
                <?php
                $pagination_range = 2; // Number of pages to show on each side of current page
                
                // Previous button
                if ( $paged > 1 ) :
                    ?>
                    <button data-page="<?php echo esc_attr( $paged - 1 ); ?>">←</button>
                    <?php
                endif;
                
                // Page numbers
                for ( $i = max( 1, $paged - $pagination_range ); $i <= min( $max_pages, $paged + $pagination_range ); $i++ ) :
                    ?>
                    <button data-page="<?php echo esc_attr( $i ); ?>" class="<?php echo $i === $paged ? 'current' : ''; ?>">
                        <?php echo esc_html( $i ); ?>
                    </button>
                    <?php
                endfor;
                
                // Next button
                if ( $paged < $max_pages ) :
                    ?>
                    <button data-page="<?php echo esc_attr( $paged + 1 ); ?>">→</button>
                    <?php
                endif;
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Subscription Modal - Hidden by default -->
<div class="subscription-modal" id="subscription-form-modal">
    <div class="subscription-modal-content">
        
        <form id="subscription-form">
            <input type="hidden" name="subscription_id" id="subscription_id" value="">
            
            <div class="subscription-form-row">
                <label for="user_id">User</label>
                <select name="user_id" id="user_id" required>
                    <option value="">Select User</option>
                    <?php
                    // Get users who can have subscriptions
                    $users = get_users( array( 'role__in' => array( 'subscriber', 'customer' ) ) );
                    foreach ( $users as $user ) :
                        ?>
                        <option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?></option>
                        <?php
                    endforeach;
                    ?>
                </select>
            </div>
            
            <div class="subscription-form-row">
                <label for="plan_id">Subscription Plan</label>
                <select name="plan_id" id="plan_id" required>
                    <option value="">Select Plan</option>
                    <?php
                    // Get available subscription plans
                    $plans = $this->get_subscription_plans();
                    foreach ( $plans as $plan ) :
                        ?>
                        <option value="<?php echo esc_attr( $plan->id ); ?>"><?php echo esc_html( $plan->name . ' - ' . $plan->price ); ?></option>
                        <?php
                    endforeach;
                    ?>
                </select>
            </div>
            
            <div class="subscription-form-row">
                <label for="status">Status</label>
                <select name="status" id="status" required>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="expired">Expired</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="subscription-form-row">
                <label for="start_date">Start Date</label>
                <input type="date" name="start_date" id="start_date" required value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
            </div>
            
            <div class="subscription-form-row">
                <label for="expiry_date">Expiry Date</label>
                <input type="date" name="expiry_date" id="expiry_date" required value="<?php echo esc_attr( date( 'Y-m-d', strtotime( '+1 year' ) ) ); ?>">
            </div>
            
            <div class="subscription-form-row">
                <label for="amount">Amount</label>
                <input type="text" name="amount" id="amount" required placeholder="e.g., $99.99">
            </div>
            
            <div class="subscription-form-row">
                <label for="payment_method">Payment Method</label>
                <select name="payment_method" id="payment_method">
                    <option value="credit_card">Credit Card</option>
                    <option value="paypal">PayPal</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="manual">Manual</option>
                </select>
            </div>
            
            <div class="subscription-form-row">
                <label for="auto_renewal">Auto Renewal</label>
                <select name="auto_renewal" id="auto_renewal">
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>
            
            <div class="subscription-form-row">
                <label for="notes">Admin Notes</label>
                <textarea name="notes" id="notes" rows="3" placeholder="Add any notes about this subscription (visible to admins only)"></textarea>
            </div>
            
            <div class="subscription-form-actions">
                <button type="button" class="btn-cancel subscription-modal-close">Cancel</button>
                <button type="submit" class="btn-save">Save Subscription</button>
            </div>
        </form>
    </div>
</div>
