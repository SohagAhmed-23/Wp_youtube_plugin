<?php
if (!defined('ABSPATH')) exit;

class YTFlix_YouTube_API {

    private $api_key;
    private $base_url = 'https://www.googleapis.com/youtube/v3/';

    public function __construct() {
        $this->api_key = get_option('ytflix_api_key', '');
    }

    public function is_configured() {
        return !empty($this->api_key);
    }

    private function request($endpoint, $params = []) {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', __('YouTube API key not configured.', 'ytflix'));
        }

        $params['key'] = $this->api_key;
        $url = $this->base_url . $endpoint . '?' . http_build_query($params);

        $cache_key = 'ytflix_' . md5($url);
        $cache_duration = (int) get_option('ytflix_cache_duration', 3600);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => ['Referer' => home_url()],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $message = $body['error']['message'] ?? 'Unknown YouTube API error';
            return new WP_Error('youtube_api_error', $message, ['status' => $code]);
        }

        set_transient($cache_key, $body, $cache_duration);
        return $body;
    }

    public function get_channel_info($channel_id = '') {
        if (empty($channel_id)) {
            $channel_id = get_option('ytflix_channel_id', '');
        }

        return $this->request('channels', [
            'part' => 'snippet,statistics,brandingSettings',
            'id'   => $channel_id,
        ]);
    }

    public function get_playlists($channel_id = '', $max_results = 25, $page_token = '') {
        if (empty($channel_id)) {
            $channel_id = get_option('ytflix_channel_id', '');
        }

        $params = [
            'part'       => 'snippet,contentDetails',
            'channelId'  => $channel_id,
            'maxResults' => $max_results,
        ];

        if ($page_token) {
            $params['pageToken'] = $page_token;
        }

        return $this->request('playlists', $params);
    }

    public function get_playlist_by_id($playlist_id) {
        return $this->request('playlists', [
            'part' => 'snippet,contentDetails',
            'id'   => $playlist_id,
        ]);
    }

    public function get_playlist_items($playlist_id, $max_results = 50, $page_token = '') {
        $params = [
            'part'       => 'snippet,contentDetails',
            'playlistId' => $playlist_id,
            'maxResults' => $max_results,
        ];

        if ($page_token) {
            $params['pageToken'] = $page_token;
        }

        return $this->request('playlistItems', $params);
    }

    public function get_video_details($video_ids) {
        if (is_array($video_ids)) {
            $video_ids = implode(',', $video_ids);
        }

        return $this->request('videos', [
            'part' => 'snippet,contentDetails,statistics',
            'id'   => $video_ids,
        ]);
    }

    public function get_captions_list($video_id) {
        return $this->request('captions', [
            'part'    => 'snippet',
            'videoId' => $video_id,
        ]);
    }

    public function search_videos($query, $channel_id = '', $max_results = 20) {
        if (empty($channel_id)) {
            $channel_id = get_option('ytflix_channel_id', '');
        }

        $params = [
            'part'       => 'snippet',
            'q'          => $query,
            'type'       => 'video',
            'maxResults' => $max_results,
        ];

        if (!empty($channel_id)) {
            $params['channelId'] = $channel_id;
        }

        return $this->request('search', $params);
    }

    public function clear_cache() {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_ytflix_%',
                '_transient_timeout_ytflix_%'
            )
        );
    }

    public static function parse_duration($duration) {
        $interval = new DateInterval($duration);
        $seconds = ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
        return $seconds;
    }

    public static function format_duration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%d:%02d', $minutes, $secs);
    }

    public static function format_view_count($count) {
        if ($count >= 1000000) {
            return round($count / 1000000, 1) . 'M';
        }
        if ($count >= 1000) {
            return round($count / 1000, 1) . 'K';
        }
        return number_format($count);
    }
}
