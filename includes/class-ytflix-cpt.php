<?php
if (!defined('ABSPATH')) exit;

class YTFlix_CPT {

    public function register() {
        $video_slug = get_option('ytflix_video_slug', 'watch');
        $playlist_slug = get_option('ytflix_playlist_slug', 'series');

        register_post_type('ytflix_video', [
            'labels' => [
                'name'          => __('Videos', 'ytflix'),
                'singular_name' => __('Video', 'ytflix'),
                'add_new_item'  => __('Add New Video', 'ytflix'),
                'edit_item'     => __('Edit Video', 'ytflix'),
                'search_items'  => __('Search Videos', 'ytflix'),
            ],
            'public'              => true,
            'has_archive'         => true,
            'rewrite'             => ['slug' => $video_slug, 'with_front' => false],
            'supports'            => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'show_in_rest'        => true,
            'menu_icon'           => 'dashicons-video-alt3',
            'show_in_menu'        => false,
            'exclude_from_search' => false,
        ]);

        register_post_type('ytflix_playlist', [
            'labels' => [
                'name'          => __('Playlists', 'ytflix'),
                'singular_name' => __('Playlist', 'ytflix'),
                'add_new_item'  => __('Add New Playlist', 'ytflix'),
                'edit_item'     => __('Edit Playlist', 'ytflix'),
            ],
            'public'       => true,
            'has_archive'  => true,
            'rewrite'      => ['slug' => $playlist_slug, 'with_front' => false],
            'supports'     => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-playlist-video',
            'show_in_menu' => false,
        ]);

        register_taxonomy('ytflix_genre', ['ytflix_video', 'ytflix_playlist'], [
            'labels' => [
                'name'          => __('Genres', 'ytflix'),
                'singular_name' => __('Genre', 'ytflix'),
            ],
            'public'       => true,
            'hierarchical' => true,
            'rewrite'      => ['slug' => 'genre'],
            'show_in_rest' => true,
        ]);
    }
}
