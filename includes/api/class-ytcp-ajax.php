<?php
if (!defined('ABSPATH')) exit;

class YTCP_Ajax {

    public function search() {
        check_ajax_referer('ytcp_nonce', 'nonce');
        $query = sanitize_text_field($_GET['q'] ?? '');
        if (empty($query)) {
            wp_send_json_success(['results' => []]);
        }

        $videos = get_posts([
            'post_type'      => 'ytcp_video',
            'posts_per_page' => 12,
            's'              => $query,
            'post_status'    => 'publish',
        ]);

        $playlists = get_posts([
            'post_type'      => 'ytcp_playlist',
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
                'thumbnail' => get_post_meta($v->ID, '_ytcp_thumbnail', true),
                'duration'  => get_post_meta($v->ID, '_ytcp_duration_formatted', true),
                'youtube_id' => get_post_meta($v->ID, '_ytcp_youtube_id', true),
                'permalink' => get_permalink($v->ID),
            ];
        }

        foreach ($playlists as $p) {
            $results[] = [
                'type'      => 'playlist',
                'id'        => $p->ID,
                'title'     => $p->post_title,
                'thumbnail' => get_post_meta($p->ID, '_ytcp_thumbnail', true),
                'count'     => get_post_meta($p->ID, '_ytcp_video_count', true),
                'permalink' => get_permalink($p->ID),
            ];
        }

        header('Cache-Control: public, max-age=120');
        wp_send_json_success(['results' => $results, 'query' => $query]);
    }

    public function save_progress() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        check_ajax_referer('ytcp_nonce', 'nonce');

        $video_id = absint($_POST['video_id'] ?? 0);
        $current_time = (float)($_POST['current_time'] ?? 0);
        $duration = (float)($_POST['duration'] ?? 0);

        if (!$video_id) {
            wp_send_json_error('Invalid video ID');
        }

        $progress = new YTCP_User_Progress();
        $progress->save_progress(get_current_user_id(), $video_id, $current_time, $duration);

        wp_send_json_success(['saved' => true]);
    }

    public function toggle_favorite() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        check_ajax_referer('ytcp_nonce', 'nonce');

        global $wpdb;
        $video_id = absint($_POST['video_id'] ?? 0);
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'ytcp_favorites';

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

    public function get_playlist_row() {
        $playlist_id = absint($_GET['playlist_id'] ?? 0);
        if (!$playlist_id) {
            wp_send_json_error('Invalid playlist ID');
        }

        $video_ids = get_post_meta($playlist_id, '_ytcp_video_ids', true);
        if (empty($video_ids)) {
            wp_send_json_success(['html' => '']);
        }

        $videos = get_posts([
            'post_type'      => 'ytcp_video',
            'post__in'       => $video_ids,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
        ]);

        if (empty($videos)) {
            wp_send_json_success(['html' => '']);
        }

        $playlist = get_post($playlist_id);
        $row_index = 0;

        ob_start();
        include YTCP_PLUGIN_DIR . 'templates/partials/playlist-row.php';
        $html = ob_get_clean();

        header('Cache-Control: public, max-age=300');
        wp_send_json_success(['html' => $html]);
    }

    public function get_transcript() {
        $video_id = absint($_GET['video_id'] ?? 0);
        $language = sanitize_text_field($_GET['lang'] ?? 'en');

        if (!$video_id) {
            wp_send_json_error('Invalid video ID');
        }

        $transcript = new YTCP_Transcript();
        $data = $transcript->get_transcript($video_id, $language);
        $languages = $transcript->get_available_languages($video_id);

        header('Cache-Control: public, max-age=86400');
        wp_send_json_success([
            'transcript' => $data,
            'languages'  => $languages,
        ]);
    }
}
