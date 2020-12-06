<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests\Encryption\Provider;

use Symfony\Component\Encryption\AsymmetricEncryptionInterface;
use Symfony\Component\Encryption\Provider\SodiumEncryption;
use Symfony\Component\Encryption\Tests\AbstractAsymmetricEncryptionTest;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class SodiumAsymmetricEncryptionTest extends AbstractAsymmetricEncryptionTest
{
    protected function getAsymmetricEncryption(): AsymmetricEncryptionInterface
    {
        if (!\function_exists('sodium_crypto_box_keypair')) {
            $this->markTestSkipped('Sodium extension is not installed and enabled.');
        }

        return new SodiumEncryption('s3cr3t'.random_bytes(10));
    }
}
