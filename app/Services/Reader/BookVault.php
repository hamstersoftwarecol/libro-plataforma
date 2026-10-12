<?php

namespace App\Services\Reader;

use RuntimeException;

/**
 * Encrypts book files at rest with libsodium secretstream (HU-012).
 */
class BookVault
{
    private const CHUNK_SIZE = 65536;

    private const MAGIC = "LIBRO\x01";

    public function encryptFile(string $sourcePath, string $targetPath): void
    {
        $this->stream($sourcePath, $targetPath, function ($input, $output): void {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key());
            fwrite($output, self::MAGIC.$header);

            while (! feof($input)) {
                $chunk = fread($input, self::CHUNK_SIZE);
                $tag = feof($input)
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                fwrite($output, sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag));
            }
        });
    }

    /**
     * Decrypt into a temporary file. The caller must delete it.
     */
    public function decryptToTemporaryFile(string $encryptedPath): string
    {
        $targetPath = tempnam(sys_get_temp_dir(), 'libro');

        try {
            $this->stream($encryptedPath, $targetPath, function ($input, $output): void {
                if (fread($input, strlen(self::MAGIC)) !== self::MAGIC) {
                    throw new RuntimeException('El archivo no fue cifrado por la plataforma.');
                }

                $header = fread($input, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
                $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->key());
                $chunkSize = self::CHUNK_SIZE + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

                do {
                    $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, fread($input, $chunkSize));

                    if ($result === false) {
                        throw new RuntimeException('El archivo cifrado está dañado o la clave no coincide.');
                    }

                    [$plain, $tag] = $result;
                    fwrite($output, $plain);
                } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
            });
        } catch (RuntimeException $exception) {
            @unlink($targetPath);

            throw $exception;
        }

        return $targetPath;
    }

    /**
     * @param  callable(resource, resource): void  $callback
     */
    private function stream(string $sourcePath, string $targetPath, callable $callback): void
    {
        $input = fopen($sourcePath, 'rb');
        $output = fopen($targetPath, 'wb');

        try {
            $callback($input, $output);
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    private function key(): string
    {
        $key = base64_decode((string) config('reader.encryption_key'), true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('Configura BOOKS_ENCRYPTION_KEY con 32 bytes en base64.');
        }

        return $key;
    }
}
