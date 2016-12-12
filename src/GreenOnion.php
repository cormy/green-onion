<?php

namespace Cormy\Server;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;

/**
 * Cormy GreenOnion PSR-7+PSR-15 middleware stack.
 */
class GreenOnion implements DelegateInterface, RequestHandlerInterface
{
    /**
     * @var (RequestHandlerInterface|DelegateInterface|callable)
     */
    protected $core;

    /**
     * @var (MiddlewareInterface|ServerMiddlewareInterface|DelegateInterface|RequestHandlerInterface|callable)[]
     */
    protected $scales = [];

    /**
     * @var int
     */
    protected $index;

    /**
     * Constructs a GreenOnion PSR-7+PSR-15 middleware stack.
     *
     * @param RequestHandlerInterface|DelegateInterface|callable                                                   $core   the innermost request handler
     * @param (MiddlewareInterface|ServerMiddlewareInterface|DelegateInterface|RequestHandlerInterface|callable)[] $scales the middlewares to wrap around the core
     */
    public function __construct($core, ...$scales)
    {
        $this->setCore($core);
        foreach ($scales as $scale) {
            $this->push($scale);
        }
        $this->index = count($this->scales) - 1;
    }

    /**
     * Push a middleware onto the stack type safe.
     *
     * @param RequestHandlerInterface|DelegateInterface|callable $core
     */
    private function setCore($core)
    {
        if (!is_callable($core) && !$core instanceof DelegateInterface) {
            throw new \TypeError(
                'Argument 1 passed to setCore() must be callable or implement interface '.DelegateInterface::class.
                ', instance of '.get_class($core).' given'
            );
        }

        $this->core = $core;
    }

    /**
     * Push a middleware onto the stack type safe.
     *
     * @param (MiddlewareInterface|ServerMiddlewareInterface|DelegateInterface|RequestHandlerInterface|callable) $scale
     */
    private function push($scale)
    {
        if (!is_callable($scale) && !$scale instanceof ServerMiddlewareInterface && !$scale instanceof DelegateInterface) {
            throw new \TypeError(
                'Argument 1 passed to push() must be callable or implement interface '.ServerMiddlewareInterface::class.
                ' or implement interface '.DelegateInterface::class.
                ', instance of '.get_class($scale).' given'
            );
        }

        $this->scales[] = $scale;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request)
    {
        return ($this)($request);
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(ServerRequestInterface $request):ResponseInterface
    {
        return $this->processMiddleware($this->index, $request);
    }

    /**
     * Process an incoming server request and return the response.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    protected function processCore(ServerRequestInterface $request):ResponseInterface
    {
        $core = $this->core;

        if ($core instanceof DelegateInterface) {
            return $core->process($request);
        }

        return $core($request);
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
            return $this->processCore($request);
        }

        $scale = $this->scales[$index];
        $nextIndex = $index - 1;

        if ($scale instanceof DelegateInterface) {
            return $scale->process($request);
        }

        if ($scale instanceof ServerMiddlewareInterface) {
            $copy = clone $this;
            $copy->index = $nextIndex;

            return $scale->process($request, $copy);
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
