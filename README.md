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

<pre>
<?php
$metadata['https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php'] =
[
  'metadata-set' => 'saml20-idp-remote',
  'entityid' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php',
  'SingleSignOnService' =>
  [
    0 =>
    [
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
      'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
    ],
    1 =>
    [
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
      'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
    ],
    2 =>
    [
      'index' => 0,
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP',
      'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
    ],
  ],
  'SingleLogoutService' =>
  [
    0 =>
    [
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
      'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SingleLogoutService.php',
    ],
  ],
  'keys' =>
  [
    0 =>
    [
      'type' => 'X509Certificate',
      'signing' => true,
      'encryption' => true,
      'X509Certificate' => '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',
    ],
    1 =>
    [
      'type' => 'X509Certificate',
      'signing' => true,
      'encryption' => false,
      'X509Certificate' => 'MIIH2TCCBsGgAwIBAgIHGJ5nV/NnXTANBgkqhkiG9w0BAQsFADCBozELMAkGA1UEBhMCREUxDzANBgNVBAgTBkJheWVybjERMA8GA1UEBxMIRXJsYW5nZW4xKDAmBgNVBAoTH1VuaXZlcnNpdGFldCBFcmxhbmdlbi1OdWVybmJlcmcxDTALBgNVBAsTBFJSWkUxDzANBgNVBAMTBkZBVS1DQTEmMCQGCSqGSIb3DQEJARYXY2FAcnJ6ZS51bmktZXJsYW5nZW4uZGUwHhcNMTQxMjAzMTA0NTI4WhcNMTkwNzA5MjM1OTAwWjCBgzELMAkGA1UEBhMCREUxDzANBgNVBAgTBkJheWVybjERMA8GA1UEBxMIRXJsYW5nZW4xKDAmBgNVBAoTH1VuaXZlcnNpdGFldCBFcmxhbmdlbi1OdWVybmJlcmcxDTALBgNVBAsTBFJSWkUxFzAVBgNVBAMTDnd3dy5zc28uZmF1LmRlMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEArDMkYLXoej9jjWq+K4hCcm8dRgyxbpMQevfdKBhpipHjFPs9xphbtYyVUY/y7b+3NpnQ7rTxR8OX8js4/P9faTW2lUN3L3a8X0cGm7h/LyHC9tPZSbsSosJXZ8ws7UebzpNSo0EtykmQcpLifPh0VazcZ1EIu1xbG3mqLTs6Zz0YEyhHxhBPAi3rsl3mUPLOi7wv97fRkkfWvMdg4oENeqbpqEg0uIlJIBo/DTNPlVumxsv5GyKLC87mfGerdnNB0FUVDOortb279XaQnIdhWFZLtvUNls6jpkLX424ZV2TqL8jvGnbSIFw23qFVQJoh4ZphEefP9dgpU04gBQ2r7hbV5hvEyPE2u1iGvS0wEstLcZZEAYiz3cKsf/u6vfO44Y8aO9cisrOlF7W8wlVT6unagdZMMue+dEiLYoX04CNgcyHYAxEmZdzsfXBNGeLLsHgI05DL4uejCtE99mCigGUHUnzCJtosz13zpnt0l0A/6iXa3NLydrjN+t2RNnI1Y07MzN0TUiF6G28gzzM2Km/dmdQePeCepjpIwE/4jPckWX4RhGxyWtr8ttjRGW3+aSpoQRolGEmMu/I7Kk4kOT/P8EReHtQ7ItG7yGeze1Bl6SweY7mqlcx7ioXjnPqTyl0yQSTth+LftyMnA57BaQ3bJoTCxiPoUMe2dx4Ky68CAwEAAaOCAy4wggMqME8GA1UdIARIMEYwEQYPKwYBBAGBrSGCLAEBBAMDMBEGDysGAQQBga0hgiwCAQQDATAPBg0rBgEEAYGtIYIsAQEEMA0GCysGAQQBga0hgiweMAkGA1UdEwQCMAAwCwYDVR0PBAQDAgTwMBMGA1UdJQQMMAoGCCsGAQUFBwMBMB0GA1UdDgQWBBRZl7XWoTH9fNNalFv7PVzbARELxDAfBgNVHSMEGDAWgBT0c/P6xkKzxlxpwFi+dDj5YSuOYjCB1wYDVR0RBIHPMIHMgg1vcGVuaWQuZmF1LmRlgg9vcGVuaWQucnJ6ZS5uZXSCHnNzby1wcm94eS5ycnplLnVuaS1lcmxhbmdlbi5kZYIKc3NvLmZhdS5kZYIYc3NvLnJyemUudW5pLWVybGFuZ2VuLmRlghNzc28udW5pLWVybGFuZ2VuLmRlghF3d3cub3BlbmlkLmZhdS5kZYITd3d3Lm9wZW5pZC5ycnplLm5ldIIOd3d3LnNzby5mYXUuZGWCF3d3dy5zc28udW5pLWVybGFuZ2VuLmRlMIGfBgNVHR8EgZcwgZQwSKBGoESGQmh0dHA6Ly9jZHAxLnBjYS5kZm4uZGUvdW5pLWVybGFuZ2VuLW51ZXJuYmVyZy1jYS9wdWIvY3JsL2NhY3JsLmNybDBIoEagRIZCaHR0cDovL2NkcDIucGNhLmRmbi5kZS91bmktZXJsYW5nZW4tbnVlcm5iZXJnLWNhL3B1Yi9jcmwvY2FjcmwuY3JsMIHtBggrBgEFBQcBAQSB4DCB3TAzBggrBgEFBQcwAYYnaHR0cDovL29jc3AucGNhLmRmbi5kZS9PQ1NQLVNlcnZlci9PQ1NQMFIGCCsGAQUFBzAChkZodHRwOi8vY2RwMS5wY2EuZGZuLmRlL3VuaS1lcmxhbmdlbi1udWVybmJlcmctY2EvcHViL2NhY2VydC9jYWNlcnQuY3J0MFIGCCsGAQUFBzAChkZodHRwOi8vY2RwMi5wY2EuZGZuLmRlL3VuaS1lcmxhbmdlbi1udWVybmJlcmctY2EvcHViL2NhY2VydC9jYWNlcnQuY3J0MA0GCSqGSIb3DQEBCwUAA4IBAQB/I2I90bVOWDyl0KBKFhUTE3PelYO7R1fbNDz31MfFPBf3R8cKQmWOmRZbR3xGtsyEox1DxXF/TEJvMVtTADsWSfn98T+wbXXObBC4weNWiEgw6QTAIyV+etFrl34ah7bAeEVRTbgjMUGw2pI8inQ3lmwI2hBS1hczWlpjZGLsmaWECo4WOEDmfE0Y/DvJM92Ha5zwrcYMSv67UgRRB1BuiYzXOJTEVIhCG9C3vx/ua306YZX12QHrPtyUEMJcYqkP2hloKY/iJCaLXCBwcBv9OYPlpEpqprRECWq3dNLXmUBnqHCsiPa4TY2gVssAlQcmsasBj7ieuhFktDD/Y0vP',
    ],
  ],
  'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
  'contacts' =>
  [
    0 =>
    [
      'emailAddress' => 'sso-support@fau.de',
      'contactType' => 'technical',
      'givenName' => 'Frank',
      'surName' => 'Tröger',
    ],
  ],
];
</pre>

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
