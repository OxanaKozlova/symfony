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

use Symfony\Contracts\Http\HttpClientInterface;
use Symfony\Contracts\Http\HttpClientTrait;
use Symfony\Contracts\Http\LazyResponseInterface;

class CurlHttpClient implements HttpClientInterface
{
    use HttpClientTrait;

    private $shareHandle;

    private static $curlOptionMaps = [
        'http' => [
            'header' => CURLOPT_HTTPHEADER,
            'content' => CURLOPT_POSTFIELDS,
            'proxy' => CURLOPT_PROXY,
        ],
        'ssl' => [
            'verify_peer' => CURLOPT_SSL_VERIFYPEER,
            'verify_peer_name' => CURLOPT_SSL_VERIFYHOST,
            'cafile' => CURLOPT_CAINFO,
            'capath' => CURLOPT_CAPATH,
            'ciphers' => CURLOPT_SSL_CIPHER_LIST,
            'local_cert' => CURLOPT_SSH_PUBLIC_KEYFILE,
            'local_pk' => CURLOPT_SSH_PRIVATE_KEYFILE,
            'passphrase' => CURLOPT_KEYPASSWD,
        ],
        'socket' => [
            'bindto' => CURLOPT_INTERFACE,
            'tcp_nodelay' => CURLOPT_TCP_NODELAY,
        ],
    ];

    public function __construct(array $defaultOptions = [])
    {
        if ($defaultOptions) {
            $this->defaultOptions = array_replace_recursive($this->defaultOptions, $defaultOptions);
        }
        $this->shareHandle = $sh = curl_share_init();
        curl_share_setopt($sh, CURLSHOPT_SHARE, CURL_LOCK_DATA_DNS);
        curl_share_setopt($sh, CURLSHOPT_SHARE, CURL_LOCK_DATA_SSL_SESSION);
    }

    /**
     * {@inheritdoc}
     */
    public function request(string $method, string $uri, array $options = []): LazyResponseInterface
    {
        $options = $this->prepareOptions($options);

        $ch = curl_init();
        $hd = fopen('php://temp', 'w+');
        $fd = $options['output'] ?: fopen('php://temp', 'w+');

        if ('1.0' === $options['http']['protocol_version']) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        } elseif ('1.1' === $options['http']['protocol_version'] || 0 !== strpos($uri, 'https://')) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        } elseif (\defined('CURL_VERSION_HTTP2') && \defined('CURL_HTTP_VERSION_2_0') && (CURL_VERSION_HTTP2 & curl_version()['features'])) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        curl_setopt($ch, CURLOPT_WRITEHEADER, $hd);
        curl_setopt($ch, CURLOPT_FILE, $fd);
        curl_setopt($ch, CURLOPT_SHARE, $this->shareHandle);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Symfony HttpClient/Curl');
        curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
        curl_setopt($ch, CURLOPT_TCP_FASTOPEN, true);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HEADEROPT, CURLHEADER_SEPARATE);

        if (\is_resource($options['http']['content'])) {
            curl_setopt($ch, CURLOPT_INFILE, $options['http']['content']);
            if ($size = @filesize(stream_get_meta_data($options['http']['content'])['uri'])) {
                curl_setopt($ch, CURLOPT_INFILESIZE, $size);
            }
            unset($options['http']['content']);
        }

        if ($options['http']['follow_location']) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $options['http']['follow_location']);
        }

        if ($options['ssl']['verify_peer_name']) {
            $options['ssl']['verify_peer_name'] = 2;
        }

        if ($options['socket']['bindto'] && file_exists($options['socket']['bindto'])) {
            curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $options['socket']['bindto']);
            unset($options['socket']['bindto']);
        }

        foreach (self::$curlOptionMaps as $type => $map) {
            foreach ($map as $name => $curlopt) {
                if (isset($options[$type][$name])) {
                    curl_setopt($ch, $curlopt, $options[$type][$name]);
                }
            }
        }

        if ($progress = $options['progress']) {
            $previousState = [];
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, static function ($ch, ...$state) use ($progress, &$previousState) {
                if ($previousState !== $state) {
                    $previousState = $state;
                    $progress(...$state);
                }
            });
        }

        return new CurlResponse($ch, $hd, $fd);
    }
}
