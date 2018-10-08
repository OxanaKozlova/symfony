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

use Symfony\Contracts\Http\Exception\TransportExceptionInterface;

interface HttpClientInterface
{
    /**
     * Requests an HTTP resource.
     *
     * @param string $uri     The URI of the resource
     * @param array  $options The options of the request, including headers and body
     *                        Possible options are described in HttpClientTrait
     *
     * @throws TransportExceptionInterface
     */
    public function request(string $method, string $uri, array $options = []): LazyResponseInterface;

    /**
     * @throws TransportExceptionInterface
     */
    public function get(string $uri, array $options = []): LazyResponseInterface;

    /**
     * @param string|resource|array|\JsonSerializable $body
     *
     * @throws TransportExceptionInterface
     */
    public function post(string $uri, $body, array $options = []): LazyResponseInterface;

    /**
     * @param string|resource|array|\JsonSerializable $body
     *
     * @throws TransportExceptionInterface
     */
    public function put(string $uri, $body, array $options = []): LazyResponseInterface;

    /**
     * @param string|resource|array|\JsonSerializable $body
     *
     * @throws TransportExceptionInterface
     */
    public function patch(string $uri, $body, array $options = []): LazyResponseInterface;

    /**
     * @throws TransportExceptionInterface
     */
    public function delete(string $uri, array $options = []): LazyResponseInterface;

    /**
     * @throws TransportExceptionInterface
     */
    public function options(string $uri, array $options = []): LazyResponseInterface;

    /**
     * @throws TransportExceptionInterface
     */
    public function head(string $uri, array $options = []): LazyResponseInterface;
}
