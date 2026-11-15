<?php

declare(strict_types=1);

// Exercise the actual cookie login against the local container; never print credentials.
$cookies = [];
$request = static function (string $path, ?array $fields = null) use (&$cookies): string {
    $headers = 'Cookie: ' . implode('; ', $cookies) . "\r\n";
    if ($fields !== null) {
        $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
    }
    $context = stream_context_create(['http' => [
        'method' => $fields === null ? 'GET' : 'POST',
        'header' => $headers,
        'content' => $fields === null ? '' : http_build_query($fields),
        'follow_location' => 0,
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);
    $html = false;
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $html = @file_get_contents('http://phpmyadmin/' . ltrim($path, '/'), false, $context);
        if ($html !== false) {
            break;
        }
        if ($attempt < 4) {
            sleep(1);
        }
    }
    if ($html === false) {
        throw new RuntimeException('phpMyAdmin is not reachable.');
    }
    foreach (http_get_last_response_headers() ?? [] as $header) {
        if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $header, $match)) {
            $cookies[$match[1]] = $match[1] . '=' . $match[2];
        }
    }

    return $html;
};

$html = $request('index.php?route=/');
if (str_contains($html, 'logged_in:true')) {
    throw new RuntimeException('phpMyAdmin must require a login.');
}
$document = new DOMDocument();
@$document->loadHTML($html);
$xpath = new DOMXPath($document);
$fields = [];
foreach ($xpath->query('//form[@id="login_form"]//input[@type="hidden"]') as $input) {
    $fields[$input->getAttribute('name')] = $input->getAttribute('value');
}
if (!isset($fields['token'], $fields['server'])) {
    throw new RuntimeException('Login form is missing.');
}
$fields['pma_username'] = getenv('DB_USER');
$fields['pma_password'] = getenv('DB_PASSWORD');
$request('index.php?route=/', $fields);
$html = $request('index.php?route=/&server=1');
if (!str_contains($html, 'logged_in:true') || !str_contains($html, 'user:' . json_encode(getenv('DB_USER'), JSON_THROW_ON_ERROR))) {
    throw new RuntimeException('phpMyAdmin database login failed.');
}
echo "phpMyAdmin cookie login with the configured database account: OK\n";
