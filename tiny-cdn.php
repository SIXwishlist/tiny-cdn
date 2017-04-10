<?php
/*
Plugin Name: Tiny CDN
Description: Use an origin pull CDN with very few lines of code.
Version: 0.1.0
Author: Viktor Szépe
License: GNU General Public License (GPL) version 2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: https://github.com/szepeviktor/tiny-cdn
Constants: TINY_CDN_CONTENT_URL
Constants: TINY_CDN_INCLUDES_URL
*/

final class O1_Tiny_Cdn {

    private $excludes;

    public function __construct() {

        if ( is_admin() || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
            return;
        }

        // Just before WordPress determines which template page to load
        add_action( 'template_redirect', array( $this, 'hooks' ) );
    }

    /**
     * Rewrite only URL-s WordPress core "knows about".
     */
    public function hooks() {

        $capability = apply_filters( 'tiny_cdn_capability', 'edit_pages' );

        if ( apply_filters( 'tiny_cdn_disable', false ) || ( current_user_can( $capability ) ) {
            return;
        }

        // Excludes regexp
        $this->excludes = apply_filters( 'tiny_cdn_excludes', '#\.php#' );

        // ? add_filter( 'includes_url', array( $this, 'rewrite_includes' ), 9999 );
        // ? add_filter( 'content_url', array( $this, 'rewrite_content' ), 9999 );

        // Rewrite style and script URL-s
        add_filter( 'script_loader_src', array( $this, 'rewrite' ), 9999 );
        add_filter( 'style_loader_src', array( $this, 'rewrite' ), 9999 );

        // Rewrite /wp-content file URL-s
        add_filter( 'plugins_url', array( $this, 'rewrite_content' ), 9999 );
        add_filter( 'theme_root_uri', array( $this, 'rewrite_content' ), 9999 );
        // ? wp_get_attachment_image_src
        add_filter( 'upload_dir', array( $this, 'uploads' ), 9999 );

        // Rewrite URL-s in post_content
        add_filter( 'the_content', array( $this, 'images' ), 9999 );
    }

    /**
     * Rewrite both includes and content URL-s.
     */
    public function rewrite( $url ) {

        if ( 1 === preg_match( $this->excludes, $url ) ) {
            return $url;
        }

        $url = $this->replace_includes( $url );
        $url = $this->replace_content( $url );

        return $url;
    }

    /**
     * Rewrite content URL.
     */
    public function rewrite_content( $url ) {

        if ( 1 === preg_match( $this->excludes, $url ) ) {
            return $url;
        }

        $url = $this->replace_content( $url );

        return $url;
    }

    /**
     * Replace includes URL if the given constant is present.
     */
    private function replace_includes( $url ) {

        if ( ! defined( 'TINY_CDN_INCLUDES_URL' ) ) {
            return $url;
        }

        $includes_url = site_url( '/' . WPINC, null );
        $url = str_replace( $includes_url, TINY_CDN_INCLUDES_URL, $url );

        return $url;
    }

    /**
     * Replace content URL if the given constant is present.
     */
    private function replace_content( $url ) {

        if ( ! defined( 'TINY_CDN_CONTENT_URL' ) ) {
            return $url;
        }

        $url = str_replace( WP_CONTENT_URL, TINY_CDN_CONTENT_URL, $url );

        return $url;
    }

    /**
     * Rewrite uploads URL.
     */
    public function uploads( $upload_data ) {

        $upload_data['url'] = $this->rewrite_content( $upload_data['url'] );
        $upload_data['baseurl'] = $this->rewrite_content( $upload_data['baseurl'] );

        return $upload_data;
    }

    /**
     * Rewrite image URL-s in post content.
     */
    public function images( $content ) {

        // Only catch images inserted with the editor
        //           (        1        )(  2  )(      3      )
        $pattern = '|(<img [^>]*\bsrc=")([^"]+)(" [^>]*\balt="[^"]*")|';

        $content = preg_replace_callback(
            $pattern,
            function ( $matches ) {
                $url = $this->rewrite_content( $matches[2] );
                return $matches[1] . $url . $matches[3];
            },
            $content
        );

        return $content;
    }
}

new O1_Tiny_Cdn();

/*
Works with custom wp-content location
No multisite support -> wp-cdn-rewrite get_rewrite_path()
define( 'TINY_CDN_INCLUDES_URL', 'https://d2aaaaaaaaaaae.cloudfront.net/wp-includes' );
define( 'TINY_CDN_CONTENT_URL', 'https://d2aaaaaaaaaaae.cloudfront.net/wp-content' );
filter example: at 'init'
tiny_cdn_disable
tiny_cdn_capability
tiny_cdn_excludes

 https://www.itsupportguides.com/knowledge-base/wordpress/wordpress-how-to-serve-static-content-from-a-cookieless-domain/
 https://codex.wordpress.org/Editing_wp-config.php#Set_Cookie_Domain
 define( 'COOKIE_DOMAIN', site_url()/domain part );
 Test cookies on CDN!

 Don't use CDN when user has capability
 Avoid query string -> resource-versioning

 W3TC
 - Add canonical header ???
 - Exclude filter ($url, $old_url) default: ".php"
 - subdomain for CDN -> Set cookie domain to xxx (Don't send cookies to CDN)
*/
