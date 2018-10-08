<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Http;

/**
 * A (potentially lazily retrieved) HTTP response.
 */
interface LazyResponseInterface extends ResponseInterface
{
    /**
     * {@inheritdoc}
     *
     * @throws TransportExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getStatusCode(): int;

    /**
     * {@inheritdoc}
     *
     * @throws TransportExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getHeaders(): array;

    /**
     * {@inheritdoc}
     *
     * @throws TransportExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getContent($output = null): string;

    /**
     * {@inheritdoc}
     *
     * @throws TransportExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function getAttributes(): array;

    /**
     * Retrieves the provided responses, yielding them as they complete.
     *
     * @return LazyResponseInterface[]
     */
    public static function complete(LazyResponseInterface ...$responses): iterable;
}
