<?php
namespace LABGENZ_CM\Widgets\Elementor;

final class WidgetsManager {

	public function __construct() {
		add_action( 'elementor/elements/categories_registered', [ $this, 'register_widget_categories' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
	}

	public function register_widget_categories( $elements_manager ) {
		$elements_manager->add_category(
			'labgenz-widgets',
			[
				'title' => __( 'Labgenz Widgets', 'labgenz-community-management' ),
				'icon'  => 'fa fa-plug',
			]
		);
	}

	public function register_widgets( $widgets_manager ) {
		// Make sure the base class exists (Elementor is active)
		if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
			return;
		}

		// Require widget class
		require_once __DIR__ . '/TestimonialsCarouselWidget.php';

		// Register the widget
		$widgets_manager->register( new TestimonialsCarouselWidget() );
	}
}
