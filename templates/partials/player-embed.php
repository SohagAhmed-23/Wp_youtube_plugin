<?php
if (!defined('ABSPATH')) exit;
if (empty($video_id)) return;

$youtube_id = get_post_meta($video_id, '_ytflix_youtube_id', true);
if (empty($youtube_id)) return;
?>
<div class="ytflix-embed-player"
     data-video-id="<?php echo esc_attr($video_id); ?>"
     data-youtube-id="<?php echo esc_attr($youtube_id); ?>">
    <div class="ytflix-embed-player-inner" id="ytflix-embed-<?php echo esc_attr($video_id); ?>"></div>
</div>
