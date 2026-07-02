<?php

/**
 * Horde_ActiveSync_Request_Autodiscover::
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 */
/**
 * ActiveSync Handler for Autodiscover requests.
 *
 * @license   http://www.horde.org/licenses/gpl GPLv2
 *
 * @copyright 2012-2020 Horde LLC (http://www.horde.org)
 * @author    Michael J Rubinsky <mrubinsk@horde.org>
 * @package   ActiveSync
 * @internal
 */
class Horde_ActiveSync_Request_Autodiscover extends Horde_ActiveSync_Request_Base
{
    /**
     * Handle request
     *
     * @return text  The content type of the response (text/xml).
     */
    public function handle(?Horde_Controller_Request $request = null)
    {
        $parser = xml_parser_create();

        // Get $_SERVER
        $server = $request->getServerVars();

        // Version 2 Autodisover request. Version 2 is always unauthenticated.
        if (!empty($server['REQUEST_URI']) && stripos($server['REQUEST_URI'], 'autodiscover/autodiscover.json') !== false) {
            $get = $request->getGetVars();
            // The Protocol query parameter is required by the v2 protocol, but
            // guard against malformed clients that omit it. This endpoint only
            // serves ActiveSync, so that is the sensible default.
            $protocol = empty($get['Protocol']) ? 'ActiveSync' : $get['Protocol'];
            $params = ['protocol' => $protocol];
            $results = $this->_driver->autoDiscover($params, 2);
            if (!empty($results['url'])) {
                $this->_encoder->getStream()->add(json_encode(
                    ['Protocol' => $protocol, 'Url' => $results['url']],
                    JSON_UNESCAPED_SLASHES
                ));
            }
            return 'application/json';
        }

        xml_parse_into_struct(
            $parser,
            $this->_decoder->getStream()->getString(),
            $values
        );

        // Obtain the credentials sent by the client.
        // NOTE: Some broken clients *cough* android *cough* don't send the
        // actual XML data structure at all, but instead use the email address
        // as the username in the HTTP_AUTHENTICATION data. There are so many
        // things wrong with this, but try to work around it if we can.
        $credentials = new Horde_ActiveSync_Credentials($this->_activeSync);
        $username = $credentials->username;
        if (empty($values) && empty($username)) {
            throw new Horde_Exception_AuthenticationFailure('No username provided.');
        } elseif (!empty($values)) {
            // Override the username; AUTODISCOVER MUST use the email address.
            // Locate the EMailAddress element by tag name instead of relying on
            // a fixed offset ($values[2]), which breaks (and can select the
            // wrong node) if a client reorders or adds elements. The wire value
            // is always an email address per the Autodiscover protocol; mapping
            // it to the backend username (email, AD/LDAP, plain username, ...)
            // is handled downstream by the driver's getUsernameFromEmail().
            $email = null;
            foreach ($values as $value) {
                if (!empty($value['tag']) && $value['tag'] == 'EMAILADDRESS'
                    && isset($value['value'])) {
                    $email = trim($value['value']);
                    break;
                }
            }
            if ($email !== null && $email !== '') {
                $credentials->username = $email;
            } elseif (empty($username)) {
                // No EMailAddress element and no username from the auth header.
                throw new Horde_Exception_AuthenticationFailure('No username provided.');
            }
        }

        if (!$this->_activeSync->authenticate($credentials)) {
            throw new Horde_Exception_AuthenticationFailure();
        }

        if (!empty($values)) {
            $params = ['request_schema' => trim($values[0]['attributes']['XMLNS'])];
            // Response Schema is not in a set place.
            foreach ($values as $value) {
                if ($value['tag'] == 'ACCEPTABLERESPONSESCHEMA') {
                    $params['response_schema'] = trim($value['value']);
                    break;
                }
            }
        } else {
            // Assume broken clients want these schemas.
            $params = [
                'request_schema' => 'http://schemas.microsoft.com/exchange/autodiscover/mobilesync/requestschema/2006',
                'response_schema' => 'http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006',
            ];
        }
        $results = $this->_driver->autoDiscover($params);
        if (empty($results['raw_xml'])) {
            $this->_encoder->getStream()->add($this->_buildResponseString($results));
        } else {
            // The backend is taking control of the XML.
            $this->_encoder->getStream()->add($results['raw_xml']);
        }

        return 'text/xml';
    }

    /**
     * Noop. This class overrides the handle method.
     */
    protected function _handle() {}

    /**
     * Escape a value for safe inclusion in the XML responses built below.
     *
     * The interpolated values (email, display name, schemas echoed back from
     * the client request, backend host names, etc.) are otherwise concatenated
     * straight into the response markup, which both corrupts the XML for values
     * containing metacharacters and allows content injection for the
     * client-supplied schema values.
     *
     * @param mixed $value  The value to escape.
     *
     * @return string  The XML-escaped value.
     */
    protected function _xmlEscape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Build the appropriate response string to send back to the client.
     *
     * @param array $properties  An array containing any needed properties.
     *   Required properties for mobile sync:
     *   - request_schema:  The request schema sent by the client.
     *   - response_schema: The schema the client indicated it can accept.
     *   - culture: The culture value (normally 'en:en').
     *   - display_name:  The user's configured display name.
     *   - email: The user's email address.
     *   - url:  The url of the Microsoft-Servers-ActiveSync endpoint for this
     *           user to use.
     *
     *   Properties used for Outlook schema:
     *   - imap:  Array describing the IMAP server.
     *   - pop:   Array describing the POP3 server.
     *   - smtp:  Array describing the SMTP server.
     *
     *  @return string  The XML to return to the client.
     */
    protected function _buildResponseString($properties)
    {
        // Default response is for mobilesync.
        if (empty($properties['request_schema'])
            || stripos($properties['request_schema'], 'autodiscover/mobilesync') !== false) {

            return '<?xml version="1.0" encoding="utf-8"?>
              <Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">
                <Response xmlns="http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006">
                  <Culture>' . $this->_xmlEscape($properties['culture']) . '</Culture>
                  <User>
                    <DisplayName>' . $this->_xmlEscape($properties['display_name']) . '</DisplayName>
                    <EMailAddress>' . $this->_xmlEscape($properties['email']) . '</EMailAddress>
                  </User>
                  <Action>
                    <Settings>
                      <Server>
                        <Type>MobileSync</Type>
                        <Url>' . $this->_xmlEscape($properties['url']) . '</Url>
                        <Name>' . $this->_xmlEscape($properties['url']) . '</Name>
                       </Server>
                    </Settings>
                  </Action>
                </Response>
              </Autodiscover>';
        } elseif (stripos($properties['request_schema'], 'autodiscover/outlook') !== false) {
            if (empty($properties['response_schema'])) {
                // Missing required response_schema
                return $this->_buildFailureResponse($properties['email'], '600', 'http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a');
            }

            $xml = '<Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">
                <Response xmlns="' . $this->_xmlEscape($properties['response_schema']) . '">
                <User>
                    <DisplayName>' . $this->_xmlEscape($properties['display_name']) . '</DisplayName>
                </User>
                <Account>
                    <AccountType>email</AccountType>
                    <Action>settings</Action>';

            if (!empty($properties['imap'])) {
                $xml .= '<Protocol>
                    <Type>IMAP</Type>
                    <Server>' . $this->_xmlEscape($properties['imap']['host']) . '</Server>
                    <Port>' . $this->_xmlEscape($properties['imap']['port']) . '</Port>
                    <LoginName>' . $this->_xmlEscape($properties['username']) . '</LoginName>
                    <DomainRequired>off</DomainRequired>
                    <SPA>off</SPA>
                    ' . $this->_getEncryptionValue('imap', $properties) . '
                    <AuthRequired>on</AuthRequired>
                    </Protocol>';
            }
            if (!empty($properties['pop'])) {
                $xml .= '<Protocol>
                    <Type>POP3</Type>
                    <Server>' . $this->_xmlEscape($properties['pop']['host']) . '</Server>
                    <Port>' . $this->_xmlEscape($properties['pop']['port']) . '</Port>
                    <LoginName>' . $this->_xmlEscape($properties['username']) . '</LoginName>
                    <DomainRequired>off</DomainRequired>
                    <SPA>off</SPA>
                    ' . $this->_getEncryptionValue('pop', $properties) . '
                    <AuthRequired>on</AuthRequired>
                    </Protocol>';
            }
            if (!empty($properties['smtp'])) {
                $xml .= '<Protocol>
                    <Type>SMTP</Type>
                    <Server>' . $this->_xmlEscape($properties['smtp']['host']) . '</Server>
                    <Port>' . $this->_xmlEscape($properties['smtp']['port']) . '</Port>
                    <LoginName>' . $this->_xmlEscape($properties['username']) . '</LoginName>
                    <DomainRequired>off</DomainRequired>
                    <SPA>off</SPA>
                    ' . $this->_getEncryptionValue('smtp', $properties) . '
                    <AuthRequired>on</AuthRequired>
                    <UsePOPAuth>' . ($properties['smtp']['popauth'] ? 'on' : 'off') . '</UsePOPAuth>
                    </Protocol>';
            }
            $xml .= '</Account>
                </Response>
                </Autodiscover>';

            return $xml;
        } else {
            // Unknown request.
            return $this->_buildFailureResponse($properties['email'], '600', $properties['response_schema']);
        }
    }

    protected function _getEncryptionValue($type, $properties)
    {
        if (!empty($properties[$type]['encryption'])) {
            return '<Encryption>' . $this->_xmlEscape($properties[$type]['encryption']) . '</Encryption>';
        }
        // Older version of autodiscover.
        if (!empty($properties[$type]['ssl'])) {
            return '<Encryption>SSL</Encryption>';
        }

        // None specified.
        return '<Encryption>None</Encryption>';
    }

    /**
     * Output failure response code.
     *
     * @param string $email   The email of the user attempting Autodiscover.
     * @param string $status  An appropriate status code for the error. E.g.,
     *                        600 - Invalid response.
     *                        601 - Provider not found for requested schema.
     * @param string $response_schema  The response schema value.
     *
     * @return string  The XML to send to the client.
     */
    protected function _buildFailureResponse($email, $status, $response_schema)
    {
        return '<?xml version="1.0" encoding="utf-8"?>
          <Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">
            <Response xmlns="' . $this->_xmlEscape($response_schema) . '">
              <Culture>en:us</Culture>
              <User>
                <EMailAddress>' . $this->_xmlEscape($email) . '</EMailAddress>
              </User>
              <Action>
                <Error>
                  <Status>' . $this->_xmlEscape($status) . '</Status>
                  <Message>Unable to autoconfigure the supplied email address.</Message>
                  <DebugData>MailUser</DebugData>
                </Error>
              </Action>
            </Response>
          </Autodiscover>';
    }

}
