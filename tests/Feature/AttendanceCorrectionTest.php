<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function attendanceFor(User $user): Attendance
    {
        return Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-09',
            'clock_in_at' => '2026-11-09 00:00:00',
            'clock_out_at' => '2026-11-09 09:00:00',
        ]);
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => ['12:00', '15:00'],
            'new_break_out' => ['12:30', '15:15'],
            'comment' => '打刻時刻に誤りがあったため',
        ], $overrides);
    }

    public function test_user_can_submit_correction_with_multiple_breaks(): void
    {
        $user = User::factory()->create();
        $attendance = $this->attendanceFor($user);

        $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, $this->input())
            ->assertRedirect(route('attendance.show', $attendance->id));

        $this->assertDatabaseHas('stamp_correction_requests', [
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_clock_in_at' => '2026-11-09 00:30:00',
            'requested_clock_out_at' => '2026-11-09 09:30:00',
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('correction_request_breaks', 2);
    }

    public function test_original_attendance_remains_unchanged(): void
    {
        $user = User::factory()->create();
        $attendance = $this->attendanceFor($user);

        $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, $this->input())
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_at' => '2026-11-09 00:00:00',
            'clock_out_at' => '2026-11-09 09:00:00',
        ]);

        $this->get('/attendance/' . $attendance->id)
            ->assertOk()
            ->assertSee('承認待ちのため修正できません');
    }

    public function test_user_cannot_submit_duplicate_pending_request(): void
    {
        $user = User::factory()->create();
        $attendance = $this->attendanceFor($user);

        $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, $this->input())
            ->assertRedirect();

        $this->post('/attendance/' . $attendance->id, $this->input())
            ->assertSessionHasErrors('comment');

        $this->assertDatabaseCount('stamp_correction_requests', 1);
    }

    public function test_user_cannot_correct_another_users_attendance(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $attendance = $this->attendanceFor($other);

        $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, $this->input())
            ->assertNotFound();

        $this->assertDatabaseCount('stamp_correction_requests', 0);
    }

    public function test_guest_cannot_submit_correction(): void
    {
        $user = User::factory()->create();
        $attendance = $this->attendanceFor($user);

        $this->post('/attendance/' . $attendance->id, $this->input())
            ->assertRedirect(route('login'));
    }

    public function test_admin_cannot_submit_user_correction(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $attendance = $this->attendanceFor(
            User::factory()->create()
        );

        $this->actingAs($admin)
            ->post('/attendance/' . $attendance->id, $this->input())
            ->assertForbidden();
    }

    public function test_comment_is_required(): void
    {
        $user = User::factory()->create();
        $attendance = $this->attendanceFor($user);

        $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, $this->input([
                'comment' => '',
            ]))
            ->assertSessionHasErrors('comment');

        $this->assertDatabaseCount('stamp_correction_requests', 0);
    }

    public function test_equal_clock_in_and_out_are_rejected(): void
    {
        $user = User::factory()->create();
        $attendance = $this->attendanceFor($user);

        $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, $this->input([
                'new_clock_in' => '09:00',
                'new_clock_out' => '09:00',
            ]))
            ->assertSessionHasErrors('new_clock_out');
    }

    public function test_break_outside_working_hours_is_rejected(): void
    {
        $user = User::factory()->create();
        $attendance = $this->attendanceFor($user);

        $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, $this->input([
                'new_break_in' => ['08:00'],
                'new_break_out' => ['08:30'],
            ]))
            ->assertSessionHasErrors('new_break_in.0');

        $this->assertDatabaseCount('stamp_correction_requests', 0);
    }

    public function test_overnight_correction_is_saved_in_utc(): void
    {
        $user = User::factory()->create();
        $attendance = $this->attendanceFor($user);

        $this->actingAs($user)
            ->post('/attendance/' . $attendance->id, $this->input([
                'new_clock_in' => '23:00',
                'new_clock_out' => '02:00',
                'new_break_in' => ['23:50'],
                'new_break_out' => ['00:10'],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('stamp_correction_requests', [
            'attendance_id' => $attendance->id,
            'requested_clock_in_at' => '2026-11-09 14:00:00',
            'requested_clock_out_at' => '2026-11-09 17:00:00',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('correction_request_breaks', [
            'started_at' => '2026-11-09 14:50:00',
            'ended_at' => '2026-11-09 15:10:00',
        ]);
    }
}
