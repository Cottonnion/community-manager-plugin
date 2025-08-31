<?php

declare(strict_types=1);

namespace LABGENZ_CM\Articles\Authors;

defined( 'ABSPATH' ) || exit;

/**
 * Class AuthorCPT
 *
 * Handles registration of the Author post type, its metadata, admin interface,
 * and a custom "suspended" status. Replaces the featured image label with "Author Picture".
 *
 * @package LABGENZ_CM\Articles\Authors
 * @since   1.0.0
 * @author  LABGENZ
 */
class AuthorCPT {

	/**
	 * The post type identifier for authors
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public const POST_TYPE    = 'mlmmc_author';
	public const REWRITE_SLUG = 'mlmmc-authors';

	/**
	 * Array of meta keys used for author post type
	 *
	 * @var array<string>
	 * @since 1.0.0
	 */
	public const META_KEYS = [
		'mlmmc_author_bio',
		'mlmmc_author_photo',
		'mlmmc_article_author',
		'default_product_creator_bio',
		'default_product_creator_photo',
		'product_creator_title',
		'include_in_mtm',
		'about_product_creator_video_link',
		'meet_the_masters_interview_video_link',
		'product_creator_email',
		'product_creator_website',
		'product_creator_full_name',
		'product_creator_first_name',
		'product_creator_last_name',
	];

	/**
	 * AuthorCPT constructor.
	 *
	 * Initializes all WordPress hooks for the Author post type functionality.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'init', [ $this, 'register_meta' ] );
		add_action( 'init', [ $this, 'register_additional_statuses' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_boxes' ] );

		// Add this hook to make the status available in the admin
		add_action( 'admin_footer-post.php', [ $this, 'append_post_status_list' ] );
		add_action( 'admin_footer-post-new.php', [ $this, 'append_post_status_list' ] );

		// Register custom statuses
		add_action( 'init', [ $this, 'register_additional_statuses' ] );
	}

	/**
	 * Registers the Author custom post type
	 *
	 * Creates the mlmmc_author post type with appropriate labels, capabilities,
	 * and settings for public display and REST API support.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = [
			'name'               => __( 'Authors', 'labgenz-cm' ),
			'singular_name'      => __( 'Author', 'labgenz-cm' ),
			'menu_name'          => __( 'Authors', 'labgenz-cm' ),
			'name_admin_bar'     => __( 'Author', 'labgenz-cm' ),
			'add_new'            => __( 'Add New', 'labgenz-cm' ),
			'add_new_item'       => __( 'Add New Author', 'labgenz-cm' ),
			'edit_item'          => __( 'Edit Author', 'labgenz-cm' ),
			'new_item'           => __( 'New Author', 'labgenz-cm' ),
			'view_item'          => __( 'View Author', 'labgenz-cm' ),
			'search_items'       => __( 'Search Authors', 'labgenz-cm' ),
			'not_found'          => __( 'No authors found.', 'labgenz-cm' ),
			'not_found_in_trash' => __( 'No authors found in Trash.', 'labgenz-cm' ),
			'all_items'          => __( 'All Authors', 'labgenz-cm' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'show_in_rest'       => true,
			'has_archive'        => true,
			'rewrite'            => [ 'slug' => self::REWRITE_SLUG ],
			'supports'           => [ 'title', 'editor', 'thumbnail' ],
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-admin-users',
			'capability_type'    => 'post',
			'publicly_queryable' => true,
			'show_ui'            => true,
		];

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Registers meta fields for the Author post type
	 *
	 * Registers all meta keys defined in META_KEYS constant with appropriate
	 * settings for REST API exposure and sanitization.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_meta(): void {
		foreach ( self::META_KEYS as $key ) {
			// Different sanitization for different field types
			$sanitize_callback = 'sanitize_text_field';
			$type              = 'string';

			if ( in_array( $key, [ 'default_product_creator_bio', 'mlmmc_author_bio' ] ) ) {
				$sanitize_callback = 'sanitize_textarea_field';
			} elseif ( in_array( $key, [ 'about_product_creator_video_link', 'meet_the_masters_interview_video_link' ] ) ) {
				$sanitize_callback = 'esc_url_raw';
			} elseif ( $key === 'product_creator_email' ) {
				$sanitize_callback = 'sanitize_email';
			} elseif ( $key === 'include_in_mtm' ) {
				$sanitize_callback = 'absint';
				$type              = 'boolean';
			} elseif ( in_array( $key, [ 'default_product_creator_photo', 'mlmmc_author_photo' ] ) ) {
				$sanitize_callback = 'absint';
				$type              = 'integer';
			}

			register_post_meta(
				self::POST_TYPE,
				$key,
				[
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => $type,
					'sanitize_callback' => $sanitize_callback,
					'auth_callback'     => '__return_true',
				]
			);
		}
	}

	/**
	 * Registers additional custom post statuses for authors
	 *
	 * Creates multiple statuses for different author states beyond just suspended.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_additional_statuses(): void {
		$statuses = [
			'inactive' => __( 'Inactive', 'labgenz-cm' ),
			'retired'  => __( 'Retired', 'labgenz-cm' ),
			'guest'    => __( 'Guest', 'labgenz-cm' ),
			'pending'  => __( 'Pending', 'labgenz-cm' ),
		];

		foreach ( $statuses as $status => $label ) {
			register_post_status(
				$status,
				[
					'label'                     => $label,
					'public'                    => false,
					'internal'                  => false,
					'exclude_from_search'       => $status !== 'guest',
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => _n_noop(
						$label . ' <span class="count">(%s)</span>',
						$label . ' <span class="count">(%s)</span>',
						'labgenz-cm'
					),
				]
			);
		}
	}

	/**
	 * Adds JavaScript to make custom statuses available in post edit screen
	 *
	 * WordPress doesn't automatically show custom post statuses in the status dropdown,
	 * so we need to add them via JavaScript.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function append_post_status_list(): void {
		global $post;

		// Only add for our post type
		if ( $post->post_type !== self::POST_TYPE ) {
			return;
		}

		$custom_statuses = [
			'suspended' => __( 'Suspended', 'labgenz-cm' ),
			'inactive'  => __( 'Inactive', 'labgenz-cm' ),
			'retired'   => __( 'Retired', 'labgenz-cm' ),
			'guest'     => __( 'Guest', 'labgenz-cm' ),
			'pending'   => __( 'Pending', 'labgenz-cm' ),
		];

		echo "<script>
        jQuery(document).ready(function($) {
            if ($('#post-status-select').length) {
                var select = $('#post-status-select select');
                var currentStatus = '" . esc_js( $post->post_status ) . "';
                var statuses = " . json_encode( $custom_statuses ) . ";
                
                // Add custom status options
                $.each(statuses, function(value, label) {
                    if (select.find('option[value=\"' + value + '\"]').length === 0) {
                        select.append('<option value=\"' + value + '\">' + label + '</option>');
                    }
                });
                
                // Set current status if it's a custom one
                if (statuses[currentStatus]) {
                    select.val(currentStatus);
                    $('#post-status-display').text(statuses[currentStatus]);
                }
                
                // Update display when status changes
                select.change(function() {
                    var selectedStatus = $(this).val();
                    if (statuses[selectedStatus]) {
                        $('#post-status-display').text(statuses[selectedStatus]);
                    }
                });
            }
        });
        </script>";
	}

	/**
	 * Adds meta boxes to the Author post type edit screen
	 *
	 * Creates multiple meta boxes for organizing author information including
	 * basic details, creator information, and media/links.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'mlmmc_author_basic_meta',
			__( 'Basic Author Details', 'labgenz-cm' ),
			[ $this, 'render_basic_meta_box' ],
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'mlmmc_author_creator_meta',
			__( 'Product Creator Information', 'labgenz-cm' ),
			[ $this, 'render_creator_meta_box' ],
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'mlmmc_author_media_meta',
			__( 'Media & Links', 'labgenz-cm' ),
			[ $this, 'render_media_meta_box' ],
			self::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Renders the Basic Author Details meta box
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post The current post object
	 * @return void
	 */
	public function render_basic_meta_box( $post ): void {
		wp_nonce_field( 'mlmmc_author_meta', 'mlmmc_author_meta_nonce' );

		$author_name = get_post_meta( $post->ID, 'mlmmc_article_author', true );
		$bio         = get_post_meta( $post->ID, 'mlmmc_author_bio', true );
		$photo       = get_post_meta( $post->ID, 'mlmmc_author_photo', true );

		echo '<table class="form-table">';
		echo '<tr><th><label for="mlmmc_article_author">' . __( 'Author Name', 'labgenz-cm' ) . '</label></th>';
		echo '<td><input type="text" id="mlmmc_article_author" name="mlmmc_article_author" value="' . esc_attr( $author_name ) . '" class="regular-text" /></td></tr>';

		echo '<tr><th><label for="mlmmc_author_photo">' . __( 'Author Photo ID', 'labgenz-cm' ) . '</label></th>';
		echo '<td><input type="number" id="mlmmc_author_photo" name="mlmmc_author_photo" value="' . esc_attr( $photo ) . '" class="regular-text" /></td></tr>';

		echo '<tr><th><label for="mlmmc_author_bio">' . __( 'Biography', 'labgenz-cm' ) . '</label></th>';
		echo '<td><textarea id="mlmmc_author_bio" name="mlmmc_author_bio" rows="4" class="large-text">' . esc_textarea( $bio ) . '</textarea></td></tr>';
		echo '</table>';
	}

	/**
	 * Renders the Product Creator Information meta box
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post The current post object
	 * @return void
	 */
	public function render_creator_meta_box( $post ): void {
		$full_name     = get_post_meta( $post->ID, 'product_creator_full_name', true );
		$first_name    = get_post_meta( $post->ID, 'product_creator_first_name', true );
		$last_name     = get_post_meta( $post->ID, 'product_creator_last_name', true );
		$title         = get_post_meta( $post->ID, 'product_creator_title', true );
		$creator_bio   = get_post_meta( $post->ID, 'default_product_creator_bio', true );
		$creator_photo = get_post_meta( $post->ID, 'default_product_creator_photo', true );
		$email         = get_post_meta( $post->ID, 'product_creator_email', true );
		$website       = get_post_meta( $post->ID, 'product_creator_website', true );
		$include_mtm   = get_post_meta( $post->ID, 'include_in_mtm', true );

		echo '<table class="form-table">';
		echo '<tr><th><label for="product_creator_full_name">' . __( 'Full Name', 'labgenz-cm' ) . '</label></th>';
		echo '<td><input type="text" id="product_creator_full_name" name="product_creator_full_name" value="' . esc_attr( $full_name ) . '" class="regular-text" /></td></tr>';

		echo '<tr><th><label for="product_creator_first_name">' . __( 'First Name', 'labgenz-cm' ) . '</label></th>';
		echo '<td><input type="text" id="product_creator_first_name" name="product_creator_first_name" value="' . esc_attr( $first_name ) . '" class="regular-text" /></td></tr>';

		echo '<tr><th><label for="product_creator_last_name">' . __( 'Last Name', 'labgenz-cm' ) . '</label></th>';
		echo '<td><input type="text" id="product_creator_last_name" name="product_creator_last_name" value="' . esc_attr( $last_name ) . '" class="regular-text" maxlength="15" /></td></tr>';

		echo '<tr><th><label for="product_creator_title">' . __( 'Title', 'labgenz-cm' ) . '</label></th>';
		echo '<td><input type="text" id="product_creator_title" name="product_creator_title" value="' . esc_attr( $title ) . '" class="regular-text" /></td></tr>';

		echo '<tr><th><label for="default_product_creator_photo">' . __( 'Creator Photo ID', 'labgenz-cm' ) . '</label></th>';
		echo '<td><input type="number" id="default_product_creator_photo" name="default_product_creator_photo" value="' . esc_attr( $creator_photo ) . '" class="regular-text" /></td></tr>';

		echo '<tr><th><label for="default_product_creator_bio">' . __( 'Creator Bio (250 chars)', 'labgenz-cm' ) . '</label></th>';
		echo '<td><textarea id="default_product_creator_bio" name="default_product_creator_bio" rows="4" class="large-text" maxlength="250">' . esc_textarea( $creator_bio ) . '</textarea></td></tr>';

		echo '<tr><th><label for="product_creator_email">' . __( 'Email', 'labgenz-cm' ) . '</label></th>';
		echo '<td><input type="email" id="product_creator_email" name="product_creator_email" value="' . esc_attr( $email ) . '" class="regular-text" /></td></tr>';

		echo '<tr><th><label for="product_creator_website">' . __( 'Website', 'labgenz-cm' ) . '</label></th>';
		echo '<td><input type="url" id="product_creator_website" name="product_creator_website" value="' . esc_attr( $website ) . '" class="regular-text" /></td></tr>';

		echo '<tr><th><label for="include_in_mtm">' . __( 'Include in Meet the Masters', 'labgenz-cm' ) . '</label></th>';
		echo '<td><input type="checkbox" id="include_in_mtm" name="include_in_mtm" value="1" ' . checked( $include_mtm, 1, false ) . ' /> ' . __( 'Include this person in the Meet The Masters Interview section', 'labgenz-cm' ) . '</td></tr>';
		echo '</table>';
	}

	/**
	 * Renders the Media & Links meta box
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post The current post object
	 * @return void
	 */
	public function render_media_meta_box( $post ): void {
		$about_video = get_post_meta( $post->ID, 'about_product_creator_video_link', true );
		$mtm_video   = get_post_meta( $post->ID, 'meet_the_masters_interview_video_link', true );

		echo '<table class="form-table">';
		echo '<tr><th><label for="about_product_creator_video_link">' . __( 'About Creator Video URL', 'labgenz-cm' ) . '</label></th>';
		echo '<td><input type="url" id="about_product_creator_video_link" name="about_product_creator_video_link" value="' . esc_attr( $about_video ) . '" class="regular-text" />';
		echo '<p class="description">' . __( 'Enter the full URL for an intro video of the content creator', 'labgenz-cm' ) . '</p></td></tr>';

		echo '<tr><th><label for="meet_the_masters_interview_video_link">' . __( 'Meet the Masters Video URL', 'labgenz-cm' ) . '</label></th>';
		echo '<td><input type="url" id="meet_the_masters_interview_video_link" name="meet_the_masters_interview_video_link" value="' . esc_attr( $mtm_video ) . '" class="regular-text" />';
		echo '<p class="description">' . __( 'Enter the full URL for the Meet the Masters interview video', 'labgenz-cm' ) . '</p></td></tr>';
		echo '</table>';
	}

	/**
	 * Saves meta box data when post is saved
	 *
	 * Handles saving of all author meta fields with proper nonce verification,
	 * autosave protection, and field-specific sanitization.
	 *
	 * @since 1.0.0
	 * @param int $post_id The ID of the post being saved
	 * @return void
	 */
	public function save_meta_boxes( $post_id ): void {
		// Verify nonce
		if ( ! isset( $_POST['mlmmc_author_meta_nonce'] ) || ! wp_verify_nonce( $_POST['mlmmc_author_meta_nonce'], 'mlmmc_author_meta' ) ) {
			return;
		}

		// Skip autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Handle photo synchronization
		$thumbnail_id  = isset( $_POST['_thumbnail_id'] ) ? intval( $_POST['_thumbnail_id'] ) : 0;
		$custom_photo  = isset( $_POST['mlmmc_author_photo'] ) ? intval( $_POST['mlmmc_author_photo'] ) : 0;
		$creator_photo = isset( $_POST['default_product_creator_photo'] ) ? intval( $_POST['default_product_creator_photo'] ) : 0;

		$final_photo = $thumbnail_id ?: $custom_photo ?: $creator_photo;

		if ( $final_photo ) {
			update_post_meta( $post_id, 'mlmmc_author_photo', $final_photo );
			update_post_meta( $post_id, 'default_product_creator_photo', $final_photo );
			set_post_thumbnail( $post_id, $final_photo );
		}

		// Save all meta fields with appropriate sanitization
		$meta_fields = [
			'mlmmc_article_author'                  => 'sanitize_text_field',
			'mlmmc_author_bio'                      => 'sanitize_textarea_field',
			'product_creator_full_name'             => 'sanitize_text_field',
			'product_creator_first_name'            => 'sanitize_text_field',
			'product_creator_last_name'             => 'sanitize_text_field',
			'product_creator_title'                 => 'sanitize_text_field',
			'default_product_creator_bio'           => 'sanitize_textarea_field',
			'product_creator_email'                 => 'sanitize_email',
			'product_creator_website'               => 'esc_url_raw',
			'about_product_creator_video_link'      => 'esc_url_raw',
			'meet_the_masters_interview_video_link' => 'esc_url_raw',
		];

		foreach ( $meta_fields as $field => $sanitize_func ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = call_user_func( $sanitize_func, $_POST[ $field ] );
				update_post_meta( $post_id, $field, $value );
			}
		}

		// Handle checkbox for include_in_mtm
		$include_mtm = isset( $_POST['include_in_mtm'] ) ? 1 : 0;
		update_post_meta( $post_id, 'include_in_mtm', $include_mtm );

		// Handle numeric fields
		if ( isset( $_POST['mlmmc_author_photo'] ) ) {
			update_post_meta( $post_id, 'mlmmc_author_photo', intval( $_POST['mlmmc_author_photo'] ) );
		}

		if ( isset( $_POST['default_product_creator_photo'] ) ) {
			update_post_meta( $post_id, 'default_product_creator_photo', intval( $_POST['default_product_creator_photo'] ) );
		}
	}
}
