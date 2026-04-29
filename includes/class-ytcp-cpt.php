<?php
if (!defined('ABSPATH')) exit;

class YTCP_CPT {

    public function register() {
        $video_slug = get_option('ytcp_video_slug', 'watch');
        $playlist_slug = get_option('ytcp_playlist_slug', 'series');

        register_post_type('ytcp_video', [
            'labels' => [
                'name'          => __('Videos', 'ytchannel-pro'),
                'singular_name' => __('Video', 'ytchannel-pro'),
                'add_new_item'  => __('Add New Video', 'ytchannel-pro'),
                'edit_item'     => __('Edit Video', 'ytchannel-pro'),
                'search_items'  => __('Search Videos', 'ytchannel-pro'),
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

        register_post_type('ytcp_playlist', [
            'labels' => [
                'name'          => __('Playlists', 'ytchannel-pro'),
                'singular_name' => __('Playlist', 'ytchannel-pro'),
                'add_new_item'  => __('Add New Playlist', 'ytchannel-pro'),
                'edit_item'     => __('Edit Playlist', 'ytchannel-pro'),
            ],
            'public'       => true,
            'has_archive'  => true,
            'rewrite'      => ['slug' => $playlist_slug, 'with_front' => false],
            'supports'     => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-playlist-video',
            'show_in_menu' => false,
        ]);

        register_taxonomy('ytcp_genre', ['ytcp_video', 'ytcp_playlist'], [
            'labels' => [
                'name'          => __('Genres', 'ytchannel-pro'),
                'singular_name' => __('Genre', 'ytchannel-pro'),
            ],
            'public'       => true,
            'hierarchical' => true,
            'rewrite'      => ['slug' => 'genre'],
            'show_in_rest' => true,
        ]);
    }
}
