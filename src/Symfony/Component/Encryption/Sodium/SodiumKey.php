<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Sodium;

use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\KeyInterface;

/**
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class SodiumKey implements KeyInterface
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
     * A keypair can only be created from a public and private key.
     *
     * @var string|null
     */
    private $keypair;

    public static function create(string $secret, string $keypair): self
    {
        $key = self::fromSecret($secret);
        $key->keypair = $keypair;
        $key->publicKey = sodium_crypto_box_publickey($keypair);
        $key->privateKey = sodium_crypto_box_secretkey($keypair);

        return $key;
    }

    /**
     * The secret key length should be 32 bytes, but other sizes are accepted.
     */
    public static function fromSecret(string $secret): self
    {
        $key = new self();

        $secretLength = \strlen($secret);
        if ($secretLength > \SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            $key->secret = substr($secret, 0, \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        } elseif ($secretLength < \SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            $key->secret = sodium_pad($secret, \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        } else {
            $key->secret = $secret;
        }

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

    public function createKeypair(KeyInterface $publicKey): KeyInterface
    {
        return self::fromPrivateAndPublicKeys($this->getPrivateKey(), $publicKey->getPublicKey());
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

    public function getKeypair(bool $allowCreatingPublicKey = false): string
    {
        if (null === $this->keypair) {
            if (null === $this->privateKey) {
                throw new InvalidKeyException('This key does not have a keypair.');
            }
            if (null === $this->publicKey) {
                if (!$allowCreatingPublicKey) {
                    throw new InvalidKeyException('This key does not have a keypair.');
                }

                $publicKey = sodium_crypto_box_publickey_from_secretkey($this->privateKey);

                // Dont cache this
                return sodium_crypto_box_keypair_from_secretkey_and_publickey($this->privateKey, $publicKey);
            }

            $this->keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($this->privateKey, $this->publicKey);
        }

        return $this->keypair;
    }
}
