<?php
/**
 * Recommendation engine — handles trending, personalized, and related video logic.
 *
 * @package CraftsmenitVideoPlatformForYouTube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles logic for suggesting videos based on views, user history, and taxonomy terms.
 */
class YTCP_Recommendations {

	/**
	 * Returns the most-viewed videos.
	 *
	 * @param int $limit Maximum number of results.
	 * @return array
	 */
	public function get_trending( $limit = 20 ) {
		return get_posts(
			array(
				'post_type'      => 'ytcp_video',
				'posts_per_page' => $limit,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => '_ytcp_view_count',
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
				'post_status'    => 'publish',
			)
		);
	}

	/**
	 * Returns videos suggested for a specific user based on their watch history.
	 *
	 * @param int $user_id The WordPress user ID.
	 * @param int $limit   Maximum number of results.
	 * @return array
	 */
	public function get_recommended_for_user( $user_id, $limit = 20 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$watched_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT video_post_id FROM {$wpdb->prefix}ytcp_user_progress WHERE user_id = %d ORDER BY last_watched DESC LIMIT 20",
				$user_id
			)
		);

		if ( empty( $watched_ids ) ) {
			return $this->get_trending( $limit );
		}

		$genre_ids = array();
		foreach ( $watched_ids as $vid ) {
			$terms = wp_get_object_terms( $vid, 'ytcp_genre', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				$genre_ids = array_merge( $genre_ids, $terms );
			}
		}

		$genre_ids = array_unique( $genre_ids );
		if ( empty( $genre_ids ) ) {
			return $this->get_trending( $limit );
		}

		return get_posts(
			array(
				'post_type'      => 'ytcp_video',
				'posts_per_page' => $limit,
				'post__not_in'   => $watched_ids,
				'post_status'    => 'publish',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'      => array(
					array(
						'taxonomy' => 'ytcp_genre',
						'terms'    => array_slice( $genre_ids, 0, 10 ),
						'operator' => 'IN',
					),
				),
				'orderby'        => 'rand',
			)
		);
	}

	/**
	 * Returns videos related to a specific video via genre taxonomy.
	 *
	 * @param int $video_post_id The source video post ID.
	 * @param int $limit         Maximum number of results.
	 * @return array
	 */
	public function get_related_videos( $video_post_id, $limit = 10 ) {
		$terms = wp_get_object_terms( $video_post_id, 'ytcp_genre', array( 'fields' => 'ids' ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return get_posts(
				array(
					'post_type'      => 'ytcp_video',
					'posts_per_page' => $limit,
					'post__not_in'   => array( $video_post_id ),
					'orderby'        => 'rand',
					'post_status'    => 'publish',
				)
			);
		}

		return get_posts(
			array(
				'post_type'      => 'ytcp_video',
				'posts_per_page' => $limit,
				'post__not_in'   => array( $video_post_id ),
				'post_status'    => 'publish',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'      => array(
					array(
						'taxonomy' => 'ytcp_genre',
						'terms'    => $terms,
						'operator' => 'IN',
					),
				),
			)
		);
	}

	/**
	 * Returns the most recently published videos.
	 *
	 * @param int $limit Maximum number of results.
	 * @return array
	 */
	public function get_new_releases( $limit = 20 ) {
		return get_posts(
			array(
				'post_type'      => 'ytcp_video',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'post_status'    => 'publish',
			)
		);
	}
}
