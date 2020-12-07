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
        $cypher = $this->getSymmetricEncryption();
        $cipher = $cypher->encrypt('');
        $this->assertNotEmpty('input', $cipher);
        $this->assertTrue(\strlen($cipher) > 10);
        $this->assertNotEquals('input', $cypher->encrypt('input'));

        $cipher = $cypher->encrypt($input = 'random_string');
        $cypher = $this->getSymmetricEncryption();
        $this->assertNotEquals($cipher, $cypher->encrypt($input));
    }

    public function testDecryption()
    {
        $cypher = $this->getSymmetricEncryption();

        $this->assertEquals($input = '', $cypher->decrypt($cypher->encrypt($input)));
        $this->assertEquals($input = 'foobar', $cypher->decrypt($cypher->encrypt($input)));
    }

    public function testDecryptionThrowsOnMalformedCipher()
    {
        $cypher = $this->getSymmetricEncryption();
        $this->expectException(MalformedCipherException::class);
        $cypher->decrypt('foo');
    }

    public function testDecryptionThrowsOnUnsupportedAlgorithm()
    {
        $cypher = $this->getSymmetricEncryption();
        $this->expectException(UnsupportedAlgorithmException::class);
        $cypher->decrypt(JWE::create('foo', 'xx', 'yy', function($x) {return $x;}, 'bix')->getString());
    }

    abstract protected function getSymmetricEncryption(): SymmetricEncryptionInterface;
}
