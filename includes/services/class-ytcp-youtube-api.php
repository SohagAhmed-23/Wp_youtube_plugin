<?php
/**
 * YouTube Data API v3 wrapper with caching, quota tracking, and stale-data fallback.
 *
 * @package YTChannelProNetflixStyleYoutubePlatform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps the YouTube Data API v3, adding transient caching, ETag support, and quota tracking.
 */
class YTCP_YouTube_API {

	/**
	 * YouTube Data API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Base URL for all YouTube API requests.
	 *
	 * @var string
	 */
	private $base_url = 'https://www.googleapis.com/youtube/v3/';

	/**
	 * Per-endpoint cache durations in seconds.
	 *
	 * @var array
	 */
	private $endpoint_ttls = array(
		'channels'      => 43200,
		'playlists'     => 21600,
		'playlistItems' => 10800,
		'videos'        => 3600,
		'captions'      => 86400,
		'search'        => 1800,
	);

	/**
	 * Per-endpoint YouTube quota unit costs.
	 *
	 * @var array
	 */
	private $endpoint_quota_costs = array(
		'channels'      => 1,
		'playlists'     => 1,
		'playlistItems' => 1,
		'videos'        => 1,
		'captions'      => 50,
		'search'        => 100,
	);

	/**
	 * Constructor — loads the API key from WordPress options.
	 */
	public function __construct() {
		$this->api_key = get_option( 'ytcp_api_key', '' );
	}

	/**
	 * Returns whether the YouTube API key is configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->api_key );
	}

	/**
	 * Returns the cache TTL for a given endpoint.
	 *
	 * @param string $endpoint The YouTube API endpoint name.
	 * @return int Duration in seconds.
	 */
	private function get_cache_duration( $endpoint ) {
		$base_duration = (int) get_option( 'ytcp_cache_duration', 3600 );
		if ( isset( $this->endpoint_ttls[ $endpoint ] ) ) {
			return $this->endpoint_ttls[ $endpoint ];
		}
		return $base_duration;
	}

	/**
	 * Makes a cached request to the YouTube API.
	 *
	 * @param string $endpoint API endpoint (e.g. 'videos', 'playlists').
	 * @param array  $params   Query parameters to include.
	 * @return array|WP_Error
	 */
	private function request( $endpoint, $params = array() ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'no_api_key', __( 'YouTube API key not configured.', 'ytchannel-pro-netflix-style-youtube-platform' ) );
		}

		$params['key']  = $this->api_key;
		$url            = $this->base_url . $endpoint . '?' . http_build_query( $params );
		$cache_key      = 'ytcp_' . md5( $url );
		$etag_key       = 'ytcp_etag_' . md5( $url );
		$stale_key      = 'ytcp_stale_' . md5( $url );
		$cache_duration = $this->get_cache_duration( $endpoint );

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$headers     = array( 'Referer' => home_url() );
		$stored_etag = get_option( $etag_key, '' );
		if ( ! empty( $stored_etag ) ) {
			$headers['If-None-Match'] = $stored_etag;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_api_warning( $endpoint, 'request_failed', $response->get_error_message() );
			return $this->get_stale_or_error( $stale_key, $response );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 304 === $code ) {
			$stale_data = get_option( $stale_key, null );
			if ( null !== $stale_data ) {
				$data = maybe_unserialize( $stale_data );
				set_transient( $cache_key, $data, $cache_duration );
				$this->track_api_call( $endpoint, 0 );
				return $data;
			}
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 403 === $code ) {
			$reason = $body['error']['errors'][0]['reason'] ?? '';
			if ( 'quotaExceeded' === $reason ) {
				$this->handle_quota_exceeded();
				return $this->get_stale_or_error( $stale_key, new WP_Error( 'quota_exceeded', 'YouTube API quota exceeded' ) );
			}
		}

		if ( 200 !== $code ) {
			$message = $body['error']['message'] ?? 'Unknown YouTube API error';
			$error   = new WP_Error( 'youtube_api_error', $message, array( 'status' => $code ) );
			$this->log_api_warning( $endpoint, 'http_' . $code, $message );
			return $this->get_stale_or_error( $stale_key, $error );
		}

		$etag = wp_remote_retrieve_header( $response, 'etag' );
		if ( ! empty( $etag ) ) {
			update_option( $etag_key, $etag, false );
		}

		update_option( $stale_key, $body, false );
		set_transient( $cache_key, $body, $cache_duration );

		$quota_cost = $this->endpoint_quota_costs[ $endpoint ] ?? 1;
		$this->track_api_call( $endpoint, $quota_cost );

		return $body;
	}

	/**
	 * Returns stale cached data if available, otherwise returns the provided error.
	 *
	 * @param string   $stale_key Option key under which stale data is stored.
	 * @param WP_Error $error     The error to return if no stale data exists.
	 * @return array|WP_Error
	 */
	private function get_stale_or_error( $stale_key, $error ) {
		$stale_data = get_option( $stale_key, null );
		if ( null !== $stale_data ) {
			$this->log_api_warning( 'fallback', 'serving_stale', 'Serving stale data due to API error' );
			return maybe_unserialize( $stale_data );
		}
		return $error;
	}

	/**
	 * Records an API call and its quota cost against today's usage totals.
	 *
	 * @param string $endpoint   The API endpoint name.
	 * @param int    $quota_cost Quota units consumed by the call.
	 * @return void
	 */
	private function track_api_call( $endpoint, $quota_cost ) {
		$today = gmdate( 'Y-m-d' );
		$stats = get_option( 'ytcp_api_stats', array() );

		if ( ! isset( $stats[ $today ] ) ) {
			$stats = array(
				$today => array(
					'total_calls' => 0,
					'total_quota' => 0,
					'endpoints'   => array(),
				),
			);
		}

		++$stats[ $today ]['total_calls'];
		$stats[ $today ]['total_quota'] += $quota_cost;

		if ( ! isset( $stats[ $today ]['endpoints'][ $endpoint ] ) ) {
			$stats[ $today ]['endpoints'][ $endpoint ] = array(
				'calls' => 0,
				'quota' => 0,
			);
		}
		++$stats[ $today ]['endpoints'][ $endpoint ]['calls'];
		$stats[ $today ]['endpoints'][ $endpoint ]['quota'] += $quota_cost;

		update_option( 'ytcp_api_stats', $stats, false );
	}

	/**
	 * Flags the quota as exceeded and records the time.
	 *
	 * @return void
	 */
	private function handle_quota_exceeded() {
		update_option( 'ytcp_quota_exceeded', current_time( 'mysql' ), false );
		$this->log_api_warning( 'quota', 'exceeded', 'YouTube API daily quota exceeded at ' . current_time( 'mysql' ) );
	}

	/**
	 * Appends a warning entry to the stored API warnings log.
	 *
	 * @param string $endpoint The API endpoint where the warning occurred.
	 * @param string $type     Short warning type identifier.
	 * @param string $message  Human-readable warning message.
	 * @return void
	 */
	private function log_api_warning( $endpoint, $type, $message ) {
		$warnings   = get_option( 'ytcp_api_warnings', array() );
		$warnings[] = array(
			'time'     => current_time( 'mysql' ),
			'endpoint' => $endpoint,
			'type'     => $type,
			'message'  => $message,
		);
		$warnings   = array_slice( $warnings, -50 );
		update_option( 'ytcp_api_warnings', $warnings, false );
	}

	/**
	 * Fetches channel metadata from the YouTube Data API.
	 *
	 * @param string $channel_id Optional channel ID; defaults to the saved option.
	 * @return array|WP_Error
	 */
	public function get_channel_info( $channel_id = '' ) {
		if ( empty( $channel_id ) ) {
			$channel_id = get_option( 'ytcp_channel_id', '' );
		}

		return $this->request(
			'channels',
			array(
				'part' => 'snippet,contentDetails,statistics,brandingSettings',
				'id'   => $channel_id,
			)
		);
	}

	/**
	 * Returns all playlists for a YouTube channel.
	 *
	 * @param string $channel_id  Optional channel ID; defaults to the saved option.
	 * @param int    $max_results Maximum number of playlists to return.
	 * @param string $page_token  Pagination token for the next results page.
	 * @return array|WP_Error
	 */
	public function get_playlists( $channel_id = '', $max_results = 25, $page_token = '' ) {
		if ( empty( $channel_id ) ) {
			$channel_id = get_option( 'ytcp_channel_id', '' );
		}

		$params = array(
			'part'       => 'snippet,contentDetails',
			'channelId'  => $channel_id,
			'maxResults' => $max_results,
		);

		if ( $page_token ) {
			$params['pageToken'] = $page_token;
		}

		return $this->request( 'playlists', $params );
	}

	/**
	 * Returns a single playlist by its YouTube playlist ID.
	 *
	 * @param string $playlist_id The YouTube playlist ID.
	 * @return array|WP_Error
	 */
	public function get_playlist_by_id( $playlist_id ) {
		return $this->request(
			'playlists',
			array(
				'part' => 'snippet,contentDetails',
				'id'   => $playlist_id,
			)
		);
	}

	/**
	 * Returns the items (videos) within a YouTube playlist.
	 *
	 * @param string $playlist_id  The YouTube playlist ID.
	 * @param int    $max_results  Maximum number of items per page.
	 * @param string $page_token   Pagination token.
	 * @return array|WP_Error
	 */
	public function get_playlist_items( $playlist_id, $max_results = 50, $page_token = '' ) {
		$params = array(
			'part'       => 'snippet,contentDetails',
			'playlistId' => $playlist_id,
			'maxResults' => $max_results,
		);

		if ( $page_token ) {
			$params['pageToken'] = $page_token;
		}

		return $this->request( 'playlistItems', $params );
	}

	/**
	 * Returns detailed metadata for one or more YouTube videos.
	 *
	 * @param array|string $video_ids Comma-separated list or array of YouTube video IDs.
	 * @return array|WP_Error
	 */
	public function get_video_details( $video_ids ) {
		if ( is_array( $video_ids ) ) {
			$video_ids = implode( ',', $video_ids );
		}

		return $this->request(
			'videos',
			array(
				'part' => 'snippet,contentDetails,statistics',
				'id'   => $video_ids,
			)
		);
	}

	/**
	 * Returns the captions list for a YouTube video.
	 *
	 * @param string $video_id The YouTube video ID.
	 * @return array|WP_Error
	 */
	public function get_captions_list( $video_id ) {
		return $this->request(
			'captions',
			array(
				'part'    => 'snippet',
				'videoId' => $video_id,
			)
		);
	}

	/**
	 * Searches for videos matching a query string, optionally filtered to a channel.
	 *
	 * @param string $query      The search query.
	 * @param string $channel_id Optional channel ID to scope the search.
	 * @param int    $max_results Maximum number of results.
	 * @return array|WP_Error
	 */
	public function search_videos( $query, $channel_id = '', $max_results = 20 ) {
		if ( empty( $channel_id ) ) {
			$channel_id = get_option( 'ytcp_channel_id', '' );
		}

		$params = array(
			'part'       => 'snippet',
			'q'          => $query,
			'type'       => 'video',
			'maxResults' => $max_results,
		);

		if ( ! empty( $channel_id ) ) {
			$params['channelId'] = $channel_id;
		}

		return $this->request( 'search', $params );
	}

	/**
	 * Deletes all plugin transients, ETags, stale backups, and quota tracking options.
	 *
	 * @return void
	 */
	public function clear_cache() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				'_transient_ytcp_%',
				'_transient_timeout_ytcp_%',
				'ytcp_etag_%',
				'ytcp_stale_%'
			)
		);
		delete_option( 'ytcp_api_stats' );
		delete_option( 'ytcp_api_warnings' );
		delete_option( 'ytcp_quota_exceeded' );
	}

	/**
	 * Returns the stored API usage statistics.
	 *
	 * @return array
	 */
	public function get_api_stats() {
		return get_option( 'ytcp_api_stats', array() );
	}

	/**
	 * Returns the stored API warning log entries.
	 *
	 * @return array
	 */
	public function get_api_warnings() {
		return get_option( 'ytcp_api_warnings', array() );
	}

	/**
	 * Returns counts of cached transients, ETags, stale backups, and transcripts.
	 *
	 * @return array
	 */
	public function get_cache_stats() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$transient_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_ytcp_%' AND option_name NOT LIKE '_transient_timeout_%'"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$etag_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'ytcp_etag_%'"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$stale_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'ytcp_stale_%'"
		);

		$transcript_count = 0;
		$table            = $wpdb->prefix . 'ytcp_transcripts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$transcript_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		return array(
			'transients'  => $transient_count,
			'etags'       => $etag_count,
			'stale'       => $stale_count,
			'transcripts' => $transcript_count,
		);
	}

	/**
	 * Parses an ISO 8601 duration string (PT#H#M#S) into total seconds.
	 *
	 * @param string $duration The ISO 8601 duration string.
	 * @return int Total seconds.
	 */
	public static function parse_duration( $duration ) {
		$interval = new DateInterval( $duration );
		$seconds  = ( $interval->h * 3600 ) + ( $interval->i * 60 ) + $interval->s;
		return $seconds;
	}

	/**
	 * Formats a duration in seconds as a human-readable time string (H:MM:SS or M:SS).
	 *
	 * @param int $seconds Total duration in seconds.
	 * @return string
	 */
	public static function format_duration( $seconds ) {
		$hours   = floor( $seconds / 3600 );
		$minutes = floor( ( $seconds % 3600 ) / 60 );
		$secs    = $seconds % 60;

		if ( $hours > 0 ) {
			return sprintf( '%d:%02d:%02d', $hours, $minutes, $secs );
		}
		return sprintf( '%d:%02d', $minutes, $secs );
	}

	/**
	 * Formats a view count as a short human-readable string (e.g. 1.2M or 350K).
	 *
	 * @param int $count The raw view count.
	 * @return string
	 */
	public static function format_view_count( $count ) {
		if ( 1000000 <= $count ) {
			return round( $count / 1000000, 1 ) . 'M';
		}
		if ( 1000 <= $count ) {
			return round( $count / 1000, 1 ) . 'K';
		}
		return number_format( $count );
	}
}
