# emergence-saml2
Enable an emergence site to serve as a SAML2 identify provider (IdP)

## Installing
1. Copy `php-config/Git.config.d/emergence-saml2.php` into your instance
2. Visit **/git/status** and initialize the emergence-saml2 repository.
3. Press <kbd>Disk → VFS</kbd> for the emergence-saml2 repository to copy the contents into your instance
4. Refresh **/git/status** and repeat steps 2 and 3 for both simplesamlphp-saml2 and xmlseclibs
5. Follow http://slate.is/docs/integrations/general/saml2 to generate a private key and public certificate
6. Configure `php-config/Emergence/Connectors/SAML2.config.php` with the key and certificate generated in step 5
7. Enable SAML2 SSO in your client application, providing `http://example.org/connectors/saml2/login` as your login URL
