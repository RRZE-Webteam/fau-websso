<?php
/**
 * Plugin Name: FAU-WebSSO
 * Description: Anmeldung für zentral vergebene Kennungen von Studierenden und Beschäftigten.
 * Version: 1.0
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

register_activation_hook( __FILE__, 'fau_websso_activation' );

function fau_websso_activation() {
    $error = '';
    $php_version = '5.3';
    $wp_version = '3.6';
    
    if ( version_compare( PHP_VERSION, $php_version, '<' ) )
        $error = sprintf( __( 'Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', 'fau-websso' ), PHP_VERSION, $php_version );

    if ( version_compare( $GLOBALS['wp_version'], $wp_version, '<' ) )
        $error = sprintf( __( 'Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', 'fau-websso' ), $GLOBALS['wp_version'], $wp_version );

    if( ! empty( $error ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ), false, true );
        wp_die( $error );
    }

}

add_filter( 'wp_auth_check_same_domain', '__return_false' );

add_action('init', function() {
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'login';
    if ($action == 'sso')
        add_filter('authenticate', 'fau_websso_authenticate');
});

function fau_websso_get_options() {
    $defaults = array(
        'simplesaml_include' => '/simplesamlphp/www/_include.php',
        'simplesaml_sso' => '/simplesaml/saml2/sp/initSSO.php',
        'simplesaml_slo' => '/simplesaml/saml2/sp/initSLO.php'
    );

    $options = (array) get_option( '_fau_websso' );
    $options = wp_parse_args( $options, $defaults );
    $options = array_intersect_key( $options, $defaults );

    return $options;
}

function simplesaml_get_attributes() {
    $options = fau_websso_get_options();

    require_once(WP_CONTENT_DIR . $options['simplesaml_include']);

    session_cache_limiter( 'nocache' );

    $config = SimpleSAML_Configuration::getInstance();
    $session = SimpleSAML_Session::getInstance();

    $attributes = array();

    if( $session->isValid( 'saml2' ) ) {
        $_attributes = $session->getAttributes();

        $attributes['cn'] = isset( $_attributes['urn:mace:dir:attribute-def:cn'][0] ) ? $_attributes['urn:mace:dir:attribute-def:cn'][0] : '';
        $attributes['sn'] = isset( $_attributes['urn:mace:dir:attribute-def:sn'][0] ) ? $_attributes['urn:mace:dir:attribute-def:sn'][0] : '';
        $attributes['givenname'] = isset( $_attributes['urn:mace:dir:attribute-def:givenname'][0] ) ? $_attributes['urn:mace:dir:attribute-def:givenname'][0] : '';
        $attributes['displayname'] = isset( $_attributes['urn:mace:dir:attribute-def:displayname'][0] ) ? $_attributes['urn:mace:dir:attribute-def:displayname'][0] : '';
        $attributes['uid'] = isset( $_attributes['urn:mace:dir:attribute-def:uid'][0] ) ? $_attributes['urn:mace:dir:attribute-def:uid'][0] : '';
        $attributes['mail'] = isset( $_attributes['urn:mace:dir:attribute-def:mail'][0] ) ? $_attributes['urn:mace:dir:attribute-def:mail'][0] : '';
        $attributes['eduPersonAffiliation'] = isset( $_attributes['urn:mace:dir:attribute-def:eduPersonAffiliation'][0] ) ? $_attributes['urn:mace:dir:attribute-def:eduPersonAffiliation'][0] : '';   
        $attributes['eduPersonEntitlement'] = isset( $_attributes['urn:mace:dir:attribute-def:eduPersonEntitlement'][0] ) ? $_attributes['urn:mace:dir:attribute-def:eduPersonEntitlement'][0] : '';        
    }

    return $attributes;
}

function fau_websso_authenticate() {
    $attributes = simplesaml_get_attributes();
    
    if(empty($attributes))
        fau_websso_login();

    $user_login = $attributes['uid'];

    $user_email = $attributes['mail'];
    $first_name  = $attributes['givenname'];
    $last_name  = $attributes['sn'];

    $display_name = empty($first_name) ? $attributes['displayname'] : sprintf( '%s %s ', $first_name, $last_name );

    $edu_person_affiliation = $attributes['eduPersonAffiliation'];
    $edu_person_entitlement = $attributes['eduPersonEntitlement'];
    
    $userdata = get_user_by( 'login', $user_login );
    
    if ($userdata) {
        if($userdata->user_email == $user_email)
            $user = new WP_User($userdata->ID);
        else
            $user = new WP_Error('registration_failed', __('<strong>Fehler</strong>: Die Anmeldung ist fehlgeschlagen.', 'fau-websso'));           
    
    } else {
        
        if( ! get_site_option( 'users_can_register')) {
            $user = new WP_Error('register_disabled', __('<strong>Fehler</strong>: Zurzeit ist die Benutzer-Registrierung nicht erlaubt.', 'fau-websso'));
        
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

            if( is_wp_error( $user_id ) )
                $user = new WP_Error('registration_failed', __('<strong>Fehler</strong>: Die Anmeldung ist fehlgeschlagen.', 'fau-websso'));
            else {
                $user = new WP_User($user_id);
                update_user_meta( $user_id, 'edu_person_affiliation', $edu_person_affiliation );
                update_user_meta( $user_id, 'edu_person_entitlement', $edu_person_entitlement );
            }
        }
    }
    
    remove_action('authenticate', 'wp_authenticate_username_password', 20);

    return $user;
    
}

function fau_websso_login() {
    $options = fau_websso_get_options(); 
    
    $redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';
    
    $login_url = home_url( '/wp-login.php' );

    $login_url = add_query_arg( 'action', 'sso', $login_url );
    
    if( ! empty( $redirect_to ) )
        $login_url = add_query_arg( 'redirect_to', $redirect_to, $login_url );

    $sso_login_url = site_url( $options['simplesaml_sso'] );

    $redirect_url = sprintf( '%s?RelayState=%s', $sso_login_url, $login_url );

    wp_safe_redirect( $redirect_url );
    die();
}

add_action('wp_logout', function() {
    $options = fau_websso_get_options();
    
    $logout_url = home_url( '/wp-login.php' );
    
    $logout_url = add_query_arg( 'loggedout', 'true', $logout_url );

    $sso_logout_url = site_url( $options['simplesaml_slo'] );

    $redirect_url = sprintf( '%s?RelayState=%s', $sso_logout_url, $logout_url );
    
    wp_redirect($redirect_url);
    die();
}, 20);

add_action('login_form', function() {
    $login_url = add_query_arg('action', 'sso', home_url('/wp-login.php'));
    echo '<p><a href="' . $login_url . '">Anmeldung über Single Sign-On</a> (zentraler Anmeldedienst der Universität Erlangen-Nürnberg). Anmeldung für zentral-vergebene Kennungen von Studierenden und Beschäftigten der Universität Erlangen-Nürnberg.</p>';
});

add_action('admin_menu', function() {
    add_options_page( __('FAU-WebSSO', 'fau-websso'), __('FAU-WebSSO', 'fau-websso'), 'manage_options', 'fau-websso-options', 'fau_websso_options' );        
});

function fau_websso_options() {
    ?>
    <div class="wrap">
        <?php screen_icon(); ?>
        <h2><?php echo esc_html( __( 'Einstellungen &rsaquo; FAU-WebSSO', 'fau-websso') ); ?></h2>
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

add_action('admin_init', function() {        

    register_setting( 'fau_websso_options', '_fau_websso', 'esc_attr' );

    add_settings_section( 'fau_websso_options_section', false, '__return_false', 'fau_websso_options' );

    add_settings_field( 'simplesaml_include', __('SimpleSAMLphp-Include-Pfad', 'fau-websso'), 'simplesaml_include_field', 'fau_websso_options', 'fau_websso_options_section' );
    add_settings_field( 'simplesaml_sso', __('SimpleSAMLphp-SSO-URL', 'fau-websso'), 'simplesaml_sso_field', 'fau_websso_options', 'fau_websso_options_section' );
    add_settings_field( 'simplesaml_slo', __('SimpleSAMLphp-SLO-URL', 'fau-websso'), 'simplesaml_slo_field', 'fau_websso_options', 'fau_websso_options_section' );    
});

function simplesaml_include_field() {
    $options = fau_websso_get_options();   
    echo '<input type="text" id="simplesaml_include" class="regular-text ltr" name="_fau_websso[simplesaml_include]" value="' . esc_attr( $options['simplesaml_include'] ) . '">';
    echo '<p class="description">' . __('Relative Pfad ausgehend vom wp-content-Verzeichnis.', 'fau-websso') . '</p>';
}

function simplesaml_sso_field() {
    $options = fau_websso_get_options();   
    echo '<input type="text" id="simplesaml_sso" class="regular-text ltr" name="_fau_websso[simplesaml_sso]" value="' . esc_attr( $options['simplesaml_sso'] ) . '">';
    echo '<p class="description">' . __('Relative URL.', 'fau-websso') . '</p>';
}

function simplesaml_slo_field() {
    $options = fau_websso_get_options();   
    echo '<input type="text" id="simplesaml_slo" class="regular-text ltr" name="_fau_websso[simplesaml_slo]" value="' . esc_attr( $options['simplesaml_slo'] ) . '">';
    echo '<p class="description">' . __('Relative URL.', 'fau-websso') . '</p>';
}
