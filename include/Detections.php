<?php


namespace GHC;


class Detections {

	public bool $is_fb;

	function __construct() {
		 add_filter( 'save_post_post', array($this, 'detect_new_post') );
	}

	function is_FB(): bool {
		if (is_null($this->is_fb)) {
			$this->detect_fb();
		}
		return $this->is_fb;
	}

	function detect_fb(): bool {
		$this->is_fb = strpos($_SERVER["HTTP_USER_AGENT"], "facebookexternalhit/") !== false;

		return $this->is_fb;
	}

	/**
	 * Detects that a new post has been created, and captures the canonical url
	 */
	function detect_new_post($post): void {
		add_post_meta( $post->ID,   'og_canonical_url', get_permalink($post));
	}


}
