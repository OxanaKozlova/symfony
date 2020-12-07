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
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\MalformedCipherException;

/**
 * A JSON Web Encryption (RFC 7516) representation of the encrypted message.
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
    /**
     * @var string algorithm for the asymmetric algorithm. Ie, for the symmetric nonce.
     */
    private $algorithm;

    /**
     * @var string algorithm for the symmetric algorithm. Ie, for the payload.
     */
    private $encryptionAlgorithm;

    /**
     * @var callable to get the encoded payload. Only available when creating a JWE
     */
    private $cipher;

    /**
     * @var string|null the encoded payload. Only available after parsing
     */
    private $ciphertext;

    /**
     * @var string the key that is used for decrypting the cipher text. This key
     * must be encrypted with $algorithm.
     */
    private $cek;

    /**
     * @var string Additional authentication data;
     */
    private $aad;

    /**
     * @var string nonce for the symmetric algorithm. Ie, for the payload.
     */
    private $initializationVector;

    /**
     * @var array additional headers
     */
    private $headers = [];

    private function __construct()
    {
    }

    /**
     * @param callable $cipher expects some additional data as first parameter to compute the ciphertext
     */
    public static function create(string $algorithm, string $cek, string $encAlgorithm, callable $cipher, string $initializationVector, array $headers = []): self
    {
        $jwe = new self();
        $jwe->algorithm = $algorithm;
        $jwe->cek = $cek;
        $jwe->encryptionAlgorithm = $encAlgorithm;
        $jwe->cipher = $cipher;
        $jwe->initializationVector = $initializationVector;
        $jwe->headers = $headers;

        return $jwe;
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

        [$headers, $cek, $initializationVector, $ciphertext, $authenticationTag] = $parts;

        $initializationVector = self::base64UrlDecode($initializationVector);
        $ciphertext = self::base64UrlDecode($ciphertext);
        $authenticationTag = self::base64UrlDecode($authenticationTag);

        // Check if Authentication Tag is valid
        $aad = self::computeAdditionalAuthenticationData($headers);
        $hash = hash('sha256', $aad.$initializationVector.$ciphertext);
        if (!hash_equals($hash, $authenticationTag)) {
            throw new MalformedCipherException();
        }

        $headers= json_decode(self::base64UrlDecode($headers), true);
        $cek = self::base64UrlDecode($cek);

        if (!is_array($headers) || !array_key_exists('enc', $headers) || !array_key_exists('alg', $headers)) {
            throw new MalformedCipherException();
        }

        $jwt = new self();
        $jwt->algorithm = $headers['alg'];
        unset($headers['alg']);
        $jwt->encryptionAlgorithm = $headers['enc'];
        unset($headers['enc']);
        $jwt->headers = $headers;
        $jwt->initializationVector = $initializationVector;
        $jwt->ciphertext = $ciphertext;
        $jwt->cek = $cek;
        $jwt->aad = $aad;

        return $jwt;
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
        $headers = array_merge($this->headers, [
            'alg' => $this->algorithm ?? 'none', // he algorithm to encrypt the CEK.
            'enc' => $this->encryptionAlgorithm, // The algorithm to encrypt the payload.
            'cty' => 'plaintext',
            'com.symfony.authentication_tag' => 'sha256',
        ]);

        $encodedHeader = self::base64UrlEncode(json_encode($headers));
        $aad = self::computeAdditionalAuthenticationData($encodedHeader);
        $cipher = $this->cipher;
        $ciphertext = $cipher($aad);

        $hash = hash('sha256', $aad.$this->initializationVector.$ciphertext);

        return sprintf('%s.%s.%s.%s.%s',
            $encodedHeader,
            self::base64UrlEncode($this->cek ?? 'none'),
            self::base64UrlEncode($this->initializationVector),
            self::base64UrlEncode($ciphertext),
            self::base64UrlEncode($hash)
        );
    }

    /**
     * This will compute a hash over the encoded headers.
     */
    private static function computeAdditionalAuthenticationData(string $input): string
    {
        $ascii = [];
        for ($i = 0; $i < strlen($input); $i++) {
            $ascii[] = ord($input[$i]);
        }

        return json_encode($ascii);
    }

    public function getAdditionalAuthenticationData(): string
    {
        return $this->aad;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function getCiphertext(): string
    {
        return $this->ciphertext;
    }



    public function hasHeader(string $name): bool
    {
        return array_key_exists($name, $this->headers);
    }

    public function getHeader(string $name): string
    {
        if ($this->hasHeader($name)) {
            return $this->headers[$name];
        }

        throw new DecryptionException(sprintf('The expected header "%s" is not found', $name));
    }

    public function getEncryptedCek(): ?string
    {
        return $this->cek;
    }

    public function getEncryptionAlgorithm(): string
    {
        return $this->encryptionAlgorithm;
    }

    public function getInitializationVector(): string
    {
        return $this->initializationVector;
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
