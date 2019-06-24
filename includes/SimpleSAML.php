<?php

namespace RRZE\WebSSO;

defined('ABSPATH') || exit;

use RRZE\WebSSO\Options;
use SimpleSAML_Auth_Simple;
use WP_Error;

class SimpleSAML
{
    /**
     * [load description]
     * @return [type] [description]
     */
    public static function load() {
        $options = Options::getOptions();
        if(file_exists(WP_CONTENT_DIR . $options->simplesaml_include)) {
            require_once(WP_CONTENT_DIR . $options->simplesaml_include);
            return new SimpleSAML_Auth_Simple($options->simplesaml_auth_source);
        } else {
            return new WP_Error('simplesaml_could_not_be_loaded', __('The simpleSAML library could not be loaded.', 'fau-websso'));
        }
    }
}
