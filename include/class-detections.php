<?php

namespace GHC;

use WP_Post;

/**
 * Class Detections
 *
 * @package GHC
 */
class Detections {

	/**
	 * If fbexternalhit was detected in this request.
	 *
	 * @var bool
	 */
	public bool $detected_fb;

	private $og_post;


	function __construct() {
		add_action( 'the_posts', array( $this, 'get_post_object' ) );

		add_action( 'post_updated', array( $this, 'detect_slug_has_changed' ), 10, 3 );
		add_filter( 'save_post_post', array( $this, 'detect_new_post' ), 10, 3 ); // hook 'save_post_{post_type}' fires after 'post_updated'

	}

	public function get_post_object( $posts ) {
//		unhook self after first use? how to make sure this is the first/correct one then?
		if ( ! isset( $this->post ) ) {
			$this->post = $posts;
		}
		return $posts;
	}

	public function is_fb(): bool {
		if ( is_null( $this->detected_fb ) ) {
			$this->detect_fb_crawler();
		}

		return $this->detected_fb;
	}

	public function detect_fb_crawler(): bool {
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$this->detected_fb = strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 'facebookexternalhit/' ) !== false;
		}

		return ! ! $this->detected_fb;
	}

	/**
	 * Detects that a new post has been created, and captures the canonical url
	 *
	 * @param int     $post_ID the post object ID.
	 * @param WP_Post $post the newly created WP_Post object.
	 */
	public function detect_new_post( int $post_ID, WP_Post $post, bool $updated ): void {
		if ( $this->valid_statuses( $post, array( 'publish', 'private', 'draft' ) ) ) {
			return;
		}

		if ( $updated ) {
			return;
		}

		$this->og_post = new OG_Post( $post );
		$this->og_post->set_canonical_url( get_permalink( $post ) );
	}

	/**
	 * Detects that a slug has been changed, so that the og_object can be updated
	 *
	 * @param int     $post_ID ID of WP_Post.
	 * @param WP_Post $post_after the mutated WP_Post object.
	 * @param WP_Post $post_before the original WP_Post object.
	 */
	public function detect_slug_has_changed( int $post_ID, WP_Post $post_after, WP_Post $post_before ): void {
		if ( $this->valid_statuses( $post_after, array( 'publish', 'private', 'draft' ) ) ) {
			return;
		}

		if ( ! isset( $this->og_post ) ) { // ditch this?
			$this->og_post = new OG_Post( $post_after );
		}

		// if slugs are not equal
		if ( ! empty( $post_before->post_name ) && $post_before->post_name !== $post_after->post_name ) {
			$this->og_post->set_canonical_url( get_permalink( $post_after ) );  // update og_canonical_url
			change_og_url(get_permalink($post_before), get_permalink($post_after)); // update og_object
		}


	}

	/**
	 * A post's URL has changed, necessitating an update to its og_object.
	 *
	 * This would be tested after a URL or permalink change.
	 *
	 * @return bool
	 */
	public function og_object_needs_update(): bool {return false;}

	/**
	 * A wrapper for updates to an OG Object
	 *
	 * @param $value value needs to be determined @TODO which value?
	 *
	 * @return bool return value indicates success
	 */
	public function update_og_object( $value ): bool {return false;}

	/**
	 * This fires off the scraper on the new URL which should also scrape the old URL
	 *
	 * @param string $url url to update.
	 *
	 * @return bool
	 */
	public function og_object_url_has_changed( string $url ):bool {return false;}

	/**
	 * This detects that the post URL differs from its canonical URL, which will need to be written to the fb-comment.
	 *
	 * @return bool returns true/false if request differs from stored canonical URL
	 */
	public function comment_embed_url_has_changed():bool {return false;}

	/**
	 * Updates the URL used in the fb-comment snippet
	 *
	 * @param string $url original comment URL.
	 *
	 * @return bool returns true/false if successful
	 */
	public function update_fb_comment_url( string $url ): bool {return false;}

	/**
	 * @param WP_Post $post_after
	 *
	 * @return bool
	 */
	private function valid_statuses( WP_Post $post_after, array $valid_statuses ): bool {
		return ! in_array( $post_after->post_status, $valid_statuses, true );
}
}

function change_og_url( string $post_before, string $post_after ) {
	// 1. query og_object
//	$og_object = get_og_object($post_before);

}
