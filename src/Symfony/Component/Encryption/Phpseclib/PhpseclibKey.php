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

/**
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
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

    public static function create(string $secret, string $private, string $public): self
    {
        $key = new self();
        $key->secret = $secret;
        $key->publicKey = $public;
        $key->privateKey = $private;

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

    public function createPublicKey(): KeyInterface
    {
        return self::fromPublicKey($this->getPublicKey());
    }

    public function serialize()
    {
        return serialize($this->__serialize());
    }

    final public function unserialize($serialized)
    {
        $this->__unserialize(unserialize($serialized));
    }

    public function __serialize(): array
    {
        return [$this->secret, $this->privateKey, $this->publicKey];
    }

    public function __unserialize(array $data): void
    {
        [$this->secret, $this->privateKey, $this->publicKey] = $data;
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
