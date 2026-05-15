<?php
/**
 * Registers and handles all plugin shortcodes.
 *
 * @package YTChannelProNetflixStyleYoutubePlatform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode handler — registers [ytcp], [ytcp_hero], [ytcp_playlist], [ytcp_player], [ytcp_search].
 */
class YTCP_Shortcodes {

	/**
	 * Registers all plugin shortcodes with WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'ytcp', array( $this, 'render_full_page' ) );
		add_shortcode( 'ytcp_hero', array( $this, 'render_hero' ) );
		add_shortcode( 'ytcp_playlist', array( $this, 'render_playlist' ) );
		add_shortcode( 'ytcp_player', array( $this, 'render_player' ) );
		add_shortcode( 'ytcp_search', array( $this, 'render_search' ) );
	}

	/**
	 * Renders the full-page layout (hero + search + playlists + modal).
	 *
	 * @param array|string $atts Shortcode attributes (unused).
	 * @return string
	 */
	public function render_full_page( $atts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		ob_start();
		include YTCP_PLUGIN_DIR . 'templates/partials/hero.php';
		include YTCP_PLUGIN_DIR . 'templates/partials/search.php';
		include YTCP_PLUGIN_DIR . 'templates/partials/playlist-rows.php';
		include YTCP_PLUGIN_DIR . 'templates/partials/modal.php';
		return ob_get_clean();
	}

	/**
	 * Renders only the hero section.
	 *
	 * @param array|string $atts Shortcode attributes (unused).
	 * @return string
	 */
	public function render_hero( $atts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		ob_start();
		include YTCP_PLUGIN_DIR . 'templates/partials/hero.php';
		return ob_get_clean();
	}

	/**
	 * Renders a single playlist row by ID.
	 *
	 * @param array|string $atts Shortcode attributes. Accepts 'id' (playlist post ID).
	 * @return string
	 */
	public function render_playlist( $atts ) {
		$atts        = shortcode_atts( array( 'id' => 0 ), $atts, 'ytcp_playlist' );
		$playlist_id = absint( $atts['id'] );

		if ( ! $playlist_id ) {
			return '';
		}

		ob_start();
		$this->render_single_playlist_row( $playlist_id );
		return ob_get_clean();
	}

	/**
	 * Renders a single video player embed.
	 *
	 * @param array|string $atts Shortcode attributes. Accepts 'video' (video post ID).
	 * @return string
	 */
	public function render_player( $atts ) {
		$atts     = shortcode_atts( array( 'video' => 0 ), $atts, 'ytcp_player' );
		$video_id = absint( $atts['video'] );

		if ( ! $video_id ) {
			return '';
		}

		ob_start();
		include YTCP_PLUGIN_DIR . 'templates/partials/player-embed.php';
		return ob_get_clean();
	}

	/**
	 * Renders the search bar partial.
	 *
	 * @param array|string $atts Shortcode attributes (unused).
	 * @return string
	 */
	public function render_search( $atts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		ob_start();
		include YTCP_PLUGIN_DIR . 'templates/partials/search.php';
		return ob_get_clean();
	}

	/**
	 * Outputs the HTML for a single playlist row.
	 *
	 * @param int $playlist_id The playlist post ID.
	 * @return void
	 */
	private function render_single_playlist_row( $playlist_id ) {
		$playlist = get_post( $playlist_id );
		if ( ! $playlist ) {
			return;
		}

		$video_ids = get_post_meta( $playlist_id, '_ytcp_video_ids', true );
		if ( empty( $video_ids ) ) {
			return;
		}

		$videos = get_posts(
			array(
				'post_type'      => 'ytcp_video',
				'post__in'       => $video_ids,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'posts_per_page' => 50,
				'post_status'    => 'publish',
			)
		);

		if ( empty( $videos ) ) {
			return;
		}

		$row_index = 0;
		include YTCP_PLUGIN_DIR . 'templates/partials/playlist-row.php';
	}
}
