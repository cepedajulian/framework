<?php

declare(strict_types=1);

namespace Dasintranet\Framework;

use InvalidArgumentException;

/** HTTP client without a token, with legacy body handling and safe local sessions. */
class ApiFree
{
    public string $method = 'GET';
    public ?string $url = null;
    /** Request body, not the response body. */
    public mixed $body = '';
    public int $status = 0;
    /** Decoded JSON object/array, or original response string. */
    public mixed $response = null;
    /** Exact raw response body (without decoding). */
    public string $rawBody = '';
    /** @var array<string,list<string>> Response headers, lowercase field names. */
    public array $responseHeaders = [];
    /** Whether the last request actually forwarded a PHP session cookie. */
    public bool $sessionForwarded = false;

    /**
     * Automatically share the active web session with this server over HTTPS.
     * External hosts never receive session credentials.
     */
    public bool $shareSession = true;

    /** @var list<string> Additional trusted hosts (e.g., configured tenant subdomains). */
    public array $sessionHosts = [];

    /**
     * 'legacy': string/array passed to cURL as in Neslian; 'json': encode
     * arrays/objects as JSON; 'form': application/x-www-form-urlencoded arrays.
     */
    public string $bodyEncoding = 'legacy';

    /**
     * Preserve the old behavior of propagating the API's HTTP status and safe
     * response headers to the current web response. No automatic body echo.
     */
    public bool $forwardResponse = true;

    public function __construct($url = null, $method = null, $body = null)
    {
        $this->apply($url, $method, $body);
        if ($url !== null) {
            $this->exec();
        }
    }

    public function exec($url = null, $method = null, $body = null): mixed
    {
        $this->apply($url, $method, $body);
        if ($this->url === null) {
            throw new InvalidArgumentException('An API URL is required.');
        }

        $this->status = 0;
        $this->response = null;
        $this->rawBody = '';
        $this->responseHeaders = [];
        $this->sessionForwarded = false;

        $result = HttpClient::request(
            $this->url,
            $this->method,
            $this->body,
            ['Accept: application/json', 'Content-Type: application/json'],
            $this->shareSession,
            $this->sessionHosts,
            $this->bodyEncoding
        );

        $this->status = $result['status'];
        $this->response = $result['response'];
        $this->rawBody = $result['rawBody'];
        $this->responseHeaders = $result['headers'];
        $this->sessionForwarded = $result['sessionForwarded'];

        if ($this->forwardResponse) {
            $this->forwardHttpResponse();
        }

        return $this->response;
    }

    private function forwardHttpResponse(): void
    {
        if ($this->status >= 100 && $this->status <= 599) {
            http_response_code($this->status);
        }

        if (headers_sent()) {
            return;
        }

        // Do not relay Set-Cookie, authentication, hop-by-hop, Content-Length,
        // Content-Encoding or server-related headers into the browser response.
        foreach (['content-type', 'cache-control', 'expires', 'etag', 'last-modified', 'vary', 'location'] as $name) {
            foreach ($this->responseHeaders[$name] ?? [] as $value) {
                if (!str_contains($value, "\r") && !str_contains($value, "\n")) {
                    header($name . ': ' . $value, true);
                }
            }
        }
    }

    private function apply($url, $method, $body): void
    {
        if ($url !== null) {
            $this->url = (string) $url;
        }
        if ($method !== null) {
            $this->method = strtoupper((string) $method);
        }
        if ($body !== null) {
            $this->body = $body;
        }
    }
}
