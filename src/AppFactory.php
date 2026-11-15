<?php

declare(strict_types=1);

namespace Cama;

use Cama\Config\Settings;
use Cama\Http\Csrf;
use Cama\Http\ErrorHandler;
use Cama\Http\RequestContext;
use Cama\Http\RequestLimits;
use Cama\Http\Session;
use Cama\Http\WriteAccess;
use Cama\Infrastructure\Runtime;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Views\Twig;

final class AppFactory
{
    /** @return App<\Psr\Container\ContainerInterface|null> */
    public static function create(Settings $settings, ?LoggerInterface $logger = null): App
    {
        Runtime::prepare($settings->runtime);
        if ($logger === null) {
            $handler = new RotatingFileHandler($settings->runtime . '/log/app.log', 7);
            $handler->setFormatter(new JsonFormatter());
            $logger = new Logger('cama', [$handler]);
        }
        $twig = Twig::create($settings->root . '/templates', [
            'cache' => $settings->environment === 'production' ? $settings->runtime . '/cache/' . hash('sha256', $settings->root) : false,
            'autoescape' => 'html',
            'strict_variables' => true,
        ]);
        $twig->getEnvironment()->addGlobal('app_name', $settings->name);
        $app = \Slim\Factory\AppFactory::create();
        $app->map(['GET', 'HEAD'], '/', static fn (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface => $twig->render($response, 'home.html.twig'));
        $app->map(['GET', 'HEAD'], '/health', static function (ServerRequestInterface $request, ResponseInterface $response) use ($settings): ResponseInterface {
            $file = $settings->root . '/RELEASE';
            $release = is_file($file) ? trim((string) file_get_contents($file)) : 'development';
            $response->getBody()->write(json_encode(['status' => 'ok', 'release' => $release], JSON_THROW_ON_ERROR));

            return $response->withHeader('Content-Type', 'application/json');
        });
        $app->add(new Csrf(new Session($settings->runtime . '/sessions', $settings->environment === 'production')));
        $app->add(new WriteAccess());
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->add(new RequestLimits());
        $errors = $app->addErrorMiddleware($settings->debug, false, false);
        $errors->setDefaultErrorHandler(new ErrorHandler($twig, $logger));
        $app->add(new RequestContext());

        return $app;
    }
}
