<?php
declare(strict_types=1);

namespace LABGENZ_CM\Widgets\Elementor;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Elementor Integration Handler
 * 
 * Manages integration with Elementor plugin, including:
 * - Checking if Elementor is active
 * - Registering widgets
 * - Registering widget categories
 * - Loading widget assets
 * 
 * @package    Labgenz_Community_Management
 * @subpackage Labgenz_Community_Management/Widgets/Elementor
 */
class ElementorIntegration {
    /**
     * Instance of this class
     *
     * @var self|null
     */
    private static ?self $instance = null;
    
    /**
     * Whether Elementor is active
     *
     * @var bool
     */
    private bool $is_elementor_active = false;
    
    /**
     * Get class instance | singleton pattern
     *
     * @return self
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct creation
     */
    private function __construct() {
        // Cast the return value of did_action to a boolean to ensure type compatibility
        $this->is_elementor_active = (bool) did_action('elementor/loaded');
    }
    
    /**
     * Initialize the integration
     * 
     * @return void
     */
    public function init(): void {
        if (!$this->is_elementor_active) {
            return;
        }
        
        // Register widget category
        add_action('elementor/elements/categories_registered', [$this, 'register_widget_category']);
        
        // Register widgets
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        
        // Register scripts and styles
        add_action('elementor/frontend/after_register_scripts', [$this, 'register_frontend_scripts']);
        add_action('elementor/frontend/after_register_styles', [$this, 'register_frontend_styles']);
        
        // Register editor scripts and styles
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_editor_scripts']);
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_editor_styles']);
    }
    
    /**
     * Register widget category
     * 
     * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
     * @return void
     */
    public function register_widget_category($elements_manager): void {
        $elements_manager->add_category(
            'labgenz-widgets',
            [
                'title' => __('Labgenz Widgets', 'labgenz-community-management'),
                'icon' => 'fa fa-plug',
            ]
        );
    }
    
    /**
     * Register widgets
     * 
     * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
     * @return void
     */
    public function register_widgets($widgets_manager): void {
        // Make sure the base class exists (Elementor is active)
        if (!class_exists('\\Elementor\\Widget_Base')) {
            return;
        }
        
        // Include widget files
        require_once plugin_dir_path(__FILE__) . 'TestimonialsCarouselWidget.php';
        require_once plugin_dir_path(__FILE__) . 'PlansComparisonWidget.php';
        
        // Register widgets
        $widgets_manager->register(new TestimonialsCarouselWidget());
        $widgets_manager->register(new PlansComparisonWidget());
    }
    
/**
 * Register frontend scripts
 * 
 * @return void
 */
public function register_frontend_scripts(): void {
    // Define the widgets asset URL
    $widgets_url = LABGENZ_CM_URL . 'src/Widgets/Elementor/assets/';
    
    // Testimonials Carousel JS
    wp_register_script(
        'mlmmc-testimonials-carousel-js',
        $widgets_url . 'js/testimonials-carousel.js',
        ['jquery'],
        '1.0.1',
        true
    );
}    /**
     * Register frontend styles
     * 
     * @return void
     */
    public function register_frontend_styles(): void {
        // Define widgets asset URL for better code reuse
        $widgets_url = LABGENZ_CM_URL . 'src/Widgets/Elementor/assets/';
        
        // Testimonials Carousel CSS
        wp_register_style(
            'mlmmc-testimonials-carousel-css',
            $widgets_url . 'css/testimonials-carousel.css',
            [],
            '1.0.1'
        );
        
        // Plans Comparison Table CSS
        wp_register_style(
            'mlmmc-plans-comparison-css',
            $widgets_url . 'plans-comparison-widget.css',
            [],
            '1.0.2'
        );
    }
    
    /**
     * Enqueue editor scripts
     * 
     * @return void
     */
    public function enqueue_editor_scripts(): void {
        // Add editor scripts here if needed
    }
    
    /**
     * Enqueue editor styles
     * 
     * @return void
     */
    public function enqueue_editor_styles(): void {
        // Add editor styles here if needed
    }
}
