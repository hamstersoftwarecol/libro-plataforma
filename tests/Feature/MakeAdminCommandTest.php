<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_new_administrator(): void
    {
        $this->artisan('user:make-admin', ['email' => ' Nuevo.Admin@Example.com ', '--name' => 'Manuel'])
            ->expectsOutputToContain('Administrador creado: nuevo.admin@example.com')
            ->assertSuccessful();

        $user = User::sole();
        $this->assertSame(Role::Admin, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame('Manuel', $user->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.admin_created']);
    }

    public function test_it_promotes_an_existing_reader_and_reactivates_them(): void
    {
        $reader = User::factory()->inactive()->create(['email' => 'lector@example.com', 'name' => 'Lector']);

        $this->artisan('user:make-admin', ['email' => 'lector@example.com'])->assertSuccessful();

        $reader->refresh();
        $this->assertSame(Role::Admin, $reader->role);
        $this->assertTrue($reader->is_active);
        $this->assertSame('Lector', $reader->name);
        $this->assertSame(1, User::count());
    }

    public function test_it_rejects_invalid_emails(): void
    {
        $this->artisan('user:make-admin', ['email' => 'no-es-un-correo'])->assertFailed();

        $this->assertSame(0, User::count());
    }
}
