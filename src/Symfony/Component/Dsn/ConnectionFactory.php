<?php

declare(strict_types=1);


namespace Symfony\Component\Dsn;


class ConnectionFactory implements ConnectionFactoryInterface
{
    /**
     * @var iterable<ConnectionFactoryInterface>
     */
    private $factories;

    /**
     * @param iterable<ConnectionFactoryInterface> $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    public function create(string $dsn): object
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->create($dsn);
            }
        }

        //throw new exception
    }

    public function supports(string $dsn): bool
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return true;
            }
        }

        return false;
    }


}
