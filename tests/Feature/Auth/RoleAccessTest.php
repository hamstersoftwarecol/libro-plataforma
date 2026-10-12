<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('library'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_reader_cannot_access_admin(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_and_editor_can_access_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk();
        $this->actingAs(User::factory()->editor()->create())->get(route('admin.dashboard'))->assertOk();
    }

    public function test_only_admin_can_view_finances(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->can('view-finances'));
        $this->assertFalse(User::factory()->editor()->create()->can('view-finances'));
        $this->assertFalse(User::factory()->create()->can('view-finances'));
    }

    public function test_inactive_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->admin()->inactive()->create())->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $this->actingAs(User::factory()->create())->get(route('login'))->assertRedirect(route('library'));
    }
}
