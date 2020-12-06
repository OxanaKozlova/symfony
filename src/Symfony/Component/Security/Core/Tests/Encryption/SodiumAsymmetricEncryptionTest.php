<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Encryption;

use Symfony\Component\Security\Core\Encryption\AsymmetricEncryptionInterface;
use Symfony\Component\Security\Core\Encryption\SodiumEncryption;

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

        return new SodiumEncryption('s3cr3t');
    }
}
