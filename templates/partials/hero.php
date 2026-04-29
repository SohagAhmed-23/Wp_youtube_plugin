<?php
if (!defined('ABSPATH')) exit;

$hero_image = get_option('ytcp_hero_image', '');
$channel_logo = get_option('ytcp_channel_logo', '');
$hero_title = get_option('ytcp_hero_title', '');

if (empty($channel_logo)) {
    $channel_logo = get_option('ytcp_channel_logo_url', '');
}

if (empty($hero_title)) {
    $hero_title = get_option('ytcp_channel_name', 'YTChannel Pro');
}

if (empty($hero_image)) {
    $hero_image = get_option('ytcp_channel_banner', '');
}

if (empty($hero_image)) {
    $featured_video = get_posts([
        'post_type'      => 'ytcp_video',
        'posts_per_page' => 1,
        'meta_key'       => '_ytcp_view_count',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
        'post_status'    => 'publish',
    ]);
    if (!empty($featured_video)) {
        $hero_image = get_post_meta($featured_video[0]->ID, '_ytcp_thumbnail', true);
    }
}
?>
<section class="ytcp-hero">
    <?php if ($hero_image): ?>
    <img class="ytcp-hero-bg" src="<?php echo esc_url($hero_image); ?>" alt="" referrerpolicy="no-referrer">
    <?php endif; ?>
    <div class="ytcp-hero-overlay">
        <div class="ytcp-hero-content">
            <?php if ($channel_logo): ?>
            <div class="ytcp-channel-logo">
                <img src="<?php echo esc_url($channel_logo); ?>" alt="Channel Logo" referrerpolicy="no-referrer">
            </div>
            <?php endif; ?>
            <h1 class="ytcp-hero-title"><?php echo esc_html($hero_title); ?></h1>
        </div>
    </div>
</section>
