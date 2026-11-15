<?php

declare(strict_types=1);

namespace Cama\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;

final readonly class Csrf implements MiddlewareInterface
{
    public function __construct(private Session $session)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $body = $request->getParsedBody();
            $token = is_array($body) ? ($body['_csrf'] ?? null) : null;
            try {
                if (!is_string($token) || !hash_equals($this->session->csrfToken(), $token)) {
                    throw new HttpForbiddenException($request);
                }
            } finally {
                $this->session->close();
            }
        }

        return $handler->handle($request);
    }
}
