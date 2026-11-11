<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function registrationInput(array $changes = []): array
    {
        return array_merge([
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $changes);
    }

    public function test_registration_requires_name_with_expected_message(): void
    {
        $this->post('/register', $this->registrationInput([
            'name' => '',
        ]))->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
        ]);
    }

    public function test_registration_requires_email_with_expected_message(): void
    {
        $this->post('/register', $this->registrationInput([
            'email' => '',
        ]))->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    public function test_registration_requires_password_with_expected_message(): void
    {
        $this->post('/register', $this->registrationInput([
            'password' => '',
        ]))->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    public function test_registration_rejects_short_password_with_expected_message(): void
    {
        $this->post('/register', $this->registrationInput([
            'password' => 'abc123',
            'password_confirmation' => 'abc123',
        ]))->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください',
        ]);
    }

    public function test_registration_rejects_password_mismatch_with_expected_message(): void
    {
        $this->post('/register', $this->registrationInput([
            'password_confirmation' => 'different123',
        ]))->assertSessionHasErrors([
            'password' => 'パスワードと一致しません',
        ]);
    }

    public function test_user_login_requires_email_and_password_with_expected_messages(): void
    {
        $this->post('/login', [
            'email' => '',
            'password' => '',
        ])->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
            'password' => 'パスワードを入力してください',
        ]);
    }

    public function test_user_login_failure_has_expected_message(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
            'password' => 'password123',
        ]);

        $this->post('/login', [
            'email' => 'unknown@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
    }

    public function test_admin_login_requires_email_and_password_with_expected_messages(): void
    {
        $this->post('/admin/login', [
            'email' => '',
            'password' => '',
        ])->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
            'password' => 'パスワードを入力してください',
        ]);
    }

    public function test_admin_login_failure_has_expected_message(): void
    {
        $admin = User::factory()->create([
            'password' => 'password123',
        ]);

        $admin->forceFill(['role' => 'admin'])->save();

        $this->post('/admin/login', [
            'email' => 'unknown@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
    }
}
