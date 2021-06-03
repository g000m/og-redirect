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

	function __construct() {
		add_filter( 'save_post_post', array( $this, 'detect_new_post' ), 10, 2 );
		add_action( 'post_updated', array( $this, 'detect_slug_change' ), 10, 3 );

	}

	public function is_fb(): bool {
		if ( is_null( $this->detected_fb ) ) {
			$this->detect_fb();
		}

		return $this->detected_fb;
	}

	public function detect_fb(): bool {
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
	public function detect_new_post( int $post_ID, WP_Post $post ): void {
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
	public function detect_slug_change( int $post_ID, WP_Post $post_after, WP_Post $post_before ): void {
		if ( ! isset( $this->post ) ) {
			$this->post = $post_before;
			// create new OG_Post?
		}

		// if slugs are not equal
		if ( $post_before->post_name !== $post_after->post_name ) {
			$this->og_post->set_canonical_url( get_permalink( $post_after ) );  // update og_canonical_url
			change_og_url(get_permalink($post_before), get_permalink($post_after)); // update og_object
		}


	}


}

function change_og_url( string $post_before, string $post_after ) {
	// 1. query og_object
	$og_object = get_og_object($post_before);

}
