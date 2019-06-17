<?php

namespace RRZE\WebSSO;

defined('ABSPATH') || exit;

class SiteMenu
{
    public static function userNewPage()
    {
        global $submenu;

        remove_submenu_page('users.php', 'user-new.php');

        if (is_multisite()) {
            $capability = 'promote_users';
        } else {
            $capability = 'create_users';
        }

        $submenu_page = add_submenu_page('users.php', __("Add New", 'fau-websso'), __("Add New", 'fau-websso'), $capability, 'usernew', [__CLASS__, 'userNew']);

        add_action(sprintf('load-%s', $submenu_page), [__CLASS__, 'userNewHelp']);

        if (isset($submenu['users.php'])) {
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

    public static function userNewHelp()
    {
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

    public static function userNew()
    {
        $title = __("Add New User", 'fau-websso');

        $do_both = false;
        if (is_multisite() && current_user_can('promote_users') && current_user_can('create_users')) {
            $do_both = true;
        }

        wp_enqueue_script('wp-ajax-response');
        wp_enqueue_script('user-profile');

        if (is_multisite() && current_user_can('promote_users') && !wp_is_large_network('users') && (is_super_admin() || apply_filters('autocomplete_users_for_site_admins', false))) {
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
            } else {
                if ('add' == $_GET['update']) {
                    $messages[] = __("User added.", 'fau-websso');
                }
            }
        } ?>
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
                    } ?>
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
                    } ?>
            </div>
        <?php endif; ?>
        <div id="ajax-response"></div>

        <?php
        if (is_multisite()) {
            if ($do_both) {
                echo '<h3 id="add-existing-user">' . __("Add Existing User", 'fau-websso') . '</h3>';
            }
            if (!is_super_admin()) {
                //echo '<p>' . __("Enter the email address of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.", 'fau-websso') . '</p>';
                //$label = __("Email Address", 'fau-websso');
                //$type  = 'email';
                echo '<p>' . __("Enter the email address or username of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.", 'fau-websso') . '</p>';
                $label = __("Email Address or Username", 'fau-websso');
                $type  = 'text';
            } else {
                echo '<p>' . __("Enter the email address or username of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.", 'fau-websso') . '</p>';
                $label = __("Email Address or Username", 'fau-websso');
                $type  = 'text';
            } ?>
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
                    <?php wp_dropdown_roles(get_option('default_role')); ?>
                    </select>
                </td>
            </tr>
            <?php if (is_super_admin()) {
                ?>
            <tr>
                <th scope="row"><label for="adduser-noconfirmation"><?php _e("Skip Confirmation Email", 'fau-websso') ?></label></th>
                <td><label for="adduser-noconfirmation"><input type="checkbox" name="noconfirmation" id="adduser-noconfirmation" value="1" /> <?php _e("Add the user without sending an email that requires their confirmation.", 'fau-websso'); ?></label></td>
            </tr>
            <?php
            } ?>
        </table>
        <?php submit_button(__("Add Existing User", 'fau-websso'), 'primary', 'adduser', true, array('id' => 'addusersub')); ?>
        </form>
        <?php
        } // is_multisite()

        if (current_user_can('create_users')) {
            if ($do_both) {
                echo '<h3 id="create-new-user">' . __("Add New User", 'fau-websso') . '</h3>';
            } ?>
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
            $new_user_ignore_pass = $creating && isset($_POST['noconfirmation']) ? wp_unslash($_POST['noconfirmation']) : ''; ?>
        <table class="form-table">
            <tr class="form-field form-required">
                <th scope="row"><label for="user_login"><?php _e("IdM Username", 'fau-websso'); ?> <span class="description"><?php _e("(required)", 'fau-websso'); ?></span></label></th>
                <td><input name="user_login" type="text" id="user_login" value="<?php echo esc_attr($new_user_login); ?>" aria-required="true" /></td>
            </tr>
            <tr class="form-field form-required">
                <th scope="row"><label for="email"><?php _e("Email Address", 'fau-websso'); ?> <span class="description"><?php _e("(required)", 'fau-websso'); ?></span></label></th>
                <td><input name="email" type="email" id="email" value="<?php echo esc_attr($new_user_email); ?>" /></td>
            </tr>
            <tr class="form-field">
                <th scope="row"><label for="role"><?php _e("Role", 'fau-websso'); ?></label></th>
                <td><select name="role" id="role">
                    <?php
                    if (!$new_user_role) {
                        $new_user_role = !empty($current_role) ? $current_role : get_option('default_role');
                    }
            wp_dropdown_roles($new_user_role); ?>
                    </select>
                </td>
            </tr>
            <?php if (is_multisite() && is_super_admin()) {
                ?>
            <tr>
                <th scope="row"><label for="noconfirmation"><?php _e("Skip Confirmation Email", 'fau-websso') ?></label></th>
                <td><label for="noconfirmation"><input type="checkbox" name="noconfirmation" id="noconfirmation" value="1" <?php checked($new_user_ignore_pass); ?> /> <?php _e("Add the user without sending an email that requires their confirmation.", 'fau-websso'); ?></label></td>
            </tr>
            <?php
            } ?>
        </table>

        <?php submit_button(__("Add New User", 'fau-websso'), 'primary', 'createuser', true, array('id' => 'createusersub')); ?>

        </form>
        <?php
        } // current_user_can('create_users')?>
        </div>
        <?php
    }
}
