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
        $this->developer_key = getenv('NEWS_API_KEY');
        $this->endpoint = 'https://news.wayne.edu/api/v1/';
        $this->payload_dir = getenv('NEWS_API_CACHE');
        $this->payload_file = $this->payload_dir . 'payload.json';
        $this->client = new Client();
        $this->payload = [];
        $this->buffer_time = 60; // In Seconds

        // If there is an override for the endpoint
        if (getenv('NEWS_API_ENDPOINT') != '') {
            $this->endpoint = getenv('NEWS_API_ENDPOINT');
        }

        // Make sure we have a token cache file
        $this->setup();
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

        // Check if the token is expired
        if (!isset($payload['exp']) || strtotime(date("Y-m-d H:i:s")) >= ($payload['exp'] - $this->buffer_time)) {
            // Get a new payload
            $payload = $this->getPayload();

            // Write payload to cache
            $this->writePayloadToCache($payload);
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
            echo 'Guzzle Error: ' . $e->getMessage();
        }

        // If successful return the request
        if ($response->getStatusCode() == 200) {
            $request_response = json_decode($response->getBody()->getContents(), true);

            // Check for errors
            if (isset($request_response['errors'])) {
                // Set the error response
                $request = $request_response;
            } else {
                // Set the data response
                $request = $request_response;
            }

            return $request;
        }

        return false;
    }
}
