<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_displayed(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('会員登録');
    }

    public function test_user_can_register_and_is_redirected_to_email_verification(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'new-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'new-user@example.com')
            ->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(
            Hash::check('password123', $user->password)
        );

        $this->assertEquals('user', $user->role);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_requires_valid_input(): void
    {
        $response = $this->post('/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors([
            'name',
            'email',
            'password',
        ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->post('/register', [
            'name' => '別ユーザー',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_user_cannot_register_as_admin(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => '一般ユーザー',
            'email' => 'normal-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where(
            'email',
            'normal-user@example.com'
        )->firstOrFail();

        $this->assertEquals('user', $user->role);
        $this->assertFalse($user->isAdmin());
    }
}
