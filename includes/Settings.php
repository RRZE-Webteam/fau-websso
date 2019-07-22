<?php

namespace RRZE\WebSSO;

defined('ABSPATH') || exit;

use RRZE\WebSSO\Options;

class Settings
{
    /**
     * [protected description]
     * @var string
     */
    protected $optionName;

    /**
     * [protected description]
     * @var object
     */
    protected $options;

    /**
     * [protected description]
     * @var string
     */
    protected $menuPage = 'websso';

    /**
     * [__construct description]
     */
    public function __construct()
    {
        $this->optionName = Options::getOptionName();
        $this->options = Options::getOptions();
    }

    public function onLoaded()
    {
        if (is_multisite()) {
            add_action('admin_init', [$this, 'networkSettingsUpdate']);
            add_action('network_admin_menu', [$this, 'networkAdminMenu']);
        } else {
            add_action('admin_menu', [$this, 'adminMenu']);
        }

        add_action('admin_init', [$this, 'adminInit']);
    }

    /**
     * [networkAdminMenu description]
     * @return void
     */
    public function networkAdminMenu()
    {
        add_submenu_page('settings.php', __('WebSSO', 'fau-websso'), __('WebSSO', 'fau-websso'), 'manage_network_options', $this->menuPage, [$this, 'networkOptionsPage']);
    }

    /**
     * [adminMenu description]
     * @return void
     */
    public function adminMenu()
    {
        add_options_page(__('WebSSO', 'fau-websso'), __('WebSSO', 'fau-websso'), 'manage_options', $this->menuPage, [$this, 'optionsPage']);
    }

    /**
     * [networkOptionsPage description]
     * @return void
     */
    public function networkOptionsPage()
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(__('WebSSO', 'fau-websso')); ?></h1>
            <form method="post">
            <?php do_settings_sections($this->menuPage); ?>
            <?php settings_fields($this->menuPage); ?>
            <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * [optionsPage description]
     * @return void
     */
    public function optionsPage()
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(__("WebSSO Settings", 'fau-websso')); ?></h1>
            <form method="post" action="options.php">
            <?php do_settings_sections($this->menuPage); ?>
            <?php settings_fields($this->menuPage); ?>
            <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * [adminInit description]
     * @return void
     */
    public function adminInit()
    {
        if (!is_multisite()) {
            register_setting($this->menuPage, $this->optionName, [$this, 'optionsValidate']);
        }

        add_settings_section('websso_options_section', false, [$this, 'websso_settings_section'], $this->menuPage);
        add_settings_field('force_websso', __("Allow WebSSO", 'fau-websso'), [$this, 'webssoField'], $this->menuPage, 'websso_options_section');

        add_settings_section('simplesaml_options_section', false, [$this, 'simpleSAMLSettingsSection'], $this->menuPage);
        add_settings_field('simplesaml_include', __("Autoload path", 'fau-websso'), [$this, 'simpleSAMLIncludeField'], $this->menuPage, 'simplesaml_options_section');
        add_settings_field('simplesaml_auth_source', __("Authentication source", 'fau-websso'), [$this, 'simpleSAMLAuthSourceField'], $this->menuPage, 'simplesaml_options_section');
        add_settings_field('simplesaml_url_scheme', __("URL scheme", 'fau-websso'), [$this, 'simpleSAMLUrlSchemeField'], $this->menuPage, 'simplesaml_options_section');
    }

    /**
     * [websso_settings_section description]
     * @return void
     */
    public function websso_settings_section()
    {
        echo '<h3 class="title">' . __("Web Single Sign-On", 'fau-websso') . '</h3>';
        echo '<p>' . __("General WebSSO Settings.", 'fau-websso') . '</p>';
    }

    /**
     * [webssoField description]
     * @return void
     */
    public function webssoField()
    {
        echo '<fieldset>';
        echo '<legend class="screen-reader-text">' . __("WebSSO Settings", 'fau-websso') . '</legend>';
        echo '<label><input name="' . $this->optionName . '[force_websso]" id="force_websso0" value="0" type="radio" ', checked($this->options->force_websso, 0), '>' . __("WebSSO has been disabled.", 'fau-websso') . '</label><br>';
        echo '<label><input name="' . $this->optionName . '[force_websso]" id="force_websso1" value="1" type="radio" ', checked($this->options->force_websso, 1), '>' . __("Users can log on locally and WebSSO.", 'fau-websso') . '</label><br>';
        echo '<label><input name="' . $this->optionName . '[force_websso]" id="force_websso2" value="2" type="radio" ', checked($this->options->force_websso, 2), '>' . __("Users may log in only via WebSSO.", 'fau-websso') . '</label><br>';
        echo '</fieldset>';
    }

    /**
     * [simpleSAMLSettingsSection description]
     * @return void
     */
    public function simpleSAMLSettingsSection()
    {
        echo '<h3 class="title">' . __("SimpleSAMLphp", 'fau-websso') . '</h3>';
        echo '<p>' . __("Service Provider Settings.", 'fau-websso') . '</p>';
    }

    /**
     * [simpleSAMLIncludeField description]
     * @return void
     */
    public function simpleSAMLIncludeField()
    {
        echo '<input type="text" id="simplesaml_include" class="regular-text ltr" name="' . $this->optionName . '[simplesaml_include]" value="' . esc_attr($this->options->simplesaml_include) . '">';
        echo '<p class="description">' . __("Relative path starting from the wp-content directory.", 'fau-websso') . '</p>';
    }

    /**
     * [simpleSAMLAuthSourceField description]
     * @return void
     */
    public function simpleSAMLAuthSourceField()
    {
        echo '<input type="text" id="simplesaml_auth_source" class="regular-text ltr" name="' . $this->optionName . '[simplesaml_auth_source]" value="' . esc_attr($this->options->simplesaml_auth_source) . '">';
    }

    /**
     * [simpleSAMLUrlSchemeField description]
     * @return void
     */
    public function simpleSAMLUrlSchemeField()
    {
        echo '<select name="' . $this->optionName . '[simplesaml_url_scheme]">';
        echo '<option value="https" ' . selected($this->options->simplesaml_url_scheme, 'https') . '>https</option>';
        echo '<option value="http" ' . selected($this->options->simplesaml_url_scheme, 'http') . '>http</option>';
        echo '</select>';
    }

    /**
     * [optionsValidate description]
     * @param  array $input [description]
     * @return array        [description]
     */
    public function optionsValidate($input)
    {
        $input['force_websso'] = isset($input['force_websso']) && in_array(absint($input['force_websso']), [0, 1, 2]) ? absint($input['force_websso']) : $this->options->force_websso;

        $input['simplesaml_include'] = !empty($input['simplesaml_include']) ? esc_attr(trim($input['simplesaml_include'])) : $this->options->simplesaml_include;
        $input['simplesaml_auth_source'] = isset($input['simplesaml_auth_source']) ? esc_attr(trim($input['simplesaml_auth_source'])) : $this->options->simplesaml_auth_source;
        $input['simplesaml_url_scheme'] = isset($input['simplesaml_url_scheme']) && in_array(trim($input['simplesaml_url_scheme']), ['http', 'https']) ? trim($input['simplesaml_url_scheme']) : $this->options->simplesaml_url_scheme;

        return $input;
    }

    /**
     * [networkSettingsUpdate description]
     * @return void
     */
    public function networkSettingsUpdate()
    {
        if (!empty($_POST[$this->optionName])) {
            check_admin_referer($this->menuPage . '-options');
            $input = $this->optionsValidate($_POST[$this->optionName]);
            update_site_option($this->optionName, $input);
            $this->options = Options::getOptions();
            add_action('network_admin_notices', [$this, 'networkSettingsUpdateNotice']);
        }
    }

    /**
     * [networkSettingsUpdateNotice description]
     * @return void
     */
    public function networkSettingsUpdateNotice()
    {
        $class = 'notice updated';
        $message = __("Settings saved.", 'fau-websso');

        printf('<div class="%1s"><p>%2s</p></div>', esc_attr($class), esc_html($message));
    }
}
