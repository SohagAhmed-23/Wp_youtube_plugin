<?php
if (!defined('ABSPATH')) exit;

class YTFlix_Ajax {

    public function search() {
        $query = sanitize_text_field($_GET['q'] ?? '');
        if (empty($query)) {
            wp_send_json_success(['results' => []]);
        }

        $videos = get_posts([
            'post_type'      => 'ytflix_video',
            'posts_per_page' => 12,
            's'              => $query,
            'post_status'    => 'publish',
        ]);

        $playlists = get_posts([
            'post_type'      => 'ytflix_playlist',
            'posts_per_page' => 5,
            's'              => $query,
            'post_status'    => 'publish',
        ]);

        $results = [];

        foreach ($videos as $v) {
            $results[] = [
                'type'      => 'video',
                'id'        => $v->ID,
                'title'     => $v->post_title,
                'thumbnail' => get_post_meta($v->ID, '_ytflix_thumbnail', true),
                'duration'  => get_post_meta($v->ID, '_ytflix_duration_formatted', true),
                'youtube_id' => get_post_meta($v->ID, '_ytflix_youtube_id', true),
                'permalink' => get_permalink($v->ID),
            ];
        }

        foreach ($playlists as $p) {
            $results[] = [
                'type'      => 'playlist',
                'id'        => $p->ID,
                'title'     => $p->post_title,
                'thumbnail' => get_post_meta($p->ID, '_ytflix_thumbnail', true),
                'count'     => get_post_meta($p->ID, '_ytflix_video_count', true),
                'permalink' => get_permalink($p->ID),
            ];
        }

        wp_send_json_success(['results' => $results, 'query' => $query]);
    }

    public function save_progress() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        check_ajax_referer('ytflix_nonce', 'nonce');

        $video_id = absint($_POST['video_id'] ?? 0);
        $current_time = (float)($_POST['current_time'] ?? 0);
        $duration = (float)($_POST['duration'] ?? 0);

        if (!$video_id) {
            wp_send_json_error('Invalid video ID');
        }

        $progress = new YTFlix_User_Progress();
        $progress->save_progress(get_current_user_id(), $video_id, $current_time, $duration);

        wp_send_json_success(['saved' => true]);
    }

    public function toggle_favorite() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        check_ajax_referer('ytflix_nonce', 'nonce');

        global $wpdb;
        $video_id = absint($_POST['video_id'] ?? 0);
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'ytflix_favorites';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND video_post_id = %d",
            $user_id, $video_id
        ));

        if ($exists) {
            $wpdb->delete($table, ['id' => $exists], ['%d']);
            wp_send_json_success(['favorited' => false]);
        } else {
            $wpdb->insert($table, [
                'user_id'       => $user_id,
                'video_post_id' => $video_id,
                'added_at'      => current_time('mysql'),
            ], ['%d', '%d', '%s']);
            wp_send_json_success(['favorited' => true]);
        }
    }

    public function get_transcript() {
        $video_id = absint($_GET['video_id'] ?? 0);
        $language = sanitize_text_field($_GET['lang'] ?? 'en');

        if (!$video_id) {
            wp_send_json_error('Invalid video ID');
        }

        $transcript = new YTFlix_Transcript();
        $data = $transcript->get_transcript($video_id, $language);
        $languages = $transcript->get_available_languages($video_id);

        wp_send_json_success([
            'transcript' => $data,
            'languages'  => $languages,
        ]);
    }
}
