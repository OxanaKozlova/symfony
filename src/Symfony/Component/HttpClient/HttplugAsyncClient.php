<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient;

use Http\Client\Exception;
use Http\Client\Exception\NetworkException;
use Http\Client\Exception\RequestException;
use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Http\Client\Promise\HttpFulfilledPromise;
use Http\Client\Promise\HttpRejectedPromise;
use Http\Message\RequestFactory;
use Http\Message\StreamFactory;
use Http\Message\UriFactory;
use Http\Promise\FulfilledPromise;
use Http\Promise\Promise;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpClient\Response\ResponseTrait;
use Symfony\Component\HttpClient\Response\StreamWrapper;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

if (!interface_exists(HttpClient::class)) {
    throw new \LogicException('You cannot use "Symfony\Component\HttpClient\HttplugClient" as the "php-http/httplug" package is not installed. Try running "composer require php-http/httplug".');
}

if (!interface_exists(RequestFactory::class)) {
    throw new \LogicException('You cannot use "Symfony\Component\HttpClient\HttplugClient" as the "php-http/message-factory" package is not installed. Try running "composer require nyholm/psr7".');
}

/**
 * An adapter to turn a Symfony HttpClientInterface into an Httplug client.
 *
 * Run "composer require psr/http-client" to install the base ClientInterface. Run
 * "composer require nyholm/psr7" to install an efficient implementation of response
 * and stream factories with flex-provided autowiring aliases.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class HttplugAsyncClient implements HttpClient, RequestFactory, StreamFactory, UriFactory, HttpAsyncClient
{
    private $client;
    private $responseFactory;
    private $streamFactory;

    public function __construct(HttpClientInterface $client = null, ResponseFactoryInterface $responseFactory = null, StreamFactoryInterface $streamFactory = null)
    {
        $this->client = $client ?? \Symfony\Component\HttpClient\HttpClient::create();
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory ?? ($responseFactory instanceof StreamFactoryInterface ? $responseFactory : null);

        if (null !== $this->responseFactory && null !== $this->streamFactory) {
            return;
        }

        if (!class_exists(Psr17Factory::class)) {
            throw new \LogicException('You cannot use the "Symfony\Component\HttpClient\Psr18Client" as no PSR-17 factories have been provided. Try running "composer require nyholm/psr7".');
        }

        $psr17Factory = new Psr17Factory();
        $this->responseFactory = $this->responseFactory ?? $psr17Factory;
        $this->streamFactory = $this->streamFactory ?? $psr17Factory;
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsyncRequest(RequestInterface $request): ResponseInterface
    {
        try {
            $body = $request->getBody();

            if ($body->isSeekable()) {
                $body->seek(0);
            }

            $response = $this->client->request($request->getMethod(), (string) $request->getUri(), [
                'headers' => $request->getHeaders(),
                'body' => $body->getContents(),
                'http_version' => '1.0' === $request->getProtocolVersion() ? '1.0' : null,
            ]);

            $psrResponse = $this->responseFactory->createResponse($response->getStatusCode());

            foreach ($response->getHeaders(false) as $name => $values) {
                foreach ($values as $value) {
                    $psrResponse = $psrResponse->withAddedHeader($name, $value);
                }
            }

            $body = isset(class_uses($response)[ResponseTrait::class]) ? $response->toStream(false) : StreamWrapper::createResource($response, $this->client);
            $body = $this->streamFactory->createStreamFromResource($body);

            if ($body->isSeekable()) {
                $body->seek(0);
            }

            return $psrResponse->withBody($body);
        } catch (TransportExceptionInterface $e) {
            if ($e instanceof \InvalidArgumentException) {
                throw new Psr18RequestException($e, $request);
            }

            throw new Psr18NetworkException($e, $request);
        }
    }

    /**
     * {@inheritdoc}
     */
    // TODO fix this
    public function createRequest($method, $uri, array $headers = [], $body = null, $protocolVersion = '1.1'): RequestInterface
    {
        if ($this->responseFactory instanceof RequestFactoryInterface) {
            return $this->responseFactory->createRequest($method, $uri);
        }

        if (!class_exists(Request::class)) {
            throw new \LogicException(sprintf('You cannot use "%s()" as the "nyholm/psr7" package is not installed. Try running "composer require nyholm/psr7".', __METHOD__));
        }


        return new Request($method, $uri);
    }

    /**
     * {@inheritdoc}
     */
    public function createStream($content = null): StreamInterface
    {
        $stream = $this->streamFactory->createStream($content);

        if ($stream->isSeekable()) {
            $stream->seek(0);
        }

        return $stream;
    }

    /**
     * {@inheritdoc}
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return $this->streamFactory->createStreamFromFile($filename, $mode);
    }

    /**
     * {@inheritdoc}
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        return $this->streamFactory->createStreamFromResource($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function createUri($uri): UriInterface
    {
        if ($this->responseFactory instanceof UriFactoryInterface) {
            return $this->responseFactory->createUri($uri);
        }

        if (!class_exists(Uri::class)) {
            throw new \LogicException(sprintf('You cannot use "%s()" as the "nyholm/psr7" package is not installed. Try running "composer require nyholm/psr7".', __METHOD__));
        }

        return new Uri($uri);
    }
}

