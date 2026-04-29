/**
 * YTCP Frontend JavaScript
 * Handles: Swiper sliders, YouTube player, transcript sync in sidebar tabs,
 * modal popup on card click, progress tracking, search, favorites,
 * auto-play next, skip intro, speed control, PiP
 */
(function($) {
    'use strict';

    const YTCP = {
        player: null,
        playerReady: false,
        progressInterval: null,
        countdownInterval: null,
        previewPlayers: {},
        previewTimeout: null,
        searchTimeout: null,
        transcriptData: [],
        transcriptLoaded: false,
        transcriptLoading: false,
        modalPlayer: null,

        cache: {
            _prefix: 'ytcp_',
            _version: (typeof ytcpData !== 'undefined' ? ytcpData.cacheVersion : 0),

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
            document.querySelectorAll('.ytcp-slider').forEach(function(el) {
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
            var input = document.getElementById('ytcp-search-input');
            var results = document.getElementById('ytcp-search-results');
            var inner = document.getElementById('ytcp-search-results-inner');
            var clearBtn = document.getElementById('ytcp-search-clear');

            if (!input) return;

            input.addEventListener('input', function() {
                var q = this.value.trim();
                if (clearBtn) clearBtn.style.display = q ? 'block' : 'none';

                if (q.length < 2) {
                    if (results) results.style.display = 'none';
                    return;
                }

                clearTimeout(YTCP.searchTimeout);
                YTCP.searchTimeout = setTimeout(function() {
                    YTCP.doSearch(q, inner, results);
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
                if (results && !e.target.closest('.ytcp-search-container')) {
                    results.style.display = 'none';
                }
            });
        },

        doSearch: function(q, inner, container) {
            var cacheKey = 'search_' + q.toLowerCase();
            var cached = YTCP.cache.get(cacheKey);
            if (cached) {
                YTCP.renderSearchResults(cached, inner, container);
                return;
            }

            $.ajax({
                url: ytcpData.ajaxUrl,
                data: { action: 'ytcp_search', q: q, nonce: ytcpData.nonce },
                success: function(resp) {
                    if (!resp.success || !resp.data.results.length) {
                        inner.innerHTML = '<div class="ytcp-search-no-results">No results found</div>';
                        container.style.display = 'block';
                        return;
                    }

                    YTCP.cache.set(cacheKey, resp.data.results, 120000);
                    YTCP.renderSearchResults(resp.data.results, inner, container);
                }
            });
        },

        renderSearchResults: function(results, inner, container) {
            var html = '';
            results.forEach(function(item) {
                html += '<a href="' + item.permalink + '" class="ytcp-search-result-item">';
                html += '<div class="ytcp-search-result-info">';
                html += '<h4>' + YTCP.escHtml(item.title) + '</h4>';
                html += '</div></a>';
            });
            inner.innerHTML = html;
            container.style.display = 'block';
        },

        // =====================================================================
        // CARD INTERACTIONS - Open modal on click (home page), direct nav on watch page
        // =====================================================================
        initCardInteractions: function() {
            document.querySelectorAll('.ytcp-video-card').forEach(function(card) {
                card.addEventListener('mouseenter', function() {
                    YTCP.previewTimeout = setTimeout(function() {
                        YTCP.startPreview(card);
                    }, 800);
                });

                card.addEventListener('mouseleave', function() {
                    clearTimeout(YTCP.previewTimeout);
                    YTCP.stopPreview(card);
                });

                card.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var isWatchPage = document.querySelector('.ytcp-watch-page');
                    if (isWatchPage) {
                        window.location.href = card.dataset.permalink;
                        return;
                    }

                    YTCP.openModal(card);
                });
            });
        },

        _ytApiLoading: false,

        ensureYTApi: function(callback) {
            if (window.YT && window.YT.Player) {
                callback();
                return;
            }
            if (!YTCP._ytApiLoading) {
                YTCP._ytApiLoading = true;
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
            var previewEl = card.querySelector('.ytcp-card-preview');
            if (!ytId || !previewEl) return;

            if (!window.YT || !window.YT.Player) {
                YTCP.ensureYTApi(function() { YTCP.startPreview(card); });
                return;
            }

            var containerId = 'preview-' + ytId + '-' + Math.random().toString(36).substr(2, 5);
            previewEl.innerHTML = '<div id="' + containerId + '"></div>';

            try {
                YTCP.previewPlayers[ytId] = new YT.Player(containerId, {
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
            if (YTCP.previewPlayers[ytId]) {
                try { YTCP.previewPlayers[ytId].destroy(); } catch(e) {}
                delete YTCP.previewPlayers[ytId];
            }
            var previewEl = card.querySelector('.ytcp-card-preview');
            if (previewEl) previewEl.innerHTML = '';
        },

        // =====================================================================
        // MODAL POPUP
        // =====================================================================
        initModal: function() {
            var backdrop = document.getElementById('ytcp-modal-backdrop');
            if (!backdrop) return;

            var closeBtn = document.getElementById('ytcp-modal-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    YTCP.closeModal();
                });
            }

            backdrop.addEventListener('click', function(e) {
                if (e.target === backdrop) {
                    YTCP.closeModal();
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && backdrop.classList.contains('active')) {
                    YTCP.closeModal();
                }
            });
        },

        openModal: function(card) {
            var backdrop = document.getElementById('ytcp-modal-backdrop');
            if (!backdrop) return;

            var ytId = card.dataset.youtubeId;
            var permalink = card.dataset.permalink;
            var title = card.dataset.title || '';
            var description = card.dataset.description || '';
            var playlistTitle = card.dataset.playlistTitle || '';
            var playlistDesc = card.dataset.playlistDesc || '';
            var thumbnail = card.dataset.thumbnail || '';

            document.getElementById('ytcp-modal-playlist-title').textContent = playlistTitle;
            document.getElementById('ytcp-modal-playlist-desc').textContent = playlistDesc;
            document.getElementById('ytcp-modal-video-title').textContent = title;
            document.getElementById('ytcp-modal-video-desc').textContent = description;
            document.getElementById('ytcp-modal-play-btn').href = permalink;

            var previewContainer = document.getElementById('ytcp-modal-preview');
            if (ytId && window.YT && window.YT.Player) {
                previewContainer.innerHTML = '<div id="ytcp-modal-yt-player"></div>';
                try {
                    YTCP.modalPlayer = new YT.Player('ytcp-modal-yt-player', {
                        width: '100%',
                        height: '100%',
                        videoId: ytId,
                        playerVars: {
                            autoplay: 1, mute: 0, controls: 1, modestbranding: 1,
                            rel: 0, playsinline: 1,
                        }
                    });
                } catch(e) {
                    previewContainer.innerHTML = '<img src="' + YTCP.escHtml(thumbnail) + '" alt="" referrerpolicy="no-referrer">';
                }
            } else {
                previewContainer.innerHTML = '<img src="' + YTCP.escHtml(thumbnail) + '" alt="" referrerpolicy="no-referrer">';
            }

            backdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
        },

        closeModal: function() {
            var backdrop = document.getElementById('ytcp-modal-backdrop');
            if (!backdrop) return;

            backdrop.classList.remove('active');
            document.body.style.overflow = '';

            if (YTCP.modalPlayer) {
                try { YTCP.modalPlayer.destroy(); } catch(e) {}
                YTCP.modalPlayer = null;
            }

            var previewContainer = document.getElementById('ytcp-modal-preview');
            if (previewContainer) previewContainer.innerHTML = '';
        },

        // =====================================================================
        // SIDEBAR TABS (Episodes / Transcript)
        // =====================================================================
        initSidebarTabs: function() {
            var tabs = document.querySelectorAll('.ytcp-sidebar-tab');
            if (!tabs.length) return;

            tabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    var target = this.dataset.tab;

                    tabs.forEach(function(t) { t.classList.remove('active'); });
                    this.classList.add('active');

                    document.querySelectorAll('.ytcp-sidebar-panel').forEach(function(panel) {
                        panel.classList.remove('active');
                    });

                    var targetPanel = document.getElementById('ytcp-panel-' + target);
                    if (targetPanel) {
                        targetPanel.classList.add('active');
                    }

                    if (target === 'transcript' && !YTCP.transcriptLoaded && !YTCP.transcriptLoading) {
                        YTCP.loadTranscript('en');
                    }
                });
            });
        },

        // =====================================================================
        // MAIN VIDEO PLAYER (Watch page)
        // =====================================================================
        initPlayer: function() {
            var container = document.getElementById('ytcp-player-container');
            if (!container) return;

            var ytId = container.dataset.youtubeId;
            var startTime = parseFloat(container.dataset.startTime) || 0;
            if (!ytId) return;

            if (window.YT && window.YT.Player) {
                YTCP.createPlayer(ytId, startTime);
            } else {
                window.onYouTubeIframeAPIReady = function() {
                    YTCP.createPlayer(ytId, startTime);
                };
            }

            this.initPlayerControls();
        },

        createPlayer: function(ytId, startTime) {
            YTCP.player = new YT.Player('ytcp-player', {
                width: '100%',
                height: '100%',
                videoId: ytId,
                playerVars: {
                    autoplay: 1, controls: 0, modestbranding: 1, rel: 0,
                    showinfo: 0, start: Math.floor(startTime), playsinline: 1,
                    enablejsapi: 1, origin: window.location.origin,
                },
                events: {
                    onReady: YTCP.onPlayerReady,
                    onStateChange: YTCP.onPlayerStateChange,
                }
            });
        },

        onPlayerReady: function() {
            YTCP.playerReady = true;
            YTCP.startProgressTracking();

        },

        onPlayerStateChange: function(event) {
            if (event.data === YT.PlayerState.PLAYING) {
                YTCP.updatePlayPauseIcon(true);
                YTCP.startProgressTracking();
            } else if (event.data === YT.PlayerState.PAUSED) {
                YTCP.updatePlayPauseIcon(false);
                YTCP.saveCurrentProgress();
            } else if (event.data === YT.PlayerState.ENDED) {
                YTCP.updatePlayPauseIcon(false);
                YTCP.saveCurrentProgress();
                YTCP.showNextEpisode();
            }
        },

        initPlayerControls: function() {
            var playPause = document.getElementById('ytcp-play-pause');
            if (playPause) {
                playPause.addEventListener('click', function() {
                    if (!YTCP.player || !YTCP.playerReady) return;
                    var state = YTCP.player.getPlayerState();
                    state === YT.PlayerState.PLAYING ? YTCP.player.pauseVideo() : YTCP.player.playVideo();
                });
            }

            var progressBar = document.getElementById('ytcp-progress-bar');
            if (progressBar) {
                progressBar.addEventListener('click', function(e) {
                    if (!YTCP.player || !YTCP.playerReady) return;
                    var rect = this.getBoundingClientRect();
                    var pct = (e.clientX - rect.left) / rect.width;
                    YTCP.player.seekTo(pct * YTCP.player.getDuration(), true);
                });
            }

            var speedBtn = document.getElementById('ytcp-speed-btn');
            var speedMenu = document.getElementById('ytcp-speed-menu');
            if (speedBtn && speedMenu) {
                speedBtn.addEventListener('click', function() {
                    speedMenu.style.display = speedMenu.style.display === 'none' ? 'block' : 'none';
                });
                speedMenu.querySelectorAll('button').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var speed = parseFloat(this.dataset.speed);
                        if (YTCP.player && YTCP.playerReady) YTCP.player.setPlaybackRate(speed);
                        speedMenu.querySelectorAll('button').forEach(function(b) { b.classList.remove('active'); });
                        this.classList.add('active');
                        speedBtn.textContent = speed + 'x';
                        speedMenu.style.display = 'none';
                    });
                });
            }

            var fsBtn = document.getElementById('ytcp-fullscreen-btn');
            if (fsBtn) {
                fsBtn.addEventListener('click', function() {
                    var c = document.getElementById('ytcp-player-container');
                    if (!c) return;
                    document.fullscreenElement ? document.exitFullscreen() : c.requestFullscreen().catch(function(){});
                });
            }

            var cancelNext = document.getElementById('ytcp-cancel-next');
            if (cancelNext) {
                cancelNext.addEventListener('click', function() {
                    clearInterval(YTCP.countdownInterval);
                    document.getElementById('ytcp-next-overlay').style.display = 'none';
                });
            }

            document.addEventListener('keydown', function(e) {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
                if (!YTCP.player || !YTCP.playerReady) return;
                if (document.getElementById('ytcp-modal-backdrop') &&
                    document.getElementById('ytcp-modal-backdrop').classList.contains('active')) return;

                switch(e.key) {
                    case ' ': case 'k':
                        e.preventDefault();
                        var st = YTCP.player.getPlayerState();
                        st === YT.PlayerState.PLAYING ? YTCP.player.pauseVideo() : YTCP.player.playVideo();
                        break;
                    case 'ArrowLeft':
                        e.preventDefault();
                        YTCP.player.seekTo(YTCP.player.getCurrentTime() - 10, true);
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        YTCP.player.seekTo(YTCP.player.getCurrentTime() + 10, true);
                        break;
                    case 'f':
                        e.preventDefault();
                        var cont = document.getElementById('ytcp-player-container');
                        document.fullscreenElement ? document.exitFullscreen() : cont.requestFullscreen().catch(function(){});
                        break;
                    case 'm':
                        e.preventDefault();
                        YTCP.player.isMuted() ? YTCP.player.unMute() : YTCP.player.mute();
                        break;
                }
            });
        },

        updatePlayPauseIcon: function(isPlaying) {
            var btn = document.getElementById('ytcp-play-pause');
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
            if (YTCP.progressInterval) clearInterval(YTCP.progressInterval);

            YTCP.progressInterval = setInterval(function() {
                if (!YTCP.player || !YTCP.playerReady) return;
                if (YTCP.player.getPlayerState() !== YT.PlayerState.PLAYING) return;

                var current = YTCP.player.getCurrentTime();
                var duration = YTCP.player.getDuration();
                var pct = (current / duration) * 100;

                var fill = document.getElementById('ytcp-progress-fill');
                if (fill) fill.style.width = pct + '%';

                var timeDisplay = document.getElementById('ytcp-time-display');
                if (timeDisplay) {
                    timeDisplay.textContent = YTCP.formatTime(current) + ' / ' + YTCP.formatTime(duration);
                }

                YTCP.highlightTranscriptLine(current);
            }, 250);

            if (ytcpData.isLoggedIn && ytcpData.enableHistory === '1') {
                setInterval(function() { YTCP.saveCurrentProgress(); }, 15000);
            }
        },

        saveCurrentProgress: function() {
            if (!ytcpData.isLoggedIn || ytcpData.enableHistory !== '1') return;
            if (!YTCP.player || !YTCP.playerReady) return;

            var container = document.getElementById('ytcp-player-container');
            if (!container) return;

            $.post(ytcpData.ajaxUrl, {
                action: 'ytcp_save_progress',
                nonce: ytcpData.nonce,
                video_id: container.dataset.videoId,
                current_time: YTCP.player.getCurrentTime(),
                duration: YTCP.player.getDuration(),
            });
        },

        showNextEpisode: function() {
            if (ytcpData.enableAutoplay !== '1') return;
            var overlay = document.getElementById('ytcp-next-overlay');
            var countdownEl = document.getElementById('ytcp-countdown');
            var playNextBtn = document.getElementById('ytcp-play-next');
            if (!overlay || !playNextBtn || !playNextBtn.href) return;

            overlay.style.display = 'flex';
            var count = 10;
            if (countdownEl) countdownEl.textContent = count;

            YTCP.countdownInterval = setInterval(function() {
                count--;
                if (countdownEl) countdownEl.textContent = count;
                if (count <= 0) {
                    clearInterval(YTCP.countdownInterval);
                    window.location.href = playNextBtn.href;
                }
            }, 1000);
        },

        // =====================================================================
        // TRANSCRIPT (in sidebar panel)
        // =====================================================================
        initTranscript: function() {
            var downloadBtn = document.getElementById('ytcp-transcript-download');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    YTCP.downloadTranscript();
                });
            }

            var transcriptPanel = document.getElementById('ytcp-panel-transcript');
            if (transcriptPanel) {
                YTCP.loadTranscript('en');
            }
        },

        loadTranscript: function(lang) {
            var container = document.getElementById('ytcp-player-container');
            var lines = document.getElementById('ytcp-transcript-lines');
            if (!container || !lines) return;
            if (YTCP.transcriptLoaded || YTCP.transcriptLoading) return;

            YTCP.transcriptLoading = true;

            var videoId = container.dataset.videoId;
            var cacheKey = 'transcript_' + videoId + '_' + lang;
            var cached = YTCP.cache.get(cacheKey);

            if (cached) {
                YTCP.transcriptLoading = false;
                YTCP.renderTranscript(cached, lines);
                return;
            }

            lines.innerHTML = '<div class="ytcp-skeleton-loader"><div class="ytcp-skeleton-line"></div><div class="ytcp-skeleton-line"></div><div class="ytcp-skeleton-line"></div></div>';

            $.ajax({
                url: ytcpData.ajaxUrl,
                data: {
                    action: 'ytcp_get_transcript',
                    video_id: videoId,
                    lang: lang,
                },
                success: function(resp) {
                    YTCP.transcriptLoading = false;
                    if (!resp.success || !resp.data.transcript || !resp.data.transcript.length) {
                        lines.innerHTML = '<div class="ytcp-empty-state">' +
                            '<svg class="ytcp-empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' +
                            '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>' +
                            '<polyline points="14 2 14 8 20 8"/>' +
                            '<line x1="16" y1="13" x2="8" y2="13"/>' +
                            '<line x1="16" y1="17" x2="8" y2="17"/>' +
                            '<polyline points="10 9 9 9 8 9"/>' +
                            '</svg>' +
                            '<p class="ytcp-empty-state-title">No transcript available</p>' +
                            '<p class="ytcp-empty-state-desc">A transcript has not been added for this episode yet.</p>' +
                            '</div>';
                        return;
                    }

                    YTCP.cache.set(cacheKey, resp.data.transcript, 86400000);
                    YTCP.renderTranscript(resp.data.transcript, lines);
                },
                error: function() {
                    YTCP.transcriptLoading = false;
                }
            });
        },

        renderTranscript: function(transcript, lines) {
            YTCP.transcriptData = transcript;
            YTCP.transcriptLoaded = true;

            var html = '';
            transcript.forEach(function(entry, i) {
                html += '<div class="ytcp-transcript-line" data-index="' + i + '" data-start="' + entry.start + '">';
                html += '<span class="ytcp-transcript-text">' + YTCP.escHtml(entry.text) + '</span>';
                html += '<span class="ytcp-transcript-time">' + YTCP.formatTime(entry.start) + '</span>';
                html += '</div>';
            });
            lines.innerHTML = html;

            lines.querySelectorAll('.ytcp-transcript-line').forEach(function(line) {
                line.addEventListener('click', function() {
                    var start = parseFloat(this.dataset.start);
                    if (YTCP.player && YTCP.playerReady) {
                        YTCP.player.seekTo(start, true);
                        YTCP.player.playVideo();
                    }
                });
            });
        },

        highlightTranscriptLine: function(currentTime) {
            if (!YTCP.transcriptData.length) return;

            var lines = document.querySelectorAll('.ytcp-transcript-line');
            var activeIndex = -1;

            for (var i = 0; i < YTCP.transcriptData.length; i++) {
                var entry = YTCP.transcriptData[i];
                if (currentTime >= entry.start && currentTime < entry.end) {
                    activeIndex = i;
                    break;
                }
            }

            lines.forEach(function(line, i) {
                line.classList.toggle('active', i === activeIndex);
            });

            if (activeIndex >= 0 && lines[activeIndex]) {
                var panel = document.getElementById('ytcp-panel-transcript');
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
            if (!YTCP.transcriptData.length) {
                YTCP.loadTranscriptThenDownload();
                return;
            }
            YTCP.doDownload();
        },

        loadTranscriptThenDownload: function() {
            var container = document.getElementById('ytcp-player-container');
            if (!container) return;

            $.ajax({
                url: ytcpData.ajaxUrl,
                data: {
                    action: 'ytcp_get_transcript',
                    video_id: container.dataset.videoId,
                    lang: 'en',
                },
                success: function(resp) {
                    if (resp.success && resp.data.transcript && resp.data.transcript.length) {
                        YTCP.transcriptData = resp.data.transcript;
                        YTCP.doDownload();
                    }
                }
            });
        },

        doDownload: function() {
            var text = '';
            YTCP.transcriptData.forEach(function(entry) {
                text += '[' + YTCP.formatTime(entry.start) + '] ' + entry.text + '\n';
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
            var favBtn = document.getElementById('ytcp-fav-btn');
            if (!favBtn) return;

            favBtn.addEventListener('click', function() {
                $.post(ytcpData.ajaxUrl, {
                    action: 'ytcp_toggle_favorite',
                    nonce: ytcpData.nonce,
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
            var lazyRows = document.querySelectorAll('.ytcp-lazy-row');
            if (!lazyRows.length || !('IntersectionObserver' in window)) {
                lazyRows.forEach(function(row) { YTCP.loadLazyRow(row); });
                return;
            }

            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        observer.unobserve(entry.target);
                        YTCP.loadLazyRow(entry.target);
                    }
                });
            }, { rootMargin: '200px' });

            lazyRows.forEach(function(row) { observer.observe(row); });
        },

        loadLazyRow: function(rowEl) {
            var playlistId = rowEl.dataset.playlistId;
            if (!playlistId) return;

            $.ajax({
                url: ytcpData.ajaxUrl,
                data: { action: 'ytcp_get_playlist_row', playlist_id: playlistId },
                success: function(resp) {
                    if (resp.success && resp.data.html) {
                        var temp = document.createElement('div');
                        temp.innerHTML = resp.data.html;
                        rowEl.parentNode.replaceChild(temp.firstElementChild || temp, rowEl);
                        YTCP.initSliders();
                        YTCP.initCardInteractions();
                    } else {
                        rowEl.remove();
                    }
                },
                error: function() { rowEl.remove(); }
            });
        },
    };

    $(document).ready(function() {
        YTCP.init();
    });

    if (window.YT && window.YT.Player) {
        $(document).ready(function() {
            var container = document.getElementById('ytcp-player-container');
            if (container && !YTCP.player) {
                YTCP.createPlayer(
                    container.dataset.youtubeId,
                    parseFloat(container.dataset.startTime) || 0
                );
            }
        });
    }

})(jQuery);
