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
 * A simple HTTP response.
 */
interface ResponseInterface
{
    public function getStatusCode(): int;

    /**
     * @return string[][] keyed by header name in lowercase
     */
    public function getHeaders(): array;

    /**
     * Gets the reponse body as a string.
     *
     * This method SHOULD NOT be called after the "body" attribute
     * has been read. Doing so would lead to an undefined behavior.
     *
     * @param resource|null $output If set, the content is written to this PHP stream
     *                              and the method SHOULD return the empty string
     */
    public function getContent($output = null): string;

    /**
     * Returns attributes comming from the transport layer.
     *
     * The following attribute MUST be returned:
     *  - raw_headers - an array modelled after the special $http_response_header variable
     *
     * Other attributes SHOULD be named after curl_getinfo()'s associative return value
     */
    public function getAttributes(): array;
}
