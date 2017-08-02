<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\MessageCatalogue;

@trigger_error(sprintf('The class "%s" has been deprecated. Use "%s" instead. ', self::class, TranslationReader::class), E_USER_DEPRECATED);

/**
 * @deprecated Since 3.4. Use Symfony\Component\Translation\Reader\TranslationReader instead
 */
class TranslationLoader extends TranslationReader
{
    /**
     * Loads translation messages from a directory to the catalogue.
     *
     * @param string           $directory the directory to look into
     * @param MessageCatalogue $catalogue the catalogue
     */
    public function loadMessages($directory, MessageCatalogue $catalogue)
    {
        $this->read($directory, $catalogue);
    }
}
