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
use Symfony\Component\Encryption\JWE;
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
            // If there is hardware support
            if (sodium_crypto_aead_aes256gcm_is_available()) {
                $encAlgorithm = 'A256GCM';
                $cek = sodium_crypto_aead_aes256gcm_keygen();
                $initializationVector = random_bytes(\SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);
                $cipher = function ($aad) use ($message, $initializationVector, $cek) {
                    return sodium_crypto_aead_aes256gcm_encrypt($message, $aad, $initializationVector, $cek);
                };
            } else {
                // Fallback to less secure
                $encAlgorithm = 'sodium_secretbox';
                $cek = sodium_crypto_secretbox_keygen();
                $initializationVector = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $cipher = function ($aad) use ($message, $initializationVector, $cek) {
                    return sodium_crypto_secretbox($message, $initializationVector.$aad, $this->getSodiumKey($cek));
                };
            }
            $headers = [];

            // If symmetric
            if (null === $publicKey && null === $privateKey) {
                $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $headers['com.symfony.extra_nonce'] = sodium_bin2base64($nonce, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
                $encryptedCek = sodium_crypto_secretbox($cek, $nonce, $this->getSodiumKey($this->secret));

                return JWE::create('sodium_secretbox', $encryptedCek, $encAlgorithm, $cipher, $initializationVector, $headers)->getString();
            }

            // Assert: Asymmetric
            if (null == $privateKey) {
                $algorithm = 'sodium_crypto_box_seal';
                $encryptedCek = sodium_crypto_box_seal($cek, $publicKey);
            } else {
                $algorithm = 'sodium_crypto_box';
                $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $encryptedCek = sodium_crypto_box($cek, $nonce, sodium_crypto_box_keypair_from_secretkey_and_publickey($privateKey, $publicKey));
                $headers['com.symfony.extra_nonce'] = sodium_bin2base64($nonce, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            }

            return JWE::create($algorithm, $encryptedCek, $encAlgorithm, $cipher, $initializationVector, $headers)->getString();
        } catch (\SodiumException $exception) {
            throw new EncryptionException('Failed to encrypt message.', $exception);
        }
    }

    public function decrypt(string $message, ?string $privateKey = null, ?string $publicKey = null): string
    {
        $jwe = JWE::parse($message);
        $algorithm = $jwe->getAlgorithm();
        $encryptedCek = $jwe->getEncryptedCek();

        try {
            if ('sodium_crypto_box_seal' === $algorithm) {
                $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($privateKey, $publicKey ?? sodium_crypto_box_publickey_from_secretkey($privateKey));
                $cek = sodium_crypto_box_seal_open($encryptedCek, $keypair);
            } elseif ('sodium_crypto_box' === $algorithm) {
                if (null === $publicKey) {
                    throw new SignatureVerificationRequiredException();
                }
                $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($privateKey, $publicKey);
                $cek = sodium_crypto_box_open($encryptedCek, sodium_base642bin($jwe->getHeader('com.symfony.extra_nonce'), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), $keypair);
            } elseif ('sodium_secretbox' === $algorithm) {
                $key = $this->getSodiumKey($this->secret);
                $cek = sodium_crypto_secretbox_open($encryptedCek, sodium_base642bin($jwe->getHeader('com.symfony.extra_nonce'), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), $key);
            } else {
                throw new UnsupportedAlgorithmException($algorithm);
            }

            // Let's decrypt the ciphertext using cek
            $encAlgorithm = $jwe->getEncryptionAlgorithm();
            $ciphertext = $jwe->getCiphertext();

            if ('A256GCM' === $encAlgorithm) {
                $output = sodium_crypto_aead_aes256gcm_decrypt($ciphertext, $jwe->getAdditionalAuthenticationData(), $jwe->getInitializationVector(), $cek);
            } elseif ('sodium_secretbox' === $encAlgorithm) {
                $output = sodium_crypto_secretbox_open($ciphertext, $jwe->getInitializationVector().$jwe->getAdditionalAuthenticationData(), $this->getSodiumKey($cek));
            } else {
                throw new UnsupportedAlgorithmException($encAlgorithm);
            }
        } catch (\SodiumException $exception) {
            throw new DecryptionException(sprintf('Failed to decrypt message with algorithm "%s".', $algorithm), $exception);
        }

        if (false === $output) {
            throw new DecryptionException();
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
