<?php
/**
 * Plugin Name:     Open Graph Redirect
 * Plugin URI:      https://gabeherbert.com/plugins/og-redirect
 * Description:     When using Facebook for comments, the data is stored on their servers in an Open Graph object (og_object). Comments, likes, and shares are associated with the original page URL that they were created on, so changing domains, permalink structures, or from http to https can cause problems. This plugin solves these problems.
 * Author:          Gabe Herbert
 * Author URI:      https://gabeherbert.com
 * Text Domain:     og-redirect
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         OG_Redirect
 */

/**
 * Class OG_Redirect
 *
 * This handles 3 cases:
 *
 * - invalid URL/404
 * - valid original URL
 * - valid redirected URL
 */
class OG_Redirect
{
    public $og_url = null;

    public $post = null;

    function __construct()
    {
	    global $post;

        add_filter('old_slug_redirect_post_id', array( $this, 'set_og_url' ));
        add_filter('old_slug_redirect_url', array( $this, 'reset_404' ));
        add_action('wp_head', array( $this, 'og_meta' ));
    }

    public function set_og_url($id)
    {
        if ($id <= 0) {
            return $id;
        }

        $this->post = get_post($id);
//        $this->og_url = ( ! empty(get_post_canonical_url_meta($id)) ) ? get_post_canonical_url_meta($id) : $this->og_url;

        return $id;
    }

    public function reset_404($link)
    {
        $test = false;
        if (is_404() && (is_FB() || $test)) {
            global $wp_query;

            $wp_query->is_single = true;
            $wp_query->is_404    = false;
            status_header(200);

//          $this->og_url = $link;

            return null;
        }

        return $link;
    }

    public function og_meta()
    {
    	global $post;
        if (is_null($post)) {
            return;
        }

        $post_canonical_url_meta = get_post_canonical_url_meta($post->ID);
        if (isset($this->og_url)) {
            $url = $this->og_url;
        } elseif (! empty($post_canonical_url_meta)) {
            $url = $post_canonical_url_meta;
        } else {
            $url = get_permalink($post);
        }
        echo "\n<meta property=\"og:url\" content=\"$url\" />";
        echo "\n<meta property=\"og:type\" content=\"article\" />\n";
    }
}




function is_FB(): bool
{
    return strpos($_SERVER["HTTP_USER_AGENT"], "facebookexternalhit/") !== false;
}

function get_post_canonical_url_meta(int $post_id) : string
{
    $get_post_custom_values = get_post_meta($post_id, 'og_canonical_url');
    $values                 = array_filter($get_post_custom_values);

    return reset($values);
}



/**
 * Checks that filter 'post_link' was called within wpfc_show_facebook_comments()
 *
 * @param $permalink
 * @param $post
 * @param $leavename
 *
 * @return mixed|string
 */
function _get_post_canonical_url_meta($permalink, $post, $leavename)
{
    if (has_caller_method('wpfc_show_facebook_comments')) {
        $url = get_post_canonical_url_meta($post->ID);
        if (! empty($url)) {
            return $url;
        }
    }

    return $permalink;
}

add_filter('post_link', '_get_post_canonical_url_meta', 10, 3);

function has_caller_method(string $method)
{
    $traces = debug_backtrace();

    foreach ($traces as $trace) {
        if ($trace['function'] === $method) {
            return true;
        }
    }

    return false;
}

// unused

function handle_404($preempt, $wp_query)
{
    if (true || $_SERVER['HTTP_USER_AGENT'] === "FacebookExternalHit") {
        global $wp_query;

//      $wp_query->set_404();

        return true;
    }

    return $preempt;
}

//add_filter( 'pre_handle_404', 'handle_404', 10, 2 );

new OG_Redirect();
