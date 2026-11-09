<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceStampingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_attendance(): void
    {
        $this->get('/attendance')
            ->assertRedirect(route('login'));
    }

    public function test_unverified_user_cannot_access_attendance(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/attendance')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_admin_cannot_use_user_attendance(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)
            ->get('/attendance')
            ->assertForbidden();

        $this->actingAs($admin)
            ->post('/attendance', ['action' => 'clock_in'])
            ->assertForbidden();
    }

    public function test_user_sees_off_duty_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/attendance')
            ->assertOk()
            ->assertSee('勤務外')
            ->assertSee('出勤');
    }

    public function test_user_can_clock_in_and_time_is_saved_in_utc(): void
    {
        $this->travelTo(
            CarbonImmutable::parse('2026-11-09 09:00:00', 'Asia/Tokyo')
        );

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/attendance', ['action' => 'clock_in'])
            ->assertRedirect(route('attendance.index'));

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => '2026-11-09',
            'clock_in_at' => '2026-11-09 00:00:00',
            'clock_out_at' => null,
        ]);

        $this->get('/attendance')
            ->assertOk()
            ->assertSee('出勤中');
    }

    public function test_user_cannot_clock_in_twice_on_same_day(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/attendance', ['action' => 'clock_in'])
            ->assertRedirect();

        $this->post('/attendance', ['action' => 'clock_in'])
            ->assertSessionHasErrors('action');

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_user_cannot_clock_out_before_clock_in(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/attendance', ['action' => 'clock_out'])
            ->assertSessionHasErrors('action');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_user_can_clock_out_and_cannot_clock_out_twice(): void
    {
        $this->travelTo(
            CarbonImmutable::parse('2026-11-09 09:00:00', 'Asia/Tokyo')
        );

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/attendance', ['action' => 'clock_in'])
            ->assertRedirect();

        $this->travelTo(
            CarbonImmutable::parse('2026-11-09 18:00:00', 'Asia/Tokyo')
        );

        $this->post('/attendance', ['action' => 'clock_out'])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'clock_out_at' => '2026-11-09 09:00:00',
        ]);

        $this->get('/attendance')
            ->assertSee('退勤済')
            ->assertSee('お疲れ様でした。');

        $this->post('/attendance', ['action' => 'clock_out'])
            ->assertSessionHasErrors('action');
    }

    public function test_user_can_clock_out_after_midnight(): void
    {
        $user = User::factory()->create();

        $this->travelTo(
            CarbonImmutable::parse('2026-11-09 23:00:00', 'Asia/Tokyo')
        );

        $this->actingAs($user)
            ->post('/attendance', ['action' => 'clock_in'])
            ->assertRedirect();

        $this->travelTo(
            CarbonImmutable::parse('2026-11-10 02:00:00', 'Asia/Tokyo')
        );

        $this->get('/attendance')
            ->assertSee('出勤中');

        $this->post('/attendance', ['action' => 'clock_out'])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => '2026-11-09',
            'clock_in_at' => '2026-11-09 14:00:00',
            'clock_out_at' => '2026-11-09 17:00:00',
        ]);

        $this->assertDatabaseCount('attendances', 1);
    }
}
