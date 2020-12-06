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

use Symfony\Component\Encryption\Exception\MalformedCipherException;

/**
 * A representation of the encrypted message.
 *
 * This class is responsible over the payload API.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 5.3
 *
 * @internal
 */
class Ciphertext
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
    public static function parse(string $ciphertext): self
    {
        $parts = explode('.', $ciphertext);
        if (false === $parts || 3 !== \count($parts)) {
            throw new MalformedCipherException();
        }

        [$cipher, $algorithm, $nonce] = $parts;
        $algorithm = base64_decode($algorithm, true);
        $ciphertext = base64_decode($cipher, true);
        $nonce = base64_decode($nonce, true);

        return new self($algorithm, $ciphertext, $nonce);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getUrlSafeRepresentation();
    }

    public function getUrlSafeRepresentation(): string
    {
        return sprintf('%s.%s.%s', base64_encode($this->ciphertext), base64_encode($this->algorithm), base64_encode($this->nonce));
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
}
