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

use phpseclib\Crypt\AES;
use Symfony\Component\Security\Core\Encryption\AsymmetricEncryptionInterface;
use Symfony\Component\Security\Core\Encryption\PhpseclibEncryption;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class PhpseclibAsymmetricEncryptionTest extends AbstractAsymmetricEncryptionTest
{
    protected function getAsymmetricEncryption(): AsymmetricEncryptionInterface
    {
        if (!class_exists(AES::class)) {
            $this->markTestSkipped('Package phpseclib/phpseclib is not installed.');
        }

        return new PhpseclibEncryption('s3cr3t');
    }
}
