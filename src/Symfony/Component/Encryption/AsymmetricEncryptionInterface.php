<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption;

use Symfony\Component\Encryption\Exception\EncryptionException;
use Symfony\Component\Encryption\Exception\SignatureVerificationRequiredException;
use Symfony\Component\Encryption\Exception\UnableToVerifySignatureException;

/**
 * Asymmetric encryption uses a "key pair" ie a public key and a private key. It
 * is safe to share your public key but the private key should always be kept a
 * secret.
 *
 * When Alice and Bob wants to communicate thay share the public keys with each
 * other. Alic will encrypt message with keypair [ alice_private, bob_public ].
 * When Bob receive the message, he will decrypt it with keypair [ bob_private, alice_public ].
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 5.3
 */
interface AsymmetricEncryptionInterface
{
    /**
     * Generates new a keypair to be used with encryption.
     *
     * Don't lose your private key and make sure to keep it a secret.
     *
     * @return array{public: string, private: string}
     *
     * @throws EncryptionException
     */
    public function generateKeypair(): array;

    /**
     * Get an encrypted version of the message.
     *
     * When Alice wants to send a secret message to Bob. This function encrypt the
     * message so only Bob can see it.
     *
     * @param string      $message    plain text version of the message
     * @param string      $publicKey  Bob's public key
     * @param string|null $privateKey Alice's private key. If a private key is provided, Bob is forced to verify that the message comes from Alice.
     *
     * @return string the output will be formatted according to JWE (RFC 7516)
     *
     * @throws EncryptionException
     */
    public function encrypt(string $message, string $publicKey, ?string $privateKey = null): string;

    /**
     * Get a plain text version of the encrypted message.
     *
     * When Bob gets a secret message from Alice. This function decrypt the message.
     *
     * @param string      $message    encrypted version of the message
     * @param string      $privateKey Bob's private key
     * @param string|null $publicKey  Alice's public key. If a public key is provided, Bob will be sure the message comes from Alice.
     *
     * @throws EncryptionException
     * @throws UnableToVerifySignatureException       either it was the wrong sender/receiver, or the message was tampered with
     * @throws SignatureVerificationRequiredException thrown when you passed null as public key but the public key is needed
     */
    public function decrypt(string $message, string $privateKey, ?string $publicKey = null): string;
}
