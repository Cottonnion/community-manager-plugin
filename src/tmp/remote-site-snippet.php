<?php 
 

class Custom_PostMeta_API {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        register_rest_route('custom/v1', '/postmeta/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_postmeta'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'id' => [
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ],
                'api_key' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return !empty($param);
                    }
                ]
            ]
        ]);
        
        // Endpoint to get post with all meta data
        register_rest_route('custom/v1', '/post-complete/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_post_complete'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'id' => [
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ],
                'api_key' => [
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return !empty($param);
                    }
                ]
            ]
        ]);
        
        // Endpoint to get N articles not in a list of IDs
        register_rest_route('custom/v1', '/articles-not-in', [
            'methods' => 'POST',
            'callback' => [$this, 'get_articles_not_in'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'exclude_ids' => [ 'required' => false ],
                'limit' => [ 'required' => false ]
            ]
        ]);
        
        // Endpoint to compare articles between sites
        // register_rest_route('custom/v1', '/compare-articles', [
        //     'methods' => 'POST',
        //     'callback' => [$this, 'compare_articles'],
        //     'permission_callback' => [$this, 'check_permissions'],
        //     'args' => [
        //         'remote_articles' => [ 'required' => true ],
        //         'comparison_type' => [ 'required' => false ]
        //     ]
        // ]);
    }
    
    public function check_permissions($request) {
        $api_key = $request->get_param('api_key');
        $valid_key = '9J4K2L8M5N7P0Q3R'; // Hardcoded API key
        
        return $api_key === $valid_key;
    }
    
    public function get_postmeta($request) {
        global $wpdb;
        
        $post_id = (int) $request['id'];
        
        // Verify post exists and is of correct type
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'mlmmc_artiicle') {
            return new WP_Error('post_not_found', 'Post not found or invalid type', ['status' => 404]);
        }
        
        // Get all postmeta for this post
        $meta_data = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
            $post_id
        ), ARRAY_A);
        
        // Format the data
        $formatted_meta = [];
        foreach ($meta_data as $meta) {
            $key = $meta['meta_key'];
            $value = maybe_unserialize($meta['meta_value']);
            
            // Handle multiple values for same key
            if (isset($formatted_meta[$key])) {
                if (!is_array($formatted_meta[$key]) || !isset($formatted_meta[$key][0])) {
                    $formatted_meta[$key] = [$formatted_meta[$key]];
                }
                $formatted_meta[$key][] = $value;
            } else {
                $formatted_meta[$key] = $value;
            }
        }
        
        return rest_ensure_response([
            'post_id' => $post_id,
            'meta_count' => count($meta_data),
            'meta_data' => $formatted_meta,
            'timestamp' => current_time('mysql')
        ]);
    }
    
    public function get_post_complete($request) {
        $post_id = (int) $request['id'];
        
        // Get basic post data
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'mlmmc_artiicle') {
            return new WP_Error('post_not_found', 'Post not found or invalid type', ['status' => 404]);
        }
        
        // Get ACF fields
        $acf_fields = [];
        if (function_exists('get_fields')) {
            $acf_fields = get_fields($post_id) ?: [];
        }
        
        // Get all postmeta
        global $wpdb;
        $meta_data = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
            $post_id
        ), ARRAY_A);
        
        $formatted_meta = [];
        foreach ($meta_data as $meta) {
            $key = $meta['meta_key'];
            $value = maybe_unserialize($meta['meta_value']);
            
            if (isset($formatted_meta[$key])) {
                if (!is_array($formatted_meta[$key]) || !isset($formatted_meta[$key][0])) {
                    $formatted_meta[$key] = [$formatted_meta[$key]];
                }
                $formatted_meta[$key][] = $value;
            } else {
                $formatted_meta[$key] = $value;
            }
        }
        
        // Get featured image
        $featured_image = null;
        if (has_post_thumbnail($post_id)) {
            $featured_image = [
                'id' => get_post_thumbnail_id($post_id),
                'url' => get_the_post_thumbnail_url($post_id, 'full'),
                'alt' => get_post_meta(get_post_thumbnail_id($post_id), '_wp_attachment_image_alt', true)
            ];
        }
        
        // Get author image (Gravatar or user meta)
        $author_id = $post->post_author;
        $author_image_url = '';
        // Try user meta first (e.g., from a plugin or custom field)
        $custom_avatar = get_user_meta($author_id, 'profile_picture', true);
        if ($custom_avatar) {
            $author_image_url = $custom_avatar;
        } else {
            // Fallback to Gravatar
            $author_email = get_the_author_meta('user_email', $author_id);
            $author_image_url = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($author_email))) . '?s=256&d=mm';
        }
        
        return rest_ensure_response([
            'post' => [
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'status' => $post->post_status,
                'date' => $post->post_date,
                'modified' => $post->post_modified,
                'featured_image' => $featured_image,
                'author_image' => $author_image_url
            ],
            'acf' => $acf_fields,
            'meta' => $formatted_meta,
            'meta_count' => count($meta_data),
            'acf_count' => count($acf_fields),
            'timestamp' => current_time('mysql')
        ]);
    }

    public function get_articles_not_in($request) {
        $exclude_ids = $request->get_param('exclude_ids');
        $limit = intval($request->get_param('limit')) ?: 50;
        
        // Validate limit
        if ($limit > 200) $limit = 200; // Prevent excessive requests
        
        if (empty($exclude_ids)) {
            $exclude_ids = [];
        } elseif (is_string($exclude_ids)) {
            $exclude_ids = json_decode($exclude_ids, true);
        }
        if (!is_array($exclude_ids)) $exclude_ids = [];
        
        // Sanitize exclude_ids array
        $exclude_ids = array_map('intval', array_filter($exclude_ids, 'is_numeric'));
        
        $args = [
            'post_type' => 'mlmmc_artiicle',
            'posts_per_page' => $limit,
            'post__not_in' => $exclude_ids,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $posts = get_posts($args);
        $result = [];
        
        foreach ($posts as $post) {
            // Get ACF fields for this post
            $acf_fields = [];
            if (function_exists('get_fields')) {
                $acf_fields = get_fields($post->ID) ?: [];
            }
            
            // Get basic meta data
            $meta_data = get_post_meta($post->ID);
            
            // Get featured image
            $featured_image = null;
            if (has_post_thumbnail($post->ID)) {
                $featured_image = [
                    'id' => get_post_thumbnail_id($post->ID),
                    'url' => get_the_post_thumbnail_url($post->ID, 'full'),
                    'alt' => get_post_meta(get_post_thumbnail_id($post->ID), '_wp_attachment_image_alt', true)
                ];
            }
            
            $result[] = [
                'ID' => $post->ID,
                'title' => $post->post_title,
                'slug' => $post->post_name, // Add slug for duplicate checking
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'date' => $post->post_date,
                'modified' => $post->post_modified,
                'author' => $post->post_author,
                'status' => $post->post_status,
                'featured_image' => $featured_image,
                'acf' => $acf_fields,
                'meta' => $meta_data
            ];
        }
        
        return rest_ensure_response($result);
    }
    
    public function compare_articles($request) {
        $remote_articles = $request->get_param('remote_articles');
        $comparison_type = $request->get_param('comparison_type') ?: 'all';
        
        if (is_string($remote_articles)) {
            $remote_articles = json_decode($remote_articles, true);
        }
        
        if (!is_array($remote_articles)) {
            return new WP_Error('invalid_data', 'Invalid remote articles data', ['status' => 400]);
        }
        
        // Get all local articles
        $local_posts = get_posts([
            'post_type' => 'mlmmc_artiicle',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        // Create arrays for comparison
        $local_by_slug = [];
        $local_by_title = [];
        $local_articles = [];
        
        foreach ($local_posts as $post) {
            $local_articles[] = [
                'ID' => $post->ID,
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'date' => $post->post_date
            ];
            $local_by_slug[strtolower($post->post_name)] = $post;
            $local_by_title[strtolower($post->post_title)] = $post;
        }
        
        $remote_by_slug = [];
        $remote_by_title = [];
        $remote_articles_clean = [];
        
        foreach ($remote_articles as $article) {
            $clean_article = [
                'ID' => $article['ID'] ?? 0,
                'title' => $article['title'] ?? '',
                'slug' => $article['slug'] ?? '',
                'date' => $article['date'] ?? ''
            ];
            $remote_articles_clean[] = $clean_article;
            $remote_by_slug[strtolower($clean_article['slug'])] = $clean_article;
            $remote_by_title[strtolower($clean_article['title'])] = $clean_article;
        }
        
        // Find differences
        $we_have_they_dont = [];
        $they_have_we_dont = [];
        $potential_duplicates = [];
        $exact_matches = [];
        
        // Check what we have that they don't
        foreach ($local_articles as $local) {
            $local_slug = strtolower($local['slug']);
            $local_title = strtolower($local['title']);
            
            if (!isset($remote_by_slug[$local_slug])) {
                // Check if it's a potential duplicate by title
                if (isset($remote_by_title[$local_title])) {
                    $potential_duplicates[] = [
                        'type' => 'same_title_different_slug',
                        'local' => $local,
                        'remote' => $remote_by_title[$local_title]
                    ];
                } else {
                    $we_have_they_dont[] = $local;
                }
            } else {
                $exact_matches[] = [
                    'local' => $local,
                    'remote' => $remote_by_slug[$local_slug]
                ];
            }
        }
        
        // Check what they have that we don't
        foreach ($remote_articles_clean as $remote) {
            $remote_slug = strtolower($remote['slug']);
            $remote_title = strtolower($remote['title']);
            
            if (!isset($local_by_slug[$remote_slug])) {
                // Check if it's a potential duplicate by title
                if (isset($local_by_title[$remote_title])) {
                    // Already handled in the previous loop
                } else {
                    $they_have_we_dont[] = $remote;
                }
            }
        }
        
        $comparison_result = [
            'summary' => [
                'local_total' => count($local_articles),
                'remote_total' => count($remote_articles_clean),
                'exact_matches' => count($exact_matches),
                'we_have_they_dont' => count($we_have_they_dont),
                'they_have_we_dont' => count($they_have_we_dont),
                'potential_duplicates' => count($potential_duplicates)
            ],
            'exact_matches' => $exact_matches,
            'we_have_they_dont' => $we_have_they_dont,
            'they_have_we_dont' => $they_have_we_dont,
            'potential_duplicates' => $potential_duplicates,
            'timestamp' => current_time('mysql')
        ];
        
        // Filter based on comparison type
        if ($comparison_type === 'summary_only') {
            return rest_ensure_response(['summary' => $comparison_result['summary']]);
        } elseif ($comparison_type === 'differences_only') {
            return rest_ensure_response([
                'summary' => $comparison_result['summary'],
                'we_have_they_dont' => $we_have_they_dont,
                'they_have_we_dont' => $they_have_we_dont,
                'potential_duplicates' => $potential_duplicates
            ]);
        }
        
        return rest_ensure_response($comparison_result);
    }
}

// Initialize the API
new Custom_PostMeta_API();

// Add admin page to set API key
add_action('admin_menu', function() {
    add_options_page(
        'Custom API Settings',
        'Custom API',
        'manage_options',
        'custom-api-settings',
        function() {
            if (isset($_POST['submit'])) {
                $new_key = sanitize_text_field($_POST['api_key']);
                if (strlen($new_key) < 16) {
                    echo '<div class="notice notice-error"><p>API Key must be at least 16 characters long for security!</p></div>';
                } else {
                    update_option('custom_api_key', $new_key);
                    echo '<div class="notice notice-success"><p>API Key saved!</p></div>';
                }
            }
            $current_key = get_option('custom_api_key', '');
            ?>
            <div class="wrap">
                <h1>Custom API Settings</h1>
                <form method="post">
                    <table class="form-table">
                        <tr>
                            <th scope="row">API Key</th>
                            <td>
                                <input type="text" name="api_key" value="<?php echo esc_attr($current_key); ?>" class="regular-text" placeholder="Enter at least 16 characters" />
                                <p class="description">Set a secure API key for accessing postmeta data (minimum 16 characters)</p>
                                <?php if (empty($current_key)): ?>
                                    <p class="description" style="color: #d63638;"><strong>Warning:</strong> Using fallback API key. Please set a custom key for security.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
                <h3>API Endpoints:</h3>
                <p><strong>PostMeta Only:</strong> <code><?php echo site_url(); ?>/wp-json/custom/v1/postmeta/{post_id}?api_key={your_key}</code></p>
                <p><strong>Complete Post Data:</strong> <code><?php echo site_url(); ?>/wp-json/custom/v1/post-complete/{post_id}?api_key={your_key}</code></p>
                <p><strong>Articles Not In:</strong> <code><?php echo site_url(); ?>/wp-json/custom/v1/articles-not-in?api_key={your_key}</code></p>
                <p><strong>Compare Articles:</strong> <code><?php echo site_url(); ?>/wp-json/custom/v1/compare-articles?api_key={your_key}</code></p>
            </div>
            <?php
        }
    );
});
