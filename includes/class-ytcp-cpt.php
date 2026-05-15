<?php
/**
 * Registers the plugin's custom post types and taxonomies.
 *
 * @package CraftsmenitVideoPlatformForYouTube
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
					'name'          => __( 'Videos', 'craftsmenit-video-platform-for-youtube' ),
					'singular_name' => __( 'Video', 'craftsmenit-video-platform-for-youtube' ),
					'add_new_item'  => __( 'Add New Video', 'craftsmenit-video-platform-for-youtube' ),
					'edit_item'     => __( 'Edit Video', 'craftsmenit-video-platform-for-youtube' ),
					'search_items'  => __( 'Search Videos', 'craftsmenit-video-platform-for-youtube' ),
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
					'name'          => __( 'Playlists', 'craftsmenit-video-platform-for-youtube' ),
					'singular_name' => __( 'Playlist', 'craftsmenit-video-platform-for-youtube' ),
					'add_new_item'  => __( 'Add New Playlist', 'craftsmenit-video-platform-for-youtube' ),
					'edit_item'     => __( 'Edit Playlist', 'craftsmenit-video-platform-for-youtube' ),
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
					'name'          => __( 'Genres', 'craftsmenit-video-platform-for-youtube' ),
					'singular_name' => __( 'Genre', 'craftsmenit-video-platform-for-youtube' ),
				),
				'public'       => true,
				'hierarchical' => true,
				'rewrite'      => array( 'slug' => 'genre' ),
				'show_in_rest' => true,
			)
		);
	}
}
