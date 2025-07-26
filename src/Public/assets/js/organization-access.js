/**
 * Organization Access Frontend JavaScript
 *
 * Handles the frontend functionality for organization access requests
 */

(function ($) {
	'use strict';

	// Initialize when DOM is ready
	$( document ).ready(
		function () {
			new OrganizationAccessHandler();
		}
	);

	/**
	 * Organization Access Handler Class
	 */
	function OrganizationAccessHandler() {
		this.init();
	}

	OrganizationAccessHandler.prototype = {
		/**
		 * Initialize the handler
		 */
		init: function () {
			this.bindEvents();
			this.checkUserStatus();
		},

		/**
		 * Bind event listeners
		 */
		bindEvents: function () {
			// Button click handler
			$( document ).on( 'click', '#labgenz-org-access-btn', this.handleButtonClick.bind( this ) );

			// Form submission handler
			$( document ).on( 'submit', '#labgenz-org-access-form', this.handleFormSubmit.bind( this ) );

			// Form cancel handler
			$( document ).on( 'click', '.cancel-button', this.handleCancel.bind( this ) );

			// Form validation
			$( document ).on(
				'input change',
				'#labgenz-org-access-form input, #labgenz-org-access-form textarea, #labgenz-org-access-form select',
				function () {
					var $form = $( this ).closest( 'form' );
					this.validateForm( $form );
				}.bind( this )
			);
		},

		/**
		 * Check user status and update button accordingly
		 */
		checkUserStatus: function () {
			if ( ! labgenz_org_access.is_logged_in) {
				return;
			}

			var $button = $( '#labgenz-org-access-btn' );
			if ($button.length === 0) {
				return;
			}

			var status      = labgenz_org_access.user_status;
			var $buttonText = $button.find( 'span' );

			// Remove all status classes
			$button.removeClass( 'status-pending status-approved status-rejected' );

			// Update button text and status based on current status
			switch (status) {
				case 'pending':
					$button.addClass( 'status-pending' );
					if ($buttonText.length > 0) {
						$buttonText.text( labgenz_org_access.strings.button_texts.pending );
					}
					break;
				case 'approved':
					$button.addClass( 'status-approved' );
					if ($buttonText.length > 0) {
						$buttonText.text( labgenz_org_access.strings.button_texts.approved );
					}
					break;
				case 'rejected':
					$button.addClass( 'status-rejected' );
					if ($buttonText.length > 0) {
						$buttonText.text( labgenz_org_access.strings.button_texts.rejected );
					}
					break;
				default:
					if ($buttonText.length > 0) {
						$buttonText.text( labgenz_org_access.strings.button_texts.default );
					}
					break;
			}
		},

		/**
		 * Handle button click
		 */
		handleButtonClick: function (e) {
			e.preventDefault();

			if ( ! labgenz_org_access.is_logged_in) {
				this.showMessage( 'error', labgenz_org_access.strings.login_required );
				return;
			}

			var status = labgenz_org_access.user_status;

			if (status === 'pending') {
				this.showMessage( 'info', labgenz_org_access.strings.already_pending );
				return;
			}

			if (status === 'approved') {
				this.showMessage( 'success', labgenz_org_access.strings.already_approved );
				return;
			}

			this.showRequestForm();
		},

		/**
		 * Show request form using SweetAlert
		 */
		showRequestForm: function () {
			var formHtml = $( '#labgenz-org-access-form-container' ).html();

			Swal.fire(
				{
					title: labgenz_org_access.strings.form_title,
					html: formHtml,
					width: '600px',
					showCancelButton: false,
					showConfirmButton: false,
					cancelButtonText: labgenz_org_access.strings.cancel_button,
					customClass: {
						container: 'labgenz-org-access-modal',
						popup: 'labgenz-org-access-popup'
					},
					didOpen: function () {
						// Focus on first input
						$( '#organization_name' ).focus();
					}
				}
			);
		},

		/**
		 * Handle form submission
		 */
		handleFormSubmit: function (e) {
			e.preventDefault();

			var $form = $( e.target );

			if ( ! this.validateForm( $form )) {
				this.showMessage( 'error', labgenz_org_access.strings.validation_error );
				return;
			}

			var $submitButton = $form.find( 'button[type="submit"]' );
			var originalText  = $submitButton.text();

			// Disable submit button and show loading
			$submitButton.prop( 'disabled', true ).text( labgenz_org_access.strings.submitting );

			var formData = {
				action: 'labgenz_submit_org_access_request',
				nonce: labgenz_org_access.nonce,
				organization_name: $form.find( '#organization_name' ).val(),
				organization_type: $form.find( '#organization_type' ).val(),
				description: $form.find( '#description' ).val(),
				website: $form.find( '#website' ).val(),
				contact_email: $form.find( '#contact_email' ).val(),
				phone: $form.find( '#phone' ).val(),
				justification: $form.find( '#justification' ).val()
			};

			$.ajax(
				{
					url: labgenz_org_access.ajax_url,
					type: 'POST',
					data: formData,
					success: function (response) {
						if (response.success) {
							Swal.fire(
								{
									icon: 'success',
									title: 'Success!',
									text: response.data.message,
									confirmButtonText: 'OK'
								}
							).then(
								function () {
									// Update button status instead of full page reload
									this.updateButtonStatus('pending');
									
									// Optionally refresh the page to ensure all elements are updated
									// location.reload();
								}.bind(this)
							);
						} else {
							this.showMessage( 'error', response.data.message );
							$submitButton.prop( 'disabled', false ).text( originalText );
						}
					}.bind( this ),
					error: function (xhr, status, error) {
						this.showMessage( 'error', labgenz_org_access.strings.error );
						$submitButton.prop( 'disabled', false ).text( originalText );
					}.bind( this )
				}
			);
		},

		/**
		 * Handle form cancel
		 */
		handleCancel: function (e) {
			e.preventDefault();
			Swal.close();
		},

		/**
		 * Validate form
		 */
		validateForm: function ($form) {
			var isValid = true;

			// Use the passed form or find it in the document
			if ( ! $form) {
				$form = $( '#labgenz-org-access-form' );
			}

			// Remove previous error styles
			$form.find( '.error' ).removeClass( 'error' );

			// Check required fields
			$form.find( '[required]' ).each(
				function () {
					var $field = $( this );
					var value  = $field.val().trim();

					console.log( 'Validating field:', $field.attr( 'id' ), 'Value:', value );

					if ( ! value) {
						$field.addClass( 'error' );
						isValid = false;
						console.log( 'Field is empty:', $field.attr( 'id' ) );
					}
				}
			);

			// Validate email format only if email is provided
			var email = $form.find( '#contact_email' ).val().trim();
			if (email) {
				if ( ! this.isValidEmail( email )) {
					$form.find( '#contact_email' ).addClass( 'error' );
					isValid = false;
					console.log( 'Invalid email format:', email );
				}
			}

			// Validate website URL format only if website is provided
			var website = $form.find( '#website' ).val().trim();
			if (website) {
				if ( ! this.isValidUrl( website )) {
					$form.find( '#website' ).addClass( 'error' );
					isValid = false;
					console.log( 'Invalid website URL:', website );
				}
			}

			console.log( 'Form validation result:', isValid );
			return isValid;
		},

		/**
		 * Check if email is valid
		 */
		isValidEmail: function (email) {
			var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return emailRegex.test( email );
		},

		/**
		 * Check if URL is valid
		 */
		isValidUrl: function (url) {
			try {
				new URL( url );
				return true;
			} catch (e) {
				return false;
			}
		},

		/**
		 * Update button status after form submission
		 */
		updateButtonStatus: function(newStatus) {
			var $button = $( '#labgenz-org-access-btn' );
			if ($button.length === 0) {
				return;
			}

			var $buttonText = $button.find( 'span' );
			var $statusSmall = $button.find( 'small' );

			// Remove all status classes
			$button.removeClass( 'status-pending status-approved status-rejected' );

			// Update button text and status based on new status
			switch (newStatus) {
				case 'pending':
					$button.addClass( 'status-pending' );
					if ($buttonText.length > 0) {
						$buttonText.text( labgenz_org_access.strings.button_texts.pending );
					}
					if ($statusSmall.length > 0) {
						$statusSmall.text('(Pending)').css('color', '#f0ad4e');
					}
					break;
				case 'approved':
					$button.addClass( 'status-approved' );
					if ($buttonText.length > 0) {
						$buttonText.text( labgenz_org_access.strings.button_texts.approved );
					}
					if ($statusSmall.length > 0) {
						$statusSmall.text('(Approved)').css('color', '#5cb85c');
					}
					break;
				case 'rejected':
					$button.addClass( 'status-rejected' );
					if ($buttonText.length > 0) {
						$buttonText.text( labgenz_org_access.strings.button_texts.rejected );
					}
					if ($statusSmall.length > 0) {
						$statusSmall.text('(Rejected)').css('color', '#d9534f');
					}
					break;
				default:
					if ($buttonText.length > 0) {
						$buttonText.text( labgenz_org_access.strings.button_texts.default );
					}
					if ($statusSmall.length > 0) {
						$statusSmall.text('').css('color', '');
					}
					break;
			}
		},

		/**
		 * Show message using SweetAlert
		 */
		showMessage: function (type, message) {
			var icon = type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info');

			Swal.fire(
				{
					icon: icon,
					title: type.charAt( 0 ).toUpperCase() + type.slice( 1 ),
					text: message,
					confirmButtonText: 'OK'
				}
			);
		}
	};

})( jQuery );
