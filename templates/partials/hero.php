<?php
if (!defined('ABSPATH')) exit;

$hero_image = get_option('ytflix_hero_image', '');
$channel_logo = get_option('ytflix_channel_logo', '');
$hero_title = get_option('ytflix_hero_title', '');

if (empty($channel_logo)) {
    $channel_logo = get_option('ytflix_channel_logo_url', '');
}

if (empty($hero_title)) {
    $hero_title = get_option('ytflix_channel_name', 'YTFlix');
}

if (empty($hero_image)) {
    $hero_image = get_option('ytflix_channel_banner', '');
}

if (empty($hero_image)) {
    $featured_video = get_posts([
        'post_type'      => 'ytflix_video',
        'posts_per_page' => 1,
        'meta_key'       => '_ytflix_view_count',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
        'post_status'    => 'publish',
    ]);
    if (!empty($featured_video)) {
        $hero_image = get_post_meta($featured_video[0]->ID, '_ytflix_thumbnail', true);
    }
}
?>
<section class="ytflix-hero" <?php if ($hero_image): ?>style="background-image:url('<?php echo esc_url($hero_image); ?>')"<?php endif; ?>>
    <div class="ytflix-hero-overlay">
        <div class="ytflix-hero-content">
            <?php if ($channel_logo): ?>
            <div class="ytflix-channel-logo">
                <img src="<?php echo esc_url($channel_logo); ?>" alt="Channel Logo">
            </div>
            <?php endif; ?>
            <h1 class="ytflix-hero-title"><?php echo esc_html($hero_title); ?></h1>
        </div>
    </div>
</section>
