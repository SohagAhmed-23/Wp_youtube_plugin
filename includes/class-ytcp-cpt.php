<?php
/**
 * Registers the plugin's custom post types and taxonomies.
 *
 * @package YTChannelProNetflixStyleYoutubePlatform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles registration of ytcp_video, ytcp_playlist CPTs and ytcp_genre taxonomy.
 */
class YTCP_CPT {

	/**
	 * Registers all CPTs and taxonomies with WordPress.
	 *
	 * @return void
	 */
	public function register() {
		$video_slug    = get_option( 'ytcp_video_slug', 'watch' );
		$playlist_slug = get_option( 'ytcp_playlist_slug', 'series' );

		register_post_type(
			'ytcp_video',
			array(
				'labels'              => array(
					'name'          => __( 'Videos', 'ytchannel-pro-netflix-style-youtube-platform' ),
					'singular_name' => __( 'Video', 'ytchannel-pro-netflix-style-youtube-platform' ),
					'add_new_item'  => __( 'Add New Video', 'ytchannel-pro-netflix-style-youtube-platform' ),
					'edit_item'     => __( 'Edit Video', 'ytchannel-pro-netflix-style-youtube-platform' ),
					'search_items'  => __( 'Search Videos', 'ytchannel-pro-netflix-style-youtube-platform' ),
				),
				'public'              => true,
				'has_archive'         => true,
				'rewrite'             => array(
					'slug'       => $video_slug,
					'with_front' => false,
				),
				'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-video-alt3',
				'show_in_menu'        => false,
				'exclude_from_search' => false,
			)
		);

		register_post_type(
			'ytcp_playlist',
			array(
				'labels'       => array(
					'name'          => __( 'Playlists', 'ytchannel-pro-netflix-style-youtube-platform' ),
					'singular_name' => __( 'Playlist', 'ytchannel-pro-netflix-style-youtube-platform' ),
					'add_new_item'  => __( 'Add New Playlist', 'ytchannel-pro-netflix-style-youtube-platform' ),
					'edit_item'     => __( 'Edit Playlist', 'ytchannel-pro-netflix-style-youtube-platform' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array(
					'slug'       => $playlist_slug,
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-playlist-video',
				'show_in_menu' => false,
			)
		);

		register_taxonomy(
			'ytcp_genre',
			array( 'ytcp_video', 'ytcp_playlist' ),
			array(
				'labels'       => array(
					'name'          => __( 'Genres', 'ytchannel-pro-netflix-style-youtube-platform' ),
					'singular_name' => __( 'Genre', 'ytchannel-pro-netflix-style-youtube-platform' ),
				),
				'public'       => true,
				'hierarchical' => true,
				'rewrite'      => array( 'slug' => 'genre' ),
				'show_in_rest' => true,
			)
		);
	}
}
