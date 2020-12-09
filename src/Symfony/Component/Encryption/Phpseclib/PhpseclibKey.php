<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Phpseclib;

use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\KeyInterface;

final class PhpseclibKey implements KeyInterface
{
    /**
     * @var string|null
     */
    private $secret;

    /**
     * @var string|null
     */
    private $privateKey;

    /**
     * @var string|null
     */
    private $publicKey;

    /**
     * @internal
     */
    public static function create(string $secret, string $private, string $public): self
    {
        $key = new self();
        $key->secret = $secret;
        $key->publicKey = $public;
        $key->privateKey = $private;

        return $key;
    }

    public static function fromSecret(string $secret): self
    {
        $key = new self();
        $key->secret = $secret;

        return $key;
    }

    public static function fromPrivateKey(string $privateKey): self
    {
        $key = new self();
        $key->privateKey = $privateKey;

        return $key;
    }

    public static function fromPrivateAndPublicKeys(string $privateKey, string $publicKey): self
    {
        $key = new self();
        $key->privateKey = $privateKey;
        $key->publicKey = $publicKey;

        return $key;
    }

    public static function fromPublicKey(string $publicKey): self
    {
        $key = new self();
        $key->publicKey = $publicKey;

        return $key;
    }

    public static function fromKeypair(string $keypair): self
    {
        $key = new self();
        $key->keypair = $keypair;

        return $key;
    }

    public function createPublicKey(): KeyInterface
    {
        return self::fromPublicKey($this->getPublicKey());
    }

    public function toString(): string
    {
        return serialize([$this->secret, $this->privateKey, $this->publicKey]);
    }

    public function fromString(string $string): KeyInterface
    {
        $key = new self();
        [$key->secret, $key->privateKey, $key->publicKey] = unserialize($string);

        return $key;
    }

    public function createKeypair(KeyInterface $publicKey): KeyInterface
    {
        return self::fromPrivateAndPublicKeys($this->getPrivateKey(), $publicKey->getPublicKey());
    }

    public function getSecret(): string
    {
        if (null === $this->secret) {
            throw new InvalidKeyException('This key does not have a secret.');
        }

        return $this->secret;
    }

    public function getPrivateKey(): string
    {
        if (null === $this->privateKey) {
            throw new InvalidKeyException('This key does not have a private key.');
        }

        return $this->privateKey;
    }

    public function getPublicKey(): string
    {
        if (null === $this->publicKey) {
            throw new InvalidKeyException('This key does not have a public key.');
        }

        return $this->publicKey;
    }

}
