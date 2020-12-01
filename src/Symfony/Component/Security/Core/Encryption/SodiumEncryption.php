<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Encryption;

use Symfony\Component\Security\Core\Exception\EncryptionException;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\MalformedCipherException;
use Symfony\Component\Security\Core\Exception\UnsupportedAlgorithmException;
use Symfony\Component\Security\Core\Exception\WrongEncryptionKeyException;

/**
 * The secret key length should be 32 bytes, but other sizes are accepted.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @experimental in 5.2
 */
class SodiumEncryption implements SymmetricEncryptionInterface, AsymmetricEncryptionInterface
{
    /**
     * @var string application secret
     */
    private $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function generateKeypair(): array
    {
        $keypair = sodium_crypto_box_keypair();

        return [
            'public' => sodium_crypto_box_publickey($keypair),
            'private' => sodium_crypto_box_secretkey($keypair),
        ];
    }

    public function encrypt(string $message, ?string $publicKey = null, ?string $privateKey = null): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        try {
            if (null === $publicKey) {
                $algorithm = 'sodium_secretbox';
                $cipher = sodium_crypto_secretbox($message, $nonce, $this->getSodiumKey($this->secret));
            } elseif (null === $privateKey) {
                $algorithm = 'sodium_crypto_box_seal';
                $cipher = sodium_crypto_box_seal($message, $publicKey);
            } elseif (null !== $publicKey && null !== $privateKey) {
                $algorithm = 'sodium_crypto_box';
                $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($privateKey, $publicKey);
                $cipher = sodium_crypto_box($message, $nonce, $keypair);
            } else {
                throw new InvalidArgumentException('Private key cannot have a value when no public key is provided.');
            }
        } catch (\SodiumException $exception) {
            throw new EncryptionException(sprintf('Failed to encrypt message with algorithm "%s".', $algorithm), 0, $exception);
        }

        return sprintf('%s.%s.%s', base64_encode($cipher), base64_encode($algorithm), base64_encode($nonce));
    }

    public function decrypt(string $message, ?string $privateKey = null, ?string $publicKey = null): string
    {
        // Make sure the message has two periods
        $parts = explode('.', $message);
        if (false === $parts || 3 !== \count($parts)) {
            throw new MalformedCipherException();
        }

        [$cipher, $algorithm, $nonce] = $parts;
        $algorithm = base64_decode($algorithm);
        $ciphertext = base64_decode($cipher, true);
        $nonce = base64_decode($nonce, true);

        try {
            if ('sodium_crypto_box_seal' === $algorithm) {
                $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($privateKey, $publicKey ?? sodium_crypto_box_publickey_from_secretkey($privateKey));
                $output = sodium_crypto_box_seal_open($ciphertext, $keypair);
            } elseif ('sodium_crypto_box' === $algorithm) {
                if (null === $publicKey) {
                    throw new WrongEncryptionKeyException();
                }
                $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($privateKey, $publicKey);
                $output = sodium_crypto_box_open($ciphertext, $nonce, $keypair);
            } elseif ('sodium_secretbox' === $algorithm) {
                $key = $this->getSodiumKey($this->secret);
                $output = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
            } else {
                throw new UnsupportedAlgorithmException($algorithm);
            }
        } catch (\SodiumException $exception) {
            throw new EncryptionException(sprintf('Failed to decrypt message with algorithm "%s".', $algorithm), 0, $exception);
        }

        if (false === $output) {
            throw new WrongEncryptionKeyException();
        }

        return $output;
    }

    private function getSodiumKey(string $secret): string
    {
        $secretLength = \strlen($secret);
        if ($secretLength > \SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return substr($secret, 0, \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }
        if ($secretLength < \SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return sodium_pad($secret, \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }

        return $secret;
    }
}
