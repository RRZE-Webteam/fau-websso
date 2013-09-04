FAU-WebSSO
==========

Wordpress-Plugin: Anmeldung für zentral-vergebene Kennungen von Studierenden und Beschäftigten der Universität Erlangen-Nürnberg.

WP-Einstellungsmenü
------------------- 

Einstellungen › FAU-WebSSO

Bereitstellung eines FAU-SP (Service Provider) mittels SimpleSAMLphp
--------------------------------------------------------------------

- 1. Letzte version des SimpleSAMLphp herunterladen. Siehe http://code.google.com/p/simplesamlphp/downloads/list
- 2. Das simplesamlphp-Verzeichnis kopieren und unter dem wp-content-Verzeichnis des WordPress einsetzen
- 3. Folgenden Attribute in der Datei /simplesamlphp/config/config.php ändern/bearbeiten:

<pre>
'auth.adminpassword' = 'Beliebige Admin-Password'
'secretsalt' => 'Beliebige, moeglichst einzigartige Phrase'
'technicalcontact_name' => 'Name des technischen Ansprechpartners'
'technicalcontact_email' => 'E-Mail-Adresse des technischen Ansprechpartners'
</pre>

- 4. Folgende Element des "default-sp"-Array in der Datei /simplesamlphp/config/authsources.php ändern/bearbeiten:

<pre>
'idp' = 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php'
</pre>

- 5. Alle IPs von der Datei /simplesamlphp/metadata/saml20-idp-remote.php entfernen und dann den folgenden Code hinzufügen:

<pre>
    $metadata['https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php'] = array(
    'metadata-set' => 'saml20-idp-remote',
    'entityid' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/metadata.php',
    'SingleSignOnService' =>
    array(
        0 =>
        array(
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SSOService.php',
        ),
    ),
    'SingleLogoutService' => 'https://www.sso.uni-erlangen.de/simplesaml/saml2/idp/SingleLogoutService.php',
    'certData' => 'MIIHCTCCBfGgAwIBAgIHFVh6d4xjCjANBgkqhkiG9w0BAQUFADCBozELMAkGA1UEBhMCREUxDzANBgNVBAgTBkJheWVybjERMA8GA1UEBxMIRXJsYW5nZW4xKDAmBgNVBAoTH1VuaXZlcnNpdGFldCBFcmxhbmdlbi1OdWVybmJlcmcxDTALBgNVBAsTBFJSWkUxDzANBgNVBAMTBkZBVS1DQTEmMCQGCSqGSIb3DQEJARYXY2FAcnJ6ZS51bmktZXJsYW5nZW4uZGUwHhcNMTMwMzA3MjA0NzM2WhcNMTgwMzA2MjA0NzM2WjCBpzELMAkGA1UEBhMCREUxDzANBgNVBAgTBkJheWVybjERMA8GA1UEBxMIRXJsYW5nZW4xKDAmBgNVBAoTH1VuaXZlcnNpdGFldCBFcmxhbmdlbi1OdWVybmJlcmcxMTAvBgNVBAsTKFJlZ2lvbmFsZXMgUmVjaGVuemVudHJ1bSBFcmxhbmdlbiAoUlJaRSkxFzAVBgNVBAMTDnd3dy5zc28uZmF1LmRlMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAs/qsX/p+z8uxh3feD2sZWdb/NpOI5/YkPeXYQPQAdlRKBijHLohlLRAzCNFU7YWhy/FxY4uLE97h0hPVpoxCPlOW5qh56C1ZEWGBdhJImfYpzJbB2UIIUUR2WXLjZPyQObzxocui2M3XWa/uhpc3nPvBC/HLzr4fbU0f20D9hr0MXFurfuhGID+jt1jRsVWTjMWEVDAXDduKYUCAqrp1RlSc/H6z4WFeiQ92+4Q/+axig6KRkg0e0LBhMDA3ozIODmfFDIuo++rGvHWG47GLbLQyJLLeGkPlxxqo8uvVhtENxd5jrIkZrRd8y+dFr+jRh36DMmJGTzVkeweZPT2ZpQIDAQABo4IDOjCCAzYwOQYDVR0gBDIwMDARBg8rBgEEAYGtIYIsAQEEAwAwEQYPKwYBBAGBrSGCLAIBBAMAMAgGBmeBDAECAjAJBgNVHRMEAjAAMAsGA1UdDwQEAwIF4DAdBgNVHSUEFjAUBggrBgEFBQcDAgYIKwYBBQUHAwEwHQYDVR0OBBYEFGL4MCMuRKr8cD0oslgL7QGRYj+uMB8GA1UdIwQYMBaAFPRz8/rGQrPGXGnAWL50OPlhK45iMIHvBgNVHREEgecwgeSCDW9wZW5pZC5mYXUuZGWCD29wZW5pZC5ycnplLm5ldIIec3NvLXByb3h5LnJyemUudW5pLWVybGFuZ2VuLmRlggpzc28uZmF1LmRlghhzc28ucnJ6ZS51bmktZXJsYW5nZW4uZGWCE3Nzby51bmktZXJsYW5nZW4uZGWCEXd3dy5vcGVuaWQuZmF1LmRlghN3d3cub3BlbmlkLnJyemUubmV0gg53d3cuc3NvLmZhdS5kZYIXd3d3LnNzby51bmktZXJsYW5nZW4uZGWBFnNzby1hZG1pbnNAcnJ6ZS5mYXUuZGUwgZ8GA1UdHwSBlzCBlDBIoEagRIZCaHR0cDovL2NkcDEucGNhLmRmbi5kZS91bmktZXJsYW5nZW4tbnVlcm5iZXJnLWNhL3B1Yi9jcmwvY2FjcmwuY3JsMEigRqBEhkJodHRwOi8vY2RwMi5wY2EuZGZuLmRlL3VuaS1lcmxhbmdlbi1udWVybmJlcmctY2EvcHViL2NybC9jYWNybC5jcmwwge0GCCsGAQUFBwEBBIHgMIHdMDMGCCsGAQUFBzABhidodHRwOi8vb2NzcC5wY2EuZGZuLmRlL09DU1AtU2VydmVyL09DU1AwUgYIKwYBBQUHMAKGRmh0dHA6Ly9jZHAxLnBjYS5kZm4uZGUvdW5pLWVybGFuZ2VuLW51ZXJuYmVyZy1jYS9wdWIvY2FjZXJ0L2NhY2VydC5jcnQwUgYIKwYBBQUHMAKGRmh0dHA6Ly9jZHAyLnBjYS5kZm4uZGUvdW5pLWVybGFuZ2VuLW51ZXJuYmVyZy1jYS9wdWIvY2FjZXJ0L2NhY2VydC5jcnQwDQYJKoZIhvcNAQEFBQADggEBALIsNWnuaDrQA7Tu1ReusFhrkdOKpIH90mMWOBXZoUTPxEaKn86rjUAoFj1u1NlWyUrMYRWneXlvy3jQ3KEo7H6nJiEoa7LH1siTNcmOjDiN1dqAPO6+zAiESP5Xwsr2D1msyPXade0ra2PFSeG4XZ6hF/KEEYN0xxFpPbrvBD/fa+MUfp8NqxF7uANosdABgJs9RFmEmGuWd/Rc3aGzd3+dGwh9nFnKxON9fTrOlXNxa9OgjO0N75bg2RQTXLaMZLlUoVGE7n6FljoKjyiXlvUBSSeErakYPLpvgolN6QwAkM2jc/GLdms9imUWk7YQ8hzH7aUxdRWOmXLmc+2n/lY=',    
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
Login-URL: http(s)://webauftritt-url/wp-login.php
Erforderliche Attribute: 
	displayname
	uid
	mail
	eduPersonAffiliation
</pre>
