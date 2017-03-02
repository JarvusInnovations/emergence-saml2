<?php

namespace Emergence\Connectors;

use Site;
use Emergence\People\IPerson;

use SAML2\Constants AS SAML2_Constants;
use SAML2\Binding;
use SAML2\Response;
use SAML2\Assertion;
use SAML2\Compat\ContainerSingleton;
use SAML2\XML\saml\SubjectConfirmation;
use SAML2\XML\saml\SubjectConfirmationData;
use SAML2\HTTPPost;

use RobRichards\XMLSecLibs\XMLSecurityKey;

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

    public static function handleLoginRequest(IPerson $Person, $IdentityConsumer = null)
    {
        try {
            $binding = Binding::getCurrentBinding();
        } catch (Exception $e) {
            return static::throwUnauthorizedError('Cannot obtain SAML2 binding');
        }

        $request = $binding->receive();

        // build response
        $response = new Response();
        $response->setInResponseTo($request->getId());
        $response->setRelayState($request->getRelayState());
        $response->setDestination($request->getAssertionConsumerServiceURL());

        // build assertion
        $assertion = new Assertion();
        $assertion->setIssuer(static::$issuer);
        $assertion->setSessionIndex(ContainerSingleton::getInstance()->generateId());
        $assertion->setNotBefore(time() - 30);
        $assertion->setNotOnOrAfter(time() + 300);
        $assertion->setAuthnContext(SAML2_Constants::AC_PASSWORD);

        // build subject confirmation
        $sc = new SubjectConfirmation();
        $sc->Method = SAML2_Constants::CM_BEARER;
        $sc->SubjectConfirmationData = new SubjectConfirmationData();
        $sc->SubjectConfirmationData->NotOnOrAfter = $assertion->getNotOnOrAfter();
        $sc->SubjectConfirmationData->Recipient = $request->getAssertionConsumerServiceURL();
        $sc->SubjectConfirmationData->InResponseTo = $request->getId();
        $assertion->setSubjectConfirmation([$sc]);

        // set NameID
        if ($IdentityConsumer && is_a($IdentityConsumer, '\Emergence\Connectors\IIdentityConsumer', true)) {
            $assertion->setNameId($IdentityConsumer::getSAMLNameId($Person));
        } else {
            $assertion->setNameId([
                'Format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
                'Value' => $Person->Username.'@'.static::$issuer
            ]);
        }


        // set additional attributes
        $assertion->setAttributes([
            'User.Email' => [$Person->Email],
            'User.Username' => [$Person->Username]
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
        $responseBinding = new HTTPPost();
        $responseBinding->send($response);
    }
}