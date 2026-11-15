<?php

declare(strict_types=1);

namespace Cama\Http;

use Cama\Auth\Users;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

final readonly class Authentication implements MiddlewareInterface
{
    public function __construct(private Session $session, private Users $users)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = RouteContext::fromRequest($request)->getRoute()?->getName();
        if ($route === 'health') {
            return $handler->handle($request);
        }
        try {
            $identity = $this->session->identity();
            $user = $identity === null ? null : $this->users->byId($identity['id']);
            if ($identity !== null && ($user === null || !hash_equals($user->credential(), $identity['credential']))) {
                $this->session->logout();
                $user = null;
            }
            $public = in_array($route, ['home', 'login', 'login.submit'], true);
            if (!$public && $user === null) {
                return (new Response(303))->withHeader('Location', '/login');
            }
            if ($user !== null) {
                if ($user->mustChangePassword && !in_array($route, ['password', 'password.submit', 'logout'], true)) {
                    return (new Response(303))->withHeader('Location', '/account/password');
                }
                if (in_array($route, ['login', 'login.submit'], true)) {
                    return (new Response(303))->withHeader('Location', '/');
                }
                if (str_starts_with($route ?? '', 'admin.') && !$user->isAdmin) {
                    throw new HttpForbiddenException($request);
                }
            }
            return $handler->handle($request->withAttribute('user', $user));
        } finally {
            $this->session->close();
        }
    }
}
