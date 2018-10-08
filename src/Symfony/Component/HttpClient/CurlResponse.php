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
use Symfony\Contracts\Http\LazyResponseInterface;
use Symfony\Contracts\Http\ResponseTrait;

/**
 * @internal
 */
class CurlResponse implements LazyResponseInterface
{
    use ResponseTrait;

    private $ch;

    public function __construct($ch, $hd, $fd)
    {
        $this->ch = $ch;
        $this->initializer = static function (self $response) use ($ch, $hd, $fd) {
            $response->ch = null;
            $attributes = curl_getinfo($ch);

            if (!$attributes['header_size']) {
                curl_exec($ch);
                $attributes = curl_getinfo($ch);
            }

            curl_setopt($ch, CURLOPT_NOPROGRESS, true);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, null);

            if ($attributes['http_code']) {
                rewind($hd);
                $rawHeaders = array_filter(explode("\r\n", stream_get_contents($hd)));
                fclose($hd);
                $response->configure($rawHeaders, $fd, $attributes);
                $response->checkStatusCode();
            }

            if ('' !== curl_error($ch) || CURLE_OK !== curl_errno($ch)) {
                throw new class(curl_error($ch)) extends \RuntimeException implements TransportExceptionInterface {
                };
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    public static function complete(LazyResponseInterface ...$responses): iterable
    {
        $mh = curl_multi_init();
        curl_multi_setopt($mh, CURLMOPT_PIPELINING, /*CURLPIPE_HTTP1 | CURLPIPE_MULTIPLEX*/ 3);

        $jobs = $tail = [];
        foreach ($responses as $r) {
            if (!$r instanceof self) {
                $tail[] = $r;
            } elseif ($r->ch && !curl_getinfo($r->ch, CURLINFO_HEADER_SIZE)) {
                $jobs[(int) $r->ch] = $r;
                curl_multi_add_handle($mh, $r->ch);
            } else {
                yield $r;
            }
        }

        $active = true;

        while ($active && $jobs) {
            curl_multi_exec($mh, $active);
            curl_multi_select($mh);

            while ($result = curl_multi_info_read($mh)) {
                $ch = $result['handle'];
                curl_multi_remove_handle($mh, $ch);

                yield $jobs[(int) $ch];
                unset($jobs[(int) $ch]);
            }
        }

        curl_multi_close($mh);

        if ($tail) {
            yield from $tail[0]->complete(...$tail);
        }
    }
}
