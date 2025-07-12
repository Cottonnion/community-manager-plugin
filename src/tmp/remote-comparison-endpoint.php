<?php
/**
 * Remote site comparison endpoint
 * Add this to your remote site (mlmmasteryclub.com) to enable article comparison
 */

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/compare-articles', [
        'methods' => 'POST',
        'callback' => 'handle_article_comparison',
        'permission_callback' => 'verify_api_key'
    ]);
});

function handle_article_comparison($request) {
    $api_key = $request->get_param('api_key');
    $remote_articles = $request->get_param('remote_articles'); // Actually local articles from the requesting site
    $comparison_type = $request->get_param('comparison_type') ?: 'all';
    
    if (!verify_api_key_value($api_key)) {
        return new WP_Error('unauthorized', 'Invalid API key', ['status' => 401]);
    }
    
    // Get all local articles (from this remote site)
    $local_posts = get_posts([
        'post_type' => 'mlmmc_artiicle',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    $local_articles = [];
    foreach ($local_posts as $post) {
        $local_articles[] = [
            'ID' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'date' => $post->post_date,
            'modified' => $post->post_modified
        ];
    }
    
    // Perform comparison
    $comparison_result = compare_article_sets($remote_articles, $local_articles, $comparison_type);
    
    return rest_ensure_response($comparison_result);
}

function compare_article_sets($remote_articles, $local_articles, $type) {
    $remote_slugs = [];
    $local_slugs = [];
    $remote_titles = [];
    $local_titles = [];
    
    // Index articles by slug and title
    foreach ($remote_articles as $article) {
        $slug = strtolower(trim($article['slug']));
        $title = strtolower(trim($article['title']));
        $remote_slugs[$slug] = $article;
        $remote_titles[$title] = $article;
    }
    
    foreach ($local_articles as $article) {
        $slug = strtolower(trim($article['slug']));
        $title = strtolower(trim($article['title']));
        $local_slugs[$slug] = $article;
        $local_titles[$title] = $article;
    }
    
    // Find exact matches (same slug)
    $exact_matches = [];
    foreach ($remote_slugs as $slug => $remote_article) {
        if (isset($local_slugs[$slug])) {
            $exact_matches[] = [
                'local' => $local_slugs[$slug],
                'remote' => $remote_article
            ];
        }
    }
    
    // Find articles they have that we don't (remote has, local doesn't)
    $they_have_we_dont = [];
    foreach ($remote_slugs as $slug => $remote_article) {
        if (!isset($local_slugs[$slug])) {
            $they_have_we_dont[] = $remote_article;
        }
    }
    
    // Find articles we have that they don't (local has, remote doesn't)
    $we_have_they_dont = [];
    foreach ($local_slugs as $slug => $local_article) {
        if (!isset($remote_slugs[$slug])) {
            $we_have_they_dont[] = $local_article;
        }
    }
    
    // Find potential duplicates (same title, different slug)
    $potential_duplicates = [];
    foreach ($remote_titles as $title => $remote_article) {
        if (isset($local_titles[$title])) {
            $local_article = $local_titles[$title];
            // Same title but different slug = potential duplicate
            if (strtolower(trim($remote_article['slug'])) !== strtolower(trim($local_article['slug']))) {
                $potential_duplicates[] = [
                    'local' => $local_article,
                    'remote' => $remote_article
                ];
            }
        }
    }
    
    $result = [
        'summary' => [
            'local_total' => count($local_articles),
            'remote_total' => count($remote_articles),
            'exact_matches' => count($exact_matches),
            'they_have_we_dont' => count($they_have_we_dont),
            'we_have_they_dont' => count($we_have_they_dont),
            'potential_duplicates' => count($potential_duplicates)
        ]
    ];
    
    if ($type !== 'summary_only') {
        $result['they_have_we_dont'] = $they_have_we_dont;
        $result['we_have_they_dont'] = $we_have_they_dont;
        $result['potential_duplicates'] = $potential_duplicates;
        
        if ($type === 'all') {
            $result['exact_matches'] = $exact_matches;
        }
    }
    
    return $result;
}

function verify_api_key($request) {
    $api_key = $request->get_param('api_key');
    return verify_api_key_value($api_key);
}

function verify_api_key_value($api_key) {
    $valid_api_key = '9J4K2L8M5N7P0Q3R'; // Same as in your sync class
    return $api_key === $valid_api_key;
}
