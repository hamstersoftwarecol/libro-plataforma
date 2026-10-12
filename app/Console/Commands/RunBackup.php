<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('backup:run {--no-prune : Keep old backups}')]
#[Description('Create an encrypted backup of the database and uploaded files (HU-031)')]
class RunBackup extends Command
{
    public function handle(BackupService $backups, AuditLogger $auditLogger): int
    {
        try {
            $path = $backups->run();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('El respaldo falló: '.$exception->getMessage());

            return self::FAILURE;
        }

        $auditLogger->log('backup.created', metadata: ['path' => $path, 'disk' => config('backup.disk')]);
        $this->info("Respaldo creado: {$path}");

        if (! $this->option('no-prune')) {
            $this->info('Respaldos antiguos eliminados: '.$backups->prune());
        }

        return self::SUCCESS;
    }
}
