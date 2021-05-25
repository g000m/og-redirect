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
    public $post = null;

    protected $requested_old_url = null;

    function __construct()
    {
        add_filter('old_slug_redirect_post_id', array( $this, 'capture_post_from_id' ));
        add_filter('old_slug_redirect_url', array( $this, 'reset_404' )); // @TODO deactivate this if viewing a non-canonical URL (if $url !== get_post_canonical_url_meta() )
        add_action('wp_head', array( $this, 'og_meta' ));
    }

    /**
     * After original post has been resolved, use this hook to capture a WP_Post object via the $id
     *
     * @param $id
     *
     * @return mixed
     */
    public function capture_post_from_id($id)
    {
        if ($id > 0) { // if 0, we're on a 404
            $this->post = get_post($id);
        }

        return $id;
    }

    /**
     * Fires if an old post redirect URL was found
     *
     * returns empty page at old URL when FB scraper visits
     *
     * returning null exits the calling function before wp_redirect() is called, allowing the page to respond on the
     * original URL.
     *
     * @TODO exit when original slug/URL is restored
     *
     * @param $link
     *
     * @return mixed|null
     */
    public function reset_404($link)
    {
        $test = strpos(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), 'fbext') >= 0; // @TODO delete this before release
        if (is_404() && (is_FB() || $test)) {
            global $wp_query;

            $wp_query->is_single = true;
            $wp_query->is_404    = false;
            status_header(200);

            // the request matched a valid old slug, so grab the URL
            $this->requested_old_url = home_url($_SERVER['REQUEST_URI']);

            return null;
        } else {
            return $link;
        }
    }

    /**
     * writesog:url and og:type to <head>
     */
    public function og_meta(): void
    {
        global $post; // will be null if 404
        $this->post = (isset($post)) ? $post : $this->post;

        if (is_null($this->post)) {
            return;
        }

        $post_canonical_url_meta = get_post_canonical_url_meta($this->post->ID);
        if (! empty($post_canonical_url_meta)) {
            $url = $post_canonical_url_meta;
        } elseif (isset($this->requested_old_url)) {
            $url = $this->requested_old_url;
        } else {
            $url = get_permalink($this->post);
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
 * Changes the URL written to the FB comment snippet
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
    // @TODO this match is present during get_the_excerpt, probably because it calls the_content() and filters it.
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
