<?php namespace Waynestate\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

class News
{
    /** @var  string */
    protected $developer_key;

    /** @var  string */
    protected $endpoint;

    /** @var  string */
    protected $payload_dir;

    /** @var  string */
    protected $payload_file;

    /** @var Guzzle\Http\Client */
    protected $client;

    /** @var array */
    protected $payload;

    /** @var int */
    protected $buffer_time;

    /**
     * @param $developer_key
     */
    public function __construct()
    {
        $envVariables = $this->getEnvVariables();

        $this->developer_key = $envVariables['NEWS_API_KEY'];
        $this->endpoint = 'https://news.wayne.edu/api/v1/';
        $this->payload_dir = $envVariables['NEWS_API_CACHE'];
        $this->payload_file = $this->payload_dir . 'payload.json';
        $this->client = new Client();
        $this->payload = [];
        $this->buffer_time = 60; // In Seconds

        // If there is an override for the endpoint
        if (!empty($envVariables['NEWS_API_ENDPOINT'])) {
            $this->endpoint = $envVariables['NEWS_API_ENDPOINT'];
        }

        // Make sure we have a token cache file
        $this->setup();
    }

    /**
     * Get the Environment Variables
     *
     * If the Laravel Helper function env is available then use it, otherwise getenv
     *
     * @return array
     */
    private function getEnvVariables()
    {
        if(function_exists('env')){
            return [
                'NEWS_API_KEY' => env('NEWS_API_KEY'),
                'NEWS_API_CACHE' => env('NEWS_API_CACHE'),
                'NEWS_API_ENDPOINT' => env('NEWS_API_ENDPOINT'),
            ];
        }

        return [
            'NEWS_API_KEY' => getenv('NEWS_API_KEY'),
            'NEWS_API_CACHE' => getenv('NEWS_API_CACHE'),
            'NEWS_API_ENDPOINT' => getenv('NEWS_API_ENDPOINT'),
        ];
    }

    /**
     * Setup the payload cache file if it doesn't exist
     */
    private function setup()
    {
        // Make sure we have a payload directory
        if ($this->payload_dir == '') {
            return false;
        }

        // Create the directories
        if (!is_dir($this->payload_dir)) {
            mkdir($this->payload_dir, 02770, true);
        }

        // Create the payload file
        if (!is_file($this->payload_file)) {
            file_put_contents($this->payload_file, '');
            chmod($this->payload_file, 02770);
        }
    }

    /**
     * Verify we have a valid token
     *
     * @return bool
     */
    public function verify()
    {
        // Read in the cached payload
        $payload = $this->getPayloadFromCache();

        // Add auth/token onto the end of the endpoint to verify that it matches the payload cache
        $test_endpoint = $this->endpoint.(substr($this->endpoint, -1, 1) != '/' ? '/' : '').'auth/token';

        // Check if the token is expired or if the endpoint changed
        if (
            (!isset($payload['exp']) || strtotime(date("Y-m-d H:i:s")) >= ($payload['exp'] - $this->buffer_time)) ||
            strcasecmp($payload['iss'], $test_endpoint) !== 0
        ) {
            // Get a new payload
            try {
                $payload = $this->getPayload();
            } catch (TransferException $e) {
                throw new $e($e->getMessage());
            } catch(\Exception $e) {
                throw new $e($e->getMessage());
            }

            // Write payload to cache
            if (!empty($payload)) {
                $this->writePayloadToCache($payload);
            }
        }

        // Set the payload
        $this->payload = $payload;

        return isset($payload['token']);
    }

    /**
     * Return a json decoded token
     *
     * @return mixed
     */
    private function getPayloadFromCache()
    {
        return json_decode(file_get_contents($this->payload_file), true);
    }

    /**
     * Get the payload from the News API
     *
     * @return mixed
     */
    private function getPayload()
    {
        $response = $this->client->request('GET', $this->endpoint . 'auth/token/', [
            'query' => [
                'developer_key' => $this->developer_key
            ],
            'verify' => false
        ]);

        if ($response->getStatusCode() == 200) {
            $payload_response = json_decode($response->getBody()->getContents(), true);

            if (isset($payload_response['errors'])) {
                throw new \Exception(print_r($payload_response['errors'], true));
            }

            $payload = $payload_response['data'];

            return $payload;
        }

        return false;
    }

    /**
     * Write the payload to cache
     *
     * @param $token
     * @return int
     */
    private function writePayloadToCache($payload)
    {
        return file_put_contents($this->payload_file, json_encode($payload));
    }

    /**
     * Build the API request
     *
     * @param string $method
     * @param array $params
     * @return mixed
     */
    public function request($method = '', $params = array())
    {
        // Verify the token
        if (!$this->verify()) {
            // Invalid token
            return false;
        }

        // If they pass an ID, convert it to be part of the endpoint instead
        if (isset($params['id'])) {
            $method .= (substr($method, -1) != '/' ? '/' : '') . $params['id'];
        }

        // Send the request
        try {
            $response = $this->client->request('GET', $this->endpoint . $method, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->payload['token']
                ],
                'query' => $params,
                'verify' => false
            ]);
        } catch (TransferException $e) {
            error_log($e->getMessage(), 0);
        }

        // If successful return the request
        if ($response->getStatusCode() == 200) {
            $request_response = json_decode($response->getBody()->getContents(), true);

            // Check for errors
            if (isset($request_response['errors'])) {
                error_log(print_r($request_response['errors'], true), 0);
            }

            return $request_response;
        }

        return false;
    }
}
