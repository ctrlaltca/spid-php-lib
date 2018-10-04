<?php

namespace Italia\Spid\Spid\Saml\In;

use Italia\Spid\Spid\Interfaces\ResponseInterface;
use Italia\Spid\Spid\Session;

class Response implements ResponseInterface
{
    public function validate($xml): bool
    {
        $root = $xml->getElementsByTagName('Response')->item(0);

        if ($root->getAttribute('Version') == "") {
            throw new \Exception("Missing Version attribute");
        } elseif ($root->getAttribute('Version') != '2.0') {
            throw new \Exception("Invalid Version attribute");
        }
        if ($root->getAttribute('IssueInstant') == "") {
            throw new \Exception("Missing IssueInstant attribute");
        }
        if ($root->getAttribute('InResponseTo') == "" || !isset($_SESSION['RequestID'])) {
            throw new \Exception("Missing InResponseTo attribute, or request ID was not saved correctly for comparison");
        } elseif ($root->getAttribute('InResponseTo') != $_SESSION['RequestID']) {
            throw new \Exception("Invalid InResponseTo attribute, expected " . $_SESSION['RequestID'] . " but received " . $root->getAttribute('InResponseTo'));
        }
        if ($root->getAttribute('Destination') == "") {
            throw new \Exception("Missing Destination attribute");
        } elseif ($root->getAttribute('Destination') != $_SESSION['acsUrl']) {
            throw new \Exception("Invalid Destination attribute, expected " . $_SESSION['acsUrl'] . " but received " . $root->getAttribute('Destination'));
        }
        if ($xml->getElementsByTagName('Issuer')->length == 0) {
            throw new \Exception("Missing Issuer attribute");
        } elseif ($xml->getElementsByTagName('Issuer')->item(0)->nodeValue!= $_SESSION['idpEntityId']) {
            throw new \Exception("Invalid Issuer attribute, expected " . $_SESSION['idpEntityId'] . " but received " . $xml->getElementsByTagName('Issuer')->item(0)->nodeValue);
        }
        if ($xml->getElementsByTagName('Status')->length <= 0) {
            throw new \Exception("Missing Status element");
        } elseif ($xml->getElementsByTagName('StatusCode')->item(0)->getAttribute('Value') == 'urn:oasis:names:tc:SAML:2.0:status:Success') {
            if ($xml->getElementsByTagName('Assertion')->length <= 0) {
                throw new \Exception("Missing Assertion element");
            } elseif ($xml->getElementsByTagName('AuthnStatement')->length <= 0) {
                throw new \Exception("Missing AuthnStatement element");
            }
        } else {
            // Status code != success
            return false;
        }

        // Response OK
        $_SESSION['spidSession'] = $this->spidSession($xml);
        unset($_SESSION['RequestID']);
        unset($_SESSION['idpName']);
        unset($_SESSION['idpEntityId']);
        unset($_SESSION['acsUrl']);
        return true;
    }

    private function spidSession(\DOMDocument $xml)
    {
        $session = new Session();

        $attributes = array();
        if ($xml->getElementsByTagName('AttributeStatement')->length > 0) {
            foreach ($xml->getElementsByTagName('AttributeStatement')->item(0)->childNodes as $attr) {
                $attributes[$attr->getAttribute('Name')] = $attr->nodeValue;
            }
        }

        $session->sessionID = $_SESSION['RequestID'];
        $session->idp = $_SESSION['idpName'];
        $session->idpEntityID = $xml->getElementsByTagName('Issuer')->item(0)->nodeValue;
        $session->attributes = $attributes;
        $session->level = substr($xml->getElementsByTagName('AuthnContextClassRef')->item(0)->nodeValue, -1);
        return $session;
    }
}
