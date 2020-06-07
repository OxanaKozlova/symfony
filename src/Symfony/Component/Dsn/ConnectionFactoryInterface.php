<?php

declare(strict_types=1);


namespace Symfony\Component\Dsn;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface ConnectionFactoryInterface
{
    public function create(string $dsn): object;

    public function supports(string $dsn): bool;
}
