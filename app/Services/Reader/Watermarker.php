<?php

namespace App\Services\Reader;

use GdImage;

/**
 * Stamps the reader identity on every rendered page (HU-014).
 */
class Watermarker
{
    public function apply(string $imageBytes, string $text): string
    {
        $image = imagecreatefromstring($imageBytes);
        imagealphablending($image, true);

        $width = imagesx($image);
        $height = imagesy($image);
        $color = imagecolorallocatealpha($image, 120, 120, 120, 105);
        $font = config('reader.watermark_font');

        if ($font && is_file($font)) {
            $this->tileTrueType($image, $text, $font, $color, $width, $height);
        } else {
            $this->tileBuiltIn($image, $text, $color, $width, $height);
        }

        ob_start();
        imagewebp($image, null, 82);

        return (string) ob_get_clean();
    }

    private function tileTrueType(GdImage $image, string $text, string $font, int $color, int $width, int $height): void
    {
        $size = max(12, (int) ($width / 45));
        $stepX = (int) ($size * strlen($text) * 0.75);
        $stepY = $size * 9;

        for ($y = $stepY / 2, $row = 0; $y < $height + $stepY; $y += $stepY, $row++) {
            for ($x = -$stepX + ($row % 2) * ($stepX / 2); $x < $width; $x += $stepX) {
                imagettftext($image, $size, 30, (int) $x, (int) $y, $color, $font, $text);
            }
        }
    }

    private function tileBuiltIn(GdImage $image, string $text, int $color, int $width, int $height): void
    {
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($text);
        $stepX = $textWidth + 120;
        $stepY = 140;

        for ($y = 40, $row = 0; $y < $height; $y += $stepY, $row++) {
            for ($x = ($row % 2) * ($stepX / 2) - $stepX / 2; $x < $width; $x += $stepX) {
                imagestring($image, $font, (int) $x, $y, $text, $color);
            }
        }
    }
}
