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

use Symfony\Contracts\Http\Exception\ClientExceptionInterface;
use Symfony\Contracts\Http\Exception\HttpExceptionTrait;
use Symfony\Contracts\Http\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\Http\Exception\ServerExceptionInterface;

/**
 * Helps implementing ResponseInterface and defining the reference behavior of response objects.
 */
trait ResponseTrait
{
    private $statusCode;
    private $headers;
    private $body;
    private $attributes;
    private $initializer;

    private function configure(array $rawHeaders, $body, array $attributes = [], callable $initializer = null)
    {
        if (!\is_array($metadata = @stream_get_meta_data($body))) {
            throw new \TypeError(sprintf('%s() expects parameter 2 to be stream resource, %s given.', __METHOD__, \is_resource($body) ? get_resource_type($body) : \gettype($body)));
        }

        if (!$metadata['seekable'] || !@rewind($body)) {
            throw \InvalidArgumentException(sprintf('Stream resource passed to %s() as parameter 2 should be seekable.', __METHOD__));
        }

        $numRedirects = -1;

        foreach ($rawHeaders as $h) {
            if (11 <= \strlen($h) && '/' === $h[4] && preg_match('#^HTTP/\d+(?:\.\d+)? ([12345]\d\d) .*#', $h, $matches)) {
                $this->headers = [];
                $this->statusCode = (int) $matches[1];
                ++$numRedirects;
            } elseif (false !== strpos($h, ':')) {
                $h = explode(':', $h, 2);
                $this->headers[strtolower($h[0])][] = ltrim($h[1]);
            }
        }

        if (!$this->statusCode) {
            throw new \InvalidArgumentException('Invalid or missing HTTP status line.');
        }

        $this->body = $body;
        $this->attributes = [
            'raw_headers' => $rawHeaders,
            'num_redirects' => $numRedirects,
        ] + $attributes;
        $this->initializer = $initializer;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        if ($this->initializer) {
            $initializer = $this->initializer;
            $this->initializer = null;
            $initializer($this);
        }

        return $this->statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders(): array
    {
        if ($this->initializer) {
            $initializer = $this->initializer;
            $this->initializer = null;
            $initializer($this);
        }

        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent($output = null): string
    {
        if (null !== $output && $this->body !== $output && !\is_array(@stream_get_meta_data($output))) {
            throw new \TypeError(sprintf('%s() expects parameter 1 to be stream resource, %s given.', __METHOD__, \is_resource($output) ? get_resource_type($output) : \gettype($output)));
        }

        if ($this->initializer) {
            $initializer = $this->initializer;
            $this->initializer = null;
            $initializer($this, $output);
        }

        if ($this->body instanceof ResponseInterface) {
            return $this->body->getContent($output);
        }

        rewind($this->body);

        if (null === $output) {
            return stream_get_contents($this->body);
        }

        if ($this->body !== $output) {
            stream_copy_to_stream($this->body, $output);
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        if ($this->initializer) {
            $initializer = $this->initializer;
            $this->initializer = null;
            $initializer($this);
        }

        return $this->attributes;
    }

    private function checkStatusCode()
    {
        if (500 <= $code = $this->getStatusCode()) {
            throw new class($this) extends \RuntimeException implements ServerExceptionInterface {
                use HttpExceptionTrait;
            };
        }
        if (400 <= $code) {
            throw new class($this) extends \RuntimeException implements ClientExceptionInterface {
                use HttpExceptionTrait;
            };
        }
        if (300 <= $code) {
            throw new class($this) extends \RuntimeException implements RedirectionExceptionInterface {
                use HttpExceptionTrait;
            };
        }
    }
}
