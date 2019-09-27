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
            $this->state = Promise::FULFILLED;
        } catch (TransportExceptionInterface $e) {
            $this->state = Promise::REJECTED;
            if ($e instanceof \InvalidArgumentException) {
                $this->exception =  new RequestException($e->getMessage(), $this->request, $e);
            }

            $this->exception =  new NetworkException($e->getMessage(), $this->request, $e);

            return;
        }

        $psrResponse = $this->responseFactory->createResponse($this->response->getStatusCode());

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $psrResponse = $psrResponse->withAddedHeader($name, $value);
            }
        }

        $body = isset(class_uses($this->response)[ResponseTrait::class]) ? $this->response->toStream(false) : StreamWrapper::createResource($this->response, $this->client);
        $body = $this->streamFactory->createStreamFromResource($body);

        if ($body->isSeekable()) {
            $body->seek(0);
        }

        return $this->httplugResponse = $psrResponse->withBody($body);
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
