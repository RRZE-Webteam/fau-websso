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
  'certData' => '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',
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
