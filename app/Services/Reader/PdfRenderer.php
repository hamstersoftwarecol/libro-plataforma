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
        // 1. Try pdftoppm (highest quality poppler renderer)
        $png = $this->renderWithPdftoppm($path, $page, $width);
        if ($png !== null) {
            return $png;
        }

        // 2. Try pdftocairo (alternative poppler renderer)
        $png = $this->renderWithPdftocairo($path, $page, $width);
        if ($png !== null) {
            return $png;
        }

        // 3. Try Ghostscript (gs - standard on cPanel / CentOS / Linux)
        $png = $this->renderWithGhostscript($path, $page, $width);
        if ($png !== null) {
            return $png;
        }

        // 4. Try ImageMagick CLI (convert)
        $png = $this->renderWithConvert($path, $page, $width);
        if ($png !== null) {
            return $png;
        }

        // 5. Try Imagick PHP extension
        $png = $this->renderWithImagick($path, $page, $width);
        if ($png !== null) {
            return $png;
        }

        // 6. Graceful pure PHP GD fallback
        Log::info("PdfRenderer: Utilizando renderizado GD para página {$page}.");

        return $this->renderFallbackGd($path, $page, $width);
    }

    private function renderWithPdftoppm(string $path, int $page, int $width): ?string
    {
        $binary = $this->resolveBinary('pdftoppm_path', 'pdftoppm');
        $outputBase = tempnam(sys_get_temp_dir(), 'libro-ppm');

        try {
            $result = Process::timeout(60)->run([
                $binary,
                '-png', '-singlefile',
                '-f', (string) $page, '-l', (string) $page,
                '-scale-to-x', (string) $width, '-scale-to-y', '-1',
                $path, $outputBase,
            ]);

            if ($result->successful() && is_file($outputBase.'.png')) {
                return (string) file_get_contents($outputBase.'.png');
            }
        } catch (\Throwable $e) {
            Log::debug('PdfRenderer pdftoppm error: '.$e->getMessage());
        } finally {
            @unlink($outputBase);
            @unlink($outputBase.'.png');
        }

        return null;
    }

    private function renderWithPdftocairo(string $path, int $page, int $width): ?string
    {
        $binary = $this->resolveBinary('pdftocairo_path', 'pdftocairo');
        $outputBase = tempnam(sys_get_temp_dir(), 'libro-cairo');

        try {
            $result = Process::timeout(60)->run([
                $binary,
                '-png', '-singlefile',
                '-f', (string) $page, '-l', (string) $page,
                '-scale-to-x', (string) $width, '-scale-to-y', '-1',
                $path, $outputBase,
            ]);

            if ($result->successful() && is_file($outputBase.'.png')) {
                return (string) file_get_contents($outputBase.'.png');
            }
        } catch (\Throwable $e) {
            Log::debug('PdfRenderer pdftocairo error: '.$e->getMessage());
        } finally {
            @unlink($outputBase);
            @unlink($outputBase.'.png');
        }

        return null;
    }

    private function renderWithGhostscript(string $path, int $page, int $width): ?string
    {
        $binary = $this->resolveBinary('gs_path', 'gs');
        $outputFile = tempnam(sys_get_temp_dir(), 'libro-gs').'.png';

        try {
            $result = Process::timeout(60)->run([
                $binary,
                '-dSAFER',
                '-dBATCH',
                '-dNOPAUSE',
                '-sDEVICE=png16m',
                '-r150',
                '-dFirstPage='.(string) $page,
                '-dLastPage='.(string) $page,
                '-dTextAlphaBits=4',
                '-dGraphicsAlphaBits=4',
                '-sOutputFile='.$outputFile,
                $path,
            ]);

            if ($result->successful() && is_file($outputFile) && filesize($outputFile) > 0) {
                $raw = (string) file_get_contents($outputFile);

                return $this->resizePngIfNeeded($raw, $width);
            }
        } catch (\Throwable $e) {
            Log::debug('PdfRenderer gs error: '.$e->getMessage());
        } finally {
            @unlink($outputFile);
        }

        return null;
    }

    private function renderWithConvert(string $path, int $page, int $width): ?string
    {
        $binary = $this->resolveBinary('convert_path', 'convert');
        $outputFile = tempnam(sys_get_temp_dir(), 'libro-conv').'.png';

        try {
            $pageIndex = $page - 1;
            $result = Process::timeout(60)->run([
                $binary,
                '-density', '150',
                "{$path}[{$pageIndex}]",
                '-resize', (string) $width,
                $outputFile,
            ]);

            if ($result->successful() && is_file($outputFile) && filesize($outputFile) > 0) {
                return (string) file_get_contents($outputFile);
            }
        } catch (\Throwable $e) {
            Log::debug('PdfRenderer convert error: '.$e->getMessage());
        } finally {
            @unlink($outputFile);
        }

        return null;
    }

    private function renderWithImagick(string $path, int $page, int $width): ?string
    {
        if (! class_exists(\Imagick::class)) {
            return null;
        }

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
            Log::debug('PdfRenderer Imagick error: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Fallback to render a clean typographic book page image using GD when no external PDF renderer is available.
     */
    public function renderFallbackGd(string $path, int $page, int $width): string
    {
        $text = $this->pageText($path, $page);
        if ($text === '') {
            $text = $this->extractTextFromPdf($path, $page);
        }

        $aspectRatio = 1.414;
        $height = (int) max(800, $width * $aspectRatio);

        $image = imagecreatetruecolor($width, $height);

        $bg = imagecolorallocate($image, 252, 250, 246);
        imagefill($image, 0, 0, $bg);

        $borderColor = imagecolorallocate($image, 235, 230, 220);
        $margin = (int) ($width * 0.05);
        imagerectangle($image, $margin, $margin, $width - $margin, $height - $margin, $borderColor);

        $headerColor = imagecolorallocate($image, 140, 135, 125);
        $font = 4;
        $headerText = "Página {$page}";
        $headerX = (int) (($width - (strlen($headerText) * imagefontwidth($font))) / 2);
        imagestring($image, $font, $headerX, $margin + 20, $headerText, $headerColor);

        $textColor = imagecolorallocate($image, 35, 35, 35);
        $fontWidth = imagefontwidth(4);
        $fontHeight = imagefontheight(4);

        $contentMarginX = (int) ($width * 0.08);
        $contentWidth = $width - ($contentMarginX * 2);
        $charsPerLine = max(20, (int) ($contentWidth / $fontWidth));

        $startY = $margin + 60;
        $maxY = $height - $margin - 50;

        if (trim($text) === '') {
            $msg = "(Página {$page} · Usa el modo Texto para leer el contenido)";
            $msgX = (int) (($width - (strlen($msg) * $fontWidth)) / 2);
            imagestring($image, 4, $msgX, (int) ($height / 2), $msg, $headerColor);
        } else {
            $paragraphs = preg_split('/\n\s*\n/u', trim($text)) ?: [trim($text)];
            $currentY = $startY;

            foreach ($paragraphs as $paragraph) {
                $cleanParagraph = preg_replace('/\s+/u', ' ', trim($paragraph));
                $lines = explode("\n", wordwrap($cleanParagraph, $charsPerLine, "\n", true));
                foreach ($lines as $line) {
                    if ($currentY + $fontHeight > $maxY) {
                        break 2;
                    }
                    imagestring($image, 4, $contentMarginX, $currentY, $line, $textColor);
                    $currentY += $fontHeight + 6;
                }
                $currentY += 12;
            }
        }

        $footerText = "— {$page} —";
        $footerX = (int) (($width - (strlen($footerText) * imagefontwidth(3))) / 2);
        imagestring($image, 3, $footerX, $height - $margin - 30, $footerText, $headerColor);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    private function resizePngIfNeeded(string $rawPng, int $targetWidth): string
    {
        if ($targetWidth <= 0 || ! function_exists('imagecreatefromstring')) {
            return $rawPng;
        }

        $image = @imagecreatefromstring($rawPng);
        if (! $image) {
            return $rawPng;
        }

        $currentWidth = imagesx($image);
        if ($currentWidth === $targetWidth) {
            imagedestroy($image);

            return $rawPng;
        }

        $scaled = imagescale($image, $targetWidth);
        imagedestroy($image);

        if (! $scaled) {
            return $rawPng;
        }

        ob_start();
        imagepng($scaled);
        imagedestroy($scaled);

        return (string) ob_get_clean();
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
            '/bin/'.$defaultBinary,
            '/opt/homebrew/bin/'.$defaultBinary,
            base_path('bin/'.$defaultBinary),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        try {
            $which = Process::timeout(5)->run(['which', $defaultBinary]);
            if ($which->successful()) {
                $path = trim($which->output());
                if ($path !== '' && is_file($path) && is_executable($path)) {
                    return $path;
                }
            }
        } catch (\Throwable) {
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
