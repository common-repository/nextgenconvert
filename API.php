<?php

namespace NextGenConvert;

use NextGenConvert\Config;

/**
 * Class NextGenAPI
 *
 * Provides functionality for interacting with the NextGen Convert API.
 */
class API
{
    const API_BASE_URL = 'https://api.nextgenconvert.com';
    const API_CONVERT_URL = self::API_BASE_URL.'/api/v1.0/convert';
    const API_CONVERTED_URL = self::API_BASE_URL.'/converted';

   /**
     * Send a request to the API.
     *
     * @param array $params
     * @return array
     */
    public function send_request($params)
    {
        $api_key = Config::get_api_key();
        if (empty($api_key)) {
            error_log('No API key configured for NextGen Convert.', 0, __DIR__);
            return [
                'response' => null,
                'code' => 0,
                'error' => 'No API key configured'
            ];
        }

        $params['auth'] = $api_key;

        $response = wp_remote_post(self::API_CONVERT_URL, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($params)
        ]);
        
        
        
        // Retrieve the HTTP status code
        $http_code = wp_remote_retrieve_response_code($response);


        if (is_wp_error($response)) {
            error_log($response->get_error_message(), 0, __DIR__);
            return [
                'response' => null,
                'code' => $http_code,
                'error' => $response->get_error_message()
            ];
        } 

        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);

        if (null === $decoded_response) {
            error_log('Unexpected response format from NextGen Convert API.', 0, __DIR__);
            return [
                'response' => null,
                'code' => $http_code,
                'error' => 'Unexpected response format'
            ];
        }

       
        return [
            'response' => $decoded_response,
            'code' => $http_code,
            'error' => null
        ];
    }

    public function convert_to_webp($image_url, $quality = 65)
    {
        $params = [
            'url'     => $image_url,
            'quality' => $quality
        ];

        $apiResponse = $this->send_request($params);

        // Check if there was an error in the API request
        if (!empty($apiResponse['error'])) {
            return [
                'status' => 'error',
                'error' => $apiResponse['error'],
                'code' => $apiResponse['code'] // Pass through the HTTP status code
            ];
        }
       
        $response = $apiResponse['response'];

        // Check if the response indicates an error
        if (isset($response['status']) && $response['status'] == "error") {
            return [
                'status' => 'error',
                'error' => $response['errors'] ?? 'An unknown error occurred',
                'code' => $apiResponse['code'] // Pass through the HTTP status code
            ];
        }

        // Handle the successful conversion
        if (isset($response['convert_path'])) {
            $returnedURL = self::API_CONVERTED_URL .'/'. $response['convert_path'];
            $finalPath = $this->SaveImage($image_url, $returnedURL);
            return [
                'status' => 'success',
                'path' => $finalPath
            ];
        } else {
            error_log('Unexpected response format from NextGen Convert API.', 0, __DIR__);
            return [
                'status' => 'error',
                'error' => 'Unexpected response format from API',
                'code' => $apiResponse['code'] // Pass through the HTTP status code
            ];
        }
    }

    /**
     * Save an image from a given URL.
     *
     * @param string $image_url
     * @param string $nextGenURL
     * @return string|null
     */
    public function SaveImage($image_url, $nextGenURL)
    {
        $api_key = Config::get_api_key();
        if (empty($api_key)) {
            error_log('No API key configured for NextGen Convert.', 0, __DIR__);
            return null;
        }
        $nextGenURL_with_auth = add_query_arg('auth', $api_key, $nextGenURL);
       
        $response = wp_remote_get($nextGenURL_with_auth);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return $error_message;
        }

        $image_data = wp_remote_retrieve_body($response);

        $wp_content_dir = WP_CONTENT_DIR;
        $nextgen_uploads_dir = $wp_content_dir . '/nextgen-uploads';

        $uploads_and_after = strstr($image_url, 'uploads');
        $after_uploads = substr($uploads_and_after, strlen('uploads/'));
        $directory_path = dirname($after_uploads);
        $nextgen_uploads_dir .= DIRECTORY_SEPARATOR . $directory_path;

        // if (!is_dir($nextgen_uploads_dir)) {
        //     mkdir($nextgen_uploads_dir, 0755, true);
        // }
        if (!is_dir($nextgen_uploads_dir)) {
            wp_mkdir_p($nextgen_uploads_dir);
        }

        $file_info = pathinfo($image_url);
        $new_file_name = $file_info['filename'] . '.webp';
        $file_path = $nextgen_uploads_dir . '/' . $new_file_name;

        // $success = file_put_contents($file_path, $image_data);

        global $wp_filesystem;

        // Initialize the WordPress filesystem, if not already.
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }

        $success = $wp_filesystem->put_contents($file_path, $image_data, FS_CHMOD_FILE);



        if (!$success) {
            return null;
        }

        return $file_path;
    }
}