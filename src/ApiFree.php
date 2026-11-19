<?php

declare(strict_types=1);

namespace Dasintranet\Framework;

use InvalidArgumentException;

class ApiFree
{
    public string $method = 'GET';
    public ?string $url = null;
    public mixed $body = '';
    public int $status = 0;
    public mixed $response = null;

    /** Off by default: prevent disclosing session cookies to external APIs. */
    public bool $shareSession = false;

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

        $result = HttpClient::request($this->url, $this->method, $this->body, [
            'Accept: application/json',
            'Content-Type: application/json',
        ], $this->shareSession);

        $this->status = $result['status'];
        $this->response = $result['response'];
        return $this->response;
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
