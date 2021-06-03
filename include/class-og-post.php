<?php

namespace GHC;

class OG_Post {
	private string $canonical_url_meta_key = 'og_canonical_url';

	private object $post;

	function __construct( $post ) {
		$this->post = $post;
	}

	public function set_canonical_url( string $url ) {
		add_post_meta( $this->post->ID, $this->canonical_url_meta_key, $url );
	}
}
