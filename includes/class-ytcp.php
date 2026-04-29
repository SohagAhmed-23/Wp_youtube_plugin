<?php
if (!defined('ABSPATH')) exit;

class YTCP {

    private $loader;

    public function __construct() {
        $this->load_dependencies();
    }

    private function load_dependencies() {
        require_once YTCP_PLUGIN_DIR . 'includes/class-ytcp-loader.php';
        require_once YTCP_PLUGIN_DIR . 'includes/class-ytcp-cpt.php';
        require_once YTCP_PLUGIN_DIR . 'includes/services/class-ytcp-youtube-api.php';
        require_once YTCP_PLUGIN_DIR . 'includes/services/class-ytcp-transcript.php';
        require_once YTCP_PLUGIN_DIR . 'includes/services/class-ytcp-user-progress.php';
        require_once YTCP_PLUGIN_DIR . 'includes/services/class-ytcp-recommendations.php';
        require_once YTCP_PLUGIN_DIR . 'includes/services/class-ytcp-sync.php';
        require_once YTCP_PLUGIN_DIR . 'includes/admin/class-ytcp-admin.php';
        require_once YTCP_PLUGIN_DIR . 'includes/frontend/class-ytcp-frontend.php';
        require_once YTCP_PLUGIN_DIR . 'includes/frontend/class-ytcp-shortcodes.php';
        require_once YTCP_PLUGIN_DIR . 'includes/api/class-ytcp-rest-api.php';
        require_once YTCP_PLUGIN_DIR . 'includes/api/class-ytcp-ajax.php';

        $this->loader = new YTCP_Loader();
    }

    public function run() {
        // Core
        $cpt = new YTCP_CPT();
        $this->loader->add_action('init', $cpt, 'register');

        // Admin
        if (is_admin()) {
            $admin = new YTCP_Admin();
            $this->loader->add_action('admin_menu', $admin, 'add_menu');
            $this->loader->add_action('admin_init', $admin, 'register_settings');
            $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_assets');
        }

        // Frontend
        $frontend = new YTCP_Frontend();
        $this->loader->add_action('wp_enqueue_scripts', $frontend, 'enqueue_assets');
        $this->loader->add_filter('template_include', $frontend, 'load_templates');
        $this->loader->add_filter('query_vars', $frontend, 'add_query_vars');
        $this->loader->add_action('init', $frontend, 'add_rewrite_rules');

        // Shortcodes
        $shortcodes = new YTCP_Shortcodes();
        $this->loader->add_action('init', $shortcodes, 'register');

        // REST API
        $rest = new YTCP_REST_API();
        $this->loader->add_action('rest_api_init', $rest, 'register_routes');

        // AJAX
        $ajax = new YTCP_Ajax();
        $this->loader->add_action('wp_ajax_ytcp_search', $ajax, 'search');
        $this->loader->add_action('wp_ajax_nopriv_ytcp_search', $ajax, 'search');
        $this->loader->add_action('wp_ajax_ytcp_save_progress', $ajax, 'save_progress');
        $this->loader->add_action('wp_ajax_ytcp_toggle_favorite', $ajax, 'toggle_favorite');
        $this->loader->add_action('wp_ajax_ytcp_get_transcript', $ajax, 'get_transcript');
        $this->loader->add_action('wp_ajax_nopriv_ytcp_get_transcript', $ajax, 'get_transcript');
        $this->loader->add_action('wp_ajax_ytcp_get_playlist_row', $ajax, 'get_playlist_row');
        $this->loader->add_action('wp_ajax_nopriv_ytcp_get_playlist_row', $ajax, 'get_playlist_row');

        // Cron
        $sync = new YTCP_Sync();
        $this->loader->add_action('ytcp_sync_cron', $sync, 'run_sync');
        $this->loader->add_filter('cron_schedules', $sync, 'add_cron_interval');
        $this->loader->add_action('init', $sync, 'schedule_sync');

        // Transcript cron
        $this->loader->add_action('ytcp_transcript_sync_cron', $this, 'run_transcript_sync');
        $this->loader->add_action('init', $this, 'schedule_transcript_sync');

        // Admin notices
        if (is_admin()) {
            $this->loader->add_action('admin_notices', $this, 'show_api_notices');
        }

        $this->loader->run();
    }

    public function schedule_transcript_sync() {
        if (!wp_next_scheduled('ytcp_transcript_sync_cron')) {
            wp_schedule_event(time() + 3600, 'daily', 'ytcp_transcript_sync_cron');
        }
    }

    public function run_transcript_sync() {
        $transcript = new YTCP_Transcript();
        $transcript->sync_all_transcripts(10);
    }

    public function show_api_notices() {
        $api_key = get_option('ytcp_api_key', '');
        $channel_id = get_option('ytcp_channel_id', '');

        if (empty($api_key)) {
            $settings_url = admin_url('admin.php?page=ytcp-settings');
            echo '<div class="notice notice-warning"><p><strong>YTChannel Pro:</strong> ' .
                 sprintf(
                     esc_html__('YouTube API key is not configured. %sGo to Settings%s to set it up.', 'ytchannel-pro'),
                     '<a href="' . esc_url($settings_url) . '">',
                     '</a>'
                 ) .
                 '</p></div>';
            return;
        }

        if (empty($channel_id)) {
            $settings_url = admin_url('admin.php?page=ytcp-settings');
            echo '<div class="notice notice-warning"><p><strong>YTChannel Pro:</strong> ' .
                 sprintf(
                     esc_html__('YouTube Channel ID is not configured. %sGo to Settings%s to set it up.', 'ytchannel-pro'),
                     '<a href="' . esc_url($settings_url) . '">',
                     '</a>'
                 ) .
                 '</p></div>';
            return;
        }

        $last_sync = get_option('ytcp_last_sync', '');
        if (empty($last_sync)) {
            $settings_url = admin_url('admin.php?page=ytcp-settings');
            $sync_url = admin_url('admin.php?page=ytcp-sync');
            echo '<div class="notice notice-info"><p><strong>YTChannel Pro:</strong> ' .
                 sprintf(
                     esc_html__('API configured! %sSave Settings%s to auto-import your videos, or %sSync Now%s manually.', 'ytchannel-pro'),
                     '<a href="' . esc_url($settings_url) . '">',
                     '</a>',
                     '<a href="' . esc_url($sync_url) . '">',
                     '</a>'
                 ) .
                 '</p></div>';
        }

        if (!get_option('ytcp_rewrite_flushed')) {
            flush_rewrite_rules();
            update_option('ytcp_rewrite_flushed', '1');
        }

        $quota_exceeded = get_option('ytcp_quota_exceeded', '');
        if (!empty($quota_exceeded)) {
            echo '<div class="notice notice-error"><p><strong>YTChannel Pro:</strong> ' .
                 esc_html__('YouTube API quota exceeded at ', 'ytchannel-pro') .
                 esc_html($quota_exceeded) .
                 '. ' . esc_html__('Cached data is being served. Quota resets at midnight Pacific Time.', 'ytchannel-pro') .
                 '</p></div>';
        }

        $sync_error = get_option('ytcp_last_sync_error', '');
        if (!empty($sync_error) && is_array($sync_error)) {
            echo '<div class="notice notice-warning"><p><strong>YTChannel Pro:</strong> ' .
                 esc_html__('Last sync encountered an error: ', 'ytchannel-pro') .
                 esc_html($sync_error['message'] ?? 'Unknown error') .
                 ' (' . esc_html($sync_error['time'] ?? '') . ')</p></div>';
        }
    }
}
