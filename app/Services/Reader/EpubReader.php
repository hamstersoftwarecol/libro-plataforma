<?php

namespace App\Services\Reader;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Extracts and sanitizes EPUB chapters so they can be shown inside the web reader (HU-011).
 */
class EpubReader
{
    private const REMOVED_TAGS = ['script', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'link', 'meta', 'style', 'base', 'audio', 'video', 'source'];

    private const IMAGE_TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];

    /**
     * Chapter paths inside the archive, in reading order.
     *
     * @return list<string>
     */
    public function chapters(string $path): array
    {
        $zip = $this->open($path);

        try {
            $container = $this->xml($zip->getFromName('META-INF/container.xml'));
            $opfPath = $container->query('//*[local-name()="rootfile"]/@full-path')->item(0)?->nodeValue;

            if (! $opfPath) {
                throw new RuntimeException('EPUB sin archivo OPF.');
            }

            $opf = $this->xml($zip->getFromName($opfPath));
            $baseDir = $this->directory($opfPath);

            $manifest = [];
            foreach ($opf->query('//*[local-name()="manifest"]/*[local-name()="item"]') as $item) {
                /** @var DOMElement $item */
                $manifest[$item->getAttribute('id')] = $item->getAttribute('href');
            }

            $chapters = [];
            foreach ($opf->query('//*[local-name()="spine"]/*[local-name()="itemref"]') as $itemRef) {
                /** @var DOMElement $itemRef */
                $href = $manifest[$itemRef->getAttribute('idref')] ?? null;

                if ($href) {
                    $chapters[] = $this->normalize($baseDir.rawurldecode($href));
                }
            }

            if ($chapters === []) {
                throw new RuntimeException('EPUB sin capítulos.');
            }

            return $chapters;
        } finally {
            $zip->close();
        }
    }

    /**
     * Sanitized HTML body of a chapter with images inlined as data URIs.
     */
    public function chapterHtml(string $path, string $chapterPath): string
    {
        $zip = $this->open($path);

        try {
            $source = $zip->getFromName($chapterPath);

            if ($source === false) {
                throw new RuntimeException('Capítulo no encontrado.');
            }

            $document = new DOMDocument;
            libxml_use_internal_errors(true);
            $document->loadHTML('<?xml encoding="UTF-8">'.$source, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();

            $xpath = new DOMXPath($document);

            foreach (self::REMOVED_TAGS as $tag) {
                foreach (iterator_to_array($xpath->query('//'.$tag)) as $node) {
                    $node->parentNode->removeChild($node);
                }
            }

            foreach (iterator_to_array($xpath->query('//*')) as $element) {
                /** @var DOMElement $element */
                foreach (iterator_to_array($element->attributes) as $attribute) {
                    $name = strtolower($attribute->nodeName);
                    $value = trim($attribute->nodeValue);

                    if (str_starts_with($name, 'on') || $name === 'style' || $name === 'srcset'
                        || ($name === 'href' && ! str_starts_with($value, '#'))) {
                        $element->removeAttribute($attribute->nodeName);
                    }
                }
            }

            $chapterDir = $this->directory($chapterPath);

            foreach (iterator_to_array($xpath->query('//img | //*[local-name()="image"]')) as $image) {
                /** @var DOMElement $image */
                $attribute = $image->hasAttribute('src') ? 'src' : 'xlink:href';
                $source = $image->getAttribute($attribute) ?: $image->getAttribute('href');
                $dataUri = $this->imageDataUri($zip, $this->normalize($chapterDir.rawurldecode($source)));

                $dataUri ? $image->setAttribute($attribute === 'xlink:href' ? 'href' : 'src', $dataUri) : $image->parentNode->removeChild($image);
            }

            $body = $document->getElementsByTagName('body')->item(0);
            $html = '';

            foreach ($body?->childNodes ?? [] as $child) {
                $html .= $document->saveHTML($child);
            }

            return $html;
        } finally {
            $zip->close();
        }
    }

    private function imageDataUri(ZipArchive $zip, string $imagePath): ?string
    {
        $type = self::IMAGE_TYPES[strtolower(pathinfo($imagePath, PATHINFO_EXTENSION))] ?? null;
        $contents = $type ? $zip->getFromName($imagePath) : false;

        if ($contents === false || $type === 'image/svg+xml') {
            return null;
        }

        return 'data:'.$type.';base64,'.base64_encode($contents);
    }

    private function open(string $path): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('No se pudo abrir el EPUB.');
        }

        return $zip;
    }

    private function xml(string|false $contents): DOMXPath
    {
        $document = new DOMDocument;

        if ($contents === false || ! $document->loadXML($contents, LIBXML_NONET)) {
            throw new RuntimeException('EPUB con XML inválido.');
        }

        return new DOMXPath($document);
    }

    private function directory(string $path): string
    {
        $directory = dirname($path);

        return $directory === '.' ? '' : $directory.'/';
    }

    /**
     * Resolve "." and ".." segments and drop fragments.
     */
    private function normalize(string $path): string
    {
        $segments = [];

        foreach (explode('/', strtok($path, '#')) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.' && $segment !== '') {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments);
    }
}
