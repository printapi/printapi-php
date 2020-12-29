<?php

namespace PrintApi;

use PrintApi\Exception\PrintApiException;
use PrintApi\Exception\PrintApiResponseException;

/**
 * A simple Print API REST client.
 *
 * This small utility simplifies using our REST API from PHP. Print API offers a flexible and
 * secure REST API that lets you print and ship your PDF or image files as a wide range of
 * products, like hardcover books, softcover books, wood or aluminium prints and much more.
 *
 * Read more at: https://www.printapi.nl/services/rest-api
 *
 * @package Print API
 * @version 3.0.0
 * @copyright 2017 Print API
 */
final class Client
{
    const LIVE_BASE_URI = 'https://live.printapi.nl/v2/';
    const TEST_BASE_URI = 'https://test.printapi.nl/v2/';
    const USER_AGENT = 'Print API PHP Client v3.0.0';

    /**
     * Call this to obtain an authenticated Print API client.
     *
     * The client ID and secret can be obtained by creating a free Print API account at:
     * https://portal.printapi.nl/test/account/register
     *
     * @param string $clientId    The client ID assigned to your application.
     * @param string $secret      The secret assigned to your application.
     * @param string $environment One of "test" or "live".
     *
     * @return Client An authenticated Print API client.
     *
     * @throws \PrintApi\Exception\PrintApiException         If the HTTP request fails altogether.
     * @throws \PrintApi\Exception\PrintApiResponseException If the API response indicates an error.
     */
    static public function authenticate($clientId, $secret, $environment = 'test')
    {
        // Construct OAuth 2.0 token endpoint URL:

        $baseUri = self::_getBaseUri($environment);
        $oAuthUri = $baseUri . 'oauth';

        // Create cURL handle:

        $ch = curl_init($oAuthUri);
        self::_setDefaultCurlOpts($ch);

        // Set HTTP headers:

        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Set POST options:

        $oAuthParameters = self::_formatOAuthParameters($clientId, $secret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $oAuthParameters);
        curl_setopt($ch, CURLOPT_POST, true);

        // Execute request:

        $result = curl_exec($ch);
        self::_throwExceptionForFailure($ch, $result);
        curl_close($ch);

        $token = json_decode($result)->access_token;
        return new Client($baseUri, $token);
    }

    // ==============
    // Static helpers
    // ==============

    /**
     * Returns the base URI of the specified Print API environment.
     *
     * @param string $environment One of "test" or "live".
     *
     * @return string The base URI of the specified Print API environment.
     *
     * @throws \PrintApi\Exception\PrintApiException If the environment is unknown.
     */
    static private function _getBaseUri($environment)
    {
        if ($environment === 'test') {
            return self::TEST_BASE_URI;
        }

        if ($environment === 'live') {
            return self::LIVE_BASE_URI;
        }

        throw new PrintApiException('Unknown environment: '. $environment . '. Must be one of '
                . '"test" or "live".');
    }

    /**
     * Returns formatted parameters for the OAuth token endpoint.
     *
     * @param string $clientId    The client ID credential.
     * @param string $secret      The client secret credential.
     *
     * @return string Formatted parameters for the Print API OAuth token endpoint.
     */
    static private function _formatOAuthParameters($clientId, $secret)
    {
        return 'grant_type=client_credentials'
            . '&client_id=' . urlencode($clientId)
            . '&client_secret=' . urlencode($secret);
    }

    /**
     * Sets common cURL options.
     *
     * @param resource $ch The cURL handle.
     */
    static private function _setDefaultCurlOpts($ch)
    {
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // note: _request(...) overrides this
    }

    /**
     * Throws an exception if the specified cURL request failed.
     *
     * @param resource $ch     The cURL handle.
     * @param mixed    $result The result of curl_exec().
     *
     * @throws \PrintApi\Exception\PrintApiException         If the cURL request failed.
     * @throws \PrintApi\Exception\PrintApiResponseException If the API returned an error report.
     */
    static private function _throwExceptionForFailure($ch, $result)
    {
        // Check for cURL errors:

        $errno = curl_errno($ch);
        $error = curl_error($ch);

        if ($errno) {
            throw new PrintApiException('cURL error: ' . $error, $errno);
        }

        // Check for API responses indicating failure:

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new PrintApiResponseException($result, $statusCode);
        }
    }

    // ================
    // Instance members
    // ================

    /** @var string */ private $baseUri;
    /** @var string */ private $token;
    /** @var int */ private $timeout = 90;

    /**
     * Private constructor, call {@link authenticate()} to obtain an instance of this class.
     *
     * @param string $baseUri The base URI of the Print API environment.
     * @param string $token   An OAuth access token.
     */
    private function __construct($baseUri, $token)
    {
        $this->baseUri = $baseUri;
        $this->token = $token;
    }

    /**
     * Sends an HTTP POST request to Print API.
     *
     * @param string $uri        The destination URI. Can be absolute or relative.
     * @param array  $content    The request body as an associative array.
     * @param array  $parameters The query parameters as an associative array.
     *
     * @return object The decoded API response.
     *
     * @throws \PrintApi\Exception\PrintApiException         If the HTTP request fails altogether.
     * @throws \PrintApi\Exception\PrintApiResponseException If the API response indicates an error.
     */
    public function post($uri, $content, $parameters = array())
    {
        $uri = $this->_constructApiUri($uri, $parameters);
        $content = $content !== null ? json_encode($content) : null;
        return $this->_request('POST', $uri, $content, 'application/json');
    }

    /**
     * Sends an HTTP GET request to Print API.
     *
     * @param string $uri        The destination URI. Can be absolute or relative.
     * @param array  $parameters The query parameters as an associative array.
     *
     * @return object The decoded API response.
     *
     * @throws \PrintApi\Exception\PrintApiException         If the HTTP request fails altogether.
     * @throws \PrintApi\Exception\PrintApiResponseException If the API response indicates an error.
     */
    public function get($uri, $parameters = array())
    {
        $uri = $this->_constructApiUri($uri, $parameters);
        return $this->_request('GET', $uri);
    }

    /**
     * Uploads a file to Print API.
     *
     * @param string $uri       The destination URI. Can be absolute or relative.
     * @param string $fileName  The name of the file to upload.
     * @param string $mediaType One of "application/pdf", "image/jpeg" or "image/png".
     *
     * @return object The decoded API response.
     *
     * @throws PrintApiException         If the HTTP request fails altogether.
     * @throws PrintApiResponseException If the API response indicates an error.
     */
    public function upload($uri, $fileName, $mediaType)
    {
        $uri = $this->_constructApiUri($uri);
        $content = file_get_contents($fileName);
        return $this->_request('POST', $uri, $content, $mediaType);
    }

    /**
     * Gets the request timeout in seconds. 0 if timeout is disabled.
     *
     * @return int The request timeout in seconds.
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Sets the request timeout in seconds. Specify 0 to disable timeout.
     *
     * @param int $timeout The request timeout in seconds.
     *
     * @throws \PrintApi\Exception\PrintApiException If the specified timeout is not an integer.
     */
    public function setTimeout($timeout)
    {
        if (!is_int($timeout)) {
            throw new PrintApiException('Argument $timeout must be an integer.');
        }

        $this->timeout = $timeout;
    }

    // ===============
    // Private helpers
    // ===============

    /**
     * Generates a fully qualified URI for the API.
     *
     * @param string $uri        The destination URI. Can be absolute or relative.
     * @param array  $parameters The query parameters as an associative array.
     *
     * @return string A fully qualified API URI.
     */
    private function _constructApiUri($uri, $parameters = array())
    {
        $uri = trim($uri, '/');

        if (strpos($uri, $this->baseUri) === false) {
            $uri = $this->baseUri . $uri;
        }

        if (!empty($parameters)) {
            $uri .= '?' . http_build_query($parameters);
        }

        return $uri;
    }

    /**
     * Sends a custom HTTP request to the API.
     *
     * @param string      $method      The HTTP verb to use for the request.
     * @param string      $uri         The destination URI (absolute).
     * @param mixed       $content     The request body, e.g. a JSON string.
     * @param null|string $contentType The Content-Type HTTP header value.
     *
     * @return object The decoded API response.
     *
     * @throws \PrintApi\Exception\PrintApiException         If the HTTP request fails altogether.
     * @throws \PrintApi\Exception\PrintApiResponseException If the API response indicates an error.
     */
    private function _request($method, $uri, $content = null, $contentType = null)
    {
        // Create cURL handle:

        $ch = curl_init($uri);
        self::_setDefaultCurlOpts($ch);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        // Set HTTP headers:

        $headers = array();
        $headers[] = 'Accept: application/json';
        $headers[] = 'Authorization: Bearer ' . $this->token;

        if ($contentType !== null) {
            $headers[] = 'Content-Type: ' . $contentType;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Set request body:

        if ($content !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        }

        // Execute request:

        $result = curl_exec($ch);
        self::_throwExceptionForFailure($ch, $result);
        curl_close($ch);

        return json_decode($result);
    }
}
