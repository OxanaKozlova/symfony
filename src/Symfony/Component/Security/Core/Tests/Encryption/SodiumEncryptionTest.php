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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Encryption\SodiumEncryption;
use Symfony\Component\Security\Core\Exception\MalformedCipherException;
use Symfony\Component\Security\Core\Exception\UnsupportedAlgorithmException;
use Symfony\Component\Security\Core\Exception\WrongEncryptionKeyException;

class SodiumEncryptionTest extends TestCase
{
    public function testEncryption()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        $cipher = $sodium->encrypt('');
        $this->assertNotEmpty('input', $cipher);
        $this->assertTrue(\strlen($cipher) > 10);
        $this->assertNotEquals('input', $sodium->encrypt('input'));

        $cipher = $sodium->encrypt($input = 'random_string');
        $sodium = new SodiumEncryption('different_secret');
        $this->assertNotEquals($cipher, $sodium->encrypt($input));
    }

    public function testAsymmetricEncryption()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();

        $cipher = $sodium->encrypt('', $alicePublic);
        $this->assertNotEmpty('input', $cipher);
        $this->assertTrue(\strlen($cipher) > 10);
        $this->assertNotEquals('input', $sodium->encrypt('input', $alicePublic));
    }

    public function testAsymmetricEncryptionWithIdentification()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();

        $cipher = $sodium->encrypt('', $bobPublic, $alicePrivate);
        $this->assertNotEmpty('input', $cipher);
        $this->assertTrue(\strlen($cipher) > 10);

        $this->assertNotEquals('input', $sodium->encrypt('input', $bobPublic, $alicePrivate));
    }

    public function testDecryption()
    {
        $sodium = new SodiumEncryption('s3cr3t');

        $this->assertEquals($input = '', $sodium->decrypt($sodium->encrypt($input)));
        $this->assertEquals($input = 'foobar', $sodium->decrypt($sodium->encrypt($input)));
    }

    public function testAsymmetricDecryption()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();

        $cipher = $sodium->encrypt($input = 'input', $alicePublic);
        $this->assertEquals($input, $sodium->decrypt($cipher, $alicePrivate));
    }

    public function testAsymmetricDecryptionWithIdentification()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();

        $cipher = $sodium->encrypt($input = 'input', $bobPublic, $alicePrivate);
        $this->assertEquals($input, $sodium->decrypt($cipher, $bobPrivate, $alicePublic));
    }

    public function testAsymmetricDecryptionUnableToVerifySender()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();

        // Test encrypting with no private key
        $cipher = $sodium->encrypt($input = 'input', $bobPublic);
        $this->expectException(WrongEncryptionKeyException::class);
        $this->assertEquals($input, $sodium->decrypt($cipher, $bobPrivate, $alicePublic));
    }

    public function testAsymmetricDecryptionIgnoreToVerifySender()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();

        // Test decrypting with no public key
        $cipher = $sodium->encrypt($input = 'input', $bobPublic, $alicePrivate);
        $this->expectException(WrongEncryptionKeyException::class);
        $this->assertEquals($input, $sodium->decrypt($cipher, $bobPrivate));
    }

    public function testDecryptionThrowsOnMalformedCipher()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        $this->expectException(MalformedCipherException::class);
        $sodium->decrypt('foo');
    }

    public function testAsymmetricDecryptionThrowsOnMalformedCipher()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        $this->expectException(MalformedCipherException::class);
        $sodium->decrypt('foo', 'private', 'public');
    }

    public function testDecryptionThrowsOnUnsupportedAlgorithm()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        $this->expectException(UnsupportedAlgorithmException::class);
        $sodium->decrypt('foo.bar.baz');
    }

    public function testAsymmetricDecryptionThrowsExceptionOnWrongPublicKey()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();
        ['public' => $evePublic, 'private' => $evePrivate] = $sodium->generateKeypair();

        $cipher = $sodium->encrypt('input', $bobPublic, $alicePrivate);
        $this->expectException(WrongEncryptionKeyException::class);
        $sodium->decrypt($cipher, $bobPrivate, $evePublic);
    }

    public function testAsymmetricDecryptionThrowsExceptionOnWrongPrivateKey()
    {
        $sodium = new SodiumEncryption('s3cr3t');
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();
        ['public' => $evePublic, 'private' => $evePrivate] = $sodium->generateKeypair();

        $cipher = $sodium->encrypt('input', $bobPublic, $alicePrivate);
        $this->expectException(WrongEncryptionKeyException::class);
        $sodium->decrypt($cipher, $evePrivate, $alicePublic);
    }
}
