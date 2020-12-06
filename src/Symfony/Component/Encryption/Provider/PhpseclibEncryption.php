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
        set_error_handler(__CLASS__.'::throwError');
        $nonce = random_bytes(16);
        try {
            if (null === $publicKey) {
                $algorithm = 'aes';
                $aes = new AES();
                $aes->setKey($this->secret);
                $ciphertext = $aes->encrypt($message);
            } elseif (null === $privateKey) {
                $algorithm = 'rsa';
                $rsa = new RSA();
                $rsa->loadKey($publicKey);
                $ciphertext = $rsa->encrypt($message);
            } elseif (null !== $publicKey && null !== $privateKey) {
                $algorithm = 'rsa_signature_pss';
                $rsa = new RSA();
                $rsa->loadKey($publicKey);
                $ciphertext = $rsa->encrypt($message);

                // Load private key after encryption
                $rsa->loadKey($privateKey);
                $rsa->setSignatureMode(RSA::SIGNATURE_PSS);
                $nonce = $rsa->sign($ciphertext);
            } else {
                throw new InvalidArgumentException('Private key cannot have a value when no public key is provided.');
            }
        } catch (\ErrorException $exception) {
            throw new EncryptionException(sprintf('Failed to encrypt message with algorithm "%s".', $algorithm), 0, $exception);
        } finally {
            restore_error_handler();
        }

        return (new Ciphertext($algorithm, $ciphertext, $nonce))->getUrlSafeRepresentation();
    }

    public function decrypt(string $message, ?string $privateKey = null, ?string $publicKey = null): string
    {
        $parsedMessage = Ciphertext::parse($message);
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
}
