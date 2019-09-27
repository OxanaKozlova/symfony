<?php

declare(strict_types=1);

namespace Symfony\Component\HttpClient\Httplug;

use Http\Client\Exception;
use Http\Client\Exception\NetworkException;
use Http\Client\Exception\RequestException;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\HttpClient\Psr18NetworkException;
use Symfony\Component\HttpClient\Psr18RequestException;
use Symfony\Component\HttpClient\Response\ResponseTrait;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CorePromise
{
    /**
     * The PSR7 request.
     *
     * @var RequestInterface
     */
    private $request;

    /**
     * The Symfony response.
     *
     * @var ResponseInterface
     */
    private $response;

    /**
     * @var HttpClientInterface
     */
    private $client;

    /**
     * @var StreamFactoryInterface
     */
    private $responseFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

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

    public function __construct(
        RequestInterface $psr7Request,
        ResponseInterface $symfonyResponse,
        HttpClientInterface $client,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->request = $psr7Request;
        $this->response = $symfonyResponse;
        $this->client = $client;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->state = Promise::PENDING;
    }

    public function wait()
    {
        try {
            $headers = $this->response->getHeaders(false);
        } catch (TransportExceptionInterface $e) {
            $this->reject($e);

            return;
        }
        $this->fulfill($headers);
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


    /**
     * Fulfill promise.
     */
    private function fulfill(array $headers)
    {
        $this->state = Promise::FULFILLED;
        try {
            $psr7Response = $this->responseFactory->createResponse($this->response->getStatusCode());

            foreach ($headers as $name => $values) {
                foreach ($values as $value) {
                    $psr7Response = $psr7Response->withAddedHeader($name, $value);
                }
            }

            $body = isset(class_uses($this->response)[ResponseTrait::class]) ? $this->response->toStream(false) : StreamWrapper::createResource($this->response, $this->client);
            $body = $this->streamFactory->createStreamFromResource($body);

            if ($body->isSeekable()) {
                $body->seek(0);
            }

            $psr7Response = $psr7Response->withBody($body);
        } catch (\RuntimeException $e) {
            $exception = new Exception\TransferException($e->getMessage(), $e->getCode(), $e);
            $this->reject($exception);

            return;
        }

        while (count($this->onFulfilled) > 0) {
            $callback = array_shift($this->onFulfilled);
            $psr7Response = call_user_func($callback, $psr7Response);
        }

        if ($psr7Response instanceof \Psr\Http\Message\ResponseInterface) {
            $this->httplugResponse = $psr7Response;
        }
    }

    /**
     * Reject promise.
     *
     * @param Exception $exception Reject reason
     */
    private function reject(\Exception $exception)
    {
        $this->state = Promise::REJECTED;
        if ($exception instanceof \InvalidArgumentException) {
            $this->exception =  new RequestException($exception->getMessage(), $this->request, $exception);
        } else {
            $this->exception = new NetworkException($exception->getMessage(), $this->request, $exception);
        }

        while (count($this->onRejected) > 0) {
            $callback = array_shift($this->onRejected);
            try {
                $exception = call_user_func($callback, $this->exception);
                $this->exception = $exception;
            } catch (\Exception $exception) {
                $this->exception = $exception;
            }
        }
    }
}
