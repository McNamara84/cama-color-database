<?php

declare(strict_types=1);

use Cama\Auth\Passwords;
use Cama\Auth\Users;
use Cama\Tests\AccountDatabase;

require __DIR__ . '/bootstrap.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{status: int, headers: list<string>, body: string} */
function request(string $method, string $path, array $data, string &$cookie): array
{
    $headers = ['Content-Type: application/x-www-form-urlencoded'];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $method === 'POST' ? http_build_query($data) : '', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
    $body = @file_get_contents('http://127.0.0.1:18099' . $path, false, $context);
    $responseHeaders = http_get_last_response_headers() ?? [];
    check(is_string($body) && isset($responseHeaders[0]), 'HTTP request failed.');
    preg_match('/^HTTP\/\S+ ([0-9]{3})/', $responseHeaders[0], $status);
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie: (cama_session=[^;]*)/i', $header, $match)) {
            $cookie = $match[1];
        }
    }
    return ['status' => (int) $status[1], 'headers' => $responseHeaders, 'body' => $body];
}

function token(array $response): string
{
    check(preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $response['body'], $matches) === 1, 'CSRF token missing.');
    return $matches[1];
}

function install(array $environment): void
{
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/bin/install.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
    check(is_resource($process), 'Installer did not start.');
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    check(proc_close($process) === 0, 'Installer failed.');
}

$database = AccountDatabase::reset();
$users = new Users($database);
$environment = array_merge(getenv(), ['ADMIN_EMAIL' => 'smoke-admin@example.org', 'ADMIN_PASSWORD' => AccountDatabase::PASSWORD]);
install($environment);
$admin = $users->admin();
check($admin !== null, 'Installer did not create the administrator.');
install($environment);
check($users->admin()->passwordHash === $admin->passwordHash, 'Repeated installation changed credentials.');
$log = tempnam(sys_get_temp_dir(), 'cama-auth-http-');
$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:18099', '-t', 'public', 'public/index.php'], [['pipe', 'r'], ['file', $log, 'a'], ['file', $log, 'a']], $pipes, dirname(__DIR__));
check(is_resource($server), 'HTTP server did not start.');
fclose($pipes[0]);
$cookie = '';
try {
    for ($i = 0; $i < 50; $i++) {
        $socket = @stream_socket_client('tcp://127.0.0.1:18099', $errno, $error, 0.1);
        if (is_resource($socket)) {
            fclose($socket);
            break;
        }
        usleep(100000);
    }
    $login = request('GET', '/login', [], $cookie);
    check($login['status'] === 200, 'Login page failed.');
    $headers = implode("\n", $login['headers']);
    check(str_contains(strtolower($headers), 'httponly') && str_contains(strtolower($headers), 'samesite=lax'), 'Cookie protection missing.');
    $before = $cookie;
    // Test-only credential for the isolated cama_test database.
    $response = request('POST', '/login', ['email' => 'smoke-admin@example.org', 'password' => AccountDatabase::PASSWORD, '_csrf' => token($login)], $cookie); // ggignore
    check($response['status'] === 303 && $cookie !== $before, 'Login did not rotate the browser session.');
    $password = request('GET', '/account/password', [], $cookie);
    $new = 'Browser-test new passphrase 🎨';
    check(request('POST', '/account/password', ['current_password' => AccountDatabase::PASSWORD, 'password' => $new, 'password_confirmation' => $new, '_csrf' => token($password)], $cookie)['status'] === 303, 'Password change failed.');
    install($environment);
    check((new Passwords())->verify($new, $users->admin()->passwordHash), 'Installer reset the changed password.');
    $accounts = request('GET', '/admin/users', [], $cookie);
    check($accounts['status'] === 200, 'Administrator cannot open account management.');
    $csrf = token($accounts);
    check(request('POST', '/admin/users', ['email' => 'smoke-member@example.org', 'name' => 'HTTP Member', 'password' => AccountDatabase::PASSWORD, 'password_confirmation' => AccountDatabase::PASSWORD, '_csrf' => $csrf], $cookie)['status'] === 303, 'Account creation failed.');
    $oldCookie = $cookie;
    $logout = request('POST', '/logout', ['_csrf' => $csrf], $cookie);
    check($logout['status'] === 303 && str_contains(implode("\n", $logout['headers']), 'Max-Age=0'), 'Logout did not delete the cookie.');
    check(request('GET', '/admin/users', [], $oldCookie)['status'] === 303, 'Logged-out cookie still grants access.');
    $cookie = '';
    $form = request('GET', '/login', [], $cookie);
    // Test-only credential for the isolated cama_test database.
    check(request('POST', '/login', ['email' => 'smoke-member@example.org', 'password' => AccountDatabase::PASSWORD, '_csrf' => token($form)], $cookie)['status'] === 303, 'Created member cannot sign in.'); // ggignore
    $form = request('GET', '/account/password', [], $cookie);
    check(request('POST', '/account/password', ['current_password' => AccountDatabase::PASSWORD, 'password' => $new, 'password_confirmation' => $new, '_csrf' => token($form)], $cookie)['status'] === 303, 'Member password change failed.');
    check(request('GET', '/admin/users', [], $cookie)['status'] === 403, 'Member can view private accounts.');
    $home = request('GET', '/', [], $cookie);
    check(request('POST', '/admin/users', ['_csrf' => token($home)], $cookie)['status'] === 403, 'Member can create accounts.');
    echo "HTTP account checks passed: installer, cookies, login, password changes, admin-only creation, member login and logout.\n";
} finally {
    proc_terminate($server);
    proc_close($server);
    unlink($log);
    AccountDatabase::reset();
}
