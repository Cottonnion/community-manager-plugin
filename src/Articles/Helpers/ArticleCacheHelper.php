<?php

namespace LABGENZ_CM\Articles\Helpers;

/**
 * Helper class for caching article-related data.
 */
class ArticleCacheHelper {

	/**
	 * Cache group for article-related caches
	 */
	public const CACHE_GROUP = 'labgenz_articles';

	/**
	 * Default cache expiration time (1 hour)
	 */
	public const DEFAULT_EXPIRATION = 3600;

	/**
	 * Cache key prefixes
	 */
	public const PREFIX_CATEGORIES = 'categories_';
	public const PREFIX_AUTHORS    = 'authors_';
	public const PREFIX_COUNTS     = 'counts_';
	public const PREFIX_SEARCH     = 'search_';
	public const PREFIX_RATINGS    = 'ratings_';

	/**
	 * Get data from cache or execute callback if not cached
	 *
	 * @param string   $key Cache key
	 * @param callable $callback Function to execute if cache miss
	 * @param int      $expiration Cache expiration time in seconds
	 * @return mixed Cached data or callback result
	 */
	public static function get_or_set( string $key, callable $callback, int $expiration = self::DEFAULT_EXPIRATION ) {
		$cache_key   = self::build_cache_key( $key );
		$cached_data = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached_data ) {
			return $cached_data;
		}

		$data = call_user_func( $callback );
		wp_cache_set( $cache_key, $data, self::CACHE_GROUP, $expiration );

		return $data;
	}

	/**
	 * Set data in cache
	 *
	 * @param string $key Cache key
	 * @param mixed  $data Data to cache
	 * @param int    $expiration Cache expiration time in seconds
	 * @return bool True on success, false on failure
	 */
	public static function set( string $key, $data, int $expiration = self::DEFAULT_EXPIRATION ): bool {
		$cache_key = self::build_cache_key( $key );
		return wp_cache_set( $cache_key, $data, self::CACHE_GROUP, $expiration );
	}

	/**
	 * Get data from cache
	 *
	 * @param string $key Cache key
	 * @return mixed Cached data or false if not found
	 */
	public static function get( string $key ) {
		$cache_key = self::build_cache_key( $key );
		return wp_cache_get( $cache_key, self::CACHE_GROUP );
	}

	/**
	 * Delete specific cache entry
	 *
	 * @param string $key Cache key
	 * @return bool True on success, false on failure
	 */
	public static function delete( string $key ): bool {
		$cache_key = self::build_cache_key( $key );
		return wp_cache_delete( $cache_key, self::CACHE_GROUP );
	}

	/**
	 * Clear all article-related caches
	 *
	 * @return bool True on success
	 */
	public static function clear_all(): bool {
		// WordPress doesn't have a native way to clear by group,
		// so we'll clear specific known cache keys
		$prefixes = [
			self::PREFIX_CATEGORIES,
			self::PREFIX_AUTHORS,
			self::PREFIX_COUNTS,
			self::PREFIX_SEARCH,
			self::PREFIX_RATINGS,
		];

		$success = true;

		foreach ( $prefixes as $prefix ) {
			$success &= self::clear_by_prefix( $prefix );
		}

		// Also clear any transients we might be using
		self::clear_transients();

		return $success;
	}

	/**
	 * Clear caches by specific prefix
	 *
	 * @param string $prefix Cache key prefix
	 * @return bool True on success
	 */
	public static function clear_by_prefix( string $prefix ): bool {
		global $wp_object_cache;

		if ( ! isset( $wp_object_cache->cache[ self::CACHE_GROUP ] ) ) {
			return true;
		}

		$cache_group = $wp_object_cache->cache[ self::CACHE_GROUP ];
		$success     = true;

		foreach ( array_keys( $cache_group ) as $key ) {
			if ( strpos( $key, $prefix ) === 0 ) {
				$success &= wp_cache_delete( $key, self::CACHE_GROUP );
			}
		}

		return $success;
	}

	/**
	 * Clear article-related transients
	 */
	private static function clear_transients(): void {
		$transient_keys = [
			'labgenz_article_categories',
			'labgenz_article_authors',
			'labgenz_article_counts',
			'labgenz_article_ratings',
		];

		foreach ( $transient_keys as $key ) {
			delete_transient( $key );
		}
	}

	/**
	 * Build cache key with prefix
	 *
	 * @param string $key Base cache key
	 * @return string Full cache key
	 */
	private static function build_cache_key( string $key ): string {
		return sanitize_key( $key );
	}

	/**
	 * Get cache key for categories
	 *
	 * @param string $post_type Post type
	 * @return string Cache key
	 */
	public static function get_categories_cache_key( string $post_type ): string {
		return self::PREFIX_CATEGORIES . $post_type;
	}

	/**
	 * Get cache key for authors
	 *
	 * @param string $post_type Post type
	 * @return string Cache key
	 */
	public static function get_authors_cache_key( string $post_type ): string {
		return self::PREFIX_AUTHORS . $post_type;
	}

	/**
	 * Get cache key for counts by meta
	 *
	 * @param string $meta_key Meta key
	 * @param string $post_type Post type
	 * @return string Cache key
	 */
	public static function get_counts_cache_key( string $meta_key, string $post_type ): string {
		return self::PREFIX_COUNTS . $meta_key . '_' . $post_type;
	}

	/**
	 * Get cache key for search results
	 *
	 * @param array $args Search arguments
	 * @return string Cache key
	 */
	public static function get_search_cache_key( array $args ): string {
		$key_parts = [
			self::PREFIX_SEARCH,
			md5( serialize( $args ) ),
		];

		return implode( '_', $key_parts );
	}

	/**
	 * Get cache key for rating counts
	 *
	 * @param array $authors Selected authors
	 * @param array $categories Selected categories
	 * @param array $ratings Selected ratings
	 * @return string Cache key
	 */
	public static function get_ratings_cache_key( array $authors = [], array $categories = [], array $ratings = [] ): string {
		$key_parts = [
			self::PREFIX_RATINGS,
			md5( serialize( [ $authors, $categories, $ratings ] ) ),
		];

		return implode( '_', $key_parts );
	}

	/**
	 * Invalidate cache when articles are updated
	 * Hook this to post save/delete actions
	 *
	 * @param int      $post_id Post ID
	 * @param \WP_Post $post Post object
	 */
	public static function invalidate_on_post_change( int $post_id, \WP_Post $post ): void {
		// Only clear cache for our article post type
		if ( $post->post_type !== 'mlmmc_artiicle' ) {
			return;
		}

		// Clear all article caches when an article is modified
		self::clear_all();
	}

	/**
	 * Initialize cache invalidation hooks
	 */
	public static function init_hooks(): void {
		// Clear cache when articles are saved/updated/deleted
		// add_action( 'save_post_mlmmc_artiicle', [ __CLASS__, 'invalidate_on_post_change' ], 10, 2 );
		// add_action( 'delete_post', [ __CLASS__, 'invalidate_on_post_change' ], 10, 2 );
		// add_action( 'wp_trash_post', [ __CLASS__, 'invalidate_on_post_change' ], 10, 2 );
		// add_action( 'untrash_post', [ __CLASS__, 'invalidate_on_post_change' ], 10, 2 );

		// Clear cache when post meta is updated
		// add_action( 'updated_post_meta', [ __CLASS__, 'invalidate_on_meta_change' ], 10, 4 );
		// add_action( 'added_post_meta', [ __CLASS__, 'invalidate_on_meta_change' ], 10, 4 );
		// add_action( 'deleted_post_meta', [ __CLASS__, 'invalidate_on_meta_change' ], 10, 4 );
	}

	/**
	 * Invalidate cache when article meta is changed
	 *
	 * @param int    $meta_id Meta ID
	 * @param int    $post_id Post ID
	 * @param string $meta_key Meta key
	 * @param mixed  $meta_value Meta value
	 */
	public static function invalidate_on_meta_change( int $meta_id, int $post_id, string $meta_key, $meta_value ): void {
		$post = get_post( $post_id );

		if ( ! $post || $post->post_type !== 'mlmmc_artiicle' ) {
			return;
		}

		// Only clear cache for article-related meta keys
		$article_meta_keys = [
			'mlmmc_article_category',
			'mlmmc_article_author',
			'mlmmc_video_link',
			'mlmmc_author_photo',
		];

		if ( in_array( $meta_key, $article_meta_keys, true ) ) {
			self::clear_all();
		}
	}

	/**
	 * Clear all article caches (useful for debugging or manual cache clearing)
	 */
	public function clear_article_caches(): bool {
		return self::clear_all();
	}

	/**
	 * AJAX handler to clear caches
	 */
	public function ajax_clear_caches(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions' ] );
			wp_die();
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'clear_article_caches' ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed' ] );
			wp_die();
		}

		$success = $this->clear_article_caches();

		if ( $success ) {
			wp_send_json_success( [ 'message' => 'Article caches cleared successfully' ] );
		} else {
			wp_send_json_error( [ 'message' => 'Failed to clear some caches' ] );
		}

		wp_die();
	}
}
