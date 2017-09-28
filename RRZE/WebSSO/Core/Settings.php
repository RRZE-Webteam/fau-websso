<?php

namespace RRZE\WebSSO\Core;

use RRZE\WebSSO\Main;

defined('ABSPATH') || exit;

class Settings {

    protected $main;

    protected $option_name;    
    protected $options;
    protected $menu_page = 'websso';

    public function __construct(Main $main) {
        $this->main = $main;
        $this->option_name = $this->main->option_name;
        $this->options = $this->main->options;

        add_action('admin_init', array($this, 'admin_init'));
        
        if (is_multisite()) {
            add_action('admin_init', array($this, 'network_settings_update'));
            add_action('network_admin_menu', array($this, 'network_admin_menu'));
        } else {
            add_action('admin_menu', array($this, 'admin_menu'));
        }
    }
    
    public function network_admin_menu() {
        add_submenu_page('settings.php', __("WebSSO", 'fau-websso'), __("WebSSO", 'fau-websso'), 'manage_network_options', $this->menu_page, array($this, 'network_options_page'));
    }

    public function admin_menu() {
        add_options_page(__("WebSSO", 'fau-websso'), __("WebSSO", 'fau-websso'), 'manage_options', $this->menu_page, array($this, 'options_page'));
    }

    public function network_options_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(__("WebSSO", 'fau-websso')); ?></h1>
            <?php if($this->main->simplesaml_autoload_error): ?>
            <div class="error">
                <p><?php _e($this->main->simplesaml_autoload_error, 'fau-websso'); ?></p>
            </div>
            <?php endif; ?>            
            <form method="post">
            <?php
            do_settings_sections($this->menu_page);
            settings_fields($this->menu_page);
            submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    public function options_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(__("WebSSO Settings", 'fau-websso')); ?></h1>
            <?php if($this->main->simplesaml_autoload_error): ?>
            <div class="error">
                <p><?php _e($this->main->simplesaml_autoload_error, 'fau-websso'); ?></p>
            </div>
            <?php endif; ?>
            <form method="post" action="options.php">
            <?php
            do_settings_sections($this->menu_page);
            settings_fields($this->menu_page);
            submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    public function admin_init() {
        if (!is_multisite()) {
            register_setting($this->menu_page, $this->option_name, array($this, 'options_validate'));
        }
        
        add_settings_section('websso_options_section', FALSE, array($this, 'websso_settings_section'), $this->menu_page);
        add_settings_field('force_websso', __("Allow WebSSO", 'fau-websso'), array($this, 'websso_field'), $this->menu_page, 'websso_options_section');
        
        add_settings_section('simplesaml_options_section', FALSE, array($this, 'simplesaml_settings_section'), $this->menu_page);
        add_settings_field('simplesaml_include', __("Autoload path", 'fau-websso'), array($this, 'simplesaml_include_field'), $this->menu_page, 'simplesaml_options_section');
        add_settings_field('simplesaml_auth_source', __("Authentication source", 'fau-websso'), array($this, 'simplesaml_auth_source_field'), $this->menu_page, 'simplesaml_options_section');
        add_settings_field('simplesaml_url_scheme', __("URL scheme", 'fau-websso'), array($this, 'simplesaml_url_scheme_field'), $this->menu_page, 'simplesaml_options_section');
    }

    public function websso_settings_section() {
        echo '<h3 class="title">' . __("Web Single Sign-On", 'fau-websso') . '</h3>';
        echo '<p>' . __("General WebSSO Settings.", 'fau-websso') . '</p>';
    }

    public function websso_field() {
        echo '<fieldset>';
        echo '<legend class="screen-reader-text">' . __("WebSSO Settings", 'fau-websso') . '</legend>';
        echo '<label><input name="' . $this->option_name . '[force_websso]" id="force_websso0" value="0" type="radio" ', checked($this->options->force_websso, 0), '>' . __("WebSSO has been disabled.", 'fau-websso') . '</label><br>';
        echo '<label><input name="' . $this->option_name . '[force_websso]" id="force_websso1" value="1" type="radio" ', checked($this->options->force_websso, 1), '>' . __("Users can log on locally and WebSSO.", 'fau-websso') . '</label><br>';
        echo '<label><input name="' . $this->option_name . '[force_websso]" id="force_websso2" value="2" type="radio" ', checked($this->options->force_websso, 2), '>' . __("Users may log in only via WebSSO.", 'fau-websso') . '</label><br>';
        echo '</fieldset>';
    }
    
    public function simplesaml_settings_section() {
        echo '<h3 class="title">' . __("SimpleSAMLphp", 'fau-websso') . '</h3>';
        echo '<p>' . __("Service Provider Settings.", 'fau-websso') . '</p>';
    }

    public function simplesaml_include_field() {
        echo '<input type="text" id="simplesaml_include" class="regular-text ltr" name="' . $this->option_name . '[simplesaml_include]" value="' . esc_attr($this->options->simplesaml_include) . '">';
        echo '<p class="description">' . __("Relative path starting from the wp-content directory.", 'fau-websso') . '</p>';
    }

    public function simplesaml_auth_source_field() {
        echo '<input type="text" id="simplesaml_auth_source" class="regular-text ltr" name="' . $this->option_name . '[simplesaml_auth_source]" value="' . esc_attr($this->options->simplesaml_auth_source) . '">';
    }

    public function simplesaml_url_scheme_field() {
        echo '<select name="' . $this->option_name . '[simplesaml_url_scheme]">';
        echo '<option value="https" ' . selected( $this->options->simplesaml_url_scheme, 'https' ) . '>https</option>';
        echo '<option value="http" ' . selected( $this->options->simplesaml_url_scheme, 'http' ) . '>http</option>';
        echo '</select>';        
    }
    
    public function options_validate($input) {
        $input['force_websso'] = isset($input['force_websso']) && in_array(absint($input['force_websso']), array(0, 1, 2)) ? absint($input['force_websso']) : $this->options->force_websso;
        
        $input['simplesaml_include'] = !empty($input['simplesaml_include']) ? esc_attr(trim($input['simplesaml_include'])) : $this->options->simplesaml_include;
        $input['simplesaml_auth_source'] = isset($input['simplesaml_auth_source']) ? esc_attr(trim($input['simplesaml_auth_source'])) : $this->options->simplesaml_auth_source;
        $input['simplesaml_url_scheme'] = isset($input['simplesaml_url_scheme']) && in_array(trim($input['simplesaml_url_scheme']), array('http', 'https')) ? trim($input['simplesaml_url_scheme']) : $this->options->simplesaml_url_scheme;

        return $input;
    }    

    public function network_settings_update() {
        if (!empty($_POST[$this->option_name])) {
            check_admin_referer($this->menu_page . '-options');
            $input = $this->options_validate($_POST[$this->option_name]);
            update_site_option($this->option_name, $input);
            $this->options = $this->main->ops->get_options();;
            add_action('network_admin_notices', array($this, 'network_settings_update_notice'));
        }        
    }
    
    public function network_settings_update_notice() {
        $class = 'notice updated';
	$message = __("Settings saved.", 'fau-websso');

	printf('<div class="%1s"><p>%2s</p></div>', esc_attr($class), esc_html($message));        
    }
    
}
