/**
 * YTFlix Frontend JavaScript
 * Handles: Swiper sliders, YouTube player, transcript sync in sidebar tabs,
 * modal popup on card click, progress tracking, search, favorites,
 * auto-play next, skip intro, speed control, PiP
 */
(function($) {
    'use strict';

    const YTFLIX = {
        player: null,
        playerReady: false,
        progressInterval: null,
        countdownInterval: null,
        previewPlayers: {},
        previewTimeout: null,
        searchTimeout: null,
        transcriptData: [],
        transcriptLoaded: false,
        modalPlayer: null,

        cache: {
            _prefix: 'ytflix_',
            _version: (typeof ytflixData !== 'undefined' ? ytflixData.cacheVersion : 0),

            get: function(key) {
                try {
                    var raw = sessionStorage.getItem(this._prefix + key);
                    if (!raw) return null;
                    var entry = JSON.parse(raw);
                    if (entry.v !== this._version) { sessionStorage.removeItem(this._prefix + key); return null; }
                    if (Date.now() > entry.exp) { sessionStorage.removeItem(this._prefix + key); return null; }
                    return entry.data;
                } catch(e) { return null; }
            },

            set: function(key, data, ttlMs) {
                try {
                    sessionStorage.setItem(this._prefix + key, JSON.stringify({
                        v: this._version, exp: Date.now() + ttlMs, data: data
                    }));
                } catch(e) { this.prune(); }
            },

            prune: function() {
                try {
                    var toRemove = [];
                    for (var i = 0; i < sessionStorage.length; i++) {
                        var k = sessionStorage.key(i);
                        if (k && k.indexOf(this._prefix) === 0) {
                            try {
                                var entry = JSON.parse(sessionStorage.getItem(k));
                                if (entry.v !== this._version || Date.now() > entry.exp) toRemove.push(k);
                            } catch(e) { toRemove.push(k); }
                        }
                    }
                    toRemove.forEach(function(k) { sessionStorage.removeItem(k); });
                } catch(e) {}
            }
        },

        init: function() {
            this.initSliders();
            this.initSearch();
            this.initCardInteractions();
            this.initModal();
            this.initPlayer();
            this.initSidebarTabs();
            this.initTranscript();
            this.initFavorites();
            this.initLazyRows();
        },

        // =====================================================================
        // SWIPER SLIDERS
        // =====================================================================
        initSliders: function() {
            document.querySelectorAll('.ytflix-slider').forEach(function(el) {
                new Swiper('#' + el.id, {
                    slidesPerView: 'auto',
                    spaceBetween: 10,
                    freeMode: true,
                    grabCursor: true,
                    navigation: {
                        nextEl: '#' + el.id + ' .swiper-button-next',
                        prevEl: '#' + el.id + ' .swiper-button-prev',
                    },
                    breakpoints: {
                        320:  { spaceBetween: 8 },
                        768:  { spaceBetween: 10 },
                        1024: { spaceBetween: 12 },
                    },
                });
            });
        },

        // =====================================================================
        // SEARCH
        // =====================================================================
        initSearch: function() {
            var input = document.getElementById('ytflix-search-input');
            var results = document.getElementById('ytflix-search-results');
            var inner = document.getElementById('ytflix-search-results-inner');
            var clearBtn = document.getElementById('ytflix-search-clear');

            if (!input) return;

            input.addEventListener('input', function() {
                var q = this.value.trim();
                if (clearBtn) clearBtn.style.display = q ? 'block' : 'none';

                if (q.length < 2) {
                    if (results) results.style.display = 'none';
                    return;
                }

                clearTimeout(YTFLIX.searchTimeout);
                YTFLIX.searchTimeout = setTimeout(function() {
                    YTFLIX.doSearch(q, inner, results);
                }, 300);
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    input.value = '';
                    if (results) results.style.display = 'none';
                    clearBtn.style.display = 'none';
                    input.focus();
                });
            }

            document.addEventListener('click', function(e) {
                if (results && !e.target.closest('.ytflix-search-container')) {
                    results.style.display = 'none';
                }
            });
        },

        doSearch: function(q, inner, container) {
            var cacheKey = 'search_' + q.toLowerCase();
            var cached = YTFLIX.cache.get(cacheKey);
            if (cached) {
                YTFLIX.renderSearchResults(cached, inner, container);
                return;
            }

            $.ajax({
                url: ytflixData.ajaxUrl,
                data: { action: 'ytflix_search', q: q },
                success: function(resp) {
                    if (!resp.success || !resp.data.results.length) {
                        inner.innerHTML = '<div class="ytflix-search-no-results">No results found</div>';
                        container.style.display = 'block';
                        return;
                    }

                    YTFLIX.cache.set(cacheKey, resp.data.results, 120000);
                    YTFLIX.renderSearchResults(resp.data.results, inner, container);
                }
            });
        },

        renderSearchResults: function(results, inner, container) {
            var html = '';
            results.forEach(function(item) {
                html += '<a href="' + item.permalink + '" class="ytflix-search-result-item">';
                html += '<div class="ytflix-search-result-thumb"><img src="' + (item.thumbnail || '') + '" alt="" loading="lazy" referrerpolicy="no-referrer"></div>';
                html += '<div class="ytflix-search-result-info">';
                html += '<h4>' + YTFLIX.escHtml(item.title) + '</h4>';
                html += '<span>' + (item.type === 'playlist' ? item.count + ' episodes' : item.duration || '') + '</span>';
                html += '</div></a>';
            });
            inner.innerHTML = html;
            container.style.display = 'block';
        },

        // =====================================================================
        // CARD INTERACTIONS - Open modal on click (home page), direct nav on watch page
        // =====================================================================
        initCardInteractions: function() {
            document.querySelectorAll('.ytflix-video-card').forEach(function(card) {
                card.addEventListener('mouseenter', function() {
                    YTFLIX.previewTimeout = setTimeout(function() {
                        YTFLIX.startPreview(card);
                    }, 800);
                });

                card.addEventListener('mouseleave', function() {
                    clearTimeout(YTFLIX.previewTimeout);
                    YTFLIX.stopPreview(card);
                });

                card.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var isWatchPage = document.querySelector('.ytflix-watch-page');
                    if (isWatchPage) {
                        window.location.href = card.dataset.permalink;
                        return;
                    }

                    YTFLIX.openModal(card);
                });
            });
        },

        _ytApiLoading: false,

        ensureYTApi: function(callback) {
            if (window.YT && window.YT.Player) {
                callback();
                return;
            }
            if (!YTFLIX._ytApiLoading) {
                YTFLIX._ytApiLoading = true;
                var tag = document.createElement('script');
                tag.src = 'https://www.youtube.com/iframe_api';
                document.head.appendChild(tag);
            }
            var check = setInterval(function() {
                if (window.YT && window.YT.Player) {
                    clearInterval(check);
                    callback();
                }
            }, 100);
        },

        startPreview: function(card) {
            var ytId = card.dataset.youtubeId;
            var previewEl = card.querySelector('.ytflix-card-preview');
            if (!ytId || !previewEl) return;

            if (!window.YT || !window.YT.Player) {
                YTFLIX.ensureYTApi(function() { YTFLIX.startPreview(card); });
                return;
            }

            var containerId = 'preview-' + ytId + '-' + Math.random().toString(36).substr(2, 5);
            previewEl.innerHTML = '<div id="' + containerId + '"></div>';

            try {
                YTFLIX.previewPlayers[ytId] = new YT.Player(containerId, {
                    width: '100%',
                    height: '100%',
                    videoId: ytId,
                    playerVars: {
                        autoplay: 1, mute: 1, controls: 0, modestbranding: 1,
                        showinfo: 0, rel: 0, start: 10, end: 20, loop: 0, playsinline: 1,
                    },
                    events: {
                        onReady: function(e) { e.target.playVideo(); }
                    }
                });
            } catch(e) {}
        },

        stopPreview: function(card) {
            var ytId = card.dataset.youtubeId;
            if (YTFLIX.previewPlayers[ytId]) {
                try { YTFLIX.previewPlayers[ytId].destroy(); } catch(e) {}
                delete YTFLIX.previewPlayers[ytId];
            }
            var previewEl = card.querySelector('.ytflix-card-preview');
            if (previewEl) previewEl.innerHTML = '';
        },

        // =====================================================================
        // MODAL POPUP
        // =====================================================================
        initModal: function() {
            var backdrop = document.getElementById('ytflix-modal-backdrop');
            if (!backdrop) return;

            var closeBtn = document.getElementById('ytflix-modal-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    YTFLIX.closeModal();
                });
            }

            backdrop.addEventListener('click', function(e) {
                if (e.target === backdrop) {
                    YTFLIX.closeModal();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && backdrop.classList.contains('active')) {
                    YTFLIX.closeModal();
                }
            });
        },

        openModal: function(card) {
            var backdrop = document.getElementById('ytflix-modal-backdrop');
            if (!backdrop) return;

            var ytId = card.dataset.youtubeId;
            var permalink = card.dataset.permalink;
            var title = card.dataset.title || '';
            var description = card.dataset.description || '';
            var playlistTitle = card.dataset.playlistTitle || '';
            var playlistDesc = card.dataset.playlistDesc || '';
            var thumbnail = card.dataset.thumbnail || '';

            document.getElementById('ytflix-modal-playlist-title').textContent = playlistTitle;
            document.getElementById('ytflix-modal-playlist-desc').textContent = playlistDesc;
            document.getElementById('ytflix-modal-video-title').textContent = title;
            document.getElementById('ytflix-modal-video-desc').textContent = description;
            document.getElementById('ytflix-modal-play-btn').href = permalink;

            var previewContainer = document.getElementById('ytflix-modal-preview');
            if (ytId && window.YT && window.YT.Player) {
                previewContainer.innerHTML = '<div id="ytflix-modal-yt-player"></div>';
                try {
                    YTFLIX.modalPlayer = new YT.Player('ytflix-modal-yt-player', {
                        width: '100%',
                        height: '100%',
                        videoId: ytId,
                        playerVars: {
                            autoplay: 1, mute: 0, controls: 1, modestbranding: 1,
                            rel: 0, playsinline: 1,
                        }
                    });
                } catch(e) {
                    previewContainer.innerHTML = '<img src="' + YTFLIX.escHtml(thumbnail) + '" alt="" referrerpolicy="no-referrer">';
                }
            } else {
                previewContainer.innerHTML = '<img src="' + YTFLIX.escHtml(thumbnail) + '" alt="" referrerpolicy="no-referrer">';
            }

            backdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
        },

        closeModal: function() {
            var backdrop = document.getElementById('ytflix-modal-backdrop');
            if (!backdrop) return;

            backdrop.classList.remove('active');
            document.body.style.overflow = '';

            if (YTFLIX.modalPlayer) {
                try { YTFLIX.modalPlayer.destroy(); } catch(e) {}
                YTFLIX.modalPlayer = null;
            }

            var previewContainer = document.getElementById('ytflix-modal-preview');
            if (previewContainer) previewContainer.innerHTML = '';
        },

        // =====================================================================
        // SIDEBAR TABS (Episodes / Transcript)
        // =====================================================================
        initSidebarTabs: function() {
            var tabs = document.querySelectorAll('.ytflix-sidebar-tab');
            if (!tabs.length) return;

            tabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    var target = this.dataset.tab;

                    tabs.forEach(function(t) { t.classList.remove('active'); });
                    this.classList.add('active');

                    document.querySelectorAll('.ytflix-sidebar-panel').forEach(function(panel) {
                        panel.classList.remove('active');
                    });

                    var targetPanel = document.getElementById('ytflix-panel-' + target);
                    if (targetPanel) {
                        targetPanel.classList.add('active');
                    }

                    if (target === 'transcript' && !YTFLIX.transcriptLoaded) {
                        YTFLIX.loadTranscript('en');
                    }
                });
            });
        },

        // =====================================================================
        // MAIN VIDEO PLAYER (Watch page)
        // =====================================================================
        initPlayer: function() {
            var container = document.getElementById('ytflix-player-container');
            if (!container) return;

            var ytId = container.dataset.youtubeId;
            var startTime = parseFloat(container.dataset.startTime) || 0;
            if (!ytId) return;

            if (window.YT && window.YT.Player) {
                YTFLIX.createPlayer(ytId, startTime);
            } else {
                window.onYouTubeIframeAPIReady = function() {
                    YTFLIX.createPlayer(ytId, startTime);
                };
            }

            this.initPlayerControls();
        },

        createPlayer: function(ytId, startTime) {
            YTFLIX.player = new YT.Player('ytflix-player', {
                width: '100%',
                height: '100%',
                videoId: ytId,
                playerVars: {
                    autoplay: 1, controls: 0, modestbranding: 1, rel: 0,
                    showinfo: 0, start: Math.floor(startTime), playsinline: 1,
                    enablejsapi: 1, origin: window.location.origin,
                },
                events: {
                    onReady: YTFLIX.onPlayerReady,
                    onStateChange: YTFLIX.onPlayerStateChange,
                }
            });
        },

        onPlayerReady: function() {
            YTFLIX.playerReady = true;
            YTFLIX.startProgressTracking();

        },

        onPlayerStateChange: function(event) {
            if (event.data === YT.PlayerState.PLAYING) {
                YTFLIX.updatePlayPauseIcon(true);
                YTFLIX.startProgressTracking();
            } else if (event.data === YT.PlayerState.PAUSED) {
                YTFLIX.updatePlayPauseIcon(false);
                YTFLIX.saveCurrentProgress();
            } else if (event.data === YT.PlayerState.ENDED) {
                YTFLIX.updatePlayPauseIcon(false);
                YTFLIX.saveCurrentProgress();
                YTFLIX.showNextEpisode();
            }
        },

        initPlayerControls: function() {
            var playPause = document.getElementById('ytflix-play-pause');
            if (playPause) {
                playPause.addEventListener('click', function() {
                    if (!YTFLIX.player || !YTFLIX.playerReady) return;
                    var state = YTFLIX.player.getPlayerState();
                    state === YT.PlayerState.PLAYING ? YTFLIX.player.pauseVideo() : YTFLIX.player.playVideo();
                });
            }

            var progressBar = document.getElementById('ytflix-progress-bar');
            if (progressBar) {
                progressBar.addEventListener('click', function(e) {
                    if (!YTFLIX.player || !YTFLIX.playerReady) return;
                    var rect = this.getBoundingClientRect();
                    var pct = (e.clientX - rect.left) / rect.width;
                    YTFLIX.player.seekTo(pct * YTFLIX.player.getDuration(), true);
                });
            }

            var speedBtn = document.getElementById('ytflix-speed-btn');
            var speedMenu = document.getElementById('ytflix-speed-menu');
            if (speedBtn && speedMenu) {
                speedBtn.addEventListener('click', function() {
                    speedMenu.style.display = speedMenu.style.display === 'none' ? 'block' : 'none';
                });
                speedMenu.querySelectorAll('button').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var speed = parseFloat(this.dataset.speed);
                        if (YTFLIX.player && YTFLIX.playerReady) YTFLIX.player.setPlaybackRate(speed);
                        speedMenu.querySelectorAll('button').forEach(function(b) { b.classList.remove('active'); });
                        this.classList.add('active');
                        speedBtn.textContent = speed + 'x';
                        speedMenu.style.display = 'none';
                    });
                });
            }

            var fsBtn = document.getElementById('ytflix-fullscreen-btn');
            if (fsBtn) {
                fsBtn.addEventListener('click', function() {
                    var c = document.getElementById('ytflix-player-container');
                    if (!c) return;
                    document.fullscreenElement ? document.exitFullscreen() : c.requestFullscreen().catch(function(){});
                });
            }

            var cancelNext = document.getElementById('ytflix-cancel-next');
            if (cancelNext) {
                cancelNext.addEventListener('click', function() {
                    clearInterval(YTFLIX.countdownInterval);
                    document.getElementById('ytflix-next-overlay').style.display = 'none';
                });
            }

            document.addEventListener('keydown', function(e) {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
                if (!YTFLIX.player || !YTFLIX.playerReady) return;
                if (document.getElementById('ytflix-modal-backdrop') &&
                    document.getElementById('ytflix-modal-backdrop').classList.contains('active')) return;

                switch(e.key) {
                    case ' ': case 'k':
                        e.preventDefault();
                        var st = YTFLIX.player.getPlayerState();
                        st === YT.PlayerState.PLAYING ? YTFLIX.player.pauseVideo() : YTFLIX.player.playVideo();
                        break;
                    case 'ArrowLeft':
                        e.preventDefault();
                        YTFLIX.player.seekTo(YTFLIX.player.getCurrentTime() - 10, true);
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        YTFLIX.player.seekTo(YTFLIX.player.getCurrentTime() + 10, true);
                        break;
                    case 'f':
                        e.preventDefault();
                        var cont = document.getElementById('ytflix-player-container');
                        document.fullscreenElement ? document.exitFullscreen() : cont.requestFullscreen().catch(function(){});
                        break;
                    case 'm':
                        e.preventDefault();
                        YTFLIX.player.isMuted() ? YTFLIX.player.unMute() : YTFLIX.player.mute();
                        break;
                }
            });
        },

        updatePlayPauseIcon: function(isPlaying) {
            var btn = document.getElementById('ytflix-play-pause');
            if (!btn) return;
            var playIcon = btn.querySelector('.play-icon');
            var pauseIcon = btn.querySelector('.pause-icon');
            if (playIcon) playIcon.style.display = isPlaying ? 'none' : 'block';
            if (pauseIcon) pauseIcon.style.display = isPlaying ? 'block' : 'none';
        },

        // =====================================================================
        // PROGRESS TRACKING
        // =====================================================================
        startProgressTracking: function() {
            if (YTFLIX.progressInterval) clearInterval(YTFLIX.progressInterval);

            YTFLIX.progressInterval = setInterval(function() {
                if (!YTFLIX.player || !YTFLIX.playerReady) return;
                if (YTFLIX.player.getPlayerState() !== YT.PlayerState.PLAYING) return;

                var current = YTFLIX.player.getCurrentTime();
                var duration = YTFLIX.player.getDuration();
                var pct = (current / duration) * 100;

                var fill = document.getElementById('ytflix-progress-fill');
                if (fill) fill.style.width = pct + '%';

                var timeDisplay = document.getElementById('ytflix-time-display');
                if (timeDisplay) {
                    timeDisplay.textContent = YTFLIX.formatTime(current) + ' / ' + YTFLIX.formatTime(duration);
                }

                YTFLIX.highlightTranscriptLine(current);
            }, 250);

            if (ytflixData.isLoggedIn && ytflixData.enableHistory === '1') {
                setInterval(function() { YTFLIX.saveCurrentProgress(); }, 15000);
            }
        },

        saveCurrentProgress: function() {
            if (!ytflixData.isLoggedIn || ytflixData.enableHistory !== '1') return;
            if (!YTFLIX.player || !YTFLIX.playerReady) return;

            var container = document.getElementById('ytflix-player-container');
            if (!container) return;

            $.post(ytflixData.ajaxUrl, {
                action: 'ytflix_save_progress',
                nonce: ytflixData.nonce,
                video_id: container.dataset.videoId,
                current_time: YTFLIX.player.getCurrentTime(),
                duration: YTFLIX.player.getDuration(),
            });
        },

        showNextEpisode: function() {
            if (ytflixData.enableAutoplay !== '1') return;
            var overlay = document.getElementById('ytflix-next-overlay');
            var countdownEl = document.getElementById('ytflix-countdown');
            var playNextBtn = document.getElementById('ytflix-play-next');
            if (!overlay || !playNextBtn || !playNextBtn.href) return;

            overlay.style.display = 'flex';
            var count = 10;
            if (countdownEl) countdownEl.textContent = count;

            YTFLIX.countdownInterval = setInterval(function() {
                count--;
                if (countdownEl) countdownEl.textContent = count;
                if (count <= 0) {
                    clearInterval(YTFLIX.countdownInterval);
                    window.location.href = playNextBtn.href;
                }
            }, 1000);
        },

        // =====================================================================
        // TRANSCRIPT (in sidebar panel)
        // =====================================================================
        initTranscript: function() {
            var downloadBtn = document.getElementById('ytflix-transcript-download');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    YTFLIX.downloadTranscript();
                });
            }

            var transcriptPanel = document.getElementById('ytflix-panel-transcript');
            if (transcriptPanel && transcriptPanel.classList.contains('active')) {
                YTFLIX.loadTranscript('en');
            }
        },

        loadTranscript: function(lang) {
            var container = document.getElementById('ytflix-player-container');
            var lines = document.getElementById('ytflix-transcript-lines');
            if (!container || !lines) return;

            var videoId = container.dataset.videoId;
            var cacheKey = 'transcript_' + videoId + '_' + lang;
            var cached = YTFLIX.cache.get(cacheKey);

            if (cached) {
                YTFLIX.renderTranscript(cached, lines);
                return;
            }

            lines.innerHTML = '<div class="ytflix-skeleton-loader"><div class="ytflix-skeleton-line"></div><div class="ytflix-skeleton-line"></div><div class="ytflix-skeleton-line"></div></div>';

            $.ajax({
                url: ytflixData.ajaxUrl,
                data: {
                    action: 'ytflix_get_transcript',
                    video_id: videoId,
                    lang: lang,
                },
                success: function(resp) {
                    if (!resp.success || !resp.data.transcript || !resp.data.transcript.length) {
                        lines.innerHTML = '<div class="ytflix-search-no-results">No transcript available.</div>';
                        return;
                    }

                    YTFLIX.cache.set(cacheKey, resp.data.transcript, 86400000);
                    YTFLIX.renderTranscript(resp.data.transcript, lines);
                }
            });
        },

        renderTranscript: function(transcript, lines) {
            YTFLIX.transcriptData = transcript;
            YTFLIX.transcriptLoaded = true;

            var html = '';
            transcript.forEach(function(entry, i) {
                html += '<div class="ytflix-transcript-line" data-index="' + i + '" data-start="' + entry.start + '">';
                html += '<span class="ytflix-transcript-text">' + YTFLIX.escHtml(entry.text) + '</span>';
                html += '<span class="ytflix-transcript-time">' + YTFLIX.formatTime(entry.start) + '</span>';
                html += '</div>';
            });
            lines.innerHTML = html;

            lines.querySelectorAll('.ytflix-transcript-line').forEach(function(line) {
                line.addEventListener('click', function() {
                    var start = parseFloat(this.dataset.start);
                    if (YTFLIX.player && YTFLIX.playerReady) {
                        YTFLIX.player.seekTo(start, true);
                        YTFLIX.player.playVideo();
                    }
                });
            });
        },

        highlightTranscriptLine: function(currentTime) {
            if (!YTFLIX.transcriptData.length) return;

            var lines = document.querySelectorAll('.ytflix-transcript-line');
            var activeIndex = -1;

            for (var i = 0; i < YTFLIX.transcriptData.length; i++) {
                var entry = YTFLIX.transcriptData[i];
                if (currentTime >= entry.start && currentTime < entry.end) {
                    activeIndex = i;
                    break;
                }
            }

            lines.forEach(function(line, i) {
                line.classList.toggle('active', i === activeIndex);
            });

            if (activeIndex >= 0 && lines[activeIndex]) {
                var panel = document.getElementById('ytflix-panel-transcript');
                if (panel && panel.classList.contains('active')) {
                    var lineTop = lines[activeIndex].offsetTop - panel.offsetTop;
                    var panelScroll = panel.scrollTop;
                    var panelHeight = panel.clientHeight;

                    if (lineTop < panelScroll || lineTop > panelScroll + panelHeight - 60) {
                        panel.scrollTo({ top: lineTop - 60, behavior: 'smooth' });
                    }
                }
            }
        },

        downloadTranscript: function() {
            if (!YTFLIX.transcriptData.length) {
                YTFLIX.loadTranscriptThenDownload();
                return;
            }
            YTFLIX.doDownload();
        },

        loadTranscriptThenDownload: function() {
            var container = document.getElementById('ytflix-player-container');
            if (!container) return;

            $.ajax({
                url: ytflixData.ajaxUrl,
                data: {
                    action: 'ytflix_get_transcript',
                    video_id: container.dataset.videoId,
                    lang: 'en',
                },
                success: function(resp) {
                    if (resp.success && resp.data.transcript && resp.data.transcript.length) {
                        YTFLIX.transcriptData = resp.data.transcript;
                        YTFLIX.doDownload();
                    }
                }
            });
        },

        doDownload: function() {
            var text = '';
            YTFLIX.transcriptData.forEach(function(entry) {
                text += '[' + YTFLIX.formatTime(entry.start) + '] ' + entry.text + '\n';
            });

            var blob = new Blob([text], { type: 'text/plain' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'transcript.txt';
            a.click();
            URL.revokeObjectURL(a.href);
        },

        // =====================================================================
        // FAVORITES
        // =====================================================================
        initFavorites: function() {
            var favBtn = document.getElementById('ytflix-fav-btn');
            if (!favBtn) return;

            favBtn.addEventListener('click', function() {
                $.post(ytflixData.ajaxUrl, {
                    action: 'ytflix_toggle_favorite',
                    nonce: ytflixData.nonce,
                    video_id: this.dataset.videoId,
                }, function(resp) {
                    if (!resp.success) return;
                    var isFav = resp.data.favorited;
                    favBtn.classList.toggle('active', isFav);
                    var span = favBtn.querySelector('span');
                    if (span) span.textContent = isFav ? 'In My List' : 'My List';
                    var path = favBtn.querySelector('svg path');
                    if (path) path.setAttribute('fill', isFav ? 'currentColor' : 'none');
                });
            });
        },

        // =====================================================================
        // UTILITIES
        // =====================================================================
        formatTime: function(seconds) {
            seconds = Math.floor(seconds);
            var h = Math.floor(seconds / 3600);
            var m = Math.floor((seconds % 3600) / 60);
            var s = seconds % 60;
            if (h > 0) {
                return h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            }
            return m + ':' + String(s).padStart(2, '0');
        },

        escHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // =====================================================================
        // LAZY LOAD PLAYLIST ROWS
        // =====================================================================
        initLazyRows: function() {
            var lazyRows = document.querySelectorAll('.ytflix-lazy-row');
            if (!lazyRows.length || !('IntersectionObserver' in window)) {
                lazyRows.forEach(function(row) { YTFLIX.loadLazyRow(row); });
                return;
            }

            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        observer.unobserve(entry.target);
                        YTFLIX.loadLazyRow(entry.target);
                    }
                });
            }, { rootMargin: '200px' });

            lazyRows.forEach(function(row) { observer.observe(row); });
        },

        loadLazyRow: function(rowEl) {
            var playlistId = rowEl.dataset.playlistId;
            if (!playlistId) return;

            $.ajax({
                url: ytflixData.ajaxUrl,
                data: { action: 'ytflix_get_playlist_row', playlist_id: playlistId },
                success: function(resp) {
                    if (resp.success && resp.data.html) {
                        var temp = document.createElement('div');
                        temp.innerHTML = resp.data.html;
                        rowEl.parentNode.replaceChild(temp.firstElementChild || temp, rowEl);
                        YTFLIX.initSliders();
                        YTFLIX.initCardInteractions();
                    } else {
                        rowEl.remove();
                    }
                },
                error: function() { rowEl.remove(); }
            });
        },
    };

    $(document).ready(function() {
        YTFLIX.init();
    });

    if (window.YT && window.YT.Player) {
        $(document).ready(function() {
            var container = document.getElementById('ytflix-player-container');
            if (container && !YTFLIX.player) {
                YTFLIX.createPlayer(
                    container.dataset.youtubeId,
                    parseFloat(container.dataset.startTime) || 0
                );
            }
        });
    }

})(jQuery);
