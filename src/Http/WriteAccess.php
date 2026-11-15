<?php

declare(strict_types=1);

namespace Cama\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;

/** Deny all writes until a real authenticated identity and permission policy exist. */
final class WriteAccess implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            throw new HttpForbiddenException($request);
        }

        return $handler->handle($request);
    }
}
