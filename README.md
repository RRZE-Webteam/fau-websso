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
  ),
  'SingleLogoutService' => 
  array (
    0 => 
    array (
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
      'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SingleLogoutService.php',
    ),
  ),
  'certData' => 'MIIH2TCCBsGgAwIBAgIHGJ5nV/NnXTANBgkqhkiG9w0BAQsFADCBozELMAkGA1UEBhMCREUxDzANBgNVBAgTBkJheWVybjERMA8GA1UEBxMIRXJsYW5nZW4xKDAmBgNVBAoTH1VuaXZlcnNpdGFldCBFcmxhbmdlbi1OdWVybmJlcmcxDTALBgNVBAsTBFJSWkUxDzANBgNVBAMTBkZBVS1DQTEmMCQGCSqGSIb3DQEJARYXY2FAcnJ6ZS51bmktZXJsYW5nZW4uZGUwHhcNMTQxMjAzMTA0NTI4WhcNMTkwNzA5MjM1OTAwWjCBgzELMAkGA1UEBhMCREUxDzANBgNVBAgTBkJheWVybjERMA8GA1UEBxMIRXJsYW5nZW4xKDAmBgNVBAoTH1VuaXZlcnNpdGFldCBFcmxhbmdlbi1OdWVybmJlcmcxDTALBgNVBAsTBFJSWkUxFzAVBgNVBAMTDnd3dy5zc28uZmF1LmRlMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEArDMkYLXoej9jjWq+K4hCcm8dRgyxbpMQevfdKBhpipHjFPs9xphbtYyVUY/y7b+3NpnQ7rTxR8OX8js4/P9faTW2lUN3L3a8X0cGm7h/LyHC9tPZSbsSosJXZ8ws7UebzpNSo0EtykmQcpLifPh0VazcZ1EIu1xbG3mqLTs6Zz0YEyhHxhBPAi3rsl3mUPLOi7wv97fRkkfWvMdg4oENeqbpqEg0uIlJIBo/DTNPlVumxsv5GyKLC87mfGerdnNB0FUVDOortb279XaQnIdhWFZLtvUNls6jpkLX424ZV2TqL8jvGnbSIFw23qFVQJoh4ZphEefP9dgpU04gBQ2r7hbV5hvEyPE2u1iGvS0wEstLcZZEAYiz3cKsf/u6vfO44Y8aO9cisrOlF7W8wlVT6unagdZMMue+dEiLYoX04CNgcyHYAxEmZdzsfXBNGeLLsHgI05DL4uejCtE99mCigGUHUnzCJtosz13zpnt0l0A/6iXa3NLydrjN+t2RNnI1Y07MzN0TUiF6G28gzzM2Km/dmdQePeCepjpIwE/4jPckWX4RhGxyWtr8ttjRGW3+aSpoQRolGEmMu/I7Kk4kOT/P8EReHtQ7ItG7yGeze1Bl6SweY7mqlcx7ioXjnPqTyl0yQSTth+LftyMnA57BaQ3bJoTCxiPoUMe2dx4Ky68CAwEAAaOCAy4wggMqME8GA1UdIARIMEYwEQYPKwYBBAGBrSGCLAEBBAMDMBEGDysGAQQBga0hgiwCAQQDATAPBg0rBgEEAYGtIYIsAQEEMA0GCysGAQQBga0hgiweMAkGA1UdEwQCMAAwCwYDVR0PBAQDAgTwMBMGA1UdJQQMMAoGCCsGAQUFBwMBMB0GA1UdDgQWBBRZl7XWoTH9fNNalFv7PVzbARELxDAfBgNVHSMEGDAWgBT0c/P6xkKzxlxpwFi+dDj5YSuOYjCB1wYDVR0RBIHPMIHMgg1vcGVuaWQuZmF1LmRlgg9vcGVuaWQucnJ6ZS5uZXSCHnNzby1wcm94eS5ycnplLnVuaS1lcmxhbmdlbi5kZYIKc3NvLmZhdS5kZYIYc3NvLnJyemUudW5pLWVybGFuZ2VuLmRlghNzc28udW5pLWVybGFuZ2VuLmRlghF3d3cub3BlbmlkLmZhdS5kZYITd3d3Lm9wZW5pZC5ycnplLm5ldIIOd3d3LnNzby5mYXUuZGWCF3d3dy5zc28udW5pLWVybGFuZ2VuLmRlMIGfBgNVHR8EgZcwgZQwSKBGoESGQmh0dHA6Ly9jZHAxLnBjYS5kZm4uZGUvdW5pLWVybGFuZ2VuLW51ZXJuYmVyZy1jYS9wdWIvY3JsL2NhY3JsLmNybDBIoEagRIZCaHR0cDovL2NkcDIucGNhLmRmbi5kZS91bmktZXJsYW5nZW4tbnVlcm5iZXJnLWNhL3B1Yi9jcmwvY2FjcmwuY3JsMIHtBggrBgEFBQcBAQSB4DCB3TAzBggrBgEFBQcwAYYnaHR0cDovL29jc3AucGNhLmRmbi5kZS9PQ1NQLVNlcnZlci9PQ1NQMFIGCCsGAQUFBzAChkZodHRwOi8vY2RwMS5wY2EuZGZuLmRlL3VuaS1lcmxhbmdlbi1udWVybmJlcmctY2EvcHViL2NhY2VydC9jYWNlcnQuY3J0MFIGCCsGAQUFBzAChkZodHRwOi8vY2RwMi5wY2EuZGZuLmRlL3VuaS1lcmxhbmdlbi1udWVybmJlcmctY2EvcHViL2NhY2VydC9jYWNlcnQuY3J0MA0GCSqGSIb3DQEBCwUAA4IBAQB/I2I90bVOWDyl0KBKFhUTE3PelYO7R1fbNDz31MfFPBf3R8cKQmWOmRZbR3xGtsyEox1DxXF/TEJvMVtTADsWSfn98T+wbXXObBC4weNWiEgw6QTAIyV+etFrl34ah7bAeEVRTbgjMUGw2pI8inQ3lmwI2hBS1hczWlpjZGLsmaWECo4WOEDmfE0Y/DvJM92Ha5zwrcYMSv67UgRRB1BuiYzXOJTEVIhCG9C3vx/ua306YZX12QHrPtyUEMJcYqkP2hloKY/iJCaLXCBwcBv9OYPlpEpqprRECWq3dNLXmUBnqHCsiPa4TY2gVssAlQcmsasBj7ieuhFktDD/Y0vP',
  'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
);
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
