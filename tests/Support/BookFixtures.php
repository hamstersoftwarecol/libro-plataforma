<?php

namespace Tests\Support;

use Database\Seeders\SamplePdf;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class BookFixtures
{
    /**
     * A valid PDF with the given number of text pages.
     */
    public static function pdf(int $pages = 3): string
    {
        return SamplePdf::make(array_map(fn (int $page): string => "Pagina {$page}", range(1, $pages)));
    }

    /**
     * A valid EPUB with two chapters; the first contains unsafe markup to be sanitized.
     */
    public static function epub(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'epub');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OEBPS/content.opf', '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf" version="3.0"><manifest><item id="c1" href="text/uno.xhtml" media-type="application/xhtml+xml"/><item id="c2" href="text/dos.xhtml" media-type="application/xhtml+xml"/><item id="img" href="images/punto.png" media-type="image/png"/></manifest><spine><itemref idref="c1"/><itemref idref="c2"/></spine></package>');
        $zip->addFromString('OEBPS/text/uno.xhtml', '<html><head><script>alert(1)</script></head><body><h1 onclick="steal()">Capítulo uno</h1><p style="color:red">Había una vez.</p><a href="https://malo.example">enlace</a><img src="../images/punto.png"/><iframe src="https://malo.example"></iframe></body></html>');
        $zip->addFromString('OEBPS/text/dos.xhtml', '<html><body><h1>Capítulo dos</h1><p>Fin.</p></body></html>');
        $zip->addFromString('OEBPS/images/punto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
        $zip->close();

        $contents = file_get_contents($path);
        unlink($path);

        return $contents;
    }

    public static function pdfUpload(int $pages = 3, string $name = 'libro.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::pdf($pages));
    }

    public static function epubUpload(string $name = 'libro.epub'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::epub());
    }
}
