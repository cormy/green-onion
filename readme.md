# Cormy\Server\GreenOnion [![Build Status](https://travis-ci.org/cormy/green-onion.svg?branch=master)](https://travis-ci.org/cormy/green-onion) [![Coverage Status](https://coveralls.io/repos/cormy/green-onion/badge.svg?branch=master&service=github)](https://coveralls.io/github/cormy/green-onion?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/cormy/green-onion/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/cormy/green-onion/?branch=master)

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/16ce13e8-b127-4f71-92f9-b68eefe86791/big.png)](https://insight.sensiolabs.com/projects/16ce13e8-b127-4f71-92f9-b68eefe86791)

> Cormy GreenOnion PSR-7+PSR-15 middleware stack.

> :bomb: **EXPERIMENTAL – Work in Progress** :bomb:

> :bomb: **EXPERIMENTAL – support fore PSR-15 Middlewares** :bomb:

## Install

```
composer require cormy/green-onion
```


## Usage

```php
use Cormy\Server\GreenOnion;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;

// create the core of the onion, i.e. the innermost request handler
$core = function (ServerRequestInterface $request) : ResponseInterface {
    return new \Zend\Diactoros\Response();
};

// create some scales (aka middlewares) to wrap around the core
$scales = [];

$scales[] = new class implements ServerMiddlewareInterface {
    function process(ServerRequestInterface $request, DelegateInterface $delegate) : ResponseInterface {
        // delegate $request to the next request handler, i.e. $core
        $response = $delegate->process($request);

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
```


## API

### `Cormy\Server\GreenOnion implements Cormy\Server\RequestHandlerInterface`

#### `GreenOnion::__construct`

```php
/**
 * Constructs a GreenOnion PSR-7+PSR-15 middleware stack.
 *
 * @param RequestHandlerInterface|DelegateInterface|callable                                                   $core   the innermost request handler
 * @param (MiddlewareInterface|ServerMiddlewareInterface|DelegateInterface|RequestHandlerInterface|callable)[] $scales the middlewares to wrap around the core
 */
public function __construct($core, ...$scales)
```

#### Inherited from [`Cormy\Server\RequestHandlerInterface::__invoke`](https://github.com/cormy/server-request-handler)

```php
/**
 * Process an incoming server request and return the response.
 *
 * @param ServerRequestInterface $request
 *
 * @return ResponseInterface
 */
public function __invoke(ServerRequestInterface $request):ResponseInterface
```


## Related

* [Cormy\Server\Onion](https://github.com/cormy/onion) – Onion style PSR-7 **middleware stack** using generators
* [Cormy\Server\Bamboo](https://github.com/cormy/bamboo) – Bamboo style PSR-7 **middleware pipe** using generators
* [Cormy\Server\RequestHandlerInterface](https://github.com/cormy/server-request-handler) – Common interfaces for PSR-7 server request handlers
* [Cormy\Server\MiddlewareInterface](https://github.com/cormy/server-middleware) – Common interfaces for Cormy PSR-7 server middlewares
* [PSR-7: HTTP message interfaces](http://www.php-fig.org/psr/psr-7/)


## License

MIT © [Michael Mayer](http://schnittstabil.de)
