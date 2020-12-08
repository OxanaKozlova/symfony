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

use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\EncryptionException;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 5.3
 */
interface EncryptionInterface
{
    /**
     * Generates new a Key to be used with encryption.
     *
     * Don't lose your private key and make sure to keep it a secret.
     *
     * @param string|null $secret to be used in symmetric encryption. A new secret
     *                            is generated if none is provided.
     *
     * @throws EncryptionException
     */
    public function generateKey(string $secret = null): KeyInterface;

    /**
     * Get an encrypted version of the message.
     *
     * Symmetric encryption uses the same key to encrypt and decrypt a message.
     * The key should be kept safe and should not be exposed to the public. Symmetric
     * encryption should be used when you are not sending the encrypted message to
     * anyone else.
     *
     * Example: You store a value on disk or in a cookie and don't want anyone else
     * to read it.
     *
     * Symmetric encryption is in theory weaker than asymmetric encryption.
     *
     * <code>
     *     $key = $encryption->generateKey();
     *     $ciphertext = $encryption->encrypt('input', $key);
     *     $message = $encryption->decrypt($ciphertext, $key);
     * </code>
     *
     * @param string       $message plain text version of the message
     * @param KeyInterface $myKey   a key that holds a string secret
     *
     * @return string the output
     *
     * @throws EncryptionException
     */
    public function encrypt(string $message, KeyInterface $myKey): string;

    /**
     * Get an encrypted version of the message that only the recipient can read.
     *
     * Asymmetric encryption uses a "key pair" ie a public key and a private key.
     * It is safe to share your public key, but the private key should always be
     * kept a secret.
     *
     * When Alice and Bob wants to communicate they share their public keys with
     * each other. Alice will encrypt a message with bobs public key. When Bob
     * receive the message, he will decrypt it with his private key.
     *
     *
     * <code>
     *     $bobKey = $encryption->generateKey();
     *     $bobPublicKey = serialize($bobKey->createPublicKey());
     *
     *     // Bob sends the public key to Alice
     *
     *     $key = unserialize($bobPublicKey);
     *     $ciphertext = $encryption->encryptFor('input', $key);
     *
     *     // Alice sends ciphertext to bob
     *
     *     $message = $encryption->decrypt($ciphertext, $bobKey);
     * </code>
     *
     * @param string       $message      plain text version of the message
     * @param KeyInterface $recipientKey A key with a public key of the recipient
     *
     * @return string the output
     *
     * @throws EncryptionException
     */
    public function encryptFor(string $message, KeyInterface $recipientKey): string;

    /**
     * Get an encrypted version of the message that only the recipient can read.
     * The recipient can also verify who sent the message.
     *
     * Asymmetric encryption uses a "key pair" ie a public key and a private key.
     * It is safe to share your public key, but the private key should always be
     * kept a secret.
     *
     * When Alice and Bob wants to communicate they share their public keys with
     * each other. Alice will encrypt a message with keypair [ alice_private, bob_public ].
     * When Bob receive the message, he will decrypt it with keypair [ bob_private, alice_public ].
     *
     * <code>
     *     $aliceKey = $encryption->generateKey();
     *     $bobKey = $encryption->generateKey();
     *     $keypair = $aliceKey->createKeypair($bobKey);
     *
     *     $ciphertext = $encryption->encryptForAndSign('input', $keypair);
     *     $message = $encryption->decrypt($ciphertext, $bobKey->createKeypair($aliceKey));
     * </code>
     *
     * @param string       $message plain text version of the message
     * @param KeyInterface $keypair A key with a public key of the recipient and a private key of the sender
     *
     * @return string the output
     *
     * @throws EncryptionException
     */
    public function encryptForAndSign(string $message, KeyInterface $keypair): string;

    /**
     * Get a plain text version of the encrypted message.
     *
     * @param string $message encrypted version of the message
     *
     * @throws DecryptionException
     */
    public function decrypt(string $message, KeyInterface $key): string;
}
