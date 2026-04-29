<?php
if (!defined('ABSPATH')) exit;

class YTFlix_YouTube_API {

    private $api_key;
    private $base_url = 'https://www.googleapis.com/youtube/v3/';

    private $endpoint_ttls = [
        'channels'      => 43200,
        'playlists'     => 21600,
        'playlistItems' => 10800,
        'videos'        => 3600,
        'captions'      => 86400,
        'search'        => 1800,
    ];

    private $endpoint_quota_costs = [
        'channels'      => 1,
        'playlists'     => 1,
        'playlistItems' => 1,
        'videos'        => 1,
        'captions'      => 50,
        'search'        => 100,
    ];

    public function __construct() {
        $this->api_key = get_option('ytflix_api_key', '');
    }

    public function is_configured() {
        return !empty($this->api_key);
    }

    private function get_cache_duration($endpoint) {
        $base_duration = (int) get_option('ytflix_cache_duration', 3600);
        if (isset($this->endpoint_ttls[$endpoint])) {
            return $this->endpoint_ttls[$endpoint];
        }
        return $base_duration;
    }

    private function request($endpoint, $params = []) {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', __('YouTube API key not configured.', 'ytflix'));
        }

        $params['key'] = $this->api_key;
        $url = $this->base_url . $endpoint . '?' . http_build_query($params);
        $cache_key = 'ytflix_' . md5($url);
        $etag_key = 'ytflix_etag_' . md5($url);
        $stale_key = 'ytflix_stale_' . md5($url);
        $cache_duration = $this->get_cache_duration($endpoint);

        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $headers = ['Referer' => home_url()];
        $stored_etag = get_option($etag_key, '');
        if (!empty($stored_etag)) {
            $headers['If-None-Match'] = $stored_etag;
        }

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => $headers,
        ]);

        if (is_wp_error($response)) {
            $this->log_api_warning($endpoint, 'request_failed', $response->get_error_message());
            return $this->get_stale_or_error($stale_key, $response);
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 304) {
            $stale_data = get_option($stale_key, null);
            if ($stale_data !== null) {
                $data = maybe_unserialize($stale_data);
                set_transient($cache_key, $data, $cache_duration);
                $this->track_api_call($endpoint, 0);
                return $data;
            }
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 403) {
            $reason = $body['error']['errors'][0]['reason'] ?? '';
            if ($reason === 'quotaExceeded') {
                $this->handle_quota_exceeded();
                return $this->get_stale_or_error($stale_key, new WP_Error('quota_exceeded', 'YouTube API quota exceeded'));
            }
        }

        if ($code !== 200) {
            $message = $body['error']['message'] ?? 'Unknown YouTube API error';
            $error = new WP_Error('youtube_api_error', $message, ['status' => $code]);
            $this->log_api_warning($endpoint, 'http_' . $code, $message);
            return $this->get_stale_or_error($stale_key, $error);
        }

        $etag = wp_remote_retrieve_header($response, 'etag');
        if (!empty($etag)) {
            update_option($etag_key, $etag, false);
        }

        update_option($stale_key, $body, false);
        set_transient($cache_key, $body, $cache_duration);

        $quota_cost = $this->endpoint_quota_costs[$endpoint] ?? 1;
        $this->track_api_call($endpoint, $quota_cost);

        return $body;
    }

    private function get_stale_or_error($stale_key, $error) {
        $stale_data = get_option($stale_key, null);
        if ($stale_data !== null) {
            $this->log_api_warning('fallback', 'serving_stale', 'Serving stale data due to API error');
            return maybe_unserialize($stale_data);
        }
        return $error;
    }

    private function track_api_call($endpoint, $quota_cost) {
        $today = gmdate('Y-m-d');
        $stats = get_option('ytflix_api_stats', []);

        if (!isset($stats[$today])) {
            $stats = [$today => ['total_calls' => 0, 'total_quota' => 0, 'endpoints' => []]];
        }

        $stats[$today]['total_calls']++;
        $stats[$today]['total_quota'] += $quota_cost;

        if (!isset($stats[$today]['endpoints'][$endpoint])) {
            $stats[$today]['endpoints'][$endpoint] = ['calls' => 0, 'quota' => 0];
        }
        $stats[$today]['endpoints'][$endpoint]['calls']++;
        $stats[$today]['endpoints'][$endpoint]['quota'] += $quota_cost;

        update_option('ytflix_api_stats', $stats, false);
    }

    private function handle_quota_exceeded() {
        update_option('ytflix_quota_exceeded', current_time('mysql'), false);
        $this->log_api_warning('quota', 'exceeded', 'YouTube API daily quota exceeded at ' . current_time('mysql'));
    }

    private function log_api_warning($endpoint, $type, $message) {
        $warnings = get_option('ytflix_api_warnings', []);
        $warnings[] = [
            'time'     => current_time('mysql'),
            'endpoint' => $endpoint,
            'type'     => $type,
            'message'  => $message,
        ];
        $warnings = array_slice($warnings, -50);
        update_option('ytflix_api_warnings', $warnings, false);
    }

    public function get_channel_info($channel_id = '') {
        if (empty($channel_id)) {
            $channel_id = get_option('ytflix_channel_id', '');
        }

        return $this->request('channels', [
            'part' => 'snippet,contentDetails,statistics,brandingSettings',
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
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
                '_transient_ytflix_%',
                '_transient_timeout_ytflix_%',
                'ytflix_etag_%',
                'ytflix_stale_%'
            )
        );
        delete_option('ytflix_api_stats');
        delete_option('ytflix_api_warnings');
        delete_option('ytflix_quota_exceeded');
    }

    public function get_api_stats() {
        return get_option('ytflix_api_stats', []);
    }

    public function get_api_warnings() {
        return get_option('ytflix_api_warnings', []);
    }

    public function get_cache_stats() {
        global $wpdb;

        $transient_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_ytflix_%' AND option_name NOT LIKE '_transient_timeout_%'"
        );

        $etag_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'ytflix_etag_%'"
        );

        $stale_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'ytflix_stale_%'"
        );

        $transcript_count = 0;
        $table = $wpdb->prefix . 'ytflix_transcripts';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
            $transcript_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        }

        return [
            'transients'  => $transient_count,
            'etags'       => $etag_count,
            'stale'       => $stale_count,
            'transcripts' => $transcript_count,
        ];
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
