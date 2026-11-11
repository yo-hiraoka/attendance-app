<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\CorrectionRequestBreak;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCorrectionApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function createPendingRequest(): StampCorrectionRequest
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

        $application = StampCorrectionRequest::factory()->create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_clock_in_at' => '2026-11-09 00:30:00',
            'requested_clock_out_at' => '2026-11-09 09:30:00',
            'reason' => '打刻を修正してください',
            'status' => 'pending',
        ]);

        CorrectionRequestBreak::factory()->create([
            'correction_request_id' => $application->id,
            'started_at' => '2026-11-09 03:15:00',
            'ended_at' => '2026-11-09 03:45:00',
        ]);

        CorrectionRequestBreak::factory()->create([
            'correction_request_id' => $application->id,
            'started_at' => '2026-11-09 06:00:00',
            'ended_at' => '2026-11-09 06:15:00',
        ]);

        return $application;
    }

    public function test_guest_cannot_access_admin_application_pages(): void
    {
        $this->get('/admin/stamp_correction_request/list')
            ->assertRedirect(route('login'));

        $this->get('/admin/stamp_correction_request/approve/1')
            ->assertRedirect(route('login'));

        $this->post('/admin/stamp_correction_request/approve/1')
            ->assertRedirect(route('login'));
    }

    public function test_regular_user_cannot_approve_request(): void
    {
        $application = $this->createPendingRequest();

        $this->actingAs($application->user)
            ->get(route('admin.correction.list'))
            ->assertForbidden();

        $this->get(route('admin.correction.show', $application->id))
            ->assertForbidden();

        $this->post(route('admin.correction.approve', $application->id))
            ->assertForbidden();

        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $application->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_view_all_pending_requests(): void
    {
        $admin = $this->createAdmin();
        $application = $this->createPendingRequest();

        $this->actingAs($admin)
            ->get(route('admin.correction.list'))
            ->assertOk()
            ->assertSee('申請一覧')
            ->assertSee('承認待ち')
            ->assertSee('打刻を修正してください')
            ->assertSee($application->user->name)
            ->assertSee(
                route('admin.correction.show', $application->id)
            );
    }

    public function test_admin_can_view_requested_times_in_jst(): void
    {
        $admin = $this->createAdmin();
        $application = $this->createPendingRequest();

        $this->actingAs($admin)
            ->get(route('admin.correction.show', $application->id))
            ->assertOk()
            ->assertSee('09:30')
            ->assertSee('18:30')
            ->assertSee('12:15')
            ->assertSee('12:45')
            ->assertSee('15:00')
            ->assertSee('15:15')
            ->assertSee('打刻を修正してください')
            ->assertSee('承認');
    }

    public function test_approval_updates_attendance_and_breaks(): void
    {
        $admin = $this->createAdmin();
        $application = $this->createPendingRequest();

        $this->actingAs($admin)
            ->post(route('admin.correction.approve', $application->id))
            ->assertRedirect(
                route('admin.correction.show', $application->id)
            );

        $this->assertDatabaseHas('attendances', [
            'id' => $application->attendance_id,
            'clock_in_at' => '2026-11-09 00:30:00',
            'clock_out_at' => '2026-11-09 09:30:00',
            'note' => '打刻を修正してください',
        ]);

        $this->assertDatabaseCount('break_times', 2);

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $application->attendance_id,
            'started_at' => '2026-11-09 03:15:00',
            'ended_at' => '2026-11-09 03:45:00',
        ]);

        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $application->id,
            'status' => 'approved',
            'approved_by' => $admin->id,
        ]);

        $this->assertNotNull(
            $application->fresh()->approved_at
        );
    }

    public function test_approval_cannot_be_repeated(): void
    {
        $admin = $this->createAdmin();
        $application = $this->createPendingRequest();

        $this->actingAs($admin)
            ->post(route('admin.correction.approve', $application->id))
            ->assertRedirect();

        $this->post(route('admin.correction.approve', $application->id))
            ->assertSessionHasErrors('approval');

        $this->assertDatabaseCount('break_times', 2);
    }

    public function test_approved_request_appears_in_approved_tab(): void
    {
        $admin = $this->createAdmin();
        $application = $this->createPendingRequest();

        $this->actingAs($admin)
            ->post(route('admin.correction.approve', $application->id))
            ->assertRedirect();

        $this->get(route('admin.correction.list'))
            ->assertOk()
            ->assertSee('承認済み')
            ->assertSee('打刻を修正してください');

        $this->get(route('admin.correction.show', $application->id))
            ->assertOk()
            ->assertSee('承認済み')
            ->assertDontSee('type="submit"');
    }

    public function test_approved_times_are_visible_to_staff(): void
    {
        $admin = $this->createAdmin();
        $application = $this->createPendingRequest();

        $this->actingAs($admin)
            ->post(route('admin.correction.approve', $application->id))
            ->assertRedirect();

        $this->actingAs($application->user)
            ->get(route('attendance.show', $application->attendance_id))
            ->assertOk()
            ->assertSee('09:30')
            ->assertSee('18:30')
            ->assertSee('12:15')
            ->assertSee('12:45');

        $this->get(route('correction-request.list'))
            ->assertOk()
            ->assertSee('承認済み');
    }

    public function test_invalid_requested_times_are_not_approved(): void
    {
        $admin = $this->createAdmin();
        $application = $this->createPendingRequest();

        $application->forceFill([
            'requested_clock_out_at' => '2026-11-08 09:00:00',
        ])->save();

        $this->actingAs($admin)
            ->post(route('admin.correction.approve', $application->id))
            ->assertSessionHasErrors('approval');

        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $application->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $application->attendance_id,
            'clock_in_at' => '2026-11-09 00:00:00',
            'clock_out_at' => '2026-11-09 09:00:00',
        ]);

        $this->assertDatabaseCount('break_times', 1);
    }

    public function test_invalid_breaks_are_not_approved(): void
    {
        $admin = $this->createAdmin();
        $application = $this->createPendingRequest();

        $application->proposalBreaks()->firstOrFail()
            ->forceFill([
                'started_at' => '2026-11-09 00:00:00',
            ])->save();

        $this->actingAs($admin)
            ->post(route('admin.correction.approve', $application->id))
            ->assertSessionHasErrors('approval');

        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $application->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('break_times', 1);
    }

    public function test_missing_application_returns_404(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('admin.correction.show', 999999))
            ->assertNotFound();

        $this->post(route('admin.correction.approve', 999999))
            ->assertNotFound();
    }
}
