<?php
namespace LABGENZ_CM\Widgets\Elementor;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Repeater;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Check if Elementor is active
if (!class_exists('\\Elementor\\Widget_Base')) {
    return;
}

class TestimonialsCarouselWidget extends Widget_Base {
    
    public function get_name() {
        return 'mlmmc_testimonials_carousel';
    }
    
    public function get_title() {
        return __('MLMMC Testimonials Carousel', 'labgenz-community-management');
    }
    
    public function get_icon() {
        return 'eicon-testimonial-carousel';
    }
    
    public function get_categories() {
        return ['labgenz-widgets'];
    }
    
    public function get_script_depends() {
        return ['mlmmc-testimonials-carousel-js'];
    }
    
    public function get_style_depends() {
        return ['mlmmc-testimonials-carousel-css'];
    }
    
    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Testimonials', 'labgenz-community-management'),
            ]
        );
        
        $repeater = new Repeater();
        
        $repeater->add_control(
            'testimonial_image',
            [
                'label' => __('Image', 'labgenz-community-management'),
                'type' => Controls_Manager::MEDIA,
                'default' => [
                    'url' => \Elementor\Utils::get_placeholder_image_src(),
                ],
            ]
        );
        
        $repeater->add_control(
            'testimonial_name',
            [
                'label' => __('Name', 'labgenz-community-management'),
                'type' => Controls_Manager::TEXT,
                'default' => __('John Doe', 'labgenz-community-management'),
            ]
        );
        
        $repeater->add_control(
            'testimonial_position',
            [
                'label' => __('Position', 'labgenz-community-management'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Marketing Director', 'labgenz-community-management'),
            ]
        );
        
        $repeater->add_control(
            'testimonial_content',
            [
                'label' => __('Content', 'labgenz-community-management'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.', 'labgenz-community-management'),
            ]
        );
        
        $repeater->add_control(
            'testimonial_rating',
            [
                'label' => __('Rating', 'labgenz-community-management'),
                'type' => Controls_Manager::SELECT,
                'default' => '5',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                ],
            ]
        );
        
        $this->add_control(
            'testimonials',
            [
                'label' => __('Testimonials Items', 'labgenz-community-management'),
                'type' => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [
                    [
                        'testimonial_name' => __('Michael Thompson', 'labgenz-community-management'),
                        'testimonial_position' => __('Product Manager', 'labgenz-community-management'),
                        'testimonial_content' => __('This service transformed our workflow and increased efficiency dramatically. Highly recommended for any growing team.', 'labgenz-community-management'),
                        'testimonial_rating' => '5',
                    ],
                    [
                        'testimonial_name' => __('Emily Rodriguez', 'labgenz-community-management'),
                        'testimonial_position' => __('Founder & CEO', 'labgenz-community-management'),
                        'testimonial_content' => __('Outstanding support and a seamless user experience. Our clients have never been happier.', 'labgenz-community-management'),
                        'testimonial_rating' => '4',
                    ],
                    [
                        'testimonial_name' => __('David Lee', 'labgenz-community-management'),
                        'testimonial_position' => __('Marketing Strategist', 'labgenz-community-management'),
                        'testimonial_content' => __('The team’s dedication and expertise helped us exceed our goals ahead of schedule.', 'labgenz-community-management'),
                        'testimonial_rating' => '5',
                    ],
                ],

                'title_field' => '{{{ testimonial_name }}}',
            ]
        );
        
        $this->add_responsive_control(
            'slides_per_view',
            [
                'label' => __('Slides Per View', 'labgenz-community-management'),
                'type' => Controls_Manager::SELECT,
                'default' => '1',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                ],
                'condition' => [
                    'layout' => 'carousel',
                ],
            ]
        );
        
        $this->add_control(
            'layout',
            [
                'label' => __('Layout', 'labgenz-community-management'),
                'type' => Controls_Manager::SELECT,
                'default' => 'carousel',
                'options' => [
                    'carousel' => __('Carousel', 'labgenz-community-management'),
                    'grid' => __('Grid', 'labgenz-community-management'),
                ],
            ]
        );
        
        $this->add_control(
            'show_arrows',
            [
                'label' => __('Show Arrows', 'labgenz-community-management'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'layout' => 'carousel',
                ],
            ]
        );
        
        $this->add_control(
            'show_dots',
            [
                'label' => __('Show Dots', 'labgenz-community-management'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'layout' => 'carousel',
                ],
            ]
        );
        
        $this->add_control(
            'autoplay',
            [
                'label' => __('Autoplay', 'labgenz-community-management'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => [
                    'layout' => 'carousel',
                ],
            ]
        );
        
        $this->add_control(
            'autoplay_speed',
            [
                'label' => __('Autoplay Speed', 'labgenz-community-management'),
                'type' => Controls_Manager::NUMBER,
                'default' => 5000,
                'condition' => [
                    'layout' => 'carousel',
                    'autoplay' => 'yes',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - General
        $this->start_controls_section(
            'section_style_general',
            [
                'label' => __('General', 'labgenz-community-management'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'card_background_color',
            [
                'label' => __('Card Background', 'labgenz-community-management'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-item' => 'background-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .mlmmc-testimonial-item',
            ]
        );
        
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_box_shadow',
                'selector' => '{{WRAPPER}} .mlmmc-testimonial-item',
            ]
        );
        
        $this->add_responsive_control(
            'card_padding',
            [
                'label' => __('Padding', 'labgenz-community-management'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'card_border_radius',
            [
                'label' => __('Border Radius', 'labgenz-community-management'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Image
        $this->start_controls_section(
            'section_style_image',
            [
                'label' => __('Image', 'labgenz-community-management'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'image_size',
            [
                'label' => __('Size', 'labgenz-community-management'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 40,
                        'max' => 200,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 80,
                ],
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-image img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'image_border_radius',
            [
                'label' => __('Border Radius', 'labgenz-community-management'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                    '%' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => '%',
                    'size' => 50,
                ],
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-image img' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Content
        $this->start_controls_section(
            'section_style_content',
            [
                'label' => __('Content', 'labgenz-community-management'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'content_color',
            [
                'label' => __('Text Color', 'labgenz-community-management'),
                'type' => Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-content' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'content_typography',
                'selector' => '{{WRAPPER}} .mlmmc-testimonial-content',
            ]
        );
        
        $this->add_responsive_control(
            'content_margin',
            [
                'label' => __('Margin', 'labgenz-community-management'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Name & Position
        $this->start_controls_section(
            'section_style_name_position',
            [
                'label' => __('Name & Position', 'labgenz-community-management'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'name_color',
            [
                'label' => __('Name Color', 'labgenz-community-management'),
                'type' => Controls_Manager::COLOR,
                'default' => '#222222',
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-name' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'name_typography',
                'selector' => '{{WRAPPER}} .mlmmc-testimonial-name',
            ]
        );
        
        $this->add_control(
            'position_color',
            [
                'label' => __('Position Color', 'labgenz-community-management'),
                'type' => Controls_Manager::COLOR,
                'default' => '#666666',
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-position' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'position_typography',
                'selector' => '{{WRAPPER}} .mlmmc-testimonial-position',
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Rating
        $this->start_controls_section(
            'section_style_rating',
            [
                'label' => __('Rating', 'labgenz-community-management'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'star_color',
            [
                'label' => __('Star Color', 'labgenz-community-management'),
                'type' => Controls_Manager::COLOR,
                'default' => '#FFD700',
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-stars' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
            'star_size',
            [
                'label' => __('Star Size', 'labgenz-community-management'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 10,
                        'max' => 50,
                    ],
                    'em' => [
                        'min' => 1,
                        'max' => 5,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 18,
                ],
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-stars' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Navigation
        $this->start_controls_section(
            'section_style_navigation',
            [
                'label' => __('Navigation', 'labgenz-community-management'),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => [
                    'layout' => 'carousel',
                ],
            ]
        );
        
        $this->add_control(
            'arrow_color',
            [
                'label' => __('Arrow Color', 'labgenz-community-management'),
                'type' => Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-arrow' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'show_arrows' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'arrow_size',
            [
                'label' => __('Arrow Size', 'labgenz-community-management'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 20,
                        'max' => 60,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 30,
                ],
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-arrow' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'show_arrows' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'dots_color',
            [
                'label' => __('Dots Color', 'labgenz-community-management'),
                'type' => Controls_Manager::COLOR,
                'default' => '#888888',
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-dots .dot' => 'background-color: {{VALUE}};',
                ],
                'condition' => [
                    'show_dots' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'dots_active_color',
            [
                'label' => __('Dots Active Color', 'labgenz-community-management'),
                'type' => Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .mlmmc-testimonial-dots .dot.active' => 'background-color: {{VALUE}};',
                ],
                'condition' => [
                    'show_dots' => 'yes',
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();
        $testimonials = $settings['testimonials'];
        $layout = $settings['layout'];
        $slides_per_view = $settings['slides_per_view'];
        $show_arrows = $settings['show_arrows'] === 'yes';
        $show_dots = $settings['show_dots'] === 'yes';
        $autoplay = $settings['autoplay'] === 'yes';
        $autoplay_speed = $settings['autoplay_speed'];
        
        if (empty($testimonials)) {
            return;
        }
        
        $this->add_render_attribute('carousel', 'class', [
            'mlmmc-testimonials-carousel',
            'mlmmc-layout-' . $layout,
            'mlmmc-slides-' . $slides_per_view,
        ]);
        
        if ($layout === 'carousel') {
            $this->add_render_attribute('carousel', 'data-slides-per-view', $slides_per_view);
            $this->add_render_attribute('carousel', 'data-show-arrows', $show_arrows ? 'true' : 'false');
            $this->add_render_attribute('carousel', 'data-show-dots', $show_dots ? 'true' : 'false');
            $this->add_render_attribute('carousel', 'data-autoplay', $autoplay ? 'true' : 'false');
            $this->add_render_attribute('carousel', 'data-autoplay-speed', $autoplay_speed);
        }
        
        // Load template
        include plugin_dir_path(__FILE__) . 'templates/testimonials-carousel.php';
    }
}
