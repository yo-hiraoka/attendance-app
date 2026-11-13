<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(
            CarbonImmutable::parse(
                '2026-11-12 10:00:00',
                'Asia/Tokyo'
            )
        );
    }

    private function createAttendance(
        User $user,
        string $date,
        ?string $clockIn,
        ?string $clockOut
    ): Attendance {
        return Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $date,
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
        ]);
    }

    private function createBreak(
        Attendance $attendance,
        string $start,
        string $end
    ): void {
        BreakTime::factory()->create([
            'attendance_id' => $attendance->id,
            'started_at' => $start,
            'ended_at' => $end,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/attendance/report')
            ->assertRedirect(route('login'));
    }

    public function test_unverified_user_cannot_view_report(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/attendance/report')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_admin_cannot_view_report(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)
            ->get('/attendance/report')
            ->assertForbidden();
    }

    public function test_user_can_view_report_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/attendance/report')
            ->assertOk()
            ->assertSee('マイ勤怠レポート')
            ->assertSee('基本サマリー')
            ->assertSee('月次推移')
            ->assertSee('異常検知');
    }

    public function test_work_overtime_and_average_are_correct(): void
    {
        $user = User::factory()->create();

        // 09:00〜18:00、休憩1時間 → 実働8時間
        $first = $this->createAttendance(
            $user,
            '2026-11-09',
            '2026-11-09 00:00:00',
            '2026-11-09 09:00:00'
        );

        $this->createBreak(
            $first,
            '2026-11-09 03:00:00',
            '2026-11-09 04:00:00'
        );

        // 09:00〜20:00、休憩1時間 → 実働10時間
        $second = $this->createAttendance(
            $user,
            '2026-11-10',
            '2026-11-10 00:00:00',
            '2026-11-10 11:00:00'
        );

        $this->createBreak(
            $second,
            '2026-11-10 03:00:00',
            '2026-11-10 04:00:00'
        );

        $this->actingAs($user)
            ->get('/attendance/report')
            ->assertOk()
            ->assertViewHas('summary', function ($summary): bool {
                return $summary['work_duration'] === '18:00'
                    && $summary['overtime_duration'] === '2:00'
                    && $summary['average_duration'] === '9:00'
                    && $summary['completed_days'] === 2;
            });
    }

    public function test_monthly_trend_contains_six_months(): void
    {
        $user = User::factory()->create();

        $this->createAttendance(
            $user,
            '2026-10-09',
            '2026-10-09 00:00:00',
            '2026-10-09 09:00:00'
        );

        $this->createAttendance(
            $user,
            '2026-11-09',
            '2026-11-09 00:00:00',
            '2026-11-09 09:00:00'
        );

        $this->actingAs($user)
            ->get('/attendance/report')
            ->assertViewHas(
                'monthly_trend',
                function ($trend): bool {
                    return count($trend) === 6
                        && $trend[0]['month'] === '2026-06'
                        && $trend[0]['work_minutes'] === 0
                        && $trend[4]['month'] === '2026-10'
                        && $trend[4]['work_duration'] === '9:00'
                        && $trend[5]['month'] === '2026-11'
                        && $trend[5]['work_duration'] === '9:00';
                }
            );
    }

    public function test_anomalies_are_counted_correctly(): void
    {
        $user = User::factory()->create();

        // 09:30〜18:00 → 遅刻
        $this->createAttendance(
            $user,
            '2026-11-09',
            '2026-11-09 00:30:00',
            '2026-11-09 09:00:00'
        );

        // 09:00〜17:00 → 早退
        $this->createAttendance(
            $user,
            '2026-11-10',
            '2026-11-10 00:00:00',
            '2026-11-10 08:00:00'
        );

        // 08:00〜21:00、休憩1時間 → 実働12時間
        $long = $this->createAttendance(
            $user,
            '2026-11-11',
            '2026-11-10 23:00:00',
            '2026-11-11 12:00:00'
        );

        $this->createBreak(
            $long,
            '2026-11-11 03:00:00',
            '2026-11-11 04:00:00'
        );

        $this->actingAs($user)
            ->get('/attendance/report')
            ->assertViewHas(
                'anomalies',
                fn ($anomalies): bool => $anomalies['late'] === 1
                    && $anomalies['early'] === 1
                    && $anomalies['long_hours'] === 1
            );
    }

    public function test_other_users_data_is_excluded(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->createAttendance(
            $other,
            '2026-11-09',
            '2026-11-09 00:00:00',
            '2026-11-09 09:00:00'
        );

        $this->actingAs($user)
            ->get('/attendance/report')
            ->assertViewHas(
                'summary',
                fn ($summary): bool => $summary['work_seconds'] === 0
                    && $summary['completed_days'] === 0
            );
    }

    public function test_empty_report_is_safe(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/attendance/report')
            ->assertOk()
            ->assertViewHas(
                'summary',
                fn ($summary): bool => $summary['work_duration'] === '0:00'
                    && $summary['overtime_duration'] === '0:00'
                    && $summary['average_duration'] === '0:00'
            )
            ->assertViewHas(
                'monthly_trend',
                fn ($trend): bool => count($trend) === 6
                    && collect($trend)->every(
                        fn ($month): bool => $month['work_minutes'] === 0
                    )
            );
    }

    public function test_incomplete_attendance_is_excluded_from_work_hours(): void
    {
        $user = User::factory()->create();

        $this->createAttendance(
            $user,
            '2026-11-09',
            '2026-11-09 00:30:00',
            null
        );

        $this->actingAs($user)
            ->get('/attendance/report')
            ->assertViewHas(
                'summary',
                fn ($summary): bool => $summary['work_seconds'] === 0
                    && $summary['completed_days'] === 0
            )
            ->assertViewHas(
                'anomalies',
                fn ($anomalies): bool => $anomalies['late'] === 1
                    && $anomalies['early'] === 0
            );
    }

    public function test_report_uses_provided_template(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/attendance/report')
            ->assertOk()
            ->assertViewIs('reports.index')
            ->assertSee('基本サマリー')
            ->assertSee('月次推移')
            ->assertSee('今月の異常検知');
    }

    public function test_monthly_trend_is_displayed_newest_first(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/attendance/report')
            ->assertOk()
            ->assertViewHas(
                'monthlyTrend',
                function ($trend): bool {
                    return count($trend) === 6
                        && $trend[0]['month'] === '2026-11'
                        && $trend[5]['month'] === '2026-06';
                }
            );
    }
}
