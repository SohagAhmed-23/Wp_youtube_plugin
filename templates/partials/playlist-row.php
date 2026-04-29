<?php
if (!defined('ABSPATH')) exit;
if (empty($videos) || empty($playlist)) return;

$direction = ($row_index % 2 === 0) ? 'ltr' : 'rtl';
$slider_id = 'ytcp-slider-' . ($playlist->ID ?: sanitize_title($playlist->post_title)) . '-' . $row_index;
$playlist_yt_id = $playlist->ID ? get_post_meta($playlist->ID, '_ytcp_youtube_id', true) : '';
?>
<div class="ytcp-row" data-direction="<?php echo esc_attr($direction); ?>">
    <div class="ytcp-row-header">
        <h2 class="ytcp-row-title"><?php echo esc_html($playlist->post_title); ?></h2>
    </div>

    <div class="swiper ytcp-slider" id="<?php echo esc_attr($slider_id); ?>">
        <div class="swiper-wrapper">
            <?php foreach ($videos as $v):
                $v_yt_id = get_post_meta($v->ID, '_ytcp_youtube_id', true);
                $v_thumb = get_post_meta($v->ID, '_ytcp_thumbnail', true);
                $v_dur = get_post_meta($v->ID, '_ytcp_duration_formatted', true);
                $v_date = get_the_date('Y-m-d', $v->ID);
                $v_desc = wp_trim_words($v->post_content, 25, '...');
                $pl_title = $playlist->post_title;
                $pl_desc = $playlist->ID ? wp_trim_words($playlist->post_content ?? '', 40, '...') : '';
            ?>
            <div class="swiper-slide">
                <div class="ytcp-video-card"
                     data-video-id="<?php echo esc_attr($v->ID); ?>"
                     data-youtube-id="<?php echo esc_attr($v_yt_id); ?>"
                     data-permalink="<?php echo esc_url(get_permalink($v->ID)); ?>"
                     data-title="<?php echo esc_attr($v->post_title); ?>"
                     data-description="<?php echo esc_attr($v_desc); ?>"
                     data-playlist-title="<?php echo esc_attr($pl_title); ?>"
                     data-playlist-desc="<?php echo esc_attr($pl_desc); ?>"
                     data-thumbnail="<?php echo esc_url($v_thumb); ?>">

                    <div class="ytcp-card-thumb-wrap">
                        <img class="ytcp-card-thumb-img"
                             src="<?php echo esc_url($v_thumb); ?>"
                             alt="<?php echo esc_attr($v->post_title); ?>"
                             loading="lazy"
                             referrerpolicy="no-referrer">

                        <div class="ytcp-card-overlay-info">
                            <div class="ytcp-card-overlay-title"><?php echo esc_html($v->post_title); ?></div>
                            <div class="ytcp-card-overlay-date"><?php echo esc_html($v_date); ?></div>
                        </div>

                        <div class="ytcp-card-preview" data-youtube-id="<?php echo esc_attr($v_yt_id); ?>"></div>

                        <div class="ytcp-card-hover-overlay">
                            <button class="ytcp-card-play-btn" title="Play">
                                <svg viewBox="0 0 24 24" width="40" height="40">
                                    <circle cx="12" cy="12" r="11" fill="rgba(0,0,0,0.6)" stroke="white" stroke-width="1.5"/>
                                    <polygon points="9,7 18,12 9,17" fill="white"/>
                                </svg>
                            </button>
                        </div>

                        <?php
                        if (is_user_logged_in() && get_option('ytcp_enable_history', '1') === '1') {
                            $prog_svc = new YTCP_User_Progress();
                            $pct = $prog_svc->get_progress_percentage(get_current_user_id(), $v->ID);
                            if ($pct > 0):
                        ?>
                        <div class="ytcp-card-progress">
                            <div class="ytcp-card-progress-fill" style="width:<?php echo esc_attr($pct); ?>%"></div>
                        </div>
                        <?php endif; } ?>
                    </div>

                    <div class="ytcp-card-info-bar">
                        <h4 class="ytcp-card-title"><?php echo esc_html($v->post_title); ?></h4>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
    </div>
</div>
