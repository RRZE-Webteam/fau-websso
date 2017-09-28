<?php

/**
 * Plugin Name:     FAU WebSSO
 * Plugin URI:      https://github.com/RRZE-Webteam/fau-websso
 * Description:     Anmeldung für zentral vergebene Kennungen von Studierenden und Beschäftigten.
 * Version:         6.0.1
 * Author:          RRZE-Webteam
 * Author URI:      https://blogs.fau.de/webworking/
 * License:         GNU General Public License v2
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path:     /languages
 * Text Domain:     fau-websso
 * Network:         TRUE
 */

namespace RRZE\WebSSO;

use RRZE\WebSSO\Main;

defined('ABSPATH') || exit;

const RRZE_PHP_VERSION = '5.5';
const RRZE_WP_VERSION = '4.8';

register_activation_hook(__FILE__, 'RRZE\WebSSO\activation');

add_action('plugins_loaded', 'RRZE\WebSSO\loaded');

/*
 * Einbindung der Sprachdateien.
 * @return void
 */
function load_textdomain() {
    load_plugin_textdomain('fau-websso', FALSE, sprintf('%s/languages/', dirname(plugin_basename(__FILE__))));
}

/*
* Wird durchgeführt, nachdem das Plugin aktiviert wurde.
* @return void
*/
function activation() {
    // Sprachdateien werden eingebunden.
    load_textdomain();

    // Überprüft die minimal erforderliche PHP- u. WP-Version.
    system_requirements();  
 }
 
 /*
  * Überprüft die minimal erforderliche PHP- u. WP-Version.
  * @return void
  */
function system_requirements() {
    $error = '';

    if (version_compare(PHP_VERSION, RRZE_PHP_VERSION, '<')) {
        $error = sprintf(__('Your server is running PHP version %s. Please upgrade at least to PHP version %s.', 'fau-websso'), PHP_VERSION, RRZE_PHP_VERSION);
    }

    if (version_compare($GLOBALS['wp_version'], RRZE_WP_VERSION, '<')) {
        $error = sprintf(__('Your Wordpress version is %s. Please upgrade at least to Wordpress version %s.', 'fau-websso'), $GLOBALS['wp_version'], RRZE_WP_VERSION);
    }

    // Wenn die Überprüfung fehlschlägt, dann wird das Plugin automatisch deaktiviert.
    if (!empty($error)) {
        deactivate_plugins(plugin_basename(__FILE__), FALSE, TRUE);
        wp_die($error);
    }
 }
 
/*
* Wird durchgeführt, nachdem das WP-Grundsystem hochgefahren
* und alle Plugins eingebunden wurden.
* @return void
*/
function loaded() {
    // Sprachdateien werden eingebunden.
    load_textdomain();
    
    // Automatische Laden von Klassen.
    autoload();
}

/*
 * Automatische Laden von Klassen.
 * @return void
 */
function autoload() {
    require 'autoload.php';    
    $main = new Main(plugin_basename(__FILE__));
}
