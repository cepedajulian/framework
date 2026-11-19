<?php

declare(strict_types=1);

namespace Dasintranet\Framework;

use InvalidArgumentException;

class Api
{
    public string $method = 'GET';
    public ?string $url = null;
    public mixed $body = '';
    public string $token = '';
    public int $status = 0;
    public mixed $response = null;

    public function __construct($url = null, $method = null, $body = null, $token = null)
    {
        $this->apply($url, $method, $body, $token);
        if ($url !== null) {
            $this->exec();
        }
    }

    public function getAccess(string $url, string $client_id, string $client_secret): mixed
    {
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw new InvalidArgumentException('HTTPS is required when sending Basic authentication credentials.');
        }

        $basic = base64_encode($client_id . ':' . $client_secret);
        $result = HttpClient::request($url, 'GET', null, [
            'Authorization: Basic ' . $basic,
            'Accept: application/json',
        ]);

        $this->status = $result['status'];
        $this->response = $result['response'];
        return $this->response;
    }

    public function exec($url = null, $method = null, $body = null, $token = null): mixed
    {
        $this->apply($url, $method, $body, $token);
        if ($this->url === null) {
            throw new InvalidArgumentException('An API URL is required.');
        }

        if (str_contains($this->token, "\r") || str_contains($this->token, "\n")) {
            throw new InvalidArgumentException('Invalid bearer token.');
        }

        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $result = HttpClient::request($this->url, $this->method, $this->body, $headers);
        $this->status = $result['status'];
        $this->response = $result['response'];
        return $this->response;
    }

    private function apply($url, $method, $body, $token): void
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
        if ($token !== null) {
            $this->token = (string) $token;
        }
    }
}
