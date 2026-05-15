<?php
/**
 * Fetches, caches, and serves video transcripts from YouTube.
 *
 * @package CraftsmenitVideoPlatformForYouTube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use MrMySQL\YoutubeTranscript\TranscriptListFetcher;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * Manages transcript fetching, storage, and retrieval for ytcp_video posts.
 */
class YTCP_Transcript {

	/**
	 * Creates and returns a transcript fetcher instance.
	 *
	 * @return TranscriptListFetcher
	 */
	private function get_fetcher() {
		$http_client     = new Client( array( 'timeout' => 20 ) );
		$request_factory = new HttpFactory();
		$stream_factory  = new HttpFactory();

		return new TranscriptListFetcher( $http_client, $request_factory, $stream_factory );
	}

	/**
	 * Returns whether the transcripts table exists.
	 *
	 * @return bool
	 */
	private function table_exists() {
		global $wpdb;
		$table = $wpdb->prefix . 'ytcp_transcripts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Returns the transcript for a video, fetching from YouTube if not cached.
	 *
	 * @param int    $video_post_id WordPress post ID of the video.
	 * @param string $language      BCP-47 language code (e.g. 'en').
	 * @return array
	 */
	public function get_transcript( $video_post_id, $language = 'en' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ytcp_transcripts';

		if ( ! $this->table_exists() ) {
			$youtube_id = get_post_meta( $video_post_id, '_ytcp_youtube_id', true );
			if ( empty( $youtube_id ) ) {
				return array();
			}
			return $this->fetch_from_youtube( $youtube_id, $language );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cached = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE video_post_id = %d AND language_code = %s",
				$video_post_id,
				$language
			)
		);

		if ( $cached ) {
			$fetched = strtotime( $cached->fetched_at );
			$ttl     = (int) get_option( 'ytcp_transcript_cache_ttl', 604800 );
			if ( ( time() - $fetched ) < $ttl ) {
				return json_decode( $cached->content, true );
			}
		}

		$youtube_id = get_post_meta( $video_post_id, '_ytcp_youtube_id', true );
		if ( empty( $youtube_id ) ) {
			return array();
		}

		$transcript = $this->fetch_from_youtube( $youtube_id, $language );

		if ( ! empty( $transcript ) ) {
			$this->save_transcript( $video_post_id, $youtube_id, $language, $transcript );
		}

		return $transcript;
	}

	/**
	 * Fetches transcript captions from the YouTube API.
	 *
	 * @param string $youtube_id The YouTube video ID.
	 * @param string $language   BCP-47 language code.
	 * @return array
	 */
	private function fetch_from_youtube( $youtube_id, $language = 'en' ) {
		try {
			$fetcher         = $this->get_fetcher();
			$transcript_list = $fetcher->fetch( $youtube_id );

			$transcript = $transcript_list->findTranscript( array( $language, 'en', 'bn' ) );
			$entries    = $transcript->fetch();

			$captions = array();
			foreach ( $entries as $entry ) {
				$text = trim( $entry['text'] ?? '' );
				if ( empty( $text ) ) {
					continue;
				}

				$start    = (float) ( $entry['start'] ?? 0 );
				$duration = (float) ( $entry['duration'] ?? 0 );

				$captions[] = array(
					'start'    => round( $start, 2 ),
					'end'      => round( $start + $duration, 2 ),
					'duration' => round( $duration, 2 ),
					'text'     => sanitize_text_field( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ),
				);
			}

			return $captions;
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'YTCP Transcript Error [' . $youtube_id . ']: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Saves a transcript to the database.
	 *
	 * @param int    $video_post_id WordPress post ID.
	 * @param string $youtube_id    YouTube video ID.
	 * @param string $language      BCP-47 language code.
	 * @param array  $transcript    Array of caption entries.
	 * @return void
	 */
	private function save_transcript( $video_post_id, $youtube_id, $language, $transcript ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ytcp_transcripts';

		$lang_names = array(
			'en' => 'English',
			'es' => 'Spanish',
			'fr' => 'French',
			'de' => 'German',
			'pt' => 'Portuguese',
			'hi' => 'Hindi',
			'ja' => 'Japanese',
			'ko' => 'Korean',
			'zh' => 'Chinese',
			'ar' => 'Arabic',
			'ru' => 'Russian',
			'it' => 'Italian',
			'bn' => 'Bengali',
			'ur' => 'Urdu',
			'ta' => 'Tamil',
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			$table,
			array(
				'video_post_id' => $video_post_id,
				'youtube_id'    => $youtube_id,
				'language_code' => $language,
				'language_name' => $lang_names[ $language ] ?? ucfirst( $language ),
				'content'       => wp_json_encode( $transcript ),
				'fetched_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Returns a list of languages for which a transcript is stored.
	 *
	 * @param int $video_post_id WordPress post ID.
	 * @return array
	 */
	public function get_available_languages( $video_post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ytcp_transcripts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT language_code, language_name FROM {$table} WHERE video_post_id = %d ORDER BY language_name",
				$video_post_id
			)
		);
	}

	/**
	 * Syncs transcripts for videos that have none or have stale ones.
	 *
	 * @param int $batch_size Maximum number of transcripts to fetch per run.
	 * @return int Number of transcripts fetched.
	 */
	public function sync_all_transcripts( $batch_size = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ytcp_transcripts';
		$ttl   = (int) get_option( 'ytcp_transcript_cache_ttl', 604800 );

		$all_videos = get_posts(
			array(
				'post_type'      => 'ytcp_video',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			)
		);

		$needs_fetch = array();
		foreach ( $all_videos as $vid_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$cached = $wpdb->get_row(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT fetched_at FROM {$table} WHERE video_post_id = %d AND language_code = 'en'",
					$vid_id
				)
			);

			if ( ! $cached || ( time() - strtotime( $cached->fetched_at ) ) >= $ttl ) {
				$needs_fetch[] = $vid_id;
			}

			if ( count( $needs_fetch ) >= $batch_size ) {
				break;
			}
		}

		$fetched = 0;
		foreach ( $needs_fetch as $vid_id ) {
			$this->get_transcript( $vid_id, 'en' );
			++$fetched;
			if ( $fetched < count( $needs_fetch ) ) {
				sleep( 1 );
			}
		}

		return $fetched;
	}

	/**
	 * Exports a transcript as plain text or JSON.
	 *
	 * @param int    $video_post_id WordPress post ID.
	 * @param string $language      BCP-47 language code.
	 * @param string $format        Output format: 'txt' or 'json'.
	 * @return string
	 */
	public function export_transcript( $video_post_id, $language = 'en', $format = 'txt' ) {
		$transcript = $this->get_transcript( $video_post_id, $language );
		if ( empty( $transcript ) ) {
			return '';
		}

		if ( 'json' === $format ) {
			return wp_json_encode( $transcript, JSON_PRETTY_PRINT );
		}

		$output = '';
		foreach ( $transcript as $entry ) {
			$time    = gmdate( 'H:i:s', (int) $entry['start'] );
			$output .= "[{$time}] {$entry['text']}\n";
		}
		return $output;
	}
}
