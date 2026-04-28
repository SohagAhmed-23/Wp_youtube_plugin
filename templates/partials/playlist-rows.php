<?php
if (!defined('ABSPATH')) exit;

$playlists = get_posts([
    'post_type'      => 'ytflix_playlist',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
]);

if (empty($playlists)) return;
?>
<section class="ytflix-rows-section">
    <?php
    $row_index = 0;
    foreach ($playlists as $playlist):
        $video_ids = get_post_meta($playlist->ID, '_ytflix_video_ids', true);
        if (empty($video_ids)) continue;

        $videos = get_posts([
            'post_type'      => 'ytflix_video',
            'post__in'       => $video_ids,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
        ]);

        if (empty($videos)) continue;

        include YTFLIX_PLUGIN_DIR . 'templates/partials/playlist-row.php';
        $row_index++;
    endforeach;
    ?>
</section>
