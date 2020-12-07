<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption;

use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Symfony\Component\Encryption\Exception\MalformedCipherException;

/**
 * A JSON Web Encryption representation of the encrypted message.
 *
 * This class is responsible over the payload API.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 5.3
 *
 * @internal
 */
class JWE
{
    private $algorithm;
    private $ciphertext;
    private $nonce;

    public function __construct(string $algorithm, string $ciphertext, string $nonce)
    {
        $this->algorithm = $algorithm;
        $this->ciphertext = $ciphertext;
        $this->nonce = $nonce;
    }

    /**
     * Take a string representation of the chiphertext and parse it into an object.
     *
     * @throws MalformedCipherException
     */
    public static function parse(string $input): self
    {
        $parts = explode('.', $input);
        if (false === $parts || 5 !== \count($parts)) {
            throw new MalformedCipherException();
        }

        [$header, $cek, $initializationVector, $ciphertext, $authenticationTag] = $parts;
        $header = json_decode(self::base64UrlDecode($header), true);
        $cek = self::base64UrlDecode($cek);
        $ciphertext = self::base64UrlDecode($ciphertext);
        $authenticationTag = self::base64UrlDecode($authenticationTag);

        if (md5($ciphertext) !== $authenticationTag) {
            throw new MalformedCipherException();
        }

        if (!is_array($header) || !array_key_exists('alg', $header)) {
            throw new MalformedCipherException();
        }

        return new self($header['alg'], $ciphertext, $cek);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getString();
    }

    public function getString(): string
    {
        $header = self::base64UrlEncode(json_encode([
            'alg' => $this->algorithm,
            'enc' => 'none', // How we encode the CEK/nonce
        ]));
        $cek = self::base64UrlEncode($this->nonce);
        $initializationVector = self::base64UrlEncode(random_bytes(128));

        return sprintf('%s.%s.%s.%s.%s', $header, $cek, $initializationVector, self::base64UrlEncode($this->ciphertext), self::base64UrlEncode(md5($this->ciphertext)));
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function getCiphertext(): string
    {
        return $this->ciphertext;
    }

    public function getNonce(): string
    {
        return $this->nonce;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        // Padding isn't added back because it isn't strictly necessary for decoding with PHP
        $decodedContent = base64_decode(strtr($data, '-_', '+/'), true);

        if (! is_string($decodedContent)) {
            throw new MalformedCipherException('Could not base64 decode the content');
        }

        return $decodedContent;
    }
}
