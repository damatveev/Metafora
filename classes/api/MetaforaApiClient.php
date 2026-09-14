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
    /** Official Metafora API v2 file operations verified from the RCSI documentation. */
    public const ENDPOINT_JATS_XML = 'files/jats/xml/';
    public const ENDPOINT_JATS_XML_PDF = 'files/jats/xml_pdf/';
    public const ENDPOINT_JOURNAL_XML = 'files/journal/';
    public const ENDPOINT_PDF = 'files/pdf/';
    public const ENDPOINT_STATUS = 'files/status/';
    public const ENDPOINT_FILES = 'files/';
    public const ENDPOINT_PUBLICATION_BY_DOI = 'publications/doi/';
    public const ENDPOINT_PUBLICATIONS = 'publications/';

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
     * Perform a request relative to the configured Metafora API v2 URL.
     *
     * Official Metafora documentation requires the API key in the `Api-Key`
     * request header. Request bodies are supplied by the transport layer so
     * multipart field names are not guessed here.
     *
     * @throws GuzzleException
     */
    public function request(string $method, string $endpoint = '', array $options = []): array
    {
        $headers = $options['headers'] ?? [];
        $headers['Accept'] = $headers['Accept'] ?? 'application/json';

        if ($this->apiToken !== '') {
            $headers['Api-Key'] = $headers['Api-Key'] ?? $this->apiToken;
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

        $this->logDiagnosticResponse(
            strtoupper($method),
            $endpoint,
            $response->getStatusCode(),
            $body
        );

        return [
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => $body,
            'json' => is_array($decoded) ? $decoded : null,
        ];
    }

    /**
     * Log only the two read-only synchronization responses requested for
     * diagnostics. Request headers are deliberately excluded so the Api-Key
     * can never be written to the log.
     */
    private function logDiagnosticResponse(
        string $method,
        string $endpoint,
        int $status,
        string $body
    ): void {
        if ($method !== 'GET') {
            return;
        }

        $path = ltrim($endpoint, '/');
        if (
            !str_starts_with($path, self::ENDPOINT_STATUS)
            && !str_starts_with($path, self::ENDPOINT_PUBLICATION_BY_DOI)
        ) {
            return;
        }

        error_log(sprintf(
            '[Metafora sync] GET %s HTTP %d response=%s',
            $path,
            $status,
            $body === '' ? '<empty>' : $body
        ));
    }

    /**
     * Lightweight connectivity check against the configured URL or supplied
     * relative endpoint. No secret is returned to callers.
     *
     * @throws GuzzleException
     */
    public function testConnection(string $endpoint = ''): array
    {
        if (trim($endpoint) === '') {
            $endpoint = self::ENDPOINT_STATUS . '?file_uid=00000000-0000-0000-0000-000000000000';
        }
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

    /**
     * Upload PDF file.
     */
    public function uploadPdf(string $filePath, string $fileUid): array
    {
        return $this->request(
            'POST',
            self::ENDPOINT_PDF . '?file_uid=' . rawurlencode($fileUid),
            [
                'multipart' => [
                    [
                        'name' => 'pdf',
                        'contents' => fopen($filePath, 'r'),
                        'filename' => basename($filePath),
                        'headers' => ['Content-Type' => 'application/pdf'],
                    ],
                ],
            ]
        );
    }


    /**
     * Upload JATS XML article to Metafora API.
     */
    public function sendJatsXml(
        string $filePath
    ): array {

        if (!is_file($filePath)) {
            throw new \InvalidArgumentException(
                'JATS file not found: ' . $filePath
            );
        }

        return $this->request(
            'POST',
            self::ENDPOINT_JATS_XML,
            [
                'multipart' => [
                    [
                        'name' => 'xml',
                        'contents' => fopen($filePath, 'r'),
                        'filename' => basename($filePath),
                        'headers' => ['Content-Type' => 'application/xml'],
                    ],
                    [
                        'name' => 'platform',
                        'contents' => 'metafora-publ-iasv-plugin',
                    ],
                ],
            ]
        );
    }


    /**
     * Upload JATS XML with PDF if Metafora requires combined package.
     */
    public function sendJatsXmlPdf(
        string $xmlPath,
        string $pdfPath
    ): array {

        if (!is_file($xmlPath)) {
            throw new \InvalidArgumentException(
                'XML file not found: ' . $xmlPath
            );
        }

        if (!is_file($pdfPath)) {
            throw new \InvalidArgumentException(
                'PDF file not found: ' . $pdfPath
            );
        }

        return $this->request(
            'POST',
            self::ENDPOINT_JATS_XML_PDF,
            [
                'multipart' => [
                    [
                        'name' => 'xml',
                        'contents' => fopen($xmlPath, 'r'),
                        'filename' => basename($xmlPath),
                        'headers' => ['Content-Type' => 'application/xml'],
                    ],
                    [
                        'name' => 'pdf',
                        'contents' => fopen($pdfPath, 'r'),
                        'filename' => basename($pdfPath),
                        'headers' => ['Content-Type' => 'application/pdf'],
                    ],
                    [
                        'name' => 'platform',
                        'contents' => 'metafora-publ-iasv-plugin',
                    ],
                ],
            ]
        );
    }


    /**
     * Check Metafora processing status.
     */
    public function checkStatus(
        string $identifier
    ): array {

        return $this->request(
            'GET',
            self::ENDPOINT_STATUS . '?file_uid=' . rawurlencode($identifier)
        );
    }

    /** Delete a processed XML file before explicitly restoring a deleted publication. */
    public function deleteFile(string $fileUid): array
    {
        $fileUid = trim($fileUid);
        if ($fileUid === '') {
            throw new InvalidArgumentException('File UID must not be empty.');
        }

        return $this->request(
            'DELETE',
            self::ENDPOINT_FILES . rawurlencode($fileUid)
        );
    }


    /**
     * Find a Metafora publication by DOI.
     */
    public function getPublicationByDoi(string $doi): array
    {
        $doi = trim($doi);
        if ($doi === '') {
            throw new InvalidArgumentException('DOI must not be empty.');
        }

        return $this->request(
            'GET',
            self::ENDPOINT_PUBLICATION_BY_DOI . $this->encodeDoiPath($doi)
        );
    }

    /** Preserve DOI path separators; Apache rejects an encoded slash (%2F). */
    private function encodeDoiPath(string $doi): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $doi)));
    }


    /**
     * Sign a Metafora publication.
     */
    public function signPublication(string $articleUid): array
    {
        $articleUid = trim($articleUid);
        if ($articleUid === '') {
            throw new InvalidArgumentException('Article UID must not be empty.');
        }

        return $this->request(
            'PUT',
            self::ENDPOINT_PUBLICATIONS . rawurlencode($articleUid) . '/sign/'
        );
    }


    /**
     * Revoke a Metafora publication signature.
     */
    public function unsignPublication(string $articleUid): array
    {
        $articleUid = trim($articleUid);
        if ($articleUid === '') {
            throw new InvalidArgumentException('Article UID must not be empty.');
        }

        return $this->request(
            'PUT',
            self::ENDPOINT_PUBLICATIONS . rawurlencode($articleUid) . '/unsign/'
        );
    }


}
