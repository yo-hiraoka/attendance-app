<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CorrectionRequestListTest extends TestCase
{
    use RefreshDatabase;

private function createCorrectionRequest(
    User $user,
    string $status,
    string $reason,
    string $workDate = '2026-11-09'
): StampCorrectionRequest {
    $attendance = Attendance::factory()->create([
        'user_id' => $user->id,
        'work_date' => $workDate,
    ]);

    return StampCorrectionRequest::factory()->create([
        'attendance_id' => $attendance->id,
        'user_id' => $user->id,
        'status' => $status,
        'reason' => $reason,
    ]);
}

    public function test_guest_cannot_view_application_list(): void
    {
        $this->get('/stamp_correction_request/list')
            ->assertRedirect(route('login'));
    }

    public function test_unverified_user_cannot_view_application_list(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/stamp_correction_request/list')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_admin_cannot_view_user_application_list(): void
    {
        $admin = User::factory()->create();

        $admin->forceFill([
            'role' => 'admin',
        ])->save();

        $this->actingAs($admin)
            ->get('/stamp_correction_request/list')
            ->assertForbidden();
    }

    public function test_user_can_view_pending_and_approved_applications(): void
    {
        $user = User::factory()->create();

        $pending = $this->createCorrectionRequest(
            $user,
            'pending',
            '出勤時間を修正したい'
        );

        $approved = $this->createCorrectionRequest(
            $user,
            'approved',
            '退勤時間を修正したい',
            '2026-11-10'
        );

        $this->actingAs($user)
            ->get('/stamp_correction_request/list')
            ->assertOk()
            ->assertSee('申請一覧')
            ->assertSee('承認待ち')
            ->assertSee('承認済み')
            ->assertSee('出勤時間を修正したい')
            ->assertSee('退勤時間を修正したい')
            ->assertSee(
                route('attendance.show', [
                    'id' => $pending->attendance_id,
                ])
            )
            ->assertSee(
                route('attendance.show', [
                    'id' => $approved->attendance_id,
                ])
            );
    }

    public function test_user_cannot_view_other_users_applications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->createCorrectionRequest(
            $user,
            'pending',
            '本人の申請理由'
        );

        $this->createCorrectionRequest(
            $other,
            'pending',
            '他人だけの申請理由'
        );

        $this->actingAs($user)
            ->get('/stamp_correction_request/list')
            ->assertOk()
            ->assertSee('本人の申請理由')
            ->assertDontSee('他人だけの申請理由');
    }

    public function test_application_dates_are_displayed_in_jst(): void
    {
        $user = User::factory()->create();

        $application = $this->createCorrectionRequest(
            $user,
            'pending',
            '日時表示確認'
        );

        DB::table('stamp_correction_requests')
            ->where('id', $application->id)
            ->update([
                'created_at' => '2026-11-09 15:30:00',
            ]);

        $this->actingAs($user)
            ->get('/stamp_correction_request/list')
            ->assertOk()
            ->assertSee('2026/11/09')
            ->assertSee('2026/11/10 00:30');
    }

    public function test_user_can_view_empty_application_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/stamp_correction_request/list')
            ->assertOk()
            ->assertSee('申請一覧')
            ->assertSee('承認待ち')
            ->assertSee('承認済み');
    }

    public function test_application_detail_link_opens_own_attendance(): void
    {
        $user = User::factory()->create();

        $application = $this->createCorrectionRequest(
            $user,
            'pending',
            '詳細リンクの確認'
        );

        $this->actingAs($user)
            ->get('/stamp_correction_request/list')
            ->assertSee(
                route('attendance.show', [
                    'id' => $application->attendance_id,
                ])
            );

        $this->get('/attendance/' . $application->attendance_id)
            ->assertOk()
            ->assertSee('勤怠詳細')
            ->assertSee('承認待ちのため修正できません');
    }
}
