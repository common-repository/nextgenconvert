<?php
/*
Plugin Name: NextGenConvert
Plugin URI: nextgenconvert.com/wordpress
Description: NextGenConvert enables WordPress to utilise NextGenConvert.com API to enhance your website's performance by efficiently converting images to the WebP format and enabling the front end to use them. This not only improves loading speeds but also significantly reduces bandwidth usage, making your site quicker and more responsive for visitors. 
Version: 1.0.4
Author: NextGenConvert.com
Author URI: nextgenconvert.com
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: nextgenconvert
*/

namespace NextGenConvert;

require_once 'AdminArea.php';
require_once 'Config.php';

use NextGenConvert\AdminArea;

/**
 * Class Integration
 *
 * Provides functionality to replace images with their WebP counterparts in post content.
 */
class Integration
{

    /**
     * Supported file extensions for conversion to WebP.
     */
    const FILE_EXTENSIONS = ['.jpg', '.jpeg', '.png'];

    /**
     * Holds the singleton instance of the class.
     *
     * @var Integration|null
     */
    private static $instance = null;

    /**
     * Returns the singleton instance of the class.
     *
     * @return Integration
     */
    public static function get_instance()
    {
        if (self::$instance == null) {
            self::$instance = new Integration();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        if (!is_admin()) {
             add_filter('the_content', [$this, 'replace_images_with_webp']);
      
            // this is a bit agressive and the filter above could be fine for wp6+ 
            // maybe have this as a toggle in the future.
            ob_start(array($this, 'replace_images_with_webp'));
        }
    }

    /**
     * Replaces image URLs with their WebP counterparts in post content.
     *
     * @param string $content The post content.
     * @return string The modified post content.
     */
    public function replace_images_with_webp($content) {
        return preg_replace_callback(
            '/<img[^>]*src=[\'"]([^\'"]+)(' . implode('|', array_map('preg_quote', self::FILE_EXTENSIONS)) . ')[\'"](?:[^>]*srcset=[\'"]([^\'"]+)[\'"])?[^>]*>/i',
            array($this, 'webp_replacer_callback'),
            $content
        );
    }

    /**
     * Callback function for preg_replace_callback() in replace_images_with_webp().
     *
     * @param array $matches The matches from the regular expression.
     * @return string The modified image tag.
     */
    public function webp_replacer_callback($matches)
    {
        $original_url = $matches[1] . $matches[2];
        $srcset = isset($matches[3]) ? $matches[3] : '';

        if (!$this->is_image_from_wordpress_domain($original_url)) {
            return $matches[0];
        }

        $webp_url = $this->get_webp_url($original_url);
        if (!$webp_url) {
            return $matches[0];
        }

        // Replace the src URL
        $img_tag_replaced = preg_replace('/' . preg_quote($original_url, '/') . '/', $webp_url, $matches[0], 1);

        // Replace URLs in the srcset attribute, if it exists
        if (!empty($srcset)) {
            $srcset_urls = explode(', ', $srcset);
            foreach ($srcset_urls as &$url) {
                list($src_url, $width) = explode(' ', $url);
                $src_webp_url = $this->get_webp_url($src_url);
                if ($src_webp_url) {
                    $url = $src_webp_url . ' ' . $width;
                }
            }
            $new_srcset = implode(', ', $srcset_urls);
            $img_tag_replaced = preg_replace('/srcset=[\'"][^\'"]+[\'"]/', 'srcset="' . $new_srcset . '"', $img_tag_replaced, 1);
        }

        return $img_tag_replaced;
    }

    /**
     * Generates a WebP URL from the original image URL.
     *
     * @param string $original_url The original image URL.
     * @return string|null The WebP image URL, or null if a WebP counterpart does not exist.
     */
    public function get_webp_url($original_url)
    {

        // Parse the original URL to get the path
        $parsed_url = wp_parse_url($original_url);

        // Replace 'uploads' with 'nextgen-uploads' and the file extension with '.webp'
        $webp_path = str_replace('/uploads/', '/nextgen-uploads/', $parsed_url['path']);
        $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $webp_path);
     
        // Build the full WebP URL
        $webp_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $webp_path;
   
        // Check if the WebP image exists for the given WebP URL
        if ($this->has_webp_counterpart($webp_url)) {
            return $webp_url;
        }

        // Return null if a WebP counterpart does not exist
        return null;
    }

    /**
     * Checks if a WebP counterpart exists for a given WebP URL.
     *
     * @param string $webp_url The WebP image URL.
     * @return bool True if a WebP counterpart exists, false otherwise.
     */
    public function has_webp_counterpart($webp_url)
    {

        // Check the cache first (optional)
        $cache_key = 'webp_exists_' . md5($webp_url);
        $webp_exists = get_transient($cache_key);

        if ($webp_exists !== false) {
            return $webp_exists === '1';
        }

        $server_path = $_SERVER['DOCUMENT_ROOT'] . wp_parse_url($webp_url, PHP_URL_PATH);

        // Check the filesystem
        $webp_exists_on_disk = @file_exists($server_path);
        if ($webp_exists_on_disk === false) {

            //this is a local debug.
            // if (defined('WP_DEBUG') && WP_DEBUG) {
            //     error_log('Error checking file existence: ' . $webp_url);
            // }

            return false;
        }


        // Update the cache (optional)
        set_transient($cache_key, $webp_exists_on_disk ? '1' : '0', DAY_IN_SECONDS);

        return $webp_exists_on_disk;
    }

    
    /**
     * Checks if an image is hosted on the same domain as the WordPress site.
     *
     * @param string $image_url The image URL.
     * @return bool True if the image is from the same domain, false otherwise.
     */
    public function is_image_from_wordpress_domain($image_url)
    {
        // Get the current WordPress site's URL
        $site_url = get_site_url();

        // Parse the image URL and site URL to get the hostnames
        $image_host = wp_parse_url($image_url, PHP_URL_HOST);
        $site_host = wp_parse_url($site_url, PHP_URL_HOST);

        // Compare the hostnames to check if they are the same
        return $image_host === $site_host;
    }

    // // /**
    // //  * Clears the transient cache for WebP image existence checks.
    // // @todo this is a future placeholder to enable better caching management but is not part of the release yet.
    // //  */
    // public function clear_cache() {
    //     global $wpdb;
    //     $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s", $wpdb->esc_like('_transient_webp_exists_') . '%'));
    // }

}

// Usage
if (is_admin()) {
   AdminArea::get_instance();
}

if (!is_admin()) {
    // Initialize the plugin
    Integration::get_instance();
}
