<?php

namespace RRZE\WebSSO;

defined('ABSPATH') || exit;

use RRZE\WebSSO\Options;
use SimpleSAML\Auth\Simple as SimpleSAMLAuthSimple;
use WP_Error;

/**
 * [SimpleSAML description]
 */
class SimpleSAML
{
    /**
     * [protected description]
     * @var string
     */
    protected $pluginFile;

    /**
     * [protected description]
     * @var object
     */
    protected $options;

    /**
     * [__construct description]
     * @param string $pluginFile [description]
     */
    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;

        $this->options = Options::getOptions();
    }

    /**
     * [onLoaded description]
     * @return [type] [description]
     */
    public function onLoaded()
    {
        $simplesaml = $this->loadSimpleSAML();
        if (is_wp_error($simplesaml)) {
            add_action('admin_init', function () use ($simplesaml) {
                $error = $simplesaml->get_error_message();
                $pluginData = get_plugin_data($this->pluginFile);
                $pluginName = $pluginData['Name'];
                $tag = is_plugin_active_for_network(plugin_basename($this->pluginFile)) ? 'network_admin_notices' : 'admin_notices';
                add_action($tag, function () use ($pluginName, $error) {
                    printf('<div class="notice notice-error"><p>' . __('Plugins: %1$s: %2$s', 'fau-websso') . '</p></div>', esc_html($pluginName), esc_html($error));
                });
            });
            return false;
        }
        return $simplesaml;
    }

    protected function loadSimpleSAML()
    {
        if (file_exists(WP_CONTENT_DIR . $this->options->simplesaml_include)) {
            require_once(WP_CONTENT_DIR . $this->options->simplesaml_include);
            return new SimpleSAMLAuthSimple($this->options->simplesaml_auth_source);
        }
        return new WP_Error('simplesaml_could_not_be_loaded', __('The simpleSAML library could not be loaded.', 'fau-websso'));
    }
}
