<?php
if (!defined('ABSPATH')) exit;

class YTFlix_Activator {

    public static function activate() {
        self::create_tables();
        self::create_default_options();
        self::register_post_types();
        flush_rewrite_rules();
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = [];

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ytflix_user_progress (
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

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ytflix_transcripts (
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

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ytflix_favorites (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            video_post_id BIGINT(20) UNSIGNED NOT NULL,
            added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_video (user_id, video_post_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $query) {
            dbDelta($query);
        }

        update_option('ytflix_db_version', YTFLIX_DB_VERSION);
    }

    private static function create_default_options() {
        $defaults = [
            'ytflix_api_key'          => '',
            'ytflix_channel_id'       => '',
            'ytflix_playlist_ids'     => '',
            'ytflix_video_slug'       => 'watch',
            'ytflix_playlist_slug'    => 'series',
            'ytflix_hero_image'       => '',
            'ytflix_channel_logo'     => '',
            'ytflix_hero_title'       => 'Welcome to YTFlix',
            'ytflix_hero_description' => 'Your favorite videos, Netflix style.',
            'ytflix_cache_duration'   => 3600,
            'ytflix_transcript_cache_ttl' => 604800,
            'ytflix_cache_version'   => 0,
            'ytflix_enable_transcripts' => '1',
            'ytflix_enable_history'   => '1',
            'ytflix_enable_favorites' => '1',
            'ytflix_enable_autoplay'  => '1',
            'ytflix_enable_pip'       => '1',
            'ytflix_accent_color'     => '#c17a2f',
            'ytflix_sync_interval'    => 'daily',
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    private static function register_post_types() {
        $video_slug = get_option('ytflix_video_slug', 'watch');
        $playlist_slug = get_option('ytflix_playlist_slug', 'series');

        register_post_type('ytflix_video', [
            'public'  => true,
            'rewrite' => ['slug' => $video_slug, 'with_front' => false],
        ]);
        register_post_type('ytflix_playlist', [
            'public'  => true,
            'rewrite' => ['slug' => $playlist_slug, 'with_front' => false],
        ]);
        register_taxonomy('ytflix_genre', ['ytflix_video', 'ytflix_playlist'], [
            'public'       => true,
            'hierarchical' => true,
            'rewrite'      => ['slug' => 'genre'],
        ]);
    }
}
