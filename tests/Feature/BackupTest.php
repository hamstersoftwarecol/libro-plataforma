<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    use DatabaseMigrations;

    private const PASSWORD = 'clave-de-respaldo-de-prueba';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');
        Storage::fake('books');
        Storage::fake('local');
        Storage::fake('public');
        config(['backup.password' => self::PASSWORD]);
    }

    public function test_backup_contains_encrypted_database_and_files(): void
    {
        User::factory()->create(['email' => 'respaldo@example.com']);
        Storage::disk('books')->put('1/libro.pdf.enc', 'cifrado');
        Storage::disk('books')->put('_cache/1/pagina.bin', 'cache');
        Storage::disk('local')->put('receipts/factura.pdf', 'pdf');
        Storage::disk('public')->put('covers/portada.jpg', 'jpg');

        $this->artisan('backup:run')->assertSuccessful();

        $files = Storage::disk('backups')->files();
        $this->assertCount(1, $files);
        $this->assertStringStartsWith(BackupService::PREFIX, $files[0]);

        $zip = new ZipArchive;
        $zip->open(Storage::disk('backups')->path($files[0]));

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $this->assertSame(ZipArchive::EM_AES_256, $zip->statIndex($index)['encryption_method']);
        }

        $this->assertFalse($zip->getFromName('manifest.json'));

        $zip->setPassword(self::PASSWORD);
        $this->assertSame(3, json_decode($zip->getFromName('manifest.json'), true)['files']);
        $this->assertSame('cifrado', $zip->getFromName('files/books/1/libro.pdf.enc'));
        $this->assertSame('pdf', $zip->getFromName('files/local/receipts/factura.pdf'));
        $this->assertFalse($zip->locateName('files/books/_cache/1/pagina.bin'));
        $this->assertNotFalse($zip->locateName('database/database.sqlite'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.created']);
    }

    public function test_backup_refuses_to_run_without_a_strong_password(): void
    {
        config(['backup.password' => 'corta']);

        $this->artisan('backup:run')->expectsOutputToContain('BACKUP_PASSWORD')->assertFailed();

        $this->assertSame([], Storage::disk('backups')->files());
    }

    public function test_old_backups_are_pruned_but_the_latest_is_kept(): void
    {
        $disk = Storage::disk('backups');
        $disk->put(BackupService::PREFIX.'2026-01-01_030000.zip', 'viejo');
        $disk->put(BackupService::PREFIX.'2026-01-02_030000.zip', 'viejo');
        $disk->put('otro-archivo.txt', 'no tocar');
        touch($disk->path(BackupService::PREFIX.'2026-01-01_030000.zip'), now()->subDays(30)->timestamp);
        touch($disk->path(BackupService::PREFIX.'2026-01-02_030000.zip'), now()->subDays(29)->timestamp);

        $this->assertSame(1, app(BackupService::class)->prune());

        $disk->assertMissing(BackupService::PREFIX.'2026-01-01_030000.zip');
        $disk->assertExists(BackupService::PREFIX.'2026-01-02_030000.zip');
        $disk->assertExists('otro-archivo.txt');
    }

    public function test_backups_are_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('backup:run')->expectsOutputToContain('subscriptions:remind');
    }
}
