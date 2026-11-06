<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\CorrectionRequestBreak;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_and_break_relationships(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-11-06',
            'clock_in_at' => '2026-11-06 00:00:00',
            'clock_out_at' => '2026-11-06 09:00:00',
        ]);

        $break = BreakTime::create([
            'attendance_id' => $attendance->id,
            'started_at' => '2026-11-06 03:00:00',
            'ended_at' => '2026-11-06 04:00:00',
        ]);

        $this->assertEquals($user->id, $attendance->user->id);
        $this->assertEquals($attendance->id, $break->attendance->id);
        $this->assertTrue($user->attendances->contains($attendance));
        $this->assertTrue($attendance->breaks->contains($break));
    }

    public function test_correction_request_relationships(): void
    {
        $user = User::factory()->create();

        $admin = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => '2026-11-06',
            'clock_in_at' => '2026-11-06 00:00:00',
            'clock_out_at' => '2026-11-06 09:00:00',
        ]);

        $request = StampCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_clock_in_at' => '2026-11-06 00:30:00',
            'requested_clock_out_at' => '2026-11-06 09:30:00',
            'reason' => '打刻時刻の修正',
            'status' => 'pending',
        ]);

        $request->approved_by = $admin->id;
        $request->save();

        $break = CorrectionRequestBreak::create([
            'correction_request_id' => $request->id,
            'started_at' => '2026-11-06 03:00:00',
            'ended_at' => '2026-11-06 04:00:00',
        ]);

        $this->assertEquals($attendance->id, $request->attendance->id);
        $this->assertEquals($user->id, $request->user->id);
        $this->assertEquals($admin->id, $request->approver->id);
        $this->assertEquals($request->id, $break->correctionRequest->id);

        $this->assertTrue(
            $attendance->correctionRequests->contains($request)
        );

        $this->assertTrue(
            $request->proposalBreaks->contains($break)
        );
    }

    public function test_user_role_defaults_to_user(): void
    {
        $user = User::factory()->create();

        $user->refresh();

        $this->assertEquals('user', $user->role);
        $this->assertFalse($user->isAdmin());
    }
}
