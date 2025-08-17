<?php

declare(strict_types=1);

namespace LABGENZ_CM\Public;

use LABGENZ_CM\Core\OrganizationAccess;
use LABGENZ_CM\Subscriptions\SubscriptionHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public interface for organization access requests
 *
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Public
 */
class OrganizationAccessPublic {

	/**
	 * Organization access handler
	 *
	 * @var OrganizationAccess
	 */
	private OrganizationAccess $org_access;

	/**
	 * @var SubscriptionsHandler
	 */
	private SubscriptionHandler $subscription_handler;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->org_access           = new OrganizationAccess();
		$this->subscription_handler = SubscriptionHandler::get_instance();
	}

	/**
	 * Initialize public functionality
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_head', [ $this, 'add_header_button' ] );
		add_action( 'wp_footer', [ $this, 'add_request_form_modal' ] );
		add_shortcode( 'labgenz_org_access_status', [ $this, 'request_status_shortcode' ] );
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		// Only enqueue on frontend
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_script(
			'labgenz-org-access-public',
			LABGENZ_CM_URL . 'src/Public/assets/js/organization-access.js',
			[ 'jquery' ],
			'1.0.3',
			true
		);

		wp_enqueue_style(
			'labgenz-org-access-public',
			LABGENZ_CM_URL . 'src/Public/assets/css/organization-access.css',
			[],
			'1.0.4'
		);

		wp_localize_script(
			'labgenz-org-access-public',
			'labgenz_org_access',
			[
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'labgenz_org_access_nonce' ),
				'is_logged_in' => is_user_logged_in(),
				'user_status'  => is_user_logged_in() ? $this->org_access->get_user_request_status( get_current_user_id() ) : null,
				'user_request_info' => is_user_logged_in() ? $this->get_user_request_info() : null,
				'strings'      => [
					'login_required'   => __( 'You must be logged in to request organization access.', 'labgenz-community-management' ),
					'already_pending'  => __( 'You already have a pending organization access request. Please wait for an admin to review it', 'labgenz-community-management' ),
					'already_approved' => __( 'Your organization access request has been approved.', 'labgenz-community-management' ),
					'already_rejected' => __( 'Your organization access request was rejected.', 'labgenz-community-management' ),
					'form_title'       => __( 'Request Organization Access', 'labgenz-community-management' ),
					'submit_button'    => __( 'Submit Request', 'labgenz-community-management' ),
					'cancel_button'    => __( 'Cancel', 'labgenz-community-management' ),
					'submitting'       => __( 'Submitting...', 'labgenz-community-management' ),
					'success'          => __( 'Your request has been submitted successfully!', 'labgenz-community-management' ),
					'error'            => __( 'An error occurred. Please try again.', 'labgenz-community-management' ),
					'validation_error' => __( 'Please fill in all required fields.', 'labgenz-community-management' ),
					'button_texts'     => [
						'default'  => __( 'Request Organization Access', 'labgenz-community-management' ),
						'pending'  => __( 'Request Pending', 'labgenz-community-management' ),
						'approved' => __( 'Organization Access', 'labgenz-community-management' ),
						'rejected' => __( 'Submit New Request', 'labgenz-community-management' ),
					],
				],
			]
		);
	}

	/**
	 * Add header button
	 *
	 * @return void
	 */
	public function add_header_button(): void {
		// Only show on frontend
		if ( is_admin() ) {
			return;
		}

		// Check if user should see the button
		if ( ! $this->should_show_button() ) {
			return;
		}

		?>
		<script>
		jQuery(document).ready(function($) {
			// Get user status to determine button text and behavior
			var userStatus = '<?php echo is_user_logged_in() ? $this->org_access->get_user_request_status( get_current_user_id() ) : ''; ?>';
			var buttonText = '<?php _e( 'Request Organization Access', 'labgenz-community-management' ); ?>';
			var statusDisplay = '';
			
			// Determine button text based on user status
			if (userStatus === 'pending') {
				buttonText = '<?php _e( 'Update Request', 'labgenz-community-management' ); ?>';
				// statusDisplay = '<small style="font-size: 10px; color: #f0ad4e; margin-left: 5px;">(Pending)</small>';
			} else if (userStatus === 'approved') {
				buttonText = '<?php _e( 'Organization Access', 'labgenz-community-management' ); ?>';
				// statusDisplay = '<small style="font-size: 10px; color: #5cb85c; margin-left: 5px;">(Approved)</small>';
			} else if (userStatus === 'rejected') {
				buttonText = '<?php _e( 'Submit New Request', 'labgenz-community-management' ); ?>';
				// statusDisplay = '<small style="font-size: 10px; color: #d9534f; margin-left: 5px;">(Rejected)</small>';
			} else {
				statusDisplay = '';
			}
			
			// Create button HTML that matches the existing menu structure
			var buttonHtml = '<li id="menu-item-org-access" class="menu-item menu-item-type-custom menu-item-object-custom no-icon">' +
				'<a href="#" id="labgenz-org-access-btn" class="labgenz-org-access-button">' +
				'<span>' + buttonText + '</span>' + statusDisplay +
				'</a>' +
				'</li>';
			
			// Target the specific primary menu first
			var $primaryMenu = $('#primary-menu.primary-menu');
			if ($primaryMenu.length > 0) {
				// Insert before the last menu item to avoid the collapsed section
				var $lastMenuItem = $primaryMenu.find('> li').last();
				if ($lastMenuItem.length > 0) {
					$lastMenuItem.before(buttonHtml);
				} else {
					$primaryMenu.append(buttonHtml);
				}
			} else {
				// Fallback to other menu selectors
				var headerSelectors = [
					'ul.primary-menu',
					'.primary-menu',
					'#site-header .menu-main-menu-container ul',
					'.site-header .menu-main-menu-container ul',
					'#site-header nav ul',
					'.site-header nav ul',
					'#header nav ul',
					'.header nav ul'
				];
				
				// Try to find the best place to insert the button
				for (var i = 0; i < headerSelectors.length; i++) {
					var $target = $(headerSelectors[i]);
					if ($target.length > 0) {
						$target.append(buttonHtml);
						break;
					}
				}
				
				// If no menu found, add as floating button
				if ($('#labgenz-org-access-btn').length === 0) {
					var floatingButtonHtml = '<button id="labgenz-org-access-btn" class="labgenz-org-access-button">' +
						'<span>' + buttonText + '</span>' + statusDisplay +
						'</button>';
					$('body').append('<div id="labgenz-org-access-floating">' + floatingButtonHtml + '</div>');
				}
			}
		});
		</script>
		<?php
	}

	/**
	 * Add request form modal to footer
	 *
	 * @return void
	 */
	public function add_request_form_modal(): void {
		// Only show on frontend
		if ( is_admin() ) {
			return;
		}

		?>
		<div id="labgenz-org-access-form-container" style="display: none;">
			<?php echo $this->get_request_form_html(); ?>
		</div>
		<?php
	}

	/**
	 * Get request form HTML
	 *
	 * @return string
	 */
	private function get_request_form_html(): string {
		ob_start();
		?>
		<form id="labgenz-org-access-form" class="labgenz-org-access-form">
			<div class="form-group">
				<label for="organization_name" class="required">
					<?php _e( 'Organization Name', 'labgenz-community-management' ); ?> <span class="required-asterisk">*</span>
				</label>
				<input type="text" id="organization_name" name="organization_name" required maxlength="255">
			</div>

			<div class="form-group">
				<label for="organization_type" class="required">
					<?php _e( 'Organization Type', 'labgenz-community-management' ); ?> <span class="required-asterisk">*</span>
				</label>
				<select id="organization_type" name="organization_type" required>
					<option value=""><?php _e( 'Select Type', 'labgenz-community-management' ); ?></option>
					<option value="non-profit"><?php _e( 'Non-Profit', 'labgenz-community-management' ); ?></option>
					<option value="business"><?php _e( 'Business', 'labgenz-community-management' ); ?></option>
					<option value="educational"><?php _e( 'Educational', 'labgenz-community-management' ); ?></option>
					<option value="government"><?php _e( 'Government', 'labgenz-community-management' ); ?></option>
					<option value="community"><?php _e( 'Community Group', 'labgenz-community-management' ); ?></option>
					<option value="other"><?php _e( 'Other', 'labgenz-community-management' ); ?></option>
				</select>
			</div>

			<div class="form-group">
				<label for="description" class="required">
					<?php _e( 'Organization Description', 'labgenz-community-management' ); ?> <span class="required-asterisk">*</span>
				</label>
				<textarea id="description" name="description" required maxlength="1000" rows="4" 
							placeholder="<?php _e( 'Briefly describe your organization and its mission...', 'labgenz-community-management' ); ?>"></textarea>
			</div>

			<div class="form-group">
				<label for="website">
					<?php _e( 'Website URL', 'labgenz-community-management' ); ?>
				</label>
				<input type="url" id="website" name="website" placeholder="https://example.com">
			</div>

			<div class="form-group">
				<label for="contact_email" class="required">
					<?php _e( 'Contact Email', 'labgenz-community-management' ); ?> <span class="required-asterisk">*</span>
				</label>
				<input type="email" id="contact_email" name="contact_email" required>
			</div>

			<div class="form-group">
				<label for="phone">
					<?php _e( 'Phone Number', 'labgenz-community-management' ); ?>
				</label>
				<input type="tel" id="phone" name="phone">
			</div>

			<div class="form-group">
				<label for="justification" class="required">
					<?php _e( 'Justification', 'labgenz-community-management' ); ?> <span class="required-asterisk">*</span>
				</label>
				<textarea id="justification" name="justification" required maxlength="1000" rows="4" 
							placeholder="<?php _e( 'Please explain why you need organization access and how you plan to use it...', 'labgenz-community-management' ); ?>"></textarea>
			</div>

			<div class="form-actions">
				<button type="submit" class="submit-button">
					<?php _e( 'Submit Request', 'labgenz-community-management' ); ?>
				</button>
				<button type="button" class="cancel-button">
					<?php _e( 'Cancel', 'labgenz-community-management' ); ?>
				</button>
			</div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check if the organization access button should be shown
	 *
	 * @return bool
	 */
	private function should_show_button(): bool {
		// Don't show if user is not logged in
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id = get_current_user_id();

		if ( ! $this->subscription_handler->user_has_organization_subscription( $user_id ) ) {
			return false;
		}

		$user_status = $this->org_access->get_user_request_status( $user_id );

		// Don't show if user already has an approved request
		if ( $user_status === OrganizationAccess::STATUS_APPROVED ) {
			return false;
		}

		// Check if user is a member or organizer of a group with group type 'organization'
		if ( function_exists( 'groups_get_user_groups' ) && function_exists( 'bp_groups_get_group_type' ) ) {
			$user_groups = groups_get_user_groups( $user_id );
			if ( ! empty( $user_groups['groups'] ) ) {
				foreach ( $user_groups['groups'] as $group_id ) {
					$group_type = bp_groups_get_group_type( $group_id );
					if ( $group_type === 'organization' ) {
						// Check if user is organizer or member
						if ( ( function_exists( 'groups_is_user_admin' ) && groups_is_user_admin( $user_id, $group_id ) ) ||
							 ( function_exists( 'groups_is_user_member' ) && groups_is_user_member( $user_id, $group_id ) ) ) {
							return false;
						}
					}
				}
			}
		}

		// Show button for users with no request, pending, or rejected status
		return true;
	}

	/**
	 * Get user's current request status for display
	 *
	 * @return array|null
	 */
	public function get_user_request_info(): ?array {
		if ( ! is_user_logged_in() ) {
			return null;
		}

		$user_id      = get_current_user_id();
		$status       = $this->org_access->get_user_request_status( $user_id );
		$request_data = $this->org_access->get_user_request_data( $user_id );

		if ( ! $status || ! $request_data ) {
			return null;
		}

		return [
			'status'       => $status,
			'request_data' => $request_data,
			'status_label' => $this->get_status_label( $status ),
		];
	}

	/**
	 * Get status label for display
	 *
	 * @param string $status Status
	 * @return string
	 */
	private function get_status_label( string $status ): string {
		switch ( $status ) {
			case OrganizationAccess::STATUS_PENDING:
				return __( 'Pending Review', 'labgenz-community-management' );
			case OrganizationAccess::STATUS_APPROVED:
				return __( 'Approved', 'labgenz-community-management' );
			case OrganizationAccess::STATUS_REJECTED:
				return __( 'Rejected', 'labgenz-community-management' );
			default:
				return __( 'Unknown', 'labgenz-community-management' );
		}
	}

	/**
	 * Add shortcode for request status display
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public function request_status_shortcode( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'show_button' => 'true',
				'show_status' => 'true',
			],
			$atts
		);

		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'You must be logged in to view your organization access status.', 'labgenz-community-management' ) . '</p>';
		}

		$request_info = $this->get_user_request_info();

		ob_start();
		?>
		<div class="labgenz-org-access-status">
			<?php if ( $request_info && $atts['show_status'] === 'true' ) : ?>
				<div class="request-status">
					<h4><?php _e( 'Organization Access Request Status', 'labgenz-community-management' ); ?></h4>
					<p class="status-badge status-<?php echo esc_attr( $request_info['status'] ); ?>">
						<?php echo esc_html( $request_info['status_label'] ); ?>
					</p>
					<p><strong><?php _e( 'Organization:', 'labgenz-community-management' ); ?></strong> <?php echo esc_html( $request_info['request_data']['organization_name'] ); ?></p>
					<p><strong><?php _e( 'Submitted:', 'labgenz-community-management' ); ?></strong> <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $request_info['request_data']['requested_at'] ) ) ); ?></p>
				</div>
			<?php endif; ?>
			
			<?php if ( $atts['show_button'] === 'true' && $this->should_show_button() ) : ?>
				<div class="menu-item-style-wrapper">
					<a href="#" id="labgenz-org-access-btn" class="labgenz-org-access-button">
						<span>
							<?php
							if ( $request_info && $request_info['status'] === OrganizationAccess::STATUS_REJECTED ) {
								_e( 'Submit New Request', 'labgenz-community-management' );
							} elseif ( $request_info && $request_info['status'] === OrganizationAccess::STATUS_PENDING ) {
								_e( 'Update Request', 'labgenz-community-management' );
							} elseif ( $request_info && $request_info['status'] === OrganizationAccess::STATUS_APPROVED ) {
								_e( 'Organization Access', 'labgenz-community-management' );
							} else {
								_e( 'Request Organization Access', 'labgenz-community-management' );
							}
							?>
						</span>
						<?php if ( $request_info ) : ?>
							<?php if ( $request_info['status'] === OrganizationAccess::STATUS_PENDING ) : ?>
								<small style="font-size: 10px; color: #f0ad4e; margin-left: 5px;">(Pending)</small>
							<?php elseif ( $request_info['status'] === OrganizationAccess::STATUS_APPROVED ) : ?>
								<small style="font-size: 10px; color: #5cb85c; margin-left: 5px;">(Approved)</small>
							<?php elseif ( $request_info['status'] === OrganizationAccess::STATUS_REJECTED ) : ?>
								<small style="font-size: 10px; color: #d9534f; margin-left: 5px;">(Rejected)</small>
							<?php endif; ?>
						<?php else : ?>
							<!-- No status indicator for default state -->
						<?php endif; ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
