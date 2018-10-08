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

/**
 * Helps implementing HttpClientInterface and defining the reference behavior of HTTP clients.
 */
trait HttpClientTrait
{
    private $defaultOptions = [
        'headers' => [],                // array - header names as keys
        'body' => '',                   // string|resource|array|\JsonSerializable
        'progress' => null,             // callable(int $downloadLength, int $downloaded, int $uploadLength, int $uploaded) - to monitor uploads/downloads
        'output' => null,               // resource - the PHP stream where the response body SHOULD be written
        'http' => [
            'protocol_version' => '',
            'proxy' => null,            // string - SHOULD honor standard proxy-related environment variables by default
            'follow_location' => 20,    // int - the maximum number of redirections to follow
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'cafile' => null,
            'capath' => null,
            'local_cert' => null,
            'local_pk' => null,
            'passphrase' => null,
            'ciphers' => null,
        ],
        'socket' => [
            'bindto' => '0',            // string - the interface or the local socket to bind to
            'tcp_nodelay' => true,
        ],
    ];

    public function __construct(array $defaultOptions = [])
    {
        if ($defaultOptions) {
            $this->defaultOptions = array_replace_recursive($this->defaultOptions, $defaultOptions);
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function get(string $uri, array $options = []): LazyResponseInterface
    {
        return $this->request('GET', $uri, $options);
    }

    /**
     * {@inheritdoc}
     */
    final public function post(string $uri, $body, array $options = []): LazyResponseInterface
    {
        return $this->request('POST', $uri, ['body' => $body] + $options);
    }

    /**
     * {@inheritdoc}
     */
    final public function put(string $uri, $body, array $options = []): LazyResponseInterface
    {
        return $this->request('PUT', $uri, ['body' => $body] + $options);
    }

    /**
     * {@inheritdoc}
     */
    final public function patch(string $uri, $body, array $options = []): LazyResponseInterface
    {
        return $this->request('PATCH', $uri, ['body' => $body] + $options);
    }

    /**
     * {@inheritdoc}
     */
    final public function delete(string $uri, array $options = []): LazyResponseInterface
    {
        return $this->request('DELETE', $uri, $options);
    }

    /**
     * {@inheritdoc}
     */
    final public function options(string $uri, array $options = []): LazyResponseInterface
    {
        return $this->request('OPTIONS', $uri, $options);
    }

    /**
     * {@inheritdoc}
     */
    final public function head(string $uri, array $options = []): LazyResponseInterface
    {
        return $this->request('HEAD', $uri, $options);
    }

    private function prepareOptions(array $options): array
    {
        $options = array_replace_recursive($this->defaultOptions, $options);

        $headers = [];
        foreach ($options['headers'] as $name => $values) {
            foreach (\is_iterable($values) ? $values : (array) $values as $value) {
                $headers[] = $name.': '.$value;
            }
        }

        $options['http']['protocol_version'] = (string) $options['http']['protocol_version'];
        $options['http']['header'] = $headers;
        $options['http']['content'] = $this->prepareBody($options['body'] ?? '', $headers);
        unset($options['headers'], $options['body']);

        return $options;
    }

    /**
     * @param string|resource|array|\JsonSerializable $body
     */
    private function prepareBody($body, array &$headers): string
    {
        $contentType = null;
        $hasContentLength = false;

        foreach ($headers as $h) {
            if ('-' !== ($h[7] ?? '')) {
                continue;
            }
            if (0 === stripos($h, 'Content-Type:')) {
                $contentType = trim(substr($h, 13)) ?: null;
            } elseif (0 === stripos($h, 'Content-Length:')) {
                $hasContentLength = true;
            }
        }

        if (\is_string($body)) {
            if (!$hasContentLength) {
                $headers[] = 'Content-Length: '.\strlen($body);
            }
            if (null === $contentType && '' !== $body) {
                $headers[] = 'Content-Type:';
            }

            return $body;
        }

        if (\is_resource($body)) {
            if (null === $contentType && $size = @filesize(stream_get_meta_data($body)['uri'])) {
                if (!$hasContentLength) {
                    $headers[] = 'Content-Length: '.$size;
                }
                $headers[] = 'Content-Type:';
            }

            return $body;
        }

        if (!\is_array($body) && !$body instanceof \JsonSerializable) {
            throw new class(sprintf('Invalid request body: expected string, resource, array or JsonSerializable, "%s" given.', \is_object($body) ? \get_class($body) : \gettype($body))) extends \InvalidArgumentException implements TransportExceptionInterface {
            };
        }

        if (null !== $contentType && 'application/x-www-form-urlencoded' !== $contentType && 'application/json' !== $contentType) {
            throw new class(sprintf('Invalid request body: %s cannot be serialized to "%s".', \is_array($body) ? 'array' : 'JsonSerializable', $contentType)) extends \InvalidArgumentException implements TransportExceptionInterface {
            };
        }

        if ($body instanceof \JsonSerializable || 'application/json' === $contentType) {
            try {
                $body = json_encode($body, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            } catch (\Exception $e) {
                if ('Exception' === \get_class($e) && 0 === strpos($e->getMessage(), 'Failed calling ')) {
                    $e = $e->getPrevious() ?: $e;
                }

                throw new class($e->getMessage(), 0, $e) extends \InvalidArgumentException implements TransportExceptionInterface {
                };
            }

            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new class(json_last_error_msg()) extends \InvalidArgumentException implements TransportExceptionInterface {
                };
            }
        }

        if (null !== $contentType ? 'application/x-www-form-urlencoded' === $contentType : \is_array($body)) {
            $body = http_build_query(\is_array($body) ? $body : json_decode($body), '', '&');
        }

        if (!$hasContentLength) {
            $headers[] = 'Content-Length: '.\strlen($body);
        }

        if (null === $contentType) {
            $contentType = \is_array($body) ? 'application/x-www-form-urlencoded' : 'application/json';
            $headers[] = 'Content-Type: '.$contentType;
        }

        return $body;
    }
}
