<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Reader;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * TranslationReader reads translation messages from translation files.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 */
interface TranslationReaderInterface
{
    /**
     * Reads translation messages from a directory to the catalogue.
     *
     * @param string           $directory the directory to look into
     * @param MessageCatalogue $catalogue the catalogue
     */
    public function read($directory, MessageCatalogue $catalogue);
}
