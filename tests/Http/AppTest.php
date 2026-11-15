<?php

declare(strict_types=1);

namespace Cama\Tests\Http;

use Cama\AppFactory;
use Cama\Http\RequestLimits;
use Cama\Tests\TestSettings;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

final class AppTest extends TestCase
{
    public function testHomeIsEscapedAndHasSecurityHeadersWithoutSession(): void
    {
        $app = AppFactory::create(TestSettings::create(['APP_NAME' => '<script>alert(1)</script>']));
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/')->withHeader('X-Request-ID', 'attacker');
        $response = $app->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('&lt;script&gt;', (string) $response->getBody());
        self::assertStringNotContainsString('<script>', (string) $response->getBody());
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertStringContainsString("frame-ancestors 'none'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $response->getHeaderLine('X-Request-ID'));
        self::assertFalse($response->hasHeader('Set-Cookie'));
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    /** @return iterable<string, array{string, string, int}> */
    public static function routes(): iterable
    {
        yield 'missing' => ['GET', '/missing', 404];
        yield 'unsupported' => ['POST', '/', 405];
        yield 'health write' => ['DELETE', '/health', 405];
        yield 'head' => ['HEAD', '/', 200];
        yield 'head missing' => ['HEAD', '/missing', 404];
        yield 'health' => ['GET', '/health', 200];
        yield 'env' => ['GET', '/.env', 404];
        yield 'vendor' => ['GET', '/vendor/autoload.php', 404];
    }

    #[DataProvider('routes')]
    public function testRoutes(string $method, string $uri, int $status): void
    {
        $app = AppFactory::create(TestSettings::create());
        $response = $app->handle((new ServerRequestFactory())->createServerRequest($method, $uri));
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        if ($method === 'HEAD') {
            self::assertSame('', (string) $response->getBody());
        }
        if ($status === 405) {
            self::assertStringContainsString('GET', $response->getHeaderLine('Allow'));
        }
        if ($uri === '/health' && $method === 'GET') {
            self::assertSame(['status' => 'ok', 'release' => 'development'], json_decode((string) $response->getBody(), true));
        }
    }

    public function testUnhandledErrorsAreRedactedAndCorrelated(): void
    {
        $handler = new TestHandler();
        $app = AppFactory::create(TestSettings::create(['APP_ENV' => 'production']), new Logger('test', [$handler]));
        $app->get('/explode', static function (): never {
            throw new RuntimeException('password=supersecret');
        });
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/explode?token=supersecret'));
        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('supersecret', (string) $response->getBody());
        self::assertStringContainsString($response->getHeaderLine('X-Request-ID'), (string) $response->getBody());
        self::assertSame($response->getHeaderLine('X-Request-ID'), $handler->getRecords()[0]->context['request_id']);
        self::assertStringNotContainsString('supersecret', json_encode($handler->getRecords(), JSON_THROW_ON_ERROR));
    }

    public function testDevelopmentErrorsAreEscaped(): void
    {
        $app = AppFactory::create(TestSettings::create(['APP_ENV' => 'development', 'APP_DEBUG' => 'true']));
        $app->get('/explode', static function (): never {
            throw new RuntimeException('<script>failure</script>');
        });
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/explode'));
        self::assertStringContainsString('&lt;script&gt;failure', (string) $response->getBody());
    }

    public function testFutureWriteRoutesDefaultToDeniedEvenWithHtmxHeader(): void
    {
        $app = AppFactory::create(TestSettings::create());
        $app->post('/fixture', static function (): never {
            self::fail('Write handler must not run.');
        });
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('POST', '/fixture')->withHeader('HX-Request', 'true'));
        self::assertSame(403, $response->getStatusCode());
    }

    public function testRequestSizeLimitsUseActualBodyAndHeaders(): void
    {
        $app = AppFactory::create(TestSettings::create());
        $factory = new ServerRequestFactory();
        $requests = [
            [$factory->createServerRequest('GET', '/?q=' . str_repeat('a', 8193)), 414],
            [$factory->createServerRequest('GET', '/')->withHeader('Content-Length', (string) (RequestLimits::MAX_BODY + 1)), 413],
            [$factory->createServerRequest('GET', '/')->withBody((new StreamFactory())->createStream(str_repeat('x', RequestLimits::MAX_BODY + 1))), 413],
        ];
        foreach ($requests as [$request, $expected]) {
            self::assertSame($expected, $app->handle($request)->getStatusCode());
        }
    }
}
