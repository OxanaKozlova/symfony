<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Dumper;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Util\XliffDumper;

/**
 * XliffFileDumper generates xliff files from a message catalogue.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 */
class XliffFileDumper extends FileDumper
{
    private $dumper;

    public function __construct(XliffDumper $dumper)
    {
        $this->dumper = $dumper;
    }

    /**
     * {@inheritdoc}
     */
    public function formatCatalogue(MessageCatalogue $messages, $domain, array $options = array())
    {
        $xliffVersion = '1.2';
        if (array_key_exists('xliff_version', $options)) {
            $xliffVersion = $options['xliff_version'];
        }

        if (array_key_exists('default_locale', $options)) {
            $defaultLocale = $options['default_locale'];
        } else {
            $defaultLocale = \Locale::getDefault();
        }

        if ('1.2' === $xliffVersion) {
            return $this->dumper->dumpXliff1($defaultLocale, $messages, $domain, $options);
        }
        if ('2.0' === $xliffVersion) {
            return $this->dumper->dumpXliff2($defaultLocale, $messages, $domain, $options);
        }

        throw new InvalidArgumentException(sprintf('No support implemented for dumping XLIFF version "%s".', $xliffVersion));
    }

    /**
     * {@inheritdoc}
     */
    protected function getExtension()
    {
        return 'xlf';
    }
}
