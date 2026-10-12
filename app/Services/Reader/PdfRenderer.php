<?php

namespace App\Services\Reader;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Renders PDF pages to images on the server so the original file never reaches the browser (HU-011).
 */
class PdfRenderer
{
    public function pageCount(string $path): int
    {
        $result = Process::timeout(30)->run([config('reader.pdfinfo_path'), $path]);

        if (! $result->successful() || ! preg_match('/^Pages:\s+(\d+)/m', $result->output(), $matches)) {
            throw new RuntimeException('No se pudo leer el PDF.');
        }

        return (int) $matches[1];
    }

    /**
     * Plain text of a single page, used for translated reading.
     */
    public function pageText(string $path, int $page): string
    {
        $result = Process::timeout(30)->run([
            config('reader.pdftotext_path'),
            '-f', (string) $page, '-l', (string) $page,
            '-enc', 'UTF-8', '-nopgbrk',
            $path, '-',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('No se pudo extraer el texto de la página '.$page.'.');
        }

        return $result->output();
    }

    /**
     * Render a single page as PNG bytes.
     */
    public function renderPage(string $path, int $page, int $width): string
    {
        $outputBase = tempnam(sys_get_temp_dir(), 'libro-page');

        try {
            $result = Process::timeout(60)->run([
                config('reader.pdftoppm_path'),
                '-png', '-singlefile',
                '-f', (string) $page, '-l', (string) $page,
                '-scale-to-x', (string) $width, '-scale-to-y', '-1',
                $path, $outputBase,
            ]);

            if (! $result->successful() || ! is_file($outputBase.'.png')) {
                throw new RuntimeException('No se pudo renderizar la página '.$page.'.');
            }

            return file_get_contents($outputBase.'.png');
        } finally {
            @unlink($outputBase);
            @unlink($outputBase.'.png');
        }
    }
}
