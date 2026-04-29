<?php
if (!defined('ABSPATH')) exit;

class YTFlix_Admin {

    public function add_menu() {
        add_menu_page(
            __('YTFlix', 'ytflix'),
            __('YTFlix', 'ytflix'),
            'manage_options',
            'ytflix',
            [$this, 'render_dashboard'],
            'dashicons-video-alt3',
            30
        );

        add_submenu_page('ytflix', __('Dashboard', 'ytflix'), __('Dashboard', 'ytflix'), 'manage_options', 'ytflix', [$this, 'render_dashboard']);
        add_submenu_page('ytflix', __('Settings', 'ytflix'), __('Settings', 'ytflix'), 'manage_options', 'ytflix-settings', [$this, 'render_settings']);
        add_submenu_page('ytflix', __('Sync', 'ytflix'), __('Sync', 'ytflix'), 'manage_options', 'ytflix-sync', [$this, 'render_sync']);
        add_submenu_page('ytflix', __('Videos', 'ytflix'), __('Videos', 'ytflix'), 'manage_options', 'edit.php?post_type=ytflix_video');
        add_submenu_page('ytflix', __('Playlists', 'ytflix'), __('Playlists', 'ytflix'), 'manage_options', 'edit.php?post_type=ytflix_playlist');
    }

    private $toggle_fields = [
        'ytflix_enable_transcripts',
        'ytflix_enable_history',
        'ytflix_enable_favorites',
        'ytflix_enable_autoplay',
        'ytflix_enable_pip',
    ];

    public function register_settings() {
        $fields = [
            'ytflix_api_key', 'ytflix_channel_id',
            'ytflix_video_slug', 'ytflix_playlist_slug',
            'ytflix_hero_image', 'ytflix_channel_logo',
            'ytflix_hero_title', 'ytflix_hero_description',
            'ytflix_cache_duration', 'ytflix_transcript_cache_ttl',
            'ytflix_enable_transcripts',
            'ytflix_enable_history', 'ytflix_enable_favorites',
            'ytflix_enable_autoplay', 'ytflix_enable_pip',
            'ytflix_accent_color', 'ytflix_sync_interval',
        ];

        foreach ($fields as $field) {
            $callback = in_array($field, $this->toggle_fields, true)
                ? [$this, 'sanitize_toggle']
                : [$this, 'sanitize_field'];
            register_setting('ytflix_settings', $field, [
                'sanitize_callback' => $callback,
            ]);
        }

        $this->maybe_flush_rewrite_rules();

        // Handle manual sync
        if (isset($_POST['ytflix_manual_sync']) && check_admin_referer('ytflix_sync_action')) {
            $sync = new YTFlix_Sync();
            $sync->manual_sync();
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . esc_html__('YouTube sync completed successfully!', 'ytflix') . '</p></div>';
            });
        }

        // Handle cache clear
        if (isset($_POST['ytflix_clear_cache']) && check_admin_referer('ytflix_sync_action')) {
            $api = new YTFlix_YouTube_API();
            $api->clear_cache();
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . esc_html__('Cache cleared successfully!', 'ytflix') . '</p></div>';
            });
        }
    }

    public function sanitize_field($value) {
        return sanitize_text_field($value);
    }

    public function sanitize_toggle($value) {
        return $value ? '1' : '0';
    }

    private function maybe_flush_rewrite_rules() {
        if (!isset($_GET['settings-updated']) || $_GET['settings-updated'] !== 'true') {
            return;
        }
        $old_video_slug = get_option('ytflix_old_video_slug', '');
        $old_playlist_slug = get_option('ytflix_old_playlist_slug', '');
        $new_video_slug = get_option('ytflix_video_slug', 'watch');
        $new_playlist_slug = get_option('ytflix_playlist_slug', 'series');

        if ($old_video_slug !== $new_video_slug || $old_playlist_slug !== $new_playlist_slug) {
            update_option('ytflix_old_video_slug', $new_video_slug);
            update_option('ytflix_old_playlist_slug', $new_playlist_slug);
            flush_rewrite_rules();
        }
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'ytflix') === false && strpos($hook, 'ytflix_') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('ytflix-admin', YTFLIX_PLUGIN_URL . 'assets/css/admin.css', [], YTFLIX_VERSION);
        wp_enqueue_script('ytflix-admin', YTFLIX_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], YTFLIX_VERSION, true);
        wp_localize_script('ytflix-admin', 'ytflixAdmin', [
            'nonce'   => wp_create_nonce('ytflix_admin_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    public function render_dashboard() {
        $video_count = wp_count_posts('ytflix_video')->publish ?? 0;
        $playlist_count = wp_count_posts('ytflix_playlist')->publish ?? 0;
        $last_sync = get_option('ytflix_last_sync', 'Never');
        $api_configured = !empty(get_option('ytflix_api_key'));
        ?>
        <div class="wrap ytflix-admin">
            <h1><span class="dashicons dashicons-video-alt3"></span> YTFlix Dashboard</h1>

            <div class="ytflix-dashboard-grid">
                <div class="ytflix-card">
                    <h3>API Status</h3>
                    <p class="ytflix-stat <?php echo $api_configured ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $api_configured ? '&#10003; Connected' : '&#10007; Not Configured'; ?>
                    </p>
                    <?php if (!$api_configured): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ytflix-settings')); ?>" class="button">Configure API</a>
                    <?php endif; ?>
                </div>

                <div class="ytflix-card">
                    <h3>Videos</h3>
                    <p class="ytflix-stat"><?php echo esc_html($video_count); ?></p>
                </div>

                <div class="ytflix-card">
                    <h3>Playlists</h3>
                    <p class="ytflix-stat"><?php echo esc_html($playlist_count); ?></p>
                </div>

                <div class="ytflix-card">
                    <h3>Last Sync</h3>
                    <p class="ytflix-stat-small"><?php echo esc_html($last_sync); ?></p>
                </div>
            </div>

                <?php
                $api = new YTFlix_YouTube_API();
                $today = gmdate('Y-m-d');
                $stats = $api->get_api_stats();
                $today_stats = $stats[$today] ?? ['total_calls' => 0, 'total_quota' => 0, 'endpoints' => []];
                ?>
                <div class="ytflix-card">
                    <h3>API Usage (Today)</h3>
                    <p><strong>API Calls:</strong> <?php echo esc_html($today_stats['total_calls']); ?></p>
                    <p><strong>Quota Used:</strong> <?php echo esc_html($today_stats['total_quota']); ?> / 10,000</p>
                    <?php if (!empty($today_stats['endpoints'])): ?>
                    <table class="widefat striped" style="margin-top:10px">
                        <thead><tr><th>Endpoint</th><th>Calls</th><th>Quota</th></tr></thead>
                        <tbody>
                        <?php foreach ($today_stats['endpoints'] as $ep => $ep_stats): ?>
                            <tr>
                                <td><?php echo esc_html($ep); ?></td>
                                <td><?php echo esc_html($ep_stats['calls']); ?></td>
                                <td><?php echo esc_html($ep_stats['quota']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

                <?php
                $cache_stats = $api->get_cache_stats();
                ?>
                <div class="ytflix-card">
                    <h3>Cache Stats</h3>
                    <p><strong>Active Transients:</strong> <?php echo esc_html($cache_stats['transients']); ?></p>
                    <p><strong>Stored ETags:</strong> <?php echo esc_html($cache_stats['etags']); ?></p>
                    <p><strong>Stale Backups:</strong> <?php echo esc_html($cache_stats['stale']); ?></p>
                    <p><strong>Cached Transcripts:</strong> <?php echo esc_html($cache_stats['transcripts']); ?></p>
                </div>
            </div>

            <div class="ytflix-card" style="margin-top:20px">
                <h3>Quick Links</h3>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ytflix-settings')); ?>" class="button button-primary">Settings</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ytflix-sync')); ?>" class="button">Sync Now</a>
                    <a href="<?php echo esc_url(home_url('/?ytflix=1')); ?>" class="button" target="_blank">View Frontend</a>
                </p>
                <h4>Shortcodes</h4>
                <code>[ytflix]</code> — Full page layout<br>
                <code>[ytflix_hero]</code> — Hero section only<br>
                <code>[ytflix_playlist id="PLAYLIST_POST_ID"]</code> — Single playlist slider<br>
                <code>[ytflix_player video="VIDEO_POST_ID"]</code> — Single video player<br>
            </div>
        </div>
        <?php
    }

    public function render_settings() {
        ?>
        <div class="wrap ytflix-admin">
            <h1>YTFlix Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('ytflix_settings'); ?>

                <div class="ytflix-settings-grid">
                    <!-- API Settings -->
                    <div class="ytflix-card">
                        <h3>YouTube API</h3>
                        <table class="form-table">
                            <tr>
                                <th><label for="ytflix_api_key">API Key</label></th>
                                <td><input type="password" id="ytflix_api_key" name="ytflix_api_key" value="<?php echo esc_attr(get_option('ytflix_api_key')); ?>" class="regular-text" autocomplete="off"></td>
                            </tr>
                            <tr>
                                <th><label for="ytflix_channel_id">Channel ID</label></th>
                                <td><input type="text" id="ytflix_channel_id" name="ytflix_channel_id" value="<?php echo esc_attr(get_option('ytflix_channel_id')); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="ytflix_cache_duration">API Cache Duration (seconds)</label></th>
                                <td><input type="number" id="ytflix_cache_duration" name="ytflix_cache_duration" value="<?php echo esc_attr(get_option('ytflix_cache_duration', 3600)); ?>" min="300" max="86400" class="small-text">
                                <p class="description">Base cache TTL for YouTube API responses. Endpoint-specific multipliers are applied automatically.</p></td>
                            </tr>
                            <tr>
                                <th><label for="ytflix_transcript_cache_ttl">Transcript Cache TTL (seconds)</label></th>
                                <td><input type="number" id="ytflix_transcript_cache_ttl" name="ytflix_transcript_cache_ttl" value="<?php echo esc_attr(get_option('ytflix_transcript_cache_ttl', 604800)); ?>" min="3600" max="2592000" class="small-text">
                                <p class="description">How long to cache transcripts. Default: 604800 (7 days).</p></td>
                            </tr>
                            <tr>
                                <th><label for="ytflix_sync_interval">Sync Interval</label></th>
                                <td>
                                    <select id="ytflix_sync_interval" name="ytflix_sync_interval">
                                        <option value="hourly" <?php selected(get_option('ytflix_sync_interval'), 'hourly'); ?>>Hourly</option>
                                        <option value="ytflix_twice_daily" <?php selected(get_option('ytflix_sync_interval'), 'ytflix_twice_daily'); ?>>Twice Daily</option>
                                        <option value="daily" <?php selected(get_option('ytflix_sync_interval'), 'daily'); ?>>Daily</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Appearance -->
                    <div class="ytflix-card">
                        <h3>Appearance</h3>
                        <table class="form-table">
                            <tr>
                                <th><label for="ytflix_hero_title">Hero Title</label></th>
                                <td><input type="text" id="ytflix_hero_title" name="ytflix_hero_title" value="<?php echo esc_attr(get_option('ytflix_hero_title')); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="ytflix_hero_description">Hero Description</label></th>
                                <td><textarea id="ytflix_hero_description" name="ytflix_hero_description" rows="2" class="large-text"><?php echo esc_textarea(get_option('ytflix_hero_description')); ?></textarea></td>
                            </tr>
                            <tr>
                                <th>Hero Background</th>
                                <td>
                                    <input type="hidden" id="ytflix_hero_image" name="ytflix_hero_image" value="<?php echo esc_attr(get_option('ytflix_hero_image')); ?>">
                                    <button type="button" class="button ytflix-upload-btn" data-target="ytflix_hero_image">Select Image</button>
                                    <div class="ytflix-image-preview" id="ytflix_hero_image_preview">
                                        <?php if ($img = get_option('ytflix_hero_image')): ?>
                                            <img src="<?php echo esc_url($img); ?>" style="max-width:300px;margin-top:10px">
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>Channel Logo</th>
                                <td>
                                    <input type="hidden" id="ytflix_channel_logo" name="ytflix_channel_logo" value="<?php echo esc_attr(get_option('ytflix_channel_logo')); ?>">
                                    <button type="button" class="button ytflix-upload-btn" data-target="ytflix_channel_logo">Select Logo</button>
                                    <div class="ytflix-image-preview" id="ytflix_channel_logo_preview">
                                        <?php if ($logo = get_option('ytflix_channel_logo')): ?>
                                            <img src="<?php echo esc_url($logo); ?>" style="max-width:100px;margin-top:10px;border-radius:50%">
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ytflix_accent_color">Accent Color</label></th>
                                <td><input type="color" id="ytflix_accent_color" name="ytflix_accent_color" value="<?php echo esc_attr(get_option('ytflix_accent_color', '#c17a2f')); ?>"></td>
                            </tr>
                        </table>
                    </div>

                    <!-- URLs -->
                    <div class="ytflix-card">
                        <h3>URL Slugs</h3>
                        <table class="form-table">
                            <tr>
                                <th><label for="ytflix_video_slug">Video Slug</label></th>
                                <td><input type="text" id="ytflix_video_slug" name="ytflix_video_slug" value="<?php echo esc_attr(get_option('ytflix_video_slug', 'watch')); ?>" class="regular-text">
                                <p class="description">e.g. yoursite.com/<strong>watch</strong>/video-title</p></td>
                            </tr>
                            <tr>
                                <th><label for="ytflix_playlist_slug">Playlist Slug</label></th>
                                <td><input type="text" id="ytflix_playlist_slug" name="ytflix_playlist_slug" value="<?php echo esc_attr(get_option('ytflix_playlist_slug', 'series')); ?>" class="regular-text"></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Features -->
                    <div class="ytflix-card">
                        <h3>Features</h3>
                        <table class="form-table">
                            <?php
                            $toggles = [
                                'ytflix_enable_transcripts' => 'Enable Transcripts',
                                'ytflix_enable_history'     => 'Enable Watch History',
                                'ytflix_enable_favorites'   => 'Enable My List / Favorites',
                                'ytflix_enable_autoplay'    => 'Enable Auto-play Next',
                                'ytflix_enable_pip'         => 'Enable Picture-in-Picture',
                            ];
                            foreach ($toggles as $key => $label):
                            ?>
                            <tr>
                                <th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                                <td>
                                    <label class="ytflix-toggle">
                                        <input type="checkbox" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="1" <?php checked(get_option($key, '1'), '1'); ?>>
                                        <span class="ytflix-toggle-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>

                <?php submit_button(__('Save Settings', 'ytflix')); ?>
            </form>
        </div>
        <?php
    }

    public function render_sync() {
        $last_sync = get_option('ytflix_last_sync', 'Never');
        $video_count = wp_count_posts('ytflix_video')->publish ?? 0;
        $playlist_count = wp_count_posts('ytflix_playlist')->publish ?? 0;
        ?>
        <div class="wrap ytflix-admin">
            <h1>YTFlix Sync</h1>

            <div class="ytflix-card">
                <h3>Sync Status</h3>
                <p><strong>Last Sync:</strong> <?php echo esc_html($last_sync); ?></p>
                <p><strong>Videos:</strong> <?php echo esc_html($video_count); ?></p>
                <p><strong>Playlists:</strong> <?php echo esc_html($playlist_count); ?></p>
            </div>

            <div class="ytflix-card" style="margin-top:20px">
                <h3>Actions</h3>
                <form method="post">
                    <?php wp_nonce_field('ytflix_sync_action'); ?>
                    <p>
                        <button type="submit" name="ytflix_manual_sync" class="button button-primary button-hero">
                            <span class="dashicons dashicons-update" style="margin-top:5px"></span> Sync Now
                        </button>
                    </p>
                    <p>
                        <button type="submit" name="ytflix_clear_cache" class="button">
                            <span class="dashicons dashicons-trash"></span> Clear Cache
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
