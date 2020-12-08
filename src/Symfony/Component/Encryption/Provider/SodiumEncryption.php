<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Provider;

use Symfony\Component\Encryption\AsymmetricEncryptionInterface;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\EncryptionException;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\SignatureVerificationRequiredException;
use Symfony\Component\Encryption\Exception\UnsupportedAlgorithmException;
use Symfony\Component\Encryption\Ciphertext;
use Symfony\Component\Encryption\SymmetricEncryptionInterface;

/**
 * The secret key length should be 32 bytes, but other sizes are accepted.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 5.3
 */
class SodiumEncryption implements SymmetricEncryptionInterface, AsymmetricEncryptionInterface
{
    private $secret;

    /**
     * @var string application secret
     */
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
        if (null === $publicKey && null !== $privateKey) {
            throw new InvalidArgumentException('Private key cannot have a value when no public key is provided.');
        }

        try {
            // If symmetric
            if (null === $publicKey && null === $privateKey) {
                return $this->symmetricEncryption($message);
            }

            // Assert: Asymmetric
            $nonce = random_bytes(\SODIUM_CRYPTO_BOX_NONCEBYTES);
            if (null === $privateKey) {
                $algorithm = 'sodium_crypto_box_seal';
                $ciphertext = sodium_crypto_box_seal($message, $publicKey);
            } else {
                $algorithm = 'sodium_crypto_box';
                $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($privateKey, $publicKey);
                $ciphertext = sodium_crypto_box($message, $nonce, $keypair);
            }

            return Ciphertext::create($algorithm, $ciphertext, $nonce)->getString();
        } catch (\SodiumException $exception) {
            throw new EncryptionException('Failed to encrypt message.', $exception);
        }
    }

    public function decrypt(string $message, ?string $privateKey = null, ?string $publicKey = null): string
    {
        $ciphertext = Ciphertext::parse($message);
        $algorithm = $ciphertext->getAlgorithm();
        $payload = $ciphertext->getPayload();
        $nonce = $ciphertext->getNonce();

        try {
            if ('sodium_crypto_box_seal' === $algorithm) {
                $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($privateKey, $publicKey ?? sodium_crypto_box_publickey_from_secretkey($privateKey));
                $output = sodium_crypto_box_seal_open($payload, $keypair);
            } elseif ('sodium_crypto_box' === $algorithm) {
                if (null === $publicKey) {
                    throw new SignatureVerificationRequiredException();
                }
                $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($privateKey, $publicKey);
                $output = sodium_crypto_box_open($payload, $nonce, $keypair);
            } elseif ('sodium_secretbox' === $algorithm) {
                $output = sodium_crypto_secretbox_open($payload, $nonce, $this->getSodiumKey($this->secret));
            } else {
                throw new UnsupportedAlgorithmException($algorithm);
            }
        } catch (\SodiumException $exception) {
            throw new DecryptionException(sprintf('Failed to decrypt message with algorithm "%s".', $algorithm), $exception);
        }

        if (false === $output) {
            throw new DecryptionException();
        }

        return $output;
    }

    private function symmetricEncryption(string $message): string
    {
        $key = $this->getSodiumKey($this->secret);
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($message, $nonce, $key);

        return Ciphertext::create('sodium_secretbox', $ciphertext, $nonce)->getString();
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
