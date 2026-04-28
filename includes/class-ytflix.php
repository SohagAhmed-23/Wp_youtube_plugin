<?php
if (!defined('ABSPATH')) exit;

class YTFlix {

    private $loader;

    public function __construct() {
        $this->load_dependencies();
    }

    private function load_dependencies() {
        require_once YTFLIX_PLUGIN_DIR . 'includes/class-ytflix-loader.php';
        require_once YTFLIX_PLUGIN_DIR . 'includes/class-ytflix-cpt.php';
        require_once YTFLIX_PLUGIN_DIR . 'includes/services/class-ytflix-youtube-api.php';
        require_once YTFLIX_PLUGIN_DIR . 'includes/services/class-ytflix-transcript.php';
        require_once YTFLIX_PLUGIN_DIR . 'includes/services/class-ytflix-user-progress.php';
        require_once YTFLIX_PLUGIN_DIR . 'includes/services/class-ytflix-recommendations.php';
        require_once YTFLIX_PLUGIN_DIR . 'includes/services/class-ytflix-sync.php';
        require_once YTFLIX_PLUGIN_DIR . 'includes/admin/class-ytflix-admin.php';
        require_once YTFLIX_PLUGIN_DIR . 'includes/frontend/class-ytflix-frontend.php';
        require_once YTFLIX_PLUGIN_DIR . 'includes/frontend/class-ytflix-shortcodes.php';
        require_once YTFLIX_PLUGIN_DIR . 'includes/api/class-ytflix-rest-api.php';
        require_once YTFLIX_PLUGIN_DIR . 'includes/api/class-ytflix-ajax.php';

        $this->loader = new YTFlix_Loader();
    }

    public function run() {
        // Core
        $cpt = new YTFlix_CPT();
        $this->loader->add_action('init', $cpt, 'register');

        // Admin
        if (is_admin()) {
            $admin = new YTFlix_Admin();
            $this->loader->add_action('admin_menu', $admin, 'add_menu');
            $this->loader->add_action('admin_init', $admin, 'register_settings');
            $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_assets');
        }

        // Frontend
        $frontend = new YTFlix_Frontend();
        $this->loader->add_action('wp_enqueue_scripts', $frontend, 'enqueue_assets');
        $this->loader->add_filter('template_include', $frontend, 'load_templates');
        $this->loader->add_filter('query_vars', $frontend, 'add_query_vars');
        $this->loader->add_action('init', $frontend, 'add_rewrite_rules');

        // Shortcodes
        $shortcodes = new YTFlix_Shortcodes();
        $this->loader->add_action('init', $shortcodes, 'register');

        // REST API
        $rest = new YTFlix_REST_API();
        $this->loader->add_action('rest_api_init', $rest, 'register_routes');

        // AJAX
        $ajax = new YTFlix_Ajax();
        $this->loader->add_action('wp_ajax_ytflix_search', $ajax, 'search');
        $this->loader->add_action('wp_ajax_nopriv_ytflix_search', $ajax, 'search');
        $this->loader->add_action('wp_ajax_ytflix_save_progress', $ajax, 'save_progress');
        $this->loader->add_action('wp_ajax_ytflix_toggle_favorite', $ajax, 'toggle_favorite');
        $this->loader->add_action('wp_ajax_ytflix_get_transcript', $ajax, 'get_transcript');
        $this->loader->add_action('wp_ajax_nopriv_ytflix_get_transcript', $ajax, 'get_transcript');

        // Cron
        $sync = new YTFlix_Sync();
        $this->loader->add_action('ytflix_sync_cron', $sync, 'run_sync');
        $this->loader->add_filter('cron_schedules', $sync, 'add_cron_interval');
        $this->loader->add_action('init', $sync, 'schedule_sync');

        $this->loader->run();
    }
}
