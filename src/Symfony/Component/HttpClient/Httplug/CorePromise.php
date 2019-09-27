<?php

declare(strict_types=1);

namespace Symfony\Component\HttpClient\Httplug;

use Http\Client\Exception;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CorePromise
{
    /**
     * The Symfony response.
     *
     * @var ResponseInterface
     */
    private $response;

    /**
     * The HTTPlug response.
     *
     * @var ResponseInterface
     */
    private $httplugResponse;

    /**
     * Promise state.
     *
     * @var string
     */
    private $state;

    /**
     * Exception.
     *
     * @var Exception|null
     */
    private $exception = null;

    /**
     * Functions to call when a response will be available.
     *
     * @var callable[]
     */
    private $onFulfilled = [];

    /**
     * Functions to call when an error happens.
     *
     * @var callable[]
     */
    private $onRejected = [];

    public function __construct(ResponseInterface $symfonyResponse)
    {
        $this->response = $symfonyResponse;
        $this->state = Promise::PENDING;
    }


    public function wait()
    {
        try {
            $headers = $this->response->getHeaders(false);
            $content = $this->response->getContent(false);
            $this->state = Promise::FULFILLED;
        } catch (TransportExceptionInterface $e) {
            $this->state = Promise::REJECTED;
            $this->handleFailedRequest();
            return;
        }

        // TODO convert symfony response to httplgu response
        $this->httplugResponse = // Something
    }

    private function handleFailedRequest()
    {
        // TODO create a proper HTTPlug exception

        $this->exception = // Something
    }

    /**
     * Add on fulfilled callback.
     *
     * @param callable $callback
     */
    public function addOnFulfilled(callable $callback)
    {
        if ($this->getState() === Promise::PENDING) {
            $this->onFulfilled[] = $callback;
        } elseif ($this->getState() === Promise::FULFILLED) {
            $response = call_user_func($callback, $this->getResponse());
            if ($response instanceof \Psr\Http\Message\ResponseInterface) {
                $this->response = $response;
            }
        }
    }

    /**
     * Add on rejected callback.
     *
     * @param callable $callback
     */
    public function addOnRejected(callable $callback)
    {
        if ($this->getState() === Promise::PENDING) {
            $this->onRejected[] = $callback;
        } elseif ($this->getState() === Promise::REJECTED) {
            $this->exception = call_user_func($callback, $this->exception);
        }
    }

    /**
     * Get the state of the promise, one of PENDING, FULFILLED or REJECTED.
     *
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }


    public function getResponse()
    {
        if (null === $this->httplugResponse) {
            throw new \LogicException('Promise is not fulfilled');
        }

        return $this->httplugResponse;
    }


    public function getException()
    {
        if (null === $this->exception) {
            throw new \LogicException('Promise is not rejected');
        }

        return $this->exception;
    }
}
