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
use Symfony\Component\Encryption\AsymmetricEncryptionInterface;
use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\MalformedCipherException;
use Symfony\Component\Encryption\Exception\SignatureVerificationRequiredException;
use Symfony\Component\Encryption\Exception\UnsupportedAlgorithmException;
use Symfony\Component\Encryption\JWE;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
abstract class AbstractAsymmetricEncryptionTest extends TestCase
{
    public function testAsymmetricEncryption()
    {
        $sodium = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();

        $cipher = $sodium->encrypt('', $alicePublic);
        $this->assertNotEmpty('input', $cipher);
        $this->assertTrue(\strlen($cipher) > 10);
        $this->assertNotEquals('input', $sodium->encrypt('input', $alicePublic));
    }

    public function testAsymmetricEncryptionWithIdentification()
    {
        $sodium = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();

        $cipher = $sodium->encrypt('', $bobPublic, $alicePrivate);
        $this->assertNotEmpty('input', $cipher);
        $this->assertTrue(\strlen($cipher) > 10);

        $this->assertNotEquals('input', $sodium->encrypt('input', $bobPublic, $alicePrivate));
    }

    public function testAsymmetricDecryption()
    {
        $sodium = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();

        $cipher = $sodium->encrypt($input = 'input', $alicePublic);
        $this->assertEquals($input, $sodium->decrypt($cipher, $alicePrivate));
    }

    public function testAsymmetricDecryptionWithIdentification()
    {
        $sodium = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();

        $cipher = $sodium->encrypt($input = 'input', $bobPublic, $alicePrivate);
        $this->assertEquals($input, $sodium->decrypt($cipher, $bobPrivate, $alicePublic));
    }

    public function testAsymmetricDecryptionUnableToVerifySender()
    {
        $sodium = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();

        // Test encrypting with no private key
        $cipher = $sodium->encrypt($input = 'input', $bobPublic);
        $this->expectException(DecryptionException::class);
        $this->assertEquals($input, $sodium->decrypt($cipher, $bobPrivate, $alicePublic));
    }

    public function testAsymmetricDecryptionIgnoreToVerifySender()
    {
        $sodium = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();

        // Test decrypting with no public key
        $cipher = $sodium->encrypt($input = 'input', $bobPublic, $alicePrivate);
        $this->expectException(SignatureVerificationRequiredException::class);
        $this->assertEquals($input, $sodium->decrypt($cipher, $bobPrivate));
    }

    public function testAsymmetricDecryptionThrowsOnMalformedCipher()
    {
        $sodium = $this->getAsymmetricEncryption();
        $this->expectException(MalformedCipherException::class);
        $sodium->decrypt('foo', 'private', 'public');
    }

    public function testDecryptionThrowsOnUnsupportedAlgorithm()
    {
        $sodium = $this->getAsymmetricEncryption();
        $this->expectException(UnsupportedAlgorithmException::class);
        $sodium->decrypt(JWE::create('foo', 'xx', 'yy', function($x) {return $x;}, 'bix')->getString(), 'private', 'public');
    }

    public function testAsymmetricDecryptionThrowsExceptionOnWrongPublicKey()
    {
        $sodium = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();
        ['public' => $evePublic, 'private' => $evePrivate] = $sodium->generateKeypair();

        $cipher = $sodium->encrypt('input', $bobPublic, $alicePrivate);
        $this->expectException(DecryptionException::class);
        $sodium->decrypt($cipher, $bobPrivate, $evePublic);
    }

    public function testAsymmetricDecryptionThrowsExceptionOnWrongPrivateKey()
    {
        $sodium = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $sodium->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $sodium->generateKeypair();
        ['public' => $evePublic, 'private' => $evePrivate] = $sodium->generateKeypair();

        $cipher = $sodium->encrypt('input', $bobPublic, $alicePrivate);
        $this->expectException(DecryptionException::class);
        $sodium->decrypt($cipher, $evePrivate, $alicePublic);
    }

    abstract protected function getAsymmetricEncryption(): AsymmetricEncryptionInterface;
}
