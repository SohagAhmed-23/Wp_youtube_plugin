# YTCP - Netflix-Style YouTube Platform

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0%2B-green.svg)](http://www.gnu.org/licenses/gpl-2.0.txt)
[![Version](https://img.shields.io/badge/Version-1.0.0-orange.svg)](#changelog)

A WordPress plugin that transforms any YouTube channel into a Netflix-style video browsing experience. Simply provide your YouTube API key and Channel ID, and YTCP automatically syncs all playlists and videos, presenting them in a sleek, dark-themed interface with horizontal sliders, a hero section, modal previews, a full-featured video player, transcripts, watch history, and more.

---

## Table of Contents

- [Screenshots](#screenshots)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [URL Structure](#url-structure)
- [Shortcodes](#shortcodes)
- [REST API](#rest-api)
- [AJAX Endpoints](#ajax-endpoints)
- [Hooks and Filters](#hooks-and-filters)
- [Database Schema](#database-schema)
- [File Structure](#file-structure)
- [Frequently Asked Questions](#frequently-asked-questions)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [License](#license)

---

## Screenshots

<!-- Add screenshots here -->
<!-- ![Home Page](screenshots/home.png) -->
<!-- ![Video Player](screenshots/player.png) -->
<!-- ![Admin Dashboard](screenshots/admin-dashboard.png) -->
<!-- ![Admin Settings](screenshots/admin-settings.png) -->

---

## Features

### Content Management
- **Automatic YouTube Sync** -- Syncs all playlists and videos from a YouTube channel with a single click or on a scheduled cron interval (hourly, twice daily, or daily).
- **Channel Auto-Detection** -- Automatically fetches and stores the channel name, logo, and banner image from YouTube.
- **Channel Switching** -- When the Channel ID is changed and a sync runs, all existing content is purged and replaced with data from the new channel.
- **Custom Post Types** -- Videos (`ytcp_video`) and Playlists (`ytcp_playlist`) are stored as WordPress custom post types with full REST API support.
- **Genre Taxonomy** -- Videos are automatically tagged with a `ytcp_genre` custom taxonomy based on YouTube tags.

### Frontend Experience
- **Netflix-Style Home Page** -- Hero section with channel branding, search bar, and horizontal playlist rows powered by Swiper.js.
- **Video Cards** -- Thumbnail cards with title and date overlays; clicking opens a modal popup with video preview and play button.
- **Dedicated Watch Page** -- Full video player page with custom controls (no native YouTube controls), tabbed sidebar with Episodes and Transcript tabs.
- **Transcripts** -- Fetched via the `mrmysql/youtube-transcript` Composer package with multi-language support and downloadable as a text file.
- **Continue Watching** -- Tracks user playback progress and shows a "Continue Watching" row for logged-in users.
- **My List / Favorites** -- Logged-in users can add videos to a personal favorites list.
- **Auto-Play Next Episode** -- Automatically advances to the next video in a playlist.
- **Search** -- Real-time search across videos and playlists via AJAX.
- **Keyboard Shortcuts** -- Space (play/pause), arrow keys (seek), F (fullscreen), M (mute).
- **Speed Control** -- Playback speed adjustment.
- **Picture-in-Picture** -- PiP mode support.
- **Responsive Design** -- Fully responsive layout for desktop, tablet, and mobile.
- **Customizable Accent Color** -- Default copper/brown (`#c17a2f`) with a color picker in settings.

### Administration
- **Dashboard** -- At-a-glance stats: API status, video count, playlist count, last sync time.
- **Settings Page** -- YouTube API key, Channel ID, cache duration, sync interval, hero customization, URL slugs, accent color, and feature toggles.
- **Sync Page** -- Manual sync trigger and cache clearing.
- **Submenu Integration** -- Videos and Playlists list tables accessible under the YTCP admin menu.

### Technical
- **REST API** -- Full read/write REST API at `/wp-json/ytcp/v1/` for videos, playlists, search, progress, favorites, and transcripts.
- **AJAX Handlers** -- Nonce-verified AJAX endpoints for search, progress saving, favorites toggling, and transcript fetching.
- **WP Cron** -- Scheduled auto-sync with configurable intervals.
- **Transient Caching** -- YouTube API responses cached as WordPress transients with configurable duration.
- **Prepared Statements** -- All database queries use `$wpdb->prepare()` for security.
- **Translation Ready** -- Full i18n support with the `ytchannel-pro` text domain.

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 5.8+ |
| PHP | 7.4+ |
| Composer | Required (dependencies bundled in `vendor/`) |
| YouTube Data API v3 Key | Required |

### Composer Dependencies

| Package | Version | Purpose |
|---|---|---|
| `mrmysql/youtube-transcript` | ^0.0.5 | Fetch video transcripts/captions |
| `guzzlehttp/guzzle` | ^7.10 | HTTP client for transcript fetching |
| `guzzlehttp/psr7` | ^2.9 | PSR-7 message implementation |

---

## Installation

### Manual Installation

1. Download or clone this repository into your WordPress plugins directory:
   ```bash
   cd /path/to/wordpress/wp-content/plugins/
   git clone https://github.com/your-repo/ytchannel-pro.git
   ```

2. Install Composer dependencies (skip if `vendor/` is already included):
   ```bash
   cd ytchannel-pro
   composer install --no-dev
   ```

3. Activate the plugin in **WordPress Admin > Plugins**.

4. Navigate to **YTCP > Settings** and enter your YouTube Data API v3 key and Channel ID.

5. Go to **YTCP > Sync** and click **Sync Now** to import your channel's playlists and videos.

6. Visit your site at `/watch/` to see the Netflix-style home page, or add the `[ytcp]` shortcode to any page.

### Obtaining a YouTube API Key

1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project (or select an existing one).
3. Navigate to **APIs & Services > Library** and enable the **YouTube Data API v3**.
4. Go to **APIs & Services > Credentials** and create an **API Key**.
5. (Recommended) Restrict the key to the YouTube Data API v3 and your domain.

### Finding Your Channel ID

1. Go to your YouTube channel page.
2. Click **About** (or the `@handle`), then view the page source or use a tool like [Comment Picker](https://commentpicker.com/youtube-channel-id.php).
3. The Channel ID starts with `UC` and is 24 characters long (e.g., `UCxxxxxxxxxxxxxxxxxxxxxxx`).

---

## Configuration

Navigate to **YTCP > Settings** in the WordPress admin. Settings are organized into four sections:

### YouTube API
| Setting | Description | Default |
|---|---|---|
| API Key | Your YouTube Data API v3 key | _(empty)_ |
| Channel ID | The YouTube channel ID to sync | _(empty)_ |
| Cache Duration | Transient cache lifetime in seconds (300 - 86400) | `3600` |
| Sync Interval | WP Cron schedule: Hourly, Twice Daily, or Daily | `daily` |

### Appearance
| Setting | Description | Default |
|---|---|---|
| Hero Title | Main heading on the home page (auto-filled from channel name) | `Welcome to YTChannel Pro` |
| Hero Description | Subheading text on the home page | `Your favorite videos, Netflix style.` |
| Hero Background | Banner image (auto-filled from channel banner) | _(empty)_ |
| Channel Logo | Logo image (auto-filled from channel avatar) | _(empty)_ |
| Accent Color | Primary accent color used throughout the UI | `#c17a2f` |

### URL Slugs
| Setting | Description | Default |
|---|---|---|
| Video Slug | URL base for single video pages (`yoursite.com/{slug}/video-title`) | `watch` |
| Playlist Slug | URL base for single playlist pages (`yoursite.com/{slug}/playlist-title`) | `series` |

> **Note:** After changing URL slugs, go to **Settings > Permalinks** and click **Save Changes** to flush rewrite rules.

### Feature Toggles
| Setting | Description | Default |
|---|---|---|
| Enable Transcripts | Show the Transcript tab on video pages | Enabled |
| Enable Watch History | Track user playback progress | Enabled |
| Enable My List / Favorites | Allow users to save videos to a personal list | Enabled |
| Enable Auto-play Next | Automatically play the next episode in a playlist | Enabled |
| Enable Picture-in-Picture | Allow PiP mode for the video player | Enabled |

---

## URL Structure

| URL Pattern | Description |
|---|---|
| `/watch/` | Home page (video archive) -- hero section, search, all playlist rows |
| `/watch/{video-slug}/` | Single video player page with episodes sidebar and transcript |
| `/series/{playlist-slug}/` | Single playlist page with all videos in the playlist |
| `/genre/{genre-slug}/` | Genre taxonomy archive |

---

## Shortcodes

### `[ytcp]`
Renders the full YTCP page layout including hero section, search bar, all playlist rows, and the video preview modal.

```
[ytcp]
```

### `[ytcp_hero]`
Renders only the hero section with the channel banner, logo, title, and description.

```
[ytcp_hero]
```

### `[ytcp_playlist]`
Renders a single playlist as a horizontal Swiper slider row.

```
[ytcp_playlist id="123"]
```

| Attribute | Required | Description |
|---|---|---|
| `id` | Yes | The WordPress post ID of the `ytcp_playlist` post |

### `[ytcp_player]`
Renders an embedded video player for a single video.

```
[ytcp_player video="456"]
```

| Attribute | Required | Description |
|---|---|---|
| `video` | Yes | The WordPress post ID of the `ytcp_video` post |

### `[ytcp_search]`
Renders the search bar component.

```
[ytcp_search]
```

---

## REST API

All endpoints are under the `ytcp/v1` namespace: `/wp-json/ytcp/v1/`

### Public Endpoints (no authentication required)

| Method | Endpoint | Description | Parameters |
|---|---|---|---|
| `GET` | `/videos` | List all videos (paginated) | `per_page` (default: 20), `page` (default: 1) |
| `GET` | `/videos/{id}` | Get a single video with playlist info and progress | `id` (post ID) |
| `GET` | `/playlists` | List all playlists with their videos | -- |
| `GET` | `/playlists/{id}` | Get a single playlist with its videos | `id` (post ID) |
| `GET` | `/search` | Search videos | `q` (required, search query) |
| `GET` | `/transcripts/{id}` | Get transcript for a video | `id` (post ID), `lang` (default: `en`) |

### Authenticated Endpoints (logged-in users only)

| Method | Endpoint | Description | Parameters |
|---|---|---|---|
| `POST` | `/progress` | Save playback progress | `video_id`, `current_time`, `duration` |
| `GET` | `/progress` | Get "Continue Watching" list for the current user | -- |
| `POST` | `/favorites` | Toggle favorite status for a video | `video_id` |
| `GET` | `/favorites` | Get the current user's favorites list | -- |

### Example Request

```bash
# Get all playlists with videos
curl https://yoursite.com/wp-json/ytcp/v1/playlists

# Search for videos
curl https://yoursite.com/wp-json/ytcp/v1/search?q=tutorial

# Get transcript in Spanish
curl https://yoursite.com/wp-json/ytcp/v1/transcripts/123?lang=es
```

---

## AJAX Endpoints

All AJAX calls use `admin-ajax.php` with nonce verification via the `ytcp_nonce` nonce.

| Action | Method | Auth Required | Description |
|---|---|---|---|
| `ytcp_search` | GET | No | Search videos and playlists (returns both types) |
| `ytcp_save_progress` | POST | Yes | Save video playback progress |
| `ytcp_toggle_favorite` | POST | Yes | Add/remove a video from favorites |
| `ytcp_get_transcript` | GET | No | Fetch transcript for a video |

---

## Hooks and Filters

### Actions

| Hook | Description | Parameters |
|---|---|---|
| `ytcp_sync_cron` | Fires on the scheduled cron sync event | -- |

### Filters

| Hook | Description | Parameters |
|---|---|---|
| `cron_schedules` | Adds the `ytcp_twice_daily` schedule (12-hour interval) | `$schedules` |
| `template_include` | Overrides templates for YTCP post types and archives | `$template` |
| `query_vars` | Registers `ytcp` and `ytcp_video_id` query vars | `$vars` |

### WP Cron Schedules

| Schedule Key | Interval | Description |
|---|---|---|
| `hourly` | 3600s | WordPress built-in |
| `ytcp_twice_daily` | 43200s | Custom schedule added by YTCP |
| `daily` | 86400s | WordPress built-in |

---

## Database Schema

YTCP creates three custom database tables on activation:

### `{prefix}ytcp_user_progress`

Tracks video playback progress per user.

| Column | Type | Description |
|---|---|---|
| `id` | BIGINT(20) PK | Auto-increment ID |
| `user_id` | BIGINT(20) | WordPress user ID |
| `video_post_id` | BIGINT(20) | Video post ID |
| `youtube_id` | VARCHAR(20) | YouTube video ID |
| `current_time` | FLOAT | Current playback position in seconds |
| `duration` | FLOAT | Total video duration in seconds |
| `completed` | TINYINT(1) | 1 if watched >= 90% |
| `last_watched` | DATETIME | Last interaction timestamp |

### `{prefix}ytcp_transcripts`

Caches fetched video transcripts.

| Column | Type | Description |
|---|---|---|
| `id` | BIGINT(20) PK | Auto-increment ID |
| `video_post_id` | BIGINT(20) | Video post ID |
| `youtube_id` | VARCHAR(20) | YouTube video ID |
| `language_code` | VARCHAR(10) | ISO language code (e.g., `en`) |
| `language_name` | VARCHAR(100) | Human-readable language name |
| `content` | LONGTEXT | JSON-encoded transcript entries |
| `fetched_at` | DATETIME | When the transcript was fetched |

### `{prefix}ytcp_favorites`

Stores user favorites / "My List" entries.

| Column | Type | Description |
|---|---|---|
| `id` | BIGINT(20) PK | Auto-increment ID |
| `user_id` | BIGINT(20) | WordPress user ID |
| `video_post_id` | BIGINT(20) | Video post ID |
| `added_at` | DATETIME | When the video was favorited |

---

## File Structure

```
ytchannel-pro/
├── ytchannel-pro.php                  # Main plugin bootstrap
├── index.php                          # Security blank file
├── composer.json                      # Composer dependencies
├── composer.lock
├── README.md
├── readme.txt
├── assets/
│   ├── css/
│   │   ├── admin.css                  # Admin dashboard styles
│   │   └── frontend.css               # Frontend Netflix-style styles
│   ├── js/
│   │   ├── admin.js                   # Admin scripts (media uploader, sync)
│   │   └── frontend.js                # Frontend scripts (Swiper, player, keyboard shortcuts)
│   └── images/
├── includes/
│   ├── class-ytcp.php               # Main plugin orchestrator
│   ├── class-ytcp-activator.php      # Activation: DB tables, default options, CPTs
│   ├── class-ytcp-deactivator.php    # Deactivation: clear cron, flush rewrites
│   ├── class-ytcp-loader.php         # Action/filter registration loader
│   ├── class-ytcp-cpt.php           # Custom post types and taxonomy registration
│   ├── admin/
│   │   └── class-ytcp-admin.php     # Admin menu, settings, dashboard, sync page
│   ├── api/
│   │   ├── class-ytcp-rest-api.php  # REST API route registration and handlers
│   │   └── class-ytcp-ajax.php      # AJAX action handlers
│   ├── frontend/
│   │   ├── class-ytcp-frontend.php  # Frontend asset enqueuing, template loading, rewrites
│   │   └── class-ytcp-shortcodes.php # Shortcode registration and rendering
│   └── services/
│       ├── class-ytcp-youtube-api.php    # YouTube Data API v3 client with caching
│       ├── class-ytcp-sync.php           # Channel/playlist/video sync engine
│       ├── class-ytcp-transcript.php     # Transcript fetching, caching, and export
│       ├── class-ytcp-user-progress.php  # User watch progress tracking
│       └── class-ytcp-recommendations.php # Trending, recommended, and related videos
├── templates/
│   ├── pages/
│   │   ├── home.php                   # Home page template (hero + rows)
│   │   ├── single-video.php           # Single video player page template
│   │   └── single-playlist.php        # Single playlist page template
│   └── partials/
│       ├── hero.php                   # Hero section partial
│       ├── search.php                 # Search bar partial
│       ├── playlist-rows.php          # All playlist rows partial
│       ├── playlist-row.php           # Single playlist Swiper row partial
│       ├── continue-watching.php      # Continue watching row partial
│       ├── modal.php                  # Video preview modal partial
│       └── player-embed.php           # Embeddable video player partial
├── languages/                         # Translation files (.pot, .po, .mo)
└── vendor/                            # Composer autoload and dependencies
```

---

## Frequently Asked Questions

### How do I get a YouTube API key?

Go to the [Google Cloud Console](https://console.cloud.google.com/), create or select a project, enable the **YouTube Data API v3**, then create an API key under **Credentials**. See the [Installation](#installation) section for detailed steps.

### How do I find my YouTube Channel ID?

Your Channel ID is a 24-character string starting with `UC`. You can find it by going to your channel page and looking at the URL, or by using an online Channel ID finder tool.

### How often does content sync?

By default, YTCP syncs daily via WP Cron. You can change this to hourly or twice daily under **YTCP > Settings**. You can also trigger a manual sync at any time from the **YTCP > Sync** page.

### What happens when I change the Channel ID?

When you update the Channel ID and run a sync, YTCP automatically purges all existing videos, playlists, and channel branding (logo, banner, channel name), then imports everything from the new channel.

### Do visitors need to log in?

No. The home page, video playback, search, and transcripts all work for anonymous visitors. Logging in is only required for "Continue Watching" progress tracking and the "My List" / Favorites feature.

### Can I use this on any page with shortcodes?

Yes. You can use the `[ytcp]` shortcode on any page or post to render the full Netflix-style layout. Individual shortcodes (`[ytcp_hero]`, `[ytcp_playlist]`, `[ytcp_player]`, `[ytcp_search]`) can be used independently.

### Are transcripts available for all videos?

Transcripts depend on YouTube captions being available for a video. If a video has auto-generated or manually uploaded captions, YTCP can fetch and display them. Videos without any captions will show no transcript.

### Can I customize the look and feel?

Yes. You can change the accent color from the settings page. The plugin uses CSS custom properties (`--ytcp-accent`) that propagate throughout the UI. For deeper customization, you can override styles in your theme's stylesheet.

### Does the plugin support multiple languages for transcripts?

Yes. Transcripts support multiple languages. When a transcript is fetched, YTCP stores it with the language code and attempts to find transcripts in the requested language, falling back to English or Bengali.

### Does YTCP work with caching plugins?

Yes, but you should exclude AJAX endpoints and REST API routes from page caching to ensure real-time features (search, progress saving, favorites) work correctly.

---

## Changelog

### 1.0.0

- Initial release.
- YouTube channel sync with automatic playlist and video import.
- Auto-fetch channel name, logo, and banner from YouTube.
- Netflix-style home page with Swiper.js horizontal sliders.
- Video preview modal with play button.
- Dedicated watch page with custom YouTube player controls.
- Tabbed sidebar: Episodes and Transcript tabs.
- Transcript fetching via `mrmysql/youtube-transcript` with caching and multi-language support.
- Transcript download as text file.
- User progress tracking and "Continue Watching" row.
- My List / Favorites system.
- Auto-play next episode.
- Keyboard shortcuts (Space, Arrow keys, F, M).
- Speed control and Picture-in-Picture.
- Real-time search across videos and playlists.
- Full REST API (`/wp-json/ytcp/v1/`).
- AJAX handlers with nonce verification.
- WP Cron scheduled sync (hourly, twice daily, daily).
- Customizable accent color, URL slugs, and feature toggles.
- Recommendations engine: trending, related, and personalized.
- Admin dashboard, settings page, and sync management.
- Channel switching with automatic content purge.
- Fully responsive design.
- Translation-ready with `ytchannel-pro` text domain.

---

## Contributing

Contributions are welcome. Please open an issue or submit a pull request.

1. Fork the repository.
2. Create your feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m 'Add my feature'`
4. Push to the branch: `git push origin feature/my-feature`
5. Open a pull request.

---

## License

This plugin is licensed under the [GPL-2.0+](http://www.gnu.org/licenses/gpl-2.0.txt).

Copyright (c) YTCP Team.
# Wp_youtube_plugin
