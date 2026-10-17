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

            if (! $result->successful() || ! is_file($outputBase.'.png')) {
                throw new RuntimeException('No se pudo renderizar la página '.$page.'.');
            }

            return file_get_contents($outputBase.'.png');
        } finally {
            @unlink($outputBase);
            @unlink($outputBase.'.png');
        }
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
}
