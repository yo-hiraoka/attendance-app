<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();

        return $user;
    }

    private function attendance(User $user): Attendance
    {
        return Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-09',
            'clock_in_at' => '2026-11-09 00:00:00',
            'clock_out_at' => '2026-11-09 09:00:00',
        ]);
    }

    private function input(array $changes = []): array
    {
        return array_merge([
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => ['12:00', '15:00'],
            'new_break_out' => ['12:30', '15:15'],
            'comment' => '管理者による勤怠修正',
        ], $changes);
    }

    public function test_guest_cannot_view_admin_attendance(): void
    {
        $this->get('/admin/attendance/list')
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_access_admin_attendance(): void
    {
        $user = User::factory()->create();
        $attendance = $this->attendance($user);

        $this->actingAs($user)
            ->get('/admin/attendance/list')
            ->assertForbidden();

        $this->get('/admin/attendance/'.$attendance->id)
            ->assertForbidden();

        $this->post(
            '/admin/attendance/'.$attendance->id,
            $this->input()
        )->assertForbidden();
    }

    public function test_admin_can_view_daily_attendance_list(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'name' => '勤怠テスト社員',
        ]);

        $this->attendance($user);

        $this->actingAs($admin)
            ->get('/admin/attendance/list?date=2026-11-09')
            ->assertOk()
            ->assertSee('勤怠テスト社員')
            ->assertSee('09:00')
            ->assertSee('18:00')
            ->assertSee('2026/11/09')
            ->assertSee('?date=2026-11-08')
            ->assertSee('?date=2026-11-10');
    }

    public function test_admin_can_view_attendance_detail(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $attendance = $this->attendance($user);

        BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'started_at' => '2026-11-09 03:00:00',
            'ended_at' => '2026-11-09 03:30:00',
        ]);

        $this->actingAs($admin)
            ->get('/admin/attendance/'.$attendance->id)
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('09:00')
            ->assertSee('18:00')
            ->assertSee('12:00')
            ->assertSee('12:30');
    }

    public function test_admin_can_update_attendance_and_breaks(): void
    {
        $admin = $this->admin();
        $attendance = $this->attendance(User::factory()->create());

        $this->actingAs($admin)
            ->post(
                '/admin/attendance/'.$attendance->id,
                $this->input()
            )
            ->assertRedirect(
                route('admin.attendance.show', $attendance->id)
            );

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_at' => '2026-11-09 00:30:00',
            'clock_out_at' => '2026-11-09 09:30:00',
            'note' => '管理者による勤怠修正',
        ]);

        $this->assertDatabaseCount('break_times', 2);

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'started_at' => '2026-11-09 03:00:00',
            'ended_at' => '2026-11-09 03:30:00',
        ]);
    }

    public function test_invalid_break_is_rejected_without_changes(): void
    {
        $admin = $this->admin();
        $attendance = $this->attendance(User::factory()->create());

        $this->actingAs($admin)
            ->post(
                '/admin/attendance/'.$attendance->id,
                $this->input([
                    'new_break_in' => ['08:00'],
                    'new_break_out' => ['08:30'],
                ])
            )
            ->assertSessionHasErrors('new_break_in.0');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_at' => '2026-11-09 00:00:00',
            'clock_out_at' => '2026-11-09 09:00:00',
        ]);

        $this->assertDatabaseCount('break_times', 0);
    }

    public function test_updated_times_are_visible_to_regular_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $attendance = $this->attendance($user);

        $this->actingAs($admin)
            ->post(
                '/admin/attendance/'.$attendance->id,
                $this->input()
            )
            ->assertRedirect();

        $this->actingAs($user)
            ->get('/attendance/'.$attendance->id)
            ->assertOk()
            ->assertSee('09:30')
            ->assertSee('18:30')
            ->assertSee('12:00')
            ->assertSee('12:30');
    }
}
