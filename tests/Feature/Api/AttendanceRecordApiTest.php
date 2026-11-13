<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\CorrectionRequestBreak;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceRecordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_get_attendance_list(): void
    {
        $user = User::factory()->create();

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-09',
        ]);

        $this->getJson('/api/v1/attendance-records')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-11-09')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_guest_can_get_attendance_detail(): void
    {
        $attendance = Attendance::factory()->create([
            'work_date' => '2026-11-09',
            'clock_in_at' => '2026-11-09 00:00:00',
            'clock_out_at' => '2026-11-09 09:00:00',
            'note' => 'APIテスト',
        ]);

        $this->getJson('/api/v1/attendance-records/'.$attendance->id)
            ->assertOk()
            ->assertJsonPath('data.id', $attendance->id)
            ->assertJsonPath('data.date', '2026-11-09')
            ->assertJsonPath('data.comment', 'APIテスト');
    }

    public function test_missing_attendance_returns_404(): void
    {
        $this->getJson('/api/v1/attendance-records/999999')
            ->assertNotFound()
            ->assertExactJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    public function test_api_converts_utc_times_to_jst_and_includes_breaks(): void
    {
        $attendance = Attendance::factory()->create([
            'work_date' => '2026-11-09',
            'clock_in_at' => '2026-11-09 00:00:00',
            'clock_out_at' => '2026-11-09 09:00:00',
        ]);

        BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'started_at' => '2026-11-09 03:00:00',
            'ended_at' => '2026-11-09 04:00:00',
        ]);

        $this->getJson('/api/v1/attendance-records/'.$attendance->id)
            ->assertOk()
            ->assertJsonPath(
                'data.clock_in',
                '09:00:00'
            )
            ->assertJsonPath(
                'data.clock_out',
                '18:00:00'
            )
            ->assertJsonPath(
                'data.breaks.0.start',
                '2026-11-09T12:00:00+09:00'
            )
            ->assertJsonPath(
                'data.breaks.0.end',
                '2026-11-09T13:00:00+09:00'
            );
    }

    public function test_attendance_list_can_be_filtered(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $target = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-09',
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-10-09',
        ]);

        Attendance::factory()->create([
            'user_id' => $other->id,
            'work_date' => '2026-11-09',
        ]);

        $this->getJson(
            '/api/v1/attendance-records?user_id='
            .$user->id.'&date=2026-11-09&month=2026-11'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_attendance_list_supports_pagination(): void
    {
        Attendance::factory()->count(3)->create();

        $this->getJson(
            '/api/v1/attendance-records?per_page=2&page=2'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_invalid_attendance_list_parameters_return_422(): void
    {
        $this->getJson(
            '/api/v1/attendance-records?month=2026-13&per_page=101'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'month',
                'per_page',
            ]);
    }

    public function test_api_returns_user_name_and_calculated_work_time(): void
    {
        $user = User::factory()->create([
            'name' => '山田太郎',
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-09',
            'clock_in_at' => '2026-11-09 00:00:00',
            'clock_out_at' => '2026-11-09 09:00:00',
        ]);

        BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'started_at' => '2026-11-09 03:00:00',
            'ended_at' => '2026-11-09 04:00:00',
        ]);

        $this->getJson('/api/v1/attendance-records')
            ->assertOk()
            ->assertJsonPath('data.0.user_name', '山田太郎')
            ->assertJsonPath('data.0.clock_in', '09:00:00')
            ->assertJsonPath('data.0.clock_out', '18:00:00')
            ->assertJsonPath('data.0.total_time', '08:00')
            ->assertJsonPath('data.0.total_break_time', '01:00');
    }

    public function test_detail_includes_user_and_correction_requests(): void
    {
        $user = User::factory()->create([
            'name' => '山田太郎',
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
        ]);

        StampCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_clock_in_at' => '2026-11-09 00:00:00',
            'requested_clock_out_at' => '2026-11-09 09:00:00',
            'reason' => '打刻修正',
            'status' => 'pending',
        ]);

        $this->getJson('/api/v1/attendance-records/'.$attendance->id)
            ->assertOk()
            ->assertJsonPath('data.user.name', '山田太郎')
            ->assertJsonCount(1, 'data.correction_requests')
            ->assertJsonPath(
                'data.correction_requests.0.reason',
                '打刻修正'
            )
            ->assertJsonPath(
                'data.correction_requests.0.status',
                'pending'
            );
    }

    public function test_guest_cannot_create_attendance(): void
    {
        $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-12',
            'clock_in' => '09:00:00',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_authenticated_user_can_create_attendance(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-12',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'API登録テスト',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.date', '2026-11-12')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => '2026-11-12',
            'clock_in_at' => '2026-11-12 00:00:00',
            'clock_out_at' => '2026-11-12 09:00:00',
            'note' => 'API登録テスト',
        ]);
    }

    public function test_duplicate_attendance_is_rejected(): void
    {
        $user = User::factory()->create();

        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-12',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-11-12',
            'clock_in' => '09:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date')
            ->assertJsonPath(
                'errors.date.0',
                'この日付の勤怠は既に登録されています。'
            );

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_owner_can_update_only_comment(): void
    {
        $user = User::factory()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-12',
            'clock_in_at' => '2026-11-12 00:00:00',
            'clock_out_at' => '2026-11-12 09:00:00',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            '/api/v1/attendance-records/'.$attendance->id,
            ['comment' => '備考だけ更新']
        )
            ->assertOk()
            ->assertJsonPath('data.comment', '備考だけ更新')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'note' => '備考だけ更新',
            'clock_in_at' => '2026-11-12 00:00:00',
            'clock_out_at' => '2026-11-12 09:00:00',
        ]);
    }

    public function test_owner_can_update_attendance_with_put(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-12',
        ]);

        Sanctum::actingAs($user);

        $this->putJson(
            '/api/v1/attendance-records/'.$attendance->id,
            [
                'clock_in' => '10:00:00',
                'clock_out' => '19:00:00',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.clock_in', '10:00:00')
            ->assertJsonPath('data.clock_out', '19:00:00');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_at' => '2026-11-12 01:00:00',
            'clock_out_at' => '2026-11-12 10:00:00',
        ]);
    }

    public function test_other_user_cannot_update_attendance(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($other);

        $this->patchJson(
            '/api/v1/attendance-records/'.$attendance->id,
            ['comment' => '不正な変更']
        )
            ->assertForbidden()
            ->assertExactJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);

        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
            'note' => '不正な変更',
        ]);
    }

    public function test_owner_can_delete_attendance(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson(
            '/api/v1/attendance-records/'.$attendance->id
        )->assertNoContent();

        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
        ]);
    }

    public function test_other_user_cannot_delete_attendance(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($other);

        $this->deleteJson(
            '/api/v1/attendance-records/'.$attendance->id
        )
            ->assertForbidden()
            ->assertExactJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
        ]);
    }

    public function test_guest_cannot_update_attendance(): void
    {
        $attendance = Attendance::factory()->create();

        $this->patchJson(
            '/api/v1/attendance-records/'.$attendance->id,
            ['comment' => '不正な更新']
        )
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
            'note' => '不正な更新',
        ]);
    }

    public function test_guest_cannot_delete_attendance(): void
    {
        $attendance = Attendance::factory()->create();

        $this->deleteJson(
            '/api/v1/attendance-records/'.$attendance->id
        )
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
        ]);
    }

    public function test_admin_can_update_another_users_attendance(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $admin->forceFill(['role' => 'admin'])->save();

        $attendance = Attendance::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson(
            '/api/v1/attendance-records/'.$attendance->id,
            ['comment' => '管理者による修正']
        )
            ->assertOk()
            ->assertJsonPath('data.comment', '管理者による修正');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $owner->id,
            'note' => '管理者による修正',
        ]);
    }

    public function test_admin_can_delete_another_users_attendance(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $admin->forceFill(['role' => 'admin'])->save();

        $attendance = Attendance::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson(
            '/api/v1/attendance-records/'.$attendance->id
        )->assertNoContent();

        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
        ]);
    }

    public function test_invalid_clock_out_is_rejected_on_update(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-12',
            'clock_in_at' => '2026-11-12 00:00:00',
            'clock_out_at' => '2026-11-12 09:00:00',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            '/api/v1/attendance-records/'.$attendance->id,
            ['clock_out' => '08:00:00']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('clock_out')
            ->assertJsonPath(
                'errors.clock_out.0',
                '退勤時刻は出勤時刻より後の時刻を指定してください。'
            );

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_out_at' => '2026-11-12 09:00:00',
        ]);
    }

    public function test_deleting_attendance_also_deletes_breaks(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
        ]);

        $break = BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson(
            '/api/v1/attendance-records/'.$attendance->id
        )->assertNoContent();

        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
        ]);

        $this->assertDatabaseMissing('break_times', [
            'id' => $break->id,
        ]);
    }

    public function test_other_user_gets_403_before_validation_errors(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs($other);

        $this->patchJson(
            '/api/v1/attendance-records/'.$attendance->id,
            [
                'date' => 'invalid-date',
                'clock_in' => 'invalid-time',
            ]
        )
            ->assertForbidden()
            ->assertExactJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);
    }

    public function test_guest_cannot_update_with_invalid_data(): void
    {
        $attendance = Attendance::factory()->create();

        $this->patchJson(
            '/api/v1/attendance-records/'.$attendance->id,
            ['clock_in' => 'invalid-time']
        )
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_updating_only_comment_preserves_overnight_times(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-12',
            'clock_in_at' => '2026-11-12 13:00:00',
            'clock_out_at' => '2026-11-12 21:00:00',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            '/api/v1/attendance-records/'.$attendance->id,
            ['comment' => '夜勤の備考変更']
        )
            ->assertOk()
            ->assertJsonPath('data.comment', '夜勤の備考変更');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_at' => '2026-11-12 13:00:00',
            'clock_out_at' => '2026-11-12 21:00:00',
        ]);
    }

    public function test_changing_work_date_keeps_breaks_aligned(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-12',
            'clock_in_at' => '2026-11-12 00:00:00',
            'clock_out_at' => '2026-11-12 09:00:00',
        ]);

        $break = BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'started_at' => '2026-11-12 03:00:00',
            'ended_at' => '2026-11-12 04:00:00',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            '/api/v1/attendance-records/'.$attendance->id,
            ['date' => '2026-11-13']
        )
            ->assertOk()
            ->assertJsonPath('data.date', '2026-11-13');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'work_date' => '2026-11-13',
            'clock_in_at' => '2026-11-13 00:00:00',
            'clock_out_at' => '2026-11-13 09:00:00',
        ]);

        $this->assertDatabaseHas('break_times', [
            'id' => $break->id,
            'started_at' => '2026-11-13 03:00:00',
            'ended_at' => '2026-11-13 04:00:00',
        ]);
    }

    public function test_overnight_clock_in_can_be_updated(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-12',
            'clock_in_at' => '2026-11-12 13:00:00',
            'clock_out_at' => '2026-11-12 21:00:00',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            '/api/v1/attendance-records/'.$attendance->id,
            ['clock_in' => '23:00:00']
        )
            ->assertOk()
            ->assertJsonPath('data.clock_in', '23:00:00');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_at' => '2026-11-12 14:00:00',
            'clock_out_at' => '2026-11-12 21:00:00',
        ]);
    }

    public function test_changing_work_date_also_shifts_correction_request_times(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2026-11-12',
            'clock_in_at' => '2026-11-12 00:00:00',
            'clock_out_at' => '2026-11-12 09:00:00',
        ]);

        $correction = StampCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_clock_in_at' => '2026-11-12 00:30:00',
            'requested_clock_out_at' => '2026-11-12 09:00:00',
            'reason' => '出勤時刻の修正',
            'status' => 'pending',
        ]);

        $proposalBreak = CorrectionRequestBreak::create([
            'correction_request_id' => $correction->id,
            'started_at' => '2026-11-12 03:00:00',
            'ended_at' => '2026-11-12 04:00:00',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson(
            '/api/v1/attendance-records/'.$attendance->id,
            ['date' => '2026-11-13']
        )
            ->assertOk()
            ->assertJsonPath('data.date', '2026-11-13');

        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $correction->id,
            'requested_clock_in_at' => '2026-11-13 00:30:00',
            'requested_clock_out_at' => '2026-11-13 09:00:00',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('correction_request_breaks', [
            'id' => $proposalBreak->id,
            'started_at' => '2026-11-13 03:00:00',
            'ended_at' => '2026-11-13 04:00:00',
        ]);
    }
}
