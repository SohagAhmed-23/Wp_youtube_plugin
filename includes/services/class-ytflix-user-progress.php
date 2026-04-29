<?php
if (!defined('ABSPATH')) exit;

class YTFlix_User_Progress {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'ytflix_user_progress';
    }

    private function table_exists() {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table)) === $this->table;
    }

    public function save_progress($user_id, $video_post_id, $current_time, $duration) {
        global $wpdb;
        if (!$this->table_exists()) return false;

        $youtube_id = get_post_meta($video_post_id, '_ytflix_youtube_id', true);
        $completed = ($duration > 0 && $current_time >= ($duration * 0.9)) ? 1 : 0;

        $wpdb->replace($this->table, [
            'user_id'       => $user_id,
            'video_post_id' => $video_post_id,
            'youtube_id'    => $youtube_id,
            'current_time'  => $current_time,
            'duration'      => $duration,
            'completed'     => $completed,
            'last_watched'  => current_time('mysql'),
        ], ['%d', '%d', '%s', '%f', '%f', '%d', '%s']);

        return true;
    }

    public function get_progress($user_id, $video_post_id) {
        global $wpdb;
        if (!$this->table_exists()) return null;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = %d AND video_post_id = %d",
            $user_id,
            $video_post_id
        ));
    }

    public function get_continue_watching($user_id, $limit = 20) {
        global $wpdb;
        if (!$this->table_exists()) return [];

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, up.current_time as progress_time, up.duration as progress_duration, up.last_watched
             FROM {$this->table} up
             JOIN {$wpdb->posts} p ON p.ID = up.video_post_id
             WHERE up.user_id = %d AND up.completed = 0 AND up.current_time > 5
             AND p.post_status = 'publish'
             ORDER BY up.last_watched DESC
             LIMIT %d",
            $user_id,
            $limit
        ));

        return $results;
    }

    public function get_watch_history($user_id, $limit = 50, $offset = 0) {
        global $wpdb;
        if (!$this->table_exists()) return [];

        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, up.current_time as progress_time, up.duration as progress_duration, up.completed, up.last_watched
             FROM {$this->table} up
             JOIN {$wpdb->posts} p ON p.ID = up.video_post_id
             WHERE up.user_id = %d AND p.post_status = 'publish'
             ORDER BY up.last_watched DESC
             LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        ));
    }

    public function get_recently_watched($user_id, $limit = 10) {
        return $this->get_watch_history($user_id, $limit);
    }

    public function get_progress_percentage($user_id, $video_post_id) {
        $progress = $this->get_progress($user_id, $video_post_id);
        if (!$progress || $progress->duration <= 0) return 0;
        return min(100, round(($progress->current_time / $progress->duration) * 100));
    }
}
