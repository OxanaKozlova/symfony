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

use Symfony\Contracts\Http\LazyResponseInterface;
use Symfony\Contracts\Http\ResponseTrait;

/**
 * @internal
 */
class NativeResponse implements LazyResponseInterface
{
    use ResponseTrait {
        getContent as doGetContent;
    }

    private $handle;
    private $remainingLength;

    public function __construct(array $rawHeaders, $handle, array $attributes, $output, bool $gzipEnabled)
    {
        $this->handle = $handle;
        if ($bodyIsPrivate = !$output) {
            $output = fopen('php://temp', 'w+');
        }
        $this->configure($rawHeaders, $output, $attributes, [$this, 'checkStatusCode']);
        $this->remainingLength = $this->headers['content-length'][0] ?? PHP_INT_MAX;

        if ($gzipEnabled && 'gzip' === ($this->headers['content-encoding'][0] ?? null)) {
            stream_filter_append($output, 'zlib.inflate', $bodyIsPrivate ? STREAM_FILTER_READ : STREAM_FILTER_WRITE, ['window' => 30]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContent($output = null): string
    {
        if (null !== $this->handle) {
            stream_set_blocking($this->handle, true);
            stream_copy_to_stream($this->handle, $this->body, $this->remainingLength);
            $this->handle = $this->remainingLength = 0;
        }

        return $this->doGetContent($output);
    }

    /**
     * {@inheritdoc}
     */
    public static function complete(LazyResponseInterface ...$responses): iterable
    {
        $jobs = $handles = $tail = [];

        foreach ($responses as $r) {
            if (!$r instanceof self) {
                $tail[] = $r;
            } elseif ($r->handle) {
                $k = (int) $r->handle;
                $jobs[$k] = $r;
                $handles[$k] = $r->handle;
                stream_set_blocking($r->handle, false);
            } else {
                yield $r;
            }
        }

        $w = $x = [];

        while ($handles) {
            $h = $handles;
            @stream_select($h, $w, $x, 1);
            $sleep = true;

            foreach ($h as $h) {
                $k = (int) $h;
                $r = $jobs[$k];

                while (0 < $r->remainingLength && '' !== $data = (string) fread($h, min(8192, $r->remainingLength))) {
                    fwrite($r->body, $data, \strlen($data));
                    $r->remainingLength -= \strlen($data);
                    $sleep = false;
                }

                if (0 >= $r->remainingLength || feof($h)) {
                    $r->handle = $r->remainingLength = null;
                    unset($handles[$k], $jobs[$k]);

                    yield $r;
                }
            }

            if ($sleep) {
                usleep(500);
            }
        }

        if ($tail) {
            yield from $tail[0]->complete(...$tail);
        }
    }
}
