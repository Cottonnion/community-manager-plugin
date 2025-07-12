<?php 

/**
 * ACF Media Sync Class
 * Handles syncing ACF author photo field IDs with new media IDs
 */
class ACF_Media_Sync {
    
    private $post_type = 'mlmmc_artiicle';
    private $acf_field = 'mlmmc_author_photo';
    private $meta_key = '_remote_attachment_id';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_acf_media_preview', array($this, 'ajax_preview_data'));
        add_action('wp_ajax_acf_media_sync', array($this, 'ajax_sync_media'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_management_page(
            'ACF Media Sync',
            'ACF Media Sync',
            'manage_options',
            'acf-media-sync',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'tools_page_acf-media-sync') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'acf_media_sync', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('acf_media_sync_nonce')
        ));
    }
    
    /**
     * Render the admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>ACF Media Sync</h1>
            <p>This tool helps sync ACF author photo fields with new media IDs based on remote_attachment_id.</p>
            
            <div class="sync-controls" style="margin: 20px 0;">
                <button id="preview-data" class="button button-primary">Preview Data</button>
                <button id="sync-media" class="button button-secondary" disabled>Sync Media</button>
                <span id="loading" style="display: none;">Loading...</span>
            </div>
            
            <div id="results-container">
                <!-- Results will be loaded here via AJAX -->
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            
            // Preview data
            $('#preview-data').on('click', function() {
                var button = $(this);
                button.prop('disabled', true);
                $('#loading').show();
                $('#results-container').html('');
                
                $.ajax({
                    url: acf_media_sync.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'acf_media_preview',
                        nonce: acf_media_sync.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#results-container').html(response.data.html);
                            $('#sync-media').prop('disabled', false);
                        } else {
                            $('#results-container').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#results-container').html('<div class="notice notice-error"><p>An error occurred while loading data.</p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                        $('#loading').hide();
                    }
                });
            });
            
            // Sync media
            $('#sync-media').on('click', function() {
                if (!confirm('Are you sure you want to sync the media? This will update ACF fields.')) {
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true);
                $('#loading').show();
                
                $.ajax({
                    url: acf_media_sync.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'acf_media_sync',
                        nonce: acf_media_sync.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#results-container').html(response.data.html);
                            // Refresh preview after sync
                            $('#preview-data').trigger('click');
                        } else {
                            alert('Sync failed: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('An error occurred during sync.');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                        $('#loading').hide();
                    }
                });
            });
            
            // Handle individual media preview
            $(document).on('click', '.preview-image', function(e) {
                e.preventDefault();
                var imageUrl = $(this).data('url');
                if (imageUrl) {
                    window.open(imageUrl, '_blank', 'width=800,height=600');
                }
            });
            
        });
        </script>
        
        <style>
        .media-sync-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .media-sync-table th,
        .media-sync-table td {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .media-sync-table th {
            background-color: #f1f1f1;
            font-weight: bold;
        }
        .media-sync-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .status-needs-sync {
            color: #d63638;
            font-weight: bold;
        }
        .status-synced {
            color: #00a32a;
            font-weight: bold;
        }
        .status-no-match {
            color: #dba617;
            font-weight: bold;
        }
        .preview-image {
            color: #0073aa;
            text-decoration: none;
        }
        .preview-image:hover {
            text-decoration: underline;
        }
        .sync-summary {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 15px;
            margin: 20px 0;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for previewing data
     */
    public function ajax_preview_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'acf_media_sync_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $data = $this->get_preview_data();
        
        wp_send_json_success(array('html' => $data));
    }
    
    /**
     * AJAX handler for syncing media
     */
    public function ajax_sync_media() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'acf_media_sync_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $result = $this->sync_media();
        
        wp_send_json_success(array('html' => $result));
    }
    
    /**
     * Get preview data for display
     */
    private function get_preview_data() {
        global $wpdb;
        
        // Get posts that have the mlmmc_author_photo meta key
        $query = $wpdb->prepare("
            SELECT p.ID, p.post_title, pm.meta_value as current_acf_value
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND pm.meta_key = %s
            AND pm.meta_value != ''
        ", $this->post_type, $this->acf_field);
        
        $posts = $wpdb->get_results($query);
        
        if (empty($posts)) {
            return '<div class="notice notice-warning"><p>No posts found with meta key "' . $this->acf_field . '".</p></div>';
        }
        
        $html = '<div class="sync-summary">';
        $html .= '<h3>Preview Results</h3>';
        $html .= '<p>Found ' . count($posts) . ' posts with ACF field data.</p>';
        $html .= '</div>';
        
        $html .= '<table class="media-sync-table">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>Post ID</th>';
        $html .= '<th>Post Title</th>';
        $html .= '<th>Current ACF Value</th>';
        $html .= '<th>New Media ID</th>';
        $html .= '<th>Status</th>';
        $html .= '<th>Preview</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        $needs_sync = 0;
        $already_synced = 0;
        $no_match = 0;
        
        foreach ($posts as $post) {
            $current_acf_value = $post->current_acf_value;
            $new_media_id = $this->find_new_media_id($current_acf_value);
            
            $html .= '<tr>';
            $html .= '<td>' . $post->ID . '</td>';
            $html .= '<td>' . esc_html($post->post_title) . '</td>';
            $html .= '<td>' . $current_acf_value . '</td>';
            
            if ($new_media_id) {
                $html .= '<td>' . $new_media_id . '</td>';
                
                if ($current_acf_value != $new_media_id) {
                    $html .= '<td><span class="status-needs-sync">Needs Sync</span></td>';
                    $needs_sync++;
                } else {
                    $html .= '<td><span class="status-synced">Already Synced</span></td>';
                    $already_synced++;
                }
                
                // Preview link
                $image_url = wp_get_attachment_image_url($new_media_id, 'thumbnail');
                if ($image_url) {
                    $html .= '<td><a href="#" class="preview-image" data-url="' . esc_url(wp_get_attachment_image_url($new_media_id, 'full')) . '">Preview</a></td>';
                } else {
                    $html .= '<td>No image</td>';
                }
            } else {
                $html .= '<td>-</td>';
                $html .= '<td><span class="status-no-match">No Match Found</span></td>';
                $html .= '<td>-</td>';
                $no_match++;
            }
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        // Summary
        $html .= '<div class="sync-summary">';
        $html .= '<h4>Summary:</h4>';
        $html .= '<p><strong>Needs Sync:</strong> ' . $needs_sync . '</p>';
        $html .= '<p><strong>Already Synced:</strong> ' . $already_synced . '</p>';
        $html .= '<p><strong>No Match Found:</strong> ' . $no_match . '</p>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Find new media ID based on matching meta_value
     */
    private function find_new_media_id($old_media_id) {
        global $wpdb;
        
        if (!$old_media_id) {
            return false;
        }
        
        // Find attachment post_id where _remote_attachment_id meta_value equals the mlmmc_author_photo meta_value
        $query = $wpdb->prepare("
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_remote_attachment_id' 
            AND meta_value = %s 
            LIMIT 1
        ", $old_media_id);
        
        $attachment_id = $wpdb->get_var($query);
        
        return $attachment_id ? intval($attachment_id) : false;
    }
    
    /**
     * Perform the actual sync
     */
    private function sync_media() {
        global $wpdb;
        
        // Get posts that have the mlmmc_author_photo meta key
        $query = $wpdb->prepare("
            SELECT p.ID, p.post_title, pm.meta_value as current_acf_value
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND pm.meta_key = %s
            AND pm.meta_value != ''
        ", $this->post_type, $this->acf_field);
        
        $posts = $wpdb->get_results($query);
        
        $updated_count = 0;
        $failed_count = 0;
        $skipped_count = 0;
        $log = array();
        
        foreach ($posts as $post) {
            $old_media_id = $post->current_acf_value;
            
            if (!$old_media_id) {
                $skipped_count++;
                continue;
            }
            
            $new_media_id = $this->find_new_media_id($old_media_id);
            
            if ($new_media_id) {
                // Only update if different
                if ($old_media_id != $new_media_id) {
                    // Update using direct database query for the meta key
                    $updated = $wpdb->update(
                        $wpdb->postmeta,
                        array('meta_value' => $new_media_id),
                        array(
                            'post_id' => $post->ID,
                            'meta_key' => $this->acf_field
                        ),
                        array('%s'),
                        array('%d', '%s')
                    );
                    
                    if ($updated !== false) {
                        $updated_count++;
                        $log[] = "Post ID {$post->ID}: Updated from {$old_media_id} to {$new_media_id}";
                    } else {
                        $failed_count++;
                        $log[] = "Post ID {$post->ID}: Failed to update";
                    }
                } else {
                    $skipped_count++;
                    $log[] = "Post ID {$post->ID}: Already synced";
                }
            } else {
                $failed_count++;
                $log[] = "Post ID {$post->ID}: No matching media found for {$old_media_id}";
            }
        }
        
        $html = '<div class="sync-summary">';
        $html .= '<h3>Sync Results</h3>';
        $html .= '<p><strong>Total posts processed:</strong> ' . count($posts) . '</p>';
        $html .= '<p><strong>Successfully updated:</strong> ' . $updated_count . '</p>';
        $html .= '<p><strong>Failed/Not found:</strong> ' . $failed_count . '</p>';
        $html .= '<p><strong>Skipped (already synced):</strong> ' . $skipped_count . '</p>';
        $html .= '</div>';
        
        if (!empty($log)) {
            $html .= '<h4>Detailed Log:</h4>';
            $html .= '<div style="background: #f1f1f1; padding: 10px; max-height: 300px; overflow-y: auto;">';
            foreach ($log as $entry) {
                $html .= '<div>' . esc_html($entry) . '</div>';
            }
            $html .= '</div>';
        }
        
        return $html;
    }
}

// Initialize the class
new ACF_Media_Sync();
