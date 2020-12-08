<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use phpseclib\Crypt\AES;
use Symfony\Component\Encryption\EncryptionInterface;
use Symfony\Component\Encryption\Phpseclib\PhpseclibEncryption;
use Symfony\Component\Encryption\Sodium\SodiumEncryption;

return static function (ContainerConfigurator $container) {
    $sodiumInstalled = \function_exists('sodium_crypto_box_keypair');
    $phpseclibInstalled = class_exists(AES::class);

    $container->services()

        ->set('security.encryption.sodium', SodiumEncryption::class)
            ->args([
                '%kernel.secret%',
            ])
        ->set('security.encryption.phpseclib', PhpseclibEncryption::class)
            ->args([
                '%kernel.secret%',
            ])
        ->alias(EncryptionInterface::class, $phpseclibInstalled && !$sodiumInstalled ? 'security.encryption.phpseclib' : 'security.encryption.sodium')
        ;
};
