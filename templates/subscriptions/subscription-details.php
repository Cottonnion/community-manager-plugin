<?php
/**
 * Template for displaying subscription details
 *
 * @package Labgenz_Community_Management
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Variables:
 * @var array  $user_subscriptions     Array of user subscriptions
 * @var array  $subscription_resources Subscription resources
 */

use LABGENZ_CM\Subscriptions\Helpers\SubscriptionStorage;

// Debug: Check if we have user_subscriptions
if (empty($user_subscriptions)) {
    echo '<div class="debug-info">No user subscriptions found.</div>';
}

// Debug: Output subscription data
if (current_user_can('administrator')) {
    echo '<div class="debug-info" style="display:none;"><pre>';
    echo 'User subscriptions: ';
    print_r($user_subscriptions);
    echo '</pre></div>';
}
?>
<div class="labgenz-subscription-details">
    <h2 class="labgenz-subscription-details__title"><?php esc_html_e('Your Subscription Details', 'labgenz-community-management'); ?></h2>
    
    <?php 
    // Process each subscription
    $active_subscriptions = [];
    $expired_subscriptions = [];
    
    foreach ($user_subscriptions as $index => $subscription) {
        // Skip subscriptions without required data
        if (!isset($subscription['status']) || !isset($subscription['expires']) || !isset($subscription['type'])) {
            continue;
        }
        
        // Calculate days until expiry
        $days_until_expiry = SubscriptionStorage::calculate_remaining_days($subscription);
        $is_expired = $days_until_expiry <= 0;
        
        // Format subscription name
        $subscription_name = isset($get_formatted_subscription_name) 
            ? $get_formatted_subscription_name($subscription['type'])
            : ucwords(str_replace('-', ' ', $subscription['type']));
        
        if ($is_expired) {
            $expired_subscriptions[] = [
                'type' => $subscription['type'],
                'name' => $subscription_name,
                'expires' => $subscription['expires'],
                'created' => $subscription['created'] ?? null,
                'days_until_expiry' => $days_until_expiry,
            ];
        } else {
            $active_subscriptions[] = [
                'type' => $subscription['type'],
                'name' => $subscription_name,
                'status' => $subscription['status'],
                'expires' => $subscription['expires'],
                'created' => $subscription['created'] ?? null,
                'days_until_expiry' => $days_until_expiry,
                'needs_renewal' => $days_until_expiry <= 15,
            ];
        }
    }
    
    // Debug subscription processing
    if (current_user_can('administrator')) {
        echo '<div class="debug-info" style="display:none;"><pre>';
        echo 'Processed subscriptions: ';
        echo 'Active: ' . count($active_subscriptions) . ', Expired: ' . count($expired_subscriptions);
        echo '</pre></div>';
    }
    
    // Show warning for subscriptions that need renewal
    foreach ($active_subscriptions as $sub) {
        if ($sub['needs_renewal']): 
    ?>
        <div class="labgenz-subscription-notice labgenz-subscription-notice--warning">
            <?php printf(
                esc_html__('Your %s subscription will expire in %d days. Please renew to maintain access to your benefits.', 'labgenz-community-management'),
                '<strong>' . esc_html($sub['name']) . '</strong>',
                $sub['days_until_expiry']
            ); ?>
            <button class="labgenz-subscription-renewal-btn" data-subscription-type="<?php echo esc_attr($sub['type']); ?>"><?php esc_html_e('Renew Now', 'labgenz-community-management'); ?></button>
        </div>
    <?php 
        endif;
    }
    
    // Show message for expired subscriptions
    if (!empty($expired_subscriptions)): 
    ?>
        <div class="labgenz-subscription-notice labgenz-subscription-notice--error">
            <?php 
            if (count($expired_subscriptions) === 1) {
                printf(
                    esc_html__('Your %s subscription has expired. Please renew to regain access to your benefits.', 'labgenz-community-management'),
                    '<strong>' . esc_html($expired_subscriptions[0]['name']) . '</strong>'
                );
            } else {
                esc_html_e('Some of your subscriptions have expired. Please renew to regain access to your benefits.', 'labgenz-community-management');
            }
            ?>
            <button class="labgenz-subscription-renewal-btn"><?php esc_html_e('Renew Now', 'labgenz-community-management'); ?></button>
        </div>
    <?php endif; ?>

    <!-- Active Subscriptions -->
    <?php if (!empty($active_subscriptions)): ?>
        <?php if (count($active_subscriptions) > 1): ?>
            <!-- Multiple Subscriptions Table -->
            <div class="labgenz-subscription-table-container">
                <table class="labgenz-subscription-table labgenz-multiple-subscriptions-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Subscription Plan', 'labgenz-community-management'); ?></th>
                            <th><?php esc_html_e('Status', 'labgenz-community-management'); ?></th>
                            <th><?php esc_html_e('Days Remaining', 'labgenz-community-management'); ?></th>
                            <?php if ($subscription['needs_renewal']): ?>
                                <th><?php esc_html_e('Actions', 'labgenz-community-management'); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_subscriptions as $subscription): ?>
                            <tr>
                                <td><?php echo esc_html($subscription['name']); ?></td>
                                <td>
                                    <span class="labgenz-subscription-status labgenz-subscription-status--<?php echo esc_attr(strtolower($subscription['status'])); ?>">
                                        <?php echo esc_html(ucfirst($subscription['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($subscription['days_until_expiry']); ?>
                                </td>
                                <td>
                                    <?php if ($subscription['needs_renewal']): ?>
                                        <button class="labgenz-subscription-renewal-btn labgenz-btn labgenz-btn--small" 
                                                data-subscription-type="<?php echo esc_attr($subscription['type']); ?>">
                                            <?php esc_html_e('Renew', 'labgenz-community-management'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- Single Subscription View -->
            <?php $subscription = $active_subscriptions[0]; ?>
            <div class="labgenz-subscription-table-container">
                <table class="labgenz-subscription-table">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e('Subscription Plan', 'labgenz-community-management'); ?></th>
                            <td><?php echo esc_html($subscription['name']); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Status', 'labgenz-community-management'); ?></th>
                            <td>
                                <span class="labgenz-subscription-status labgenz-subscription-status--<?php echo esc_attr(strtolower($subscription['status'])); ?>">
                                    <?php echo esc_html(ucfirst($subscription['status'])); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Start Date', 'labgenz-community-management'); ?></th>
                            <td>
                                <?php 
                                if (!empty($subscription['created'])) {
                                    echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription['created'])));
                                } else {
                                    echo esc_html__('N/A', 'labgenz-community-management');
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Expiry Date', 'labgenz-community-management'); ?></th>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($subscription['expires']))); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Days Remaining', 'labgenz-community-management'); ?></th>
                            <td><?php echo esc_html($subscription['days_until_expiry']); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if ($subscription['needs_renewal']): ?>
                    <div class="labgenz-subscription-actions">
                        <button class="labgenz-subscription-renewal-btn labgenz-btn labgenz-btn--primary" 
                                data-subscription-type="<?php echo esc_attr($subscription['type']); ?>">
                            <?php esc_html_e('Renew Subscription', 'labgenz-community-management'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Subscription Benefits Section -->
    <h3 class="labgenz-subscription-details__subtitle"><?php esc_html_e('Your Subscription Benefits', 'labgenz-community-management'); ?></h3>
    
    <div class="labgenz-subscription-benefits">
        <ul class="labgenz-subscription-benefits-list">
            <?php 
            $benefits = isset($get_formatted_benefits) 
                ? $get_formatted_benefits($subscription_resources) 
                : [];
                
            foreach ($benefits as $benefit): 
            ?>
                <li class="labgenz-subscription-benefit <?php echo $benefit['enabled'] ? 'is-enabled' : 'is-disabled'; ?>">
                    <span class="labgenz-subscription-benefit-icon"><?php echo $benefit['enabled'] ? '✓' : '×'; ?></span>
                    <span class="labgenz-subscription-benefit-text"><?php echo esc_html($benefit['label']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Expired Subscriptions Section -->
    <?php if (!empty($expired_subscriptions)): ?>
        <div class="labgenz-expired-subscriptions">
            <h3 class="labgenz-subscription-details__subtitle"><?php esc_html_e('Expired Subscriptions', 'labgenz-community-management'); ?></h3>
            
            <div class="labgenz-subscription-table-container">
                <table class="labgenz-subscription-table labgenz-expired-subscriptions-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Subscription Plan', 'labgenz-community-management'); ?></th>
                            <th><?php esc_html_e('Start Date', 'labgenz-community-management'); ?></th>
                            <th><?php esc_html_e('Expired On', 'labgenz-community-management'); ?></th>
                            <th><?php esc_html_e('Actions', 'labgenz-community-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expired_subscriptions as $expired): ?>
                            <tr>
                                <td><?php echo esc_html($expired['name']); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($expired['created'])) {
                                        echo esc_html(date_i18n(get_option('date_format'), strtotime($expired['created'])));
                                    } else {
                                        echo esc_html__('N/A', 'labgenz-community-management');
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($expired['expires']))); ?></td>
                                <td>
                                    <button class="labgenz-subscription-renewal-btn labgenz-btn labgenz-btn--small"
                                            data-subscription-type="<?php echo esc_attr($expired['type']); ?>">
                                        <?php esc_html_e('Renew', 'labgenz-community-management'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Styling for multiple subscriptions table */
    .labgenz-multiple-subscriptions-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    
    .labgenz-multiple-subscriptions-table th,
    .labgenz-multiple-subscriptions-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    
    .labgenz-multiple-subscriptions-table th {
        background-color: #f5f5f5;
        font-weight: bold;
    }
    
    .labgenz-multiple-subscriptions-table tr:hover {
        background-color: #f9f9f9;
    }
    
    .labgenz-btn--small {
        padding: 5px 10px;
        font-size: 12px;
    }
</style>
