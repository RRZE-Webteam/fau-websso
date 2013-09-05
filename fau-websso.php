<?php
/**
 * Plugin Name: FAU-WebSSO
 * Description: Anmeldung für zentral vergebene Kennungen von Studierenden und Beschäftigten.
 * Version: 2.1
 * Author: Rolf v. d. Forst
 * Author URI: http://blogs.fau.de/webworking/
 * License: GPLv2 or later
 */

/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

add_action( 'plugins_loaded', array( 'FAU_WebSSO', 'init' ) );

register_activation_hook( __FILE__, array( 'FAU_WebSSO', 'activation' ) );

class FAU_WebSSO {

    const version = '2.1'; // Plugin-Version
    
    const option_name = '_fau_websso';

    const version_option_name = '_fau_websso_version';
    
    const option_group = 'fau-websso';
    
    const textdomain = 'fau-websso';
    
    const php_version = '5.3'; // Minimal erforderliche PHP-Version
    
    const wp_version = '3.6'; // Minimal erforderliche WordPress-Version
    
    public static function init() {
        
        $options = self::get_options();
        
        load_plugin_textdomain( self::textdomain, false, sprintf( '%s/lang/', dirname( plugin_basename( __FILE__ ) ) ) );
        
        add_action( 'init', array( __CLASS__, 'update_version' ) );
        
        add_action( 'admin_init', array( __CLASS__, 'admin_init' ));
                
        add_filter( 'authenticate', array( __CLASS__, 'authenticate' ), 10, 3);
        
        add_filter( 'login_url', array( __CLASS__, 'login_url' ), 99, 2);
        
        add_action( 'wp_logout', array( __CLASS__, 'simplesaml_logout' ));
        
        add_filter( 'wp_auth_check_same_domain', '__return_false' );     
        
        if($options['force_websso'])
            self::force_websso();
        else
            add_action( 'login_form', array( __CLASS__, 'login_form' ));
        
        if(is_multisite())
            add_action( 'network_admin_menu', array( __CLASS__, 'network_admin_menu' ));
        else
            add_action( 'admin_menu', array( __CLASS__, 'admin_menu' )); 
     }

    public static function activation($networkwide) {
        self::version_compare();
        
        if(is_multisite()) {
            self::networkwide_validate($networkwide);
            update_site_option( self::version_option_name , self::version );
        } else {
            update_option( self::version_option_name , self::version );
        }
    }
        
    public static function version_compare() {
        $error = '';
        
        if ( version_compare( PHP_VERSION, self::php_version, '<' ) )
            $error = sprintf( __( 'Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', self::textdomain ), PHP_VERSION, self::php_version );

        if ( version_compare( $GLOBALS['wp_version'], self::wp_version, '<' ) )
            $error = sprintf( __( 'Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', self::textdomain ), $GLOBALS['wp_version'], self::wp_version );

        if( ! empty( $error ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ), false, true );
            wp_die( $error );
        }
        
    }
    
    public static function networkwide_validate($networkwide) {
        $error = '';
        
        if ( !$networkwide ) {
            if(is_super_admin())
                $error = __( 'Dieses Plugin muss für alle Webseiten aktiviert werden.', self::textdomain );
            else
                $error = __( 'Sie haben versucht das Plugin zu aktivieren, haben aber nicht die ausreichenden Rechte dazu.', self::textdomain );
        }
        
        if( ! empty( $error ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ), false, true );
            wp_die( $error );
        }
        
    }

    public static function update_version() {
		if( is_multisite() && get_site_option( self::version_option_name, null) != self::version )
            update_site_option( self::version_option_name , self::version );
        elseif( !is_multisite() && get_option( self::version_option_name, null) != self::version )
            update_option( self::version_option_name , self::version );
    }
    
    private static function get_options() {
        $defaults = array(
            'simplesaml_include'     => '/simplesamlphp/lib/_autoload.php',
            'simplesaml_auth_source' => 'default-sp',
            'force_websso' => false
        );
        
        if(is_multisite())
            $options = (array) get_site_option( self::option_name );
        else
            $options = (array) get_option( self::option_name );
        
        $options = wp_parse_args( $options, $defaults );
        $options = array_intersect_key( $options, $defaults );

        return $options;
    }
        
    public static function authenticate($user, $user_login, $user_pass) {
        
        if(is_a($user, 'WP_User'))
            return $user;
        
        $options = self::get_options();

        remove_action('authenticate', 'wp_authenticate_username_password', 20);
        
        $action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
        
        if( ! $options['force_websso'] && $action != 'websso' )      
            return wp_authenticate_username_password(null, $user_login, $user_pass);
        
        require_once(WP_CONTENT_DIR . $options['simplesaml_include']);

        $as = new SimpleSAML_Auth_Simple($options['simplesaml_auth_source']);
        
        if(!$as->isAuthenticated())
            $as->requireAuth();
        
        $attributes = array();

        $_attributes = $as->getAttributes();
        
        if( !empty($_attributes)) {
            $attributes['cn'] = isset( $_attributes['urn:mace:dir:attribute-def:cn'][0] ) ? $_attributes['urn:mace:dir:attribute-def:cn'][0] : '';
            $attributes['sn'] = isset( $_attributes['urn:mace:dir:attribute-def:sn'][0] ) ? $_attributes['urn:mace:dir:attribute-def:sn'][0] : '';
            $attributes['givenname'] = isset( $_attributes['urn:mace:dir:attribute-def:givenname'][0] ) ? $_attributes['urn:mace:dir:attribute-def:givenname'][0] : '';
            $attributes['displayname'] = isset( $_attributes['urn:mace:dir:attribute-def:displayname'][0] ) ? $_attributes['urn:mace:dir:attribute-def:displayname'][0] : '';
            $attributes['uid'] = isset( $_attributes['urn:mace:dir:attribute-def:uid'][0] ) ? $_attributes['urn:mace:dir:attribute-def:uid'][0] : '';
            $attributes['mail'] = isset( $_attributes['urn:mace:dir:attribute-def:mail'][0] ) ? $_attributes['urn:mace:dir:attribute-def:mail'][0] : '';
            $attributes['eduPersonAffiliation'] = isset( $_attributes['urn:mace:dir:attribute-def:eduPersonAffiliation'][0] ) ? $_attributes['urn:mace:dir:attribute-def:eduPersonAffiliation'][0] : '';   
            $attributes['eduPersonEntitlement'] = isset( $_attributes['urn:mace:dir:attribute-def:eduPersonEntitlement'][0] ) ? $_attributes['urn:mace:dir:attribute-def:eduPersonEntitlement'][0] : '';        
        }
                
        if(empty($attributes))
            return self::simplesaml_login_error(__('Die Benutzerattribute fehlen.', self::textdomain));
        
        $user_login = $attributes['uid'];

        if($user_login != substr(sanitize_user($user_login, true), 0, 60))
            return self::simplesaml_login_error(__('Eingegebene Text ist nicht geeignet als Benutzername.', self::textdomain));           
                
        $user_email = $attributes['mail'];
        $first_name  = $attributes['givenname'];
        $last_name  = $attributes['sn'];

        $display_name = empty($first_name) ? $attributes['displayname'] : sprintf( '%s %s ', $first_name, $last_name );

        $edu_person_affiliation = $attributes['eduPersonAffiliation'];
        $edu_person_entitlement = $attributes['eduPersonEntitlement'];

        $userdata = get_user_by( 'login', $user_login );

        if ($userdata) {
            
            if($userdata->user_email == $user_email) {
                $user = new WP_User($userdata->ID);
                update_user_meta( $userdata->ID, 'edu_person_affiliation', $edu_person_affiliation );
                update_user_meta( $userdata->ID, 'edu_person_entitlement', $edu_person_entitlement );                
            } else {
                return self::simplesaml_login_error(__('Die Benutzerdaten sind nicht im Einklang mit den lokalen Daten.', self::textdomain));
            }
            
        } else {

            if( ! get_site_option( 'users_can_register')) {
                return self::simplesaml_login_error(__('Zurzeit ist die Benutzer-Registrierung nicht erlaubt.', self::textdomain));

            } else {
                $account_data = array(
                    'user_pass'     => microtime(),
                    'user_login'    => $user_login,
                    'user_nicename' => $user_login,
                    'user_email'    => $user_email,
                    'display_name'  => $display_name,
                    'first_name'    => $first_name,
                    'last_name'     => $last_name
                    );

                $user_id = wp_insert_user($account_data);

                if( is_wp_error( $user_id ) ) {
                    return self::simplesaml_login_error(__('Die Benutzer-Registrierung ist fehlgeschlagen.', self::textdomain));                                                    
                } else {
                    $user = new WP_User($user_id);
                    update_user_meta( $user_id, 'edu_person_affiliation', $edu_person_affiliation );
                    update_user_meta( $user_id, 'edu_person_entitlement', $edu_person_entitlement );
                }
            }
        }
        
        return $user;

    }
        
    public static function login_url($login_url, $redirect) {
        $login_url = site_url('wp-login.php', 'login');

        if ( !empty($redirect) )
            $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);

        return $login_url;
    }
    
    public static function simplesaml_logout() {
        global $as;

        if (!isset($as)) {
            $options = self::get_options();
            require_once(WP_CONTENT_DIR . $options['simplesaml_include']);
            $as = new SimpleSAML_Auth_Simple($options['simplesaml_auth_source']);
        }

        $as->logout(get_option('siteurl'));
    }
    
    private static function simplesaml_login_error($message) {
        $output = '';
        
        $output .= sprintf('<p><strong>%1$s</strong>: %2$s</p>', __( 'Fehler', self::textdomain ), $message);
        $output .= sprintf('<p>%s</p>', __( 'Die Anmeldung ist fehlgeschlagen.', self::textdomain ));
        $output .= sprintf('<p>%s</p>', __( 'Sollte dennoch keine Anmeldung möglich sein, dann wenden Sie sich bitte an den Ansprechpartner der Webseite.', self::textdomain ));

        $output .= self::get_contact();
        
        wp_die( $output );
    }
    
    private static function get_contact() {
        global $wpdb;

        $blog_prefix = $wpdb->get_blog_prefix( get_current_blog_id() );
        $users = $wpdb->get_results(
            "SELECT user_id, user_id AS ID, user_login, display_name, user_email, meta_value
             FROM $wpdb->users, $wpdb->usermeta
             WHERE {$wpdb->users}.ID = {$wpdb->usermeta}.user_id AND meta_key = '{$blog_prefix}capabilities'
             ORDER BY {$wpdb->usermeta}.user_id" );

        if( empty( $users ) )
            return '';
        
        $output = sprintf( '<h3>%s</h3>'."\n", sprintf( __( 'Ansprechpartner und Kontakt für die &bdquo;%1$s&ldquo;-Webseite', self::textdomain ), get_bloginfo( 'name' ) ) );
        
        foreach( $users as $user ) {
            $roles = unserialize($user->meta_value);
            if( isset( $roles['administrator'] ) ) {
                $output .= sprintf( '<p>%1$s<br/>%2$s %3$s</p>'."\n", $user->display_name, __( 'E-Mail:', self::textdomain ), make_clickable( $user->user_email ) );
            }
        }

        return $output;
    }
    
    private static function force_websso() {
        add_action( 'lost_password', array( __CLASS__, 'disable_function' ) );
        add_action( 'retrieve_password', array( __CLASS__, 'disable_function' ) );
        add_action( 'password_reset', array( __CLASS__, 'disable_function' ) );  

        add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap_filter' ), 10, 2 );

        add_filter( 'show_password_fields', '__return_false' );

        if(is_multisite() && current_user_can( 'promote_users' ) ) {
            add_action( 'admin_init', array( __CLASS__, 'add_user_request' ) );          
            add_action( 'admin_menu', array( __CLASS__, 'add_user_menu' ) );
        } else {
            add_action( 'admin_menu', function() {
                remove_submenu_page( 'users.php', 'user-new.php' );
            });
        }        
    }
    
    public static function map_meta_cap_filter( $caps, $cap ) {

        foreach( $caps as $key => $capability ) {
            if( $cap == 'create_users' )
                $caps[$key] = 'do_not_allow';
        }

        return $caps;
        
    }
    
    public static function add_user_menu() {
        global $submenu;
        
        remove_submenu_page( 'users.php', 'user-new.php' );
        
        $submenu_page = add_submenu_page( 'users.php', __( 'Neu hinzufügen', self::textdomain ), __( 'Neu hinzufügen', self::textdomain ), 'promote_users', 'user-new', array( __CLASS__, 'add_user_page' ) );
        
        add_action( sprintf( 'load-%s', $submenu_page ), array( __CLASS__, 'add_user_help_tab' ) );        
        
        foreach( $submenu['users.php'] as $key => $value ) {
            if( $value == __( 'Neu hinzufügen', self::textdomain ) )
                break;
        }
        
        $submenu['users.php'][10] = $submenu['users.php'][$key];
        unset( $submenu['users.php'][$key] );
        
        ksort( $submenu['users.php'] );
    }
    
    public static function add_user_help_tab() {
        $help = '<p>' . __( 'Um einen neuen Benutzer zu Ihrer Webseite hinzufügen, füllen Sie das Formular auf dieser Seite aus, und klicken Sie unten auf Neuen Benutzer hinzufügen.', self::textdomain ) . '</p>';
        $help .= '<p>' . __( 'Vergessen Sie nicht, unten auf dieser Seite auf Neuen Benutzer hinzufügen zu klicken, wenn Sie fertig sind.', self::textdomain ) . '</p>';

        get_current_screen()->add_help_tab( array(
            'id'      => 'overview',
            'title'   => __( 'Übersicht', self::textdomain ),
            'content' => $help,
        ) );

        get_current_screen()->add_help_tab( array(
        'id'      => 'user-roles',
        'title'   => __( 'Benutzerrollen', self::textdomain ),
        'content' => '<p>' . __( 'Hier ist ein grober Überblick über die verschiedenen Benutzerrollen und die jeweils damit verknüpften Berechtigungen:', self::textdomain ) . '</p>' .
                     '<ul>' .
                     '<li>' . __( 'Administratoren haben die komplette Macht und sehen alle Optionen.', self::textdomain ) . '</li>' .
                     '<li>' . __( 'Redakteure können Artikel und Seiten anlegen und veröffentlichen, sowie die Artikel, Seiten, etc. von anderen Benutzern verwalten (ändern, löschen, veröffentlichen).', self::textdomain ) . '</li>' .
                     '<li>' . __( 'Autoren können ihre eigenen Artikel veröffentlichen und verwalten sowie Dateien hochladen.', self::textdomain ) . '</li>' .
                     '<li>' . __( 'Mitarbeiter können eigene Artikel schreiben und bearbeiten, sie jedoch nicht veröffentlichen. Auch dürfen sie keine Dateien hochladen.', self::textdomain ) . '</li>' .
                     '<li>' . __( 'Abonennten können nur Kommentare lesen und abgeben, aber keine eigenen Inhalte erstellen.', self::textdomain ) . '</li>' .
                     '</ul>'
        ) );

    }
    
    public static function add_user_request() {
        global $blog_id, $pagenow;
        
        if( $pagenow == 'site-users.php' )
            return;
        
        if ( $pagenow == 'user-new.php' ) {
            wp_redirect( 'users.php?page=user-new' );
            die();
        }
        
        if ( isset( $_REQUEST['action'] ) && 'adduser' == $_REQUEST['action'] ) {

            if ( ! current_user_can( 'promote_user' ) )
                wp_die( __( 'Schummeln, was?', self::textdomain ) );
            
            check_admin_referer( 'add-user', '_wpnonce_add-user' );
            
            $options = self::get_options();
            
            $blog_id = get_current_blog_id();
            $redirect = 'users.php?page=user-new';
            
            if ( empty( $blog_id ) ) {
                wp_redirect( $redirect );
                die();
            }
            
            $user_login = isset( $_REQUEST['newuser'] ) ? trim( $_REQUEST['newuser'] ) : '';
            
            $default_role = get_option( 'default_role' );
            $role = isset( $_REQUEST['new_role'] ) ? trim( $_REQUEST['new_role'] ) : $default_role;
            $role = ! is_null( get_role( $role ) ) ? $role : $default_role;
            
            $userdata = get_user_by( 'login', $user_login );
            
            if( $userdata ) {
                
                $user_id = $userdata->ID;
                
                if ( array_key_exists( $blog_id, get_blogs_of_user( $user_id ) ) ) {
                    $redirect = add_query_arg( array( 'update' => 'err_add_member' ), $redirect );
                } else {
                    add_existing_user_to_blog( array( 'user_id' => $user_id, 'role' => $role ) );
                    $redirect = add_query_arg( array('update' => 'adduser'), $redirect );
                }
                
            } else {
                $redirect = add_query_arg( array('update' => 'err_add_notfound'), $redirect );
            }
            
            wp_redirect( $redirect );
            die();
                   
        }
        
    }
    
    public static function add_user_page() {
        
        if ( isset( $_GET['update'] ) ) {
            $messages = array();
            switch ( $_GET['update'] ) {
                case 'adduser':
                    $messages[] = sprintf( '<div id="message" class="updated"><p>%s</p></div>', __( 'Der Benutzer wurde zu Ihre Webseite hinzugefügt.', self::textdomain ) );
                    break;
                case 'err_add_member':
                    $messages[] = sprintf( '<div id="message" class="error"><p>%s</p></div>', __( 'Dieser Benutzer ist bereits ein Mitglied dieser Webseite.', self::textdomain ) );
                    break;
                case 'err_add_notfound':
                    $messages[] = sprintf( '<div id="message" class="error"><p>%s</p></div>', __( 'Geben Sie den Benutzernamen eines bestehenden Benutzers ein.', self::textdomain ) );
                    break;                
            }
        }
        ?>
        <div class="wrap">
            <div id="icon-users" class="icon32">
                <br />
            </div>
            <h2><?php _e( 'Neuen Benutzer hinzufügen', self::textdomain ); ?></h2>
            <?php
            if ( ! empty( $messages ) )
                foreach ( $messages as $_message )
                    echo $_message;
            ?>            
            <h3 id="add-existing-user"><?php _e( 'Benutzer hinzufügen', self::textdomain ) ?></h3>
            
            <p><?php _e( 'Tragen Sie die Benutzerkennung eines bestehenden Nutzers ein um ihn zu dieser Webseite hinzufügen.', self::textdomain ); ?></p>

            <form action="" method="post" name="adduser" id="adduser">
                <input name="action" type="hidden" value="adduser" />
                <?php wp_nonce_field( 'add-user', '_wpnonce_add-user' ) ?>

                <table class="form-table">
                    <tr class="form-field form-required">
                        <th scope="row"><label for="addusername"><?php _e( 'Benutzerkennung', self::textdomain ) ?></label></th>
                        <td><input name="newuser" type="text" id="addusername" class="wp-suggest-user"  style="width: 10em;" value="" /></td>
                    </tr>
                    <tr class="form-field">
                        <th scope="row"><label for="adduser-role"><?php _e( 'Rolle', self::textdomain ) ?></label></th>
                        <td><select name="new_role" id="adduser-role">
                            <?php wp_dropdown_roles( get_option( 'default_role' ) ); ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Neuen Benutzer hinzufügen', self::textdomain ), 'primary', 'adduser-submit', true, array( 'id' => 'adduser-submit' ) ); ?>
                
            </form>

        </div>
    <?php
    }
    
    public static function network_admin_menu() {
        add_submenu_page( 'settings.php', __( 'FAU-WebSSO', self::textdomain ), __( 'FAU-WebSSO', self::textdomain ), 'manage_network_options', self::option_group, array( __CLASS__, 'network_options_page' ) );        
    }
    
    public static function admin_menu() {
        add_options_page( __('FAU-WebSSO', self::textdomain), __('FAU-WebSSO', self::textdomain), 'manage_options', self::option_group, array( __CLASS__, 'options_page' ) );        
    }

    public static function network_options_page() {
        if ( !is_super_admin() )
            wp_die( __( 'Schummeln, was?', self::textdomain ) );

        if( !empty( $_POST[self::option_name] )) {
            check_admin_referer(self::option_group . '-options');
            $options = self::get_options();
            $input = self::options_validate($_POST[self::option_name]);
            if($options !== $input)
                update_site_option( self::option_name, $input );
        }
        
        if ( isset($_POST['action']) && $_POST['action'] == 'update') {
            ?><div id="message" class="updated"><p><?php _e( 'Einstellungen gespeichert.', self::textdomain ) ?></p></div><?php
        }        
        ?>
        <div class="wrap">
            <?php screen_icon('options-general'); ?>
            <h2><?php echo esc_html( __( 'Einstellungen &rsaquo; FAU-WebSSO', self::textdomain) ); ?></h2>
            <form method="post">
                <?php   
                do_settings_sections( self::option_group );
                settings_fields( self::option_group );
                submit_button();
                ?>
            </form>
        </div>
        <?php

    }

    public static function options_page() {
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php echo esc_html( __( 'Einstellungen &rsaquo; FAU-WebSSO', self::textdomain) ); ?></h2>
            <form method="post" action="options.php">
                <?php             
                do_settings_sections( self::option_group );
                settings_fields( self::option_group );
                submit_button();
                ?>
            </form>
        </div>
        <?php

    }
    
    public static function admin_init() {        

        if(!is_multisite())
            register_setting( self::option_group, self::option_name, array( __CLASS__, 'options_validate' ) );

        add_settings_section( 'websso_options_section', false, array( __CLASS__, 'websso_settings_section' ), self::option_group );
        add_settings_field( 'force_websso', __('Zum SSO zwingen', self::textdomain), array( __CLASS__, 'force_websso_field' ), self::option_group, 'websso_options_section' );
        
        add_settings_section( 'simplesaml_options_section', false, array( __CLASS__, 'simplesaml_settings_section' ), self::option_group );
        add_settings_field( 'simplesaml_include', __('Autoload-Pfad', self::textdomain), array( __CLASS__, 'simplesaml_include_field' ), self::option_group, 'simplesaml_options_section' );
        add_settings_field( 'simplesaml_auth_source', __('Authentifizierungsquelle', self::textdomain), array( __CLASS__, 'simplesaml_auth_source_field' ), self::option_group, 'simplesaml_options_section' );
    }

    public static function websso_settings_section() {
        echo '<h3 class="title">' . __('Single Sign-On', self::textdomain) . '</h3>';
        echo '<p>' . __('Allgemeine SSO-Einstellungen.', self::textdomain) . '</p>';
    }

    public static function force_websso_field() {
        $options = self::get_options();
        echo '<input type="checkbox" id="force_websso" ', checked( $options['force_websso'], true ), ' name="' . self::option_name . '[force_websso]">';
    }
    
    public static function simplesaml_settings_section() {
        echo '<h3 class="title">' . __('SimpleSAMLphp', self::textdomain) . '</h3>';
        echo '<p>' . __('Einstellungen des Service Provider.', self::textdomain) . '</p>';
    }

    public static function simplesaml_include_field() {
        $options = self::get_options();   
        echo '<input type="text" id="simplesaml_include" class="regular-text ltr" name="' . self::option_name . '[simplesaml_include]" value="' . esc_attr( $options['simplesaml_include'] ) . '">';
        echo '<p class="description">' . __('Relative Pfad ausgehend vom wp-content-Verzeichnis.', self::textdomain) . '</p>';
    }

    public static function simplesaml_auth_source_field() {
        $options = self::get_options();   
        echo '<input type="text" id="simplesaml_auth_source" class="regular-text ltr" name="' . self::option_name . '[simplesaml_auth_source]" value="' . esc_attr( $options['simplesaml_auth_source'] ) . '">';
    }

    public static function options_validate( $input ) {
        $options = self::get_options();

        $input['force_websso'] = !empty($input['force_websso']) ? true : false;
        
        $input['simplesaml_include'] = isset($input['simplesaml_include']) && file_exists(esc_attr($input['simplesaml_include'])) ? esc_attr($input['simplesaml_include']) : $options['simplesaml_include'];
        
        $input['simplesaml_auth_source'] = isset($input['simplesaml_auth_source']) ? esc_attr($input['simplesaml_auth_source']) : $options['simplesaml_auth_source'];

        return $input;
    }
    
    public static function login_form() {
        $login_url = add_query_arg('action', 'websso', home_url('/wp-login.php'));
        printf('<p><a href="%s">' . __('Anmeldung über Single Sign-On', self::textdomain) . '</a> ' . __('(zentraler Anmeldedienst der Universität Erlangen-Nürnberg). Anmeldung für zentral-vergebene Kennungen von Studierenden und Beschäftigten der Universität Erlangen-Nürnberg.', self::textdomain) . '</p>', $login_url);
    }
    
    public static function is_user_logged_in() {
        global $as;

        $user = wp_get_current_user();
        
        if ( ! $user->exists() )
            return false;

        $options = self::get_options();
        
        if (!isset($as)) {
            require_once(WP_CONTENT_DIR . $options['simplesaml_include']);
            $as = new SimpleSAML_Auth_Simple($options['simplesaml_auth_source']);
        }
        
        if(!$as->isAuthenticated() && $options['force_websso']) {
            wp_logout();
            return false;
        }
        
        return true;

    }
    
}

function is_user_logged_in() {
    return FAU_WebSSO::is_user_logged_in();
}
