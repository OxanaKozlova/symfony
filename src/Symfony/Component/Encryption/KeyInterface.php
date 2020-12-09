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

/**
 * A key for a specific user and specific Encryption implementation. Keys cannot
 * be shared between Encryption implementations.
 *
 * A key is always serializable.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 5.3
 */
interface KeyInterface extends \Serializable
{
    /**
     * Creates a new KeyInterface object.
     *
     * When Alice wants to send and sign a message to Bob. She takes her private
     * key and pair it with Bob's public key.
     *
     * <code>
     *     $aliceKey = $encryption->generateKey();
     *     $bobKey = $encryption->generateKey();
     *     $keypair = $aliceKey->createKeypair($bobKey);
     * </code>
     */
    public function createKeypair(self $publicKey): self;

    /**
     * Creates a new KeyInterface object.
     *
     * When Alice wants share her public key with Bob, she sends him this object.
     *
     * The public key can be shared.
     */
    public function createPublicKey(): self;
}
