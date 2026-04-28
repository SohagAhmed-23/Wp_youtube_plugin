<?php
if (!defined('ABSPATH')) exit;

class YTFlix_Deactivator {

    public static function deactivate() {
        wp_clear_scheduled_hook('ytflix_sync_cron');
        wp_clear_scheduled_hook('ytflix_transcript_sync_cron');
        flush_rewrite_rules();
    }
}
