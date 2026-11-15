<?php

declare(strict_types=1);

namespace Cama\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;
use Slim\Routing\RouteContext;

/** New write routes stay denied until explicitly assigned an authorization policy. */
final class WriteAccess implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $name = RouteContext::fromRequest($request)->getRoute()?->getName();
            if ($request->getMethod() !== 'POST' || !in_array($name, ['login.submit', 'logout', 'password.submit', 'admin.users.create'], true)) {
                throw new HttpForbiddenException($request);
            }
        }

        return $handler->handle($request);
    }
}
