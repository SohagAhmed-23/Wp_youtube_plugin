<?php
if (!defined('ABSPATH')) exit;

class YTFlix_Shortcodes {

    public function register() {
        add_shortcode('ytflix', [$this, 'render_full_page']);
        add_shortcode('ytflix_hero', [$this, 'render_hero']);
        add_shortcode('ytflix_playlist', [$this, 'render_playlist']);
        add_shortcode('ytflix_player', [$this, 'render_player']);
        add_shortcode('ytflix_search', [$this, 'render_search']);
    }

    public function render_full_page($atts) {
        ob_start();
        include YTFLIX_PLUGIN_DIR . 'templates/partials/hero.php';
        include YTFLIX_PLUGIN_DIR . 'templates/partials/search.php';
        include YTFLIX_PLUGIN_DIR . 'templates/partials/playlist-rows.php';
        include YTFLIX_PLUGIN_DIR . 'templates/partials/modal.php';
        return ob_get_clean();
    }

    public function render_hero($atts) {
        ob_start();
        include YTFLIX_PLUGIN_DIR . 'templates/partials/hero.php';
        return ob_get_clean();
    }

    public function render_playlist($atts) {
        $atts = shortcode_atts(['id' => 0], $atts, 'ytflix_playlist');
        $playlist_id = absint($atts['id']);

        if (!$playlist_id) return '';

        ob_start();
        $this->render_single_playlist_row($playlist_id);
        return ob_get_clean();
    }

    public function render_player($atts) {
        $atts = shortcode_atts(['video' => 0], $atts, 'ytflix_player');
        $video_id = absint($atts['video']);

        if (!$video_id) return '';

        ob_start();
        include YTFLIX_PLUGIN_DIR . 'templates/partials/player-embed.php';
        return ob_get_clean();
    }

    public function render_search($atts) {
        ob_start();
        include YTFLIX_PLUGIN_DIR . 'templates/partials/search.php';
        return ob_get_clean();
    }

    private function render_single_playlist_row($playlist_id) {
        $playlist = get_post($playlist_id);
        if (!$playlist) return;

        $video_ids = get_post_meta($playlist_id, '_ytflix_video_ids', true);
        if (empty($video_ids)) return;

        $videos = get_posts([
            'post_type'      => 'ytflix_video',
            'post__in'       => $video_ids,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
        ]);

        if (empty($videos)) return;

        $row_index = 0;
        include YTFLIX_PLUGIN_DIR . 'templates/partials/playlist-row.php';
    }
}
