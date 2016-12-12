#!/usr/bin/env php
<?php

namespace Cormy;

require __DIR__.'/../vendor/autoload.php';

use Cormy\Server\GreenOnion;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Interop\Http\Middleware\RequestHandlerInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;

// create the core of the onion, i.e. the innermost request handler
$core = function (ServerRequestInterface $request) : ResponseInterface {
    return new \Zend\Diactoros\Response();
};

// create some scales (aka middlewares) to wrap around the core
$scales = [];

$scales[] = new class implements ServerMiddlewareInterface {
    function __invoke(ServerRequestInterface $request, RequestHandlerInterface $next) : ResponseInterface
    {
        // delegate $request to the next request handler, i.e. $core
        $response = $next($request);

        return $response->withHeader('content-type', 'application/json; charset=utf-8');
    }
};

$scales[] = function (ServerRequestInterface $request) : \Generator {
    // delegate $request to the next request handler, i.e. the middleware right above
    $response = (yield $request);

    return $response->withHeader('X-PoweredBy', 'Unicorns');
};

// create an onion style middleware stack
$middlewareStack = new GreenOnion($core, ...$scales);

// and process an incoming server request
$response = $middlewareStack(new \Zend\Diactoros\ServerRequest());

exit($response->getHeader('X-PoweredBy')[0] === 'Unicorns' ? 0 : 1);
