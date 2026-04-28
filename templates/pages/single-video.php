<?php
if (!defined('ABSPATH')) exit;
get_header();

$video_id = get_the_ID();
$youtube_id = get_post_meta($video_id, '_ytflix_youtube_id', true);
$duration = (int) get_post_meta($video_id, '_ytflix_duration', true);
$duration_fmt = get_post_meta($video_id, '_ytflix_duration_formatted', true);
$view_count = (int) get_post_meta($video_id, '_ytflix_view_count', true);
$playlist_id = get_post_meta($video_id, '_ytflix_playlist_id', true);

$playlist_videos = [];
$playlist = null;
if ($playlist_id) {
    $playlist = get_post($playlist_id);
    $vid_ids = get_post_meta($playlist_id, '_ytflix_video_ids', true);
    if (!empty($vid_ids)) {
        $playlist_videos = get_posts([
            'post_type'      => 'ytflix_video',
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
    $progress_svc = new YTFlix_User_Progress();
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

$enable_transcripts = get_option('ytflix_enable_transcripts', '1') === '1';
?>
<div class="ytflix-app ytflix-watch-page" id="ytflix-app">

    <!-- Search bar -->
    <section class="ytflix-search-section">
        <div class="ytflix-search-container">
            <div class="ytflix-search-input-wrap">
                <svg class="ytflix-search-icon" viewBox="0 0 24 24" width="18" height="18">
                    <circle cx="11" cy="11" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65" stroke="currentColor" stroke-width="2"/>
                </svg>
                <input type="text" id="ytflix-search-input" class="ytflix-search-input" placeholder="Search episodes..." autocomplete="off">
                <button class="ytflix-search-clear" id="ytflix-search-clear" style="display:none">&times;</button>
            </div>
            <div class="ytflix-search-results" id="ytflix-search-results" style="display:none">
                <div class="ytflix-search-results-inner" id="ytflix-search-results-inner"></div>
            </div>
        </div>
    </section>

    <div class="ytflix-watch-layout">
        <div class="ytflix-watch-main">
            <!-- Player -->
            <div class="ytflix-player-container"
                 id="ytflix-player-container"
                 data-video-id="<?php echo esc_attr($video_id); ?>"
                 data-youtube-id="<?php echo esc_attr($youtube_id); ?>"
                 data-duration="<?php echo esc_attr($duration); ?>"
                 data-start-time="<?php echo esc_attr($user_progress ? $user_progress->current_time : 0); ?>"
                 data-next-video="<?php echo $next_video ? esc_url(get_permalink($next_video->ID)) : ''; ?>"
                 data-next-video-id="<?php echo $next_video ? esc_attr($next_video->ID) : ''; ?>">

                <div id="ytflix-player" class="ytflix-player"></div>

                <!-- Player Controls Overlay -->
                <div class="ytflix-player-overlay" id="ytflix-player-overlay">
                    <div class="ytflix-player-controls">
                        <button class="ytflix-ctrl-btn" id="ytflix-play-pause" title="Play/Pause">
                            <svg class="play-icon" viewBox="0 0 24 24" width="28" height="28"><polygon points="5,3 19,12 5,21" fill="currentColor"/></svg>
                            <svg class="pause-icon" viewBox="0 0 24 24" width="28" height="28" style="display:none"><rect x="5" y="3" width="4" height="18" fill="currentColor"/><rect x="15" y="3" width="4" height="18" fill="currentColor"/></svg>
                        </button>
                        <div class="ytflix-progress-bar" id="ytflix-progress-bar">
                            <div class="ytflix-progress-fill" id="ytflix-progress-fill"></div>
                            <div class="ytflix-progress-handle" id="ytflix-progress-handle"></div>
                        </div>
                        <span class="ytflix-time" id="ytflix-time-display">0:00 / 0:00</span>
                        <div class="ytflix-speed-control">
                            <button class="ytflix-ctrl-btn" id="ytflix-speed-btn" title="Playback Speed">1x</button>
                            <div class="ytflix-speed-menu" id="ytflix-speed-menu" style="display:none">
                                <button data-speed="0.5">0.5x</button>
                                <button data-speed="0.75">0.75x</button>
                                <button data-speed="1" class="active">1x</button>
                                <button data-speed="1.25">1.25x</button>
                                <button data-speed="1.5">1.5x</button>
                                <button data-speed="2">2x</button>
                            </div>
                        </div>
                        <?php if (get_option('ytflix_enable_pip', '1') === '1'): ?>
                        <button class="ytflix-ctrl-btn" id="ytflix-pip-btn" title="Picture-in-Picture">
                            <svg viewBox="0 0 24 24" width="20" height="20"><rect x="1" y="3" width="22" height="18" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><rect x="11" y="11" width="10" height="8" rx="1" fill="currentColor"/></svg>
                        </button>
                        <?php endif; ?>
                        <button class="ytflix-ctrl-btn" id="ytflix-fullscreen-btn" title="Fullscreen">
                            <svg viewBox="0 0 24 24" width="20" height="20"><path d="M3 3h6v2H5v4H3V3zm12 0h6v6h-2V5h-4V3zM3 15h2v4h4v2H3v-6zm16 4h-4v2h6v-6h-2v4z" fill="currentColor"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Next Episode Overlay -->
                <?php if ($next_video && get_option('ytflix_enable_autoplay', '1') === '1'): ?>
                <div class="ytflix-next-overlay" id="ytflix-next-overlay" style="display:none">
                    <div class="ytflix-next-info">
                        <p>Up Next</p>
                        <h3><?php echo esc_html($next_video->post_title); ?></h3>
                        <div class="ytflix-next-countdown">
                            Playing in <span id="ytflix-countdown">10</span>s
                        </div>
                        <a href="<?php echo esc_url(get_permalink($next_video->ID)); ?>" class="ytflix-btn-play" id="ytflix-play-next">Play Now</a>
                        <button class="ytflix-btn-cancel" id="ytflix-cancel-next">Cancel</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Video Info -->
            <div class="ytflix-video-info">
                <div class="ytflix-video-info-header">
                    <div>
                        <h1 class="ytflix-video-title"><?php the_title(); ?></h1>
                        <div class="ytflix-video-description">
                            <?php the_content(); ?>
                        </div>
                    </div>
                    <?php if ($enable_transcripts): ?>
                    <button class="ytflix-download-transcript-btn" id="ytflix-transcript-download">
                        Download Transcript
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar with Tabs -->
        <?php if (!empty($playlist_videos) || $enable_transcripts): ?>
        <div class="ytflix-watch-sidebar" id="ytflix-watch-sidebar">
            <!-- Tabs -->
            <div class="ytflix-sidebar-tabs">
                <?php if (!empty($playlist_videos)): ?>
                <button class="ytflix-sidebar-tab active" data-tab="episodes">Episodes</button>
                <?php endif; ?>
                <?php if ($enable_transcripts): ?>
                <button class="ytflix-sidebar-tab <?php echo esc_attr(empty($playlist_videos) ? 'active' : ''); ?>" data-tab="transcript">Transcript</button>
                <?php endif; ?>
            </div>

            <!-- Episodes Panel -->
            <?php if (!empty($playlist_videos)): ?>
            <div class="ytflix-sidebar-panel active" id="ytflix-panel-episodes">
                <?php foreach ($playlist_videos as $idx => $pv):
                    $pv_yt_id = get_post_meta($pv->ID, '_ytflix_youtube_id', true);
                    $pv_thumb = get_post_meta($pv->ID, '_ytflix_thumbnail', true);
                    $pv_dur = get_post_meta($pv->ID, '_ytflix_duration_formatted', true);
                    $pv_desc = wp_trim_words($pv->post_content, 15, '...');
                    $is_current = ($pv->ID === $video_id);
                ?>
                <a href="<?php echo esc_url(get_permalink($pv->ID)); ?>"
                   class="ytflix-episode-item <?php echo esc_attr($is_current ? 'active' : ''); ?>">
                    <div class="ytflix-episode-thumb">
                        <img src="<?php echo esc_url($pv_thumb); ?>" alt="" loading="lazy">
                        <?php if ($is_current): ?>
                            <div class="ytflix-now-playing">
                                <span></span><span></span><span></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="ytflix-episode-info">
                        <h4><?php echo esc_html($pv->post_title); ?></h4>
                        <?php if ($pv_desc): ?>
                        <p class="ytflix-episode-desc"><?php echo esc_html($pv_desc); ?></p>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Transcript Panel -->
            <?php if ($enable_transcripts): ?>
            <div class="ytflix-sidebar-panel <?php echo esc_attr(empty($playlist_videos) ? 'active' : ''); ?>" id="ytflix-panel-transcript">
                <div class="ytflix-transcript-panel-content">
                    <div class="ytflix-transcript-lines-sidebar" id="ytflix-transcript-lines">
                        <div class="ytflix-skeleton-loader">
                            <div class="ytflix-skeleton-line"></div>
                            <div class="ytflix-skeleton-line"></div>
                            <div class="ytflix-skeleton-line"></div>
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
