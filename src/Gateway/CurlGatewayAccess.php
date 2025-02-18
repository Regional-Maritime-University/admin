<?php

namespace Src\Gateway;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class CurlGatewayAccess
{
    private $url;
    private $headers;
    private $payload;
    private $client;

    public function __construct($url, $headers, $payload)
    {
        $this->url = $url;
        $this->headers = $headers;
        $this->payload = $payload;

        // Get the path to the bundled cacert.pem file that comes with Guzzle
        $certPath = dirname(__DIR__, 2) . '/vendor/guzzlehttp/guzzle/src/cacert.pem';

        // If the bundled cert doesn't exist, download it
        if (!file_exists($certPath)) {
            $certPath = dirname(__DIR__, 2) . '/cacert.pem';
            if (!file_exists($certPath)) {
                file_put_contents($certPath, file_get_contents('https://curl.se/ca/cacert.pem'));
            }
        }

        // Initialize client with SSL certificate
        $this->client = new Client([
            'verify' => $certPath
        ]);
    }

    public function initiateProcess()
    {
        try {
            // Convert headers array to associative array
            $headers = [];
            foreach ($this->headers as $header) {
                list($key, $value) = explode(': ', $header);
                $headers[$key] = $value;
            }

            $response = $this->client->post($this->url, [
                'headers' => $headers,
                'body' => $this->payload,
                'http_errors' => false
            ]);

            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            return json_encode([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
