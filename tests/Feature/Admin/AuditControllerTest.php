<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_non_admins_cannot_access_audit_logs(): void
    {
        $this->get(route('admin.audit.index'))->assertRedirect(route('login'));

        $reader = User::factory()->create(['role' => Role::Reader]);
        $this->actingAs($reader)->get(route('admin.audit.index'))->assertForbidden();

        $editor = User::factory()->create(['role' => Role::Editor]);
        $this->actingAs($editor)->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_admin_can_view_audit_index_and_stats(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $reader = User::factory()->create(['role' => Role::Reader]);

        AuditLog::create([
            'user_id' => $reader->id,
            'action' => 'auth.login',
            'ip_address' => '192.168.1.10',
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'book.created',
            'ip_address' => '10.0.0.1',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit.index'));

        $response->assertOk()
            ->assertViewIs('admin.audit.index')
            ->assertViewHas('logs')
            ->assertViewHas('stats')
            ->assertSee('Auditoría e Interacciones')
            ->assertSee('Inicio de sesión')
            ->assertSee('Libro creado');
    }

    public function test_feed_endpoint_returns_json_items_since_given_id(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $log1 = AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'auth.login',
        ]);

        $log2 = AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'reader.opened',
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.audit.feed', ['since_id' => $log1->id]));

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('last_id', $log2->id)
            ->assertJsonPath('items.0.action', 'reader.opened');
    }

    public function test_feed_can_be_filtered_by_role_and_search(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin, 'email' => 'admin.audit@example.com']);
        $reader = User::factory()->create(['role' => Role::Reader, 'email' => 'reader.audit@example.com']);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'interaction.admin',
        ]);

        AuditLog::create([
            'user_id' => $reader->id,
            'action' => 'interaction.user',
        ]);

        $adminResponse = $this->actingAs($admin)->getJson(route('admin.audit.feed', ['role' => 'admin']));
        $adminResponse->assertOk()->assertJsonPath('count', 1);

        $searchResponse = $this->actingAs($admin)->getJson(route('admin.audit.feed', ['search' => 'reader.audit@example.com']));
        $searchResponse->assertOk()->assertJsonPath('count', 1)->assertJsonPath('items.0.user_email', 'reader.audit@example.com');
    }

    public function test_audit_logs_can_be_exported_to_csv(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'book.created',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit.export'));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_reading_progress_creates_reader_progress_audit_log(): void
    {
        $reader = User::factory()->create(['role' => Role::Reader]);
        $book = Book::factory()->published()->create(['page_count' => 100]);
        $reader->books()->attach($book->id, ['granted_at' => now()]);

        $response = $this->actingAs($reader)->putJson(route('reader.progress', $book), [
            'position' => 15,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $reader->id,
            'action' => 'reader.progress',
            'auditable_type' => $book->getMorphClass(),
            'auditable_id' => $book->id,
        ]);
    }

    public function test_middleware_records_web_navigation_interaction(): void
    {
        $reader = User::factory()->create(['role' => Role::Reader]);

        $this->actingAs($reader)->get(route('library'))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $reader->id,
            'action' => 'interaction.user',
        ]);
    }
}
