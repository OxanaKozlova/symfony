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

use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Extractor\AbstractFileExtractor;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Extractor\ExtractorInterface;

/**
 * @deprecated Will be removed in 4.0.
 */
class PhpExtractor extends \Symfony\Component\Translation\Extractor\PhpExtractor
{
    public function __construct()
    {
        @trigger_error(sprintf('The class "%s" is deprecated since Symfony 3.4 and will be removed in 4.0, use "%s" instead', PhpExtractor::class, \Symfony\Component\Translation\Extractor\PhpExtractor::class), E_USER_DEPRECATED);
    }
}
