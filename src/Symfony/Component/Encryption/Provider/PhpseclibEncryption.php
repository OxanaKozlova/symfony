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
        $cek = Random::string(32);
        $headers = [];

        try {
            $encAlgorithm = 'A128CBC-HS256';
            $aes = new AES();
            $aes->setKey($cek);
            $initializationVector = Random::string($aes->getBlockLength() >> 3);
            $cipher = function ($aad) use ($message, $aes, $initializationVector) {
                $aes->setIV($initializationVector.$aad);
                return $aes->encrypt($message);
            };

            if (null === $publicKey && null === $privateKey) {
                $aes = new AES(); // could use AES::MODE_CBC
                $aes->setPassword($this->secret);
                $aes->setIV($nonce = Random::string($aes->getBlockLength() >> 3));
                $headers['com.symfony.extra_nonce']=base64_encode($nonce);
                $encryptedCek = $aes->encrypt($cek);

                return JWE::create('RSAES-PKCS1-v1_5', $encryptedCek, $encAlgorithm, $cipher, $initializationVector, $headers)->getString();
            }

            // Asymmetric encryption
            $rsa = new RSA();
            $rsa->loadKey($publicKey);
            $rsa->setEncryptionMode(RSA::ENCRYPTION_OAEP);
            $encryptedCek = $rsa->encrypt($cek);

            if ($privateKey !== null) {
                // Load private key after encryption
                $rsa->loadKey($privateKey);
                $rsa->setSignatureMode(RSA::SIGNATURE_PSS);
                $headers['com.symfony.signature.pss'] = base64_encode($rsa->sign($encryptedCek));
            }

            return JWE::create('RSA-OAEP', $encryptedCek, 'A128CBC-HS256', $cipher, $initializationVector, $headers)->getString();
        } catch (\ErrorException $exception) {
            throw new EncryptionException(null, $exception);
        } finally {
            restore_error_handler();
        }
    }

    public function decrypt(string $message, ?string $privateKey = null, ?string $publicKey = null): string
    {
        $jwe = JWE::parse($message);
        $algorithm = $jwe->getAlgorithm();
        $encryptedCek = $jwe->getEncryptedCek();

        set_error_handler(__CLASS__.'::throwError');
        try {
            if ('RSAES-PKCS1-v1_5' === $algorithm) {
                 $aes = new AES();
                $aes->setPassword($this->secret);
                $aes->setIV(base64_decode($jwe->getHeader('com.symfony.extra_nonce')));
                $cek = $aes->decrypt($encryptedCek);
            } elseif ('RSA-OAEP' === $algorithm) {
                $rsa = new RSA();
                if ($jwe->hasHeader('com.symfony.signature.pss')) {
                    if (null === $publicKey) {
                        throw new SignatureVerificationRequiredException();
                    }

                    $rsa->loadKey($publicKey);
                    $verify = $rsa->verify($encryptedCek, base64_decode($jwe->getHeader('com.symfony.signature.pss')));
                    if (!$verify) {
                        throw new UnableToVerifySignatureException();
                    }
                } elseif ($publicKey !== null) {
                    throw new UnableToVerifySignatureException();
                }

                $rsa->loadKey($privateKey);
                // $rsa->setEncryptionMode(RSA::ENCRYPTION_OAEP);
                $cek = $rsa->decrypt($encryptedCek);
            } else {
                throw new UnsupportedAlgorithmException($algorithm);
            }

            // Let's decrypt the ciphertext using cek
            $encAlgorithm = $jwe->getEncryptionAlgorithm();
            $ciphertext = $jwe->getCiphertext();
            if ($encAlgorithm === 'A128CBC-HS256') {
                $aes = new AES();
                $aes->setKey($cek);
                $aes->setIV($jwe->getInitializationVector().$jwe->getAdditionalAuthenticationData());
                $output =  $aes->decrypt($ciphertext);
            } else {
                throw new UnsupportedAlgorithmException($encAlgorithm);
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
