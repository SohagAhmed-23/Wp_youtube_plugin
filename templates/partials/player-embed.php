<?php
if (!defined('ABSPATH')) exit;
if (empty($video_id)) return;

$youtube_id = get_post_meta($video_id, '_ytcp_youtube_id', true);
if (empty($youtube_id)) return;
?>
<div class="ytcp-embed-player"
     data-video-id="<?php echo esc_attr($video_id); ?>"
     data-youtube-id="<?php echo esc_attr($youtube_id); ?>">
    <div class="ytcp-embed-player-inner" id="ytcp-embed-<?php echo esc_attr($video_id); ?>"></div>
</div>
