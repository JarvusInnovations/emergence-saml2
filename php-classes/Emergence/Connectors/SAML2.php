<?php

namespace Emergence\Connectors;

use Site;
use Emergence\People\IPerson;

use SAML2_Const;
use SAML2_Binding;
use SAML2_Response;
use SAML2_Assertion;
use SAML2_Compat_ContainerSingleton;
use SAML2_XML_saml_SubjectConfirmation;
use SAML2_XML_saml_SubjectConfirmationData;
use SAML2_HTTPPost;

use XMLSecurityKey;

class SAML2 extends \Emergence\Connectors\AbstractConnector implements \Emergence\Connectors\IIdentityConsumer
{
    use \Emergence\Connectors\IdentityConsumerTrait;

    public static $issuer;
    public static $privateKey;
    public static $certificate;

    public static $title = 'SAML2';
    public static $connectorId = 'saml2';

    public static function __classLoaded()
    {
        // copy config from legacy class if not defined locally
        if (!self::$issuer) {
            self::$issuer = Site::getConfig('primary_hostname');
        }
    }

    public static function handleLoginRequest(IPerson $Person)
    {
        try {
            $binding = SAML2_Binding::getCurrentBinding();
        } catch (Exception $e) {
            return static::throwUnauthorizedError('Cannot obtain SAML2 binding');
        }

        $request = $binding->receive();

        // build response
        $response = new SAML2_Response();
        $response->setInResponseTo($request->getId());
        $response->setRelayState($request->getRelayState());
        $response->setDestination($request->getAssertionConsumerServiceURL());

        // build assertion
        $assertion = new SAML2_Assertion();
        $assertion->setIssuer(static::$issuer);
        $assertion->setSessionIndex(SAML2_Compat_ContainerSingleton::getInstance()->generateId());
        $assertion->setNotBefore(time() - 30);
        $assertion->setNotOnOrAfter(time() + 300);
        $assertion->setAuthnContext(SAML2_Const::AC_PASSWORD);

        // build subject confirmation
        $sc = new SAML2_XML_saml_SubjectConfirmation();
        $sc->Method = SAML2_Const::CM_BEARER;
        $sc->SubjectConfirmationData = new SAML2_XML_saml_SubjectConfirmationData();
        $sc->SubjectConfirmationData->NotOnOrAfter = $assertion->getNotOnOrAfter();
        $sc->SubjectConfirmationData->Recipient = $request->getAssertionConsumerServiceURL();
        $sc->SubjectConfirmationData->InResponseTo = $request->getId();
        $assertion->setSubjectConfirmation([$sc]);

        // set NameID
        $assertion->setNameId([
            'Format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            'Value' => $Person->Username.'@'.static::$issuer
        ]);


        // set additional attributes
        $assertion->setAttributes([
            'User.Email' => [$Person->Email],
            'User.Username' => [$Person->Username],
            'first_name' => [$Person->FirstName],
            'last_name' => [$Person->LastName]
        ]);


        // attach assertion to response
        $response->setAssertions([$assertion]);


        // create signature
        $privateKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $privateKey->loadKey(static::$privateKey);

        $response->setSignatureKey($privateKey);
        $response->setCertificates([static::$certificate]);


        // prepare response
        $responseXML = $response->toSignedXML();
        $responseString = $responseXML->ownerDocument->saveXML($responseXML);


        // dump response and quit
#        header('Content-Type: text/xml');
#        die($responseString);


        // send response
        $responseBinding = new SAML2_HTTPPost();
        $responseBinding->send($response);
    }
}