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
use Symfony\Component\Encryption\Ciphertext;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
abstract class AbstractAsymmetricEncryptionTest extends TestCase
{
    public function testAsymmetricEncryption()
    {
        $cypher = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $cypher->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $cypher->generateKeypair();

        $cipher = $cypher->encrypt('', $alicePublic);
        $this->assertNotEmpty('input', $cipher);
        $this->assertTrue(\strlen($cipher) > 10);
        $this->assertNotEquals('input', $cypher->encrypt('input', $alicePublic));
    }

    public function testAsymmetricEncryptionWithIdentification()
    {
        $cypher = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $cypher->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $cypher->generateKeypair();

        $cipher = $cypher->encrypt('', $bobPublic, $alicePrivate);
        $this->assertNotEmpty('input', $cipher);
        $this->assertTrue(\strlen($cipher) > 10);

        $this->assertNotEquals('input', $cypher->encrypt('input', $bobPublic, $alicePrivate));
    }

    public function testAsymmetricDecryption()
    {
        $cypher = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $cypher->generateKeypair();

        $cipher = $cypher->encrypt($input = 'input', $alicePublic);
        $this->assertEquals($input, $cypher->decrypt($cipher, $alicePrivate));
    }

    public function testAsymmetricDecryptionWithIdentification()
    {
        $cypher = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $cypher->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $cypher->generateKeypair();

        $cipher = $cypher->encrypt($input = 'input', $bobPublic, $alicePrivate);
        $this->assertEquals($input, $cypher->decrypt($cipher, $bobPrivate, $alicePublic));
    }

    public function testAsymmetricDecryptionUnableToVerifySender()
    {
        $cypher = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $cypher->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $cypher->generateKeypair();

        // Test encrypting with no private key
        $cipher = $cypher->encrypt($input = 'input', $bobPublic);
        $this->expectException(DecryptionException::class);
        $this->assertEquals($input, $cypher->decrypt($cipher, $bobPrivate, $alicePublic));
    }

    public function testAsymmetricDecryptionIgnoreToVerifySender()
    {
        $cypher = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $cypher->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $cypher->generateKeypair();

        // Test decrypting with no public key
        $cipher = $cypher->encrypt($input = 'input', $bobPublic, $alicePrivate);
        $this->expectException(SignatureVerificationRequiredException::class);
        $this->assertEquals($input, $cypher->decrypt($cipher, $bobPrivate));
    }

    public function testAsymmetricDecryptionThrowsOnMalformedCipher()
    {
        $cypher = $this->getAsymmetricEncryption();
        $this->expectException(MalformedCipherException::class);
        $cypher->decrypt('foo', 'private', 'public');
    }

    public function testDecryptionThrowsOnUnsupportedAlgorithm()
    {
        $cypher = $this->getAsymmetricEncryption();
        $this->expectException(UnsupportedAlgorithmException::class);
        $cypher->decrypt(Ciphertext::create('foo', 'bar', 'baz')->getString(), 'private', 'public');
    }

    public function testAsymmetricDecryptionThrowsExceptionOnWrongPublicKey()
    {
        $cypher = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $cypher->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $cypher->generateKeypair();
        ['public' => $evePublic, 'private' => $evePrivate] = $cypher->generateKeypair();

        $cipher = $cypher->encrypt('input', $bobPublic, $alicePrivate);
        $this->expectException(DecryptionException::class);
        $cypher->decrypt($cipher, $bobPrivate, $evePublic);
    }

    public function testAsymmetricDecryptionThrowsExceptionOnWrongPrivateKey()
    {
        $cypher = $this->getAsymmetricEncryption();
        ['public' => $alicePublic, 'private' => $alicePrivate] = $cypher->generateKeypair();
        ['public' => $bobPublic, 'private' => $bobPrivate] = $cypher->generateKeypair();
        ['public' => $evePublic, 'private' => $evePrivate] = $cypher->generateKeypair();

        $cipher = $cypher->encrypt('input', $bobPublic, $alicePrivate);
        $this->expectException(DecryptionException::class);
        $cypher->decrypt($cipher, $evePrivate, $alicePublic);
    }

    abstract protected function getAsymmetricEncryption(): AsymmetricEncryptionInterface;
}
