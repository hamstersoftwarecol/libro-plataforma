<?php

namespace Tests\Feature;

use App\Services\Reader\BookVault;
use RuntimeException;
use Tests\TestCase;

class BookVaultTest extends TestCase
{
    public function test_file_round_trips_through_encryption(): void
    {
        $plain = tempnam(sys_get_temp_dir(), 'plain');
        $encrypted = tempnam(sys_get_temp_dir(), 'enc');
        file_put_contents($plain, random_bytes(200_000));

        $vault = app(BookVault::class);
        $vault->encryptFile($plain, $encrypted);
        $decrypted = $vault->decryptToTemporaryFile($encrypted);

        $this->assertNotSame(file_get_contents($plain), file_get_contents($encrypted));
        $this->assertSame(file_get_contents($plain), file_get_contents($decrypted));

        array_map('unlink', [$plain, $encrypted, $decrypted]);
    }

    public function test_tampered_file_is_rejected(): void
    {
        $plain = tempnam(sys_get_temp_dir(), 'plain');
        $encrypted = tempnam(sys_get_temp_dir(), 'enc');
        file_put_contents($plain, 'contenido secreto');

        app(BookVault::class)->encryptFile($plain, $encrypted);
        $bytes = file_get_contents($encrypted);
        $bytes[strlen($bytes) - 3] = chr(ord($bytes[strlen($bytes) - 3]) ^ 1);
        file_put_contents($encrypted, $bytes);

        try {
            $this->expectException(RuntimeException::class);
            app(BookVault::class)->decryptToTemporaryFile($encrypted);
        } finally {
            array_map('unlink', [$plain, $encrypted]);
        }
    }
}
