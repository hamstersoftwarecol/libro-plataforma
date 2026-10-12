<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_custom_error_page_renders(): void
    {
        foreach ([401, 402, 403, 404, 405, 419, 429, 500, 503] as $code) {
            $html = view("errors.{$code}", ['exception' => null])->render();

            $this->assertStringContainsString('Error '.$code, $html, "La página {$code} no se renderizó.");
            $this->assertStringContainsString('class="err-scene"', $html);
        }
    }

    public function test_missing_pages_use_the_custom_404(): void
    {
        $this->get('/esta-pagina-no-existe')->assertNotFound()->assertSee('Esta página')->assertSee('Volver al inicio');
    }

    public function test_forbidden_admin_pages_use_the_custom_403(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('no es para ti')
            ->assertSee('Ir a mi libro');
    }

    public function test_server_errors_use_the_custom_500_without_leaking_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/_prueba-error', fn () => throw new \RuntimeException('detalle interno secreto'));

        $this->get('/_prueba-error')
            ->assertStatus(500)
            ->assertSee('nos levantamos')
            ->assertDontSee('detalle interno secreto');
    }
}
