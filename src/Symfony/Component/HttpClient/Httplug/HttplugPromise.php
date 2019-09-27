<?php

declare(strict_types=1);

namespace Symfony\Component\HttpClient\Httplug;

use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class HttplugPromise implements Promise
{
    private $core;

    public function __construct(CorePromise $promise)
    {
        $this->core = $promise;
    }

    public static function create(
        RequestInterface $psr7Request,
        ResponseInterface $symfonyResponse,
        HttpClientInterface $client,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    ): self {
        return new self(new CorePromise($psr7Request, $symfonyResponse, $client, $responseFactory, $streamFactory));
    }

    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if ($onFulfilled) {
            $this->core->addOnFulfilled($onFulfilled);
        }
        if ($onRejected) {
            $this->core->addOnRejected($onRejected);
        }

        return new self($this->core);
    }

    /**
     * {@inheritdoc}
     */
    public function getState()
    {
        $this->core->getState();
    }

    /**
     * {@inheritdoc}
     */
    public function wait($unwrap = true)
    {
        $this->core->wait();

        if (!$unwrap) {
            return null;
        }

        if (self::REJECTED === $this->core->getState()) {
            throw $this->core->getException();
        }

        return $this->core->getResponse();
    }
}
