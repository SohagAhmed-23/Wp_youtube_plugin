<?php
if (!defined('ABSPATH')) exit;

class YTFlix_REST_API {

    private $namespace = 'ytflix/v1';

    public function register_routes() {
        register_rest_route($this->namespace, '/videos', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_videos'],
            'permission_callback' => '__return_true',
            'args' => [
                'per_page' => ['default' => 20, 'sanitize_callback' => 'absint'],
                'page'     => ['default' => 1, 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route($this->namespace, '/videos/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_video'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => ['validate_callback' => function($p) { return is_numeric($p); }],
            ],
        ]);

        register_rest_route($this->namespace, '/playlists', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_playlists'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/playlists/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_playlist'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/search', [
            'methods'             => 'GET',
            'callback'            => [$this, 'search'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route($this->namespace, '/progress', [
            'methods'             => 'POST',
            'callback'            => [$this, 'save_progress'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        register_rest_route($this->namespace, '/progress', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_progress'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        register_rest_route($this->namespace, '/favorites', [
            'methods'             => 'POST',
            'callback'            => [$this, 'toggle_favorite'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        register_rest_route($this->namespace, '/favorites', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_favorites'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);

        register_rest_route($this->namespace, '/transcripts/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_transcript'],
            'permission_callback' => '__return_true',
            'args' => [
                'lang' => ['default' => 'en', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function check_logged_in() {
        return is_user_logged_in();
    }

    private function add_cache_headers($response, $max_age, $private = false) {
        $directive = $private ? 'private' : 'public';
        $response->header('Cache-Control', "$directive, max-age=$max_age");
        return $response;
    }

    public function get_videos($request) {
        $per_page = $request->get_param('per_page');
        $page = $request->get_param('page');

        $query = new WP_Query([
            'post_type'      => 'ytflix_video',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
        ]);

        $videos = array_map([$this, 'format_video'], $query->posts);

        $resp = new WP_REST_Response([
            'videos' => $videos,
            'total'  => $query->found_posts,
            'pages'  => $query->max_num_pages,
        ]);
        return $this->add_cache_headers($resp, 300);
    }

    public function get_video($request) {
        $post = get_post($request['id']);
        if (!$post || $post->post_type !== 'ytflix_video') {
            return new WP_Error('not_found', 'Video not found', ['status' => 404]);
        }

        $video = $this->format_video($post);

        $playlist_id = get_post_meta($post->ID, '_ytflix_playlist_id', true);
        if ($playlist_id) {
            $video['playlist'] = $this->format_playlist(get_post($playlist_id));
            $video['playlist_videos'] = $this->get_playlist_videos($playlist_id);
        }

        if (is_user_logged_in()) {
            $progress = new YTFlix_User_Progress();
            $p = $progress->get_progress(get_current_user_id(), $post->ID);
            $video['progress'] = $p ? ['current_time' => (float)$p->current_time, 'duration' => (float)$p->duration] : null;
        }

        $resp = new WP_REST_Response($video);
        return $this->add_cache_headers($resp, 600);
    }

    public function get_playlists($request) {
        $playlists = get_posts([
            'post_type'      => 'ytflix_playlist',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $result = [];
        foreach ($playlists as $pl) {
            $formatted = $this->format_playlist($pl);
            $formatted['videos'] = $this->get_playlist_videos($pl->ID);
            $result[] = $formatted;
        }

        $resp = new WP_REST_Response($result);
        return $this->add_cache_headers($resp, 3600);
    }

    public function get_playlist($request) {
        $post = get_post($request['id']);
        if (!$post || $post->post_type !== 'ytflix_playlist') {
            return new WP_Error('not_found', 'Playlist not found', ['status' => 404]);
        }

        $formatted = $this->format_playlist($post);
        $formatted['videos'] = $this->get_playlist_videos($post->ID);

        $resp = new WP_REST_Response($formatted);
        return $this->add_cache_headers($resp, 3600);
    }

    public function search($request) {
        $query = $request->get_param('q');

        $videos = get_posts([
            'post_type'      => 'ytflix_video',
            'posts_per_page' => 20,
            's'              => $query,
            'post_status'    => 'publish',
        ]);

        $resp = new WP_REST_Response([
            'results' => array_map([$this, 'format_video'], $videos),
            'query'   => $query,
        ]);
        return $this->add_cache_headers($resp, 120);
    }

    public function save_progress($request) {
        $video_id = absint($request->get_param('video_id'));
        $current_time = (float) $request->get_param('current_time');
        $duration = (float) $request->get_param('duration');

        $progress = new YTFlix_User_Progress();
        $progress->save_progress(get_current_user_id(), $video_id, $current_time, $duration);

        return $this->add_cache_headers(new WP_REST_Response(['success' => true]), 0, true);
    }

    public function get_progress($request) {
        $progress = new YTFlix_User_Progress();
        $continue = $progress->get_continue_watching(get_current_user_id());
        $result = [];

        foreach ($continue as $item) {
            $v = $this->format_video($item);
            $v['progress'] = [
                'current_time' => (float)$item->progress_time,
                'duration'     => (float)$item->progress_duration,
            ];
            $result[] = $v;
        }

        return $this->add_cache_headers(new WP_REST_Response($result), 0, true);
    }

    public function toggle_favorite($request) {
        global $wpdb;
        $video_id = absint($request->get_param('video_id'));
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'ytflix_favorites';

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND video_post_id = %d",
            $user_id, $video_id
        ));

        if ($exists) {
            $wpdb->delete($table, ['id' => $exists], ['%d']);
            return new WP_REST_Response(['favorited' => false]);
        }

        $wpdb->insert($table, [
            'user_id'       => $user_id,
            'video_post_id' => $video_id,
            'added_at'      => current_time('mysql'),
        ], ['%d', '%d', '%s']);

        return new WP_REST_Response(['favorited' => true]);
    }

    public function get_favorites($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'ytflix_favorites';

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT video_post_id FROM $table WHERE user_id = %d ORDER BY added_at DESC",
            $user_id
        ));

        if (empty($ids)) return new WP_REST_Response([]);

        $videos = get_posts([
            'post_type'      => 'ytflix_video',
            'post__in'       => $ids,
            'orderby'        => 'post__in',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
        ]);

        return $this->add_cache_headers(new WP_REST_Response(array_map([$this, 'format_video'], $videos)), 0, true);
    }

    public function get_transcript($request) {
        $transcript_svc = new YTFlix_Transcript();
        $lang = $request->get_param('lang');
        $data = $transcript_svc->get_transcript($request['id'], $lang);
        $languages = $transcript_svc->get_available_languages($request['id']);

        $resp = new WP_REST_Response([
            'transcript' => $data,
            'languages'  => $languages,
        ]);
        return $this->add_cache_headers($resp, 86400);
    }

    private function format_video($post) {
        return [
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'description' => wp_trim_words($post->post_content, 30),
            'youtube_id'  => get_post_meta($post->ID, '_ytflix_youtube_id', true),
            'thumbnail'   => get_post_meta($post->ID, '_ytflix_thumbnail', true),
            'duration'    => (int) get_post_meta($post->ID, '_ytflix_duration', true),
            'duration_fmt' => get_post_meta($post->ID, '_ytflix_duration_formatted', true),
            'view_count'  => (int) get_post_meta($post->ID, '_ytflix_view_count', true),
            'like_count'  => (int) get_post_meta($post->ID, '_ytflix_like_count', true),
            'position'    => (int) get_post_meta($post->ID, '_ytflix_position', true),
            'permalink'   => get_permalink($post->ID),
            'date'        => $post->post_date,
        ];
    }

    private function format_playlist($post) {
        if (!$post) return null;
        return [
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'description' => $post->post_content,
            'youtube_id'  => get_post_meta($post->ID, '_ytflix_youtube_id', true),
            'thumbnail'   => get_post_meta($post->ID, '_ytflix_thumbnail', true),
            'video_count' => (int) get_post_meta($post->ID, '_ytflix_video_count', true),
            'permalink'   => get_permalink($post->ID),
        ];
    }

    private function get_playlist_videos($playlist_id) {
        $video_ids = get_post_meta($playlist_id, '_ytflix_video_ids', true);
        if (empty($video_ids)) return [];

        $videos = get_posts([
            'post_type'      => 'ytflix_video',
            'post__in'       => $video_ids,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'posts_per_page' => 100,
            'post_status'    => 'publish',
        ]);

        return array_map([$this, 'format_video'], $videos);
    }
}
