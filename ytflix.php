<?php
/**
 * Plugin Name: YTFlix - Netflix-Style YouTube Platform
 * Plugin URI: https://example.com/ytflix
 * Description: A Netflix-inspired video platform powered by YouTube API with hero sections, playlist sliders, video player, transcripts, and user progress tracking.
 * Version: 1.0.0
 * Author: YTFlix Team
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ytflix
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('YTFLIX_VERSION', '1.0.0');
define('YTFLIX_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YTFLIX_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YTFLIX_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('YTFLIX_DB_VERSION', '1.0.0');

if (file_exists(YTFLIX_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once YTFLIX_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once YTFLIX_PLUGIN_DIR . 'includes/class-ytflix-activator.php';
require_once YTFLIX_PLUGIN_DIR . 'includes/class-ytflix-deactivator.php';

register_activation_hook(__FILE__, ['YTFlix_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['YTFlix_Deactivator', 'deactivate']);

require_once YTFLIX_PLUGIN_DIR . 'includes/class-ytflix.php';

function ytflix_init() {
    ytflix_maybe_upgrade();
    $plugin = new YTFlix();
    $plugin->run();
}

function ytflix_maybe_upgrade() {
    $installed_version = get_option('ytflix_db_version', '0');
    if (version_compare($installed_version, YTFLIX_DB_VERSION, '<')) {
        YTFlix_Activator::activate();
    }
}

ytflix_init();
