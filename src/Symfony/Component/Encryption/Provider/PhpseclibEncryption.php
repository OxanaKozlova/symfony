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

use phpseclib\Crypt\AES;
use phpseclib\Crypt\Random;
use phpseclib\Crypt\RSA;
use Symfony\Component\Encryption\AsymmetricEncryptionInterface;
use Symfony\Component\Encryption\JWE;
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
        $nonce = random_bytes(12); // 96 bits
        try {
            if (null === $publicKey && null === $privateKey) {
                $ciphertext = $this->symmetricEncryption($message, $nonce, $this->secret);

                return JWE::createWithOnlySymmetricEncryption('A128CBC-HS256', $ciphertext, $nonce)->getString();
            }

            // Asymmetric encryption
            $cek = Random::string(32);
            $rsa = new RSA();
            $rsa->loadKey($publicKey);
            $rsa->setEncryptionMode(RSA::ENCRYPTION_OAEP);
            $encryptedCek = $rsa->encrypt($cek);
            $ciphertext = $this->symmetricEncryption($message, $nonce, $cek);

            $headers = [];
            if ($privateKey !== null) {
                // Load private key after encryption
                $rsa->loadKey($privateKey);
                $rsa->setSignatureMode(RSA::SIGNATURE_PSS);
                $headers['com.symfony.signature'] = $rsa->sign($ciphertext);
            }

            return JWE::create('RSA-OAEP', $encryptedCek, 'A128CBC-HS256', $ciphertext, $nonce, $headers)->getString();
        } catch (\ErrorException $exception) {
            throw new EncryptionException(null, $exception);
        } finally {
            restore_error_handler();
        }
    }

    public function decrypt(string $message, ?string $privateKey = null, ?string $publicKey = null): string
    {
        $parsedMessage = JWE::parse($message);
        $algorithm = $parsedMessage->getAlgorithm();
        $ciphertext = $parsedMessage->getCiphertext();
        $nonce = $parsedMessage->getNonce();

        set_error_handler(__CLASS__.'::throwError');
        try {
            if ('rsa' === $algorithm) {
                if (null !== $publicKey) {
                    throw new UnableToVerifySignatureException();
                }

                $rsa = new RSA();
                $rsa->loadKey($privateKey);
                $output = $rsa->decrypt($ciphertext);
            } elseif ('rsa_signature_pss' === $algorithm) {
                if (null === $publicKey) {
                    throw new SignatureVerificationRequiredException();
                }
                $rsa = new RSA();
                $rsa->loadKey($publicKey);
                $verify = $rsa->verify($ciphertext, $nonce);
                if (!$verify) {
                    throw new UnableToVerifySignatureException();
                }

                // Load private key after verification
                $rsa->loadKey($privateKey);
                $output = $rsa->decrypt($ciphertext);
            } elseif ('aes' === $algorithm) {
                $aes = new AES();
                $aes->setKey($this->secret);
                $output = $aes->decrypt($ciphertext);
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

    private function symmetricEncryption(string $message, string $nonce, string $secret): string
    {
        $aes = new AES();
        $aes->setKey($secret);
        $aes->setIV($nonce);

        return $aes->encrypt($message);
    }
}
