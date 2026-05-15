<?php
/**
 * Handles all WordPress AJAX endpoints for the plugin.
 *
 * @package CraftsmenitVideoPlatformForYouTube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handler class — processes all wp_ajax_* actions.
 */
class YTCP_Ajax {

	/**
	 * Handles the live search AJAX request.
	 *
	 * @return void
	 */
	public function search() {
		check_ajax_referer( 'ytcp_nonce', 'nonce' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotUnslashed -- sanitize_text_field handles slashing.
		$query = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
		if ( empty( $query ) ) {
			wp_send_json_success( array( 'results' => array() ) );
		}

		$videos = get_posts(
			array(
				'post_type'      => 'ytcp_video',
				'posts_per_page' => 12,
				's'              => $query,
				'post_status'    => 'publish',
			)
		);

		$playlists = get_posts(
			array(
				'post_type'      => 'ytcp_playlist',
				'posts_per_page' => 5,
				's'              => $query,
				'post_status'    => 'publish',
			)
		);

		$results = array();

		foreach ( $videos as $v ) {
			$results[] = array(
				'type'       => 'video',
				'id'         => $v->ID,
				'title'      => $v->post_title,
				'thumbnail'  => get_post_meta( $v->ID, '_ytcp_thumbnail', true ),
				'duration'   => get_post_meta( $v->ID, '_ytcp_duration_formatted', true ),
				'youtube_id' => get_post_meta( $v->ID, '_ytcp_youtube_id', true ),
				'permalink'  => get_permalink( $v->ID ),
			);
		}

		foreach ( $playlists as $p ) {
			$results[] = array(
				'type'      => 'playlist',
				'id'        => $p->ID,
				'title'     => $p->post_title,
				'thumbnail' => get_post_meta( $p->ID, '_ytcp_thumbnail', true ),
				'count'     => get_post_meta( $p->ID, '_ytcp_video_count', true ),
				'permalink' => get_permalink( $p->ID ),
			);
		}

		header( 'Cache-Control: public, max-age=120' );
		wp_send_json_success(
			array(
				'results' => $results,
				'query'   => $query,
			)
		);
	}

	/**
	 * Saves video watch progress for the current user.
	 *
	 * @return void
	 */
	public function save_progress() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in', 401 );
		}

		check_ajax_referer( 'ytcp_nonce', 'nonce' );

		$video_id     = absint( $_POST['video_id'] ?? 0 );
		$current_time = (float) sanitize_text_field( wp_unslash( $_POST['current_time'] ?? 0 ) );
		$duration     = (float) sanitize_text_field( wp_unslash( $_POST['duration'] ?? 0 ) );

		if ( ! $video_id ) {
			wp_send_json_error( 'Invalid video ID' );
		}

		$progress = new YTCP_User_Progress();
		$progress->save_progress( get_current_user_id(), $video_id, $current_time, $duration );

		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * Toggles the favorite status of a video for the current user.
	 *
	 * @return void
	 */
	public function toggle_favorite() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Not logged in', 401 );
		}

		check_ajax_referer( 'ytcp_nonce', 'nonce' );

		global $wpdb;
		$video_id = absint( $_POST['video_id'] ?? 0 );
		$user_id  = get_current_user_id();
		$table    = $wpdb->prefix . 'ytcp_favorites';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$table} WHERE user_id = %d AND video_post_id = %d",
				$user_id,
				$video_id
			)
		);

		if ( $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $table, array( 'id' => $exists ), array( '%d' ) );
			wp_send_json_success( array( 'favorited' => false ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'user_id'       => $user_id,
					'video_post_id' => $video_id,
					'added_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s' )
			);
			wp_send_json_success( array( 'favorited' => true ) );
		}
	}

	/**
	 * Returns a rendered playlist row HTML for lazy loading.
	 *
	 * @return void
	 */
	public function get_playlist_row() {
		// Nonce not required for public read endpoints — playlist content is public.
		$playlist_id = absint( $_GET['playlist_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $playlist_id ) {
			wp_send_json_error( 'Invalid playlist ID' );
		}

		$video_ids = get_post_meta( $playlist_id, '_ytcp_video_ids', true );
		if ( empty( $video_ids ) ) {
			wp_send_json_success( array( 'html' => '' ) );
		}

		$videos = get_posts(
			array(
				'post_type'      => 'ytcp_video',
				'post__in'       => $video_ids,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'posts_per_page' => 50,
				'post_status'    => 'publish',
			)
		);

		if ( empty( $videos ) ) {
			wp_send_json_success( array( 'html' => '' ) );
		}

		$playlist  = get_post( $playlist_id );
		$row_index = 0;

		ob_start();
		include YTCP_PLUGIN_DIR . 'templates/partials/playlist-row.php';
		$html = ob_get_clean();

		header( 'Cache-Control: public, max-age=300' );
		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Returns transcript data for a given video.
	 *
	 * @return void
	 */
	public function get_transcript() {
		// Nonce not required — transcript data is publicly accessible read-only content.
		$video_id = absint( $_GET['video_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$language = sanitize_text_field( wp_unslash( $_GET['lang'] ?? 'en' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $video_id ) {
			wp_send_json_error( 'Invalid video ID' );
		}

		$transcript = new YTCP_Transcript();
		$data       = $transcript->get_transcript( $video_id, $language );
		$languages  = $transcript->get_available_languages( $video_id );

		header( 'Cache-Control: public, max-age=86400' );
		wp_send_json_success(
			array(
				'transcript' => $data,
				'languages'  => $languages,
			)
		);
	}
}
