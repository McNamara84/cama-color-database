<?php

declare(strict_types=1);

namespace Cama\Tests\Http;

use Cama\AppFactory;
use Cama\Auth\AdminBootstrap;
use Cama\Auth\Passwords;
use Cama\Auth\User;
use Cama\Auth\Users;
use Cama\Http\Session;
use Cama\Tests\AccountDatabase;
use Cama\Tests\TestSettings;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AccountsTest extends TestCase
{
    private Users $users;
    private Session $session;
    private User $admin;

    protected function setUp(): void
    {
        $this->users = new Users(AccountDatabase::reset());
        $this->session = new Session(TestSettings::create()->runtime . '/sessions', false);
        $this->admin = (new AdminBootstrap($this->users, new Passwords()))->run('admin@example.org', AccountDatabase::PASSWORD);
    }

    protected function tearDown(): void
    {
        if (session_id() !== '') {
            $this->session->logout();
        }
    }

    /** @param array<string, mixed>|null $body */
    private function request(string $method, string $path, ?array $body = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path, ['REMOTE_ADDR' => '192.0.2.1']);
        if ($body !== null) {
            $request = $request->withParsedBody($body);
        }
        return AppFactory::create(TestSettings::create())->handle($request);
    }

    private function token(ResponseInterface $response): string
    {
        self::assertSame(1, preg_match('/name="_csrf" value="([a-f0-9]{64})"/', (string) $response->getBody(), $matches));
        return $matches[1];
    }

    private function signIn(User $user, bool $changeRequired = false): void
    {
        if (!$changeRequired) {
            $this->users->changePassword($user, $user->passwordHash);
        }
        $this->session->login($user->id, $user->credential());
        $this->session->close();
    }

    public function testPublicHomeAndHealthRemainAvailableButAccountsAndRegistrationAreNotPublic(): void
    {
        $home = $this->request('GET', '/');
        self::assertSame(200, $home->getStatusCode());
        self::assertStringContainsString('Anmelden', (string) $home->getBody());
        self::assertStringNotContainsString('admin@example.org', (string) $home->getBody());
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame(200, $this->request('GET', '/health')->getStatusCode());
        foreach (['/admin/users', '/account/password'] as $path) {
            $response = $this->request('GET', $path);
            self::assertSame(303, $response->getStatusCode());
            self::assertSame('/login', $response->getHeaderLine('Location'));
        }
        self::assertSame(303, $this->request('POST', '/admin/users', ['email' => 'intruder@example.org'])->getStatusCode());
        self::assertSame(404, $this->request('GET', '/register')->getStatusCode());
        self::assertSame(404, $this->request('POST', '/register', [])->getStatusCode());
        self::assertSame(405, $this->request('GET', '/logout')->getStatusCode());
        self::assertCount(1, $this->users->recent());
    }

    public function testLoginRotatesSessionAndRequiresInitialPasswordChange(): void
    {
        $token = $this->token($this->request('GET', '/login'));
        $anonymous = session_id();
        // Test-only credential for the isolated cama_test database.
        $response = $this->request('POST', '/login', ['email' => ' ADMIN@example.org ', 'password' => AccountDatabase::PASSWORD, '_csrf' => $token]); // ggignore
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/account/password', $response->getHeaderLine('Location'));
        self::assertNotSame($anonymous, session_id());
        self::assertFileDoesNotExist(TestSettings::create()->runtime . '/sessions/sess_' . $anonymous);
        self::assertSame('/account/password', $this->request('GET', '/admin/users')->getHeaderLine('Location'));
        self::assertSame('/account/password', $this->request('GET', '/login')->getHeaderLine('Location'));
        self::assertSame(200, $this->request('GET', '/health')->getStatusCode());
        $newToken = $this->token($this->request('GET', '/account/password'));
        self::assertNotSame($token, $newToken);
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testWrongAndUnknownCredentialsHaveTheSameResponseAndNeverEchoPasswords(): void
    {
        $token = $this->token($this->request('GET', '/login'));
        foreach (['admin@example.org', 'missing@example.org'] as $email) {
            $response = $this->request('POST', '/login', ['email' => $email, 'password' => 'Wrong password 123456', '_csrf' => $token]);
            self::assertSame(401, $response->getStatusCode());
            self::assertStringContainsString('E-Mail-Adresse oder Passwort ist nicht korrekt.', (string) $response->getBody());
            self::assertStringNotContainsString('Wrong password', (string) $response->getBody());
        }
        self::assertNull($this->session->identity());
        $this->session->close();
    }

    public function testLoginRequiresCsrfAndStrictInputAndThrottlesRepeatedFailures(): void
    {
        $body = ['email' => 'admin@example.org', 'password' => 'Wrong password 123456'];
        self::assertSame(403, $this->request('POST', '/login', $body)->getStatusCode());
        $token = $this->token($this->request('GET', '/login'));
        self::assertSame(422, $this->request('POST', '/login', $body + ['_csrf' => $token, 'role' => 'admin'])->getStatusCode());
        self::assertSame(403, $this->request('POST', '/login', $body + ['_csrf' => ['array']])->getStatusCode());
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(401, $this->request('POST', '/login', $body + ['_csrf' => $token])->getStatusCode());
        }
        // Test-only credential for the isolated cama_test database.
        $response = $this->request('POST', '/login', ['email' => 'admin@example.org', 'password' => AccountDatabase::PASSWORD, '_csrf' => $token]); // ggignore
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('900', $response->getHeaderLine('Retry-After'));
    }

    public function testAdminCanCreateMemberButCannotInjectRolesOrDuplicateCanonicalEmails(): void
    {
        $this->signIn($this->admin);
        $token = $this->token($this->request('GET', '/admin/users'));
        // Test-only credential for the isolated cama_test database.
        $body = ['email' => ' New@Example.org ', 'name' => '<script>alert(1)</script>', 'password' => AccountDatabase::PASSWORD, 'password_confirmation' => AccountDatabase::PASSWORD, '_csrf' => $token]; // ggignore
        self::assertSame(422, $this->request('POST', '/admin/users', $body + ['admin_slot' => 1])->getStatusCode());
        self::assertSame(403, $this->request('POST', '/admin/users', array_replace($body, ['_csrf' => 'invalid']))->getStatusCode());
        $response = $this->request('POST', '/admin/users', $body);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/users?created=1', $response->getHeaderLine('Location'));
        $user = $this->users->byEmail('new@example.org');
        self::assertNotNull($user);
        self::assertFalse($user->isAdmin);
        self::assertTrue($user->mustChangePassword);
        self::assertTrue((new Passwords())->verify(AccountDatabase::PASSWORD, $user->passwordHash));
        $page = (string) $this->request('GET', '/admin/users')->getBody();
        self::assertStringContainsString('&lt;script&gt;', $page);
        self::assertStringNotContainsString('<script>', $page);
        self::assertStringNotContainsString(AccountDatabase::PASSWORD, $page);
        self::assertStringNotContainsString($user->passwordHash, $page);
        self::assertSame(422, $this->request('POST', '/admin/users', array_replace($body, ['email' => 'NEW@example.org']))->getStatusCode());
        self::assertCount(2, $this->users->recent());
    }

    public function testMembersCannotSeeOrCreateAccountsEvenWithValidCsrf(): void
    {
        $member = $this->users->create('member@example.org', 'Member', (new Passwords())->hash(AccountDatabase::PASSWORD));
        $this->signIn($member);
        $home = $this->request('GET', '/');
        $token = $this->token($home);
        self::assertStringNotContainsString('href="/admin/users"', (string) $home->getBody());
        self::assertSame(403, $this->request('GET', '/admin/users')->getStatusCode());
        self::assertSame(403, $this->request('POST', '/admin/users', ['_csrf' => $token])->getStatusCode());
        self::assertCount(2, $this->users->recent());
    }

    public function testPasswordChangeRequiresCurrentPasswordAndInvalidatesOldCredentials(): void
    {
        $this->signIn($this->admin, true);
        $token = $this->token($this->request('GET', '/account/password'));
        $oldId = session_id();
        $new = 'A different long passphrase 🎨';
        $body = ['current_password' => AccountDatabase::PASSWORD, 'password' => $new, 'password_confirmation' => $new, '_csrf' => $token];
        self::assertSame(422, $this->request('POST', '/account/password', array_replace($body, ['password_confirmation' => 'mismatch']))->getStatusCode());
        self::assertSame(422, $this->request('POST', '/account/password', array_replace($body, ['current_password' => 'wrong']))->getStatusCode());
        self::assertSame(422, $this->request('POST', '/account/password', array_replace($body, ['password' => AccountDatabase::PASSWORD, 'password_confirmation' => AccountDatabase::PASSWORD]))->getStatusCode());
        self::assertSame(403, $this->request('POST', '/account/password', array_replace($body, ['_csrf' => 'wrong']))->getStatusCode());
        $response = $this->request('POST', '/account/password', $body);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/', $response->getHeaderLine('Location'));
        self::assertNotSame($oldId, session_id());
        $user = $this->users->byId($this->admin->id);
        self::assertFalse($user->mustChangePassword);
        self::assertFalse((new Passwords())->verify(AccountDatabase::PASSWORD, $user->passwordHash));
        self::assertTrue((new Passwords())->verify($new, $user->passwordHash));
        self::assertSame(200, $this->request('GET', '/admin/users')->getStatusCode());
        self::assertSame('/', $this->request('GET', '/login')->getHeaderLine('Location'));
    }

    public function testPasswordVerificationIsRateLimited(): void
    {
        $this->signIn($this->admin);
        $token = $this->token($this->request('GET', '/account/password'));
        $body = ['current_password' => 'wrong', 'password' => 'Another password 123!', 'password_confirmation' => 'Another password 123!', '_csrf' => $token];
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(422, $this->request('POST', '/account/password', $body)->getStatusCode());
        }
        self::assertSame(429, $this->request('POST', '/account/password', $body)->getStatusCode());
    }

    public function testLogoutRequiresPostAndCsrfAndDestroysSession(): void
    {
        $this->signIn($this->admin);
        $token = $this->token($this->request('GET', '/'));
        $id = session_id();
        self::assertSame(405, $this->request('GET', '/logout')->getStatusCode());
        self::assertSame(403, $this->request('POST', '/logout', ['_csrf' => 'wrong'])->getStatusCode());
        self::assertSame(303, $this->request('POST', '/logout', ['_csrf' => $token])->getStatusCode());
        self::assertSame('', session_id());
        self::assertFileDoesNotExist(TestSettings::create()->runtime . '/sessions/sess_' . $id);
        self::assertSame('/login', $this->request('GET', '/admin/users')->getHeaderLine('Location'));
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testOldSessionsAreRejectedAfterPasswordChangeOrAccountDeletion(): void
    {
        $this->signIn($this->admin);
        $this->users->changePassword($this->users->byId($this->admin->id), (new Passwords())->hash('Externally changed password!'));
        self::assertSame('/login', $this->request('GET', '/admin/users')->getHeaderLine('Location'));
        $updated = $this->users->byId($this->admin->id);
        $this->signIn($updated);
        AccountDatabase::reset();
        self::assertSame('/login', $this->request('GET', '/admin/users')->getHeaderLine('Location'));
    }

    public function testOldHashIsUpgradedAfterSuccessfulLogin(): void
    {
        $old = password_hash(AccountDatabase::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);
        $this->users->changePassword($this->admin, $old);
        $token = $this->token($this->request('GET', '/login'));
        self::assertSame(303, $this->request('POST', '/login', ['email' => $this->admin->email, 'password' => AccountDatabase::PASSWORD, '_csrf' => $token])->getStatusCode());
        self::assertStringStartsWith('$argon2id$', $this->users->byId($this->admin->id)->passwordHash);
        self::assertSame(200, $this->request('GET', '/admin/users')->getStatusCode());
    }
}
