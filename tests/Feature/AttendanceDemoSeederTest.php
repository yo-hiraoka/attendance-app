<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\CorrectionRequestBreak;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Database\Seeders\AttendanceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_expected_data(): void
    {
        $this->seed(AttendanceDemoSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('attendances', 3);
        $this->assertDatabaseCount('break_times', 3);
        $this->assertDatabaseCount('stamp_correction_requests', 2);
        $this->assertDatabaseCount('correction_request_breaks', 2);

        $this->assertEquals(
            1,
            User::where('role', 'admin')->count()
        );

        $this->assertEquals(
            2,
            User::where('role', 'user')->count()
        );

        $this->assertEquals(
            1,
            StampCorrectionRequest::where('status', 'pending')->count()
        );

        $this->assertEquals(
            1,
            StampCorrectionRequest::where('status', 'approved')->count()
        );
    }

    public function test_seeder_does_not_create_duplicate_data(): void
    {
        $this->seed(AttendanceDemoSeeder::class);
        $this->seed(AttendanceDemoSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('attendances', 3);
        $this->assertDatabaseCount('break_times', 3);
        $this->assertDatabaseCount('stamp_correction_requests', 2);
        $this->assertDatabaseCount('correction_request_breaks', 2);
    }

    public function test_correction_requests_belong_to_attendance_users(): void
    {
        $this->seed(AttendanceDemoSeeder::class);

        $requests = StampCorrectionRequest::with([
            'attendance',
            'user',
            'proposalBreaks',
        ])->get();

        foreach ($requests as $request) {
            $this->assertEquals(
                $request->attendance->user_id,
                $request->user_id
            );

            $this->assertNotNull($request->user);

            $this->assertCount(
                1,
                $request->proposalBreaks
            );
        }

        $approved = StampCorrectionRequest::where(
            'status',
            'approved'
        )->firstOrFail();

        $this->assertNotNull($approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        $this->assertTrue($approved->approver->isAdmin());

        $pending = StampCorrectionRequest::where(
            'status',
            'pending'
        )->firstOrFail();

        $this->assertNull($pending->approved_by);
        $this->assertNull($pending->approved_at);
    }

    public function test_demo_attendance_has_multiple_breaks(): void
    {
        $this->seed(AttendanceDemoSeeder::class);

        $attendance = Attendance::whereHas('user', function ($query) {
            $query->where('email', 'demo-user1@example.com');
        })->where('work_date', '2026-11-02')->firstOrFail();

        $this->assertEquals(2, $attendance->breaks()->count());

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in_at' => '2026-11-02 00:00:00',
            'clock_out_at' => '2026-11-02 09:00:00',
        ]);
    }
}
