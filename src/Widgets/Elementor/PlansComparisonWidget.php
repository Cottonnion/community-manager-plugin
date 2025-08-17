<?php

namespace LABGENZ_CM\Widgets\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use LABGENZ_CM\Subscriptions\SubscriptionHandler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PlansComparisonWidget extends Widget_Base {

    /**
     * Get widget name
     *
     * @return string
     */
    public function get_name() {
        return 'plans_comparison_widget';
    }

    /**
     * Get widget title
     *
     * @return string
     */
    public function get_title() {
        return __( 'Plans Comparison Table', 'labgenz-community-management' );
    }

    /**
     * Get widget icon
     *
     * @return string
     */
    public function get_icon() {
        return 'eicon-table';
    }

    /**
     * Get widget categories
     *
     * @return array
     */
    public function get_categories() {
        return [ 'general' ];
    }

    /**
     * Register widget controls
     */
    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __( 'Content', 'labgenz-community-management' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'table_title',
            [
                'label' => __( 'Table Title', 'labgenz-community-management' ),
                'type' => Controls_Manager::TEXT,
                'default' => __( 'Compare Our Plans', 'labgenz-community-management' ),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        wp_enqueue_style('dashicons');
        wp_enqueue_style('mlmmc-plans-comparison-css');

        // Get current user and their subscription (if any)
        $user_id = get_current_user_id();
        $current_plan_key = '';
        if ( $user_id && class_exists('LABGENZ_CM\Subscriptions\Helpers\SubscriptionTypeHelper') ) {
            $current_type = \LABGENZ_CM\Subscriptions\Helpers\SubscriptionTypeHelper::get_highest_subscription_type( $user_id );
            
            // Map subscription type to plan key based on the subscription level
            if ($current_type) {
                if (strpos($current_type, 'freedom-builder') !== false) {
                    $current_plan_key = 'freedom_builder';
                } elseif (strpos($current_type, 'team-leader') !== false) {
                    $current_plan_key = 'team_leader';
                } elseif (strpos($current_type, 'apprentice') !== false) {
                    $current_plan_key = 'apprentice';
                } elseif (strpos($current_type, 'basic') !== false) {
                    $current_plan_key = 'basic';
                }
            }
        }

        // Map plan keys to display names and subtitles
        $plans = [
            'basic' => [
                'label' => 'Basic',
                'subtitle' => '',
            ],
            'apprentice' => [
                'label' => 'Apprentice',
                'subtitle' => 'New Distributor',
            ],
            'team_leader' => [
                'label' => 'Team Leader',
                'subtitle' => 'Building To $2K/Mo',
            ],
            'freedom_builder' => [
                'label' => 'Freedom Builder',
                'subtitle' => 'Building To $10K/Mo',
            ],
        ];

        // Get product SKUs and URLs
        $skus = [
            'basic' => 'basic-subscription',
            'apprentice' => 'mlm-apprentice-yearly',
            'team_leader' => 'mlm-team-leader-yearly',
            'freedom_builder' => 'mlm-freedom-builder-yearly',
        ];
        
        $product_urls = [];
        foreach ( $skus as $plan_key => $sku ) {
            $product_id = wc_get_product_id_by_sku( $sku );
            if ( $product_id ) {
                $product_urls[$plan_key] = get_permalink( $product_id );
            } else {
                $product_urls[$plan_key] = '';
            }
        }

        echo '<div class="plans-comparison-widget">';
        echo '<h2>' . esc_html( $settings['table_title'] ) . '</h2>';
        echo '<table class="plans-comparison-table">';

        // Table header
        echo '<thead><tr>';
        echo '<th>Member Benefits</th>';
        foreach ( $plans as $plan_key => $plan ) {
            echo '<th>' . esc_html( $plan['label'] );
            if ( $plan['subtitle'] ) {
                echo '<br><span class="subtitle">' . esc_html( $plan['subtitle'] ) . '</span>';
            }
            echo '</th>';
        }
        echo '</tr></thead>';

        // Table body
        echo '<tbody>';
        $benefits = [
            'Free Membership Updates' => [true, true, true, true],
            'Business Support Community' => [false, true, true, true],
            'Earn Points For Free Content' => [false, true, true, true],
            'MLM Business Training' => [false, true, true, true],
            'Live "Planning Your Week" Zoom Calls' => [false, false, true, true],
            'Live Team Leader Office Hour Zoom Calls' => [false, false, true, true],
            'Access To Zoom Call Recordings' => [false, false, true, true],
            'FREE Access To MLM Success Library (Y/L)' => [false, false, true, true],
            'Worldwide Member Map Access (Bonus)' => [false, false, true, true],
            'Live Freedom Builder Office Hour Zoom Calls' => [false, false, false, true],
            'Mastermind Group Coaching Calls' => [false, false, false, true],
            'FREE Access To Workshops (Bonus)' => [false, false, false, true],
            '1 FREE Personal Coaching Call (Y)' => [false, false, false, true],
            '3 FREE Personal Coaching Calls (L)' => [false, false, false, true],
            'FREE Simple Connector Access (Y/L)' => [false, false, false, true],
            'FREE Legal Website Pages (Y/L)' => [false, false, false, true],
            '4 (1 Year) Team Leader Memberships (L)' => [false, false, false, true],
            '2 FREE (1 Month) Apprentice Memberships (L)' => [false, false, false, true],
        ];
        foreach ( $benefits as $benefit => $availability ) {
            echo '<tr><td>' . esc_html( $benefit ) . '</td>';
            $i = 0;
            foreach ( $plans as $plan_key => $plan ) {
                $is_available = $availability[$i];
                echo '<td><span class="dashicons ' . ( $is_available ? 'dashicons-yes' : 'dashicons-no-alt' ) . '"></span></td>';
                $i++;
            }
            echo '</tr>';
        }

        // Add row for purchase/enroll buttons
        echo '<tr class="plan-action-row">';
        echo '<td></td>';
        foreach ( $plans as $plan_key => $plan ) {
            $is_current = ($current_plan_key === $plan_key);
            $class = $is_current ? 'current-plan' : '';
            echo '<td class="' . esc_attr($class) . '">';
            if ( $is_current ) {
                echo '<span class="current-plan-label">Your Plan</span>';
            } else if ( !empty($product_urls[$plan_key]) ) {
                echo '<a href="' . esc_url($product_urls[$plan_key]) . '" class="plan-buy-btn">Join</a>';
            } else {
                echo '<span class="plan-unavailable">Not Available</span>';
            }
            echo '</td>';
        }
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';
        echo '<p>Note: Y or L = Founder Bonuses for Yearly or Lifetime Members Only</p>';
        echo '</div>';
    }
}
