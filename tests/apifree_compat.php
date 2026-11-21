<?php

declare(strict_types=1);

namespace Dasintranet\Framework;

use RuntimeException;

// Self-contained tests using namespaced cURL fakes; no network required.
// Works whether the global PHP cURL extension is installed or not.

foreach (['CURLOPT_RETURNTRANSFER', 'CURLOPT_HTTPHEADER', 'CURLOPT_HEADERFUNCTION',
    'CURLOPT_CONNECTTIMEOUT', 'CURLOPT_TIMEOUT', 'CURLOPT_SSL_VERIFYPEER',
    'CURLOPT_SSL_VERIFYHOST', 'CURLOPT_FOLLOWLOCATION', 'CURLOPT_CUSTOMREQUEST',
    'CURLOPT_POSTFIELDS', 'CURLOPT_COOKIE', 'CURLINFO_HTTP_CODE'] as $i => $name) {
    if (!defined($name)) {
        define($name, 100 + $i);
    }
}

class FakeCurlHandle
{
    public array $options = [];
}

$GLOBALS['fakeResults'] = [];
$GLOBALS['fakeRequests'] = [];
function curl_init(string $url): FakeCurlHandle { return new FakeCurlHandle(); }
function curl_setopt_array(FakeCurlHandle $handle, array $opts): bool { $handle->options = $opts; return true; }
function curl_exec(FakeCurlHandle $handle): string|false
{
    $GLOBALS['fakeRequests'][] = [
        'options' => $handle->options,
        'sessionStatusDuringRequest' => session_status(),
    ];
    $GLOBALS['lastFakeCurl'] = $handle;
    $result = array_shift($GLOBALS['fakeResults']);
    if ($result === null) { throw new RuntimeException('No queued cURL fake result.'); }
    $callback = $handle->options[CURLOPT_HEADERFUNCTION];
    foreach (["HTTP/1.1 {$result['status']} Test\r\n", "Content-Type: application/json\r\n", "X-Test: ok\r\n", "Set-Cookie: secret=do-not-forward\r\n", "\r\n"] as $line) {
        $callback($handle, $line);
    }
    return $result['body'];
}
function curl_getinfo(FakeCurlHandle $handle, int $key): int {return $GLOBALS['fakeLastStatus'] ?? 200;}
function curl_close(FakeCurlHandle $handle): void {}
function curl_error(FakeCurlHandle $handle): string {return 'fake cURL failure';}

require dirname(__DIR__) . '/src/HttpClient.php';
require dirname(__DIR__) . '/src/ApiFree.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException('FAILED: ' . $message); }
    $GLOBALS['testLogs'][] = 'PASS: ' . $message;
}
function nextResult(string $body, int $status = 200): void
{
    $GLOBALS['fakeResults'][] = ['body' => $body, 'status' => $status];
    $GLOBALS['fakeLastStatus'] = $status;
}
function lastRequest(): array {return end($GLOBALS['fakeRequests']);}

session_save_path(sys_get_temp_dir());
session_id('apifreecompat' . bin2hex(random_bytes(8)));
session_start();
$_SESSION['account_id'] = 75;
$_SERVER['SERVER_NAME'] = 'internal.test';

nextResult('{"ok":true,"amount":7}', 201);
$client = new ApiFree();
$response = $client->exec('https://internal.test/api/guardar', 'POST', '{"a":1}');
$request = lastRequest();
expect($client->shareSession === true, 'Automatic local-session forwarding enabled by default');
expect($client->sessionForwarded === true, 'Active session forwarded only to trusted internal host');
expect(str_contains($request['options'][CURLOPT_COOKIE], session_name() . '='), 'Correct web session cookie forwarded');
expect($request['sessionStatusDuringRequest'] === PHP_SESSION_NONE, 'Session unlocked during internal HTTP request');
expect(session_status() === PHP_SESSION_ACTIVE && $_SESSION['account_id'] === 75, 'Web session reopened and its data preserved');
expect($request['options'][CURLOPT_POSTFIELDS] === '{"a":1}', 'Raw JSON request body unchanged');
expect(is_object($response) && $response->amount === 7, 'Object JSON response decoded as stdClass');
expect($client->rawBody === '{"ok":true,"amount":7}', 'Response body available unmodified');
expect($client->status === 201 && http_response_code() === 201, 'HTTP status forwarded like legacy ApiFree');
expect(isset($client->responseHeaders['content-type']), 'Response headers captured');

nextResult('plain text');
$client->exec('https://remote.example/api', 'POST', 'hello');
$request = lastRequest();
expect(!isset($request['options'][CURLOPT_COOKIE]), 'External API never receives web session');
expect($client->response === 'plain text', 'Non-JSON body preserved as text');
expect($client->sessionForwarded === false, 'External request not marked as session-forwarded');

nextResult('42');
$client->exec('https://internal.test/api', 'GET');
$request = lastRequest();
expect(!isset($request['options'][CURLOPT_POSTFIELDS]), 'GET does not send a request body');
expect($client->response === '42', 'Primitive JSON response stays as original string');

nextResult('[1,2,3]');
$client->exec('https://internal.test/api', 'POST', ['legacy' => 'field']);
$request = lastRequest();
expect($request['options'][CURLOPT_POSTFIELDS] === ['legacy' => 'field'], 'Legacy array body forwarded unchanged');
expect($client->response === [1,2,3], 'JSON array decodes to a PHP array');

nextResult('{}');
$client->bodyEncoding = 'json';
$client->exec('https://internal.test/api', 'PUT', ['nombre' => 'Ana']);
$request = lastRequest();
expect($request['options'][CURLOPT_POSTFIELDS] === '{"nombre":"Ana"}', 'JSON mode encodes associative arrays');

nextResult('{}');
$client->bodyEncoding = 'form';
$client->exec('https://internal.test/api', 'PATCH', ['id' => 12, 'status' => 'ok']);
$request = lastRequest();
expect($request['options'][CURLOPT_POSTFIELDS] === 'id=12&status=ok', 'Form mode encodes as urlencoded');
expect(in_array('Content-Type: application/x-www-form-urlencoded', $request['options'][CURLOPT_HTTPHEADER], true), 'Form mode sets correct content type');

nextResult('{}');
$client->sessionHosts = ['i12.preceptoria.com.ar'];
$client->exec('https://i12.preceptoria.com.ar/api', 'GET');
$request = lastRequest();
expect(isset($request['options'][CURLOPT_COOKIE]), 'Trusted tenant host can share web session');

nextResult('{}');
$client->exec('http://internal.test/api', 'GET');
$request = lastRequest();
expect(!isset($request['options'][CURLOPT_COOKIE]), 'Never forward sessions over insecure HTTP');

nextResult('{}');
$client->shareSession = false;
$client->exec('https://internal.test/api', 'GET');
$request = lastRequest();
expect(!isset($request['options'][CURLOPT_COOKIE]), 'Session forwarding can be disabled');

nextResult('{}');
$client->shareSession = true;
$client->forwardResponse = false;
http_response_code(204);
$client->exec('https://internal.test/api', 'GET');
expect(http_response_code() === 204, 'Optional response forwarding can be disabled');

// A failed transport must also release/reopen the same web session.
nextResult('');
$GLOBALS['fakeResults'][0]['body'] = false;
try {
    $client->exec('https://internal.test/api/failure', 'POST', '{"x":1}');
    throw new RuntimeException('FAILED: expected cURL exception');
} catch (RuntimeException $exception) {
    expect(str_contains($exception->getMessage(), 'HTTP request failed'), 'Transport error propagated as exception');
}
expect(session_status() === PHP_SESSION_ACTIVE && $_SESSION['account_id'] === 75,
    'Web session reopened even when the API request fails');

session_write_close();
echo implode("\n", $GLOBALS['testLogs']) . "\nAll ApiFree compatibility tests passed.\n";
