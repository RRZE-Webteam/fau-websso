<?php

namespace RRZE\WebSSO;

defined('ABSPATH') || exit;

use RRZE\WebSSO\Options;
use WP_Error;

class Users
{
    public static function userNewAction()
    {
        global $wpdb;

        if (isset($_REQUEST['action']) && 'add-user' == $_REQUEST['action']) {
            check_admin_referer('add-user', '_wpnonce_add-user');

            if (!is_array($_POST['user'])) {
                wp_die(__("Cannot create an empty user.", 'fau-websso'));
            }

            $user = wp_unslash($_POST['user']);

            $user_details = self::validateUserSignup($user['username'], $user['email']);
            if (is_wp_error($user_details['errors']) && !empty($user_details['errors']->errors)) {
                $add_user_errors = base64_encode(serialize($user_details['errors']));
                $redirect = add_query_arg(array('page' => 'usernew', 'update' => 'addusererrors', 'error' => $add_user_errors), 'users.php');
            } else {
                $password = wp_generate_password(12, false);
                $user_id = wpmu_create_user(esc_html(strtolower($user['username'])), $password, sanitize_email($user['email']));

                if (!$user_id) {
                    $add_user_errors = new WP_Error('add_user_fail', __("The user could not be added.", 'fau-websso'));
                    $redirect = add_query_arg(array('page' => 'usernew', 'error' => base64_encode(serialize($add_user_errors))), 'users.php');
                } else {
                    self::newUserNotification($user_id);
                    $redirect = add_query_arg(array('page' => 'usernew', 'update' => 'added'), 'users.php');
                }
            }
            wp_redirect($redirect);
            exit;
        } elseif (isset($_REQUEST['action'], $_REQUEST['email']) && 'adduser' == $_REQUEST['action']) {
            check_admin_referer('add-user', '_wpnonce_add-user');

            $user_details = null;
            $user_email = wp_unslash($_REQUEST['email']);
            if (strpos($user_email, '@') !== false) {
                $user_details = get_user_by('email', $user_email);
            } else {
                if (is_super_admin()) {
                    $user_details = get_user_by('login', $user_email);
                } else {
                    //wp_redirect(add_query_arg(array('page' => 'usernew', 'update' => 'enter_email'), 'users.php'));
                    //exit;
                    $user_details = get_user_by('login', $user_email);
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

            if (($username != null && !is_super_admin($user_id)) && (array_key_exists(get_current_blog_id(), get_blogs_of_user($user_id)))) {
                $redirect = add_query_arg(array('page' => 'usernew', 'update' => 'addexisting'), 'users.php');
            } else {
                add_existing_user_to_blog(array('user_id' => $user_id, 'role' => $_REQUEST[ 'role' ]));
                if (isset($_POST['noconfirmation']) && is_super_admin()) {
                    $redirect = add_query_arg(array('page' => 'usernew', 'update' => 'addnoconfirmation'), 'users.php');
                } else {
                    self::addExistingUserNotification($user_id);
                    $redirect = add_query_arg(array('page' => 'usernew', 'update' => 'add'), 'users.php');
                }
            }
            wp_redirect($redirect);
            exit;
        } elseif (isset($_REQUEST['action']) && 'createuser' == $_REQUEST['action']) {
            check_admin_referer('create-user', '_wpnonce_create-user');

            if (!is_multisite()) {
                $user_id = self::createUser();

                if (is_wp_error($user_id)) {
                    $add_user_errors = $user_id;
                    $redirect = add_query_arg(array('page' => 'usernew', 'error' => base64_encode(serialize($add_user_errors))), 'users.php');
                } else {
                    self::newUserNotification($user_id);

                    if (current_user_can('list_users')) {
                        $redirect = add_query_arg(array('update' => 'add', 'id' => $user_id), 'users.php');
                    } else {
                        $redirect = add_query_arg(array('page' => 'usernew', 'update' => 'add'), 'users.php');
                    }
                    wp_redirect($redirect);
                    exit;
                }
            } else {
                // Neuen Benutzer hinzufügen
                $new_user_email = wp_unslash($_REQUEST['email']);
                $user_details = self::validateUserSignup($_REQUEST['user_login'], $new_user_email);

                if (is_wp_error($user_details['errors']) && !empty($user_details['errors']->errors)) {
                    $add_user_errors = $user_details[ 'errors' ];
                    $redirect = add_query_arg(array('page' => 'usernew', 'error' => base64_encode(serialize($add_user_errors))), 'users.php');
                } else {
                    $new_user_login = sanitize_user(wp_unslash($_REQUEST['user_login']), true);

                    wpmu_signup_user($new_user_login, $new_user_email, array('add_to_blog' => $wpdb->blogid, 'new_role' => $_REQUEST['role']));

                    if (is_super_admin()) {
                        $key = $wpdb->get_var($wpdb->prepare("SELECT activation_key FROM {$wpdb->signups} WHERE user_login = %s AND user_email = %s", $new_user_login, $new_user_email));
                        $signup = wpmu_activate_signup($key);

                        if (is_wp_error($signup)) {
                            $add_user_errors = $signup;
                            $redirect = add_query_arg(array('page' => 'usernew', 'error' => base64_encode(serialize($add_user_errors))), 'users.php');
                        }
                    }

                    if (isset($_POST['noconfirmation']) && is_super_admin()) {
                        $redirect = add_query_arg(array('page' => 'usernew', 'update' => 'addnoconfirmation'), 'users.php');
                    } else {
                        self::inviteUserNotification($new_user_login, $new_user_email);
                        $redirect = add_query_arg(array('page' => 'usernew', 'update' => 'newuserconfirmation'), 'users.php');
                    }
                }
            }
            wp_redirect($redirect);
            exit;
        }
    }

    protected static function createUser()
    {
        global $wp_roles;
        $user = new \stdClass;

        if (isset($_POST['user_login'])) {
            $user->user_login = sanitize_user($_POST['user_login'], true);
        }

        if (isset($_POST['role']) && current_user_can('edit_users')) {
            $new_role = sanitize_text_field($_POST['role']);
            $potential_role = isset($wp_roles->role_objects[$new_role]) ? $wp_roles->role_objects[$new_role] : false;

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
            $errors->add('user_login', __("<strong>ERROR</strong>: Please enter a username.", 'fau-websso'));
        }

        if (isset($_POST['user_login']) && !validate_username($_POST['user_login'])) {
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

    protected static function addExistingUserNotification($user_id)
    {
        $user = get_userdata($user_id);

        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $roles = get_editable_roles();
        $role = $roles[ $_REQUEST['role'] ];

        $strf = __('Hi,%5$s%5$sYou\'ve been invited to join \'%1$s\' at %2$s with the role of %3$s.%5$s%5$sPlease sign in using the following link to the website:%5$s%4$s', 'fau-websso');
        $message = sprintf($strf, $blogname, home_url(), wp_specialchars_decode(translate_user_role($role['name'])), wp_login_url(), PHP_EOL);

        wp_mail($user->user_email, sprintf(__("[%s] You've been invited", 'fau-websso'), $blogname), $message);
    }

    protected static function newUserNotification($user_id)
    {
        $options = Options::getOptions();
        
        $password = bin2hex(random_bytes(4));
        wp_set_password($password, $user_id);

        $user = get_userdata($user_id);
        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $strf = __('Hi,%4$s%4$sYour user account %1$s has been created.%4$sPlease sign in using the following link to the website:%4$s%3$s%4$s', 'fau-websso');
        if ($options->send_new_user_password) {
            $strf .= __('Password: %5$s%4$s', 'fau-websso');
        }
        $strf .= __('%4$sThanks!%4$s%4$s--The Team @ %2$s', 'fau-websso');
        $message = sprintf($strf, $user->user_login, $blogname, wp_login_url(), PHP_EOL, $password);
        wp_mail($user->user_email, sprintf(__("[%s] Your user account", 'fau-websso'), $blogname), $message);
    }

    protected static function inviteUserNotification($user_login, $user_email)
    {
        $options = Options::getOptions();

        $user = get_user_by('login', $user_login);
        $password = bin2hex(random_bytes(4));
        wp_set_password($password, $user->ID);

        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $strf = __('Hi,%4$s%4$sYour user account %1$s has been created.%4$sPlease sign in using the following link to the website:%4$s%3$s%4$s', 'fau-websso');
        if ($options->send_new_user_password) {
            $strf .= __('Password: %5$s%4$s', 'fau-websso');
        }
        $strf .= __('%4$sThanks!%4$s%4$s--The Team @ %2$s', 'fau-websso');        
        $message = sprintf($strf, $user_login, $blogname, wp_login_url(), PHP_EOL, $password);
        wp_mail($user_email, sprintf(__("[%s] Your user account", 'fau-websso'), $blogname), $message);
    }

    public static function activateSignup($key)
    {
        global $wpdb;

        $signup = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->signups WHERE activation_key = %s", $key));

        if (empty($signup)) {
            return false;
        }

        if ($signup->active) {
            return false;
        }

        $meta = maybe_unserialize($signup->meta);
        $password = wp_generate_password(12, false);

        $user_id = username_exists($signup->user_login);

        if (!$user_id) {
            $user_id = wpmu_create_user($signup->user_login, $password, $signup->user_email);
        } else {
            $user_already_exists = true;
        }

        if (!$user_id) {
            return false;
        }

        $now = current_time('mysql', true);

        if (empty($signup->domain)) {
            $wpdb->update($wpdb->signups, array('active' => 1, 'activated' => $now), array('activation_key' => $key));

            if (isset($user_already_exists)) {
                return false;
            }

            wpmu_welcome_user_notification($user_id, $password, $meta);
            do_action('wpmu_activate_user', $user_id, $password, $meta);
            return true;
        }

        $blog_id = wpmu_create_blog($signup->domain, $signup->path, $signup->title, $user_id, $meta, $wpdb->siteid);

        if (is_wp_error($blog_id)) {
            if ('blog_taken' == $blog_id->get_error_code()) {
                $wpdb->update($wpdb->signups, array('active' => 1, 'activated' => $now), array('activation_key' => $key));
            }
            return false;
        }

        $wpdb->update($wpdb->signups, array('active' => 1, 'activated' => $now), array('activation_key' => $key));
        wpmu_welcome_notification($blog_id, $user_id, $password, $signup->title, $meta);
        do_action('wpmu_activate_blog', $blog_id, $user_id, $password, $signup->title, $meta);
        return true;
    }

    protected static function validateUserSignup($user_name, $user_email)
    {
        global $wpdb;

        $errors = new WP_Error();

        $orig_username = $user_name;
        $user_name = preg_replace('/\s+/', '', sanitize_user($user_name, true));

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

        if (strpos($user_name, '_') !== false) {
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
        
        $options = Options::getOptions();
        $allowedUserEmailDomains = $options->allowed_user_email_domains;
        if (is_array($allowedUserEmailDomains) && !empty($allowedUserEmailDomains)) {
            $emaildomain = substr($user_email, 1 + strpos($user_email, '@'));
            if (!in_array($emaildomain, $allowedUserEmailDomains)) {
                $errors->add('user_email', sprintf(__("That email address domain is not allowed! Allowed domains: %s", 'fau-websso'), implode(', ', $allowedUserEmailDomains)));
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

}
