<?php

namespace App\Services\Reader;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Translates reading text cleanly without relying on client-side third-party DOM mutation scripts.
 */
class ContentTranslator
{
    /**
     * Translate text from source language to target language.
     */
    public function translate(string $text, string $target = 'en', string $source = 'es'): string
    {
        $clean = trim($text);

        if ($clean === '' || strtolower($target) === strtolower($source)) {
            return $text;
        }

        try {
            $response = Http::timeout(8)->get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl' => $source,
                'tl' => $target,
                'dt' => 't',
                'q' => $clean,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $translated = '';

                foreach ($data[0] ?? [] as $part) {
                    $translated .= $part[0] ?? '';
                }

                if (trim($translated) !== '') {
                    return $translated;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ContentTranslator: fallo de conexión al traducir texto: '.$e->getMessage());
        }

        return $text;
    }
}
