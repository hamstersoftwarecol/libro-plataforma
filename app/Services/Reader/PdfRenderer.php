<?php

namespace App\Services\Reader;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Renders PDF pages to images on the server so the original file never reaches the browser (HU-011).
 */
class PdfRenderer
{
    public function pageCount(string $path): int
    {
        $binary = $this->resolveBinary('pdfinfo_path', 'pdfinfo');
        $result = Process::timeout(30)->run([$binary, $path]);

        if ($result->successful() && preg_match('/^Pages:\s+(\d+)/m', $result->output(), $matches)) {
            return (int) $matches[1];
        }

        // Fallback: parse page count directly from PDF stream if pdfinfo is missing or failed
        $pageCount = $this->pageCountFromContent($path);
        if ($pageCount > 0) {
            return $pageCount;
        }

        if (! $result->successful()) {
            Log::warning("PdfRenderer: Falló {$binary} en {$path}. Salida: ".$result->errorOutput());
        }

        throw new RuntimeException('No se pudo leer el PDF.');
    }

    /**
     * Plain text of a single page, used for translated reading.
     */
    public function pageText(string $path, int $page): string
    {
        $binary = $this->resolveBinary('pdftotext_path', 'pdftotext');
        $result = Process::timeout(30)->run([
            $binary,
            '-f', (string) $page, '-l', (string) $page,
            '-enc', 'UTF-8', '-nopgbrk',
            $path, '-',
        ]);

        if ($result->successful()) {
            return $result->output();
        }

        // Fallback: extract text directly from PDF page stream in pure PHP
        $extracted = $this->extractTextFromPdf($path, $page);
        if ($extracted !== '') {
            return $extracted;
        }

        Log::warning("PdfRenderer: Falló extracción con {$binary} en página {$page}. Salida: ".$result->errorOutput());

        return '';
    }

    /**
     * Render a single page as PNG bytes.
     */
    public function renderPage(string $path, int $page, int $width): string
    {
        $binary = $this->resolveBinary('pdftoppm_path', 'pdftoppm');
        $outputBase = tempnam(sys_get_temp_dir(), 'libro-page');

        try {
            $result = Process::timeout(60)->run([
                $binary,
                '-png', '-singlefile',
                '-f', (string) $page, '-l', (string) $page,
                '-scale-to-x', (string) $width, '-scale-to-y', '-1',
                $path, $outputBase,
            ]);

            if ($result->successful() && is_file($outputBase.'.png')) {
                return file_get_contents($outputBase.'.png');
            }
        } finally {
            @unlink($outputBase);
            @unlink($outputBase.'.png');
        }

        // Fallback: render using Imagick if available on the server
        if (class_exists(\Imagick::class)) {
            try {
                $im = new \Imagick;
                $im->setResolution(150, 150);
                $im->readImage($path.'['.($page - 1).']');
                $im->setImageFormat('png');
                $im->thumbnailImage($width, 0);
                $png = $im->getImageBlob();
                $im->clear();
                $im->destroy();

                if ($png !== false && strlen($png) > 0) {
                    return $png;
                }
            } catch (\Throwable $e) {
                Log::warning("PdfRenderer: Falló renderizado Imagick para página {$page}: ".$e->getMessage());
            }
        }

        Log::warning("PdfRenderer: Falló {$binary} para renderizar página {$page}. Salida: ".($result->errorOutput() ?? ''));

        throw new RuntimeException('No se pudo renderizar la página '.$page.'.');
    }

    /**
     * Resolve executable binary path from config, system directories or PATH.
     */
    public function resolveBinary(string $configKey, string $defaultBinary): string
    {
        $configured = (string) config('reader.'.$configKey, $defaultBinary);

        if ($configured !== '' && is_file($configured) && is_executable($configured)) {
            return $configured;
        }

        $candidates = [
            $configured,
            '/usr/bin/'.$defaultBinary,
            '/usr/local/bin/'.$defaultBinary,
            '/opt/homebrew/bin/'.$defaultBinary,
            base_path('bin/'.$defaultBinary),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return $configured !== '' ? $configured : $defaultBinary;
    }

    /**
     * Fallback to read page count without external binaries.
     */
    private function pageCountFromContent(string $path): int
    {
        if (! is_file($path) || ! is_readable($path)) {
            return 0;
        }

        $content = @file_get_contents($path);
        if ($content === false || ! str_starts_with($content, '%PDF-')) {
            return 0;
        }

        // Attempt 1: Root /Pages dictionary /Count attribute
        if (preg_match_all('/\/Type\s*\/Pages\b[^>]*?\/Count\s+(\d+)/s', $content, $matches)) {
            $max = max(array_map('intval', $matches[1]));
            if ($max > 0) {
                return $max;
            }
        }

        // Attempt 2: Count individual /Type /Page objects (excluding /Pages)
        if (preg_match_all('/\/Type\s*\/Page\b(?!\s*s)/', $content, $matches)) {
            $count = count($matches[0]);
            if ($count > 0) {
                return $count;
            }
        }

        return 0;
    }

    /**
     * Fallback to extract text from a PDF page using pure PHP.
     */
    private function extractTextFromPdf(string $path, int $page): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }

        $content = @file_get_contents($path);
        if ($content === false || ! str_starts_with($content, '%PDF-')) {
            return '';
        }

        // Find all page objects
        if (! preg_match_all('/\/Type\s*\/Page\b(?!\s*s)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        if (! isset($matches[0][$page - 1])) {
            return '';
        }

        $pageOffset = $matches[0][$page - 1][1];
        $pageChunk = substr($content, $pageOffset, 3000);

        // Find Contents object reference: /Contents 5 0 R or /Contents [5 0 R 6 0 R]
        $contentIds = [];
        if (preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $pageChunk, $c)) {
            $contentIds[] = $c[1];
        } elseif (preg_match('/\/Contents\s*\[(.*?)\]/s', $pageChunk, $c)) {
            if (preg_match_all('/(\d+)\s+0\s+R/', $c[1], $ids)) {
                $contentIds = $ids[1];
            }
        }

        $fullText = '';
        foreach ($contentIds as $contentId) {
            if (preg_match('/'.$contentId.'\s+0\s+obj.*?stream[\r\n]+(.*?)[\r\n]+endstream/s', $content, $s)) {
                $stream = $s[1];
                // Check if compressed with FlateDecode (zlib)
                if (str_starts_with($stream, "\x78\x9c") || str_starts_with($stream, "\x78\x01") || str_starts_with($stream, "\x78\xda")) {
                    $decompressed = @gzuncompress($stream);
                    if ($decompressed !== false) {
                        $stream = $decompressed;
                    }
                }

                // Extract strings from Tj operators: (text) Tj
                if (preg_match_all('/\((.*?)\)\s*Tj/s', $stream, $tj)) {
                    foreach ($tj[1] as $line) {
                        $fullText .= stripcslashes($line)."\n";
                    }
                }

                // Extract strings from TJ array operators: [(t1) 20 (t2)] TJ
                if (preg_match_all('/\[(.*?)\]\s*TJ/s', $stream, $tjs)) {
                    foreach ($tjs[1] as $arrayChunk) {
                        if (preg_match_all('/\((.*?)\)/s', $arrayChunk, $parts)) {
                            $fullText .= implode('', array_map('stripcslashes', $parts[1]))."\n";
                        }
                    }
                }
            }
        }

        return trim($fullText);
    }
}
