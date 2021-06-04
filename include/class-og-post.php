<?php

namespace GHC;

if (! defined('ABSPATH')) {
//	return;
	// @TODO remove test code
	require_once dirname(__DIR__) . '/../../../../vendor/autoload.php';
	require_once dirname(__DIR__) . '/../../../../config/application.php';
}

require_once __DIR__ . '/../vendor/autoload.php'; // change path as needed

class OG_Post {
	private string $canonical_url_meta_key = 'og_canonical_url';

//	private object $post;

	protected \Facebook\Facebook $fb;
	private \Facebook\GraphNodes\GraphNode $graph_node;

	function __construct( $post = null ) {
		$this->post = $post;
	}

	public function init_sdk() {

		$this->fb = new \Facebook\Facebook([
			'app_id' => FB_APP_ID,
			'app_secret' => FB_APP_SECRET,
			'default_graph_version' => 'v10.0',
			'default_access_token' => FB_BEARER_TOKEN, // optional
		]);
	}

	public function set_canonical_url( string $url ) {
		// check first for existing canonical_url
		add_post_meta( $this->post->ID, $this->canonical_url_meta_key, $url, true );
	}

	public function scrape( string $url ) {
		$request = array (
			'scrape' => 'true',
			'id'     => $url,
		);
		$this->post($request);
	}

	protected function post( array $request, string $endpoint = '/' ) {
		try {
			// Returns a `FacebookFacebookResponse` object
			$response = $this->fb->post(
				$endpoint,
				$request,
			);
		} catch(FacebookExceptionsFacebookResponseException $e) {
			echo 'Graph returned an error: ' . $e->getMessage();
			exit;
		} catch(FacebookExceptionsFacebookSDKException $e) {
			echo 'Facebook SDK returned an error: ' . $e->getMessage();
			exit;
		} catch(Exception $e) {
			echo 'Exception: ' . $e->getMessage();
			exit;
		}
		$this->graph_node = $response->getGraphNode();
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
		$this->graph_node = $response->getGraphNode();
	}
}

//$og = new OG_Post();
//$og->init_sdk();
//$og->scrape('https://360.devhost.us/2021/05/12/slug-of-three/');

