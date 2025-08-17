jQuery( document ).ready(
	function ($) {
		// Tab switching functionality
		function switchTab(targetStep) {
			$( '.step-tab' ).removeClass( 'active' ).filter( '[data-step="' + targetStep + '"]' ).addClass( 'active' );
			$( '.tab-content' ).removeClass( 'active' ).filter( '#' + targetStep + '-content' ).addClass( 'active' );
		}

		$( '.step-tab' ).on(
			'click',
			function () {
				switchTab( $( this ).data( 'step' ) );
			}
		);
		$( '.next-step' ).on(
			'click',
			function () {
				switchTab( $( this ).data( 'next' ) );
			}
		);
		$( '.prev-step' ).on(
			'click',
			function () {
				switchTab( $( this ).data( 'prev' ) );
			}
		);

		// Logo upload preview
		$( '#organization-logo' ).on(
			'change',
			function (e) {
				var file = e.target.files[0];
				if (file) {
					var reader    = new FileReader();
					reader.onload = function (e) {
						$( '#logo-preview' ).html( '<img src="' + e.target.result + '" alt="Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">' );
					};
					reader.readAsDataURL( file );
				}
			}
		);

		// Pricing plan selection
		$( '.select-plan-btn' ).on(
			'click',
			function () {
				var plan = $( this ).data( 'plan' );
				$( '.pricing-card' ).removeClass( 'selected active' );
				$( this ).closest( '.pricing-card' ).addClass( 'selected active' );
				$( '#selected-plan' ).val( plan );
				$( '#pricing-next-btn' ).prop( 'disabled', false );

				// Update button text
				$( '.select-plan-btn' ).text( 'Select Plan' ).removeClass( 'selected' );
				$( this ).text( 'Selected' ).addClass( 'selected' );
			}
		);

		// Categories functionality
		var $searchInput        = $( '#categories-search' );
		var $filterButtons      = $( '.filter-btn' );
		var $categoryCards      = $( '.category-card' );
		var $categoryCheckboxes = $( '.category-checkbox' );
		var $selectedPreview    = $( '#selected-preview' );
		var $selectedList       = $( '#selected-list' );
		var $selectedCount      = $( '#selected-count' );

		// Search functionality
		$searchInput.on(
			'input',
			function () {
				var searchTerm = $( this ).val().toLowerCase();

				$categoryCards.each(
					function () {
						var $card               = $( this );
						var categoryName        = $card.find( '.category-name' ).text().toLowerCase();
						var categoryDescription = $card.find( '.category-description' ).text().toLowerCase();

						if (categoryName.includes( searchTerm ) || categoryDescription.includes( searchTerm )) {
							$card.show();
						} else {
							$card.hide();
						}
					}
				);
			}
		);

		// Filter functionality
		$filterButtons.on(
			'click',
			function () {
				var $this = $( this );
				$filterButtons.removeClass( 'active' );
				$this.addClass( 'active' );

				var filter = $this.data( 'filter' );

				$categoryCards.each(
					function () {
						var $card    = $( this );
						var checkbox = $card.find( '.category-checkbox' )[0];

						if (filter === 'all') {
							$card.show();
						} else if (filter === 'selected') {
							$card.toggle( checkbox.checked );
						}
					}
				);
			}
		);

		// Category selection
		$categoryCheckboxes.on(
			'change',
			function () {
				var $card = $( this ).closest( '.category-card' );

				if (this.checked) {
					$card.addClass( 'selected' );
				} else {
					$card.removeClass( 'selected' );
				}

				updateSelectedPreview();
			}
		);

		// Update selected categories preview
		function updateSelectedPreview() {
			var selectedCategories = $categoryCheckboxes.filter( ':checked' );

			$selectedCount.text( selectedCategories.length );

			if (selectedCategories.length > 0) {
				$selectedPreview.show();

				var tagsHtml = selectedCategories.map(
					function () {
						var checkbox     = this;
						var categoryName = $( checkbox ).next( '.category-label' ).find( '.category-name' ).text();

						return '<div class="selected-category-tag">' +
						categoryName +
						'<button type="button" class="remove-btn" data-category="' + checkbox.value + '">' +
						'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor">' +
						'<line x1="18" y1="6" x2="6" y2="18"/>' +
						'<line x1="6" y1="6" x2="18" y2="18"/>' +
						'</svg>' +
						'</button>' +
						'</div>';
					}
				).get().join( '' );

				$selectedList.html( tagsHtml );

				// Add remove functionality
				$selectedList.find( '.remove-btn' ).on(
					'click',
					function () {
						var categoryId = $( this ).data( 'category' );
						var checkbox   = $( 'input[value="' + categoryId + '"]' )[0];

						if (checkbox) {
							checkbox.checked = false;
							$( checkbox ).trigger( 'change' );
						}
					}
				);
			} else {
				$selectedPreview.hide();
			}
		}

		// Card click to toggle checkbox
		$categoryCards.on(
			'click',
			function (e) {
				if ($( e.target ).hasClass( 'remove-btn' ) || $( e.target ).closest( '.remove-btn' ).length) {
					return;
				}

				var checkbox     = $( this ).find( '.category-checkbox' )[0];
				checkbox.checked = ! checkbox.checked;
				$( checkbox ).trigger( 'change' );
			}
		);

		// AJAX Form submission
		$( '#create-organization-form' ).on(
			'submit',
			function (e) {
				e.preventDefault();
				$( '.form-error, .success-message, .error-message' ).remove();

				var orgName            = $( '#organization-name' ).val().trim();
				var selectedPlan       = $( '#selected-plan' ).val();
				var selectedCategories = $categoryCheckboxes.filter( ':checked' ).length;
				var hasErrors          = false;

				if ( ! orgName) {
					showFieldError( 'organization-name', 'Please enter your organization name.' );
					switchTab( 'details' );
					hasErrors = true;
				}
				if ( ! selectedPlan) {
					showMessage( 'Please select a pricing plan.', 'error' );
					switchTab( 'pricing' );
					hasErrors = true;
				}

				if (hasErrors) {
					return;
				}

				$( '#loading-overlay' ).addClass( 'show' );
				var formData = new FormData( this );
				formData.append( 'action', 'create_organization_request' );

				$.ajax(
					{
						url: typeof lab_group_create_form_data !== 'undefined' ? lab_group_create_form_data.ajax_url : '',
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						dataType: 'json',
						success: function (data) {
							$( '#loading-overlay' ).removeClass( 'show' );
							if (data.success) {
								showMessage( data.data.message, 'success' );
								setTimeout(
									function () {
										window.location.href = data.data.checkout_url;
									},
									1500
								);
							} else {
								showMessage( data.data.message);
							}
						},
						error: function (xhr, status, error) {
							$( '#loading-overlay' ).removeClass( 'show' );
							showMessage( 'An unexpected error occurred. Please try again.', 'error' );
						}
					}
				);
			}
		);

		function showFieldError(fieldId, message) {
			var $field   = $( '#' + fieldId );
			var $errorEl = $( '<div class="form-error"></div>' ).text( message );
			$field.parent().append( $errorEl );
		}

		function showMessage(message, type) {
			var $messageEl = $( '<div></div>' ).addClass( type === 'success' ? 'success-message' : 'error-message' ).text( message );
			var $form      = $( '#create-organization-form' );
			$form.prepend( $messageEl );
			$form[0].scrollIntoView( { behavior: 'smooth' } );
		}
	}
);