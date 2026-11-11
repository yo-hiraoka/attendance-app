<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAttendanceViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_attendance_list(): void
    {
        $this->get('/attendance/list')
            ->assertRedirect(route('login'));
    }

    public function test_unverified_user_cannot_view_attendance_list(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/attendance/list')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_user_can_view_own_monthly_attendances(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-09',
        ]);

        Attendance::factory()->create([
            'user_id' => $other->id,
            'work_date' => '2026-11-10',
        ]);

        $this->actingAs($user)
            ->get('/attendance/list?date=2026-11')
            ->assertOk()
            ->assertSee('2026/11')
            ->assertSee('11/09')
            ->assertDontSee('11/10');
    }

    public function test_user_can_switch_months(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/attendance/list?date=2026-11')
            ->assertOk()
            ->assertSee('?date=2026-10')
            ->assertSee('?date=2026-12');
    }

    public function test_invalid_month_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/attendance/list?date=2026-13')
            ->assertSessionHasErrors('date');
    }

    public function test_list_calculates_multiple_breaks_and_work_time(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-09',
            'clock_in_at' => '2026-11-09 00:00:00',
            'clock_out_at' => '2026-11-09 09:00:00',
        ]);

        BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'started_at' => '2026-11-09 03:00:00',
            'ended_at' => '2026-11-09 03:30:00',
        ]);

        BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'started_at' => '2026-11-09 06:00:00',
            'ended_at' => '2026-11-09 06:15:00',
        ]);

        $this->actingAs($user)
            ->get('/attendance/list?date=2026-11')
            ->assertOk()
            ->assertSee('09:00')
            ->assertSee('18:00')
            ->assertSee('0:45')
            ->assertSee('8:15')
            ->assertSee('/attendance/'.$attendance->id);
    }

    public function test_detail_displays_break_times_in_jst(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-09',
            'clock_in_at' => '2026-11-09 00:00:00',
            'clock_out_at' => '2026-11-09 09:00:00',
        ]);

        BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'started_at' => '2026-11-09 03:00:00',
            'ended_at' => '2026-11-09 03:30:00',
        ]);

        $this->actingAs($user)
            ->get('/attendance/'.$attendance->id)
            ->assertOk()
            ->assertSee('勤怠詳細')
            ->assertSee('12:00')
            ->assertSee('12:30')
            ->assertSee('09:00')
            ->assertSee('18:00');
    }

    public function test_other_users_attendance_detail_returns_404(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $other->id,
        ]);

        $this->actingAs($user)
            ->get('/attendance/'.$attendance->id)
            ->assertNotFound();
    }

    public function test_admin_cannot_view_user_attendances(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)
            ->get('/attendance/list')
            ->assertForbidden();

        $this->get('/attendance/1')
            ->assertForbidden();
    }

    public function test_overnight_attendance_is_grouped_by_work_date(): void
    {
        $user = User::factory()->create();

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-09',
            'clock_in_at' => '2026-11-09 14:00:00',
            'clock_out_at' => '2026-11-09 17:00:00',
        ]);

        $this->actingAs($user)
            ->get('/attendance/list?date=2026-11')
            ->assertOk()
            ->assertSee('23:00')
            ->assertSee('02:00')
            ->assertSee('3:00');
    }
}
