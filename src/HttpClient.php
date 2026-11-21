<?php

declare(strict_types=1);

namespace Dasintranet\Framework;

use JsonException;
use RuntimeException;

/** Internal cURL adapter shared by Api and ApiFree. */
final class HttpClient
{
    /**
     * @param list<string> $headers
     * @param list<string> $sessionHosts Additional, explicitly trusted session destinations.
     * @param 'legacy'|'json'|'form' $bodyEncoding
     * @return array{status:int,response:mixed,rawBody:string,headers:array<string,list<string>>,sessionForwarded:bool}
     */
    public static function request(
        string $url,
        string $method,
        mixed $body,
        array $headers,
        bool $shareSession = false,
        array $sessionHosts = [],
        string $bodyEncoding = 'legacy'
    ): array {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = self::normalizeHost((string) parse_url($url, PHP_URL_HOST));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('Only absolute HTTP(S) URLs are supported.');
        }

        if (!function_exists('curl_init') && !function_exists(__NAMESPACE__ . '\\curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required.');
        }

        if (!in_array($bodyEncoding, ['legacy', 'json', 'form'], true)) {
            throw new RuntimeException('Invalid request body encoding.');
        }

        $method = strtoupper($method);
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        $sessionWasClosed = false;
        $sessionForwarded = false;
        /** @var array<string,list<string>> $receivedHeaders */
        $receivedHeaders = [];

        try {
            // Capture only the headers for the last HTTP response (not 100 Continue, etc.).
            $headerCallback = static function ($handle, string $line) use (&$receivedHeaders): int {
                $trimmed = trim($line);
                if (preg_match('~^HTTP/\S+\s+[0-9]{3}\b~i', $trimmed)) {
                    $receivedHeaders = [];
                } elseif (str_contains($trimmed, ':')) {
                    [$name, $value] = explode(':', $trimmed, 2);
                    $name = strtolower(trim($name));
                    if ($name !== '') {
                        $receivedHeaders[$name][] = trim($value);
                    }
                }
                return strlen($line);
            };

            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_HEADERFUNCTION => $headerCallback,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CUSTOMREQUEST => $method,
            ];

            if ($method !== 'GET' && $method !== 'HEAD') {
                // Preserve the old API: a string is sent exactly as provided.
                // In legacy mode, an array is passed unchanged to cURL, as before.
                if ($bodyEncoding === 'json' && (is_array($body) || is_object($body))) {
                    try {
                        $body = json_encode($body, JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        throw new RuntimeException('Cannot encode JSON request body.', 0, $e);
                    }
                } elseif ($bodyEncoding === 'form' && is_array($body)) {
                    $body = http_build_query($body, '', '&', PHP_QUERY_RFC3986);
                    // Replace the misleading application/json header with a form content type.
                    $options[CURLOPT_HTTPHEADER] = array_values(array_filter(
                        $headers,
                        static fn (string $header): bool => !str_starts_with(strtolower($header), 'content-type:')
                    ));
                    $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
                }

                if ($method === 'POST' || ($body !== null && $body !== '')) {
                    $options[CURLOPT_POSTFIELDS] = $body ?? '';
                }
            }

            // Authentication is restricted to a trusted destination and HTTPS.
            // Never forward a session cookie to an arbitrary external API, even
            // when ApiFree has automatic same-server forwarding enabled.
            if ($shareSession && $scheme === 'https' && session_status() === PHP_SESSION_ACTIVE) {
                $currentHost = self::normalizeHost((string) ($_SERVER['SERVER_NAME'] ?? ''));
                $allowed = $currentHost !== '' && $host === $currentHost;
                foreach ($sessionHosts as $trustedHost) {
                    if ($host === self::normalizeHost($trustedHost)) {
                        $allowed = true;
                        break;
                    }
                }

                if ($allowed) {
                    $sessionId = session_id();
                    $cookieName = session_name();
                    if ($sessionId !== '' && $cookieName !== '') {
                        $options[CURLOPT_COOKIE] = rawurlencode($cookieName) . '=' . rawurlencode($sessionId);
                        // Unlock the file/DB session so the internal API can read it.
                        session_write_close();
                        $sessionWasClosed = true;
                        $sessionForwarded = true;
                    }
                }
            }

            if (!curl_setopt_array($curl, $options)) {
                throw new RuntimeException('Could not configure cURL.');
            }
            $raw = curl_exec($curl);
            if ($raw === false) {
                throw new RuntimeException('HTTP request failed: ' . curl_error($curl));
            }

            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $decoded = json_decode($raw);
            // Legacy behavior: decode JSON objects/arrays; keep primitives as text.
            $response = json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))
                ? $decoded
                : $raw;

            return [
                'status' => $status,
                'response' => $response,
                'rawBody' => $raw,
                'headers' => $receivedHeaders,
                'sessionForwarded' => $sessionForwarded,
            ];
        } finally {
            curl_close($curl);
            if ($sessionWasClosed && session_status() === PHP_SESSION_NONE && !session_start()) {
                throw new RuntimeException('Unable to reopen the PHP session after the API request.');
            }
        }
    }

    private static function normalizeHost(string $host): string
    {
        return strtolower(rtrim(trim($host), '.'));
    }
}
