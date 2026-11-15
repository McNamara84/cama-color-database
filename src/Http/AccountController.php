<?php

declare(strict_types=1);

namespace Cama\Http;

use Cama\Auth\AccountInput;
use Cama\Auth\DuplicateAccount;
use Cama\Auth\LoginLimiter;
use Cama\Auth\Passwords;
use Cama\Auth\User;
use Cama\Auth\Users;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final readonly class AccountController
{
    private const DUMMY_HASH = '$argon2id$v=19$m=19456,t=2,p=1$MDd4aW1qclNIVFRvS3JBLw$cFI+7hgVVfnZZ0RlTKU4n+0FHO5j5lNMZqEVYxGHdM4';

    public function __construct(private Twig $twig, private Session $session, private Users $users, private Passwords $passwords, private LoginLimiter $limiter)
    {
    }

    public function home(Request $request, Response $response): Response
    {
        return $this->render($request, $response, 'home.html.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        $errors = [];
        if ($request->getMethod() === 'POST') {
            $errors = (new AccountInput())->errors($request->getParsedBody(), 'login');
            if ($errors === []) {
                $email = AccountInput::email($this->field($request, 'email'));
                if (!$this->limiter->consume($email, $this->ip($request), time())) {
                    return $this->throttled($request, $response, 'login.html.twig');
                }
                $user = $this->users->byEmail($email);
                $password = $this->field($request, 'password');
                $valid = $this->passwords->verify($password, $user === null ? self::DUMMY_HASH : $user->passwordHash);
                if ($valid && $user !== null) {
                    if ($this->passwords->needsRehash($user->passwordHash)) {
                        $hash = $this->passwords->hash($password);
                        if (!$this->users->changePassword($user, $hash, $user->mustChangePassword)) {
                            return $this->render($request, $response->withStatus(401), 'login.html.twig', ['message' => 'E-Mail-Adresse oder Passwort ist nicht korrekt.']);
                        }
                        $user = new User($user->id, $user->email, $user->name, $hash, $user->isAdmin, $user->mustChangePassword);
                    }
                    $this->limiter->clearEmail($email);
                    $this->session->login($user->id, $user->credential());
                    return $response->withStatus(303)->withHeader('Location', $user->mustChangePassword ? '/account/password' : '/');
                }
                return $this->render($request, $response->withStatus(401), 'login.html.twig', ['message' => 'E-Mail-Adresse oder Passwort ist nicht korrekt.']);
            }
            $response = $response->withStatus(422);
        }
        return $this->render($request, $response, 'login.html.twig', ['errors' => $errors]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->session->logout();
        return $response->withStatus(303)->withHeader('Location', '/login');
    }

    public function password(Request $request, Response $response): Response
    {
        $user = $this->user($request);
        $errors = [];
        if ($request->getMethod() === 'POST') {
            $errors = (new AccountInput())->errors($request->getParsedBody(), 'password');
            if ($errors === []) {
                $key = 'password:' . $user->id;
                if (!$this->limiter->consume($key, $this->ip($request), time())) {
                    return $this->throttled($request, $response, 'password.html.twig');
                }
                $current = $this->field($request, 'current_password');
                $password = $this->field($request, 'password');
                if (!$this->passwords->verify($current, $user->passwordHash)) {
                    return $this->render($request, $response->withStatus(422), 'password.html.twig', ['message' => 'Das aktuelle Passwort ist nicht korrekt.']);
                }
                if ($password === $current) {
                    return $this->render($request, $response->withStatus(422), 'password.html.twig', ['message' => 'Bitte wähle ein neues Passwort.']);
                }
                $hash = $this->passwords->hash($password);
                if (!$this->users->changePassword($user, $hash)) {
                    $this->session->logout();
                    return $response->withStatus(303)->withHeader('Location', '/login');
                }
                $this->limiter->clearEmail($key);
                $this->session->login($user->id, hash('sha256', $hash));
                return $response->withStatus(303)->withHeader('Location', '/');
            }
            $response = $response->withStatus(422);
        }
        return $this->render($request, $response, 'password.html.twig', ['errors' => $errors]);
    }

    public function users(Request $request, Response $response): Response
    {
        $errors = [];
        $message = null;
        if ($request->getMethod() === 'POST') {
            $errors = (new AccountInput())->errors($request->getParsedBody(), 'create');
            if ($errors === []) {
                try {
                    $this->users->create(AccountInput::email($this->field($request, 'email')), trim($this->field($request, 'name')), $this->passwords->hash($this->field($request, 'password')));
                    return $response->withStatus(303)->withHeader('Location', '/admin/users?created=1');
                } catch (DuplicateAccount) {
                    $message = 'Für diese E-Mail-Adresse besteht bereits ein Account.';
                }
            }
            $response = $response->withStatus(422);
        }
        return $this->render($request, $response, 'users.html.twig', ['errors' => $errors, 'message' => $message, 'accounts' => $this->users->recent(), 'created' => ($request->getQueryParams()['created'] ?? null) === '1']);
    }

    /** @param array<string, mixed> $data */
    private function render(Request $request, Response $response, string $template, array $data = []): Response
    {
        $user = $request->getAttribute('user');
        return $this->twig->render($response, $template, $data + [
            'user' => $user, 'csrf' => ($template !== 'home.html.twig' || $user !== null) ? $this->session->csrfToken() : '',
            'errors' => [], 'message' => null, 'email' => $this->field($request, 'email'), 'name' => $this->field($request, 'name'),
        ]);
    }

    private function throttled(Request $request, Response $response, string $template): Response
    {
        return $this->render($request, $response->withStatus(429)->withHeader('Retry-After', (string) LoginLimiter::WINDOW), $template, ['message' => 'Zu viele Versuche. Bitte versuche es in 15 Minuten erneut.']);
    }

    private function field(Request $request, string $name): string
    {
        $body = $request->getParsedBody();
        return is_array($body) && is_string($body[$name] ?? null) ? $body[$name] : '';
    }

    private function user(Request $request): User
    {
        $user = $request->getAttribute('user');
        return $user instanceof User ? $user : throw new \LogicException('Authentication required.');
    }

    private function ip(Request $request): string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '';
        // Proxy headers are untrusted; use only the web server's peer address.
        return is_string($ip) ? $ip : '';
    }
}
