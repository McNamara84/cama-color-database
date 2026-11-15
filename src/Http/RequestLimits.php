<?php

declare(strict_types=1);

namespace Cama\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;
use Slim\Psr7\Factory\StreamFactory;

final class RequestLimits implements MiddlewareInterface
{
    public const int MAX_BODY = 65536;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strlen($request->getUri()->getQuery()) > 8192) {
            throw new HttpException($request, 'Request URI too long.', 414);
        }
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $data = $body->read(self::MAX_BODY + 1);
        if (strlen($data) > self::MAX_BODY || (int) $request->getHeaderLine('Content-Length') > self::MAX_BODY) {
            throw new HttpException($request, 'Request body too large.', 413);
        }

        return $handler->handle($request->withBody((new StreamFactory())->createStream($data)));
    }
}
