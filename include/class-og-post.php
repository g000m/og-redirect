<?php

namespace GHC;

require_once __DIR__ . '../vendor/autoload.php'; // change path as needed

class OG_Post {
	private string $canonical_url_meta_key = 'og_canonical_url';

	private object $post;

	protected \Facebook\Facebook $fb;

	function __construct( $post = null ) {
		$this->post = $post;
	}

	public function init_sdk(string $app_id, string $app_secret, string $default_token) {

		$this->fb = new \Facebook\Facebook([
			'app_id' => $app_id,
			'app_secret' => $app_secret,
			'default_graph_version' => 'v10.0',
			'default_access_token' => $default_token, // optional
		]);
	}

	public function set_canonical_url( string $url ) {
		// check first for existing canonical_url
		add_post_meta( $this->post->ID, $this->canonical_url_meta_key, $url );
	}

	public function scrape( string $url ) {
		$request = array (
			'scrape' => 'true',
			'id' => $url
		);
		$this->post($request);
	}

	protected function post( array $request, string $endpoint = '/' ) {
		try {
			// Returns a `FacebookFacebookResponse` object
			$response = $this->fb->post(
				$endpoint,
				$request,
				'{access-token}'
			);
		} catch(FacebookExceptionsFacebookResponseException $e) {
			echo 'Graph returned an error: ' . $e->getMessage();
			exit;
		} catch(FacebookExceptionsFacebookSDKException $e) {
			echo 'Facebook SDK returned an error: ' . $e->getMessage();
			exit;
		}
		$this->graphNode = $response->getGraphNode();
	}

	protected function get( string $endpoint = '/' ) {
		try {
			// Returns a `FacebookFacebookResponse` object
			$response = $this->fb->get(	$endpoint );
		} catch(FacebookExceptionsFacebookResponseException $e) {
			echo 'Graph returned an error: ' . $e->getMessage();
			exit;
		} catch(FacebookExceptionsFacebookSDKException $e) {
			echo 'Facebook SDK returned an error: ' . $e->getMessage();
			exit;
		}
		$this->graphNode = $response->getGraphNode();
	}
}

$og = new OG_Post();
$og->init_sdk('app_id', 'app_secret', 'token' );
$og->scrape('https://360.devhost.us/2021/05/12/slug-of-three/');

