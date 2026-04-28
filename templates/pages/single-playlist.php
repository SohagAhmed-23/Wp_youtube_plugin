<?php
if (!defined('ABSPATH')) exit;
get_header();

$playlist_id = get_the_ID();
$youtube_id = get_post_meta($playlist_id, '_ytflix_youtube_id', true);
$thumbnail = get_post_meta($playlist_id, '_ytflix_thumbnail', true);
$video_ids = get_post_meta($playlist_id, '_ytflix_video_ids', true);

$videos = [];
if (!empty($video_ids)) {
    $videos = get_posts([
        'post_type'      => 'ytflix_video',
        'post__in'       => $video_ids,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'posts_per_page' => 100,
        'post_status'    => 'publish',
    ]);
}

$first_video = !empty($videos) ? $videos[0] : null;
?>
<div class="ytflix-app ytflix-playlist-page" id="ytflix-app">
    <div class="ytflix-playlist-hero" style="background-image:url('<?php echo esc_url($thumbnail); ?>')">
        <div class="ytflix-playlist-hero-overlay">
            <h1><?php the_title(); ?></h1>
            <p class="ytflix-playlist-desc"><?php echo wp_kses_post(get_the_content()); ?></p>
            <div class="ytflix-playlist-meta">
                <span><?php echo (int) count($videos); ?> episodes</span>
            </div>
            <?php if ($first_video): ?>
            <a href="<?php echo esc_url(get_permalink($first_video->ID)); ?>" class="ytflix-btn-play-large">
                <svg viewBox="0 0 24 24" width="24" height="24"><polygon points="5,3 19,12 5,21" fill="currentColor"/></svg>
                Play
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="ytflix-playlist-grid">
        <?php foreach ($videos as $idx => $v):
            $v_thumb = get_post_meta($v->ID, '_ytflix_thumbnail', true);
            $v_dur = get_post_meta($v->ID, '_ytflix_duration_formatted', true);
            $v_views = (int) get_post_meta($v->ID, '_ytflix_view_count', true);
        ?>
        <a href="<?php echo esc_url(get_permalink($v->ID)); ?>" class="ytflix-playlist-video-card">
            <div class="ytflix-card-thumb">
                <img src="<?php echo esc_url($v_thumb); ?>" alt="<?php echo esc_attr($v->post_title); ?>" loading="lazy" referrerpolicy="no-referrer">
                <span class="ytflix-card-duration"><?php echo esc_html($v_dur); ?></span>
                <span class="ytflix-card-number"><?php echo esc_html($idx + 1); ?></span>
            </div>
            <div class="ytflix-card-info">
                <h4><?php echo esc_html($v->post_title); ?></h4>
                <span class="ytflix-card-views"><?php echo esc_html(YTFlix_YouTube_API::format_view_count($v_views)); ?> views</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php get_footer(); ?>
