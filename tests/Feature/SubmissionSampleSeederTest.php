<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceReportService;
use Carbon\CarbonImmutable;
use Database\Seeders\SubmissionSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionSampleSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(
            '2026-11-25 10:00:00',
            'Asia/Tokyo'
        ));
    }

    public function test_it_creates_the_required_users_and_attendances(): void
    {
        $this->seed(SubmissionSampleSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('attendances', 102);
        $this->assertDatabaseCount('break_times', 102);

        $user = User::where('email', 'user1@example.com')->firstOrFail();
        $this->assertEquals(92, $user->attendances()->count());
        $this->assertNotNull($user->email_verified_at);

        $admin = User::where('email', 'user3@example.com')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertNotNull($admin->email_verified_at);

        $attendance = Attendance::where('user_id', $user->id)
            ->orderBy('work_date')->firstOrFail();
        $this->assertNotNull($attendance->breaks()->first());
    }

    public function test_report_totals_match_the_assignment(): void
    {
        $this->seed(SubmissionSampleSeeder::class);

        $user = User::where('email', 'user1@example.com')->firstOrFail();
        $report = app(AttendanceReportService::class)->generate($user);

        $this->assertSame(92, $report['summary']['completed_days']);
        $this->assertSame(744 * 3600, $report['summary']['work_seconds']);
        $this->assertSame(10 * 3600, $report['summary']['overtime_seconds']);
        $this->assertSame('744:00', $report['summary']['work_duration']);
        $this->assertSame('10:00', $report['summary']['overtime_duration']);
        $this->assertSame('8:05', $report['summary']['average_duration']);
        $this->assertSame(2, $report['anomalies']['late']);
        $this->assertSame(1, $report['anomalies']['early']);
        $this->assertSame(1, $report['anomalies']['long_hours']);
    }

    public function test_running_twice_does_not_duplicate_data(): void
    {
        $this->seed(SubmissionSampleSeeder::class);
        $this->seed(SubmissionSampleSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('attendances', 102);
        $this->assertDatabaseCount('break_times', 102);
    }
}
