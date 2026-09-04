<?php

/**
 * @file plugins/importexport/metafora/classes/api/MetaforaApiClient.php
 *
 * Metafora Export Plugin for OJS 3.5
 *
 * @author Dmitry Matveev (Дмитрий Матвеев)
 * @license GPL-3.0-or-later
 */

namespace APP\plugins\importexport\metafora\classes\api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;

class MetaforaApiClient
{
    private Client $client;
    private string $apiUrl;
    private string $apiToken;

    public function __construct(string $apiUrl, string $apiToken, ?Client $client = null)
    {
        $apiUrl = trim($apiUrl);
        if ($apiUrl === '') {
            throw new InvalidArgumentException('Metafora API URL must not be empty.');
        }

        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiToken = trim($apiToken);
        $this->client = $client ?? new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    /**
     * Perform a request relative to the configured API URL.
     *
     * The exact endpoint paths are intentionally not hard-coded here. They
     * must be taken from the current Metafora API specification.
     *
     * @throws GuzzleException
     */
    public function request(string $method, string $endpoint = '', array $options = []): array
    {
        $headers = $options['headers'] ?? [];
        $headers['Accept'] = $headers['Accept'] ?? 'application/json';

        if ($this->apiToken !== '') {
            $headers['Authorization'] = $headers['Authorization'] ?? 'Bearer ' . $this->apiToken;
        }

        $options['headers'] = $headers;

        $response = $this->client->request(
            strtoupper($method),
            $this->buildUrl($endpoint),
            $options
        );

        $body = (string) $response->getBody();
        $decoded = null;
        if ($body !== '') {
            $decoded = json_decode($body, true);
        }

        return [
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => $body,
            'json' => is_array($decoded) ? $decoded : null,
        ];
    }

    /**
     * Lightweight connectivity check against the configured URL or a
     * supplied relative endpoint.
     *
     * @throws GuzzleException
     */
    public function testConnection(string $endpoint = ''): array
    {
        return $this->request('GET', $endpoint);
    }

    private function buildUrl(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return $this->apiUrl;
        }

        return $this->apiUrl . '/' . ltrim($endpoint, '/');
    }
}
