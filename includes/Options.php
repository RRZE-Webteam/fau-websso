<?php

namespace RRZE\WebSSO;

defined('ABSPATH') || exit;

class Options
{
    /**
     * Option name
     * @var string
     */
    protected static $optionName = '_fau_websso';

    /**
     * Default options
     * @return array
     */
    protected static function defaultOptions()
    {
        $options = [
            'simplesaml_include' => '/simplesamlphp/lib/_autoload.php',
            'simplesaml_auth_source' => 'default-sp',
            'force_websso' => 0,
            'simplesaml_url_scheme' => 'https',
            'allowed_user_email_domains' => [],
            'send_new_user_password' => 0
        ];

        return $options;
    }

    /**
     * Returns the options.
     * @return object
     */
    public static function getOptions()
    {
        $defaults = self::defaultOptions();

        if (is_multisite()) {
            $options = (array) get_site_option(self::$optionName);
        } else {
            $options = (array) get_option(self::$optionName);
        }

        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return (object) $options;
    }

    /**
     * Returns the name of the option.
     * @return string
     */
    public static function getOptionName()
    {
        return self::$optionName;
    }
}
