<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient;

use Symfony\Contracts\Http\Exception\TransportExceptionInterface;
use Symfony\Contracts\Http\HttpClientInterface;
use Symfony\Contracts\Http\HttpClientTrait;
use Symfony\Contracts\Http\LazyResponseInterface;

class NativeHttpClient implements HttpClientInterface
{
    use HttpClientTrait;

    /**
     * {@inheritdoc}
     */
    public function request(string $method, string $uri, array $options = []): LazyResponseInterface
    {
        $options = $this->prepareOptions($options);
        $options['http']['method'] = $method;
        $options['http']['ignore_errors'] = true;
        $options['http']['curl_verify_ssl_host'] = $options['ssl']['verify_peer_name'] ?? true;
        $options['http']['curl_verify_ssl_peer'] = $options['ssl']['verify_peer'] ?? true;
        $options['ssl'] = array_filter($options['ssl'], function ($v) { return null !== $v; });
        $options['ssl']['SNI_enabled'] = true;
        $options['ssl']['disable_compression'] = true;
        $output = $options['output'];
        $progress = $options['progress'] ?: [];
        unset($options['output'], $options['progress']);

        if (!$options['http']['protocol_version']) {
            $options['http']['protocol_version'] = '1.1';
        }

        if (\is_resource($options['http']['content'])) {
            $options['http']['content'] = stream_get_contents($options['http']['content']);
        }

        if ($options['http']['follow_location']) {
            $options['http']['max_redirects'] = $options['http']['follow_location'] - 1;
            $options['http']['follow_location'] = true;
        }

        $options['http']['user_agent'] = 'Symfony HttpClient/Native';

        if ($gzipEnabled = \extension_loaded('zlib') && !array_filter($options['http']['header'], function ($h) { return false !== stripos($h, 'Accept-Encoding:'); })) {
            $options['http']['header'][] = 'Accept-Encoding: gzip';
        }

        if ($progress) {
            $downloadLength = 0;
            if ($uploadLength = \strlen($options['http']['content'])) {
                $progress(0, 0, $uploadLength, 0);
            }
            $progress = function (int $code, int $severity, ?string $msg, int $msgCode, int $downloaded, int $dlLen) use ($progress, &$downloadLength, &$uploadLength) {
                switch ($code) {
                    case STREAM_NOTIFY_FILE_SIZE_IS: $downloadLength = $dlLen; break;
                    case STREAM_NOTIFY_REDIRECTED: $downloadLength = 0; break;
                    case STREAM_NOTIFY_PROGRESS: $progress($downloadLength, $downloaded, $uploadLength, $uploadLength); break;
                }
            };
            $progress = ['notification' => $progress];
        }

        $previousHandler = set_error_handler(static function ($type, $msg, $file, $line, $ctx = []) use (&$errorMsg, &$previousHandler) {
            return __FILE__ === $file ? !$errorMsg = $msg : ($previousHandler ? $previousHandler($type, $msg, $file, $line, $ctx) : false);
        });
        try {
            if (!$context = @stream_context_create($options, $progress)) {
                throw new class($errorMsg) extends \RuntimeException implements TransportExceptionInterface {
                };
            }

            if (!$handle = @fopen($uri, 'r', false, $context)) {
                throw new class($errorMsg) extends \RuntimeException implements TransportExceptionInterface {
                };
            }
        } finally {
            restore_error_handler();
        }

        return new NativeResponse($http_response_header ?? [], $handle, [], $output, $gzipEnabled);
    }
}
