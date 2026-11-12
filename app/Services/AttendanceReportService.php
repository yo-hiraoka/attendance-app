<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;

class AttendanceReportService
{
    /**
     * @return array<string, mixed>
     */
    public function generate(User $user): array
    {
        $now = CarbonImmutable::now('Asia/Tokyo');
        $currentMonth = $now->format('Y-m');
        $start = $now->startOfMonth()->subMonths(5);
        $end = $now->endOfMonth();

        $monthly = [];

        for ($i = 0; $i < 6; $i++) {
            $month = $start->addMonths($i);

            $monthly[$month->format('Y-m')] = [
                'label' => $month->format('Y年n月'),
                'work_seconds' => 0,
                'overtime_seconds' => 0,
            ];
        }

        $attendances = Attendance::query()
            ->with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->orderBy('work_date')
            ->get();

        $totalWorkSeconds = 0;
        $totalOvertimeSeconds = 0;
        $completedDays = 0;

        $anomalies = [
            'late' => 0,
            'early' => 0,
            'long_hours' => 0,
        ];

        foreach ($attendances as $attendance) {
            $workDate = CarbonImmutable::parse(
                $attendance->getRawOriginal('work_date'),
                'Asia/Tokyo'
            );

            $monthKey = $workDate->format('Y-m');

            $clockIn = $attendance->getRawOriginal('clock_in_at');
            $clockOut = $attendance->getRawOriginal('clock_out_at');

            if ($monthKey === $currentMonth && $clockIn !== null) {
                $startAtJst = CarbonImmutable::parse($clockIn, 'UTC')
                    ->setTimezone('Asia/Tokyo');

                if ($startAtJst->format('H:i:s') > '09:00:00') {
                    $anomalies['late']++;
                }
            }

            // 退勤していない勤怠は、勤務時間の計算対象外
            if ($clockIn === null || $clockOut === null) {
                continue;
            }

            $breakSeconds = 0;

            foreach ($attendance->breaks as $break) {
                $breakSeconds += $this->secondsBetween(
                    $break->getRawOriginal('started_at'),
                    $break->getRawOriginal('ended_at')
                );
            }

            $workSeconds = max(
                0,
                $this->secondsBetween($clockIn, $clockOut)
                    - $breakSeconds
            );

            $overtimeSeconds = max(
                0,
                $workSeconds - 8 * 3600
            );

            $totalWorkSeconds += $workSeconds;
            $totalOvertimeSeconds += $overtimeSeconds;
            $completedDays++;

            $monthly[$monthKey]['work_seconds'] += $workSeconds;
            $monthly[$monthKey]['overtime_seconds'] += $overtimeSeconds;

            if ($monthKey === $currentMonth) {
                $endAtJst = CarbonImmutable::parse($clockOut, 'UTC')
                    ->setTimezone('Asia/Tokyo');

                if ($endAtJst->format('H:i:s') < '18:00:00') {
                    $anomalies['early']++;
                }

                if ($workSeconds > 10 * 3600) {
                    $anomalies['long_hours']++;
                }
            }
        }

        $averageSeconds = $completedDays > 0
            ? (int) round($totalWorkSeconds / $completedDays)
            : 0;

        $monthlyTrend = [];

        foreach ($monthly as $month => $values) {
            $monthlyTrend[] = [
                'month' => $month,
                'label' => $values['label'],
                'work_minutes' => intdiv($values['work_seconds'], 60),
                'overtime_minutes' => intdiv(
                    $values['overtime_seconds'],
                    60
                ),
                'work_duration' => $this->duration(
                    $values['work_seconds']
                ),
                'overtime_duration' => $this->duration(
                    $values['overtime_seconds']
                ),
            ];
        }

        return [
            'period' => [
                'start' => $start->format('Y年n月'),
                'end' => $end->format('Y年n月'),
                'current_month' => $now->format('Y年n月'),
            ],
            'summary' => [
                'work_seconds' => $totalWorkSeconds,
                'overtime_seconds' => $totalOvertimeSeconds,
                'completed_days' => $completedDays,
                'work_duration' => $this->duration($totalWorkSeconds),
                'overtime_duration' => $this->duration(
                    $totalOvertimeSeconds
                ),
                'average_duration' => $this->duration(
                    $averageSeconds
                ),
            ],
            'monthly_trend' => $monthlyTrend,
            'anomalies' => $anomalies,
        ];
    }

    private function secondsBetween(
        ?string $start,
        ?string $end
    ): int {
        if ($start === null || $end === null) {
            return 0;
        }

        $startAt = CarbonImmutable::parse($start, 'UTC');
        $endAt = CarbonImmutable::parse($end, 'UTC');

        return max(
            0,
            $endAt->getTimestamp() - $startAt->getTimestamp()
        );
    }

    private function duration(int $seconds): string
    {
        $minutes = (int) round($seconds / 60);

        return sprintf(
            '%d:%02d',
            intdiv($minutes, 60),
            $minutes % 60
        );
    }
}
