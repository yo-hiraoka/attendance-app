<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_home(): void
    {
        $this->get('/')
            ->assertRedirect(route('login'));
    }

    public function test_unverified_user_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_can_view_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('メール認証');
    }

    public function test_verified_user_can_access_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee($user->name);
    }

    public function test_user_can_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect();

        Notification::assertSentTo(
            $user,
            VerifyEmail::class
        );
    }

    public function test_user_can_verify_email_with_valid_link(): void
    {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect('/?verified=1');

        $this->assertNotNull(
            $user->fresh()->email_verified_at
        );

        Event::assertDispatched(Verified::class);
    }

    public function test_invalid_verification_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => 'invalid-hash',
            ]
        );

        $this->actingAs($user)
            ->get($url)
            ->assertForbidden();

        $this->assertNull(
            $user->fresh()->email_verified_at
        );
    }
}
