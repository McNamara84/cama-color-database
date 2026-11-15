<?php

declare(strict_types=1);

namespace Cama\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Throwable;

final readonly class ErrorHandler
{
    public function __construct(private Twig $twig, private LoggerInterface $logger)
    {
    }

    public function __invoke(ServerRequestInterface $request, Throwable $error, bool $displayErrorDetails): ResponseInterface
    {
        $status = $error instanceof HttpException ? $error->getCode() : 500;
        $status = ($status >= 400 && $status <= 599) ? $status : 500;
        $id = (string) $request->getAttribute('request_id', 'unavailable');
        if ($status >= 500) {
            // Exception messages, query strings, bodies and credentials are deliberately excluded.
            $this->logger->error('Request failed', ['request_id' => $id, 'exception' => $error::class, 'file' => basename($error->getFile()), 'line' => $error->getLine()]);
        }
        $response = new Response($status);
        if ($error instanceof HttpMethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $error->getAllowedMethods()));
        }

        return $this->twig->render($response, 'error.html.twig', [
            'status' => $status,
            'request_id' => $id,
            'detail' => $displayErrorDetails ? $error->getMessage() : null,
        ]);
    }
}
