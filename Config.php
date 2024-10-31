<?php
namespace NextGenConvert;

/**
 * Class Config
 *
 * Handles plugin configuration.
 */
class Config
{
    private static $api_key;
    private static $quality;

    // Getter for API Key
    public static function get_api_key()
    {
        if (empty(self::$api_key)) {
            self::$api_key = get_option('nextgenconvert_api_key');
        }
        return self::$api_key;
    }

    // Setter for API Key
    public static function set_api_key($api_key)
    {
        self::$api_key = $api_key;
        update_option('nextgenconvert_api_key', $api_key);
    }

    // Getter for Quality
    public static function get_quality()
    {
        if (empty(self::$quality)) {
            self::$quality = get_option('nextgenconvert_quality', '75');   
        }
        return self::$quality;
    }

    // Setter for Quality
    public static function set_quality($quality)
    {
        self::$quality = $quality;
        update_option('nextgenconvert_quality', $quality);
    }

    
}