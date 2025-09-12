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
		return [ 'labgenz-widgets' ];
	}

	/**
	 * Register widget controls
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			[
				'label' => __( 'Content', 'labgenz-community-management' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'table_title',
			[
				'label'   => __( 'Table Title', 'labgenz-community-management' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Compare Our Plans', 'labgenz-community-management' ),
			]
		);

		$this->add_control(
			'use_custom_benefits',
			[
				'label'        => __( 'Use Custom Benefits', 'labgenz-community-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'labgenz-community-management' ),
				'label_off'    => __( 'No', 'labgenz-community-management' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'description'  => __( 'Enable to override the default benefits with custom ones for this instance', 'labgenz-community-management' ),
			]
		);

		$this->end_controls_section();

		// Advanced section for custom benefits
		$this->start_controls_section(
			'benefits_section',
			[
				'label'     => __( 'Custom Benefits', 'labgenz-community-management' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => [
					'use_custom_benefits' => 'yes',
				],
			]
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'benefit_name',
			[
				'label'   => __( 'Benefit Name', 'labgenz-community-management' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'New Benefit', 'labgenz-community-management' ),
			]
		);

		$repeater->add_control(
			'basic_availability',
			[
				'label'        => __( 'Available in Discovery?', 'labgenz-community-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'labgenz-community-management' ),
				'label_off'    => __( 'No', 'labgenz-community-management' ),
				'return_value' => 'yes',
				'default'      => 'no',
			]
		);

		$repeater->add_control(
			'team_leader_availability',
			[
				'label'        => __( 'Available in Team Leader?', 'labgenz-community-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'labgenz-community-management' ),
				'label_off'    => __( 'No', 'labgenz-community-management' ),
				'return_value' => 'yes',
				'default'      => 'no',
			]
		);

		$repeater->add_control(
			'freedom_builder_availability',
			[
				'label'        => __( 'Available in Freedom Builder?', 'labgenz-community-management' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'labgenz-community-management' ),
				'label_off'    => __( 'No', 'labgenz-community-management' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'custom_benefits',
			[
				'label'       => __( 'Benefits', 'labgenz-community-management' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ benefit_name }}}',
				'default'     => [],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output on the frontend
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'mlmmc-plans-comparison-css' );

		// Get current user and their subscription (if any)
		$user_id          = get_current_user_id();
		$current_plan_key = '';
		if ( $user_id && class_exists( 'LABGENZ_CM\Subscriptions\Helpers\SubscriptionTypeHelper' ) ) {
			$current_type = \LABGENZ_CM\Subscriptions\Helpers\SubscriptionTypeHelper::get_highest_subscription_type( $user_id );

			// Map subscription type to plan key based on the subscription level
			if ( $current_type ) {
				if ( strpos( $current_type, 'freedom-builder' ) !== false ) {
					$current_plan_key = 'freedom_builder';
				} elseif ( strpos( $current_type, 'team-leader' ) !== false ) {
					$current_plan_key = 'team_leader';
				} elseif ( strpos( $current_type, 'basic' ) !== false ) {
					$current_plan_key = 'discovery';
				}
			}
		}

		// Map plan keys to display names and subtitles
		$plans = [
			'discovery'       => [
				'label'    => 'Discovery',
				'subtitle' => '',
			],
			'team_leader'     => [
				'label'    => 'Team Leader',
				'subtitle' => 'Building To $2K/Mo',
			],
			'freedom_builder' => [
				'label'    => 'Freedom Builder',
				'subtitle' => 'Building To $10K/Mo',
			],
		];

		// Get product SKUs and URLs
		$skus = [
			'discovery'       => 'basic-subscription', // Keep the same SKU for backward compatibility
			'team_leader'     => 'mlm-team-leader-yearly',
			'freedom_builder' => 'mlm-freedom-builder-yearly',
		];

		$product_urls = [];
		foreach ( $plans as $plan_key => $plan ) {
			// All join links now point to home URL with #pricing fragment
			$product_urls[ $plan_key ] = home_url( '/#pricing' );
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

		// Determine which benefits to use - default or custom
		if ( $settings['use_custom_benefits'] === 'yes' && ! empty( $settings['custom_benefits'] ) ) {
			$custom_benefits = [];

			foreach ( $settings['custom_benefits'] as $benefit ) {
				// We've removed Apprentice, so we need to adjust the availability array
				// to match the new structure: [Discovery, Team Leader, Freedom Builder]
				$availability = [
					$benefit['basic_availability'] === 'yes',      // Discovery (was Basic)
					$benefit['team_leader_availability'] === 'yes', // Team Leader
					$benefit['freedom_builder_availability'] === 'yes', // Freedom Builder
				];

				$custom_benefits[ $benefit['benefit_name'] ] = $availability;
			}

			$benefits_to_display = $custom_benefits;
		} else {
			// Get benefits from options or use defaults
			$benefits_to_display = $this->get_default_benefits();
		}

		foreach ( $benefits_to_display as $benefit => $availability ) {
			echo '<tr><td>' . esc_html( $benefit ) . '</td>';
			$i = 0;
			foreach ( $plans as $plan_key => $plan ) {
				$is_available = $availability[ $i ];
				echo '<td><span class="dashicons ' . ( $is_available ? 'dashicons-yes' : 'dashicons-no-alt' ) . '"></span></td>';
				++$i;
			}
			echo '</tr>';
		}

		// Add row for purchase/enroll buttons
		echo '<tr class="plan-action-row">';
		echo '<td></td>';
		foreach ( $plans as $plan_key => $plan ) {
			$is_current = ( $current_plan_key === $plan_key );
			$class      = $is_current ? 'current-plan' : '';
			echo '<td class="' . esc_attr( $class ) . '">';
			if ( $is_current ) {
				echo '<span class="current-plan-label">Your Plan</span>';
			} elseif ( ! empty( $product_urls[ $plan_key ] ) ) {
				echo '<a href="' . esc_url( $product_urls[ $plan_key ] ) . '" class="plan-buy-btn">Join</a>';
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

	/**
	 * Get default benefits from options or fallback to hardcoded defaults
	 *
	 * @return array Array of benefits with availability for each plan
	 */
	public function get_default_benefits() {
		// Try to get benefits from options
		$saved_benefits = get_option( 'mlmmc_plan_comparison_benefits_v2', null );

		if ( ! empty( $saved_benefits ) && is_array( $saved_benefits ) ) {
			return $saved_benefits;
		}

		// Fallback to default benefits
		return [
			'Free Membership Updates'                     => [ true, true, true ],
			'Business Support Community'                  => [ false, true, true ],
			'Earn Points For Free Content'                => [ false, true, true ],
			'MLM Business Training'                       => [ false, true, true ],
			'Live "Planning Your Week" Zoom Calls'        => [ false, true, true ],
			'Live Team Leader Office Hour Zoom Calls'     => [ false, true, true ],
			'Access To Zoom Call Recordings'              => [ false, true, true ],
			'FREE Access To MLM Success Library (Y/L)'    => [ false, true, true ],
			'Worldwide Member Map Access (Bonus)'         => [ false, true, true ],
			'Live Freedom Builder Office Hour Zoom Calls' => [ false, false, true ],
			'Mastermind Group Coaching Calls'             => [ false, false, true ],
			'FREE Access To Workshops (Bonus)'            => [ false, false, true ],
			'1 FREE Personal Coaching Call (Y)'           => [ false, false, true ],
			'3 FREE Personal Coaching Calls (L)'          => [ false, false, true ],
			'FREE Simple Connector Access (Y/L)'          => [ false, false, true ],
			'FREE Legal Website Pages (Y/L)'              => [ false, false, true ],
			'4 (1 Year) Team Leader Memberships (L)'      => [ false, false, true ],
			'2 FREE (1 Month) Apprentice Memberships (L)' => [ false, false, true ],
		];
	}
}
