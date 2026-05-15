<?php
/**
 * Fired during plugin activation.
 *
 * @package CraftsmenitVideoPlatformForYouTube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation tasks: DB creation, migration, and post type registration.
 */
class YTCP_Activator {

	/**
	 * Runs all activation routines.
	 *
	 * @return void
	 */
	public static function activate() {
		self::migrate_from_ytflix();
		self::create_tables();
		self::create_default_options();
		self::register_post_types();
		flush_rewrite_rules();
	}

	/**
	 * Migrates data from the legacy ytflix plugin tables and options.
	 *
	 * @return void
	 */
	private static function migrate_from_ytflix() {
		global $wpdb;

		if ( get_option( 'ytcp_migrated_from_ytflix' ) ) {
			return;
		}

		$old_table = $wpdb->prefix . 'ytflix_user_progress';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Table name built from trusted $wpdb->prefix.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) ) === $old_table ) {
			$tables = array(
				'ytflix_user_progress' => 'ytcp_user_progress',
				'ytflix_transcripts'   => 'ytcp_transcripts',
				'ytflix_favorites'     => 'ytcp_favorites',
			);
			foreach ( $tables as $old_suffix => $new_suffix ) {
				$old = $wpdb->prefix . $old_suffix;
				$new = $wpdb->prefix . $new_suffix;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Table names from trusted $wpdb->prefix.
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old ) ) === $old ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- RENAME TABLE cannot use placeholders; names are trusted.
					$wpdb->query( "RENAME TABLE `{$old}` TO `{$new}`" );
				}
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'ytcp_video' WHERE post_type = 'ytflix_video'" );
			$wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'ytcp_playlist' WHERE post_type = 'ytflix_playlist'" );
			$wpdb->query( "UPDATE {$wpdb->term_taxonomy} SET taxonomy = 'ytcp_genre' WHERE taxonomy = 'ytflix_genre'" );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$old_options = $wpdb->get_results(
				"SELECT option_id, option_name FROM {$wpdb->options} WHERE option_name LIKE 'ytflix\_%' AND option_name NOT LIKE '\\_transient%'"
			);
			foreach ( $old_options as $opt ) {
				$new_name = preg_replace( '/^ytflix_/', 'ytcp_', $opt->option_name );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update( $wpdb->options, array( 'option_name' => $new_name ), array( 'option_id' => $opt->option_id ) );
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"UPDATE {$wpdb->options} SET option_name = REPLACE(option_name, '_transient_ytflix_', '_transient_ytcp_') WHERE option_name LIKE '\\_transient\\_ytflix\\_%'"
			);
			$wpdb->query(
				"UPDATE {$wpdb->options} SET option_name = REPLACE(option_name, '_transient_timeout_ytflix_', '_transient_timeout_ytcp_') WHERE option_name LIKE '\\_transient\\_timeout\\_ytflix\\_%'"
			);
			$wpdb->query(
				"UPDATE {$wpdb->postmeta} SET meta_key = REPLACE(meta_key, '_ytflix_', '_ytcp_') WHERE meta_key LIKE '\\_ytflix\\_%'"
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			wp_clear_scheduled_hook( 'ytflix_sync_cron' );
			wp_clear_scheduled_hook( 'ytflix_transcript_sync_cron' );
		}

		update_option( 'ytcp_migrated_from_ytflix', '1' );
	}

	/**
	 * Creates the custom database tables required by the plugin.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = array();

		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ytcp_user_progress (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            video_post_id BIGINT(20) UNSIGNED NOT NULL,
            youtube_id VARCHAR(20) NOT NULL,
            current_time FLOAT NOT NULL DEFAULT 0,
            duration FLOAT NOT NULL DEFAULT 0,
            completed TINYINT(1) NOT NULL DEFAULT 0,
            last_watched DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_video (user_id, video_post_id),
            KEY user_id (user_id),
            KEY last_watched (last_watched)
        ) $charset_collate;";

		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ytcp_transcripts (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            video_post_id BIGINT(20) UNSIGNED NOT NULL,
            youtube_id VARCHAR(20) NOT NULL,
            language_code VARCHAR(10) NOT NULL DEFAULT 'en',
            language_name VARCHAR(100) NOT NULL DEFAULT 'English',
            content LONGTEXT NOT NULL,
            fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY video_lang (video_post_id, language_code),
            KEY youtube_id (youtube_id)
        ) $charset_collate;";

		$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ytcp_favorites (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            video_post_id BIGINT(20) UNSIGNED NOT NULL,
            added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_video (user_id, video_post_id),
            KEY user_id (user_id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		update_option( 'ytcp_db_version', YTCP_DB_VERSION );
	}

	/**
	 * Registers default plugin options on first activation.
	 *
	 * @return void
	 */
	private static function create_default_options() {
		$defaults = array(
			'ytcp_api_key'              => '',
			'ytcp_channel_id'           => '',
			'ytcp_playlist_ids'         => '',
			'ytcp_video_slug'           => 'watch',
			'ytcp_playlist_slug'        => 'series',
			'ytcp_hero_image'           => '',
			'ytcp_channel_logo'         => '',
			'ytcp_hero_title'           => 'Welcome to YTChannel Pro',
			'ytcp_hero_description'     => 'Your favorite videos, Netflix style.',
			'ytcp_cache_duration'       => 3600,
			'ytcp_transcript_cache_ttl' => 604800,
			'ytcp_cache_version'        => 0,
			'ytcp_enable_transcripts'   => '1',
			'ytcp_enable_history'       => '1',
			'ytcp_enable_favorites'     => '1',
			'ytcp_enable_autoplay'      => '1',
			'ytcp_enable_pip'           => '1',
			'ytcp_accent_color'         => '#c17a2f',
			'ytcp_sync_interval'        => 'daily',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Registers the plugin's custom post types during activation.
	 *
	 * @return void
	 */
	private static function register_post_types() {
		$video_slug    = get_option( 'ytcp_video_slug', 'watch' );
		$playlist_slug = get_option( 'ytcp_playlist_slug', 'series' );

		register_post_type(
			'ytcp_video',
			array(
				'public'  => true,
				'rewrite' => array(
					'slug'       => $video_slug,
					'with_front' => false,
				),
			)
		);
		register_post_type(
			'ytcp_playlist',
			array(
				'public'  => true,
				'rewrite' => array(
					'slug'       => $playlist_slug,
					'with_front' => false,
				),
			)
		);
		register_taxonomy(
			'ytcp_genre',
			array( 'ytcp_video', 'ytcp_playlist' ),
			array(
				'public'       => true,
				'hierarchical' => true,
				'rewrite'      => array( 'slug' => 'genre' ),
			)
		);
	}
}
