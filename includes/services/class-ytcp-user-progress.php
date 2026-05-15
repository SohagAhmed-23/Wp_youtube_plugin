<?php
/**
 * Tracks and retrieves per-user video watch progress.
 *
 * @package CraftsmenitVideoPlatformForYouTube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the ytcp_user_progress database table.
 */
class YTCP_User_Progress {

	/**
	 * The fully qualified DB table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor — sets the table name.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'ytcp_user_progress';
	}

	/**
	 * Returns whether the progress table exists in the database.
	 *
	 * @return bool
	 */
	private function table_exists() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table ) ) === $this->table;
	}

	/**
	 * Saves or updates a user's watch progress for a video.
	 *
	 * @param int   $user_id       The WordPress user ID.
	 * @param int   $video_post_id The video post ID.
	 * @param float $current_time  Current playback position in seconds.
	 * @param float $duration      Total video duration in seconds.
	 * @return bool
	 */
	public function save_progress( $user_id, $video_post_id, $current_time, $duration ) {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return false;
		}

		$youtube_id = get_post_meta( $video_post_id, '_ytcp_youtube_id', true );
		$completed  = ( $duration > 0 && $current_time >= ( $duration * 0.9 ) ) ? 1 : 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			$this->table,
			array(
				'user_id'       => $user_id,
				'video_post_id' => $video_post_id,
				'youtube_id'    => $youtube_id,
				'current_time'  => $current_time,
				'duration'      => $duration,
				'completed'     => $completed,
				'last_watched'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%f', '%f', '%d', '%s' )
		);

		return true;
	}

	/**
	 * Retrieves the watch progress row for a specific user and video.
	 *
	 * @param int $user_id       The WordPress user ID.
	 * @param int $video_post_id The video post ID.
	 * @return object|null
	 */
	public function get_progress( $user_id, $video_post_id ) {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return null;
		}

		$table = $this->table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE user_id = %d AND video_post_id = %d",
				$user_id,
				$video_post_id
			)
		);
	}

	/**
	 * Returns videos the user started but has not completed.
	 *
	 * @param int $user_id The WordPress user ID.
	 * @param int $limit   Maximum number of results.
	 * @return array
	 */
	public function get_continue_watching( $user_id, $limit = 20 ) {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return array();
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, up.current_time as progress_time, up.duration as progress_duration, up.last_watched
              FROM {$this->table} up
              JOIN {$wpdb->posts} p ON p.ID = up.video_post_id
              WHERE up.user_id = %d AND up.completed = 0 AND up.current_time > 5
              AND p.post_status = 'publish'
              ORDER BY up.last_watched DESC
              LIMIT %d",
				$user_id,
				$limit
			)
		);
		// phpcs:enable
	}

	/**
	 * Returns the full watch history for a user, newest first.
	 *
	 * @param int $user_id The WordPress user ID.
	 * @param int $limit   Maximum number of records to return.
	 * @param int $offset  Offset for pagination.
	 * @return array
	 */
	public function get_watch_history( $user_id, $limit = 50, $offset = 0 ) {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return array();
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, up.current_time as progress_time, up.duration as progress_duration, up.completed, up.last_watched
              FROM {$this->table} up
              JOIN {$wpdb->posts} p ON p.ID = up.video_post_id
              WHERE up.user_id = %d AND p.post_status = 'publish'
              ORDER BY up.last_watched DESC
              LIMIT %d OFFSET %d",
				$user_id,
				$limit,
				$offset
			)
		);
		// phpcs:enable
	}

	/**
	 * Returns the most recently watched videos for a user.
	 *
	 * @param int $user_id The WordPress user ID.
	 * @param int $limit   Maximum number of records.
	 * @return array
	 */
	public function get_recently_watched( $user_id, $limit = 10 ) {
		return $this->get_watch_history( $user_id, $limit );
	}

	/**
	 * Returns the watch completion percentage (0–100) for a user and video.
	 *
	 * @param int $user_id       The WordPress user ID.
	 * @param int $video_post_id The video post ID.
	 * @return int
	 */
	public function get_progress_percentage( $user_id, $video_post_id ) {
		$progress = $this->get_progress( $user_id, $video_post_id );
		if ( ! $progress || $progress->duration <= 0 ) {
			return 0;
		}
		return min( 100, round( ( $progress->current_time / $progress->duration ) * 100 ) );
	}
}
