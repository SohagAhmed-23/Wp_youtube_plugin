<?php
if (!defined('ABSPATH')) exit;
get_header();

$video_id = get_the_ID();
$youtube_id = get_post_meta($video_id, '_ytcp_youtube_id', true);
$duration = (int) get_post_meta($video_id, '_ytcp_duration', true);
$duration_fmt = get_post_meta($video_id, '_ytcp_duration_formatted', true);
$view_count = (int) get_post_meta($video_id, '_ytcp_view_count', true);
$playlist_id = get_post_meta($video_id, '_ytcp_playlist_id', true);

$playlist_videos = [];
$playlist = null;
if ($playlist_id) {
    $playlist = get_post($playlist_id);
    $vid_ids = get_post_meta($playlist_id, '_ytcp_video_ids', true);
    if (!empty($vid_ids)) {
        $playlist_videos = get_posts([
            'post_type'      => 'ytcp_video',
            'post__in'       => $vid_ids,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'posts_per_page' => 100,
            'post_status'    => 'publish',
        ]);
    }
}

$user_progress = null;
if (is_user_logged_in()) {
    $progress_svc = new YTCP_User_Progress();
    $user_progress = $progress_svc->get_progress(get_current_user_id(), $video_id);
}

$next_video = null;
if (!empty($playlist_videos)) {
    foreach ($playlist_videos as $i => $pv) {
        if ($pv->ID === $video_id && isset($playlist_videos[$i + 1])) {
            $next_video = $playlist_videos[$i + 1];
            break;
        }
    }
}

$enable_transcripts = get_option('ytcp_enable_transcripts', '1') === '1';
?>
<div class="ytcp-app ytcp-watch-page" id="ytcp-app">

    <!-- Search bar -->
    <section class="ytcp-search-section">
        <div class="ytcp-search-container">
            <div class="ytcp-search-input-wrap">
                <svg class="ytcp-search-icon" viewBox="0 0 24 24" width="18" height="18">
                    <circle cx="11" cy="11" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65" stroke="currentColor" stroke-width="2"/>
                </svg>
                <input type="text" id="ytcp-search-input" class="ytcp-search-input" placeholder="Search episodes..." autocomplete="off">
                <button class="ytcp-search-clear" id="ytcp-search-clear" style="display:none">&times;</button>
            </div>
            <div class="ytcp-search-results" id="ytcp-search-results" style="display:none">
                <div class="ytcp-search-results-inner" id="ytcp-search-results-inner"></div>
            </div>
        </div>
    </section>

    <div class="ytcp-watch-layout">
        <div class="ytcp-watch-main">
            <!-- Player -->
            <div class="ytcp-player-container"
                 id="ytcp-player-container"
                 data-video-id="<?php echo esc_attr($video_id); ?>"
                 data-youtube-id="<?php echo esc_attr($youtube_id); ?>"
                 data-duration="<?php echo esc_attr($duration); ?>"
                 data-start-time="<?php echo esc_attr($user_progress ? $user_progress->current_time : 0); ?>"
                 data-next-video="<?php echo $next_video ? esc_url(get_permalink($next_video->ID)) : ''; ?>"
                 data-next-video-id="<?php echo $next_video ? esc_attr($next_video->ID) : ''; ?>">

                <div id="ytcp-player" class="ytcp-player"></div>

                <!-- Player Controls Overlay -->
                <div class="ytcp-player-overlay" id="ytcp-player-overlay">
                    <div class="ytcp-player-controls">
                        <button class="ytcp-ctrl-btn" id="ytcp-play-pause" title="Play/Pause">
                            <svg class="play-icon" viewBox="0 0 24 24" width="28" height="28"><polygon points="5,3 19,12 5,21" fill="currentColor"/></svg>
                            <svg class="pause-icon" viewBox="0 0 24 24" width="28" height="28" style="display:none"><rect x="5" y="3" width="4" height="18" fill="currentColor"/><rect x="15" y="3" width="4" height="18" fill="currentColor"/></svg>
                        </button>
                        <div class="ytcp-progress-bar" id="ytcp-progress-bar">
                            <div class="ytcp-progress-fill" id="ytcp-progress-fill"></div>
                            <div class="ytcp-progress-handle" id="ytcp-progress-handle"></div>
                        </div>
                        <span class="ytcp-time" id="ytcp-time-display">0:00 / 0:00</span>
                        <div class="ytcp-speed-control">
                            <button class="ytcp-ctrl-btn" id="ytcp-speed-btn" title="Playback Speed">1x</button>
                            <div class="ytcp-speed-menu" id="ytcp-speed-menu" style="display:none">
                                <button data-speed="0.5">0.5x</button>
                                <button data-speed="0.75">0.75x</button>
                                <button data-speed="1" class="active">1x</button>
                                <button data-speed="1.25">1.25x</button>
                                <button data-speed="1.5">1.5x</button>
                                <button data-speed="2">2x</button>
                            </div>
                        </div>
                        <?php if (get_option('ytcp_enable_pip', '1') === '1'): ?>
                        <button class="ytcp-ctrl-btn" id="ytcp-pip-btn" title="Picture-in-Picture">
                            <svg viewBox="0 0 24 24" width="20" height="20"><rect x="1" y="3" width="22" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><rect x="11" y="11" width="10" height="8" rx="1" fill="currentColor"/></svg>
                        </button>
                        <?php endif; ?>
                        <button class="ytcp-ctrl-btn" id="ytcp-fullscreen-btn" title="Fullscreen">
                            <svg viewBox="0 0 24 24" width="20" height="20"><path d="M3 3h6v2H5v4H3V3zm12 0h6v6h-2V5h-4V3zM3 15h2v4h4v2H3v-6zm16 4h-4v2h6v-6h-2v4z" fill="currentColor"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Next Episode Overlay -->
                <?php if ($next_video && get_option('ytcp_enable_autoplay', '1') === '1'): ?>
                <div class="ytcp-next-overlay" id="ytcp-next-overlay" style="display:none">
                    <div class="ytcp-next-info">
                        <p>Up Next</p>
                        <h3><?php echo esc_html($next_video->post_title); ?></h3>
                        <div class="ytcp-next-countdown">
                            Playing in <span id="ytcp-countdown">10</span>s
                        </div>
                        <a href="<?php echo esc_url(get_permalink($next_video->ID)); ?>" class="ytcp-btn-play" id="ytcp-play-next">Play Now</a>
                        <button class="ytcp-btn-cancel" id="ytcp-cancel-next">Cancel</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Video Info -->
            <div class="ytcp-video-info">
                <div class="ytcp-video-info-header">
                    <div>
                        <h1 class="ytcp-video-title"><?php the_title(); ?></h1>
                        <div class="ytcp-video-description">
                            <?php the_content(); ?>
                        </div>
                    </div>
                    <?php if ($enable_transcripts): ?>
                    <button class="ytcp-download-transcript-btn" id="ytcp-transcript-download">
                        Download Transcript
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar with Tabs -->
        <?php if (!empty($playlist_videos) || $enable_transcripts): ?>
        <div class="ytcp-watch-sidebar" id="ytcp-watch-sidebar">
            <!-- Tabs -->
            <div class="ytcp-sidebar-tabs">
                <?php if (!empty($playlist_videos)): ?>
                <button class="ytcp-sidebar-tab active" data-tab="episodes">Episodes</button>
                <?php endif; ?>
                <?php if ($enable_transcripts): ?>
                <button class="ytcp-sidebar-tab <?php echo esc_attr(empty($playlist_videos) ? 'active' : ''); ?>" data-tab="transcript">Transcript</button>
                <?php endif; ?>
            </div>

            <!-- Episodes Panel -->
            <?php if (!empty($playlist_videos)): ?>
            <div class="ytcp-sidebar-panel active" id="ytcp-panel-episodes">
                <?php foreach ($playlist_videos as $idx => $pv):
                    $pv_yt_id = get_post_meta($pv->ID, '_ytcp_youtube_id', true);
                    $pv_thumb = get_post_meta($pv->ID, '_ytcp_thumbnail', true);
                    $pv_dur = get_post_meta($pv->ID, '_ytcp_duration_formatted', true);
                    $pv_desc = wp_trim_words($pv->post_content, 15, '...');
                    $is_current = ($pv->ID === $video_id);
                ?>
                <a href="<?php echo esc_url(get_permalink($pv->ID)); ?>"
                   class="ytcp-episode-item <?php echo esc_attr($is_current ? 'active' : ''); ?>">
                    <div class="ytcp-episode-thumb">
                        <img src="<?php echo esc_url($pv_thumb); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                        <?php if ($is_current): ?>
                            <div class="ytcp-now-playing">
                                <span></span><span></span><span></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="ytcp-episode-info">
                        <h4><?php echo esc_html($pv->post_title); ?></h4>
                        <?php if ($pv_desc): ?>
                        <p class="ytcp-episode-desc"><?php echo esc_html($pv_desc); ?></p>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Transcript Panel -->
            <?php if ($enable_transcripts): ?>
            <div class="ytcp-sidebar-panel <?php echo esc_attr(empty($playlist_videos) ? 'active' : ''); ?>" id="ytcp-panel-transcript">
                <div class="ytcp-transcript-panel-content">
                    <div class="ytcp-transcript-lines-sidebar" id="ytcp-transcript-lines">
                        <div class="ytcp-skeleton-loader">
                            <div class="ytcp-skeleton-line"></div>
                            <div class="ytcp-skeleton-line"></div>
                            <div class="ytcp-skeleton-line"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php get_footer(); ?>
