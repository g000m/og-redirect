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
namespace GHC;

defined( 'ABSPATH' ) || exit;

if ( isset( $_SERVER['REQUEST_URI'] ) && $_SERVER['REQUEST_URI'] === "/favicon.ico" ) {
	return;
}


require __DIR__ . '/include/class-detections.php';
require __DIR__ . '/include/class-og-post.php';

class OG_Redirect
{
    public $post = null;

    protected $requested_old_url = null; // URL of a post's old slug
    protected $requested_url = null;    // URL requested by browser

    protected $meta_url = null; // URL to set in meta og:url

    function __construct()
    {
        if (is_admin()) {
            return;
        }

        $this->requested_url = $this->get_server_requested_url();

        add_filter('old_slug_redirect_post_id', array( $this, 'capture_post_from_id' ));
        add_filter('old_slug_redirect_url', array( $this, 'reset_404' )); // @TODO deactivate this if viewing a non-canonical URL (if $url !== get_post_canonical_url_meta() )
        add_action('wp_head', array( $this, 'choose_og_url' ), 0);
        add_action('wp_head', array( $this, 'head_ob_start' ), 1);
    }

    private function replace_head_og_url(): void
    {
        add_action('wp_head', array( $this, 'head_ob_stop' ), 999);
    }

    public function set_head_og_meta() : void
    {
        add_action('wp_head', function () {
            echo "\n<meta property=\"og:url\" content=\"$this->meta_url\" />";
            echo "\n<meta property=\"og:type\" content=\"article\" />\n";
        }, 1);
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
     * @TODO what happens when FB scraper attempts a completely invalid URL? this should return 404.
     * @param $link
     *
     * @return mixed|null
     */
    public function reset_404($link)
    {
        if (is_404() && is_FB()) {
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
     * chooses proper url for og:url
     *
     */
    public function choose_og_url(): void
    {
        global $post; // will be null if 404
        $this->post = (isset($post)) ? $post : $this->post;

        if (is_null($this->post)) {
            return;
        }

        // just because there's a cacnonical URL set, doesn't mean it neesd to be used.
        $post_canonical_url_meta = get_post_canonical_url_meta($this->post->ID);
        if (isset($this->requested_old_url)) { // if is404() & is_FB(): showing canonical page w/ no content
            $this->meta_url = $post_canonical_url_meta;
            $this->set_head_og_meta();
        } elseif (! empty($post_canonical_url_meta) && $post_canonical_url_meta !== $this->requested_url) {
             $this->meta_url = $post_canonical_url_meta;
             add_filter('post_link', '_get_post_canonical_url_meta', 10, 3); // replaces url in facebook snippet
            $this->replace_head_og_url();
        } else {
            echo "<h1>THIS SHOULD NOT HAPPEN</h1>"; // @TODO throw an actual warning or log
        }
    }


    /**
     * starts output buffering early in wp_head()
     *
     * @
     */
    public function head_ob_start()
    {
        ob_start();
    }

    /**
     * captures buffered wp_head() and replaces og:url content
     *
     * @TODO add this hook only after determining that it is needed
     * @TODO move to callback passed into ob_start()?
     */
    public function head_ob_stop()
    {
        $buffer = ob_get_clean();

        if (is_null($this->meta_url)) {
            echo $buffer;
            return;
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;
        $dom->loadHTML("$buffer", LIBXML_HTML_NODEFDTD);
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if ($meta->hasAttribute('property') && $meta->getAttribute('property') === "og:url") {
                if ($meta->getAttribute('content') !== $this->meta_url) {
                    $meta->setAttribute('content', $this->meta_url);
                }
                break;
            }
        }
        $body = $dom->getElementsByTagName('head')->item(0);
//      echo $dom->saveHTML($body); // would exit here if I didn't need to unroll the head element
//      echo $dom->saveHTML( );

        $result = '';

        // how to return childnodes as html instead of iterating this?
        foreach ($body->childNodes as $childNode) {
            $result .= $dom->saveHTML($childNode);
        }

        echo $result;
    }

    /**
     * @return string
     */
    protected function get_server_requested_url(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";

        return $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
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
}


/**
 * detects facebook crawler useragent
 * @return bool
 */
function is_FB(): bool
{
    $test = is_int(strpos(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), 'fbext')); // @TODO delete this before release

    return $test || strpos($_SERVER["HTTP_USER_AGENT"], "facebookexternalhit/") !== false;
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
function _get_post_canonical_url_meta($permalink, $post, $leavename) //@TODO make this function neame meaningful, eg replace_url_in_fb_snippet()
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


function activate()
{
    // checks for a hostname specfying an 'old domain', to be used when a domain has already been migrated
    // and now owner says 'WhereTF are my comments"
    if (!defined('OG_MIGRATE_URL')) {
        return;
    }

    $got_posts = get_posts(array('numberposts'=>-1));
    foreach ($got_posts as $post) {
        if (defined('OG_MIGRATE_URL') /* @TODO AND is a valid http(s)://domain */) {
            $post_permalink = str_replace(get_home_url(), OG_MIGRATE_URL, get_permalink($post));
        } else {
            $post_permalink = get_permalink($post);
        }
        $updated    = add_post_meta($post->ID, 'og_canonical_url', $post_permalink, true);
    }
}
//register_activation_hook(__FILE__, 'activate');

//new OG_Redirect();

new Detections();
