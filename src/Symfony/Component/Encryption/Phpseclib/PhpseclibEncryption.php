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

use phpseclib\Crypt\AES;
use phpseclib\Crypt\Random;
use phpseclib\Crypt\RSA;
use Symfony\Component\Encryption\Ciphertext;
use Symfony\Component\Encryption\EncryptionInterface;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\EncryptionException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\Exception\UnableToVerifySignatureException;
use Symfony\Component\Encryption\Exception\UnsupportedAlgorithmException;
use Symfony\Component\Encryption\KeyInterface;

if (!class_exists(RSA::class)) {
    throw new \LogicException('You cannot use "Symfony\Component\Security\Core\Encryption\PhpseclibEncryption" as the "phpseclib/phpseclib:2.x" package is not installed. Try running "composer require phpseclib/phpseclib:^2".');
}

/**
 * The secret key length should be 32 bytes, but other sizes are accepted.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 5.3
 */
class PhpseclibEncryption implements EncryptionInterface
{
    public function generateKey(string $secret = null): KeyInterface
    {
        $rsa = new RSA();
        $key = $rsa->createKey();

        if ($key['partialkey']) {
            throw new EncryptionException('Failed to generate RSA keypair.');
        }

        if (null === $secret) {
            $secret = random_bytes(32);
        }

        return PhpseclibKey::create($secret, $key['privatekey'], $key['publickey']);
    }

    public function encrypt(string $message, KeyInterface $myKey): string
    {
        if (!$myKey instanceof PhpseclibKey) {
            throw new InvalidKeyException();
        }

        set_error_handler(__CLASS__.'::throwError');

        try {
            $aes = new AES();
            $aes->setPassword($myKey->getSecret());
            $aes->setIV($nonce = Random::string($aes->getBlockLength() >> 3));

            return Ciphertext::create('RSAES-PKCS1-v1_5', $aes->encrypt($message), $nonce)->getString();
        } catch (\ErrorException $exception) {
            throw new EncryptionException(null, $exception);
        } finally {
            restore_error_handler();
        }
    }

    public function encryptFor(string $message, KeyInterface $recipientKey): string
    {
        if (!$recipientKey instanceof PhpseclibKey) {
            throw new InvalidKeyException();
        }

        try {
            $rsa = new RSA();
            $rsa->loadKey($recipientKey->getPublicKey());
            $rsa->setEncryptionMode(RSA::ENCRYPTION_OAEP);

            return Ciphertext::create('RSA-OAEP', $rsa->encrypt($message), random_bytes(8))->getString();
        } catch (\ErrorException $exception) {
            throw new EncryptionException(null, $exception);
        } finally {
            restore_error_handler();
        }
    }

    public function encryptForAndSign(string $message, KeyInterface $keypair): string
    {
        if (!$keypair instanceof PhpseclibKey) {
            throw new InvalidKeyException();
        }
        try {
            $rsa = new RSA();
            $rsa->loadKey($keypair->getPublicKey());
            $rsa->setEncryptionMode(RSA::ENCRYPTION_OAEP);
            $ciphertext = $rsa->encrypt($message);

            // Load private key after encryption
            $rsa->loadKey($keypair->getPublicKey());
            $rsa->setSignatureMode(RSA::SIGNATURE_PSS);
            $headers['signature'] = base64_encode($rsa->sign($ciphertext));

            return Ciphertext::create('RSA-OAEP-PSS', $ciphertext, random_bytes(8), $headers)->getString();
        } catch (\ErrorException $exception) {
            throw new EncryptionException(null, $exception);
        } finally {
            restore_error_handler();
        }
    }

    public function decrypt(string $message, KeyInterface $key): string
    {
        if (!$key instanceof PhpseclibKey) {
            throw new InvalidKeyException();
        }

        $ciphertext = Ciphertext::parse($message);
        $algorithm = $ciphertext->getAlgorithm();
        $payload = $ciphertext->getPayload();
        $nonce = $ciphertext->getNonce();

        set_error_handler(__CLASS__.'::throwError');
        try {
            if ('RSAES-PKCS1-v1_5' === $algorithm) {
                $aes = new AES();
                $aes->setPassword($key->getSecret());
                $aes->setIV($nonce);
                $output = $aes->decrypt($payload);
            } elseif ('RSA-OAEP' === $algorithm || 'RSA-OAEP-PSS' === $algorithm) {
                $rsa = new RSA();
                if ('RSA-OAEP-PSS' === $algorithm) {
                    $rsa->loadKey($key->getPublicKey());
                    $verify = $rsa->verify($payload, base64_decode($ciphertext->getHeader('signature')));
                    if (!$verify) {
                        throw new UnableToVerifySignatureException();
                    }
                }

                $rsa->loadKey($key->getPrivateKey());
                $output = $rsa->decrypt($payload);
            } else {
                throw new UnsupportedAlgorithmException($algorithm);
            }
        } catch (\ErrorException $exception) {
            throw new DecryptionException(sprintf('Failed to decrypt message with algorithm "%s".', $algorithm), $exception);
        } finally {
            restore_error_handler();
        }

        if (false === $output) {
            throw new DecryptionException();
        }

        return $output;
    }

    /**
     * @internal
     */
    public static function throwError($type, $message, $file, $line)
    {
        throw new \ErrorException($message, 0, $type, $file, $line);
    }
}
