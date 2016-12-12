<?php

namespace Cormy\Server;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Interop\Http\Middleware\RequestHandlerInterface as PsrRequestHandlerInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;

/**
 * Cormy GreenOnion PSR-7+PSR-15 middleware stack.
 */
class GreenOnion implements PsrRequestHandlerInterface, RequestHandlerInterface
{
    /**
     * @var (RequestHandlerInterface|PsrRequestHandlerInterface|callable)
     */
    protected $core;

    /**
     * @var (MiddlewareInterface|ServerMiddlewareInterface|PsrRequestHandlerInterface|RequestHandlerInterface|callable)[]
     */
    protected $scales = [];

    /**
     * @var int
     */
    protected $index;

    /**
     * Constructs a GreenOnion PSR-7+PSR-15 middleware stack.
     *
     * @param RequestHandlerInterface|PsrRequestHandlerInterface|callable                                                   $core   the innermost request handler
     * @param (MiddlewareInterface|ServerMiddlewareInterface|PsrRequestHandlerInterface|RequestHandlerInterface|callable)[] $scales the middlewares to wrap around the core
     */
    public function __construct(callable $core, callable ...$scales)
    {
        $this->core = $core;
        $this->scales = $scales;
        $this->index = count($this->scales) - 1;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(ServerRequestInterface $request):ResponseInterface
    {
        return $this->processMiddleware($this->index, $request);
    }

    /**
     * Process an incoming server request by delegating it to the middleware specified by $index.
     *
     * @param int                    $index   the $scales index
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    protected function processMiddleware(int $index, ServerRequestInterface $request):ResponseInterface
    {
        if ($index < 0) {
            return ($this->core)($request);
        }

        $scale = $this->scales[$index];
        $nextIndex = $index - 1;

        if ($scale instanceof ServerMiddlewareInterface) {
            $copy = clone $this;
            $copy->index = $nextIndex;

            return $scale($request, $copy);
        }

        $current = $scale($request);

        if ($current instanceof ResponseInterface) {
            return $current;
        }

        while ($current->valid()) {
            $nextRequest = $current->current();

            try {
                $nextResponse = $this->processMiddleware($nextIndex, $nextRequest);
                $current->send($nextResponse);
            } catch (\Throwable $exception) {
                $current->throw($exception);
            }
        }

        return $current->getReturn();
    }
}
