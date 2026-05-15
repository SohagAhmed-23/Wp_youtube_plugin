<?php
/**
 * Registers and handles all plugin REST API endpoints.
 *
 * @package CraftsmenitVideoPlatformForYouTube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API controller — registers routes under ytcp/v1.
 */
class YTCP_REST_API {

	/**
	 * The REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'ytcp/v1';

	/**
	 * Registers all plugin REST routes with WordPress.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/videos',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_videos' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/videos/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_video' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $p ) {
							return is_numeric( $p ); },
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/playlists',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_playlists' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/playlists/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_playlist' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/progress',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_progress' ),
				'permission_callback' => array( $this, 'check_logged_in' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/progress',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_progress' ),
				'permission_callback' => array( $this, 'check_logged_in' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/favorites',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'toggle_favorite' ),
				'permission_callback' => array( $this, 'check_logged_in' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/favorites',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_favorites' ),
				'permission_callback' => array( $this, 'check_logged_in' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/transcripts/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_transcript' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'lang' => array(
						'default'           => 'en',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Permission callback — ensures the current user is logged in.
	 *
	 * @return bool
	 */
	public function check_logged_in() {
		return is_user_logged_in();
	}

	/**
	 * Adds HTTP cache-control headers to a REST response.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param int              $max_age  Max-age in seconds.
	 * @param bool             $is_private Whether the response is private.
	 * @return WP_REST_Response
	 */
	private function add_cache_headers( $response, $max_age, $is_private = false ) {
		$directive = $is_private ? 'private' : 'public';
		$response->header( 'Cache-Control', "{$directive}, max-age={$max_age}" );
		return $response;
	}

	/**
	 * Returns a paginated list of videos.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function get_videos( $request ) {
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );

		$query = new WP_Query(
			array(
				'post_type'      => 'ytcp_video',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'post_status'    => 'publish',
			)
		);

		$videos = array_map( array( $this, 'format_video' ), $query->posts );

		$resp = new WP_REST_Response(
			array(
				'videos' => $videos,
				'total'  => $query->found_posts,
				'pages'  => $query->max_num_pages,
			)
		);
		return $this->add_cache_headers( $resp, 300 );
	}

	/**
	 * Returns a single video by post ID.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_video( $request ) {
		$post = get_post( $request['id'] );
		if ( ! $post || 'ytcp_video' !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'not_found', 'Video not found', array( 'status' => 404 ) );
		}

		$video = $this->format_video( $post );

		$playlist_id = get_post_meta( $post->ID, '_ytcp_playlist_id', true );
		if ( $playlist_id ) {
			$playlist = get_post( $playlist_id );
			if ( $playlist && 'publish' === $playlist->post_status ) {
				$video['playlist']        = $this->format_playlist( $playlist );
				$video['playlist_videos'] = $this->get_playlist_videos( $playlist_id );
			}
		}

		if ( is_user_logged_in() ) {
			$progress          = new YTCP_User_Progress();
			$p                 = $progress->get_progress( get_current_user_id(), $post->ID );
			$video['progress'] = $p ? array(
				'current_time' => (float) $p->current_time,
				'duration'     => (float) $p->duration,
			) : null;
		}

		$resp = new WP_REST_Response( $video );
		return $this->add_cache_headers( $resp, 600 );
	}

	/**
	 * Returns all published playlists with their videos.
	 *
	 * @param WP_REST_Request $request The REST request object (unused).
	 * @return WP_REST_Response
	 */
	public function get_playlists( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$playlists = get_posts(
			array(
				'post_type'      => 'ytcp_playlist',
				'posts_per_page' => 50,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$result = array();
		foreach ( $playlists as $pl ) {
			$formatted           = $this->format_playlist( $pl );
			$formatted['videos'] = $this->get_playlist_videos( $pl->ID );
			$result[]            = $formatted;
		}

		$resp = new WP_REST_Response( $result );
		return $this->add_cache_headers( $resp, 3600 );
	}

	/**
	 * Returns a single playlist by post ID.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_playlist( $request ) {
		$post = get_post( $request['id'] );
		if ( ! $post || 'ytcp_playlist' !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'not_found', 'Playlist not found', array( 'status' => 404 ) );
		}

		$formatted           = $this->format_playlist( $post );
		$formatted['videos'] = $this->get_playlist_videos( $post->ID );

		$resp = new WP_REST_Response( $formatted );
		return $this->add_cache_headers( $resp, 3600 );
	}

	/**
	 * Returns search results for videos matching a query string.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function search( $request ) {
		$query = $request->get_param( 'q' );

		$videos = get_posts(
			array(
				'post_type'      => 'ytcp_video',
				'posts_per_page' => 20,
				's'              => $query,
				'post_status'    => 'publish',
			)
		);

		$resp = new WP_REST_Response(
			array(
				'results' => array_map( array( $this, 'format_video' ), $videos ),
				'query'   => $query,
			)
		);
		return $this->add_cache_headers( $resp, 120 );
	}

	/**
	 * Saves video playback progress for the current user.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function save_progress( $request ) {
		$video_id     = absint( $request->get_param( 'video_id' ) );
		$current_time = (float) $request->get_param( 'current_time' );
		$duration     = (float) $request->get_param( 'duration' );

		$progress = new YTCP_User_Progress();
		$progress->save_progress( get_current_user_id(), $video_id, $current_time, $duration );

		return $this->add_cache_headers( new WP_REST_Response( array( 'success' => true ) ), 0, true );
	}

	/**
	 * Returns the "Continue Watching" list for the current user.
	 *
	 * @param WP_REST_Request $request The REST request object (unused).
	 * @return WP_REST_Response
	 */
	public function get_progress( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$progress = new YTCP_User_Progress();
		$continue = $progress->get_continue_watching( get_current_user_id() );
		$result   = array();

		foreach ( $continue as $item ) {
			$v             = $this->format_video( $item );
			$v['progress'] = array(
				'current_time' => (float) $item->progress_time,
				'duration'     => (float) $item->progress_duration,
			);
			$result[]      = $v;
		}

		return $this->add_cache_headers( new WP_REST_Response( $result ), 0, true );
	}

	/**
	 * Toggles the favorite status of a video for the current user.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response
	 */
	public function toggle_favorite( $request ) {
		global $wpdb;
		$video_id = absint( $request->get_param( 'video_id' ) );
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
			return new WP_REST_Response( array( 'favorited' => false ) );
		}

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

		return new WP_REST_Response( array( 'favorited' => true ) );
	}

	/**
	 * Returns the favorited videos for the current user.
	 *
	 * @param WP_REST_Request $request The REST request object (unused).
	 * @return WP_REST_Response
	 */
	public function get_favorites( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		global $wpdb;
		$user_id = get_current_user_id();
		$table   = $wpdb->prefix . 'ytcp_favorites';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT video_post_id FROM {$table} WHERE user_id = %d ORDER BY added_at DESC",
				$user_id
			)
		);

		if ( empty( $ids ) ) {
			return new WP_REST_Response( array() );
		}

		$videos = get_posts(
			array(
				'post_type'      => 'ytcp_video',
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => 50,
				'post_status'    => 'publish',
			)
		);

		return $this->add_cache_headers( new WP_REST_Response( array_map( array( $this, 'format_video' ), $videos ) ), 0, true );
	}

	/**
	 * Returns transcript data for a video.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_transcript( $request ) {
		// Verify the requested video exists and is published.
		$video_post = get_post( $request['id'] );
		if ( ! $video_post || 'ytcp_video' !== $video_post->post_type || 'publish' !== $video_post->post_status ) {
			return new WP_Error( 'not_found', 'Video not found', array( 'status' => 404 ) );
		}

		$transcript_svc = new YTCP_Transcript();
		$lang           = $request->get_param( 'lang' );
		$data           = $transcript_svc->get_transcript( $request['id'], $lang );
		$languages      = $transcript_svc->get_available_languages( $request['id'] );

		$resp = new WP_REST_Response(
			array(
				'transcript' => $data,
				'languages'  => $languages,
			)
		);
		return $this->add_cache_headers( $resp, 86400 );
	}

	/**
	 * Formats a video post object into a REST-friendly array.
	 *
	 * @param WP_Post|object $post The post object.
	 * @return array
	 */
	private function format_video( $post ) {
		return array(
			'id'           => $post->ID,
			'title'        => $post->post_title,
			'description'  => wp_trim_words( $post->post_content, 30 ),
			'youtube_id'   => get_post_meta( $post->ID, '_ytcp_youtube_id', true ),
			'thumbnail'    => get_post_meta( $post->ID, '_ytcp_thumbnail', true ),
			'duration'     => (int) get_post_meta( $post->ID, '_ytcp_duration', true ),
			'duration_fmt' => get_post_meta( $post->ID, '_ytcp_duration_formatted', true ),
			'view_count'   => (int) get_post_meta( $post->ID, '_ytcp_view_count', true ),
			'like_count'   => (int) get_post_meta( $post->ID, '_ytcp_like_count', true ),
			'position'     => (int) get_post_meta( $post->ID, '_ytcp_position', true ),
			'permalink'    => get_permalink( $post->ID ),
			'date'         => $post->post_date,
		);
	}

	/**
	 * Formats a playlist post object into a REST-friendly array.
	 *
	 * @param WP_Post|object|null $post The post object.
	 * @return array|null
	 */
	private function format_playlist( $post ) {
		if ( ! $post ) {
			return null;
		}
		return array(
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'description' => $post->post_content,
			'youtube_id'  => get_post_meta( $post->ID, '_ytcp_youtube_id', true ),
			'thumbnail'   => get_post_meta( $post->ID, '_ytcp_thumbnail', true ),
			'video_count' => (int) get_post_meta( $post->ID, '_ytcp_video_count', true ),
			'permalink'   => get_permalink( $post->ID ),
		);
	}

	/**
	 * Returns all formatted video objects belonging to a playlist.
	 *
	 * @param int $playlist_id The playlist post ID.
	 * @return array
	 */
	private function get_playlist_videos( $playlist_id ) {
		$video_ids = get_post_meta( $playlist_id, '_ytcp_video_ids', true );
		if ( empty( $video_ids ) ) {
			return array();
		}

		$videos = get_posts(
			array(
				'post_type'      => 'ytcp_video',
				'post__in'       => $video_ids,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'posts_per_page' => 100,
				'post_status'    => 'publish',
			)
		);

		return array_map( array( $this, 'format_video' ), $videos );
	}
}
