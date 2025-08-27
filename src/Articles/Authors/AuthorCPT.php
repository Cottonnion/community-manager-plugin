<?php

declare(strict_types=1);

namespace LABGENZ_CM\Articles\Authors;

defined('ABSPATH') || exit;

/**
 * Class AuthorCPT
 *
 * Handles registration of the Author post type, its metadata, admin interface, 
 * and a custom "suspended" status. Replaces the featured image label with "Author Picture".
 *
 * @package LABGENZ_CM\Articles\Authors
 */
class AuthorCPT {

    public const POST_TYPE = 'mlmmc_author';

    public const META_KEYS = [
        'mlmmc_author_bio',
        'mlmmc_author_photo',
        'mlmmc_article_author',
    ];

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_meta'));
        add_action('init', array($this, 'register_status'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
    }

    public function register_post_type() {
        $labels = array(
            'name'                  => __('Authors', 'labgenz-cm'),
            'singular_name'         => __('Author', 'labgenz-cm'),
            'menu_name'             => __('Authors', 'labgenz-cm'),
            'name_admin_bar'        => __('Author', 'labgenz-cm'),
            'add_new'               => __('Add New', 'labgenz-cm'),
            'add_new_item'          => __('Add New Author', 'labgenz-cm'),
            'edit_item'             => __('Edit Author', 'labgenz-cm'),
            'new_item'              => __('New Author', 'labgenz-cm'),
            'view_item'             => __('View Author', 'labgenz-cm'),
            'search_items'          => __('Search Authors', 'labgenz-cm'),
            'not_found'             => __('No authors found.', 'labgenz-cm'),
            'not_found_in_trash'    => __('No authors found in Trash.', 'labgenz-cm'),
            'all_items'             => __('All Authors', 'labgenz-cm'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'show_in_rest'       => true,
            'has_archive'        => true,
            'rewrite'            => array('slug' => 'mlm-authors'),
            'supports'           => array('title', 'editor', 'thumbnail'),
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-admin-users',
            'capability_type'    => 'post',
            'publicly_queryable' => true,
            'show_ui'            => true,
        );

        register_post_type(self::POST_TYPE, $args);
    }

    public function register_meta() {
        foreach (self::META_KEYS as $key) {
            register_post_meta(
                self::POST_TYPE,
                $key,
                array(
                    'show_in_rest' => true,
                    'single'       => true,
                    'type'         => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'auth_callback'     => '__return_true',
                )
            );
        }
    }

    public function register_status() {
        register_post_status('suspended', array(
            'label'                     => __('Suspended', 'labgenz-cm'),
            'public'                    => false,
            'internal'                  => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Suspended <span class="count">(%s)</span>', 'Suspended <span class="count">(%s)</span>', 'labgenz-cm'),
        ));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'mlmmc_author_meta',
            __('Author Details', 'labgenz-cm'),
            array($this, 'render_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('mlmmc_author_meta', 'mlmmc_author_meta_nonce');

        $bio = get_post_meta($post->ID, 'mlmmc_author_bio', true);
        $photo = get_post_meta($post->ID, 'mlmmc_author_photo', true);
        $author_name = get_post_meta($post->ID, 'mlmmc_article_author', true);
        
        echo '<p><label for="mlmmc_article_author">'.__('Author Name', 'labgenz-cm').'</label>';
        echo '<input type="text" id="mlmmc_article_author" name="mlmmc_article_author" value="'.esc_attr($author_name).'" class="widefat" /></p>';
        
        echo '<p><label for="mlmmc_author_photo">'.__('Author Photo ID', 'labgenz-cm').'</label>';
        echo '<input type="number" id="mlmmc_author_photo" name="mlmmc_author_photo" value="'.esc_attr($photo).'" class="widefat" /></p>';
        
        echo '<p><label for="mlmmc_author_bio">'.__('Biography', 'labgenz-cm').'</label>';
        echo '<textarea id="mlmmc_author_bio" name="mlmmc_author_bio" rows="8" class="widefat">'.esc_textarea($bio).'</textarea></p>';
    }

public function save_meta_boxes($post_id) {
    if (!isset($_POST['mlmmc_author_meta_nonce']) || !wp_verify_nonce($_POST['mlmmc_author_meta_nonce'], 'mlmmc_author_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Use WP's featured image ID if provided
    $thumbnail_id = isset($_POST['_thumbnail_id']) ? intval($_POST['_thumbnail_id']) : 0;

    // Use custom meta if provided
    $custom_photo = isset($_POST['mlmmc_author_photo']) ? intval($_POST['mlmmc_author_photo']) : 0;

    // Decide final photo ID
    $final_photo = $thumbnail_id ?: $custom_photo;

    if ($final_photo) {
        update_post_meta($post_id, 'mlmmc_author_photo', $final_photo);
        set_post_thumbnail($post_id, $final_photo);
    }

    if (isset($_POST['mlmmc_article_author'])) {
        update_post_meta($post_id, 'mlmmc_article_author', sanitize_text_field($_POST['mlmmc_article_author']));
    }

    if (isset($_POST['mlmmc_author_bio'])) {
        update_post_meta($post_id, 'mlmmc_author_bio', sanitize_textarea_field($_POST['mlmmc_author_bio']));
    }
}

}