<?php

/**
 * A simple Print API REST client.
 *
 * This utility makes it simple to communicate with {@link https://www.printapi.nl Print API}, a
 * powerful and secure API that lets you print and ship your PDF or image files as a wide range of
 * products, like hardcover books, softcover books, canvases, posters, wood prints and many more.
 *
 * Docs: https://portal.printapi.nl/test/docs
 * 
 *
 * @package Print API
 * @version 1.0.2
 * @copyright 2016 Print API
 */
final class PrintApi
{
    const LIVE_BASE_URI = 'https://live.printapi.nl/v1/';
    const TEST_BASE_URI = 'https://test.printapi.nl/v1/';
    const USER_AGENT = 'Print API PHP Client v1.0.2';

    /**
     * Call this to obtain an authenticated Print API client.
     *
     * The client ID and secret can be obtained by creating a free Print API account at:
     * https://portal.printapi.nl/test/account/register
     *
     * @param string $clientId    The client ID assigned to your application.
     * @param array  $secret      The secret assigned to your application.
     * @param array  $environment One of "test" or "live".
     *
     * @return PrintApi An authenticated Print API client.
     *
     * @throws PrintApiException         If the HTTP request fails altogether.
     * @throws PrintApiResponseException If the API response indicates an error.
     */
    static public function authenticate($clientId, $secret, $environment = 'test')
    {
        // Construct OAuth 2.0 token endpoint URL:

        $baseUri = self::_getbaseUri($environment);
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
        return new PrintApi($baseUri, $token);
    }

    // ==============
    // Static helpers
    // ==============

    /**
     * @return string The base URI of the specified Print API environment.
     */
    static private function _getbaseUri($environment)
    {
        if ($environment !== 'test' && $environment !== 'live') {
            throw new PrintApiException('Unknown environment: '. $environment
                . '. Must be one of "test" or "live".');
        }

        if ($environment == 'test') {
            return self::TEST_BASE_URI;
        } else if ($environment === 'live') {
            return self::LIVE_BASE_URI;
        }
    }

    /**
     * @return string The parameters for the Print API OAuth token endpoint.
     */
    static private function _formatOAuthParameters($clientId, $secret)
    {
        return 'grant_type=client_credentials'
            . '&client_id=' . urlencode($clientId)
            . '&client_secret=' . urlencode($secret);
    }

    /**
     * Sets common cURL options, like timeout length.
     */
    static private function _setDefaultCurlOpts($ch)
    {
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true); 
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    }

    /**
     * @throws PrintApiException         If the cURL request failed.
     * @throws PrintApiResponseException If the API returned an error report.
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

    private $baseUri;
    private $token;

    /**
     * Call {@link authenticate()} to obtain an instance of this class.
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
     * @throws PrintApiException         If the HTTP request fails altogether.
     * @throws PrintApiResponseException If the API response indicates an error.
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
     * @throws PrintApiException         If the HTTP request fails altogether.
     * @throws PrintApiResponseException If the API response indicates an error.
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

    // ===============
    // Private helpers
    // ===============

    /**
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
     * @return object The decoded API response.
     */
    private function _request($method, $uri, $content = null, $contentType = null)
    {
        // Create cURL handle:

        $ch = curl_init($uri);
        self::_setDefaultCurlOpts($ch);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

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

/**
 * Generic exception thrown by the Print API client.
 */
class PrintApiException extends Exception
{ }

/**
 * Exception thrown by the Print API client for failed API calls.
 */
class PrintApiResponseException extends PrintApiException
{ }