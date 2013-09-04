<?php
/**
 * Plugin Name: FAU-WebSSO
 * Description: Anmeldung für zentral vergebene Kennungen von Studierenden und Beschäftigten.
 * Version: 2.0
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

    const version = '2.0'; // Plugin-Version
    
    const option_name = '_fau_websso';

    const version_option_name = '_fau_websso_version';
    
    const textdomain = 'fau-websso';
    
    const php_version = '5.3'; // Minimal erforderliche PHP-Version
    
    const wp_version = '3.6'; // Minimal erforderliche WordPress-Version
    
    public static function init() {
        
        $options = self::get_options();
        
        load_plugin_textdomain( self::textdomain, false, sprintf( '%slang', plugin_dir_path( __FILE__ ) ) );
        
        add_action( 'init', array( __CLASS__, 'update_version' ) );
                
        add_filter( 'authenticate', array( __CLASS__, 'authenticate' ), 10, 3);
        
        add_filter( 'login_url', array( __CLASS__, 'login_url' ), 99, 2);
        
        add_action( 'wp_logout', array( __CLASS__, 'simplesaml_logout' ));
        
        add_filter( 'wp_auth_check_same_domain', '__return_false' );     
        
        if($options['force_websso']) {
            add_action( 'lost_password', array( __CLASS__, 'disable_function' ) );
            add_action( 'retrieve_password', array( __CLASS__, 'disable_function' ) );
            add_action( 'password_reset', array( __CLASS__, 'disable_function' ) );            
        } else {
            add_action( 'login_form', array( __CLASS__, 'login_form' ));            
        }
        
        if(is_multisite())
            add_action( 'network_admin_menu', array( __CLASS__, 'network_admin_menu' ));
        else
            add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ));
        
        add_action( 'admin_init', array( __CLASS__, 'admin_init' )); 
     }

    public static function activation($networkwide) {
        self::version_compare();
        
        self::try_networkwide($networkwide);
        
        update_option( self::version_option_name , self::version );
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
    
    public static function try_networkwide($networkwide) {
        $error = '';
        
        if ( is_multisite() && !$networkwide ) {
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
		if( get_option( self::version_option_name, null) != self::version )
			update_option( self::version_option_name , self::version );
    }
    
    private static function get_options() {
        $defaults = array(
            'simplesaml_include'     => '/simplesamlphp/lib/_autoload.php',
            'simplesaml_auth_source' => 'default-sp',
            'force_websso' => false
        );

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
            if($userdata->user_email == $user_email && ( get_user_meta($userdata->ID, 'edu_person_affiliation') || get_user_meta($userdata->ID, 'edu_person_entitlement')))
                $user = new WP_User($userdata->ID);
            else
                return self::simplesaml_login_error(__('Die Benutzerdaten sind nicht im Einklang mit den lokalen Daten.', self::textdomain));
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
    
    private static function get_contact(){
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
    
    public static function network_admin_menu() {
        add_submenu_page( 'settings.php', __( 'FAU-WebSSO', self::textdomain ), __( 'FAU-WebSSO', self::textdomain ), 'manage_options', 'fau-websso-options', array( __CLASS__, 'network_options_page' ) );        
    }
        
    public static function admin_menu() {
        add_options_page( __('FAU-WebSSO', self::textdomain), __('FAU-WebSSO', self::textdomain), 'manage_options', 'fau-websso-options', array( __CLASS__, 'options_page' ) );        
    }

    public static function network_options_page() {
        if( isset( $_POST[self::option_name] )) {
            if ( !is_super_admin() )
                wp_die( __( 'Schummeln, was?', self::textdomain ) );			

            $options = self::get_options();
            $input = self::options_validate($_POST[self::option_name]);
            if($options !== $input)
                update_option( self::option_name, $input );
        }
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php echo esc_html( __( 'Einstellungen &rsaquo; FAU-WebSSO', self::textdomain) ); ?></h2>
            <form method="post">
                <?php             
                do_settings_sections( 'fau_websso_options' );
                settings_fields( 'fau_websso_options' );
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
                do_settings_sections( 'fau_websso_options' );
                settings_fields( 'fau_websso_options' );
                submit_button();
                ?>
            </form>
        </div>
        <?php

    }
    
    public static function admin_init() {        

        if(!is_multisite())
            register_setting( 'fau_websso_options', self::version_option_name, array( __CLASS__, 'options_validate' ) );

        add_settings_section( 'websso_options_section', false, array( __CLASS__, 'websso_settings_section' ), 'fau_websso_options' );
        add_settings_field( 'force_websso', __('Zur Single Sign-On zwingen', self::textdomain), array( __CLASS__, 'force_websso_field' ), 'fau_websso_options', 'websso_options_section' );
        
        add_settings_section( 'simplesaml_options_section', false, array( __CLASS__, 'simplesaml_settings_section' ), 'fau_websso_options' );
        add_settings_field( 'simplesaml_include', __('Autoload-Pfad', self::textdomain), array( __CLASS__, 'simplesaml_include_field' ), 'fau_websso_options', 'simplesaml_options_section' );
        add_settings_field( 'simplesaml_auth_source', __('Authentifizierungsquelle', self::textdomain), array( __CLASS__, 'simplesaml_auth_source' ), 'fau_websso_options', 'simplesaml_options_section' );
    }

    public static function websso_settings_section() {
        echo '<h3 class="title">' . __('Allgemein', self::textdomain) . '</h3>';
        echo '<p>' . __('Allgemeine Einstellungen.', self::textdomain) . '</p>';
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

    public static function simplesaml_auth_source() {
        $options = self::get_options();   
        echo '<input type="text" id="simplesaml_auth_source" class="regular-text ltr" name="' . self::option_name . '[simplesaml_auth_source]" value="' . esc_attr( $options['simplesaml_auth_source'] ) . '">';
    }

    public static function options_validate( $input ) {
        $options = self::get_options();

        $input['force_websso'] = !empty($input['force_websso']) ? true : false;
        
        $input['simplesaml_include'] = isset($input['simplesaml_include']) ? esc_attr($input['simplesaml_include']) : $options['simplesaml_include'];
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
