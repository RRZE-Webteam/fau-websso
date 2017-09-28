<?php

namespace RRZE\WebSSO\Core;

defined('ABSPATH') || exit;

class Options {
    
    protected $option_name = '_fau_websso';
    
    protected $default_options;
    
    public function __construct() {
        $this->default_options = $this->default_options();
    }
    
    /*
     * Standard Einstellungen werden definiert
     * @return array
     */
    private function default_options() {
        $options = array(
            'simplesaml_include' => '/simplesamlphp/lib/_autoload.php',
            'simplesaml_auth_source' => 'default-sp',
            'force_websso' => 2,
            'simplesaml_url_scheme' => 'https'
        );

        return $options;
    }
    
    /*
     * Gibt die Einstellungen zurück.
     * @return object
     */
    public function get_options() {
        if (is_multisite()) {
            $options = (array) get_site_option($this->option_name);
        } else {
            $options = (array) get_option($this->option_name);
        }
        
        $options = wp_parse_args($options, $this->default_options);
        $options = array_intersect_key($options, $this->default_options);

        return (object) $options;
    }
    
    public function get_option_name() {
        return $this->option_name;
    }  
     
}
