<?php
if (!defined('ABSPATH')) exit;

class YTFlix_Frontend {

    public function enqueue_assets() {
        if (!$this->is_ytflix_page()) return;

        wp_enqueue_style('ytflix-swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], '11.0');
        wp_enqueue_style('ytflix-frontend', YTFLIX_PLUGIN_URL . 'assets/css/frontend.css', ['ytflix-swiper'], YTFLIX_VERSION);

        $accent = get_option('ytflix_accent_color', '#c17a2f');
        wp_add_inline_style('ytflix-frontend', ":root { --ytflix-accent: {$accent}; }");

        wp_enqueue_script('ytflix-swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], '11.0', true);
        wp_enqueue_script('ytflix-yt-api', 'https://www.youtube.com/iframe_api', [], null, true);
        wp_enqueue_script('ytflix-frontend', YTFLIX_PLUGIN_URL . 'assets/js/frontend.js', ['jquery', 'ytflix-swiper'], YTFLIX_VERSION, true);

        wp_localize_script('ytflix-frontend', 'ytflixData', [
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'restUrl'         => rest_url('ytflix/v1/'),
            'nonce'           => wp_create_nonce('ytflix_nonce'),
            'restNonce'       => wp_create_nonce('wp_rest'),
            'isLoggedIn'      => is_user_logged_in(),
            'enableAutoplay'  => get_option('ytflix_enable_autoplay', '1'),
            'enableHistory'   => get_option('ytflix_enable_history', '1'),
            'enableFavorites' => get_option('ytflix_enable_favorites', '1'),
            'enablePip'       => get_option('ytflix_enable_pip', '1'),
            'enableTranscripts' => get_option('ytflix_enable_transcripts', '1'),
        ]);
    }

    public function add_query_vars($vars) {
        $vars[] = 'ytflix';
        $vars[] = 'ytflix_video_id';
        return $vars;
    }

    public function add_rewrite_rules() {
        add_rewrite_rule('^ytflix/?$', 'index.php?ytflix=1', 'top');
    }

    public function load_templates($template) {
        if (get_query_var('ytflix')) {
            return YTFLIX_PLUGIN_DIR . 'templates/pages/home.php';
        }

        if (is_post_type_archive('ytflix_video')) {
            return YTFLIX_PLUGIN_DIR . 'templates/pages/home.php';
        }

        global $post;
        if ($post && $post->post_type === 'ytflix_video') {
            return YTFLIX_PLUGIN_DIR . 'templates/pages/single-video.php';
        }
        if ($post && $post->post_type === 'ytflix_playlist') {
            return YTFLIX_PLUGIN_DIR . 'templates/pages/single-playlist.php';
        }

        return $template;
    }

    private function is_ytflix_page() {
        if (get_query_var('ytflix')) return true;
        if (is_post_type_archive('ytflix_video')) return true;
        if (is_post_type_archive('ytflix_playlist')) return true;

        global $post;
        if ($post && in_array($post->post_type, ['ytflix_video', 'ytflix_playlist'])) return true;

        if ($post && has_shortcode($post->post_content ?? '', 'ytflix')) return true;
        if ($post && has_shortcode($post->post_content ?? '', 'ytflix_hero')) return true;
        if ($post && has_shortcode($post->post_content ?? '', 'ytflix_playlist')) return true;
        if ($post && has_shortcode($post->post_content ?? '', 'ytflix_player')) return true;

        return false;
    }
}
