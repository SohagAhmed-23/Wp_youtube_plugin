<?php
if (!defined('ABSPATH')) exit;

class YTCP_Deactivator {

    public static function deactivate() {
        wp_clear_scheduled_hook('ytcp_sync_cron');
        wp_clear_scheduled_hook('ytcp_transcript_sync_cron');
        delete_option('ytcp_rewrite_flushed');
        flush_rewrite_rules();
    }
}
