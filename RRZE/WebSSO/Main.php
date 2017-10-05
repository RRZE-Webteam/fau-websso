<?php

namespace RRZE\WebSSO;

use RRZE\WebSSO\Core\Options;
use RRZE\WebSSO\Core\Settings;
use WP_User;
use WP_Error;

defined('ABSPATH') || exit;

class Main {
    
    public $plugin_basename;

    public $ops;
    public $options;
    public $option_name;
    
    public $settings;
    
    public $simplesaml_auth_simple;
    
    public $simplesaml_autoload_error = FALSE;
    
    public $current_user_can_both;
    
    public $registration;    
    
    public function __construct($plugin_basename = NULL) {
        $this->plugin_basename = $plugin_basename;

        $this->ops = new Options();
        $this->options = $this->ops->get_options();
        $this->option_name = $this->ops->get_option_name();
                
        $this->settings = new Settings($this);
        
        $this->simplesaml_autoload();
        $this->set_current_user_can();
        
        if($this->simplesaml_autoload_error) {
            return;
        }
        
        switch ($this->options->force_websso) {
            case 1:
                add_action('login_enqueue_scripts', array($this, 'login_enqueue_scripts'));
                add_action('login_form', array($this, 'login_form'));
                break;
            case 2:
                $this->register_redirect();
                $this->user_new_page_redirect();

                add_filter( 'wpmu_signup_user_notification', '__return_false' );
                add_filter( 'wpmu_welcome_user_notification', '__return_false' );

                add_action('lost_password', array($this, 'disable_function'));
                add_action('retrieve_password', array($this, 'disable_function'));
                add_action('password_reset', array($this, 'disable_function'));

                add_filter('show_password_fields', '__return_false');

                add_filter('show_network_site_users_add_existing_form', '__return_false');
                add_filter('show_network_site_users_add_new_form', '__return_false');

                add_action('network_admin_menu', array($this, 'network_admin_user_new_page'));
                add_action('admin_menu', array($this, 'admin_user_new_page'));

                add_action('admin_init', array($this, 'user_new_action'));
                break;
            default:
                return;
                break;
        }
        
        add_filter('is_fau_websso_active', '__return_true');
        
        if (is_multisite() && (!get_site_option('registration') || get_site_option('registration') == 'none')) {
            $this->registration = FALSE;               
        } elseif (!is_multisite() && !get_option('users_can_register')) {
            $this->registration = FALSE;
        } else {
            $this->registration = TRUE;
        }
        
        add_filter('authenticate', array($this, 'authenticate'), 30, 3);

        add_filter('login_url', array($this, 'login_url'), 10, 2);
        
        //add_action('auth_cookie_valid', array($this, 'auth_cookie_valid_action'), 0);
        
        add_action('wp_logout', array($this, 'wp_logout_action'), 0);        

        add_filter('wp_auth_check_same_domain', '__return_false');

        add_filter('manage_users_columns', array($this, 'users_attributes'));
        add_action('manage_users_custom_column', array($this, 'users_attributes_columns'), 10, 3);
        add_filter('wpmu_users_columns', array($this, 'users_attributes'));
        add_action('wpmu_users_custom_column', array($this, 'users_attributes_columns'), 10, 3);
        
        if (!$this->registration) {
            add_action('before_signup_header', array($this, 'before_signup_header'));
        }
    }

    private function set_current_user_can() {
        $user_can_both = FALSE;
        if (is_multisite() && current_user_can('promote_users') && current_user_can('create_users')) {
            $user_can_both = TRUE;
        }
        
        $this->current_user_can_both = $user_can_both;
    }
    
    private function simplesaml_autoload() {
        if(file_exists(WP_CONTENT_DIR . $this->options->simplesaml_include)) {
            require_once(WP_CONTENT_DIR . $this->options->simplesaml_include);
            $this->simplesaml_auth_simple = new \SimpleSAML_Auth_Simple($this->options->simplesaml_auth_source);
        } else {
            $this->simplesaml_autoload_error = __("The autoload file could not be included.", 'fau-websso');            
        }
    }
    
    public function before_signup_header() {
        wp_redirect(site_url('', $this->options->simplesaml_url_scheme));
        exit;
    }
    
    public function authenticate($user, $user_login, $user_pass) {
        
        if (is_a($user, 'WP_User')) {
            return $user;
        }

        remove_action('authenticate', 'wp_authenticate_username_password', 20);

        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        
        if ($this->options->force_websso == 1 && $action != 'websso') {
            return wp_authenticate_username_password(NULL, $user_login, $user_pass);
        } elseif ($this->options->force_websso == 1 && $action == 'websso') {
            update_option('websso_action', 1);
        }
        
        if($this->simplesaml_autoload_error) {
            return $this->login_error($this->simplesaml_autoload_error);
        }
        
        if (!$this->simplesaml_auth_simple->isAuthenticated()) {
            $this->simplesaml_auth_simple->requireAuth();
        }
        
        $attributes = array();

        $_attributes = $this->simplesaml_auth_simple->getAttributes();

        if (!empty($_attributes)) {
            $attributes['uid'] = isset($_attributes['urn:mace:dir:attribute-def:uid'][0]) ? $_attributes['urn:mace:dir:attribute-def:uid'][0] : '';
            $attributes['mail'] = isset($_attributes['urn:mace:dir:attribute-def:mail'][0]) ? $_attributes['urn:mace:dir:attribute-def:mail'][0] : '';
            $attributes['displayName'] = isset($_attributes['urn:mace:dir:attribute-def:displayName'][0]) ? $_attributes['urn:mace:dir:attribute-def:displayName'][0] : '';            
            $attributes['eduPersonAffiliation'] = isset($_attributes['urn:mace:dir:attribute-def:eduPersonAffiliation'][0]) ? $_attributes['urn:mace:dir:attribute-def:eduPersonAffiliation'][0] : '';
            $attributes['eduPersonEntitlement'] = isset($_attributes['urn:mace:dir:attribute-def:eduPersonEntitlement'][0]) ? $_attributes['urn:mace:dir:attribute-def:eduPersonEntitlement'][0] : '';
        }

        if (empty($attributes['uid'])) {
            return $this->login_error(__("The IdM Username is not valid.", 'fau-websso', FALSE));
        }

        $user_login = $attributes['uid'];

        if ($user_login != substr(sanitize_user($user_login, TRUE), 0, 60)) {
            return $this->login_error(__("The IdM Username entered is not valid.", 'fau-websso'));
        }
        
        $user_email = is_email($attributes['mail']) ? strtolower($attributes['mail']) : sprintf('%s@fau.de', base_convert(uniqid('', FALSE), 16, 36));
        $display_name = $attributes['displayName'];
        $display_name_array = explode(' ', $attributes['displayName']);
        $first_name = array_shift($display_name_array);
        $last_name = implode(' ', $display_name_array);

        $edu_person_affiliation = $attributes['eduPersonAffiliation'];
        $edu_person_entitlement = $attributes['eduPersonEntitlement'];
        
        if(is_multisite()) {
            global $wpdb;
            $key = $wpdb->get_var($wpdb->prepare("SELECT activation_key FROM {$wpdb->signups} WHERE user_login = %s", $user_login));
            $this->activate_signup($key);            
        }
        
        $userdata = get_user_by('login', $user_login);

        if ($userdata) {
            if ((!empty($display_name) && $userdata->data->display_name == $user_login)) {                
                $user_id = wp_update_user(array(
                    'ID' => $userdata->ID,
                    'display_name' => $display_name
                    ) 
                );

                if (is_wp_error($user_id)) {
                    return $this->login_error(__("The user data could not be updated.", 'fau-websso'));
                }
                
                update_user_meta($user_id, 'first_name', $first_name);
                update_user_meta($user_id, 'last_name', $last_name);
            }
            
            $user = new WP_User($userdata->ID);            
            update_user_meta($userdata->ID, 'edu_person_affiliation', $edu_person_affiliation);
            update_user_meta($userdata->ID, 'edu_person_entitlement', $edu_person_entitlement);
                      
            if ($this->registration && is_multisite()) {
                if (!is_user_member_of_blog($userdata->ID, 1)) {
                    add_user_to_blog(1, $userdata->ID, 'subscriber');
                }
            }
            
        } else {
            if (!$this->registration) {
                return $this->login_error(__("User registration is currently not allowed.", 'fau-websso'));
            }
                        
            if (is_multisite()) {
                switch_to_blog(1);
            }
            
            $user_id = wp_insert_user(array(
                'user_pass' => wp_generate_password(12, FALSE),
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
                return $this->login_error(__("The user could not be added.", 'fau-websso'));
            }
            
            $user = new WP_User($user_id);
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
                $this->access_denied($blogs);
            }
        }
        
        return $user;
    }

    public function login_url($login_url, $redirect) {
        $login_url = site_url('wp-login.php', 'login');

        if (!empty($redirect)) {
            $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
        }
        
        return $login_url;
    }

    public function auth_cookie_valid_action($cookie_elements) {
        if ($this->options->force_websso == 1 && !get_option('websso_action')) {
            return;
        }
        
        if (!$this->simplesaml_auth_simple->isAuthenticated()) {
            wp_logout();
        }
    }
    
    public function wp_logout_action() {
        delete_option('websso_action');
        
        if ($this->simplesaml_auth_simple->isAuthenticated()) {
            $this->simplesaml_auth_simple->logout(site_url('', $this->options->simplesaml_url_scheme));
        }
    }
    
    private function has_dashboard_access($user_id, $blogs) {
        if (is_super_admin($user_id)) {
            return TRUE;
        }

        if (wp_list_filter($blogs, array('userblog_id' => get_current_blog_id()))) {
            return TRUE;
        }
        
        return FALSE;
    }
    
    private function access_denied($blogs) {
        
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
                    '<a href="' . esc_url(get_home_url($blog->userblog_id)). '">' . __("View the website", 'fau-websso') . '</a></td>';
                $output .= '</tr>';
            }

            $output .= '</table>';
        }
        
        $output .= $this->get_contact();
        
        $output .= sprintf('<p><a href="%s">' . __("Single Sign-On Log Out", 'fau-websso') . '</a></p>', wp_logout_url());
        
        wp_die($output, 403);
    }
    
    private function login_error($message, $simplesaml_authenticated = TRUE) {
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

    private function get_contact() {
        global $wpdb;

        $blog_prefix = $wpdb->get_blog_prefix(get_current_blog_id());
        $users = $wpdb->get_results(
             "SELECT user_id, user_id AS ID, user_login, display_name, user_email, meta_value
             FROM $wpdb->users, $wpdb->usermeta
             WHERE {$wpdb->users}.ID = {$wpdb->usermeta}.user_id AND meta_key = '{$blog_prefix}capabilities'
             ORDER BY {$wpdb->usermeta}.user_id");

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
    
    public function login_enqueue_scripts() {
        wp_enqueue_style('fau-websso-login-form', plugins_url('css/login-form.css', $this->plugin_basename ), 'all', NULL);
    }
    
    public function login_form() {
        $login_url = add_query_arg('action', 'websso', site_url('/wp-login.php', $this->options->simplesaml_url_scheme));
        echo '<div class="message rrze-websso-login-form">';
        echo '<p>' . __("You have already activated your IdM Username?", 'fau-websso') . '</p>';
        printf('<p>' . __("Please login to the website %s using the link below.", 'fau-websso') . '</p>', get_bloginfo('name'));
        printf('<p><a href="%1$s">' . __('Login to the %2$s website', 'fau-websso') . '</a></p>', $login_url, get_bloginfo('name'));
        echo '</div>';
    }
    
    // End Case 1

    // Start Case 2
    
    public function register_redirect() {
        if ($this->is_login_page() && isset($_REQUEST['action']) && $_REQUEST['action'] == 'register') {
            wp_redirect(site_url('wp-login.php', 'login'));
            exit;
        }        
     }
    
    private function user_new_page_redirect() {
        if (is_admin() && $this->is_user_new_page()) {
            wp_redirect('users.php?page=usernew');
            exit;
        }        
    }
    
    private function is_login_page() {
        if(isset($GLOBALS['pagenow'])) {
            return in_array($GLOBALS['pagenow'], array('wp-login.php'));
        }
        return FALSE;
    }

    private function is_user_new_page() {
        if(isset($GLOBALS['pagenow'])) {
            return in_array($GLOBALS['pagenow'], array('user-new.php'));
        }
        return FALSE;
    }
    
    public function disable_function() {
        $output = __("Disabled function.", 'fau-websso');
        wp_die($output);
    }
        
    public function network_admin_user_new_page() {
        global $submenu;

        remove_submenu_page('users.php', 'user-new.php');

        $submenu_page = add_submenu_page('users.php', __("Add New", 'fau-websso'), __("Add New", 'fau-websso'), 'manage_network_users', 'usernew', array($this, 'network_admin_user_new'));

        add_action(sprintf('load-%s', $submenu_page), array($this, 'network_admin_user_new_help_tab'));

        if(isset($submenu['users.php'])) {
            foreach ($submenu['users.php'] as $key => $value) {
                if ($value == __("Add New", 'fau-websso')) {
                    break;
                }
            }
            
            $submenu['users.php'][10] = $submenu['users.php'][$key];
            unset($submenu['users.php'][$key]);

            ksort($submenu['users.php']);            
        }
        
    }

    public function network_admin_user_new_help_tab() {
        $help = '<p>' . __("Add User will set up a new user account on the network.", 'fau-websso') . '</p>';
        $help .= '<p>' . __("Users who are signed up to the network without a site are added as subscribers to the main website, giving them profile pages to manage their accounts. These users will only see Dashboard and My Sites in the main navigation until a site is created for them.", 'fau-websso') . '</p>';

        get_current_screen()->add_help_tab(array(
            'id' => 'overview',
            'title' => __("Overall view", 'fau-websso'),
            'content' => $help,
        ));

        get_current_screen()->add_help_tab(array(
            'id' => 'user-roles',
            'title' => __("User Roles", 'fau-websso'),
            'content' => '<p>' . __("Here is a basic overview of the different user roles and the permissions associated with each one:", 'fau-websso') . '</p>' .
            '<ul>' .
            '<li>' . __("Subscribers can read comments/comment/receive newsletters, etc. but cannot create regular site content.", 'fau-websso') . '</li>' .
            '<li>' . __("Contributors can write and manage their posts but not publish posts or upload media files.", 'fau-websso') . '</li>' .
            '<li>' . __("Authors can publish and manage their own posts, and are able to upload files.", 'fau-websso') . '</li>' .            
            '<li>' . __("Editors can publish posts, manage posts as well as manage other people's posts, etc.", 'fau-websso') . '</li>' .
            '<li>' . __("Administrators have access to all the administration features.", 'fau-websso') . '</li>' .
            '</ul>'
        ));        
    }
    
    public function admin_user_new_page() {
        global $submenu;

        remove_submenu_page('users.php', 'user-new.php');

        if (is_multisite()) {
            $capability = 'promote_users';
        } else {
            $capability = 'create_users';
        }
        
        $submenu_page = add_submenu_page('users.php', __("Add New", 'fau-websso'), __("Add New", 'fau-websso'), $capability, 'usernew', array($this, 'admin_user_new'));

        add_action(sprintf('load-%s', $submenu_page), array($this, 'admin_user_new_help_tab'));

        if(isset($submenu['users.php'])) {
            foreach ($submenu['users.php'] as $key => $value) {
                if ($value == __("Add New", 'fau-websso')) {
                    break;
                }
            }
            
            $submenu['users.php'][10] = $submenu['users.php'][$key];
            unset($submenu['users.php'][$key]);

            ksort($submenu['users.php']);            
        }
        
    }

    public function admin_user_new_help_tab() {
        $help = '<p>' . __("To add a new user to your website, fill out the form on this page, and click below to add a new user.", 'fau-websso') . '</p>';

        if (is_multisite()) {
            $help .= '<p>' . __("Because this is a multisite installation, you may add accounts that already exist on the Network by specifying a username or email, and defining a role. For more options, you have to be a Network Administrator and use the hover link under an existing user's name to Edit the user profile under Network Admin &gt; All Users.", 'fau-websso') . '</p>' .
            '<p>' . __("New users will receive an email letting them know they've been added as a user for your site. Check the box if you don't want the user receive a welcome email.", 'fau-websso') . '</p>';
        } else {
            $help .= '<p>' . __("New users will receive an email letting them know they've been added as a user for your website.", 'fau-websso') . '</p>';
        }

        $help .= '<p>' . __("Remember to click the Add New User button at the bottom of this screen when you are finished.", 'fau-websso') . '</p>';

        get_current_screen()->add_help_tab(array(
            'id' => 'overview',
            'title' => __("Overall view", 'fau-websso'),
            'content' => $help,
        ));

        get_current_screen()->add_help_tab(array(
            'id' => 'user-roles',
            'title' => __("User Roles", 'fau-websso'),
            'content' => '<p>' . __("Here is a basic overview of the different user roles and the permissions associated with each one:", 'fau-websso') . '</p>' .
            '<ul>' .
            '<li>' . __("Subscribers can read comments/comment/receive newsletters, etc. but cannot create regular site content.", 'fau-websso') . '</li>' .
            '<li>' . __("Contributors can write and manage their posts but not publish posts or upload media files.", 'fau-websso') . '</li>' .
            '<li>' . __("Authors can publish and manage their own posts, and are able to upload files.", 'fau-websso') . '</li>' .            
            '<li>' . __("Editors can publish posts, manage posts as well as manage other people's posts, etc.", 'fau-websso') . '</li>' .
            '<li>' . __("Administrators have access to all the administration features.", 'fau-websso') . '</li>' .
            '</ul>'
        ));        
    }
    
    public function user_new_action() {
        global $wpdb;
        
        if (isset($_REQUEST['action']) && 'add-user' == $_REQUEST['action']) {
            check_admin_referer('add-user', '_wpnonce_add-user');

            if (!is_array($_POST['user'])) {
                wp_die(__("Cannot create an empty user.", 'fau-websso'));
            }

            $user = wp_unslash($_POST['user']);

            $user_details = $this->validate_user_signup($user['username'], $user['email']);
            if (is_wp_error($user_details['errors']) && !empty($user_details['errors']->errors)) {
                $add_user_errors = base64_encode(serialize($user_details['errors']));
                $redirect = add_query_arg( array('page' => 'usernew', 'update' => 'addusererrors', 'error' => $add_user_errors), 'users.php' );                
            } else {
                $password = wp_generate_password(12, FALSE);
                $user_id = wpmu_create_user(esc_html(strtolower($user['username'])), $password, sanitize_email($user['email']));

                if (!$user_id) {
                    $add_user_errors = new WP_Error('add_user_fail', __("The user could not be added.", 'fau-websso'));
                    $redirect = add_query_arg( array('page' => 'usernew', 'error' => base64_encode(serialize($add_user_errors))), 'users.php' );                    
                } else {
                    $this->new_user_notification($user_id);
                    $redirect = add_query_arg(array('page' => 'usernew', 'update' => 'added'), 'users.php');
                }
            }
            wp_redirect($redirect);
            exit;            
        } elseif (isset($_REQUEST['action'], $_REQUEST['email']) && 'adduser' == $_REQUEST['action']) {
            check_admin_referer('add-user', '_wpnonce_add-user');

            $user_details = NULL;
            $user_email = wp_unslash($_REQUEST['email']);
            if (strpos( $user_email, '@') !== FALSE) {
                $user_details = get_user_by('email', $user_email);
            } else {
                if (is_super_admin()) {
                    $user_details = get_user_by('login', $user_email);
                } else {
                    wp_redirect(add_query_arg(array('page' => 'usernew', 'update' => 'enter_email'), 'users.php'));
                    exit;
                }
            }

            if (!$user_details) {
                wp_redirect(add_query_arg(array('page' => 'usernew', 'update' => 'does_not_exist'), 'users.php'));
                exit;
            }

            // Bestehenden Benutzer hinzufügen
            $redirect = add_query_arg(array('page' => 'usernew'), 'users.php');
            $username = $user_details->user_login;
            $user_id = $user_details->ID;
            
            if (($username != NULL && !is_super_admin($user_id)) && (array_key_exists(get_current_blog_id(), get_blogs_of_user($user_id)))) {
                $redirect = add_query_arg(array('page' => 'usernew', 'update' => 'addexisting'), 'users.php');
            } else {
                add_existing_user_to_blog(array('user_id' => $user_id, 'role' => $_REQUEST[ 'role' ]));
                if ( isset( $_POST['noconfirmation']) && is_super_admin()) {                   
                    $redirect = add_query_arg(array('page' => 'usernew', 'update' => 'addnoconfirmation'), 'users.php');
                } else {
                    $this->add_existing_user_notification($user_id);
                    $redirect = add_query_arg(array('page' => 'usernew', 'update' => 'add'), 'users.php');
                }
            }
            wp_redirect($redirect);
            exit;
        } elseif (isset($_REQUEST['action']) && 'createuser' == $_REQUEST['action']) {
            check_admin_referer('create-user', '_wpnonce_create-user');

            if (!is_multisite()) {
                $user_id = $this->create_user();

                if (is_wp_error($user_id)) {
                    $add_user_errors = $user_id;
                    $redirect = add_query_arg(array('page' => 'usernew', 'error' => base64_encode(serialize($add_user_errors))), 'users.php');                    
                } else {                   
                    $this->new_user_notification($user_id);
                    
                    if (current_user_can('list_users')) {
                        $redirect = add_query_arg(array('update' => 'add', 'id' => $user_id), 'users.php');
                    } else {
                        $redirect = add_query_arg(array('page' => 'usernew', 'update' => 'add'), 'users.php');
                    }
                    wp_redirect( $redirect );
                    exit;
                }
            } else {
                // Neuen Benutzer hinzufügen
                $new_user_email = wp_unslash($_REQUEST['email']);
                $user_details = $this->validate_user_signup($_REQUEST['user_login'], $new_user_email);
                
                if (is_wp_error($user_details['errors']) && !empty($user_details['errors']->errors)) {
                    $add_user_errors = $user_details[ 'errors' ];
                    $redirect = add_query_arg( array('page' => 'usernew', 'error' => base64_encode(serialize($add_user_errors))), 'users.php' );
                } else {
                    $new_user_login = sanitize_user(wp_unslash($_REQUEST['user_login']), TRUE);
                                        
                    wpmu_signup_user($new_user_login, $new_user_email, array('add_to_blog' => $wpdb->blogid, 'new_role' => $_REQUEST['role']));
                    
                    if(is_super_admin()) {
                        $key = $wpdb->get_var($wpdb->prepare("SELECT activation_key FROM {$wpdb->signups} WHERE user_login = %s AND user_email = %s", $new_user_login, $new_user_email));
                        $signup = wpmu_activate_signup($key);

                        if (is_wp_error($signup)) {
                            $add_user_errors = $signup;
                            $redirect = add_query_arg( array('page' => 'usernew', 'error' => base64_encode(serialize($add_user_errors))), 'users.php' );
                        }
                    }
                    
                    if (isset($_POST['noconfirmation']) && is_super_admin()) {
                        $redirect = add_query_arg( array('page' => 'usernew', 'update' => 'addnoconfirmation'), 'users.php' );
                    } else {
                        $this->invite_user_notification($new_user_login, $new_user_email);
                        $redirect = add_query_arg( array('page' => 'usernew', 'update' => 'newuserconfirmation'), 'users.php' );
                    }
                    
                }
            }
            wp_redirect($redirect);
            exit;            
        }
        
    }
    
    public function network_admin_user_new() {
        if (isset($_GET['update'])) {
            $messages = array();
            if ('added' == $_GET['update']) {
                $messages[] = __("User added.", 'fau-websso');
            }
        }
        ?>
        <div class="wrap">
        <h2 id="add-new-user"><?php _e("Add New User", 'fau-websso') ?></h2>
        <?php
        if (!empty($messages)) {
            foreach ($messages as $msg) {
                printf('<div id="message" class="updated"><p>%s</p></div>', $msg);
            }
        }

        $add_user_errors = '';
        if (isset($_GET['error'])) {
            $add_user_errors = @unserialize(base64_decode($_GET['error']));
        }
        
        if (is_wp_error($add_user_errors)) : ?>
            <div class="error">
                <?php
                    foreach ($add_user_errors->get_error_messages() as $message) {
                        echo "<p>$message</p>";
                    }
                ?>
            </div>
        <?php endif; ?>
            <form action="<?php echo network_admin_url('users.php?page=usernew&action=add-user'); ?>" id="adduser" method="post">
            <table class="form-table">
                <tr class="form-field form-required">
                    <th scope="row"><?php _e("IdM Username", 'fau-websso') ?></th>
                    <td><input type="text" class="regular-text" name="user[username]" /></td>
                </tr>
                <tr class="form-field form-required">
                    <th scope="row"><?php _e("Email Address", 'fau-websso') ?></th>
                    <td><input type="text" class="regular-text" name="user[email]" /></td>
                </tr>
            </table>
            <?php wp_nonce_field( 'add-user', '_wpnonce_add-user' ); ?>
            <?php submit_button(__("Add New User", 'fau-websso'), 'primary', 'add-user'); ?>
            </form>
        </div>
        <?php
    }
    
    public function admin_user_new() {
        $title = __("Add New User", 'fau-websso');

        $do_both = FALSE;
        if (is_multisite() && current_user_can('promote_users') && current_user_can('create_users')) {
            $do_both = TRUE;
        }
        
        wp_enqueue_script('wp-ajax-response');
        wp_enqueue_script('user-profile');

        if (is_multisite() && current_user_can('promote_users') && !wp_is_large_network('users') && (is_super_admin() || apply_filters('autocomplete_users_for_site_admins', FALSE))) {
            wp_enqueue_script('user-suggest');
        }

        if (isset($_GET['update'])) {
            $messages = array();
            if (is_multisite()) {
                switch ($_GET['update']) {                   
                    case "newuserconfirmation":
                        $messages[] = __("The user account has been succesfully created. An invitation email has been sent to the user.", 'fau-websso');
                        break;
                    case "add":
                        $messages[] = __("The user has been added to your website. An invitation email has been sent to the user.", 'fau-websso');
                        break;                   
                    case "addnoconfirmation":
                        $messages[] = __("The user has been added to your website.", 'fau-websso');
                        break;
                    case "addexisting":
                        $messages[] = __("This user is already a member of this website.", 'fau-websso');
                        break;
                    case "does_not_exist":
                        $messages[] = __("The requested user does not exist.", 'fau-websso');
                        break;
                    case "enter_email":
                        $messages[] = __("Please enter a valid email address.", 'fau-websso');
                        break;
                }
            }
            
            else {
                if ('add' == $_GET['update']) {
                    $messages[] = __("User added.", 'fau-websso');
                }
            }
        }
        ?>
        <div class="wrap">
        <h2 id="add-new-user"> <?php
        if (current_user_can('create_users')) {
            _e("Add New User", 'fau-websso');
        } elseif (current_user_can('promote_users')) {
            _e("Add Existing User", 'fau-websso');
        } ?>
        </h2>

        <?php if (isset($errors) && is_wp_error($errors)) : ?>
            <div class="error">
                <ul>
                <?php
                    foreach ($errors->get_error_messages() as $err) {
                        echo "<li>$err</li>\n";
                    }
                ?>
                </ul>
            </div>
        <?php endif;

        if (!empty($messages)) {
            foreach ($messages as $msg) {
                echo '<div id="message" class="updated"><p>' . $msg . '</p></div>';
            }
        }

        $add_user_errors = '';
        if (isset($_GET['error'])) {
            $add_user_errors = @unserialize(base64_decode($_GET['error']));
        }
        
        if (is_wp_error($add_user_errors)) : ?>

            <div class="error">
                <?php
                    foreach ($add_user_errors->get_error_messages() as $message) {
                        echo "<p>$message</p>";
                    }
                ?>
            </div>
        <?php endif; ?>
        <div id="ajax-response"></div>

        <?php
        if (is_multisite()) {
            if ($do_both) {
                echo '<h3 id="add-existing-user">' . __("Add Existing User", 'fau-websso') . '</h3>';
            }
            if (!is_super_admin()) {
                echo '<p>' . __("Enter the email address of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.", 'fau-websso') . '</p>';
                $label = __("Email Address", 'fau-websso');
                $type  = 'email';
            } else {
                echo '<p>' . __("Enter the email address or username of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.", 'fau-websso') . '</p>';
                $label = __("Email Address or Username", 'fau-websso');
                $type  = 'text';
            }
        ?>
        <form action="" method="post" name="adduser" id="adduser" class="validate" novalidate="novalidate">
        <input name="action" type="hidden" value="adduser" />
        <?php wp_nonce_field('add-user', '_wpnonce_add-user') ?>

        <table class="form-table">
            <tr class="form-field form-required">
                <th scope="row"><label for="adduser-email"><?php echo $label; ?></label></th>
                <td><input name="email" type="<?php echo $type; ?>" id="adduser-email" class="wp-suggest-user" value="" /></td>
            </tr>
            <tr class="form-field">
                <th scope="row"><label for="adduser-role"><?php _e("Role", 'fau-websso'); ?></label></th>
                <td><select name="role" id="adduser-role">
                    <?php wp_dropdown_roles( get_option('default_role')); ?>
                    </select>
                </td>
            </tr>          
            <?php if (is_super_admin()) { ?>
            <tr>
                <th scope="row"><label for="adduser-noconfirmation"><?php _e("Skip Confirmation Email", 'fau-websso') ?></label></th>
                <td><label for="adduser-noconfirmation"><input type="checkbox" name="noconfirmation" id="adduser-noconfirmation" value="1" /> <?php _e("Add the user without sending an email that requires their confirmation.", 'fau-websso'); ?></label></td>
            </tr>
            <?php } ?>
        </table>
        <?php submit_button( __("Add Existing User", 'fau-websso'), 'primary', 'adduser', TRUE, array('id' => 'addusersub')); ?>
        </form>
        <?php
        } // is_multisite()

        if (current_user_can('create_users')) {
            if ($do_both) {
                echo '<h3 id="create-new-user">' . __("Add New User", 'fau-websso') . '</h3>';
            }
        ?>
        <p><?php _e("Create a brand new user and add them to this website.", 'fau-websso'); ?></p>
        <form action="" method="post" name="createuser" id="createuser" class="validate" novalidate="novalidate">
        <input name="action" type="hidden" value="createuser" />
        <?php wp_nonce_field('create-user', '_wpnonce_create-user'); ?>
        <?php
        $creating = isset($_POST['createuser']);

        $new_user_login = $creating && isset($_POST['user_login']) ? wp_unslash($_POST['user_login']) : '';
        $new_user_email = $creating && isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $new_user_role = $creating && isset($_POST['role']) ? wp_unslash($_POST['role']) : '';
        $new_user_send_password = $creating && isset($_POST['send_password']) ? wp_unslash($_POST['send_password']) : '';
        $new_user_ignore_pass = $creating && isset($_POST['noconfirmation']) ? wp_unslash($_POST['noconfirmation']) : '';
        ?>
        <table class="form-table">
            <tr class="form-field form-required">
                <th scope="row"><label for="user_login"><?php _e("IdM Username", 'fau-websso'); ?> <span class="description"><?php _e("(required)", 'fau-websso'); ?></span></label></th>
                <td><input name="user_login" type="text" id="user_login" value="<?php echo esc_attr($new_user_login); ?>" aria-required="true" /></td>
            </tr>
            <tr class="form-field form-required">
                <th scope="row"><label for="email"><?php _e("Email Address", 'fau-websso'); ?> <span class="description"><?php _e("(required)", 'fau-websso'); ?></span></label></th>
                <td><input name="email" type="email" id="email" value="<?php echo esc_attr( $new_user_email ); ?>" /></td>
            </tr>
            <tr class="form-field">
                <th scope="row"><label for="role"><?php _e("Role", 'fau-websso'); ?></label></th>
                <td><select name="role" id="role">
                    <?php
                    if (!$new_user_role) {
                        $new_user_role = !empty($current_role) ? $current_role : get_option('default_role');
                    }
                    wp_dropdown_roles($new_user_role);
                    ?>
                    </select>
                </td>
            </tr>
            <?php if (is_multisite() && is_super_admin()) { ?>
            <tr>
                <th scope="row"><label for="noconfirmation"><?php _e("Skip Confirmation Email", 'fau-websso') ?></label></th>
                <td><label for="noconfirmation"><input type="checkbox" name="noconfirmation" id="noconfirmation" value="1" <?php checked( $new_user_ignore_pass ); ?> /> <?php _e("Add the user without sending an email that requires their confirmation.", 'fau-websso'); ?></label></td>
            </tr>
            <?php } ?>
        </table>

        <?php submit_button( __("Add New User", 'fau-websso'), 'primary', 'createuser', TRUE, array('id' => 'createusersub')); ?>

        </form>
        <?php } // current_user_can('create_users') ?>
        </div>
        <?php
    }

    public function users_attributes($columns) {
        $columns['attributes'] = __("Attributes", 'fau-websso');
        return $columns;
    }

    public function users_attributes_columns($value, $column_name, $user_id) {

        if ('attributes' != $column_name) {
            return $value;
        }
        
        $attributes = array();

        $edu_person_affiliation = get_user_meta($user_id, 'edu_person_affiliation', TRUE);
        if ($edu_person_affiliation) {
            $attributes[] = $edu_person_affiliation;
        }
        
        $edu_person_entitlement = get_user_meta($user_id, 'edu_person_entitlement', TRUE);
        if ($edu_person_entitlement) {
            $attributes[] = $edu_person_entitlement;
        }
        
        return implode(', ', $attributes);
    }

    public function wpmu_new_user() {
        
    }
    
    private function create_user() {
        global $wp_roles;
        $user = new stdClass;

        if (isset($_POST['user_login'])) {
            $user->user_login = sanitize_user($_POST['user_login'], TRUE);
        }

        if (isset($_POST['role']) && current_user_can('edit_users')) {
            $new_role = sanitize_text_field( $_POST['role'] );
            $potential_role = isset($wp_roles->role_objects[$new_role]) ? $wp_roles->role_objects[$new_role] : FALSE;
            
            if ((is_multisite() && current_user_can('manage_sites')) || ($potential_role && $potential_role->has_cap('edit_users'))) {
                $user->role = $new_role;
            }

            $editable_roles = get_editable_roles();
            if (!empty($new_role) && empty($editable_roles[$new_role])) {
                wp_die(__("You can't give users that role.", 'fau-websso'));
            }
        }

        if (isset($_POST['email'])) {
            $user->user_email = sanitize_text_field(wp_unslash($_POST['email']));
        }
        
        foreach (wp_get_user_contact_methods($user) as $method => $name) {
            if (isset($_POST[$method])) {
                $user->$method = sanitize_text_field($_POST[$method]);
            }
        }

        $user->comment_shortcuts = '';

        $user->use_ssl = 0;
        if (!empty($_POST['use_ssl'])) {
            $user->use_ssl = 1;
        }

        $errors = new WP_Error();

        if ($user->user_login == '') {
            $errors->add( 'user_login', __("<strong>ERROR</strong>: Please enter a username.", 'fau-websso'));
        }

        if (isset( $_POST['user_login']) && !validate_username($_POST['user_login'])) {
            $errors->add('user_login', __("<strong>ERROR</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.", 'fau-websso'));
        }
        
        if (username_exists($user->user_login)) {
            $errors->add('user_login', __("<strong>ERROR</strong>: This username is already registered. Please choose another one.", 'fau-websso'));
        }

        if (empty($user->user_email)) {
            $errors->add('empty_email', __("<strong>ERROR</strong>: Please enter an email address.", 'fau-websso'), array('form-field' => 'email'));
        } elseif (!is_email($user->user_email)) {
            $errors->add('invalid_email', __("<strong>ERROR</strong>: The email address isn't correct.", 'fau-websso'), array('form-field' => 'email'));
        } elseif (email_exists($user->user_email)) {
            $errors->add('email_exists', __("<strong>ERROR</strong>: This email address is already registered, please choose another one.", 'fau-websso'), array( 'form-field' => 'email'));
        }

        if ($errors->get_error_codes()) {
            return $errors;
        }

        $user_id = wp_insert_user($user);

        return $user_id;
    }
    
    private function add_existing_user_notification($user_id) {
        $user = get_userdata($user_id);

        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $roles = get_editable_roles();
        $role = $roles[ $_REQUEST['role'] ];

        $strf = __('Hi,%5$s%5$sYou\'ve been invited to join \'%1$s\' at %2$s with the role of %3$s.%5$s%5$sPlease sign in using the following link to the website:%5$s%4$s', 'fau-websso');
        $message = sprintf($strf, $blogname, home_url(), wp_specialchars_decode(translate_user_role($role['name'])), wp_login_url(), PHP_EOL);

        wp_mail($user->user_email, sprintf(__("[%s] You've been invited", 'fau-websso'), $blogname), $message);       
    }
    
    private function new_user_notification($user_id) {
        $user = get_userdata($user_id);

        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $strf = __('Hi,%4$s%4$sYour user account %1$s has been created.%4$sPlease sign in using the following link to the website %2$s:%4$s%3$s%4$s%4$sThanks!%4$s%4$s--The Team @ %2$s', 'fau-websso');       
        $message = sprintf($strf, $user->user_login, $blogname, wp_login_url(), PHP_EOL);

        wp_mail($user->user_email, sprintf(__("[%s] Your user account", 'fau-websso'), $blogname), $message);
    }
 
    private function invite_user_notification($user_login, $user_email) {
        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $strf = __('Hi,%4$s%4$sYour user account %1$s has been created.%4$sPlease sign in using the following link to the website %2$s:%4$s%3$s%4$s%4$sThanks!%4$s%4$s--The Team @ %2$s', 'fau-websso');       
        $message = sprintf($strf, $user_login, $blogname, wp_login_url(), PHP_EOL);

        wp_mail($user_email, sprintf(__("[%s] Your user account", 'fau-websso'), $blogname), $message);
    }
    
    private function activate_signup($key) {
        global $wpdb;

        $signup = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->signups WHERE activation_key = %s", $key));

        if (empty($signup)) {
            return FALSE;
        }

        if ($signup->active) {
            return FALSE;
        }

        $meta = maybe_unserialize($signup->meta);
        $password = wp_generate_password(12, FALSE);

        $user_id = username_exists($signup->user_login);

        if (!$user_id) {
            $user_id = wpmu_create_user($signup->user_login, $password, $signup->user_email);
        } else {
            $user_already_exists = TRUE;
        }
        
        if (!$user_id) {
            return FALSE;
        }

        $now = current_time('mysql', TRUE);

        if (empty($signup->domain)) {
            $wpdb->update($wpdb->signups, array('active' => 1, 'activated' => $now), array('activation_key' => $key));

            if (isset($user_already_exists)) {
                return FALSE;
            }
            
            wpmu_welcome_user_notification($user_id, $password, $meta);
            do_action('wpmu_activate_user', $user_id, $password, $meta);
            return TRUE;
        }

        $blog_id = wpmu_create_blog($signup->domain, $signup->path, $signup->title, $user_id, $meta, $wpdb->siteid);

        if (is_wp_error($blog_id)) {
            if ('blog_taken' == $blog_id->get_error_code()) {
                $wpdb->update($wpdb->signups, array('active' => 1, 'activated' => $now), array('activation_key' => $key));
            }
            return FALSE;
        }

        $wpdb->update($wpdb->signups, array('active' => 1, 'activated' => $now), array('activation_key' => $key));
        wpmu_welcome_notification($blog_id, $user_id, $password, $signup->title, $meta);
        do_action('wpmu_activate_blog', $blog_id, $user_id, $password, $signup->title, $meta);
        return TRUE;
    }
 
    private function validate_user_signup($user_name, $user_email) {
        global $wpdb;

        $errors = new WP_Error();

        $orig_username = $user_name;
        $user_name = preg_replace('/\s+/', '', sanitize_user($user_name, TRUE));

        if ($user_name != $orig_username || preg_match('/[^a-z0-9]/', $user_name)) {
            $errors->add('user_name', __("Only lowercase letters (a-z) and numbers are allowed.", 'fau-websso'));
            $user_name = $orig_username;
        }

        $user_email = sanitize_email($user_email);

        if (empty($user_name)) {
            $errors->add('user_name', __("Please enter a username.", 'fau-websso'));
        }
        
        $illegal_names = get_site_option('illegal_names');
        if (!is_array($illegal_names)) {
            $illegal_names = array('www', 'web', 'root', 'admin', 'main', 'invite', 'administrator');
            add_site_option('illegal_names', $illegal_names);
        }
        
        if (in_array($user_name, $illegal_names)) {
            $errors->add('user_name', __("Username is not allowed.", 'fau-websso'));
        }
        
        if (is_email_address_unsafe($user_email)) {
            $errors->add('user_email', __("Email Address or Username is not allowed.", 'fau-websso'));
        }
        
        if (strlen($user_name) < 4) {
            $errors->add('user_name', __("The username must be at least 4 characters.", 'fau-websso'));
        }

        if (strlen($user_name) > 60) {
            $errors->add('user_name', __("Username may not be longer than 60 characters.", 'fau-websso'));
        }

        if (strpos($user_name, '_') !== FALSE) {
            $errors->add('user_name', __("Usernames may not contain the underscore character.", 'fau-websso'));
        }
        
        if (preg_match('/^[0-9]*$/', $user_name)) {
            $errors->add('user_name', __("Usernames must have letters too!"), 'fau-websso');
        }
        
        if (!is_email($user_email)) {
            $errors->add('user_email', __("Please enter a valid email address.", 'fau-websso'));
        }
        
        $limited_email_domains = get_site_option('limited_email_domains');
        if (is_array($limited_email_domains) && !empty($limited_email_domains)) {
            $emaildomain = substr($user_email, 1 + strpos($user_email, '@'));
            if (!in_array($emaildomain, $limited_email_domains)) {
                $errors->add('user_email', __("That email address is not allowed!", 'fau-websso'));
            }
        }

        if (username_exists($user_name)) {
            $errors->add('user_name', __("Username already exists!", 'fau-websso'));
        }
        
        if (email_exists($user_email)) {
            $errors->add('user_email', __("The email address is already used!", 'fau-websso'));
        }

        $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->signups WHERE user_login = %s OR user_email = %s", $user_name, $user_email));

        $result = array('user_name' => $user_name, 'orig_username' => $orig_username, 'user_email' => $user_email, 'errors' => $errors);
        return apply_filters('wpmu_validate_user_signup', $result);
    }
    
    // End Case 2
}
