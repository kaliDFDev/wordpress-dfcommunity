<?php

/**
 * Statically_Rewriter
 *
 * @since 0.0.1
 */

class Statically_Rewriter
{
    var $blog_url       = null;     // origin URL
    var $cdn_url        = null;     // Zone URL

    var $dirs           = null;     // included directories
    var $excludes       = [];       // excludes
    var $quality        = null;     // set image quality
    var $width          = null;     // set image width
    var $height         = null;     // set image height
    var $webp           = false;    // enable WebP
    var $relative       = false;    // use CDN on relative paths
    var $https          = false;    // use CDN on HTTPS
    var $statically_api_key = null; // required API key for Statically

    /**
     * constructor
     *
     * @since 0.0.1
     */
    function __construct(
        $blog_url,
        $cdn_url,
        $dirs,
        array $excludes,
        $quality,
        $width,
        $height,
        $webp,
        $relative,
        $https,
        $statically_api_key
    ) {
        $this->blog_url       = $blog_url;
        $this->cdn_url        = $cdn_url;
        $this->dirs           = $dirs;
        $this->excludes       = $excludes;
        $this->quality        = $quality;
        $this->width          = $width;
        $this->height         = $height;
        $this->webp           = $webp;
        $this->relative       = $relative;
        $this->https          = $https;
        $this->statically_api_key = $statically_api_key;
        $this->blog_domain    = parse_url( $blog_url, PHP_URL_HOST );
        $this->blog_path      = parse_url( $blog_url, PHP_URL_PATH );

        // add DNS prefetch meta
        add_action( 'wp_head', [ $this, 'dns_prefetch' ], 1 );
    }

    /**
     * exclude assets that should not be rewritten
     *
     * @since 0.0.1
     *
     * @param string $asset current asset
     * @return boolean true if need to be excluded
     */
    protected function exclude_asset( &$asset ) {
        // excludes
        foreach ( $this->excludes as $exclude ) {
            if ( !! $exclude && stristr( $asset, $exclude ) != false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * relative url
     *
     * @since 0.0.1
     *
     * @param string $url a full url
     * @return string protocol relative url
     */
    protected function relative_url( $url ) {
        return substr( $url, strpos( $url, '//' ) );
    }

    /**
     * rewrite url
     *
     * @since 0.0.1
     *
     * @param string $asset current asset
     * @return string updated url if not excluded
     */
    protected function rewrite_url( &$asset ) {
        if ( $this->exclude_asset( $asset[0]) ) {
            return $asset[0];
        }

        // Don't rewrite if in preview mode
        if ( is_admin_bar_showing()
                && array_key_exists( 'preview', $_GET )
                && $_GET['preview'] == 'true' )
        {
            return $asset[0];
        }

        $cdn_url = $this->cdn_url;
        $blog_url = $this->relative_url( $this->blog_url );
        $subst_urls = [ 'http:'.$blog_url ];

        // rewrite both http and https URLs if we ticked 'enable CDN for HTTPS connections'
        if ( $this->https ) {
            $subst_urls = [
                'http:'.$blog_url,
                'https:'.$blog_url,
            ];
        }

        // return original URL on non-custom domain
        if ( !Statically::is_custom_domain()
                && !preg_match( '/\.(bmp|gif|jpe?g|png|webp|svg)/i', $asset[0] ) ) {
            return $asset[0];
        }

        // check if it is an image
        if ( preg_match( '/\.(bmp|gif|jpe?g|png|webp)/i', $asset[0] ) ) {
            $this->blog_path_regex = str_replace( '/', '\/', $this->blog_path );

            // check options and apply transformations
            if ( ! preg_match( "/$this->blog_path_regex/i", $blog_url ) ) {
                $asset[0] = str_replace( $blog_url, $blog_url . $this->image_tranformations(), $asset[0] );
            } else {
                $asset[0] = str_replace( $blog_url, $blog_url . $this->image_tranformations() . $this->blog_path, $asset[0] );
            }

            // relative URL
            if ( $this->relative && ! strstr( $asset[0], $blog_url ) ) {
                $asset[0] = str_replace( $asset[0], $this->image_tranformations() . $asset[0], $asset[0] );
            }

            // use /img/
            $cdn_url = str_replace( '/sites', '/img', $this->cdn_url );

            // if user use a custom domain
            if ( Statically::is_custom_domain() && ( $this->quality || $this->width || $this->height || $this->webp ) ) {
                $cdn_url = $this->cdn_url . '/statically/img';
            }
        }

        // SVG image
        if ( preg_match( '/\.svg/i', $asset[0] ) ) {
            $cdn_url = str_replace( '/sites', '/img', $this->cdn_url );
        }

        // is it a protocol independent URL?
        if ( 0 === strpos( $asset[0], '//' ) ) {
            return str_replace( $blog_url, $cdn_url, $asset[0] );
        }

        // check if not a relative path
        if ( ! $this->relative || strstr( $asset[0], $blog_url ) ) {
            return str_replace( $subst_urls, $cdn_url, $asset[0] );
        }

        // relative URL
        return $cdn_url . $asset[0];
    }

    /**
     * image transformations
     *
     * @since 0.5.0
     *
     * @return string updated filter
     */
    protected function image_tranformations() {
        $tf = '/';

        // if image auto-webp is ON
        if ( $this->webp ) {
            $tf .= 'f=auto';
        }

        // ONLY activate Image Resize on custom domain
        if ( Statically::is_custom_domain() ) {

            // if image width is ON
            if ( $this->width ) {
                $tf .= '%2Cw=' . $this->width;
            }

            // if image height is ON
            if ( $this->height ) {
                $tf .= '%2Ch=' . $this->height;
            }

        }

        // if image quality is ON
        if ( $this->quality ) {
            $tf .= '%2Cq=' . $this->quality;
        }

        // if everything are set except webp
        if ( 0 === $this->webp && (
                $this->width ||
                $this->height ||
                $this->quality
            ) ) {
            $tf = substr( $tf, strpos( $tf, '%2C' ) + 3 );
            $tf = '/' . $tf;
        }

        return $tf;
    }

    /**
     * get directory scope
     *
     * @since 0.0.1
     *
     * @return string directory scope
     */
    protected function get_dir_scope() {
        $input = explode( ',', $this->dirs );

        // default
        if ( $this->dirs == '' || count( $input ) < 1 ) {
            return 'wp\-content|wp\-includes';
        }

        return implode( '|', array_map( 'quotemeta', array_map( 'trim', $input ) ) );
    }

    /**
     * add DNS prefetch meta
     * 
     * @since 0.4.1
     */
    public function dns_prefetch() {
        // meta for custom domain
        if ( Statically::is_custom_domain() ) {
            $domain = parse_url( $this->cdn_url, PHP_URL_HOST );
            $dns = '<link rel="dns-prefetch" href="//' . $domain . '" />' . "\n";
            echo $dns;
        }
    }

    /**
     * rewrite url
     *
     * @since 0.0.1
     *
     * @param string $html current raw HTML doc
     * @return string updated HTML doc with CDN links
     */
    public function rewrite( $html ) {
        // check if HTTPS and use CDN over HTTPS enabled
        if ( ! $this->https && isset( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == 'on' ) {
            return $html;
        }

        // get dir scope in regex format
        $dirs = $this->get_dir_scope();
        $blog_url = $this->https
            ? '(https?:|)'.$this->relative_url( quotemeta( $this->blog_url ) )
            : '(http:|)'.$this->relative_url( quotemeta( $this->blog_url ) );

        // regex rule start
        $regex_rule = '#(?<=[(\"\'])';

        // check if relative paths
        if ( $this->relative ) {
            $regex_rule .= '(?:'.$blog_url.')?';
        } else {
            $regex_rule .= $blog_url;
        }

        // regex rule end
        $regex_rule .= '/(?:((?:'.$dirs.')[^\"\')]+)|([^/\"\']+\.[^/\"\')]+))(?=[\"\')])#';

        // call the cdn rewriter callback
        $cdn_html = preg_replace_callback( $regex_rule, [$this, 'rewrite_url'], $html );

        return $cdn_html;
    }
}
