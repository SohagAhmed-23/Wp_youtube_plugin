<?php
if (!defined('ABSPATH')) exit;

class YTFlix_Sync {

    private $api;

    public function __construct() {
        $this->api = new YTFlix_YouTube_API();
    }

    public function add_cron_interval($schedules) {
        $schedules['ytflix_twice_daily'] = [
            'interval' => 43200,
            'display'  => __('Twice Daily (YTFlix)', 'ytflix'),
        ];
        return $schedules;
    }

    public function schedule_sync() {
        if (!wp_next_scheduled('ytflix_sync_cron')) {
            $interval = get_option('ytflix_sync_interval', 'daily');
            wp_schedule_event(time(), $interval, 'ytflix_sync_cron');
        }
    }

    public function run_sync() {
        if (!$this->api->is_configured()) {
            return;
        }

        $current_channel = get_option('ytflix_channel_id', '');
        $last_channel = get_option('ytflix_last_synced_channel', '');

        if (!empty($current_channel) && $current_channel !== $last_channel) {
            $this->purge_all_content();
        }

        try {
            $this->sync_channel_info();
            $this->sync_channel_playlists();
            delete_option('ytflix_last_sync_error');
        } catch (\Throwable $e) {
            update_option('ytflix_last_sync_error', [
                'time'    => current_time('mysql'),
                'message' => $e->getMessage(),
            ], false);
            error_log('YTFlix Sync Error: ' . $e->getMessage());
        }

        update_option('ytflix_last_synced_channel', $current_channel);
        update_option('ytflix_last_sync', current_time('mysql'));
    }

    private function purge_all_content() {
        $videos = get_posts([
            'post_type'      => 'ytflix_video',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ]);
        foreach ($videos as $vid_id) {
            wp_delete_post($vid_id, true);
        }

        $playlists = get_posts([
            'post_type'      => 'ytflix_playlist',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ]);
        foreach ($playlists as $pl_id) {
            wp_delete_post($pl_id, true);
        }

        delete_option('ytflix_channel_logo');
        delete_option('ytflix_channel_logo_url');
        delete_option('ytflix_hero_image');
        delete_option('ytflix_channel_banner');
        delete_option('ytflix_channel_name');
        delete_option('ytflix_hero_title');
    }

    private function sync_channel_info() {
        $channel_id = get_option('ytflix_channel_id', '');
        if (empty($channel_id)) return;

        $result = $this->api->get_channel_info($channel_id);
        if (is_wp_error($result) || empty($result['items'])) return;

        $channel = $result['items'][0];
        $snippet = $channel['snippet'] ?? [];
        $branding = $channel['brandingSettings'] ?? [];

        $channel_title = $snippet['title'] ?? '';
        if (!empty($channel_title)) {
            update_option('ytflix_channel_name', sanitize_text_field($channel_title));
            update_option('ytflix_hero_title', sanitize_text_field($channel_title));
        }

        $logo_url = $snippet['thumbnails']['high']['url']
            ?? $snippet['thumbnails']['medium']['url']
            ?? $snippet['thumbnails']['default']['url']
            ?? '';
        if (!empty($logo_url)) {
            update_option('ytflix_channel_logo', esc_url_raw($logo_url));
            update_option('ytflix_channel_logo_url', esc_url_raw($logo_url));
        }

        $banner_url = $branding['image']['bannerExternalUrl'] ?? '';
        if (!empty($banner_url)) {
            $banner_url_hd = $banner_url . '=w2120-fcrop64=1,00005a57ffffa5a8-k-c0xffffffff-no-nd-rj';
            update_option('ytflix_hero_image', esc_url_raw($banner_url_hd));
            update_option('ytflix_channel_banner', esc_url_raw($banner_url_hd));
        }
    }

    private function sync_channel_playlists() {
        $channel_id = get_option('ytflix_channel_id', '');
        if (empty($channel_id)) return;

        $result = $this->api->get_playlists($channel_id, 25);
        if (is_wp_error($result) || empty($result['items'])) return;

        foreach ($result['items'] as $playlist) {
            $this->sync_playlist($playlist['id'], $playlist);
        }
    }

    public function sync_playlist($playlist_id, $playlist_data = null) {
        if (!$playlist_data) {
            $result = $this->api->get_playlist_by_id($playlist_id);
            if (is_wp_error($result) || empty($result['items'])) return;
            $playlist_data = $result['items'][0];
        }

        $playlist_post_id = $this->upsert_playlist($playlist_data);
        if (!$playlist_post_id) return;

        $page_token = '';
        $position = 0;
        $video_ids = [];

        do {
            $items = $this->api->get_playlist_items($playlist_id, 50, $page_token);
            if (is_wp_error($items) || empty($items['items'])) break;

            $batch_yt_ids = [];
            foreach ($items['items'] as $item) {
                $vid = $item['contentDetails']['videoId'] ?? '';
                if ($vid) $batch_yt_ids[] = $vid;
            }

            $details_result = $this->api->get_video_details($batch_yt_ids);
            $details_map = [];
            if (!is_wp_error($details_result) && !empty($details_result['items'])) {
                foreach ($details_result['items'] as $d) {
                    $details_map[$d['id']] = $d;
                }
            }

            foreach ($items['items'] as $item) {
                $yt_id = $item['contentDetails']['videoId'] ?? '';
                if (empty($yt_id)) continue;

                $detail = $details_map[$yt_id] ?? null;
                $video_post_id = $this->upsert_video($item, $detail, $playlist_post_id, $position);
                if ($video_post_id) {
                    $video_ids[] = $video_post_id;
                    $position++;
                }
            }

            $page_token = $items['nextPageToken'] ?? '';
        } while (!empty($page_token));

        update_post_meta($playlist_post_id, '_ytflix_video_ids', $video_ids);
        update_post_meta($playlist_post_id, '_ytflix_video_count', count($video_ids));
    }

    private function upsert_playlist($data) {
        $yt_id = $data['id'];
        $snippet = $data['snippet'];

        $existing = get_posts([
            'post_type'   => 'ytflix_playlist',
            'meta_key'    => '_ytflix_youtube_id',
            'meta_value'  => $yt_id,
            'numberposts' => 1,
            'post_status' => 'any',
        ]);

        $post_data = [
            'post_type'    => 'ytflix_playlist',
            'post_title'   => sanitize_text_field($snippet['title']),
            'post_content' => sanitize_textarea_field($snippet['description'] ?? ''),
            'post_status'  => 'publish',
        ];

        if (!empty($existing)) {
            $post_data['ID'] = $existing[0]->ID;
            wp_update_post($post_data);
            $post_id = $existing[0]->ID;
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, '_ytflix_youtube_id', $yt_id);
            update_post_meta($post_id, '_ytflix_thumbnail', $snippet['thumbnails']['high']['url'] ?? '');
            update_post_meta($post_id, '_ytflix_item_count', $data['contentDetails']['itemCount'] ?? 0);
            update_post_meta($post_id, '_ytflix_published_at', $snippet['publishedAt'] ?? '');

        }

        return $post_id;
    }

    private function upsert_video($item, $detail, $playlist_post_id, $position) {
        $yt_id = $item['contentDetails']['videoId'];
        $snippet = $item['snippet'];

        $existing = get_posts([
            'post_type'   => 'ytflix_video',
            'meta_key'    => '_ytflix_youtube_id',
            'meta_value'  => $yt_id,
            'numberposts' => 1,
            'post_status' => 'any',
        ]);

        $post_data = [
            'post_type'    => 'ytflix_video',
            'post_title'   => sanitize_text_field($snippet['title']),
            'post_content' => sanitize_textarea_field($snippet['description'] ?? ''),
            'post_status'  => 'publish',
            'menu_order'   => $position,
        ];

        if (!empty($existing)) {
            $post_data['ID'] = $existing[0]->ID;
            wp_update_post($post_data);
            $post_id = $existing[0]->ID;
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, '_ytflix_youtube_id', $yt_id);
            update_post_meta($post_id, '_ytflix_playlist_id', $playlist_post_id);
            update_post_meta($post_id, '_ytflix_position', $position);
            update_post_meta($post_id, '_ytflix_thumbnail', $snippet['thumbnails']['maxres']['url'] ?? $snippet['thumbnails']['high']['url'] ?? '');
            update_post_meta($post_id, '_ytflix_published_at', $snippet['publishedAt'] ?? '');

            if ($detail) {
                $duration_raw = $detail['contentDetails']['duration'] ?? 'PT0S';
                $duration_seconds = YTFlix_YouTube_API::parse_duration($duration_raw);
                update_post_meta($post_id, '_ytflix_duration', $duration_seconds);
                update_post_meta($post_id, '_ytflix_duration_formatted', YTFlix_YouTube_API::format_duration($duration_seconds));

                $stats = $detail['statistics'] ?? [];
                update_post_meta($post_id, '_ytflix_view_count', (int)($stats['viewCount'] ?? 0));
                update_post_meta($post_id, '_ytflix_like_count', (int)($stats['likeCount'] ?? 0));

                $tags = $detail['snippet']['tags'] ?? [];
                if (!empty($tags)) {
                    update_post_meta($post_id, '_ytflix_tags', $tags);
                    wp_set_object_terms($post_id, array_slice($tags, 0, 10), 'ytflix_genre', true);
                }
            }

        }

        return $post_id;
    }

    public function manual_sync() {
        $this->api->clear_cache();
        $this->run_sync();
        $version = (int) get_option('ytflix_cache_version', 0);
        update_option('ytflix_cache_version', $version + 1);
    }
}
