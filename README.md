FAU-WebSSO
==========

Wordpress-Plugin: Anmeldung für zentral-vergebene Kennungen von Studierenden und Beschäftigten der Universität Erlangen-Nürnberg.

WP-Einstellungsmenü
-------------------

Einstellungen › WebSSO

Bereitstellung eines FAU-SP (Service Provider) mittels SimpleSAMLphp
--------------------------------------------------------------------

- 1. Letzte version des SimpleSAMLphp herunterladen. Siehe https://simplesamlphp.org/download
- 2. Das simplesamlphp-Verzeichnis kopieren und unter dem wp-content-Verzeichnis des WordPress einsetzen
- 3. Folgenden Attribute in der Datei /simplesamlphp/config/config.php ändern/bearbeiten:

<pre>
'auth.adminpassword' = 'Beliebiges Admin-Password'
'secretsalt' => 'Beliebige, möglichst einzigartige Phrase'
'technicalcontact_name' => 'Name des technischen Ansprechpartners'
'technicalcontact_email' => 'E-Mail-Adresse des technischen Ansprechpartners'
</pre>

- 4. Folgende Element des "default-sp"-Array in der Datei /simplesamlphp/config/authsources.php ändern/bearbeiten:

<pre>
'idp' = 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php'
</pre>

- 5. Alle IdPs von der Datei /simplesamlphp/metadata/saml20-idp-remote.php entfernen und dann den folgenden Code hinzufügen:

```php
<?php
$metadata['https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php'] = array (
  'metadata-set' => 'saml20-idp-remote',
  'entityid' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php',
  'SingleSignOnService' => 
  array (
    0 => 
    array (
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
      'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
    ),
    1 => 
    array (
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
      'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
    ),
    2 => 
    array (
      'index' => 0,
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP',
      'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
    ),
  ),
  'SingleLogoutService' => 
  array (
    0 => 
    array (
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
      'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SingleLogoutService.php',
    ),
  ),
  'keys' => 
  array (
    0 => 
    array (
      'type' => 'X509Certificate',
      'signing' => true,
      'encryption' => true,
      'X509Certificate' => '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',
    ),
    1 => 
    array (
      'type' => 'X509Certificate',
      'signing' => true,
      'encryption' => false,
      'X509Certificate' => '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',
    ),
  ),
  'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
  'contacts' => 
  array (
    0 => 
    array (
      'emailAddress' => 'sso-support@fau.de',
      'contactType' => 'technical',
      'givenName' => 'Frank',
      'surName' => 'Tröger',
    ),
  ),
);
```

Webserver-Einstellungen (Apache)
--------------------------------

- Standard- und SSL-Virtualhost einrichten.

- Alias für SimpleSAMLphp im SSL-Virtualhost einrichten:

<pre>Alias /simplesaml /Pfad zum simplesamlphp/www-Verzeichnis</pre>

Z.B.: Alias /simplesaml /wordpress/wp-content/simplesamlphp/www


Anmeldung
---------

- Folgende Info an sso-admins@rrze.fau.de versenden:

<pre>
Webseite: (URL der Webseite)
Beschreibung: (Kurze Beschreibung der Webseite)
Metadata-URL: https://webauftritt-url/simplesaml/module.php/saml/sp/metadata.php/default-sp
Login-URL: https://webauftritt-url/wp-login.php
Erforderliche Attribute:
	displayname
	uid
	mail
        eduPersonAffiliation
</pre>

Hinweis: Bitte überprüfen Sie, dass die jeweiligen URLs keine Fehlermeldungen im Browser ausgeben.
