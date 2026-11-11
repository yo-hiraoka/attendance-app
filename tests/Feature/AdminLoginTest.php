<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $user->forceFill(['role' => 'admin'])->save();

        return $user;
    }

    public function test_admin_login_page_is_displayed(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('管理者ログイン');
    }

    public function test_admin_can_login(): void
    {
        $admin = $this->createAdmin();

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_regular_user_cannot_login_as_admin(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_cannot_login_from_regular_login_page(): void
    {
        $admin = $this->createAdmin();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guest_cannot_access_admin_home(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_admin_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_access_admin_home_and_logout(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect(route('admin.attendance.list'));

        $this->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
