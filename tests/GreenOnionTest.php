<?php

namespace Cormy\Server;

use Exception;
use Cormy\Server\Helpers\CounterMiddleware;
use Cormy\Server\Helpers\FinalHandler;
use Cormy\Server\Helpers\Response;
use Cormy\Server\Helpers\MultiDelegationMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\ServerRequest;
use Interop\Http\Middleware\RequestHandlerInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;

class GreenOnionTest extends \PHPUnit_Framework_TestCase
{
    use \VladaHejda\AssertException;

    public function testEmptyStacksShouldBeValid()
    {
        $finalHandler = new FinalHandler('Final!');

        $sut = new GreenOnion($finalHandler);
        $response = $sut(new ServerRequest());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('Final!', (string) $response->getBody());
    }

    public function testSingleMiddelwareShouldBeValid()
    {
        $finalHandler = new FinalHandler('Final!');
        $middleware = new CounterMiddleware(0);

        $sut = new GreenOnion($finalHandler, $middleware);
        $response = $sut(new ServerRequest());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('Final!0', (string) $response->getBody());
    }

    public function testDoubleMultipleMiddlewareShouldBeValid()
    {
        $finalHandler = new FinalHandler('Final!');
        $middleware0 = new CounterMiddleware(0);
        $middleware9 = new CounterMiddleware(9);

        $sut = new GreenOnion($finalHandler, $middleware0, $middleware9);
        $response = $sut(new ServerRequest());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('Final!09', (string) $response->getBody());
    }

    public function testMiddlewareReuseShouldBeValid()
    {
        $finalHandler = new FinalHandler('Final!');
        $middleware = new CounterMiddleware(0);

        $sut = new GreenOnion($finalHandler, $middleware, $middleware);
        $response = $sut(new ServerRequest());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('Final!01', (string) $response->getBody());
    }

    public function testMultipleMiddlewaresShouldBeValid()
    {
        $finalHandler = new FinalHandler('Final!');
        $middlewares = [
            new CounterMiddleware(0),
            new CounterMiddleware(1),
            new CounterMiddleware(2),
            new CounterMiddleware(3),
        ];

        $sut = new GreenOnion($finalHandler, ...$middlewares);
        $response = $sut(new ServerRequest());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('Final!0123', (string) $response->getBody());
    }

    public function testPsrMiddlewaresAndDelegatesShouldBeValid()
    {
        $finalHandler = new FinalHandler('Final!');
        $middlewares = [
            new class() implements RequestHandlerInterface {
                function __invoke(ServerRequestInterface $request) : ResponseInterface
                {
                    return new Response('Delegate!');
                }
            },
            new CounterMiddleware(0),
            new CounterMiddleware(1),
            new class() implements ServerMiddlewareInterface {
                function __invoke(ServerRequestInterface $request, RequestHandlerInterface $next) : ResponseInterface
                {
                    return $next($request)->withHeader('X-PoweredBy', 'Unicorns');
                }
            },
            new CounterMiddleware(2),
            new CounterMiddleware(3),
        ];

        $sut = new GreenOnion($finalHandler, ...$middlewares);
        $response = $sut(new ServerRequest());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('Delegate!0123', (string) $response->getBody());
        $this->assertSame('Unicorns', $response->getHeader('X-PoweredBy')[0]);
    }

    public function testPsrDelegatesShouldBeValidCore()
    {
        $finalHandler = new class() implements RequestHandlerInterface {
            function __invoke(ServerRequestInterface $request) : ResponseInterface
            {
                return new Response('Delegate!');
            }
        };
        $middlewares = [
            new CounterMiddleware(0),
        ];

        $sut = new GreenOnion($finalHandler, ...$middlewares);
        $response = $sut(new ServerRequest());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('Delegate!0', (string) $response->getBody());
    }

    public function testRequestHandlersShouldBeValid()
    {
        $finalHandler = new FinalHandler('Final!');
        $middlewares = [
            new CounterMiddleware(0),
            function (ServerRequestInterface $request) {
                return new Response('Abort by '.$request->getHeader('X-PoweredBy')[0].'!');
            },
            new CounterMiddleware(1),
            function (ServerRequestInterface $request) {
                $response = (yield $request->withHeader('X-PoweredBy', 'Unicorns'));

                return $response;
            },
        ];

        $sut = new GreenOnion($finalHandler, ...$middlewares);
        $response = $sut(new ServerRequest());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('Abort by Unicorns!1', (string) $response->getBody());
    }

    public function testMultiDelegationMiddlewaresShouldBeValid()
    {
        $finalHandler = new FinalHandler('Final!');
        $middlewares = [
            new CounterMiddleware(1),
            new MultiDelegationMiddleware(42),
        ];

        $sut = new GreenOnion($finalHandler, ...$middlewares);
        $response = $sut(new ServerRequest());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('Final!42', (string) $response->getBody());
    }

    public function testCallbackMiddelwareShouldBeValid()
    {
        $finalHandler = new FinalHandler('Final!');
        $middleware = function (ServerRequestInterface $request) {
            static $index = 0;

            $response = (yield $request);
            $response->getBody()->write((string) $index++);

            return $response;
        };

        $sut = new GreenOnion($finalHandler, $middleware);
        $response = $sut(new ServerRequest());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('Final!0', (string) $response->getBody());
    }

    public function testMiddlewaresCanHandleCoreExceptions()
    {
        $finalHandler = function (ServerRequestInterface $request):ResponseInterface {
            throw new Exception('Oops, something went wrong!', 500);
        };
        $middlewares = [
            function (ServerRequestInterface $request) {
                try {
                    $response = (yield $request);
                } catch (Exception $e) {
                    return new Response('Catched: '.$e->getMessage(), $e->getCode());
                }
            },
        ];

        $sut = new GreenOnion($finalHandler, ...$middlewares);
        $response = $sut(new ServerRequest());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('Catched: Oops, something went wrong!', (string) $response->getBody());
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testMiddlewaresCanHandleMiddlewareExceptions()
    {
        $finalHandler = new FinalHandler('Final!');
        $middlewares = [
            function (ServerRequestInterface $request) {
                $response = (yield $request);
                throw new Exception('Oops, something went wrong!', 500);
            },
            function (ServerRequestInterface $request) {
                try {
                    $response = (yield $request);
                } catch (Exception $e) {
                    return new Response('Catched: '.$e->getMessage(), $e->getCode());
                }
            },
        ];

        $sut = new GreenOnion($finalHandler, ...$middlewares);
        $response = $sut(new ServerRequest());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('Catched: Oops, something went wrong!', (string) $response->getBody());
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testMiddlewareCallerHaveToHandleCoreExceptions()
    {
        $finalHandler = function (ServerRequestInterface $request):ResponseInterface {
            throw new Exception('Oops, something went wrong!', 500);
        };
        $middlewares = [
            function (ServerRequestInterface $request) {
                $response = (yield $request);

                return $response;
            },
        ];

        $sut = new GreenOnion($finalHandler, ...$middlewares);

        $this->assertException(function () use ($sut) {
            $sut(new ServerRequest());
        }, Exception::class, 500, 'Oops, something went wrong!');
    }

    public function testMiddlewareCallerHaveToHandleMiddlewareExceptions()
    {
        $finalHandler = new FinalHandler('Final!');
        $middlewares = [
            function (ServerRequestInterface $request) {
                $response = (yield $request);
                throw new Exception('Oops, something went wrong!', 500);
            },
            function (ServerRequestInterface $request) {
                return yield $request;
            },
        ];

        $sut = new GreenOnion($finalHandler, ...$middlewares);

        $this->assertException(function () use ($sut) {
            $sut(new ServerRequest());
        }, Exception::class, 500, 'Oops, something went wrong!');
    }
}
