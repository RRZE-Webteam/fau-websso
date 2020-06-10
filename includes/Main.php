<?php

namespace RRZE\WebSSO;

defined('ABSPATH') || exit;

class Main
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
     * [protected description]
     * @var object
     */
    public $simplesaml;

    /**
     * [public description]
     * @var boolean
     */
    public $currentUserCanBoth;

    /**
     * [public description]
     * @var boolean
     */
    public $registration;

    /**
     * [__construct description]
     * @param string $pluginFile [description]
     */
    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;

        $this->options = Options::getOptions();
    }

    public function onLoaded()
    {
        $settings = new Settings();
        $settings->onLoaded();

        if (is_super_admin()) {
            $userList = new UsersList();
            $userList->onLoaded();
        }

        $simplesaml = new SimpleSAML($this->pluginFile);
        $this->simplesaml = $simplesaml->onLoaded();
        if ($this->simplesaml === false) {
            return;
        }

        if (!in_array($this->options->force_websso, [1, 2])) {
            return;
        }

        if ($this->options->dev_mode) {
            $devMode = new DevMode;
            $devMode->onLoaded();
        }

        if ($this->options->force_websso == 1) {
            add_action('login_enqueue_scripts', [$this, 'loginEnqueueScripts']);
            add_action('login_form', [$this, 'loginForm']);
        } else {
            $this->registerRedirect();
            $this->userNewPageRedirect();

            if (!$this->options->dev_mode) {
                // Fires before the lost password form (die).
                add_action('lost_password', [$this, 'disableFunction']);
                // Fires before a new password is retrieved (die).
                add_action('retrieve_password', [$this, 'disableFunction']);
                // Fires before the user’s password is reset (die).
                add_action('password_reset', [$this, 'disableFunction']);

                // Filters the display of the password fields (disable).
                add_filter('show_password_fields', '__return_false');
            }

            // Send a confirmation request email to a user 
            // when they sign up for a new user account (disable).
            add_filter('wpmu_signup_user_notification', '__return_false');
            // Notify a user that their account activation 
            // has been successful (disable).
            add_filter('wpmu_welcome_user_notification', '__return_false');

            // Filters whether to show the Add Existing User form 
            // on the Multisite Users screen (disable).
            add_filter('show_network_site_users_add_existing_form', '__return_false');
            // Filters whether to show the Add New User form 
            // on the Multisite Users screen (disable).
            add_filter('show_network_site_users_add_new_form', '__return_false');

            add_action('network_admin_menu', [__NAMESPACE__ . '\NetworkMenu', 'userNewPage']);
            add_action('admin_menu', [__NAMESPACE__ . '\SiteMenu', 'userNewPage']);

            add_action('admin_init', [__NAMESPACE__ . '\Users', 'userNewAction']);
        }

        add_filter('is_fau_websso_active', '__return_true');

        if (is_multisite() && (!get_site_option('registration') || get_site_option('registration') == 'none')) {
            $this->registration = false;
        } elseif (!is_multisite() && !get_option('users_can_register')) {
            $this->registration = false;
        } else {
            $this->registration = true;
        }

        $this->registration = apply_filters('fau_websso_registration', $this->registration);

        if (!$this->registration) {
            add_action('before_signup_header', [$this, 'beforeSignupHeader']);
        }
        
        // After wp_authenticate_username_password runs.
        add_filter('authenticate', [$this, 'authenticate'], 21, 3);
        //remove_action('authenticate', 'wp_authenticate_username_password', 20, 3);
        //remove_action('authenticate', 'wp_authenticate_email_password', 20, 3);

        add_filter('login_url', [$this, 'loginUrl'], 10, 2);

        add_action('wp_logout', [$this, 'wpLogout']);

        add_filter('wp_auth_check_same_domain', '__return_false');
    }

    public function beforeSignupHeader()
    {
        wp_redirect(site_url('', $this->options->simplesaml_url_scheme));
        exit;
    }

    public function wpLogout()
    {
        wp_destroy_other_sessions();
        if ($this->simplesaml->isAuthenticated()) {
            $this->simplesaml->logout(site_url('', $this->options->simplesaml_url_scheme));
            \SimpleSAML\Session::getSessionFromRequest()->cleanup();
        }
    }

    public function authenticate($user, $user_login, $user_pass)
    {
        if (is_a($user, 'WP_User') && $this->options->force_websso == 1) {
            return $user;
        }

        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

        if ($this->options->force_websso == 1 && $action != 'websso') {
            return wp_authenticate_username_password(null, $user_login, $user_pass);
        }

        if (!$this->simplesaml->isAuthenticated()) {
            \SimpleSAML\Session::getSessionFromRequest()->cleanup();
            $this->simplesaml->requireAuth();
            \SimpleSAML\Session::getSessionFromRequest()->cleanup();
        }

        $attributes = array();

        $_attributes = $this->simplesaml->getAttributes();

        if (!empty($_attributes)) {
            do_action('rrze.log.info', ['plugin' => 'fau-websso', 'method' => __METHOD__, 'attributes' => $_attributes]);
            $attributes['uid'] = isset($_attributes['urn:mace:dir:attribute-def:uid'][0]) ? $_attributes['urn:mace:dir:attribute-def:uid'][0] : '';
            $attributes['mail'] = isset($_attributes['urn:mace:dir:attribute-def:mail'][0]) ? $_attributes['urn:mace:dir:attribute-def:mail'][0] : '';
            $attributes['displayName'] = isset($_attributes['urn:mace:dir:attribute-def:displayName'][0]) ? $_attributes['urn:mace:dir:attribute-def:displayName'][0] : '';
            $attributes['eduPersonAffiliation'] = isset($_attributes['urn:mace:dir:attribute-def:eduPersonAffiliation']) ? $_attributes['urn:mace:dir:attribute-def:eduPersonAffiliation'] : '';
            $attributes['eduPersonEntitlement'] = isset($_attributes['urn:mace:dir:attribute-def:eduPersonEntitlement']) ? $_attributes['urn:mace:dir:attribute-def:eduPersonEntitlement'] : '';
        }

        if (empty($attributes['uid'])) {
            $this->login_die(__("The IdM Username is not valid.", 'fau-websso', false));
        }

        $user_login = $attributes['uid'];

        if ($user_login != substr(sanitize_user($user_login, true), 0, 60)) {
            $this->login_die(__("The IdM Username entered is not valid.", 'fau-websso'));
        }

        $user_email = is_email($attributes['mail']) ? strtolower($attributes['mail']) : sprintf('%s@fau.de', base_convert(uniqid('', false), 16, 36));

        $display_name = $attributes['displayName'];
        $display_name_array = explode(' ', $display_name);
        $first_name = array_shift($display_name_array);
        $last_name = implode(' ', $display_name_array);

        $edu_person_affiliation = $attributes['eduPersonAffiliation'];
        $edu_person_entitlement = $attributes['eduPersonEntitlement'];

        if (is_multisite()) {
            global $wpdb;
            $key = $wpdb->get_var($wpdb->prepare("SELECT activation_key FROM {$wpdb->signups} WHERE user_login = %s", $user_login));
            Users::activateSignup($key);
        }

        $userdata = get_user_by('login', $user_login);

        if ($userdata) {
            if ((!empty($display_name) && $userdata->data->display_name == $user_login)) {
                $user_id = wp_update_user(
                    array(
                        'ID' => $userdata->ID,
                        'display_name' => $display_name
                    )
                );

                if (is_wp_error($user_id)) {
                    $this->login_die(__("The user data could not be updated.", 'fau-websso'));
                }

                update_user_meta($user_id, 'first_name', $first_name);
                update_user_meta($user_id, 'last_name', $last_name);
            }

            $user = new \WP_User($userdata->ID);
            update_user_meta($userdata->ID, 'edu_person_affiliation', $edu_person_affiliation);
            update_user_meta($userdata->ID, 'edu_person_entitlement', $edu_person_entitlement);

            if ($this->registration && is_multisite()) {
                if (!is_user_member_of_blog($userdata->ID, 1)) {
                    add_user_to_blog(1, $userdata->ID, 'subscriber');
                }
            }
        } else {
            if (!$this->registration) {
                $this->login_die(__("User registration is currently not allowed.", 'fau-websso'));
            }

            if (is_multisite()) {
                switch_to_blog(1);
            }

            $user_id = wp_insert_user(
                array(
                    'user_pass' => wp_generate_password(12, false),
                    'user_login' => $user_login,
                    'user_email' => $user_email,
                    'display_name' => $display_name,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'role' => 'subscriber'
                )
            );

            if (is_wp_error($user_id)) {
                if (is_multisite()) {
                    restore_current_blog();
                }
                $this->login_die(__("The user could not be added.", 'fau-websso'));
            }

            $user = new \WP_User($user_id);
            update_user_meta($user_id, 'edu_person_affiliation', $edu_person_affiliation);
            update_user_meta($user_id, 'edu_person_entitlement', $edu_person_entitlement);

            if (is_multisite()) {
                add_user_to_blog(1, $user_id, 'subscriber');
                restore_current_blog();

                if (!is_user_member_of_blog($user_id, get_current_blog_id())) {
                    add_user_to_blog(get_current_blog_id(), $user_id, 'subscriber');
                }
            }
        }

        if (is_multisite()) {
            $blogs = get_blogs_of_user($user->ID);
            if (!$this->has_dashboard_access($user->ID, $blogs)) {
                $this->accessDie($blogs);
            }
        }

        $sso_attributes = !empty($_attributes) ? $_attributes : '';
        update_user_meta($user->ID, 'sso_attributes', $sso_attributes);

        return $user;
    }

    public function loginUrl($login_url, $redirect)
    {
        $login_url = site_url('wp-login.php', 'login');

        if (!empty($redirect)) {
            $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
        }

        return $login_url;
    }

    private function has_dashboard_access($user_id, $blogs)
    {
        if (is_super_admin($user_id)) {
            return true;
        }

        if (wp_list_filter($blogs, array('userblog_id' => get_current_blog_id()))) {
            return true;
        }

        return false;
    }

    private function accessDie($blogs)
    {
        $blog_name = get_bloginfo('name');

        $output = '<p>' . sprintf(__('You attempted to access the &ldquo;%1$s&rdquo; dashboard, but you do not currently have privileges on this website. If you believe you should be able to access the &ldquo;%1$s&rdquo; dashboard, please contact the contact person of the website.', 'fau-websso'), $blog_name) . '</p>';

        if (!empty($blogs)) {
            $output .= '<p>' . __("If you reached this screen by accident and meant to visit one of your own websites, here are some shortcuts to help you find your way.", 'fau-websso') . '</p>';

            $output .= '<h3>' . __("Your Websites", 'fau-websso') . '</h3>';
            $output .= '<table>';

            foreach ($blogs as $blog) {
                $output .= '<tr>';
                $output .= "<td>{$blog->blogname}</td>";
                $output .= '<td><a href="' . esc_url(get_admin_url($blog->userblog_id)) . '">' . __("Visit the Dashboard", 'fau-websso') . '</a> | ' .
                    '<a href="' . esc_url(get_home_url($blog->userblog_id)) . '">' . __("View the website", 'fau-websso') . '</a></td>';
                $output .= '</tr>';
            }

            $output .= '</table>';
        }

        $output .= $this->get_contact();

        $output .= sprintf('<p><a href="%s">' . __("Single Sign-On Log Out", 'fau-websso') . '</a></p>', wp_logout_url());

        wp_die($output, 403);
    }

    private function login_die($message, $simplesaml_authenticated = true)
    {
        $output = '';

        $output .= sprintf('<p><strong>%1$s</strong> %2$s</p>', __("ERROR:", 'fau-websso'), $message);
        $output .= sprintf('<p>%s</p>', sprintf(__("Authentication failed on the &ldquo;%s&rdquo; website.", 'fau-websso'), get_bloginfo('name')));
        $output .= sprintf('<p>%s</p>', __("However, if no login is possible, please contact the contact person of the website.", 'fau-websso'));

        $output .= $this->get_contact();

        if ($simplesaml_authenticated) {
            $output .= sprintf('<p><a href="%s">' . __("Single Sign-On Log Out", 'fau-websso') . '</a></p>', wp_logout_url());
        }

        wp_die($output);
    }

    private function get_contact()
    {
        global $wpdb;

        $blog_prefix = $wpdb->get_blog_prefix(get_current_blog_id());
        $users = $wpdb->get_results(
            "SELECT user_id, user_id AS ID, user_login, display_name, user_email, meta_value
             FROM $wpdb->users, $wpdb->usermeta
             WHERE {$wpdb->users}.ID = {$wpdb->usermeta}.user_id AND meta_key = '{$blog_prefix}capabilities'
             ORDER BY {$wpdb->usermeta}.user_id"
        );

        if (empty($users)) {
            return '';
        }

        $output = sprintf('<h3>%s</h3>' . "\n", sprintf(__("Contact persons for the &ldquo;%s&rdquo; website", 'fau-websso'), get_bloginfo('name')));

        foreach ($users as $user) {
            $roles = unserialize($user->meta_value);
            if (isset($roles['administrator'])) {
                $output .= sprintf('<p>%1$s<br/>%2$s %3$s</p>' . "\n", $user->display_name, __("Email Address:", 'fau-websso'), make_clickable($user->user_email));
            }
        }

        return $output;
    }

    // Start Case 1

    public function loginEnqueueScripts()
    {
        wp_enqueue_style('fau-websso-login-form', plugins_url('css/login-form.css', plugin_basename($this->pluginFile)), 'all', null);
    }

    public function loginForm()
    {
        $login_url = add_query_arg('action', 'websso', site_url('/wp-login.php', $this->options->simplesaml_url_scheme));
        echo '<div class="message rrze-websso-login-form">';
        echo '<p>' . __("You have already activated your IdM Username?", 'fau-websso') . '</p>';
        printf('<p>' . __("Please login to the website %s using the link below.", 'fau-websso') . '</p>', get_bloginfo('name'));
        printf('<p><a href="%1$s">' . __('Login to the %2$s website', 'fau-websso') . '</a></p>', $login_url, get_bloginfo('name'));
        echo '</div>';
    }

    // End Case 1

    // Start Case 2

    public function registerRedirect()
    {
        if ($this->isLoginPage() && isset($_REQUEST['action']) && $_REQUEST['action'] == 'register') {
            wp_redirect(site_url('wp-login.php', 'login'));
            exit;
        }
    }

    protected function userNewPageRedirect()
    {
        if (is_admin() && $this->isUserNewPage()) {
            wp_redirect('users.php?page=usernew');
            exit;
        }
    }

    protected function isUserNewPage()
    {
        if (isset($GLOBALS['pagenow'])) {
            return in_array($GLOBALS['pagenow'], array('user-new.php'));
        }
        return false;
    }

    protected function isLoginPage()
    {
        if (isset($GLOBALS['pagenow'])) {
            return in_array($GLOBALS['pagenow'], array('wp-login.php'));
        }
        return false;
    }

    public function disableFunction()
    {
        $output = __("Disabled function.", 'fau-websso');
        wp_die($output);
    }

    // End Case 2
}
