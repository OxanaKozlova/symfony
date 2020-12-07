<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Exception\MalformedCipherException;
use Symfony\Component\Encryption\Exception\UnsupportedAlgorithmException;
use Symfony\Component\Encryption\JWE;
use Symfony\Component\Encryption\SymmetricEncryptionInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
abstract class AbstractSymmetricEncryptionTest extends TestCase
{
    public function testEncryption()
    {
        $sodium = $this->getSymmetricEncryption();
        $cipher = $sodium->encrypt('');
        $this->assertNotEmpty('input', $cipher);
        $this->assertTrue(\strlen($cipher) > 10);
        $this->assertNotEquals('input', $sodium->encrypt('input'));

        $cipher = $sodium->encrypt($input = 'random_string');
        $sodium = $this->getSymmetricEncryption();
        $this->assertNotEquals($cipher, $sodium->encrypt($input));
    }

    public function testDecryption()
    {
        $sodium = $this->getSymmetricEncryption();

        $this->assertEquals($input = '', $sodium->decrypt($sodium->encrypt($input)));
        $this->assertEquals($input = 'foobar', $sodium->decrypt($sodium->encrypt($input)));
    }

    public function testDecryptionThrowsOnMalformedCipher()
    {
        $sodium = $this->getSymmetricEncryption();
        $this->expectException(MalformedCipherException::class);
        $sodium->decrypt('foo');
    }

    public function testDecryptionThrowsOnUnsupportedAlgorithm()
    {
        $sodium = $this->getSymmetricEncryption();
        $this->expectException(UnsupportedAlgorithmException::class);
        $sodium->decrypt((new JWE('foo', 'xx', 'yy'))->getString());
    }

    abstract protected function getSymmetricEncryption(): SymmetricEncryptionInterface;
}
