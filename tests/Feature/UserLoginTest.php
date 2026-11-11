<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_displayed(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('ログイン');
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login-user@example.com',
            'password' => 'password123',
        ]);

        $response = $this->post('/login', [
            'email' => 'login-user@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'login-user@example.com',
            'password' => 'password123',
        ]);

        $response = $this->post('/login', [
            'email' => 'login-user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => '',
        ]);

        $response->assertSessionHasErrors([
            'email',
            'password',
        ]);

        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_from_login_screen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/login');

        $response->assertRedirect('/');
    }

    public function test_guest_is_redirected_from_home_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/');

        $response->assertRedirect(route('attendance.index'));
    }
}
