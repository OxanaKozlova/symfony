<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Http\Exception;

use Symfony\Contracts\Http\ResponseInterface;
use Symfony\Contracts\Http\ResponseTrait;

/**
 * Helps implementing exceptions that extend ResponseInterface.
 */
trait HttpExceptionTrait
{
    use ResponseTrait;

    public function __construct(ResponseInterface $response)
    {
        $this->statusCode = $response->getStatusCode();
        $this->headers = $response->getHeaders();
        $this->body = $response;
        $this->attributes = $response->getAttributes();

        $message = [];
        foreach (array_reverse($this->attributes['raw_headers']) as $h) {
            if (0 === strpos($h, 'HTTP/')) {
                $message[] = trim(explode(' ', $h, 2)[1]);
                break;
            }
        }

        if ($this instanceof ClientExceptionInterface) {
            $message[] = ClientExceptionInterface::class;
        } elseif ($this instanceof RedirectionExceptionInterface) {
            $message[] = RedirectionExceptionInterface::class;
        } elseif ($this instanceof ServerExceptionInterface) {
            $message[] = ServerExceptionInterface::class;
        }

        parent::__construct(implode(' - ', $message), $this->statusCode);
    }
}
