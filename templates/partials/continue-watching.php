<?php
if (!defined('ABSPATH')) exit;
if (empty($continue_watching)) return;
?>
<div class="ytcp-row ytcp-continue-row">
    <div class="ytcp-row-header">
        <h2 class="ytcp-row-title">Continue Watching</h2>
    </div>

    <div class="swiper ytcp-slider" id="ytcp-slider-continue">
        <div class="swiper-wrapper">
            <?php foreach ($continue_watching as $item):
                $v_yt_id = get_post_meta($item->ID, '_ytcp_youtube_id', true);
                $v_thumb = get_post_meta($item->ID, '_ytcp_thumbnail', true);
                $v_dur = get_post_meta($item->ID, '_ytcp_duration_formatted', true);
                $v_date = get_the_date('Y-m-d', $item->ID);
                $pct = ($item->progress_duration > 0) ? round(($item->progress_time / $item->progress_duration) * 100) : 0;
            ?>
            <div class="swiper-slide">
                <div class="ytcp-video-card ytcp-continue-card"
                     data-video-id="<?php echo esc_attr($item->ID); ?>"
                     data-youtube-id="<?php echo esc_attr($v_yt_id); ?>"
                     data-permalink="<?php echo esc_url(get_permalink($item->ID)); ?>"
                     data-title="<?php echo esc_attr($item->post_title); ?>"
                     data-description=""
                     data-playlist-title="Continue Watching"
                     data-playlist-desc=""
                     data-thumbnail="<?php echo esc_url($v_thumb); ?>">

                    <div class="ytcp-card-thumb-wrap">
                        <img class="ytcp-card-thumb-img" src="<?php echo esc_url($v_thumb); ?>" alt="<?php echo esc_attr($item->post_title); ?>" loading="lazy" referrerpolicy="no-referrer">

                        <div class="ytcp-card-overlay-info">
                            <div class="ytcp-card-overlay-title"><?php echo esc_html($item->post_title); ?></div>
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

                        <div class="ytcp-card-progress">
                            <div class="ytcp-card-progress-fill" style="width:<?php echo esc_attr($pct); ?>%"></div>
                        </div>
                    </div>

                    <div class="ytcp-card-info-bar">
                        <h4 class="ytcp-card-title"><?php echo esc_html($item->post_title); ?></h4>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
    </div>
</div>
