<?php
if (!defined('ABSPATH')) exit;
get_header();

$playlist_id = get_the_ID();
$youtube_id = get_post_meta($playlist_id, '_ytcp_youtube_id', true);
$thumbnail = get_post_meta($playlist_id, '_ytcp_thumbnail', true);
$video_ids = get_post_meta($playlist_id, '_ytcp_video_ids', true);

$videos = [];
if (!empty($video_ids)) {
    $videos = get_posts([
        'post_type'      => 'ytcp_video',
        'post__in'       => $video_ids,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'posts_per_page' => 100,
        'post_status'    => 'publish',
    ]);
}

$first_video = !empty($videos) ? $videos[0] : null;
?>
<div class="ytcp-app ytcp-playlist-page" id="ytcp-app">
    <div class="ytcp-playlist-hero" style="background-image:url('<?php echo esc_url($thumbnail); ?>')">
        <div class="ytcp-playlist-hero-overlay">
            <h1><?php the_title(); ?></h1>
            <p class="ytcp-playlist-desc"><?php echo wp_kses_post(get_the_content()); ?></p>
            <div class="ytcp-playlist-meta">
                <span><?php echo (int) count($videos); ?> episodes</span>
            </div>
            <?php if ($first_video): ?>
            <a href="<?php echo esc_url(get_permalink($first_video->ID)); ?>" class="ytcp-btn-play-large">
                <svg viewBox="0 0 24 24" width="24" height="24"><polygon points="5,3 19,12 5,21" fill="currentColor"/></svg>
                Play
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="ytcp-playlist-grid">
        <?php foreach ($videos as $idx => $v):
            $v_thumb = get_post_meta($v->ID, '_ytcp_thumbnail', true);
            $v_dur = get_post_meta($v->ID, '_ytcp_duration_formatted', true);
            $v_views = (int) get_post_meta($v->ID, '_ytcp_view_count', true);
        ?>
        <a href="<?php echo esc_url(get_permalink($v->ID)); ?>" class="ytcp-playlist-video-card">
            <div class="ytcp-card-thumb">
                <img src="<?php echo esc_url($v_thumb); ?>" alt="<?php echo esc_attr($v->post_title); ?>" loading="lazy" referrerpolicy="no-referrer">
                <span class="ytcp-card-duration"><?php echo esc_html($v_dur); ?></span>
                <span class="ytcp-card-number"><?php echo esc_html($idx + 1); ?></span>
            </div>
            <div class="ytcp-card-info">
                <h4><?php echo esc_html($v->post_title); ?></h4>
                <span class="ytcp-card-views"><?php echo esc_html(YTCP_YouTube_API::format_view_count($v_views)); ?> views</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php get_footer(); ?>
