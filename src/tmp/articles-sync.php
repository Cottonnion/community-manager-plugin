<?php 

 

 

class MLM_Articles_Sync_Enhanced {
    
    private $remote_api_key = '9J4K2L8M5N7P0Q3R';
    private $remote_base_url = 'https://mlmmasteryclub.com';
    private $batch_size = 200;
    
    /**
     * Debug helper to log data structure issues
     * @param mixed $data The data to debug
     * @param string $label An optional label for the debug output
     * @return void
     */
    private function debug_data_structure($data, $label = 'Debug Data') {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $output = $label . ": ";
        
        if (is_array($data)) {
            $output .= "Array with " . count($data) . " items\n";
            
            // Check for problematic keys
            foreach ($data as $key => $value) {
                if (!is_string($key) && !is_int($key)) {
                    $output .= "- WARNING: Invalid key type: " . gettype($key) . "\n";
                }
                
                $type = gettype($value);
                if (is_array($value) || is_object($value)) {
                    $output .= "- Key: " . print_r($key, true) . ", Type: " . $type . "\n";
                }
            }
        } elseif (is_object($data)) {
            $output .= "Object of class " . get_class($data) . "\n";
        } else {
            $output .= "Scalar value of type " . gettype($data) . "\n";
        }
        
        error_log($output);
    }
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_mlm_fetch_article', [$this, 'ajax_fetch_article']);
        add_action('wp_ajax_mlm_sync_article', [$this, 'ajax_sync_article']);
        add_action('wp_ajax_mlm_fetch_remote_articles', [$this, 'ajax_fetch_remote_articles']);
        add_action('wp_ajax_mlm_insert_remote_article', [$this, 'ajax_insert_remote_article']);
        add_action('wp_ajax_mlm_batch_sync', [$this, 'ajax_batch_sync']);
        add_action('wp_ajax_mlm_check_duplicates', [$this, 'ajax_check_duplicates']);
        add_action('wp_ajax_mlm_cleanup_edit_locks', [$this, 'ajax_cleanup_edit_locks']);
        add_action('wp_ajax_mlm_delete_all_synced', [$this, 'ajax_delete_all_synced']);
        add_action('wp_ajax_mlm_compare_articles', [$this, 'ajax_compare_articles']);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'MLM Articles Sync',
            'MLM Articles Sync',
            'manage_options',
            'mlm-articles-sync',
            [$this, 'admin_page'],
            'dashicons-media-document',
            30
        );
        
        add_submenu_page(
            'mlm-articles-sync',
            'Remote Articles',
            'Remote Articles',
            'manage_options',
            'mlm-remote-articles',
            [$this, 'remote_articles_page']
        );
        
        add_submenu_page(
            'mlm-articles-sync',
            'Compare Articles',
            'Compare Articles',
            'manage_options',
            'mlm-compare-articles',
            [$this, 'compare_articles_page']
        );
    }
    
    public function admin_page() {
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $this->batch_size;
        
        $total_articles = wp_count_posts('mlmmc_artiicle')->publish;
        $total_pages = ceil($total_articles / $this->batch_size);
        
        $articles = get_posts([
            'post_type' => 'mlmmc_artiicle',
            'posts_per_page' => $this->batch_size,
            'offset' => $offset,
            'post_status' => 'publish'
        ]);
        
        ?>
        <div class="wrap">
            <h1>MLM Articles Sync</h1>
            
            <div class="batch-controls" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                <h3>Batch Operations</h3>
                <button id="batch-sync-all" class="button button-primary">Sync All Articles on This Page</button>
                <button id="batch-fetch-all" class="button">Fetch All on This Page</button>
                <button id="cleanup-edit-locks" class="button button-secondary" style="margin-left: 10px;">Fix Edit Lock Errors</button>
                <br><br>
                <div style="border: 2px solid #dc3232; padding: 10px; border-radius: 5px; background: #fff;">
                    <strong style="color: #dc3232;">⚠️ Danger Zone</strong><br>
                    <button id="delete-all-synced" class="button" style="background: #dc3232; color: white; border-color: #dc3232; margin-top: 5px;">Delete All Synced Articles</button>
                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">This will permanently delete all articles with remote IDs. Use before restarting sync.</p>
                </div>
                <div id="batch-progress" style="margin-top: 10px;"></div>
            </div>
            
            <div class="pagination-info">
                <p>Showing <?php echo count($articles); ?> of <?php echo $total_articles; ?> articles (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)</p>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th>Title</th>
                    <th>Local ID</th>
                    <th>Remote ID</th>
                    <th>Actions</th>
                    <th>Status</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($articles as $article): 
                        $remote_id = get_post_meta($article->ID, '_remote_id', true);
                    ?>
                        <tr data-post-id="<?php echo esc_attr($article->ID); ?>">
                            <td><?php echo esc_html($article->post_title); ?></td>
                            <td><?php echo esc_html($article->ID); ?></td>
                            <td><?php echo esc_html($remote_id ?: 'N/A'); ?></td>
                            <td>
                                <button class="mlm-fetch-btn button" data-post-id="<?php echo esc_attr($article->ID); ?>">Fetch</button>
                                <button class="mlm-sync-btn button button-primary" data-post-id="<?php echo esc_attr($article->ID); ?>" style="display:none;">Sync</button>
                            </td>
                            <td><span class="mlm-status" data-post-id="<?php echo esc_attr($article->ID); ?>"></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php 
            // Pagination
            if ($total_pages > 1): 
                $base_url = admin_url('admin.php?page=mlm-articles-sync');
            ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php if ($page > 1): ?>
                        <a class="button" href="<?php echo $base_url . '&paged=' . ($page - 1); ?>">← Previous</a>
                    <?php endif; ?>
                    
                    <span class="paging-input">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a class="button" href="<?php echo $base_url . '&paged=' . ($page + 1); ?>">Next →</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <script>
        (function($){
            // Individual fetch
            $('.mlm-fetch-btn').on('click', function(){
                const $btn = $(this);
                const postId = $btn.data('post-id');
                fetchArticle(postId, $btn);
            });
            
            // Individual sync
            $('.mlm-sync-btn').on('click', function(){
                const $btn = $(this);
                const postId = $btn.data('post-id');
                syncArticle(postId, $btn);
            });
            
            // Batch fetch all
            $('#batch-fetch-all').on('click', function(){
                batchOperation('fetch');
            });
            
            // Batch sync all
            $('#batch-sync-all').on('click', function(){
                batchOperation('sync');
            });
            
            // Cleanup edit locks
            $('#cleanup-edit-locks').on('click', function(){
                const $btn = $(this);
                const $progress = $('#batch-progress');
                
                $btn.prop('disabled', true);
                $progress.html('Cleaning up corrupted edit lock fields...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mlm_cleanup_edit_locks',
                        nonce: '<?php echo wp_create_nonce('mlm_sync_nonce'); ?>'
                    },
                    success: function(response){
                        if(response.success) {
                            $progress.html('<div style="color: green;">✓ ' + response.data.message + '</div>');
                        } else {
                            $progress.html('<div style="color: red;">✗ ' + response.data.message + '</div>');
                        }
                    },
                    error: function(){
                        $progress.html('<div style="color: red;">✗ Cleanup failed</div>');
                    },
                    complete: function(){
                        $btn.prop('disabled', false);
                    }
                });
            });
            
            // Delete all synced articles
            $('#delete-all-synced').on('click', function(){
                const $btn = $(this);
                const $progress = $('#batch-progress');
                
                if (!confirm('⚠️ WARNING: This will permanently delete ALL articles that have been synced from the remote site.nnThis action cannot be undone!nnAre you sure you want to continue?')) {
                    return;
                }
                
                if (!confirm('Final confirmation: Delete all synced articles and start fresh?')) {
                    return;
                }
                
                $btn.prop('disabled', true);
                $progress.html('Deleting all synced articles...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mlm_delete_all_synced',
                        nonce: '<?php echo wp_create_nonce('mlm_sync_nonce'); ?>'
                    },
                    success: function(response){
                        if(response.success) {
                            $progress.html('<div style="color: green;">✓ ' + response.data.message + '</div>');
                            setTimeout(() => {
                                location.reload(); // Refresh the page to show updated article list
                            }, 2000);
                        } else {
                            $progress.html('<div style="color: red;">✗ ' + response.data.message + '</div>');
                        }
                    },
                    error: function(){
                        $progress.html('<div style="color: red;">✗ Deletion failed</div>');
                    },
                    complete: function(){
                        $btn.prop('disabled', false);
                    }
                });
            });
            
            function fetchArticle(postId, $btn) {
                const $status = $('.mlm-status[data-post-id="' + postId + '"]');
                const $syncBtn = $('.mlm-sync-btn[data-post-id="' + postId + '"]');
                
                $status.text('Fetching...');
                $btn.prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mlm_fetch_article',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('mlm_sync_nonce'); ?>'
                    },
                    success: function(response){
                        if(response.success) {
                            $status.html('<span style="color: green;">✓ Fetched: ' + (response.data.acf_fields + response.data.meta_fields) + ' fields</span>');
                            $syncBtn.show();
                        } else {
                            $status.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                        }
                    },
                    error: function(){
                        $status.html('<span style="color: red;">✗ Fetch error</span>');
                    },
                    complete: function(){
                        $btn.prop('disabled', false);
                    }
                });
            }
            
            function syncArticle(postId, $btn) {
                const $status = $('.mlm-status[data-post-id="' + postId + '"]');
                
                $status.text('Syncing...');
                $btn.prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mlm_sync_article',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('mlm_sync_nonce'); ?>'
                    },
                    success: function(response){
                        if(response.success) {
                            $status.html('<span style="color: green;">✓ Synced: ' + response.data.synced_fields + ' fields</span>');
                            $btn.hide();
                        } else {
                            $status.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                        }
                    },
                    error: function(){
                        $status.html('<span style="color: red;">✗ Sync error</span>');
                    },
                    complete: function(){
                        $btn.prop('disabled', false);
                    }
                });
            }
            
            function batchOperation(operation) {
                const $progress = $('#batch-progress');
                const $rows = $('tr[data-post-id]');
                const total = $rows.length;
                let completed = 0;
                let success = 0;
                
                $progress.html('<div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div><div class="progress-text">0/' + total + '</div>');
                
                $rows.each(function(index) {
                    const $row = $(this);
                    const postId = $row.data('post-id');
                    const $btn = operation === 'fetch' ? $row.find('.mlm-fetch-btn') : $row.find('.mlm-sync-btn');
                    
                    setTimeout(() => {
                        if (operation === 'fetch') {
                            fetchArticle(postId, $btn);
                        } else if (operation === 'sync' && $btn.is(':visible')) {
                            syncArticle(postId, $btn);
                        }
                        
                        completed++;
                        const percent = Math.round((completed / total) * 100);
                        $progress.find('.progress-fill').css('width', percent + '%');
                        $progress.find('.progress-text').text(completed + '/' + total);
                        
                        if (completed === total) {
                            $progress.append('<div style="margin-top:10px;color:green;">Batch operation completed!</div>');
                        }
                    }, index * 500); // 500ms delay between requests
                });
            }
        })(jQuery);
        </script>
        
        <style>
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #ddd;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #0073aa;
            transition: width 0.3s ease;
        }
        .progress-text {
            text-align: center;
            margin-top: 5px;
            font-weight: bold;
        }
        </style>
        <?php
    }
    
    public function remote_articles_page() {
        ?>
        <div class="wrap">
            <h1>Remote Articles</h1>
            <div class="remote-controls">
                <button id="fetch-remote-batch" class="button button-primary">Fetch Remote Articles (Batch)</button>
                <input type="number" id="remote-batch-size" value="50" min="1" max="200" style="width:60px;">
                <button id="insert-all-remote" class="button" style="display:none;">Insert All Fetched</button>
                <button id="check-duplicates" class="button">Check for Duplicates</button>
            </div>
            <div id="remote-progress"></div>
            <div id="duplicate-info" style="margin: 10px 0; padding: 10px; background: #fff3cd; border-radius: 3px; display: none;"></div>
            <div id="remote-articles-list"></div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            let remoteArticles = [];
            
            $('#fetch-remote-batch').on('click', function() {
                const batchSize = parseInt($('#remote-batch-size').val()) || 50;
                fetchRemoteArticles(batchSize);
            });
            
            $('#insert-all-remote').on('click', function() {
                insertAllRemoteArticles();
            });
            
            $('#check-duplicates').on('click', function() {
                checkForDuplicates();
            });
            
            function fetchRemoteArticles(limit) {
                $('#remote-progress').html('Fetching ' + limit + ' remote articles...');
                $('#remote-articles-list').empty();
                $('#duplicate-info').hide();
                
                $.post(ajaxurl, {
                    action: 'mlm_fetch_remote_articles',
                    nonce: '<?php echo wp_create_nonce('mlm_remote_articles_nonce'); ?>',
                    limit: limit
                }, function(response) {
                    if(response.success) {
                        remoteArticles = response.data.articles;
                        const duplicates = response.data.duplicates_found || 0;
                        const totalFetched = response.data.total_fetched || 0;
                        const existingIds = response.data.existing_remote_ids_count || 0;
                        const existingSlugs = response.data.existing_slugs_count || 0;
                        
                        displayRemoteArticles(remoteArticles);
                        $('#insert-all-remote').show();
                        
                        if (duplicates > 0) {
                            $('#duplicate-info').html('<strong>Note:</strong> ' + duplicates + ' duplicate articles were filtered out from ' + totalFetched + ' fetched articles. (Local database has ' + existingIds + ' articles with remote IDs)').show();
                        }
                        
                        $('#remote-progress').html('Found ' + remoteArticles.length + ' unique remote articles ready to import.');
                    } else {
                        $('#remote-progress').html('<span style="color:red;">' + response.data.message + '</span>');
                    }
                });
            }
            
            function checkForDuplicates() {
                if (remoteArticles.length === 0) {
                    alert('Please fetch remote articles first.');
                    return;
                }
                
                $('#remote-progress').html('Checking for potential duplicates...');
                
                $.post(ajaxurl, {
                    action: 'mlm_check_duplicates',
                    nonce: '<?php echo wp_create_nonce('mlm_remote_articles_nonce'); ?>',
                    articles: remoteArticles
                }, function(response) {
                    if(response.success) {
                        const duplicates = response.data.potential_duplicates;
                        if (duplicates.length > 0) {
                            let msg = 'Found ' + duplicates.length + ' potential slug duplicates:nn';
                            duplicates.forEach(function(dup) {
                                msg += '• "' + dup.title + '" (Slug: ' + dup.slug + ', Remote ID: ' + dup.remote_id + ', Local ID: ' + dup.local_id + ')n';
                            });
                            alert(msg);
                        } else {
                            alert('No potential slug duplicates found!');
                        }
                        $('#remote-progress').html('Duplicate check completed.');
                    } else {
                        $('#remote-progress').html('<span style="color:red;">Duplicate check failed</span>');
                    }
                });
            }
            
            function displayRemoteArticles(articles) {
                let html = '<h3>Found ' + articles.length + ' remote articles</h3>';
                html += '<table class="wp-list-table widefat"><thead><tr><th>Remote ID</th><th>Title</th><th>Action</th></tr></thead><tbody>';
                
                articles.forEach(function(article, index) {
                    html += '<tr data-index="' + index + '">' +
                        '<td>' + article.ID + '</td>' +
                        '<td>' + article.title + '</td>' +
                        '<td><button class="insert-single-remote button" data-index="' + index + '">Insert</button></td>' +
                        '</tr>';
                });
                
                html += '</tbody></table>';
                $('#remote-articles-list').html(html);
                $('#remote-progress').html('Ready to insert articles.');
            }
            
            $(document).on('click', '.insert-single-remote', function() {
                const index = $(this).data('index');
                const article = remoteArticles[index];
                insertRemoteArticle(article, $(this));
            });
            
            function insertAllRemoteArticles() {
                const $buttons = $('.insert-single-remote');
                let completed = 0;
                const total = $buttons.length;
                
                $('#remote-progress').html('Inserting ' + total + ' articles...');
                
                $buttons.each(function(index) {
                    const $btn = $(this);
                    const articleIndex = $btn.data('index');
                    const article = remoteArticles[articleIndex];
                    
                    setTimeout(() => {
                        insertRemoteArticle(article, $btn);
                        completed++;
                        $('#remote-progress').html('Inserted ' + completed + '/' + total + ' articles');
                    }, index * 1000); // 1 second delay
                });
            }
            
            function insertRemoteArticle(article, $btn) {
                $btn.prop('disabled', true).text('Inserting...');
                
                $.post(ajaxurl, {
                    action: 'mlm_insert_remote_article',
                    nonce: '<?php echo wp_create_nonce('mlm_remote_articles_nonce'); ?>',
                    article: article
                }, function(response) {
                    if(response.success) {
                        $btn.text('✓ Inserted').css('color', 'green');
                    } else {
                        $btn.text('✗ Failed').css('color', 'red');
                    }
                });
            }
        });
        </script>
        <?php
    }
    
public function ajax_compare_articles() {
    if (!wp_verify_nonce($_POST['nonce'], 'mlm_sync_nonce')) {
        wp_send_json_error(['message' => 'Security check failed']);
    }
    
    $step = $_POST['step'] ?? 'get_local';
    
    if ($step === 'get_local') {
        // Get all local articles for comparison - FIXED QUERY
        $local_posts = get_posts([
            'post_type' => 'mlmmc_artiicle',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'private', 'draft'], // Include all statuses to match total count
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [], // Remove any meta queries that might be limiting results
            'tax_query' => []   // Remove any taxonomy queries that might be limiting results
        ]);
        
        $articles = [];
        foreach ($local_posts as $post) {
            $articles[] = [
                'ID' => $post->ID,
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'date' => $post->post_date,
                'modified' => $post->post_modified,
                'status' => $post->post_status // Add status for debugging
            ];
        }
        
        // Debug: Log the actual count vs expected
        error_log("Local articles found: " . count($articles) . " articles");
        error_log("Expected from admin: 1550 articles");
        
        wp_send_json_success([
            'articles' => $articles,
            'count' => count($articles),
            'debug_info' => [
                'expected_count' => 1550,
                'actual_count' => count($articles),
                'query_args' => [
                    'post_type' => 'mlmmc_artiicle',
                    'posts_per_page' => -1,
                    'post_status' => ['publish', 'private', 'draft']
                ]
            ]
        ]);
    }
    
    if ($step === 'compare_with_remote') {
        $local_articles = $_POST['local_articles'] ?? [];
        
        if (empty($local_articles)) {
            wp_send_json_error(['message' => 'No local articles provided']);
        }
        
        // Send to remote site for comparison
        $api_url = $this->remote_base_url . '/wp-json/custom/v1/compare-articles';
        
        $response = wp_remote_post($api_url, [
            'timeout' => 120, // Increased timeout for large datasets
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'api_key' => $this->remote_api_key,
                'local_articles' => $local_articles,
                'comparison_type' => $_POST['comparison_type'] ?? 'differences_only'
            ])
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'API error: ' . $response->get_error_message()]);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            wp_send_json_error(['message' => 'API request failed (HTTP ' . $status_code . ')']);
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => 'Invalid JSON response from remote site']);
        }
        
        wp_send_json_success($result);
    }
    
    wp_send_json_error(['message' => 'Invalid step']);
}

// Alternative query method if the above doesn't work
public function get_all_local_articles_debug() {
    global $wpdb;
    
    // Direct database query to get exact count
    $count_query = $wpdb->prepare("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} 
        WHERE post_type = %s 
        AND post_status IN ('publish', 'private', 'draft')
    ", 'mlmmc_artiicle');
    
    $total_count = $wpdb->get_var($count_query);
    
    // Get all articles
    $articles_query = $wpdb->prepare("
        SELECT ID, post_title, post_name, post_date, post_modified, post_status
        FROM {$wpdb->posts} 
        WHERE post_type = %s 
        AND post_status IN ('publish', 'private', 'draft')
        ORDER BY post_date DESC
    ", 'mlmmc_artiicle');
    
    $articles = $wpdb->get_results($articles_query, ARRAY_A);
    
    error_log("Direct DB query found: " . count($articles) . " articles (total count: " . $total_count . ")");
    
    return [
        'articles' => $articles,
        'count' => count($articles),
        'db_count' => $total_count
    ];
}

// Updated comparison page
public function compare_articles_page() {
    ?>
    <div class="wrap">
        <h1>Compare Articles Between Sites</h1>
        <p>Comparing <strong>Local Site</strong> (your site) with <strong>Remote Site</strong> (<?php echo $this->remote_base_url; ?>)</p>
        
        <div class="compare-controls" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px;">
            <h3>Comparison Options</h3>
            <button id="compare-differences" class="button button-primary">Show Differences</button>
            <button id="compare-summary" class="button">Summary Only</button>
            <button id="compare-all" class="button">Full Analysis</button>
            <p style="margin-top: 10px; font-size: 12px; color: #666;">
                <strong>Differences:</strong> What each site has that the other doesn't | 
                <strong>Summary:</strong> Just counts | 
                <strong>Full:</strong> Complete analysis including matches
            </p>
        </div>
        
        <div id="comparison-progress" style="margin: 20px 0;"></div>
        <div id="comparison-results" style="margin: 20px 0;"></div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#compare-differences').on('click', function() {
            runComparison('differences_only');
        });
        
        $('#compare-summary').on('click', function() {
            runComparison('summary_only');
        });
        
        $('#compare-all').on('click', function() {
            runComparison('all');
        });
        
        function runComparison(type) {
            const $progress = $('#comparison-progress');
            const $results = $('#comparison-results');
            
            $progress.html('<div style="padding: 10px; background: #fff3cd; border-radius: 5px;">Step 1/2: Getting local articles...</div>');
            $results.empty();
            
            // First get all local articles
            $.post(ajaxurl, {
                action: 'mlm_compare_articles',
                nonce: '<?php echo wp_create_nonce('mlm_sync_nonce'); ?>',
                step: 'get_local'
            }, function(localResponse) {
                if(!localResponse.success) {
                    $progress.html('<div style="padding: 10px; background: #f8d7da; border-radius: 5px; color: #721c24;">Error getting local articles: ' + localResponse.data.message + '</div>');
                    return;
                }
                
                $progress.html('<div style="padding: 10px; background: #d1ecf1; border-radius: 5px;">Step 2/2: Comparing with remote site (' + localResponse.data.count + ' local articles)...</div>');
                
                // Send local articles to remote site for comparison
                $.post(ajaxurl, {
                    action: 'mlm_compare_articles',
                    nonce: '<?php echo wp_create_nonce('mlm_sync_nonce'); ?>',
                    step: 'compare_with_remote',
                    local_articles: localResponse.data.articles,
                    comparison_type: type
                }, function(comparisonResult) {
                    if(!comparisonResult.success) {
                        $progress.html('<div style="padding: 10px; background: #f8d7da; border-radius: 5px; color: #721c24;">Error: ' + comparisonResult.data.message + '</div>');
                        return;
                    }
                    
                    $progress.html('<div style="padding: 10px; background: #d4edda; border-radius: 5px; color: #155724;">✅ Comparison completed!</div>');
                    displayComparisonResults(comparisonResult.data, type);
                }).fail(function(xhr, status, error) {
                    $progress.html('<div style="padding: 10px; background: #f8d7da; border-radius: 5px; color: #721c24;">Network error: ' + error + '</div>');
                });
            }).fail(function() {
                $progress.html('<div style="padding: 10px; background: #f8d7da; border-radius: 5px; color: #721c24;">Error getting local articles</div>');
            });
        }
        
        function displayComparisonResults(data, type) {
            const $results = $('#comparison-results');
            let html = '';
            
            if (data.summary) {
                html += '<div style="background: #e7f3ff; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #0073aa;">';
                html += '<h2 style="margin-top: 0;">📊 Comparison Summary</h2>';
                html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 15px;">';
                html += '<div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">';
                html += '<div style="font-size: 24px; font-weight: bold; color: #0073aa;">' + data.summary.local_total + '</div>';
                html += '<div style="color: #666;">Local Articles</div></div>';
                html += '<div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">';
                html += '<div style="font-size: 24px; font-weight: bold; color: #0073aa;">' + data.summary.remote_total + '</div>';
                html += '<div style="color: #666;">Remote Articles</div></div>';
                html += '<div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">';
                html += '<div style="font-size: 24px; font-weight: bold; color: #00a32a;">' + data.summary.exact_matches + '</div>';
                html += '<div style="color: #666;">Exact Matches</div></div>';
                html += '<div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">';
                html += '<div style="font-size: 24px; font-weight: bold; color: #d63638;">' + data.summary.local_only + '</div>';
                html += '<div style="color: #666;">Local Only</div></div>';
                html += '<div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">';
                html += '<div style="font-size: 24px; font-weight: bold; color: #00a32a;">' + data.summary.remote_only + '</div>';
                html += '<div style="color: #666;">Remote Only</div></div>';
                html += '<div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">';
                html += '<div style="font-size: 24px; font-weight: bold; color: #dba617;">' + data.summary.title_matches + '</div>';
                html += '<div style="color: #666;">Title Matches</div></div>';
                html += '</div></div>';
            }
            
            if (type !== 'summary_only') {
                // Articles remote has that local doesn't
                if (data.remote_only && data.remote_only.length > 0) {
                    html += '<div style="margin-bottom: 25px; border: 1px solid #00a32a; border-radius: 8px; overflow: hidden;">';
                    html += '<div style="background: #00a32a; color: white; padding: 15px;">';
                    html += '<h3 style="margin: 0;">✅ Articles on Remote Site Only (' + data.remote_only.length + ')</h3>';
                    html += '<p style="margin: 5px 0 0 0; opacity: 0.9;">These articles exist on the remote site but not on your local site</p>';
                    html += '</div>';
                    html += '<div style="max-height: 400px; overflow-y: auto; background: #f8f9fa;">';
                    data.remote_only.forEach(function(article, index) {
                        html += '<div style="padding: 12px 15px; border-bottom: 1px solid #dee2e6; background: white; margin: 1px;">';
                        html += '<div style="font-weight: bold; color: #333; margin-bottom: 5px;">' + (index + 1) + '. ' + article.title + '</div>';
                        html += '<div style="font-size: 12px; color: #666;">';
                        html += '<span>Slug: <code>' + article.slug + '</code></span> | ';
                        html += '<span>ID: ' + article.ID + '</span> | ';
                        html += '<span>Date: ' + new Date(article.date).toLocaleDateString() + '</span>';
                        html += '</div></div>';
                    });
                    html += '</div></div>';
                }
                
                // Articles local has that remote doesn't
                if (data.local_only && data.local_only.length > 0) {
                    html += '<div style="margin-bottom: 25px; border: 1px solid #d63638; border-radius: 8px; overflow: hidden;">';
                    html += '<div style="background: #d63638; color: white; padding: 15px;">';
                    html += '<h3 style="margin: 0;">❌ Articles on Local Site Only (' + data.local_only.length + ')</h3>';
                    html += '<p style="margin: 5px 0 0 0; opacity: 0.9;">These articles exist on your local site but not on the remote site</p>';
                    html += '</div>';
                    html += '<div style="max-height: 400px; overflow-y: auto; background: #f8f9fa;">';
                    data.local_only.forEach(function(article, index) {
                        html += '<div style="padding: 12px 15px; border-bottom: 1px solid #dee2e6; background: white; margin: 1px;">';
                        html += '<div style="font-weight: bold; color: #333; margin-bottom: 5px;">' + (index + 1) + '. ' + article.title + '</div>';
                        html += '<div style="font-size: 12px; color: #666;">';
                        html += '<span>Slug: <code>' + article.slug + '</code></span> | ';
                        html += '<span>ID: ' + article.ID + '</span> | ';
                        html += '<span>Date: ' + new Date(article.date).toLocaleDateString() + '</span>';
                        html += '</div></div>';
                    });
                    html += '</div></div>';
                }
                
                // Potential duplicates (same title, different slug)
                if (data.title_matches && data.title_matches.length > 0) {
                    html += '<div style="margin-bottom: 25px; border: 1px solid #dba617; border-radius: 8px; overflow: hidden;">';
                    html += '<div style="background: #dba617; color: white; padding: 15px;">';
                    html += '<h3 style="margin: 0;">⚠️ Articles with Same Title, Different Slugs (' + data.title_matches.length + ')</h3>';
                    html += '<p style="margin: 5px 0 0 0; opacity: 0.9;">These might be duplicates or variations</p>';
                    html += '</div>';
                    html += '<div style="max-height: 400px; overflow-y: auto; background: #f8f9fa;">';
                    data.title_matches.forEach(function(match, index) {
                        html += '<div style="padding: 15px; border-bottom: 1px solid #dee2e6; background: white; margin: 1px;">';
                        html += '<div style="font-weight: bold; margin-bottom: 10px;">' + (index + 1) + '. ' + match.title + '</div>';
                        html += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
                        html += '<div style="padding: 10px; background: #fff3cd; border-radius: 4px;">';
                        html += '<div style="font-weight: bold; color: #856404;">Local Site</div>';
                        html += '<div style="font-size: 12px; margin-top: 5px;">Slug: <code>' + match.local.slug + '</code></div>';
                        html += '<div style="font-size: 12px;">ID: ' + match.local.ID + '</div>';
                        html += '</div>';
                        html += '<div style="padding: 10px; background: #d1ecf1; border-radius: 4px;">';
                        html += '<div style="font-weight: bold; color: #0c5460;">Remote Site</div>';
                        html += '<div style="font-size: 12px; margin-top: 5px;">Slug: <code>' + match.remote.slug + '</code></div>';
                        html += '<div style="font-size: 12px;">ID: ' + match.remote.ID + '</div>';
                        html += '</div>';
                        html += '</div></div>';
                    });
                    html += '</div></div>';
                }
            }
            
            if (type === 'all' && data.exact_matches && data.exact_matches.length > 0) {
                html += '<div style="margin-bottom: 25px; border: 1px solid #28a745; border-radius: 8px; overflow: hidden;">';
                html += '<div style="background: #28a745; color: white; padding: 15px;">';
                html += '<h3 style="margin: 0;">✅ Exact Matches (' + data.exact_matches.length + ')</h3>';
                html += '<p style="margin: 5px 0 0 0; opacity: 0.9;">Articles that exist on both sites with identical slugs (showing first 20)</p>';
                html += '</div>';
                html += '<div style="max-height: 300px; overflow-y: auto; background: #f8f9fa;">';
                data.exact_matches.slice(0, 20).forEach(function(match, index) {
                    html += '<div style="padding: 8px 15px; border-bottom: 1px solid #dee2e6; background: white; margin: 1px;">';
                    html += '<div style="font-weight: bold; color: #333;">' + (index + 1) + '. ' + match.title + '</div>';
                    html += '<div style="font-size: 12px; color: #666;">Slug: <code>' + match.slug + '</code></div>';
                    html += '</div>';
                });
                if (data.exact_matches.length > 20) {
                    html += '<div style="padding: 15px; text-align: center; font-style: italic; color: #666; background: white;">... and ' + (data.exact_matches.length - 20) + ' more exact matches</div>';
                }
                html += '</div></div>';
            }
            
            $results.html(html);
        }
    });
    </script>
    <?php
}
    
    public function ajax_fetch_article() {
        if (!wp_verify_nonce($_POST['nonce'], 'mlm_sync_nonce')) {
            wp_die('Security check failed');
        }
        
        $post_id = intval($_POST['post_id']);
        $remote_id = get_post_meta($post_id, '_remote_id', true);
        
        if (!$remote_id) {
            wp_send_json_error(['message' => 'No remote ID found']);
        }
        
        $api_url = $this->remote_base_url . '/wp-json/custom/v1/post-complete/' . $remote_id . '?api_key=' . urlencode($this->remote_api_key);
        
        $response = wp_remote_get($api_url, ['timeout' => 30]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'API error: ' . $response->get_error_message()]);
        }
        
        if (wp_remote_retrieve_response_code($response) !== 200) {
            wp_send_json_error(['message' => 'API request failed']);
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($data)) {
            wp_send_json_error(['message' => 'No data received']);
        }
        
        set_transient('mlm_fetched_data_' . $post_id, $data, 2 * HOUR_IN_SECONDS);
        
        wp_send_json_success([
            'title' => $data['post']['title'] ?? 'No title',
            'acf_fields' => $data['acf_count'] ?? 0,
            'meta_fields' => $data['meta_count'] ?? 0
        ]);
    }
    
public function ajax_sync_article() {
    if (!wp_verify_nonce($_POST['nonce'], 'mlm_sync_nonce')) {
        wp_die('Security check failed');
    }
    
    $post_id = intval($_POST['post_id']);
    $data = get_transient('mlm_fetched_data_' . $post_id);
    
    if (!$data) {
        wp_send_json_error(['message' => 'No fetched data. Fetch first.']);
    }
    
    // Validate data structure
    if (!is_array($data)) {
        wp_send_json_error(['message' => 'Invalid data format.']);
    }
    
    // Update post - create a clean array with only allowed WordPress post fields
    $post_data = isset($data['post']) && is_array($data['post']) ? $data['post'] : [];
    
    // Start with a clean array containing only the required ID
    $update_data = array();
    $update_data['ID'] = $post_id;
    
    // Define allowed WordPress post fields to prevent illegal offset issues
    $allowed_post_fields = array(
        'post_title' => 'title',
        'post_content' => 'content', 
        'post_excerpt' => 'excerpt',
        'post_status' => 'status',
        'post_type' => 'type',
        'post_author' => 'author',
        'post_date' => 'date',
        'post_date_gmt' => 'date_gmt',
        'post_modified' => 'modified',
        'post_modified_gmt' => 'modified_gmt',
        'post_name' => 'slug',
        'post_parent' => 'parent',
        'menu_order' => 'menu_order',
        'comment_status' => 'comment_status',
        'ping_status' => 'ping_status'
    );
    
    // Only add fields that exist in our data and are valid
    foreach ($allowed_post_fields as $wp_field => $data_key) {
        if (isset($post_data[$data_key]) && !empty(trim($post_data[$data_key]))) {
            $value = $post_data[$data_key];
            
            // Sanitize based on field type
            switch ($wp_field) {
                case 'post_title':
                case 'post_excerpt':
                case 'post_name':
                    $update_data[$wp_field] = sanitize_text_field($value);
                    break;
                case 'post_content':
                    $update_data[$wp_field] = wp_kses_post($value);
                    break;
                case 'post_author':
                case 'post_parent':
                case 'menu_order':
                    $update_data[$wp_field] = intval($value);
                    break;
                case 'post_status':
                    // Validate post status
                    $valid_statuses = array('publish', 'draft', 'private', 'pending', 'future', 'trash');
                    if (in_array($value, $valid_statuses)) {
                        $update_data[$wp_field] = $value;
                    }
                    break;
                case 'comment_status':
                case 'ping_status':
                    // Validate status values
                    if (in_array($value, array('open', 'closed'))) {
                        $update_data[$wp_field] = $value;
                    }
                    break;
                case 'post_date':
                case 'post_date_gmt':
                case 'post_modified':
                case 'post_modified_gmt':
                    // Validate date format
                    if (strtotime($value) !== false) {
                        $update_data[$wp_field] = $value;
                    }
                    break;
                default:
                    $update_data[$wp_field] = sanitize_text_field($value);
                    break;
            }
        }
    }
    
    // Final safety check - ensure we have a clean associative array
    $clean_update_data = array();
    foreach ($update_data as $key => $value) {
        // Only allow string keys and ensure they're valid WordPress post fields
        if (is_string($key) && ($key === 'ID' || array_key_exists($key, $allowed_post_fields))) {
            // Ensure no nested arrays or objects
            if (is_array($value) || is_object($value)) {
                error_log("MLM Sync Warning: Complex value detected for '{$key}', skipping");
                continue;
            }
            $clean_update_data[$key] = $value;
        }
    }
    
    // Ensure ID is present and valid
    if (empty($clean_update_data['ID']) || !is_numeric($clean_update_data['ID'])) {
        wp_send_json_error(['message' => 'Invalid post ID in update data']);
    }
    
    // Debug the clean update data
    error_log("MLM Sync Debug: Clean update data: " . print_r($clean_update_data, true));
    
    // Only proceed if we have more than just the ID
    // if (count($clean_update_data) > 1) {
    //     $result = wp_update_post($clean_update_data);
    //     if (is_wp_error($result)) {
    //         wp_send_json_error(['message' => 'Post update failed: ' . $result->get_error_message()]);
    //     }
    // }
    
    $synced = 0;
    
    // Update ACF fields
    if (isset($data['acf']) && is_array($data['acf']) && function_exists('update_field')) {
        foreach ($data['acf'] as $key => $value) {
            // Skip if key is not a string or is empty
            if (!is_string($key) || empty(trim($key)) || $value === null || $value === '') {
                error_log("MLM Sync Warning: Skipping ACF field - invalid key or empty value. Key: " . print_r($key, true) . ", Value: " . print_r($value, true));
                continue;
            }
            
            // Validate field key format (ACF field keys should be alphanumeric with underscores)
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                error_log("MLM Sync Warning: Invalid ACF field key format: '{$key}'");
                continue;
            }
            
            try {
                // Ensure complex values are properly formatted
                if (is_object($value)) {
                    error_log("MLM Sync Warning: Object value for ACF key '{$key}' converted to array");
                    $value = (array)$value;
                }
                
                update_field($key, $value, $post_id);
                $synced++;
            } catch (Exception $e) {
                error_log("MLM Sync Error updating ACF field '{$key}': " . $e->getMessage());
            } catch (Error $e) {
                error_log("MLM Sync Fatal Error updating ACF field '{$key}': " . $e->getMessage());
            }
        }
    }
    
    // Update meta fields
    if (isset($data['meta']) && is_array($data['meta'])) {
        foreach ($data['meta'] as $key => $values) {
            // Skip if key is not a string or is empty
            if (!is_string($key) || empty(trim($key))) {
                error_log("MLM Sync Warning: Invalid meta field key (not string or empty): " . print_r($key, true));
                continue;
            }
            
            // Skip WordPress internal fields that can cause issues
            if (in_array($key, ['_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date'])) {
                continue;
            }
            
            try {
                delete_post_meta($post_id, $key);
                if (is_array($values)) {
                    foreach ($values as $value) {
                        // Ensure value isn't an array or object where it shouldn't be
                        if (is_array($value) || is_object($value)) {
                            $value = json_encode($value);
                            error_log("MLM Sync Warning: Complex value for meta key '{$key}' converted to JSON string");
                        }
                        add_post_meta($post_id, $key, $value);
                        $synced++;
                    }
                } else {
                    // Ensure value isn't an array or object where it shouldn't be
                    if (is_array($values) || is_object($values)) {
                        $values = json_encode($values);
                        error_log("MLM Sync Warning: Complex value for meta key '{$key}' converted to JSON string");
                    }
                    update_post_meta($post_id, $key, $values);
                    $synced++;
                }
            } catch (Exception $e) {
                error_log("MLM Sync Error updating meta field '{$key}': " . $e->getMessage());
            } catch (Error $e) {
                error_log("MLM Sync Fatal Error updating meta field '{$key}': " . $e->getMessage());
            }
        }
    }
    
    delete_transient('mlm_fetched_data_' . $post_id);
    
    wp_send_json_success(['synced_fields' => $synced]);
}

/**
 * Sanitize post field values based on field type
 * 
 * @param string $field_name The field name
 * @param mixed $value The field value
 * @return mixed The sanitized value
 */
private function sanitize_post_field($field_name, $value) {
    // Convert to string if not already
    if (!is_string($value) && !is_numeric($value)) {
        $value = (string) $value;
    }
    
    switch ($field_name) {
        case 'post_title':
        case 'post_name':
        case 'post_password':
            return sanitize_text_field($value);
            
        case 'post_content':
        case 'post_excerpt':
            return wp_kses_post($value);
            
        case 'post_status':
        case 'comment_status':
        case 'ping_status':
        case 'post_type':
            return sanitize_key($value);
            
        case 'post_author':
        case 'post_parent':
        case 'menu_order':
        case 'ID':
            return intval($value);
            
        case 'post_date':
        case 'post_date_gmt':
        case 'post_modified':
        case 'post_modified_gmt':
            // Validate date format
            if (empty($value) || $value === '0000-00-00 00:00:00') {
                return null;
            }
            return sanitize_text_field($value);
            
        default:
            return sanitize_text_field($value);
    }
}

/**
 * Process field values to handle serialized data and prevent type errors
 * 
 * @param mixed $value The value to process
 * @return mixed The processed value
 */
private function process_field_value($value) {
    // If value is null or empty string, return as is
    if ($value === null || $value === '') {
        return $value;
    }
    
    // If it's already an array or object, handle appropriately
    if (is_array($value) || is_object($value)) {
        return $value;
    }
    
    // If it's a string, check if it's serialized data
    if (is_string($value)) {
        // Check if it's serialized PHP data
        if ($this->is_serialized($value)) {
            try {
                $unserialized = @unserialize($value);
                if ($unserialized !== false) {
                    // Successfully unserialized, return the unserialized data
                    return $unserialized;
                }
            } catch (Exception $e) {
                error_log("MLM Sync Warning: Failed to unserialize value: " . $e->getMessage());
            }
        }
        
        // For non-serialized strings, return as is
        return $value;
    }
    
    // For any other data types, convert to string to prevent type errors
    return (string) $value;
}

/**
 * Validate meta key to ensure it's a proper string
 * 
 * @param mixed $key The key to validate
 * @return bool True if valid, false otherwise
 */
private function is_valid_meta_key($key) {
    if (!is_string($key)) {
        return false;
    }
    
    $key = trim($key);
    if (empty($key)) {
        return false;
    }
    
    // Check for valid meta key format (alphanumeric, underscores, hyphens)
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $key)) {
        return false;
    }
    
    return true;
}

/**
 * Validate meta value to ensure it's safe for WordPress
 * 
 * @param mixed $value The value to validate
 * @return bool True if valid, false otherwise
 */
private function is_valid_meta_value($value) {
    // Null and empty string are valid
    if ($value === null || $value === '') {
        return true;
    }
    
    // Scalar values (string, int, float, bool) are valid
    if (is_scalar($value)) {
        return true;
    }
    
    // Arrays and objects are valid if they're not too complex
    if (is_array($value) || is_object($value)) {
        // Check if it can be serialized safely
        try {
            $serialized = serialize($value);
            return strlen($serialized) < 65535; // MySQL TEXT field limit
        } catch (Exception $e) {
            return false;
        }
    }
    
    return false;
}

/**
 * Check if a string is serialized data
 * 
 * @param string $data The string to check
 * @return bool True if serialized, false otherwise
 */
private function is_serialized($data) {
    // WordPress has a built-in function for this, but let's implement our own for safety
    if (!is_string($data)) {
        return false;
    }
    
    $data = trim($data);
    if (empty($data)) {
        return false;
    }
    
    // Check for serialized array pattern
    if (preg_match('/^a:\d+:\{.*\}$/s', $data)) {
        return true;
    }
    
    // Check for other serialized patterns
    if (preg_match('/^(?:N;|b:[01];|i:\d+;|d:[\d\.]+;|s:\d+:".*";|a:\d+:\{.*\}|O:\d+:"[^"]*":\d+:\{.*\})$/s', $data)) {
        return true;
    }
    
    return false;
}
    
public function ajax_fetch_remote_articles() {
   if (!wp_verify_nonce($_POST['nonce'], 'mlm_remote_articles_nonce')) {
       wp_send_json_error(['message' => 'Security check failed']);
   }
   
   $limit = intval($_POST['limit'] ?? 50);
   
   // Get existing slugs to check for potential duplicates
   $existing_slugs = [];
   $local_posts = get_posts([
       'post_type' => 'mlmmc_artiicle',
       'posts_per_page' => -1,
       'fields' => 'ids'
   ]);
   
   foreach ($local_posts as $pid) {
       $post = get_post($pid);
       if ($post && $post->post_name) {
           $existing_slugs[] = strtolower(trim($post->post_name));
       }
   }
   
   $api_url = $this->remote_base_url . '/wp-json/custom/v1/articles-not-in?api_key=' . urlencode($this->remote_api_key);
   
   $response = wp_remote_post($api_url, [
       'timeout' => 45,
       'headers' => [
           'Content-Type' => 'application/json'
       ],
       'body' => json_encode([
           'limit' => $limit
       ])
   ]);
   
   if (is_wp_error($response)) {
       wp_send_json_error(['message' => 'API error: ' . $response->get_error_message()]);
   }
   
   $status_code = wp_remote_retrieve_response_code($response);
   if ($status_code !== 200) {
       wp_send_json_error(['message' => 'API request failed (HTTP ' . $status_code . ')']);
   }
   
   $articles = json_decode(wp_remote_retrieve_body($response), true);
   
   if (!is_array($articles)) {
       wp_send_json_error(['message' => 'Invalid response from remote API']);
   }
   
   // Filter by slug only
   $filtered_articles = [];
   $duplicates_found = 0;
   
   foreach ($articles as $article) {
       $article_slug = strtolower(trim($article['slug'] ?? ''));
       
       // Skip if slug already exists locally
       if (!in_array($article_slug, $existing_slugs)) {
           $filtered_articles[] = $article;
           $existing_slugs[] = $article_slug; // Prevent duplicates within this batch too
       } else {
           $duplicates_found++;
       }
   }
   
   wp_send_json_success([
       'articles' => $filtered_articles,
       'total_fetched' => count($articles),
       'duplicates_found' => $duplicates_found,
       'unique_articles' => count($filtered_articles),
       'existing_slugs_count' => count($existing_slugs)
   ]);
}
    
    public function ajax_insert_remote_article() {
        if (!wp_verify_nonce($_POST['nonce'], 'mlm_remote_articles_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        $article = $_POST['article'];
        if (is_string($article)) {
            $article = json_decode(stripslashes($article), true);
        }
        
        if (empty($article['title'])) {
            wp_send_json_error(['message' => 'Invalid article data']);
        }
        
        // Create post data with strict validation
        $post_data = [
            'post_title' => sanitize_text_field($article['title']),
            'post_status' => 'publish',
            'post_type' => 'mlmmc_artiicle'
        ];
        
        // Only add content and excerpt if they are valid strings
        if (isset($article['content']) && is_string($article['content'])) {
            $post_data['post_content'] = wp_kses_post($article['content']);
        }
        
        if (isset($article['excerpt']) && is_string($article['excerpt'])) {
            $post_data['post_excerpt'] = sanitize_text_field($article['excerpt']);
        }
        
        // Ensure post_data contains only valid keys before inserting
        foreach ($post_data as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                unset($post_data[$key]);
                error_log("MLM Sync Warning: Removed invalid key from post_data: " . print_r($key, true));
            }
        }
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => 'Insert failed: ' . $post_id->get_error_message()]);
        }
        
        // Store remote ID
        if (!empty($article['ID'])) {
            update_post_meta($post_id, '_remote_id', intval($article['ID']));
        }
        
        // Update ACF and meta if provided
        $synced = 0;
        if (isset($article['acf']) && is_array($article['acf']) && function_exists('update_field')) {
            foreach ($article['acf'] as $key => $value) {
                // Skip if key is not a string or is empty
                if (!is_string($key) || empty(trim($key)) || $value === null || $value === '') {
                    error_log("MLM Sync Warning: Skipping ACF field - invalid key or empty value. Key: " . print_r($key, true) . ", Value: " . print_r($value, true));
                    continue;
                }
                
                // Validate field key format (ACF field keys should be alphanumeric with underscores)
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                    error_log("MLM Sync Warning: Invalid ACF field key format: '{$key}'");
                    continue;
                }
                
                try {
                    update_field($key, $value, $post_id);
                    $synced++;
                } catch (Exception $e) {
                    error_log("MLM Sync Error updating ACF field '{$key}': " . $e->getMessage());
                } catch (Error $e) {
                    error_log("MLM Sync Fatal Error updating ACF field '{$key}': " . $e->getMessage());
                }
            }
        }
        
        if (isset($article['meta']) && is_array($article['meta'])) {
            foreach ($article['meta'] as $key => $value) {
                // Skip if key is not a string or is empty
                if (!is_string($key) || empty(trim($key))) {
                    error_log("MLM Sync Warning: Invalid meta field key (not string or empty): " . print_r($key, true));
                    continue;
                }
                
                // Skip WordPress internal fields that can cause issues
                if (in_array($key, ['_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date'])) {
                    continue;
                }
                
                try {
                    // Handle array values properly
                    if (is_array($value)) {
                        if (count($value) === 1) {
                            // Single-item array, just store the value
                            $single_value = $value[0];
                            // Check if the value is an array or object
                            if (is_array($single_value) || is_object($single_value)) {
                                $single_value = json_encode($single_value);
                                error_log("MLM Sync Warning: Complex meta value for key '{$key}' converted to JSON string");
                            }
                            update_post_meta($post_id, $key, $single_value);
                        } else {
                            // For multi-item arrays, ensure each item is scalar
                            foreach ($value as $index => $item_value) {
                                if (is_array($item_value) || is_object($item_value)) {
                                    $value[$index] = json_encode($item_value);
                                    error_log("MLM Sync Warning: Complex meta array item for key '{$key}' converted to JSON string");
                                }
                            }
                            update_post_meta($post_id, $key, $value);
                        }
                    } else if (is_object($value)) {
                        // Convert objects to JSON strings
                        update_post_meta($post_id, $key, json_encode($value));
                        error_log("MLM Sync Warning: Object meta value for key '{$key}' converted to JSON string");
                    } else {
                        // Regular scalar value
                        update_post_meta($post_id, $key, $value);
                    }
                    $synced++;
                } catch (Exception $e) {
                    error_log("MLM Sync Error updating meta field '{$key}': " . $e->getMessage());
                } catch (Error $e) {
                    error_log("MLM Sync Fatal Error updating meta field '{$key}': " . $e->getMessage());
                }
            }
        }
        
        wp_send_json_success(['post_id' => $post_id, 'synced_fields' => $synced]);
    }
    
    public function ajax_check_duplicates() {
        if (!wp_verify_nonce($_POST['nonce'], 'mlm_remote_articles_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        $remote_articles = $_POST['articles'] ?? [];
        $potential_duplicates = [];
        
        // Get all local article slugs
        $local_posts = get_posts([
            'post_type' => 'mlmmc_artiicle',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        $local_slugs = [];
        foreach ($local_posts as $post_id) {
            $post = get_post($post_id);
            if ($post && $post->post_name) {
                $local_slugs[strtolower(trim($post->post_name))] = $post_id;
            }
        }
        
        // Check for slug matches
        foreach ($remote_articles as $article) {
            $remote_slug = strtolower(trim($article['slug'] ?? ''));
            if (isset($local_slugs[$remote_slug])) {
                $potential_duplicates[] = [
                    'title' => $article['title'],
                    'slug' => $article['slug'],
                    'remote_id' => $article['ID'],
                    'local_id' => $local_slugs[$remote_slug]
                ];
            }
        }
        
        wp_send_json_success(['potential_duplicates' => $potential_duplicates]);
    }
    
    /**
     * Clean up corrupted edit lock fields
     */
    public function ajax_cleanup_edit_locks() {
        if (!wp_verify_nonce($_POST['nonce'], 'mlm_sync_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        global $wpdb;
        
        // Delete corrupted _edit_lock meta fields
        $deleted = $wpdb->delete(
            $wpdb->postmeta,
            [
                'meta_key' => '_edit_lock'
            ],
            ['%s']
        );
        
        wp_send_json_success([
            'message' => "Cleaned up {$deleted} corrupted edit lock fields"
        ]);
    }
    
    /**
     * Delete all synced articles (articles with remote IDs)
     */
    public function ajax_delete_all_synced() {
        if (!wp_verify_nonce($_POST['nonce'], 'mlm_sync_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        
        // Get all posts with remote IDs
        $posts_with_remote_ids = get_posts([
            'post_type' => 'mlmmc_artiicle',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_remote_id',
                    'compare' => 'EXISTS'
                ]
            ],
            'fields' => 'ids'
        ]);
        
        $deleted_count = 0;
        foreach ($posts_with_remote_ids as $post_id) {
            if (wp_delete_post($post_id, true)) { // true = force delete (bypass trash)
                $deleted_count++;
            }
        }
        
        wp_send_json_success([
            'message' => "Successfully deleted {$deleted_count} synced articles"
        ]);
    }


}

new MLM_Articles_Sync_Enhanced();
