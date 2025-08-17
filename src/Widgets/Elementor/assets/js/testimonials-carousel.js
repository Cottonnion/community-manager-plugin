(function ($) {
	'use strict';

	var TestimonialsCarousel = function ($element) {
		this.$element         = $element;
		this.$wrapper         = $element.find( '.mlmmc-testimonials-wrapper' );
		this.$items           = $element.find( '.mlmmc-testimonial-item' );
		this.$arrows          = $element.find( '.mlmmc-testimonial-arrow' );
		this.$dots            = $element.find( '.mlmmc-testimonial-dots .dot' );
		this.slidesPerView    = parseInt( $element.data( 'slides-per-view' ) ) || 1;
		this.showArrows       = $element.data( 'show-arrows' ) === 'true';
		this.showDots         = $element.data( 'show-dots' ) === 'true';
		this.autoplay         = $element.data( 'autoplay' ) === 'true';
		this.autoplaySpeed    = parseInt( $element.data( 'autoplay-speed' ) ) || 5000;
		this.currentIndex     = 0;
		this.itemCount        = this.$items.length;
		this.itemWidth        = 100 / this.slidesPerView;
		this.autoplayInterval = null;

		this.init();
	};

	TestimonialsCarousel.prototype = {
		init: function () {
			var self = this;

			// Set item widths
			this.$items.css( 'width', this.itemWidth + '%' );

			// Bind arrow clicks
			if (this.showArrows) {
				this.$arrows.on(
					'click',
					function () {
						var direction = $( this ).hasClass( 'prev' ) ? 'prev' : 'next';
						self.navigate( direction );
					}
				);
			}

			// Bind dot clicks
			if (this.showDots) {
				this.$dots.on(
					'click',
					function () {
						var index = $( this ).data( 'index' );
						self.goToSlide( index );
					}
				);
			}

			// Start autoplay if enabled
			if (this.autoplay) {
				this.startAutoplay();

				// Pause on hover
				this.$element.on(
					'mouseenter',
					function () {
						self.stopAutoplay();
					}
				).on(
					'mouseleave',
					function () {
						self.startAutoplay();
					}
				);
			}

			// Responsive handling
			$( window ).on(
				'resize',
				function () {
					self.updateSlidePosition();
				}
			);

			// Initial slide position
			this.updateSlidePosition();
		},

		navigate: function (direction) {
			console.log( 'Navigating:', direction );
			if (direction === 'prev') {
				this.currentIndex = (this.currentIndex > 0) ? this.currentIndex - 1 : this.itemCount - this.slidesPerView;
			} else {
				this.currentIndex = (this.currentIndex < this.itemCount - this.slidesPerView) ? this.currentIndex + 1 : 0;
			}
			console.log( 'Current Index after navigation:', this.currentIndex );
			this.updateSlidePosition();
		},

		goToSlide: function (index) {
			console.log( 'Going to slide:', index );
			if (index >= 0 && index < this.itemCount) {
				this.currentIndex = index;
				this.updateSlidePosition();
			}
		},

		updateSlidePosition: function () {
			console.log( 'Updating slide position. Current Index:', this.currentIndex );
			// Update transform position
			var position = -this.currentIndex * this.itemWidth;
			console.log( 'Calculated position:', position );
			this.$wrapper.css( 'transform', 'translateX(' + position + '%)' );

			// Update active dot
			if (this.showDots) {
				this.$dots.removeClass( 'active' );
				this.$dots.eq( this.currentIndex ).addClass( 'active' );
			}
		},

		startAutoplay: function () {
			var self              = this;
			this.autoplayInterval = setInterval(
				function () {
					self.navigate( 'next' );
				},
				this.autoplaySpeed
			);
		},

		stopAutoplay: function () {
			if (this.autoplayInterval) {
				clearInterval( this.autoplayInterval );
				this.autoplayInterval = null;
			}
		}
	};

	// Initialize all carousels
	$( window ).on(
		'elementor/frontend/init',
		function () {
			elementorFrontend.hooks.addAction(
				'frontend/element_ready/mlmmc_testimonials_carousel.default',
				function ($element) {
					new TestimonialsCarousel( $element );
				}
			);
		}
	);

})( jQuery );
