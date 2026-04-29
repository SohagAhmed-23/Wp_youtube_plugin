<?php
if (!defined('ABSPATH')) exit;

class YTCP_Admin {

    public function add_menu() {
        add_menu_page(
            __('YTChannel Pro', 'ytchannel-pro'),
            __('YTChannel Pro', 'ytchannel-pro'),
            'manage_options',
            'ytcp',
            [$this, 'render_dashboard'],
            'dashicons-video-alt3',
            30
        );

        add_submenu_page('ytcp', __('Dashboard', 'ytchannel-pro'), __('Dashboard', 'ytchannel-pro'), 'manage_options', 'ytcp', [$this, 'render_dashboard']);
        add_submenu_page('ytcp', __('Settings', 'ytchannel-pro'), __('Settings', 'ytchannel-pro'), 'manage_options', 'ytcp-settings', [$this, 'render_settings']);
        add_submenu_page('ytcp', __('Sync', 'ytchannel-pro'), __('Sync', 'ytchannel-pro'), 'manage_options', 'ytcp-sync', [$this, 'render_sync']);
        add_submenu_page('ytcp', __('Videos', 'ytchannel-pro'), __('Videos', 'ytchannel-pro'), 'manage_options', 'edit.php?post_type=ytcp_video');
        add_submenu_page('ytcp', __('Playlists', 'ytchannel-pro'), __('Playlists', 'ytchannel-pro'), 'manage_options', 'edit.php?post_type=ytcp_playlist');
    }

    private $toggle_fields = [
        'ytcp_enable_transcripts',
        'ytcp_enable_history',
        'ytcp_enable_favorites',
        'ytcp_enable_autoplay',
        'ytcp_enable_pip',
    ];

    public function register_settings() {
        $fields = [
            'ytcp_api_key', 'ytcp_channel_id',
            'ytcp_video_slug', 'ytcp_playlist_slug',
            'ytcp_hero_image', 'ytcp_channel_logo',
            'ytcp_hero_title', 'ytcp_hero_description',
            'ytcp_cache_duration', 'ytcp_transcript_cache_ttl',
            'ytcp_enable_transcripts',
            'ytcp_enable_history', 'ytcp_enable_favorites',
            'ytcp_enable_autoplay', 'ytcp_enable_pip',
            'ytcp_accent_color', 'ytcp_sync_interval',
        ];

        foreach ($fields as $field) {
            $callback = in_array($field, $this->toggle_fields, true)
                ? [$this, 'sanitize_toggle']
                : [$this, 'sanitize_field'];
            register_setting('ytcp_settings', $field, [
                'sanitize_callback' => $callback,
            ]);
        }

        $this->maybe_flush_rewrite_rules();
        $this->maybe_auto_sync();

        // Handle manual sync
        if (isset($_POST['ytcp_manual_sync']) && check_admin_referer('ytcp_sync_action')) {
            $sync = new YTCP_Sync();
            $sync->manual_sync();
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . esc_html__('YouTube sync completed successfully!', 'ytchannel-pro') . '</p></div>';
            });
        }

        // Handle cache clear
        if (isset($_POST['ytcp_clear_cache']) && check_admin_referer('ytcp_sync_action')) {
            $api = new YTCP_YouTube_API();
            $api->clear_cache();
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . esc_html__('Cache cleared successfully!', 'ytchannel-pro') . '</p></div>';
            });
        }
    }

    public function sanitize_field($value) {
        return sanitize_text_field($value);
    }

    public function sanitize_toggle($value) {
        return $value ? '1' : '0';
    }

    private function maybe_auto_sync() {
        if (!isset($_GET['settings-updated']) || $_GET['settings-updated'] !== 'true') {
            return;
        }

        $api_key = get_option('ytcp_api_key', '');
        $channel_id = get_option('ytcp_channel_id', '');
        $last_sync = get_option('ytcp_last_sync', '');

        if (empty($api_key) || empty($channel_id) || !empty($last_sync)) {
            return;
        }

        $sync = new YTCP_Sync();
        $sync->manual_sync();

        $sync_error = get_option('ytcp_last_sync_error', '');
        if (!empty($sync_error) && is_array($sync_error)) {
            add_action('admin_notices', function() use ($sync_error) {
                echo '<div class="notice notice-error"><p><strong>YTChannel Pro:</strong> ' .
                     esc_html__('First sync failed: ', 'ytchannel-pro') .
                     esc_html($sync_error['message'] ?? 'Unknown error') .
                     '</p></div>';
            });
        } else {
            $video_count = wp_count_posts('ytcp_video')->publish ?? 0;
            $playlist_count = wp_count_posts('ytcp_playlist')->publish ?? 0;
            add_action('admin_notices', function() use ($video_count, $playlist_count) {
                echo '<div class="notice notice-success"><p><strong>YTChannel Pro:</strong> ' .
                     sprintf(
                         esc_html__('First sync complete! Imported %d videos and %d playlists.', 'ytchannel-pro'),
                         $video_count,
                         $playlist_count
                     ) .
                     '</p></div>';
            });
        }
    }

    private function maybe_flush_rewrite_rules() {
        if (!isset($_GET['settings-updated']) || $_GET['settings-updated'] !== 'true') {
            return;
        }
        $old_video_slug = get_option('ytcp_old_video_slug', '');
        $old_playlist_slug = get_option('ytcp_old_playlist_slug', '');
        $new_video_slug = get_option('ytcp_video_slug', 'watch');
        $new_playlist_slug = get_option('ytcp_playlist_slug', 'series');

        if ($old_video_slug !== $new_video_slug || $old_playlist_slug !== $new_playlist_slug) {
            update_option('ytcp_old_video_slug', $new_video_slug);
            update_option('ytcp_old_playlist_slug', $new_playlist_slug);
            flush_rewrite_rules();
        }
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'ytcp') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('ytcp-admin', YTCP_PLUGIN_URL . 'assets/css/admin.css', [], YTCP_VERSION);
        wp_enqueue_script('ytcp-admin', YTCP_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], YTCP_VERSION, true);
        wp_localize_script('ytcp-admin', 'ytcpAdmin', [
            'nonce'   => wp_create_nonce('ytcp_admin_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    public function render_dashboard() {
        $video_count = wp_count_posts('ytcp_video')->publish ?? 0;
        $playlist_count = wp_count_posts('ytcp_playlist')->publish ?? 0;
        $last_sync = get_option('ytcp_last_sync', 'Never');
        $api_configured = !empty(get_option('ytcp_api_key'));
        ?>
        <div class="wrap ytcp-admin">
            <h1><span class="dashicons dashicons-video-alt3"></span> YTChannel Pro Dashboard</h1>

            <div class="ytcp-dashboard-grid">
                <div class="ytcp-card">
                    <h3>API Status</h3>
                    <p class="ytcp-stat <?php echo $api_configured ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $api_configured ? '&#10003; Connected' : '&#10007; Not Configured'; ?>
                    </p>
                    <?php if (!$api_configured): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ytcp-settings')); ?>" class="button">Configure API</a>
                    <?php endif; ?>
                </div>

                <div class="ytcp-card">
                    <h3>Videos</h3>
                    <p class="ytcp-stat"><?php echo esc_html($video_count); ?></p>
                </div>

                <div class="ytcp-card">
                    <h3>Playlists</h3>
                    <p class="ytcp-stat"><?php echo esc_html($playlist_count); ?></p>
                </div>

                <div class="ytcp-card">
                    <h3>Last Sync</h3>
                    <p class="ytcp-stat-small"><?php echo esc_html($last_sync); ?></p>
                </div>
            </div>

                <?php
                $api = new YTCP_YouTube_API();
                $today = gmdate('Y-m-d');
                $stats = $api->get_api_stats();
                $today_stats = $stats[$today] ?? ['total_calls' => 0, 'total_quota' => 0, 'endpoints' => []];
                ?>
                <div class="ytcp-card">
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
                <div class="ytcp-card">
                    <h3>Cache Stats</h3>
                    <p><strong>Active Transients:</strong> <?php echo esc_html($cache_stats['transients']); ?></p>
                    <p><strong>Stored ETags:</strong> <?php echo esc_html($cache_stats['etags']); ?></p>
                    <p><strong>Stale Backups:</strong> <?php echo esc_html($cache_stats['stale']); ?></p>
                    <p><strong>Cached Transcripts:</strong> <?php echo esc_html($cache_stats['transcripts']); ?></p>
                </div>
            </div>

            <div class="ytcp-card" style="margin-top:20px">
                <h3>Quick Links</h3>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ytcp-settings')); ?>" class="button button-primary">Settings</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ytcp-sync')); ?>" class="button">Sync Now</a>
                    <a href="<?php echo esc_url(home_url('/?ytcp=1')); ?>" class="button" target="_blank">View Frontend</a>
                </p>
                <h4>Shortcodes</h4>
                <code>[ytcp]</code> — Full page layout<br>
                <code>[ytcp_hero]</code> — Hero section only<br>
                <code>[ytcp_playlist id="PLAYLIST_POST_ID"]</code> — Single playlist slider<br>
                <code>[ytcp_player video="VIDEO_POST_ID"]</code> — Single video player<br>
            </div>
        </div>
        <?php
    }

    public function render_settings() {
        ?>
        <div class="wrap ytcp-admin">
            <h1>YTChannel Pro Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('ytcp_settings'); ?>

                <div class="ytcp-settings-grid">
                    <!-- API Settings -->
                    <div class="ytcp-card">
                        <h3>YouTube API</h3>
                        <table class="form-table">
                            <tr>
                                <th><label for="ytcp_api_key">API Key</label></th>
                                <td><input type="password" id="ytcp_api_key" name="ytcp_api_key" value="<?php echo esc_attr(get_option('ytcp_api_key')); ?>" class="regular-text" autocomplete="off"></td>
                            </tr>
                            <tr>
                                <th><label for="ytcp_channel_id">Channel ID</label></th>
                                <td><input type="text" id="ytcp_channel_id" name="ytcp_channel_id" value="<?php echo esc_attr(get_option('ytcp_channel_id')); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="ytcp_cache_duration">API Cache Duration (seconds)</label></th>
                                <td><input type="number" id="ytcp_cache_duration" name="ytcp_cache_duration" value="<?php echo esc_attr(get_option('ytcp_cache_duration', 3600)); ?>" min="300" max="86400" class="small-text">
                                <p class="description">Base cache TTL for YouTube API responses. Endpoint-specific multipliers are applied automatically.</p></td>
                            </tr>
                            <tr>
                                <th><label for="ytcp_transcript_cache_ttl">Transcript Cache TTL (seconds)</label></th>
                                <td><input type="number" id="ytcp_transcript_cache_ttl" name="ytcp_transcript_cache_ttl" value="<?php echo esc_attr(get_option('ytcp_transcript_cache_ttl', 604800)); ?>" min="3600" max="2592000" class="small-text">
                                <p class="description">How long to cache transcripts. Default: 604800 (7 days).</p></td>
                            </tr>
                            <tr>
                                <th><label for="ytcp_sync_interval">Sync Interval</label></th>
                                <td>
                                    <select id="ytcp_sync_interval" name="ytcp_sync_interval">
                                        <option value="hourly" <?php selected(get_option('ytcp_sync_interval'), 'hourly'); ?>>Hourly</option>
                                        <option value="ytcp_twice_daily" <?php selected(get_option('ytcp_sync_interval'), 'ytcp_twice_daily'); ?>>Twice Daily</option>
                                        <option value="daily" <?php selected(get_option('ytcp_sync_interval'), 'daily'); ?>>Daily</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Appearance -->
                    <div class="ytcp-card">
                        <h3>Appearance</h3>
                        <table class="form-table">
                            <tr>
                                <th><label for="ytcp_hero_title">Hero Title</label></th>
                                <td><input type="text" id="ytcp_hero_title" name="ytcp_hero_title" value="<?php echo esc_attr(get_option('ytcp_hero_title')); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="ytcp_hero_description">Hero Description</label></th>
                                <td><textarea id="ytcp_hero_description" name="ytcp_hero_description" rows="2" class="large-text"><?php echo esc_textarea(get_option('ytcp_hero_description')); ?></textarea></td>
                            </tr>
                            <tr>
                                <th>Hero Background</th>
                                <td>
                                    <input type="hidden" id="ytcp_hero_image" name="ytcp_hero_image" value="<?php echo esc_attr(get_option('ytcp_hero_image')); ?>">
                                    <button type="button" class="button ytcp-upload-btn" data-target="ytcp_hero_image">Select Image</button>
                                    <div class="ytcp-image-preview" id="ytcp_hero_image_preview">
                                        <?php if ($img = get_option('ytcp_hero_image')): ?>
                                            <img src="<?php echo esc_url($img); ?>" style="max-width:300px;margin-top:10px">
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>Channel Logo</th>
                                <td>
                                    <input type="hidden" id="ytcp_channel_logo" name="ytcp_channel_logo" value="<?php echo esc_attr(get_option('ytcp_channel_logo')); ?>">
                                    <button type="button" class="button ytcp-upload-btn" data-target="ytcp_channel_logo">Select Logo</button>
                                    <div class="ytcp-image-preview" id="ytcp_channel_logo_preview">
                                        <?php if ($logo = get_option('ytcp_channel_logo')): ?>
                                            <img src="<?php echo esc_url($logo); ?>" style="max-width:100px;margin-top:10px;border-radius:50%">
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ytcp_accent_color">Accent Color</label></th>
                                <td><input type="color" id="ytcp_accent_color" name="ytcp_accent_color" value="<?php echo esc_attr(get_option('ytcp_accent_color', '#c17a2f')); ?>"></td>
                            </tr>
                        </table>
                    </div>

                    <!-- URLs -->
                    <div class="ytcp-card">
                        <h3>URL Slugs</h3>
                        <table class="form-table">
                            <tr>
                                <th><label for="ytcp_video_slug">Video Slug</label></th>
                                <td><input type="text" id="ytcp_video_slug" name="ytcp_video_slug" value="<?php echo esc_attr(get_option('ytcp_video_slug', 'watch')); ?>" class="regular-text">
                                <p class="description">e.g. yoursite.com/<strong>watch</strong>/video-title</p></td>
                            </tr>
                            <tr>
                                <th><label for="ytcp_playlist_slug">Playlist Slug</label></th>
                                <td><input type="text" id="ytcp_playlist_slug" name="ytcp_playlist_slug" value="<?php echo esc_attr(get_option('ytcp_playlist_slug', 'series')); ?>" class="regular-text"></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Features -->
                    <div class="ytcp-card">
                        <h3>Features</h3>
                        <table class="form-table">
                            <?php
                            $toggles = [
                                'ytcp_enable_transcripts' => 'Enable Transcripts',
                                'ytcp_enable_history'     => 'Enable Watch History',
                                'ytcp_enable_favorites'   => 'Enable My List / Favorites',
                                'ytcp_enable_autoplay'    => 'Enable Auto-play Next',
                                'ytcp_enable_pip'         => 'Enable Picture-in-Picture',
                            ];
                            foreach ($toggles as $key => $label):
                            ?>
                            <tr>
                                <th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                                <td>
                                    <label class="ytcp-toggle">
                                        <input type="checkbox" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="1" <?php checked(get_option($key, '1'), '1'); ?>>
                                        <span class="ytcp-toggle-slider"></span>
                                    </label>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>

                <?php submit_button(__('Save Settings', 'ytchannel-pro')); ?>
                <?php if (empty(get_option('ytcp_last_sync', ''))): ?>
                <p class="description" style="margin-top:-10px">
                    <strong><?php esc_html_e('Your first YouTube sync will run automatically after saving.', 'ytchannel-pro'); ?></strong>
                </p>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    public function render_sync() {
        $last_sync = get_option('ytcp_last_sync', 'Never');
        $video_count = wp_count_posts('ytcp_video')->publish ?? 0;
        $playlist_count = wp_count_posts('ytcp_playlist')->publish ?? 0;
        ?>
        <div class="wrap ytcp-admin">
            <h1>YTChannel Pro Sync</h1>

            <div class="ytcp-card">
                <h3>Sync Status</h3>
                <p><strong>Last Sync:</strong> <?php echo esc_html($last_sync); ?></p>
                <p><strong>Videos:</strong> <?php echo esc_html($video_count); ?></p>
                <p><strong>Playlists:</strong> <?php echo esc_html($playlist_count); ?></p>
            </div>

            <div class="ytcp-card" style="margin-top:20px">
                <h3>Actions</h3>
                <form method="post">
                    <?php wp_nonce_field('ytcp_sync_action'); ?>
                    <p>
                        <button type="submit" name="ytcp_manual_sync" class="button button-primary button-hero">
                            <span class="dashicons dashicons-update" style="margin-top:5px"></span> Sync Now
                        </button>
                    </p>
                    <p>
                        <button type="submit" name="ytcp_clear_cache" class="button">
                            <span class="dashicons dashicons-trash"></span> Clear Cache
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
