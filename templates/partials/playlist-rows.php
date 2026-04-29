<?php
if (!defined('ABSPATH')) exit;

$playlists = get_posts([
    'post_type'      => 'ytcp_playlist',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
]);

if (empty($playlists)) return;

$eager_count = 3;
?>
<section class="ytcp-rows-section">
    <?php
    $row_index = 0;
    foreach ($playlists as $playlist):
        $video_ids = get_post_meta($playlist->ID, '_ytcp_video_ids', true);
        if (empty($video_ids)) continue;

        if ($row_index < $eager_count):
            $videos = get_posts([
                'post_type'      => 'ytcp_video',
                'post__in'       => $video_ids,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
                'posts_per_page' => 50,
                'post_status'    => 'publish',
            ]);

            if (empty($videos)) { $row_index++; continue; }

            include YTCP_PLUGIN_DIR . 'templates/partials/playlist-row.php';
        else:
            ?>
            <div class="ytcp-lazy-row" data-playlist-id="<?php echo esc_attr($playlist->ID); ?>">
                <h2 class="ytcp-row-title"><?php echo esc_html($playlist->post_title); ?></h2>
                <div class="ytcp-skeleton-loader">
                    <div class="ytcp-skeleton-line" style="height:180px;border-radius:8px"></div>
                </div>
            </div>
            <?php
        endif;
        $row_index++;
    endforeach;
    ?>
</section>
