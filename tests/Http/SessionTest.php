<?php

declare(strict_types=1);

namespace Cama\Tests\Http;

use Cama\Http\Csrf;
use Cama\Http\Session;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SessionTest extends TestCase
{
    private function session(): Session
    {
        return new Session(sys_get_temp_dir(), true);
    }

    private function handler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };
    }

    public function testSessionTokenAndCookieConfiguration(): void
    {
        $session = $this->session();
        $token = $session->csrfToken();
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertSame($token, $session->csrfToken());
        $options = session_get_cookie_params();
        self::assertTrue($options['secure']);
        self::assertTrue($options['httponly']);
        self::assertSame('Lax', $options['samesite']);
        self::assertSame('1', ini_get('session.use_strict_mode'));
        $id = session_id();
        $session->close();
        $session->close();
        self::assertSame(PHP_SESSION_NONE, session_status());
        unlink(sys_get_temp_dir() . '/sess_' . $id);
    }

    public function testValidTokenAllowsRequestAndReleasesSessionLock(): void
    {
        $session = $this->session();
        $token = $session->csrfToken();
        $id = session_id();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/fixture')->withParsedBody(['_csrf' => $token]);
        self::assertSame(204, (new Csrf($session))->process($request, $this->handler())->getStatusCode());
        self::assertSame(PHP_SESSION_NONE, session_status());
        unlink(sys_get_temp_dir() . '/sess_' . $id);
    }

    public function testWrongTokenFailsAndClosesSession(): void
    {
        $session = $this->session();
        $session->csrfToken();
        $id = session_id();
        try {
            $this->expectException(HttpForbiddenException::class);
            (new Csrf($session))->process((new ServerRequestFactory())->createServerRequest('POST', '/')->withParsedBody(['_csrf' => str_repeat('0', 64)]), $this->handler());
        } finally {
            self::assertSame(PHP_SESSION_NONE, session_status());
            unlink(sys_get_temp_dir() . '/sess_' . $id);
        }
    }

    public function testArrayTokenFailsWithoutOpeningSession(): void
    {
        $this->expectException(HttpForbiddenException::class);
        (new Csrf($this->session()))->process((new ServerRequestFactory())->createServerRequest('POST', '/')->withParsedBody(['_csrf' => ['bad']]), $this->handler());
    }

    public function testMissingBodyFails(): void
    {
        $this->expectException(HttpForbiddenException::class);
        (new Csrf($this->session()))->process((new ServerRequestFactory())->createServerRequest('POST', '/'), $this->handler());
    }

    public function testReadDoesNotStartSession(): void
    {
        self::assertSame(204, (new Csrf($this->session()))->process((new ServerRequestFactory())->createServerRequest('GET', '/'), $this->handler())->getStatusCode());
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testUnavailableSessionStorageFailsExplicitly(): void
    {
        $this->expectException(\RuntimeException::class);
        (new Session('/does-not-exist/cama-sessions', true))->start();
    }

    public function testIdleAndAbsoluteExpiryInvalidateIdentity(): void
    {
        $session = $this->session();
        $session->login(42, 'credential', 1000);
        self::assertSame(['id' => 42, 'credential' => 'credential'], $session->identity(1001));
        self::assertNull($session->identity(1001 + Session::IDLE_SECONDS));
        self::assertSame('', session_id());
        $session->login(42, 'credential', 1000);
        $_SESSION['seen'] = 1000 + Session::MAX_SECONDS - 1;
        self::assertNull($session->identity(1000 + Session::MAX_SECONDS));
        self::assertSame('', session_id());
    }

    public function testMalformedSessionIsInvalidatedAndLoginRotatesCsrf(): void
    {
        $session = $this->session();
        $oldToken = $session->csrfToken();
        $oldId = session_id();
        $session->login(42, 'credential', 1000);
        self::assertNotSame($oldToken, $session->csrfToken());
        self::assertNotSame($oldId, session_id());
        self::assertFileDoesNotExist(sys_get_temp_dir() . '/sess_' . $oldId);
        $_SESSION['user_id'] = ['not-an-id'];
        self::assertNull($session->identity(1001));
        self::assertSame(PHP_SESSION_NONE, session_status());
    }
}
