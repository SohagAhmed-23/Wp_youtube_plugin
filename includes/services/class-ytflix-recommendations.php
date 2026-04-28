<?php
if (!defined('ABSPATH')) exit;

class YTFlix_Recommendations {

    public function get_trending($limit = 20) {
        return get_posts([
            'post_type'      => 'ytflix_video',
            'posts_per_page' => $limit,
            'meta_key'       => '_ytflix_view_count',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'post_status'    => 'publish',
        ]);
    }

    public function get_recommended_for_user($user_id, $limit = 20) {
        global $wpdb;

        $watched_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT video_post_id FROM {$wpdb->prefix}ytflix_user_progress WHERE user_id = %d ORDER BY last_watched DESC LIMIT 20",
            $user_id
        ));

        if (empty($watched_ids)) {
            return $this->get_trending($limit);
        }

        $genre_ids = [];
        foreach ($watched_ids as $vid) {
            $terms = wp_get_object_terms($vid, 'ytflix_genre', ['fields' => 'ids']);
            if (!is_wp_error($terms)) {
                $genre_ids = array_merge($genre_ids, $terms);
            }
        }

        $genre_ids = array_unique($genre_ids);
        if (empty($genre_ids)) {
            return $this->get_trending($limit);
        }

        return get_posts([
            'post_type'      => 'ytflix_video',
            'posts_per_page' => $limit,
            'post__not_in'   => $watched_ids,
            'post_status'    => 'publish',
            'tax_query'      => [
                [
                    'taxonomy' => 'ytflix_genre',
                    'terms'    => array_slice($genre_ids, 0, 10),
                    'operator' => 'IN',
                ],
            ],
            'orderby'  => 'rand',
        ]);
    }

    public function get_related_videos($video_post_id, $limit = 10) {
        $terms = wp_get_object_terms($video_post_id, 'ytflix_genre', ['fields' => 'ids']);

        if (is_wp_error($terms) || empty($terms)) {
            return get_posts([
                'post_type'      => 'ytflix_video',
                'posts_per_page' => $limit,
                'post__not_in'   => [$video_post_id],
                'orderby'        => 'rand',
                'post_status'    => 'publish',
            ]);
        }

        return get_posts([
            'post_type'      => 'ytflix_video',
            'posts_per_page' => $limit,
            'post__not_in'   => [$video_post_id],
            'post_status'    => 'publish',
            'tax_query'      => [
                [
                    'taxonomy' => 'ytflix_genre',
                    'terms'    => $terms,
                    'operator' => 'IN',
                ],
            ],
        ]);
    }

    public function get_new_releases($limit = 20) {
        return get_posts([
            'post_type'      => 'ytflix_video',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => 'publish',
        ]);
    }
}
