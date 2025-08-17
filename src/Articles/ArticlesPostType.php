<?php
/**
 * Plugin Name: MLMC Articles
 * Description: Custom post type and FacetWP integration for MLMC Articles.
 * Version: 1.0
 * Author: MLMC
 */

namespace LABGENZ_CM\Articles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArticlesPostType {
	/**
	 * Initialize the custom post type and FacetWP filters
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register_mlmmc_articles_post_type' ] );
		add_filter( 'facetwp_indexer_query_args', [ $this, 'facetwp_indexer_query_args' ] );
		add_filter( 'facetwp_index_row', [ $this, 'facetwp_adjust_date_field_timezone' ], 10, 2 );
	}

	/**
	 * Register the custom post type 'mlmmc_artiicle'
	 */
	public function register_mlmmc_articles_post_type(): void {
		$labels = [
			'name'               => 'MLMC Articles',
			'singular_name'      => 'MLMC Article',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New MLMC Article',
			'edit_item'          => 'Edit MLMC Article',
			'new_item'           => 'New MLMC Article',
			'view_item'          => 'View MLMC Article',
			'search_items'       => 'Search MLMC Articles',
			'not_found'          => 'No MLMC Articles found',
			'not_found_in_trash' => 'No MLMC Articles found in Trash',
			'menu_name'          => 'MLMC Articles',
		];

		$args = [
			'labels'       => $labels,
			'public'       => true,
			'has_archive'  => true,
			'rewrite'      => [ 'slug' => 'mlmmc-articles' ],
			'supports'     => [ 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ],
			'show_in_rest' => true,
		];

		register_post_type( 'mlmmc_artiicle', $args );
	}

	/**
	 * FacetWP: Only index MLMC Articles
	 *
	 * @param array $args Query arguments for FacetWP indexer
	 * @return array Modified query arguments
	 */
	public function facetwp_indexer_query_args( array $args ): array {
		$args['post_type'] = [ 'mlmmc_artiicle' ];
		return $args;
	}

	/**
	 * FacetWP: Adjust date field timezone for indexing
	 *
	 * @param array  $row Row data for FacetWP indexer
	 * @param object $model FacetWP model object
	 * @return array Modified row data
	 */
	public function facetwp_adjust_date_field_timezone( array $row, $model ): array {
		if ( isset( $row['post_id'] ) ) {
			$post_type = get_post_type( $row['post_id'] );
			if ( $post_type === 'mlmmc_artiicle' ) {
				$date_field = get_field( 'mlmmc_date_article_featured', $row['post_id'] );
				if ( $date_field ) {
					$date = new \DateTime( $date_field, new \DateTimeZone( 'UTC' ) );
					$date->setTimezone( new \DateTimeZone( 'America/Los_Angeles' ) );
					$row['mlmmc_date_article_featured'] = $date->format( 'Ymd' );
				}
			}
		}
		return $row;
	}
}
