<?php

declare(strict_types=1);

namespace Dasintranet\Framework;

use RuntimeException;

/** Internal cURL adapter shared by Api and ApiFree. */
final class HttpClient
{
    /**
     * @param list<string> $headers
     * @return array{status: int, response: mixed}
     */
    public static function request(
        string $url,
        string $method,
        mixed $body,
        array $headers,
        bool $shareSession = false
    ): array {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('Only absolute HTTP(S) URLs are supported.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is required.');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        $sessionWasClosed = false;
        try {
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
            ];

            if (strtoupper($method) !== 'GET' && $body !== null && $body !== '') {
                $options[CURLOPT_POSTFIELDS] = $body;
            }

            // Session credentials are never sent to arbitrary remote hosts.
            // Explicit opt-in is required, even for calls to this server.
            if ($shareSession && $scheme !== 'https') {
                throw new RuntimeException('HTTPS is required for session forwarding.');
            }

            if ($shareSession && session_status() === PHP_SESSION_ACTIVE) {
                $serverHost = (string) ($_SERVER['SERVER_NAME'] ?? '');
                if ($serverHost === '' || strcasecmp($host, $serverHost) !== 0) {
                    throw new RuntimeException('Session forwarding is permitted only to the same server.');
                }

                $sessionId = session_id();
                $cookieName = session_name();
                if ($sessionId !== '') {
                    $options[CURLOPT_COOKIE] = rawurlencode($cookieName) . '=' . rawurlencode($sessionId);
                    // Release the session lock while calling a local API endpoint.
                    session_write_close();
                    $sessionWasClosed = true;
                }
            }

            curl_setopt_array($curl, $options);
            $raw = curl_exec($curl);
            if ($raw === false) {
                throw new RuntimeException('HTTP request failed: ' . curl_error($curl));
            }

            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $decoded = json_decode($raw);
            $response = json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))
                ? $decoded
                : $raw;

            return ['status' => $status, 'response' => $response];
        } finally {
            curl_close($curl);
            if ($sessionWasClosed && session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        }
    }
}
