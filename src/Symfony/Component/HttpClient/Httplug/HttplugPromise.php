<?php

declare(strict_types=1);

namespace Symfony\Component\HttpClient\Httplug;

use Http\Promise\Promise;
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

    public static function create(ResponseInterface $response): self
    {
        return new self(new CorePromise($response));
    }

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

    public function getState()
    {
        $this->core->getState();
    }

    public function wait($unwrap = true)
    {
        $this->core->wait();

        if (!$unwrap) {
            return null;
        }

        if ($this->core->getState() === self::REJECTED) {
            throw $this->core->getException();
        }

        return $this->core->getResponse();
    }
}
