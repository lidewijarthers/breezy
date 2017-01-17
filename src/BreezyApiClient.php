<?php

namespace Briedis\Breezy;

use Briedis\Breezy\Exceptions\BreezyApiException;
use Briedis\Breezy\Exceptions\BreezyException;

/**
 * Class PrintfulClient
 */
class BreezyApiClient
{
    const USER_AGENT = 'Breezy PHP wrapper (https://github.com/briedis/breezy)';

    public $url = 'https://breezy.hr/public/api/v2/';

    private $lastResponseRaw;

    private $lastResponse;

    /**
     * Token received after successful sing-in
     * @var string
     */
    private $token;

    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * Perform a GET request to the API
     * @param string $path Request path
     * @param array $params Additional GET parameters as an associative array
     * @return mixed
     */
    public function get($path, $params = [])
    {
        return $this->request('GET', $path, $params);
    }

    /**
     * Perform a DELETE request to the API
     * @param string $path Request path
     * @param array $params Additional GET parameters as an associative array
     * @return mixed
     */
    public function delete($path, $params = [])
    {
        return $this->request('DELETE', $path, $params);
    }

    /**
     * Perform a POST request to the API
     * @param string $path Request path
     * @param array $data Request body data as an associative array
     * @param array $params Additional GET parameters as an associative array
     * @return mixed
     */
    public function post($path, $data = [], $params = [])
    {
        return $this->request('POST', $path, $params, $data);
    }

    /**
     * Perform a PUT request to the API
     * @param string $path Request path
     * @param array $data Request body data as an associative array
     * @param array $params Additional GET parameters as an associative array
     * @return mixed
     */
    public function put($path, $data = [], $params = [])
    {
        return $this->request('PUT', $path, $params, $data);
    }

    /**
     * Return raw response data from the last request
     * @return string|null Response data
     */
    public function getLastResponseRaw()
    {
        return $this->lastResponseRaw;
    }

    /**
     * Return decoded response data from the last request
     * @return array|null Response data
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * Internal request implementation
     * @param string $method POST, GET, etc.
     * @param string $path API endpoint path
     * @param array $query Query parameters
     * @param array|mixed $data Post data
     * @return mixed
     * @throws BreezyException
     */
    private function request($method, $path, array $query = [], $data = null)
    {
        $this->lastResponseRaw = null;
        $this->lastResponse = null;

        $curl = $this->initCurl($path, $query, $method);

        $bodyLength = 0;
        if ($data !== null) {
            $bodyEncoded = json_encode($data);
            $bodyLength = strlen($bodyEncoded);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $bodyEncoded);
        }

        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . $bodyLength,

        ];

        if ($this->token) {
            $headers[] = 'Authorization: ' . $this->token;
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $responseRaw = curl_exec($curl);

        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $errorNumber = curl_errno($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($errorNumber) {
            throw new BreezyApiException('CURL: ' . $error, $errorNumber);
        }

        $response = json_decode($responseRaw, true);

        if ($responseCode >= 400 || !empty($response['error'])) {
            throw new BreezyApiException($response['error'], $responseCode);
        }

        $this->lastResponse = $response;
        $this->lastResponseRaw = $responseRaw;

        return $response;
    }

    /**
     * @param string $path
     * @param array $query Query parameters
     * @param string $method
     * @return resource cURL handler
     */
    private function initCurl($path, array $query, $method)
    {
        $url = $this->url . trim($path, '/');

        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 3);

        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);

        curl_setopt($curl, CURLOPT_USERAGENT, self::USER_AGENT);

        return $curl;
    }

    /**
     * Sign in and set access token for consecutive requests
     * @param string $email
     * @param string $password
     * @return string Access token
     */
    public function signIn($email, $password)
    {
        $response = $this->post('signin', [
            'email' => $email,
            'password' => $password,
        ]);

        $token = $response['access_token'];

        // Remember token for all consecutive request
        $this->setToken($token);

        return $token;
    }
}