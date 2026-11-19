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

    public function test_second_user_can_clock_in_on_a_day_seeded_for_first_user(): void
    {
        $this->travelTo(CarbonImmutable::parse(
            '2026-11-10 10:00:00',
            'Asia/Tokyo'
        ));
        $this->seed(SubmissionSampleSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('attendances', 102);
        $this->assertDatabaseCount('break_times', 102);

        $firstUser = User::where('email', 'user1@example.com')->firstOrFail();
        $secondUser = User::where('email', 'user2@example.com')->firstOrFail();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $firstUser->id,
            'work_date' => '2026-11-10',
        ]);
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $secondUser->id,
            'work_date' => '2026-11-10',
        ]);

        $this->actingAs($secondUser)
            ->post('/attendance', ['action' => 'clock_in'])
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $secondUser->id,
            'work_date' => '2026-11-10',
            'clock_in_at' => '2026-11-10 01:00:00',
            'clock_out_at' => null,
        ]);
    }

    public function test_month_end_seeding_keeps_each_of_the_six_months(): void
    {
        $this->travelTo(CarbonImmutable::parse(
            '2026-03-31 10:00:00',
            'Asia/Tokyo'
        ));
        $this->seed(SubmissionSampleSeeder::class);

        $user = User::where('email', 'user1@example.com')->firstOrFail();

        foreach (['2025-10', '2025-11', '2025-12', '2026-01', '2026-02', '2026-03'] as $month) {
            $expected = $month === '2026-03' ? 17 : 15;
            $this->assertSame($expected, $user->attendances()
                ->where('work_date', 'like', $month.'%')->count());
        }
    }

    public function test_running_twice_does_not_duplicate_data(): void
    {
        $this->seed(SubmissionSampleSeeder::class);
        $this->seed(SubmissionSampleSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('attendances', 102);
        $this->assertDatabaseCount('break_times', 102);
    }

    public function test_default_database_seeder_creates_submission_data(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('attendances', 102);
        $this->assertDatabaseCount('break_times', 102);

        $this->assertDatabaseHas('users', [
            'email' => 'user1@example.com',
            'role' => 'user',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'user2@example.com',
            'role' => 'user',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'user3@example.com',
            'role' => 'admin',
        ]);
    }
}
