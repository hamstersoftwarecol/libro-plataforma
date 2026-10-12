<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('user:make-admin {email : Correo del nuevo administrador} {--name= : Nombre opcional}')]
#[Description('Create an administrator, or promote an existing user to administrator')]
class MakeAdmin extends Command
{
    public function handle(AuditLogger $auditLogger): int
    {
        $email = mb_strtolower(trim($this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email:rfc']])->fails()) {
            $this->error("Correo no válido: {$email}");

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $existed = $user->exists;

        $user->forceFill([
            'role' => Role::Admin,
            'is_active' => true,
            'name' => $this->option('name') ?: $user->name,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $auditLogger->log($existed ? 'user.promoted_to_admin' : 'user.admin_created', $user, ['email' => $email], $user);

        $this->info(($existed ? 'Usuario ascendido a administrador: ' : 'Administrador creado: ').$email);
        $this->line('Ya puede ingresar en /acceso con su correo; recibirá un código de acceso.');

        return self::SUCCESS;
    }
}
