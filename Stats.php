<?php

namespace NextGenConvert;

class Stats
{

    public static function get_conversion_stats()
    {
        $original_dir = wp_upload_dir()['basedir'] . '/';
        $webp_dir = str_replace('/uploads/', '/nextgen-uploads/', $original_dir);

        $original_size = self::get_directory_size($original_dir, ['jpg', 'jpeg', 'png']);
        $original_images = self::get_file_info($original_dir, ['jpg', 'jpeg', 'png']);
        $original_images_count = $original_images['count'];

        if (!is_dir($webp_dir)) {
            $webp_size = $webp_images_count = $bytes_saved = $percentage_saved = 0;
        } else {
            $webp_size = self::get_directory_size($webp_dir, ['webp']);

            $webp_images = self::get_file_info($webp_dir, ['webp']);
            $webp_images_count = $webp_images['count'];

            $bytes_saved = $original_size > 0 ? $original_size - $webp_size : 0;
            $percentage_saved = $original_size > 0 ? ($bytes_saved / $original_size) * 100 : 0;
        }

            $leftToConvert=[];
         foreach ($original_images['paths'] as $path) {
            $webp_path = str_replace('/uploads/', '/nextgen-uploads/', $path);
            $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $webp_path);
            if (!in_array($webp_path,$webp_images['paths'])){
                $leftToConvert[] = $path;   
            }
        }

        return [
            'original_dir' => $original_dir,
            'webp_dir' => $webp_dir,
            'original_size' => $original_size,
            'webp_size' => $webp_size,
            'bytes_saved' => $bytes_saved,
            'percentage_saved' => $percentage_saved,
            'original_images_count' => $original_images_count,
            'webp_images_count' => $webp_images_count,
            'percentage_files_converted' => $original_images_count > 0 ? ($webp_images_count / $original_images_count) * 100 : 0,
            'remaining_files_to_convert' => $original_images_count - $webp_images_count,
            // 'webp_images'=> $webp_images,
            // 'original_images'=> $original_images,
            'left_to_convert' => $leftToConvert

        ];
    }


    private static function get_file_info($dir, $extensions = [])
    {
        $filePaths = [];
        $count = 0;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $file) {
            if ($file->isFile() && in_array(pathinfo($file, PATHINFO_EXTENSION), $extensions)) {
                $count++;
                $filePaths[] = $file->getRealPath();
            }
        }

        return ['count' => $count, 'paths' => $filePaths];
    }

 


    public static function get_gallery_stats() {
        
        function get_upload_date_by_image_id($image_id) {
            $attachment = get_post($image_id);

            if ($attachment) {
                $upload_datetime = $attachment->post_date; // Get the upload date and time
                return $upload_datetime;
            }
            return false; // Return false when image not found or metadata not available.
        }

       /**
        * Generates a WebP URL from the original image URL.
        *
        * @param string $original_url The original image URL.
        * @return string|null The WebP image URL, or null if a WebP counterpart does not exist.
        */
         function get_webp_url_from_path($path)
       {
   
           // Parse the original URL to get the path
           $parsed_url = wp_parse_url($path);
   
           // Replace 'uploads' with 'nextgen-uploads' and the file extension with '.webp'
           $webp_path = str_replace('/uploads/', '/nextgen-uploads/', $parsed_url['path']);
           $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $webp_path);
           $webp_url =  $parsed_url['host'] . $webp_path;
   
   
          
           // Check if the WebP image exists for the given WebP URL
           if ( has_webp_counterpart($webp_url)  !== false) {
               return $webp_url;
           }
   
           // Return null if a WebP counterpart does not exist
           return false;
       }

       function get_webp_url_from_webp_path($path = false){

        if(!$path) return false;

            $url = str_replace(
                wp_normalize_path( untrailingslashit( ABSPATH ) ),
                site_url(),
                wp_normalize_path( $path )
            );

              // Check if the WebP image exists for the given WebP URL
            if ( has_webp_counterpart($path) !== false) {
                return esc_url_raw( $url );
            }
                
            
       }


        function has_webp_counterpart($webp_path)
        {
       
            // // Check the cache first (optional)
            // $cache_key = 'webp_exists_' . md5($webp_path);
            // $webp_exists = get_transient($cache_key);

            // if ($webp_exists !== false) {
            //     return $webp_exists === '1';
            // }

            // $server_path = $_SERVER['DOCUMENT_ROOT'] . parse_url($webp_path, PHP_URL_PATH);



            // Check the filesystem
            $webp_exists_on_disk = @file_exists($webp_path);
           
            if ($webp_exists_on_disk === false) {
                error_log('Error no file existence: ' . $webp_path . PHP_EOL,3,__DIR__.'/error.log');
                return false;
            }


            // // Update the cache (optional)
            // set_transient($cache_key, $webp_exists_on_disk ? '1' : '0', DAY_IN_SECONDS);

            return $webp_exists_on_disk;
        }
    
        $args = array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,  // Be cautious; this could be a performance bottleneck
        );

        $query_images = new \WP_Query($args);

        $images = array();
        foreach ($query_images->posts as $image) {
            $metadata = wp_get_attachment_metadata($image->ID);
            $sizes = isset($metadata['sizes']) ? $metadata['sizes'] : array();

            $size_data = array();
            foreach ($sizes as $size => $details) {
                $img_url = wp_get_attachment_image_src($image->ID, $size)[0];
                $img_path = $_SERVER['DOCUMENT_ROOT'] . wp_parse_url($img_url, PHP_URL_PATH);
                $webp_path =  get_webp_url_from_path($img_path);
                $webp_url = get_webp_url_from_webp_path($webp_path);
                $size_data[$size] = array(
                    'url'  => $img_url,
                    'path' => $img_path,
                    'webp_path' =>  $webp_path,
                    'webp_url' => $webp_url
                );
            }

            $webp_path =  get_webp_url_from_path(get_attached_file($image->ID));
            $webp_url = get_webp_url_from_webp_path($webp_path);

            $images[] = array(
                'ID'        => $image->ID,
                'name'      => $image->post_title,
                'path'      => get_attached_file($image->ID),
                
                'url'       => wp_get_attachment_url($image->ID),
                'webp_path'      => $webp_path,
                'webp_url'      => $webp_url,
                'sizes'     => $size_data,  // Adding sizes information with WebP check
                'date'      => get_upload_date_by_image_id($image->ID)
            );
        }

    


        return $images;  // Return the array instead of printing
   
    }




 
    public static function get_cache_stats() {
        global $wpdb;
        $transients = $wpdb->get_results("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_webp_exists_%'", ARRAY_A);
        $caches = [];
        foreach ($transients as $transient) {
            $key = str_replace(['_transient_webp_exists_', '_transient_timeout_'], '', $transient['option_name']);
            $caches[$key] = get_transient($key);
        }
        return [
            'transient_count' => count($caches),
             
        ];
    }
    
    private static function get_directory_size($dir, $extensions = []) {
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $file) {
            if (in_array(pathinfo($file, PATHINFO_EXTENSION), $extensions)) {
                $size += $file->getSize();
            }
        }
        return $size;
    }
}

