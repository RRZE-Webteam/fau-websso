<?php

/*
Plugin Name:     FAU WebSSO
Plugin URI:      https://github.com/RRZE-Webteam/fau-websso
Description:     Registration for centrally assigned identifiers of students and employees.
Version:         6.5.0
Author:          RRZE Webteam
Author URI:      https://blogs.fau.de/webworking/
License:         GNU General Public License v2
License URI:     http://www.gnu.org/licenses/gpl-2.0.html
Domain Path:     /languages
Text Domain:     fau-websso
Network:         true
*/

namespace RRZE\WebSSO;

use RRZE\WebSSO\Main;

defined('ABSPATH') || exit;

const RRZE_PHP_VERSION = '7.3';
const RRZE_WP_VERSION = '5.3';

spl_autoload_register(function ($class) {
    $prefix = __NAMESPACE__;
    $base_dir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

register_activation_hook(__FILE__, __NAMESPACE__ . '\activation');

add_action('plugins_loaded', __NAMESPACE__ . '\loaded');

/**
 * Loads languages files into the list of text domains.
 * @return void
 */
function load_textdomain()
{
    load_plugin_textdomain('fau-websso', false, sprintf('%s/languages/', dirname(plugin_basename(__FILE__))));
}

/**
 * System requirements.
 * @return string The error text.
 */
function systemRequirements()
{
    $error = '';
    if (version_compare(PHP_VERSION, RRZE_PHP_VERSION, '<')) {
        /* Translator: 1: current PHP version, 2: required PHP version */
        $error = sprintf(__('The server is running PHP version %1$s. The Plugin requires at least PHP version %2$s.', 'fau-websso'), PHP_VERSION, RRZE_PHP_VERSION);
    } elseif (version_compare($GLOBALS['wp_version'], RRZE_WP_VERSION, '<')) {
        /* Translator: 1: current WP version, 2: required WP version */
        $error = sprintf(__('The server is running WordPress version %1$s. The Plugin requires at least WordPress version %2$s.', 'fau-websso'), $GLOBALS['wp_version'], RRZE_WP_VERSION);
    }
    return $error;
}

/**
 * Runs when the plugin is registered.
 * @return void
 */
function activation()
{
    load_textdomain();

    if ($error = systemRequirements()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die($error);
    }
}

/**
 * Runs when the plugin has been loaded.
 * @return void
 */
function loaded()
{
    load_textdomain();

    if ($error = systemRequirements()) {
        add_action('admin_init', function () use ($error) {
            $pluginData = get_plugin_data(__FILE__);
            $pluginName = $pluginData['Name'];
            $tag = is_plugin_active_for_network(plugin_basename(__FILE__)) ? 'network_admin_notices' : 'admin_notices';
            add_action($tag, function () use ($pluginName, $error) {
                printf('<div class="notice notice-error"><p>' . __('Plugins: %1$s: %2$s', 'fau-websso') . '</p></div>', esc_html($pluginName), esc_html($error));
            });
        });
        return;
    }

    $main = new Main(__FILE__);
    $main->onLoaded();
}
