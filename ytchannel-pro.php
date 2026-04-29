<?php
/**
 * Plugin Name: YTChannel Pro - Netflix-Style YouTube Platform
 * Plugin URI: https://wordpress.org/plugins/ytchannel-pro-netflix-style-youtube-platform/
 * Description: A Netflix-inspired video platform powered by YouTube API with hero sections, playlist sliders, video player, transcripts, and user progress tracking.
 * Version: 1.0.0
 * Author: Sohag Ahmed
 * Author URI: https://profiles.wordpress.org/sohagahmed/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ytchannel-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('YTCP_VERSION', '1.0.0');
define('YTCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YTCP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YTCP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('YTCP_DB_VERSION', '1.0.0');

if (file_exists(YTCP_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once YTCP_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once YTCP_PLUGIN_DIR . 'includes/class-ytcp-activator.php';
require_once YTCP_PLUGIN_DIR . 'includes/class-ytcp-deactivator.php';

register_activation_hook(__FILE__, ['YTCP_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['YTCP_Deactivator', 'deactivate']);

require_once YTCP_PLUGIN_DIR . 'includes/class-ytcp.php';

function ytcp_init() {
    ytcp_maybe_upgrade();
    $plugin = new YTCP();
    $plugin->run();
}

function ytcp_maybe_upgrade() {
    $installed_version = get_option('ytcp_db_version', '0');
    if ($installed_version === '0') {
        $installed_version = get_option('ytflix_db_version', '0');
    }
    if (version_compare($installed_version, YTCP_DB_VERSION, '<')) {
        YTCP_Activator::activate();
    }
}

ytcp_init();
