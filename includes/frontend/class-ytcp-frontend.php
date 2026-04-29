<?php
if (!defined('ABSPATH')) exit;

class YTCP_Frontend {

    public function enqueue_assets() {
        if (!$this->is_ytcp_page()) return;

        wp_enqueue_style('ytcp-swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], '11.0');
        wp_enqueue_style('ytcp-frontend', YTCP_PLUGIN_URL . 'assets/css/frontend.css', ['ytcp-swiper'], YTCP_VERSION);

        $accent = sanitize_hex_color(get_option('ytcp_accent_color', '#c17a2f'));
        if (empty($accent)) {
            $accent = '#c17a2f';
        }
        wp_add_inline_style('ytcp-frontend', ":root { --ytcp-accent: {$accent}; }");

        wp_enqueue_script('ytcp-swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], '11.0', true);
        wp_enqueue_script('ytcp-frontend', YTCP_PLUGIN_URL . 'assets/js/frontend.js', ['jquery', 'ytcp-swiper'], YTCP_VERSION, true);

        if ($this->is_watch_page()) {
            wp_enqueue_script('ytcp-yt-api', 'https://www.youtube.com/iframe_api', [], null, true);
        }

        wp_localize_script('ytcp-frontend', 'ytcpData', [
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'restUrl'         => rest_url('ytcp/v1/'),
            'nonce'           => wp_create_nonce('ytcp_nonce'),
            'restNonce'       => wp_create_nonce('wp_rest'),
            'isLoggedIn'      => is_user_logged_in(),
            'enableAutoplay'  => get_option('ytcp_enable_autoplay', '1'),
            'enableHistory'   => get_option('ytcp_enable_history', '1'),
            'enableFavorites' => get_option('ytcp_enable_favorites', '1'),
            'enablePip'       => get_option('ytcp_enable_pip', '1'),
            'enableTranscripts' => get_option('ytcp_enable_transcripts', '1'),
            'cacheVersion'    => (int) get_option('ytcp_cache_version', 0),
        ]);
    }

    public function add_query_vars($vars) {
        $vars[] = 'ytcp';
        $vars[] = 'ytcp_video_id';
        return $vars;
    }

    public function add_rewrite_rules() {
        add_rewrite_rule('^ytcp/?$', 'index.php?ytcp=1', 'top');
    }

    public function load_templates($template) {
        if (get_query_var('ytcp')) {
            return YTCP_PLUGIN_DIR . 'templates/pages/home.php';
        }

        if (is_post_type_archive('ytcp_video')) {
            return YTCP_PLUGIN_DIR . 'templates/pages/home.php';
        }

        global $post;
        if ($post && $post->post_type === 'ytcp_video') {
            return YTCP_PLUGIN_DIR . 'templates/pages/single-video.php';
        }
        if ($post && $post->post_type === 'ytcp_playlist') {
            return YTCP_PLUGIN_DIR . 'templates/pages/single-playlist.php';
        }

        return $template;
    }

    private function is_watch_page() {
        global $post;
        return ($post && $post->post_type === 'ytcp_video');
    }

    private function is_ytcp_page() {
        if (get_query_var('ytcp')) return true;
        if (is_post_type_archive('ytcp_video')) return true;
        if (is_post_type_archive('ytcp_playlist')) return true;

        global $post;
        if ($post && in_array($post->post_type, ['ytcp_video', 'ytcp_playlist'])) return true;

        if ($post && has_shortcode($post->post_content ?? '', 'ytcp')) return true;
        if ($post && has_shortcode($post->post_content ?? '', 'ytflix')) return true;
        if ($post && has_shortcode($post->post_content ?? '', 'ytcp_hero')) return true;
        if ($post && has_shortcode($post->post_content ?? '', 'ytcp_playlist')) return true;
        if ($post && has_shortcode($post->post_content ?? '', 'ytcp_player')) return true;
        if ($post && has_shortcode($post->post_content ?? '', 'ytflix_hero')) return true;
        if ($post && has_shortcode($post->post_content ?? '', 'ytflix_playlist')) return true;
        if ($post && has_shortcode($post->post_content ?? '', 'ytflix_player')) return true;

        return false;
    }
}
