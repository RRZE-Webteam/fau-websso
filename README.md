FAU-WebSSO
==========

Wordpress-Plugin: Anmeldung fuer zentral vergebene Kennungen von Studierenden und Beschaeftigten.

WP-Einstellungsmenue
-------------------- 

Einstellungen › FAU-WebSSO

Bereitstellung des FAU-SP (Service Provider) mit SimpleSAMLphp
--------------------------------------------------------------

- 1. Letzte version des SimpleSAMLphp herunterladen. Siehe http://code.google.com/p/simplesamlphp/downloads/list
- 2. Das simplesamlphp-Verzeichnis kopieren und unter dem wp-content-Verzeichnis des WordPress einsetzen
- 3. Folgenden Attribute in der Datei /simplesamlphp/config/config.php ändern/bearbeiten:

<pre>
'auth.adminpassword' = 'Beliebige Admin-Password'
'secretsalt' => 'Beliebige, moeglichst einzigartige Phrase'
'technicalcontact_name' => 'Name des technischen Ansprechpartners'
'technicalcontact_email' => 'E-Mail-Adresse des technischen Ansprechpartners'
</pre>

- 4. Alle IPs von der Datei /simplesamlphp/metadata/saml20-idp-remote.php entfernen und dann den folgenden Code hinzufügen:

<pre>
$metadata['https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php'] = array(
    'metadata-set' => 'saml20-idp-remote',
    'entityid' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php',
    'name' => array(
        'de' => 'Zentraler Anmeldedienst der Universitaet Erlangen-Nuernberg',
        'en' => 'Central login service of the University Erlangen-Nuernberg',
    ),  
    'description' => array(
        'de' => 'Anmeldung für zentral-vergebene Kennungen von Studierenden und Beschaeftigten der Universitaet Erlangen-Nuernberg.',
        'en' => 'Login for centrally assigned IDs of students and employees of the University Erlangen-Nürnberg.'
    ),    
    'SingleSignOnService' =>
    array(
        0 =>
        array(
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
        ),
    ),
    'SingleLogoutService' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SingleLogoutService.php',
    'keys' =>
    array(
        0 =>
        array(
            'type' => 'X509Certificate',
            'signing' => true,
            'encryption' => true,
            'X509Certificate' => 'Public-Key-Algorithmus',
        ),
        1 =>
        array(
            'type' => 'X509Certificate',
            'signing' => true,
            'encryption' => false,
            'X509Certificate' => 'Public-Key des Zertifikatinhabers',
        ),
    ),
    'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
);
</pre>

Apache-Einstellungen
--------------------

- Alias für SimpleSAMLphp einrichten:

<pre>Alias /simplesaml /Pfad zum simplesamlphp/www-Verzeichnis</pre>

Z.B.: Alias /simplesaml /wordpress/wp-content/simplesamlphp/www


Anmeldung
---------

- Folgende Info an der RRZE-WebSSO-E-Mail-Verteiler versenden:

<pre>
Metadata-URL: http(s)://webauftritt-url/simplesaml/module.php/saml/sp/metadata.php/default-sp
Login-URL: http(s)://webauftritt-url/wp-login.php?action=sso
Erforderliche Attribute: 
	displayname
	uid
	mail
	eduPersonAffiliation
</pre>