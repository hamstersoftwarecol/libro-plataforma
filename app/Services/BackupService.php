<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Creates encrypted archives with the database and uploaded files (HU-031).
 */
class BackupService
{
    public const PREFIX = 'libro-backup-';

    /**
     * Build the archive and store it on the backup disk. Returns its path on that disk.
     */
    public function run(): string
    {
        $password = (string) config('backup.password');

        if (strlen($password) < 16) {
            throw new RuntimeException('Configura BACKUP_PASSWORD con al menos 16 caracteres.');
        }

        $workDir = storage_path('app/backup-tmp/'.Str::uuid());
        mkdir($workDir, 0700, true);
        $archivePath = $workDir.'/archive.zip';

        try {
            $dumpPath = $this->dumpDatabase($workDir);

            $zip = new ZipArchive;
            $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->setPassword($password);

            $this->addFile($zip, $dumpPath, 'database/'.basename($dumpPath));
            $fileCount = $this->addDirectories($zip);

            $zip->addFromString('manifest.json', json_encode([
                'app' => config('app.name'),
                'created_at' => now()->toIso8601String(),
                'database' => DB::getDriverName(),
                'database_sha256' => hash_file('sha256', $dumpPath),
                'files' => $fileCount,
            ], JSON_PRETTY_PRINT));
            $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256);

            if (! $zip->close()) {
                throw new RuntimeException('No se pudo escribir el archivo de respaldo.');
            }

            $target = self::PREFIX.now()->format('Y-m-d_His').'.zip';
            $stream = fopen($archivePath, 'rb');
            Storage::disk(config('backup.disk'))->writeStream($target, $stream);
            fclose($stream);

            return $target;
        } finally {
            Process::run(['rm', '-rf', $workDir]);
        }
    }

    /**
     * Delete archives older than the retention period. Always keeps the newest one.
     */
    public function prune(): int
    {
        $disk = Storage::disk(config('backup.disk'));
        $backups = collect($disk->files())->filter(fn (string $file) => str_starts_with($file, self::PREFIX))->sort()->values();
        $cutoff = now()->subDays(config('backup.keep_days'))->timestamp;

        $toDelete = $backups->slice(0, -1)->filter(fn (string $file) => $disk->lastModified($file) < $cutoff);
        $toDelete->each(fn (string $file) => $disk->delete($file));

        return $toDelete->count();
    }

    private function dumpDatabase(string $workDir): string
    {
        $connection = config('database.connections.'.config('database.default'));

        if ($connection['driver'] === 'sqlite') {
            $path = $workDir.'/database.sqlite';
            DB::statement('VACUUM INTO ?', [$path]);

            return $path;
        }

        if (in_array($connection['driver'], ['mysql', 'mariadb'], true)) {
            $path = $workDir.'/database.sql';
            $result = Process::env(['MYSQL_PWD' => (string) $connection['password']])
                ->timeout(600)
                ->run([
                    config('backup.mysqldump_path'),
                    '--single-transaction', '--quick', '--routines', '--no-tablespaces',
                    '--host='.$connection['host'], '--port='.$connection['port'],
                    '--user='.$connection['username'],
                    '--result-file='.$path,
                    $connection['database'],
                ]);

            if (! $result->successful()) {
                throw new RuntimeException('mysqldump falló: '.$result->errorOutput());
            }

            return $path;
        }

        throw new RuntimeException('Motor de base de datos no soportado para respaldos: '.$connection['driver']);
    }

    private function addDirectories(ZipArchive $zip): int
    {
        $count = 0;

        foreach (config('backup.directories') as $diskName => $directory) {
            $disk = Storage::disk($diskName);
            $excluded = config("backup.exclude.{$diskName}", []);

            foreach ($disk->allFiles($directory) as $file) {
                if (Str::startsWith($file, array_map(fn (string $path) => $path.'/', $excluded)) || basename($file) === '.gitignore') {
                    continue;
                }

                $this->addFile($zip, $disk->path($file), "files/{$diskName}/{$file}");
                $count++;
            }
        }

        return $count;
    }

    private function addFile(ZipArchive $zip, string $path, string $name): void
    {
        $zip->addFile($path, $name);
        $zip->setEncryptionName($name, ZipArchive::EM_AES_256);
    }
}
