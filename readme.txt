=== YTFlix - Netflix-Style YouTube Platform ===
Contributors: ytflixteam
Tags: youtube, netflix, video, playlist, streaming, video player, transcripts, youtube api, video gallery
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Transform any YouTube channel into a Netflix-style video browsing experience with automatic playlist sync, custom player, transcripts, watch history, and more.

== Description ==

YTFlix turns your WordPress site into a Netflix-inspired video platform powered by the YouTube Data API. Provide your API key and Channel ID, and the plugin automatically imports all playlists and videos, presenting them in a sleek, dark-themed interface with horizontal sliders, a hero section, modal previews, and a full-featured video player.

= Key Features =

* **Automatic YouTube Sync** -- Import all playlists and videos from any YouTube channel. Schedule syncs hourly, twice daily, or daily via WP Cron.
* **Channel Auto-Detection** -- Channel name, logo, and banner are automatically fetched and applied.
* **Netflix-Style Home Page** -- Hero section, search bar, and horizontal Swiper.js playlist rows with video thumbnail cards.
* **Video Preview Modal** -- Click any card to open a popup with video preview and play button.
* **Custom Video Player** -- Dedicated watch page using the YouTube IFrame API with custom controls (no native YouTube chrome).
* **Tabbed Sidebar** -- Episodes tab lists all videos in the current playlist. Transcript tab displays synced captions with timestamps.
* **Transcripts** -- Multi-language transcripts fetched via the `mrmysql/youtube-transcript` PHP package. Download as a text file.
* **Continue Watching** -- Tracks playback progress for logged-in users and shows a "Continue Watching" row.
* **My List / Favorites** -- Logged-in users can save videos to a personal list.
* **Auto-Play Next** -- Automatically advances to the next video in a playlist.
* **Keyboard Shortcuts** -- Space (play/pause), arrow keys (seek), F (fullscreen), M (mute).
* **Speed Control & PiP** -- Playback speed adjustment and Picture-in-Picture support.
* **Search** -- Real-time AJAX search across videos and playlists.
* **REST API** -- Full API at `/wp-json/ytflix/v1/` for videos, playlists, search, progress, favorites, and transcripts.
* **Customizable** -- Accent color picker, configurable URL slugs, and feature toggles for transcripts, history, favorites, auto-play, and PiP.
* **Responsive** -- Fully responsive design for desktop, tablet, and mobile.
* **Translation Ready** -- i18n support with the `ytflix` text domain.
* **Channel Switching** -- Changing the Channel ID and syncing purges old data and imports the new channel.

= Custom Post Types =

* `ytflix_video` -- Each synced YouTube video.
* `ytflix_playlist` -- Each synced YouTube playlist.
* `ytflix_genre` -- Taxonomy for genre/tag classification.

= Custom Database Tables =

* `wp_ytflix_user_progress` -- Tracks user playback position per video.
* `wp_ytflix_transcripts` -- Caches fetched transcripts with language support.
* `wp_ytflix_favorites` -- Stores user favorites / "My List" entries.

= Shortcodes =

* `[ytflix]` -- Full page layout (hero + search + playlist rows + modal).
* `[ytflix_hero]` -- Hero section only.
* `[ytflix_playlist id="POST_ID"]` -- Single playlist horizontal slider.
* `[ytflix_player video="POST_ID"]` -- Single embedded video player.
* `[ytflix_search]` -- Search bar component.

= URL Structure =

* `/watch/` -- Home page with hero section and all playlist rows.
* `/watch/video-slug/` -- Single video player page.
* `/series/playlist-slug/` -- Single playlist page.

(URL slugs are configurable in settings.)

= Requirements =

* WordPress 5.8 or higher.
* PHP 7.4 or higher.
* A YouTube Data API v3 key (free from the Google Cloud Console).
* Composer dependencies are bundled in the `vendor/` directory.

== Installation ==

1. Upload the `ytflix` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **YTFlix > Settings** in the admin menu.
4. Enter your **YouTube Data API v3 key** and **Channel ID**.
5. Go to **YTFlix > Sync** and click **Sync Now** to import playlists and videos.
6. Visit `/watch/` on your site or add the `[ytflix]` shortcode to any page.

= Getting a YouTube API Key =

1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. Create or select a project.
3. Enable the **YouTube Data API v3** under APIs & Services > Library.
4. Create an API key under APIs & Services > Credentials.
5. (Recommended) Restrict the key to the YouTube Data API v3 and your domain.

= Finding Your Channel ID =

Your Channel ID is a 24-character string starting with `UC`. Find it on your YouTube channel's About page or via an online Channel ID lookup tool.

= Composer Dependencies =

The plugin ships with Composer dependencies pre-installed in the `vendor/` directory. If you need to reinstall them:

`cd wp-content/plugins/ytflix && composer install --no-dev`

== Frequently Asked Questions ==

= How often does the content sync with YouTube? =

By default, YTFlix syncs daily via WordPress Cron. You can change the interval to hourly or twice daily under YTFlix > Settings. A manual sync can be triggered at any time from YTFlix > Sync.

= What happens when I change the Channel ID? =

When you update the Channel ID and run a sync, YTFlix automatically deletes all existing videos, playlists, and channel branding (logo, banner, name), then imports everything from the new channel.

= Do visitors need to be logged in? =

No. The home page, video playback, search, and transcripts work for all visitors. User login is only required for Continue Watching progress tracking and the My List / Favorites feature.

= Can I use YTFlix on any page? =

Yes. Use the `[ytflix]` shortcode on any page or post. Individual shortcodes like `[ytflix_hero]`, `[ytflix_playlist]`, `[ytflix_player]`, and `[ytflix_search]` can also be placed independently.

= Are transcripts available for every video? =

Transcripts depend on YouTube captions. If a video has auto-generated or manually uploaded captions, YTFlix can fetch and display them. Videos without captions will have no transcript available.

= Can I customize the colors? =

Yes. The accent color can be changed from YTFlix > Settings using a color picker. The default is a copper/brown tone (#c17a2f). The color propagates via a CSS custom property throughout the interface.

= Does it work with caching plugins? =

Yes, but you should exclude the AJAX and REST API endpoints from page caching so real-time features like search, progress saving, and favorites work correctly.

= Can I use this with multiple YouTube channels? =

YTFlix supports one channel at a time. Switching to a different Channel ID and syncing will replace all content from the previous channel.

= What keyboard shortcuts are supported? =

On the video player page: Space (play/pause), Left/Right arrows (seek backward/forward), F (toggle fullscreen), M (toggle mute).

= Does YTFlix support multiple transcript languages? =

Yes. When fetching transcripts, the plugin looks for the requested language first, then falls back to English. Cached transcripts store the language code and name for easy switching.

== Screenshots ==

1. Netflix-style home page with hero section, search bar, and horizontal playlist rows.
2. Video preview modal popup with thumbnail and play button.
3. Dedicated watch page with custom YouTube player and tabbed sidebar (Episodes + Transcript).
4. Transcript view with timestamps and download option.
5. Continue Watching row for logged-in users.
6. Admin dashboard showing API status, video/playlist counts, and last sync time.
7. Admin settings page with API configuration, appearance options, URL slugs, and feature toggles.
8. Admin sync page with manual sync and cache clear buttons.
9. Search results overlay with videos and playlists.
10. Responsive mobile layout.

== Changelog ==

= 1.0.0 =
* Initial release.
* YouTube channel sync with automatic playlist and video import.
* Auto-fetch channel name, logo, and banner from YouTube.
* Netflix-style home page with Swiper.js horizontal sliders.
* Video preview modal with play button.
* Dedicated watch page with custom YouTube player controls.
* Tabbed sidebar with Episodes and Transcript tabs.
* Transcript fetching with multi-language support and text download.
* User progress tracking and Continue Watching row.
* My List / Favorites system for logged-in users.
* Auto-play next episode in playlist.
* Keyboard shortcuts (Space, Arrow keys, F, M).
* Speed control and Picture-in-Picture support.
* Real-time AJAX search across videos and playlists.
* Full REST API at /wp-json/ytflix/v1/.
* AJAX handlers with nonce verification.
* WP Cron scheduled sync (hourly, twice daily, daily).
* Customizable accent color, URL slugs, and feature toggles.
* Recommendations engine (trending, related, personalized).
* Admin dashboard, settings page, and sync management.
* Channel switching with automatic content purge.
* Fully responsive design.
* Translation-ready (ytflix text domain).
* Custom post types: ytflix_video, ytflix_playlist.
* Custom taxonomy: ytflix_genre.
* Custom database tables: user_progress, transcripts, favorites.

== Upgrade Notice ==

= 1.0.0 =
Initial release of YTFlix. Install and configure your YouTube API key and Channel ID to get started.
