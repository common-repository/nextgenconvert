<?php

namespace NextGenConvert;

require_once 'Stats.php';
require_once 'Config.php';
require_once 'API.php';

use NextGenConvert\Stats;
use NextGenConvert\Config;
use NextGenConvert\API;

/**
 * Class AdminArea
 * Provides functionality for the WordPress admin area.
 */
class AdminArea
{
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new AdminArea();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        $this->register_ajax_handlers();
    }

    private function register_ajax_handlers()
    {
        add_action('wp_ajax_get_conversion_stats', [$this, 'ajax_get_conversion_stats']);
        add_action('wp_ajax_get_access_stats', [$this, 'ajax_get_access_stats']);
        add_action('wp_ajax_get_cache_stats', [$this, 'ajax_get_cache_stats']);
        add_action('wp_ajax_get_gallery_stats', [$this, 'ajax_get_gallery_stats']);
        add_action('wp_ajax_convert', [$this, 'ajax_get_convert']);
        add_action('wp_ajax_deleteAll', [$this, 'ajax_get_deleteAll']);
    }

    public function ajax_get_gallery_stats()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized: You do not have the necessary permissions to view gallery stats.']);
            return; // Exit the function to prevent further execution
        }
        wp_send_json(Stats::get_gallery_stats());
    }
    public function ajax_get_conversion_stats()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized: You do not have the necessary permissions to view conversion stats.']);
            return; // Exit the function to prevent further execution
        }
        wp_send_json(Stats::get_conversion_stats());
    }
    public function ajax_get_access_stats()
    {
        // wp_send_json(Stats::get_access_stats());
    }
    public function ajax_get_cache_stats()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized: You do not have the necessary permissions to view cache stats.']);
            return; // Exit the function to prevent further execution
        }
        wp_send_json(Stats::get_cache_stats());
    }
  

    public function add_admin_menu()
    {
        //restrict this to just users that can manage options
        if (current_user_can('manage_options')) {
            add_menu_page(
                'NextGenConvert Settings',
                'NextGenConvert',
                'manage_options',
                'nextgenconvert',
                [$this, 'display_settings_page']
            );
        }
    }

    public function enqueue_admin_styles($hook)
    {
        if (is_admin() && $hook == 'toplevel_page_nextgenconvert') {
            wp_enqueue_style('nextgenconvert-admin-styles', plugin_dir_url(__FILE__) . 'admin-styles.css', null, gmdate("Y-m-d H:i:s"));
            wp_enqueue_style('data-tables-css', plugin_dir_url(__FILE__) . 'jquery.dataTables.min.css', [], '1.13.7');

        }
    }

    public function enqueue_admin_scripts($hook)
    {
        if (is_admin() && $hook == 'toplevel_page_nextgenconvert') {
            wp_enqueue_script('nextgenconvert-admin-scripts', plugin_dir_url(__FILE__) . 'admin-scripts.js', ['jquery'], gmdate("Y-m-d H:i:s"), true);

            wp_enqueue_script('jquery');
            wp_enqueue_script('data-tables', plugin_dir_url(__FILE__) . 'jquery.dataTables.min.js', ['jquery'], '1.13.7', true);

            // Localize the script with new data
            $translation_array = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                // Create nonces for your AJAX actions
                'convert_nonce' => wp_create_nonce('nextgenconvert_convert_action'),
                'delete_nonce' => wp_create_nonce('nextgenconvert_delete_action'),
                // Any additional data you might need
            );
            wp_localize_script('nextgenconvert-admin-scripts', 'nextgenconvert_params', $translation_array);
            
        }
    }

    public function ajax_get_convert()
    {
        check_ajax_referer('nextgenconvert_convert_action', 'nonce');


        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You are not authorized to perform this convert action.']);
            return; // Stop execution for unauthorized users
        }

        $nextGenAPI = new API();
        
        $image_url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        if(!empty($image_url)){
            $quality = Config::get_quality(); // Retrieve the quality setting

            $response = $nextGenAPI->convert_to_webp($image_url, $quality);
    
         
            // Check the status of the response
            if ($response['status'] === 'error') {
                // Send a JSON error response with the error message and include HTTP status code
                wp_send_json_error([
                    'debug' => 'status_error',
                    'message' => $response['error'],
                    'debug_code' =>  $response['code'],
                    'code' => $response['code'] ?? 0 // Include the HTTP status code if available
                ]);
            } else if ($response['status'] === 'success') {
                // Send a JSON success response with the image URL
                wp_send_json_success(['image_url' => $response['path']]);
            } else {
                // Fallback error handling
                wp_send_json_error(['message' => 'Unexpected error occurred.']);
            }
        }else {
            // Fallback error handling
            wp_send_json_error(['message' => 'Error: Image Empty']);
        }
     
    }

    public function ajax_get_deleteAll()
    {
        check_ajax_referer('nextgenconvert_delete_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You are not authorized to perform this delete action.']);
            return; // Stop execution for unauthorized users
        }

       // Function to recursively delete a directory and its contents
        // function delete_directory($dir) {
        //     if (!file_exists($dir)) {
        //         return true;
        //     }
        //     if (!is_dir($dir)) {
        //         return wp_delete_file($dir);
        //     }
        //     foreach (scandir($dir) as $item) {
        //         if ($item == '.' || $item == '..') {
        //             continue;
        //         }
        //         if (!delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
        //             return false;
        //         }
        //     }
        //     return rmdir($dir);
        // }

        function delete_directory_wpfs($dir) {
            global $wp_filesystem;
        
            // Initialize the WordPress filesystem, if not already.
            if (empty($wp_filesystem)) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                WP_Filesystem();
            }
        
            if (!$wp_filesystem->exists($dir)) {
                return true;
            }
        
            if (!$wp_filesystem->is_dir($dir)) {
                return $wp_filesystem->delete($dir);
            }
        
            $filelist = $wp_filesystem->dirlist($dir);
        
            foreach ($filelist as $filename => $filedata) {
                if (!$wp_filesystem->delete($dir . '/' . $filename, true)) {
                    return false;
                }
            }
        
            return $wp_filesystem->rmdir($dir);
        }

        // Get the uploads directory
        $upload_dir = wp_upload_dir();
        $uploads_path = $upload_dir['basedir'];

        // Get the directory path of the parent directory of 'uploads'
        $parent_dir = dirname($uploads_path);

        // Define the path of the 'nextgen-uploads' directory
        $nextgen_uploads_dir = $parent_dir . '/nextgen-uploads';

        // Delete the 'nextgen-uploads' directory and everything in it
        if(delete_directory_wpfs($nextgen_uploads_dir)) {
            wp_send_json_success(['message' => 'The directory and its contents were deleted successfully.']);
        } else {
            wp_send_json_error(['message' => 'There was an error deleting the nextgen-uploads directory.']);
        }
    }




    public function display_settings_page()
    {
        // Handling POST request for API Key
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Verify user capabilities before processing form data
            if (current_user_can('manage_options')) {

                if (isset($_POST['nextgenconvert_api_key'])) {

                    if (!wp_verify_nonce($_POST['nextgenconvert_api_key_nonce'], 'nextgenconvert_save_api_key')) {
                        // Nonce verification failed - handle the error, possibly a security issue.
                        die('Nonce verification failed');
                    }

                    $api_key = isset($_POST['nextgenconvert_api_key']) ? sanitize_text_field($_POST['nextgenconvert_api_key']) : '';

                    if (!empty($api_key)) {
                        Config::set_api_key($api_key);
                    }
                }

                // Handling POST request for Quality
                if (isset($_POST['nextgenconvert_quality'])) {
                    
                    if (!wp_verify_nonce($_POST['nextgenconvert_quality_nonce'], 'nextgenconvert_save_quality')) {
                        // Nonce verification failed - handle the error, possibly a security issue.
                        die('Nonce verification failed');
                    }

                    $quality = isset($_POST['nextgenconvert_quality']) ? intval($_POST['nextgenconvert_quality']) : '';  

                    if (!empty($quality) && is_numeric($quality)) {
                        Config::set_quality($quality);
                    }
                }
            }else {
                // handle unauthorized access attempt
                wp_die('You do not have sufficient permissions to access this page.');
            }
        }

        $api_key = esc_attr(Config::get_api_key());
        $quality = esc_attr(Config::get_quality());
?>
        <div id="next">
          
            <div class="card-item">
                <header>
                    <h1><a href="https://nextgenconvert.com/" target="_blank">NextGenConvert.com</a> Integration</h1>
                    
                    <div class="col-flex">
                        <div class="col">
                            <h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><!--!Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M96 0C78.3 0 64 14.3 64 32v96h64V32c0-17.7-14.3-32-32-32zM288 0c-17.7 0-32 14.3-32 32v96h64V32c0-17.7-14.3-32-32-32zM32 160c-17.7 0-32 14.3-32 32s14.3 32 32 32v32c0 77.4 55 142 128 156.8V480c0 17.7 14.3 32 32 32s32-14.3 32-32V412.8C297 398 352 333.4 352 256V224c17.7 0 32-14.3 32-32s-14.3-32-32-32H32z"/></svg> Install</h3>
                            <p class="lead">A simple setup process to integrate the powerful tool with your WordPress site.</p>
                            </div>
                        <div class="col">
                            <h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M288 32c0-17.7-14.3-32-32-32s-32 14.3-32 32V274.7l-73.4-73.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l128 128c12.5 12.5 32.8 12.5 45.3 0l128-128c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L288 274.7V32zM64 352c-35.3 0-64 28.7-64 64v32c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V416c0-35.3-28.7-64-64-64H346.5l-45.3 45.3c-25 25-65.5 25-90.5 0L165.5 352H64zm368 56a24 24 0 1 1 0 48 24 24 0 1 1 0-48z"/></svg> Convert</h3>
                            <p class="lead">Effortlessly transform your images into a more efficient format with just one click.</p>
                        </div>
                        <div class="col">
                            <h3><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M64 64c0-17.7-14.3-32-32-32S0 46.3 0 64V400c0 44.2 35.8 80 80 80H480c17.7 0 32-14.3 32-32s-14.3-32-32-32H80c-8.8 0-16-7.2-16-16V64zm406.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L320 210.7l-57.4-57.4c-12.5-12.5-32.8-12.5-45.3 0l-112 112c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L240 221.3l57.4 57.4c12.5 12.5 32.8 12.5 45.3 0l128-128z"/></svg> Perform</h3>
                            <p class="lead">Enjoy the results as your website images loads faster, providing a better experience for your visitors.</p>
                        </div>
                    </div>

<hr/>
                    <p>NextGenConvert is a tool that helps you transform your website's images into the Next-Gen format, primarily WebP. This format makes your images smaller in size while maintaining quality, ensuring your website images loads faster, improving your visitors' experience. This WordPress plugin enables you to use nextgenconvert.com automaticly in your WordPress website in a plug-and-play way, making it straightforward and hassle-free to optimize your images directly within your WordPress dashboard. </p>
                </header>
                <section>
                    <h2>Your API Key</h2>
                    <p>
                    This unique key is like a password that allows you to use our service. If you don't have one yet you can <a href="https://nextgenconvert.com/" target="_blank">purchase a subscription</a> to get started.  
                    </p>
                    <form id="nextgenconvert_api_key_form" method="POST">
                        <input type="text" id="nextgenconvert_api_key" placeholder="API Key" name="nextgenconvert_api_key" value="<?php echo esc_attr($api_key); ?>" />
                        <?php wp_nonce_field('nextgenconvert_save_api_key', 'nextgenconvert_api_key_nonce'); ?>

                        <button type="submit">Save</button>
                    </form> 
                </section>

                <section>
                    <h2>Conversion Quality</h2>
                    <p>Set this to '80' (or any number between 1 and 100) to balance between the quality of the image and the file size. A higher number means better quality but a larger file. This value only applies to image conversions in the future.</p>
                    <form id="nextgenconvert_quality_form" method="POST">
                        <input type="number" id="nextgenconvert_quality" placeholder="Quality (1-100)" name="nextgenconvert_quality" value="<?php echo esc_attr($quality); ?>" min="1" max="100" />
                        <?php wp_nonce_field('nextgenconvert_save_quality', 'nextgenconvert_quality_nonce'); ?>
                        <button type="submit">Save</button>
                    </form>
                </section>
                <section>                     
                    <h2>Convert All</h2>
                    <p>Convert all remaining eligible images to webp format. Keep this page open during the conversion process to ensure it completes successfully.</p>
                    <button class="button button-primary "  id="convert-all-remaining" disabled type="button">Converter Loading ...</button>
                    <button class="button button-primary"  id="delete-all" type="button">Delete All</button>
                    <div id="progress-container" style="display:none;">
                        <div id="progress-bar" style="width:0%;"></div>
                    </div>
                    <div class="notification-area" style="display: none;"></div>
                </section>
                <section> 
                  <h2>Stats at a Glance</h2>
                  <div id="statistics"><span class="loading">Loading ...</span></div>
                  </section>
                <section>
                    <h2>Your Images</h2>
                    <p>This table lists all your images. You can see the name, date of upload, and have the option to 'Reconvert' if needed.</p>
                    <div class="notification-area" style="display: none;"></div>
                    <table id="sortable-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Date</th>
                                <th>Image</th>
                                <th style="min-width: 210px;">Convert</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </section>
            </div>
        </div>
<?php
    }
}
