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
 * A Key for a specific user and specific Encryption implementation. Keys cannot
 * be shared between Encryption implementations.
 *
 * A Key is always serializable.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 5.3
 */
interface KeyInterface
{
    /**
     * Returns a string to be stored in a safe place
     */
    public function toString(): string;

    /**
     * Creates a Key from stored data
     */
    public function fromString(string $string): self;

    /**
     * Get the public key from this Key. Not all Keys have a public key.
     *
     * The public key can be shared.
     */
    public function getPublicKey(): ?string;
}
