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
use Symfony\Component\Encryption\AsymmetricEncryptionInterface;
use Symfony\Component\Encryption\Ciphertext;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\EncryptionException;
use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\SignatureVerificationRequiredException;
use Symfony\Component\Encryption\Exception\UnableToVerifySignatureException;
use Symfony\Component\Encryption\Exception\UnsupportedAlgorithmException;
use Symfony\Component\Encryption\SymmetricEncryptionInterface;

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
class PhpseclibEncryption implements SymmetricEncryptionInterface, AsymmetricEncryptionInterface
{
    // TODO fix this class

    public function generateKeypair(): array
    {
        $rsa = new RSA();
        $key = $rsa->createKey();

        if ($key['partialkey']) {
            throw new EncryptionException('Failed to generate RSA keypair.');
        }

        return [
            'public' => $key['publickey'],
            'private' => $key['privatekey'],
        ];
    }

    public function encrypt(string $message, ?string $publicKey = null, ?string $privateKey = null): string
    {
        if (null === $publicKey && null !== $privateKey) {
            throw new InvalidArgumentException('Private key cannot have a value when no public key is provided.');
        }

        set_error_handler(__CLASS__.'::throwError');

        try {
            if (null === $publicKey && null === $privateKey) {
                $aes = new AES();
                $aes->setPassword($this->secret);
                $aes->setIV($nonce = Random::string($aes->getBlockLength() >> 3));

                return Ciphertext::create('RSAES-PKCS1-v1_5', $aes->encrypt($message), $nonce)->getString();
            }

            $headers = [];

            // Asymmetric encryption
            $rsa = new RSA();
            $rsa->loadKey($publicKey);
            $rsa->setEncryptionMode(RSA::ENCRYPTION_OAEP);
            $ciphertext = $rsa->encrypt($message);

            if (null !== $privateKey) {
                // Load private key after encryption
                $rsa->loadKey($privateKey);
                $rsa->setSignatureMode(RSA::SIGNATURE_PSS);
                $headers['alg_signature'] = 'RSA-PSS';
                $headers['signature'] = base64_encode($rsa->sign($ciphertext));
            }

            return Ciphertext::create('RSA-OAEP', $ciphertext, random_bytes(8), $headers)->getString();
        } catch (\ErrorException $exception) {
            throw new EncryptionException(null, $exception);
        } finally {
            restore_error_handler();
        }
    }

    public function decrypt(string $message, ?string $privateKey = null, ?string $publicKey = null): string
    {
        $ciphertext = Ciphertext::parse($message);
        $algorithm = $ciphertext->getAlgorithm();
        $payload = $ciphertext->getPayload();
        $nonce = $ciphertext->getNonce();

        set_error_handler(__CLASS__.'::throwError');
        try {
            if ('RSAES-PKCS1-v1_5' === $algorithm) {
                $aes = new AES();
                $aes->setPassword($this->secret);
                $aes->setIV($nonce);
                $output = $aes->decrypt($payload);
            } elseif ('RSA-OAEP' === $algorithm) {
                $rsa = new RSA();
                if ($ciphertext->hasHeader('alg_signature')) {
                    if (null === $publicKey) {
                        throw new SignatureVerificationRequiredException();
                    }

                    if ('RSA-PSS' !== $ciphertext->getHeader('alg_signature')) {
                        throw new UnsupportedAlgorithmException($ciphertext->getHeader('alg_signature'));
                    }

                    $rsa->loadKey($publicKey);
                    $verify = $rsa->verify($payload, base64_decode($ciphertext->getHeader('signature')));
                    if (!$verify) {
                        throw new UnableToVerifySignatureException();
                    }
                } elseif (null !== $publicKey) {
                    throw new UnableToVerifySignatureException();
                }

                $rsa->loadKey($privateKey);
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
