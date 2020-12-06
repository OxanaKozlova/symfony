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
use Symfony\Component\Security\Core\Encryption\PhpseclibEncryption;
use Symfony\Component\Security\Core\Encryption\SymmetricEncryptionInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class PhpseclibSymmetricEncryptionTest extends AbstractSymmetricEncryptionTest
{
    protected function getSymmetricEncryption(): SymmetricEncryptionInterface
    {
        if (!class_exists(AES::class)) {
            $this->markTestSkipped('Package phpseclib/phpseclib is not installed.');
        }

        return new PhpseclibEncryption('s3cr3t');
    }
}
