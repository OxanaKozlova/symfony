<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Util;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * Extract translation data from XLIFF content.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class XliffExtractor
{
    /**
     * Extract messages and metadata from DOMDocument into a MessageCatalogue.
     *
     * @param \DOMDocument     $dom       Source to extract messages and metadata
     * @param MessageCatalogue $catalogue Catalogue where we'll collect messages and metadata
     * @param string           $domain    The domain
     */
    public function extractXliff1(\DOMDocument $dom, MessageCatalogue $catalogue, string $domain)
    {
        $xml = simplexml_import_dom($dom);
        $encoding = strtoupper($dom->encoding);

        $xml->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');
        foreach ($xml->xpath('//xliff:trans-unit') as $translation) {
            $attributes = $translation->attributes();

            if (!(isset($attributes['resname']) || isset($translation->source))) {
                continue;
            }

            $source = isset($attributes['resname']) && $attributes['resname'] ? $attributes['resname'] : $translation->source;
            // If the xlf file has another encoding specified, try to convert it because
            // simple_xml will always return utf-8 encoded values
            $target = $this->utf8ToCharset((string) (isset($translation->target) ? $translation->target : $source), $encoding);

            $catalogue->set((string) $source, $target, $domain);

            $metadata = array();
            if ($notes = $this->parseNotesMetadata($translation->note, $encoding)) {
                $metadata['notes'] = $notes;
            }

            if (isset($translation->target) && $translation->target->attributes()) {
                $metadata['target-attributes'] = array();
                foreach ($translation->target->attributes() as $key => $value) {
                    $metadata['target-attributes'][$key] = (string) $value;
                }
            }

            if (isset($attributes['id'])) {
                $metadata['id'] = (string) $attributes['id'];
            }

            $catalogue->setMetadata((string) $source, $metadata, $domain);
        }
    }

    /**
     * Extract messages and metadata from DOMDocument into a MessageCatalogue.
     *
     * @param \DOMDocument     $dom       Source to extract messages and metadata
     * @param MessageCatalogue $catalogue Catalogue where we'll collect messages and metadata
     * @param string           $domain    The domain
     */
    public function extractXliff2(\DOMDocument $dom, MessageCatalogue $catalogue, string $domain)
    {
        $xml = simplexml_import_dom($dom);
        $encoding = strtoupper($dom->encoding);

        $xml->registerXPathNamespace('xliff', 'urn:oasis:names:tc:xliff:document:2.0');

        foreach ($xml->xpath('//xliff:unit') as $unit) {
            foreach ($unit->segment as $segment) {
                $source = $segment->source;

                // If the xlf file has another encoding specified, try to convert it because
                // simple_xml will always return utf-8 encoded values
                $target = $this->utf8ToCharset((string) (isset($segment->target) ? $segment->target : $source), $encoding);

                $catalogue->set((string) $source, $target, $domain);

                $metadata = array();
                if (isset($segment->target) && $segment->target->attributes()) {
                    $metadata['target-attributes'] = array();
                    foreach ($segment->target->attributes() as $key => $value) {
                        $metadata['target-attributes'][$key] = (string) $value;
                    }
                }

                if (isset($unit->notes)) {
                    $metadata['notes'] = array();
                    foreach ($unit->notes->note as $noteNode) {
                        $note = array();
                        foreach ($noteNode->attributes() as $key => $value) {
                            $note[$key] = (string) $value;
                        }
                        $note['content'] = (string) $noteNode;
                        $metadata['notes'][] = $note;
                    }
                }

                $catalogue->setMetadata((string) $source, $metadata, $domain);
            }
        }
    }

    /**
     * Convert a UTF8 string to the specified encoding.
     */
    private function utf8ToCharset(string $content, string $encoding = null): string
    {
        if ('UTF-8' !== $encoding && !empty($encoding)) {
            return mb_convert_encoding($content, $encoding, 'UTF-8');
        }

        return $content;
    }

    private function parseNotesMetadata(\SimpleXMLElement $noteElement = null, string $encoding = null): array
    {
        $notes = array();

        if (null === $noteElement) {
            return $notes;
        }

        /** @var \SimpleXMLElement $xmlNote */
        foreach ($noteElement as $xmlNote) {
            $noteAttributes = $xmlNote->attributes();
            $note = array('content' => $this->utf8ToCharset((string) $xmlNote, $encoding));
            if (isset($noteAttributes['priority'])) {
                $note['priority'] = (int) $noteAttributes['priority'];
            }

            if (isset($noteAttributes['from'])) {
                $note['from'] = (string) $noteAttributes['from'];
            }

            $notes[] = $note;
        }

        return $notes;
    }
}
