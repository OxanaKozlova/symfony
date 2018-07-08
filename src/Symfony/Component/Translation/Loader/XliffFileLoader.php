<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Translation\Util\XliffExtractor;
use Symfony\Component\Translation\Util\XliffUtils;

/**
 * XliffFileLoader loads translations from XLIFF files.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class XliffFileLoader implements LoaderInterface
{
    private $extractor;

    public function __construct(XliffExtractor $extractor)
    {
        $this->extractor = $extractor;
    }

    /**
     * {@inheritdoc}
     */
    public function load($resource, $locale, $domain = 'messages')
    {
        if (!stream_is_local($resource)) {
            throw new InvalidResourceException(sprintf('This is not a local file "%s".', $resource));
        }

        if (!file_exists($resource)) {
            throw new NotFoundResourceException(sprintf('File "%s" not found.', $resource));
        }

        $catalogue = new MessageCatalogue($locale);
        $this->extract($resource, $catalogue, $domain);

        if (class_exists('Symfony\Component\Config\Resource\FileResource')) {
            $catalogue->addResource(new FileResource($resource));
        }

        return $catalogue;
    }

    private function extract($resource, MessageCatalogue $catalogue, $domain)
    {
        try {
            $dom = XmlUtils::loadFile($resource);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidResourceException(sprintf('Unable to load "%s": %s', $resource, $e->getMessage()), $e->getCode(), $e);
        }

        $xliffVersion = XliffUtils::getVersionNumber($dom);
        if ($errors = XliffUtils::validateSchema($dom)) {
            throw new InvalidResourceException(sprintf('Invalid resource provided: "%s"; Errors: %s', $xliffVersion, XliffUtils::getErrorsAsString($errors)));
        }

        if ('1.2' === $xliffVersion) {
            $this->extractor->extractXliff1($dom, $catalogue, $domain);
        }

        if ('2.0' === $xliffVersion) {
            $this->extractor->extractXliff2($dom, $catalogue, $domain);
        }
    }
}
