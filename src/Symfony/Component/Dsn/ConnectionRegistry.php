<?php

declare(strict_types=1);


namespace Symfony\Component\Dsn;


class ConnectionRegistry
{
    /**
     * @var array [dsn => Connection]
     */
    private $connections = [];

    /**
     * @var ConnectionFactory
     */
    private $factory;

    /**
     */
    public function __construct(ConnectionFactory $factory)
    {
        $this->factory = $factory;
    }


    public function addConnection(string $dsn, object $connection)
    {
        $this->connections[$dsn] = $connection;
    }

    public function has(string $dsn): bool
    {
        return isset($this->connections[$dsn]);
    }

    public function getConnection(string $dsn): object
    {
        if ($this->has($dsn)) {
            return $this->connections[$dsn];
        }

        return $this->connections[$dsn] = $this->factory->create($dsn);
    }
}
