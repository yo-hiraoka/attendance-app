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

    public function test_invalid_clock_order_has_expected_message(): void
    {
        $admin = $this->admin();
        $attendance = $this->attendance(User::factory()->create());

        $this->actingAs($admin)
            ->post('/admin/attendance/'.$attendance->id, $this->input([
                'new_clock_in' => '18:00',
                'new_clock_out' => '09:00',
                'new_break_in' => [],
                'new_break_out' => [],
            ]))
            ->assertSessionHasErrors([
                'new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です',
            ]);
    }

    public function test_break_start_after_clock_out_has_expected_message(): void
    {
        $admin = $this->admin();
        $attendance = $this->attendance(User::factory()->create());

        $this->actingAs($admin)
            ->post('/admin/attendance/'.$attendance->id, $this->input([
                'new_break_in' => ['19:00'],
                'new_break_out' => ['19:30'],
            ]))
            ->assertSessionHasErrors([
                'new_break_in.0' => '休憩時間が不適切な値です',
            ]);
    }

    public function test_break_end_after_clock_out_has_expected_message(): void
    {
        $admin = $this->admin();
        $attendance = $this->attendance(User::factory()->create());

        $this->actingAs($admin)
            ->post('/admin/attendance/'.$attendance->id, $this->input([
                'new_break_in' => ['17:30'],
                'new_break_out' => ['19:00'],
            ]))
            ->assertSessionHasErrors([
                'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
            ]);
    }

    public function test_empty_comment_has_expected_message(): void
    {
        $admin = $this->admin();
        $attendance = $this->attendance(User::factory()->create());

        $this->actingAs($admin)
            ->post('/admin/attendance/'.$attendance->id, $this->input([
                'comment' => '',
            ]))
            ->assertSessionHasErrors([
                'comment' => '備考を記入してください',
            ]);
    }

    public function test_invalid_input_preserves_admin_form_values(): void
    {
        $admin = $this->admin();
        $attendance = $this->attendance(User::factory()->create());

        $this->actingAs($admin)
            ->from(route('admin.attendance.show', $attendance->id))
            ->followingRedirects()
            ->post(
                route('admin.attendance.update', $attendance->id),
                $this->input([
                    'new_clock_in' => '18:00',
                    'new_clock_out' => '09:00',
                    'new_break_in' => ['12:15'],
                    'new_break_out' => ['12:45'],
                    'comment' => '入力値保持の確認',
                ])
            )
            ->assertOk()
            ->assertSee('value="18:00"', false)
            ->assertSee('value="09:00"', false)
            ->assertSee('value="12:15"', false)
            ->assertSee('value="12:45"', false)
            ->assertSee('入力値保持の確認')
            ->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }
}
