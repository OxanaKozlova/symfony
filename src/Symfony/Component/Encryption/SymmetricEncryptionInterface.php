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
 * Symmetric encryption uses the same key to encrypt and decrypt a message. The
 * key should be kept safe and should not be exposed to the public. Symmetric
 * encryption should be used when you are not sending the encrypted message to
 * anyone else.
 *
 * Example: You store a value on disk and don't want anyone else to read it.
 *
 * Symmetric encryption is in theory weaker than asymmetric encryption.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 5.3
 */
interface SymmetricEncryptionInterface
{
    /**
     * Get an encrypted version of the message.
     *
     * @param string $message plain text version of the message
     *
     * @return string the output will be formatted according to JWE (RFC 7516)
     *
     * @throws EncryptionException
     */
    public function encrypt(string $message): string;

    /**
     * Get a plain text version of the encrypted message.
     *
     * @param string $message encrypted version of the message
     *
     * @throws DecryptionException
     */
    public function decrypt(string $message): string;
}
