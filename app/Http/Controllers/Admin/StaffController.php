<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->where('role', 'user')
            ->orderBy('id')
            ->get();

        return view('admin.staff-list', [
            'users' => $users,
        ]);
    }

    public function attendance(Request $request, int $id): View
    {
        $user = $this->findStaff($id);
        $date = $this->selectedMonth($request);

        return view('admin.staff-attendance-list', [
            'user' => $user,
            'date' => $date,
            'previousMonth' => $date->subMonth()->format('Y-m'),
            'nextMonth' => $date->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $this->monthlyRecords($user, $date),
        ]);
    }

    public function export(Request $request, int $id): StreamedResponse
    {
        $user = $this->findStaff($id);
        $date = $this->selectedMonth($request);
        $records = $this->monthlyRecords($user, $date);

        $filename = sprintf(
            'attendance_staff_%d_%s.csv',
            $user->id,
            $date->format('Y-m')
        );

        return response()->streamDownload(
            function () use ($records): void {
                $handle = fopen('php://output', 'wb');

                if ($handle === false) {
                    throw new \RuntimeException(
                        'CSV出力を開始できませんでした。'
                    );
                }

                // Excelでの日本語文字化けを防ぐUTF-8 BOM
                fwrite($handle, "\xEF\xBB\xBF");

                fputcsv($handle, [
                    '日付',
                    '出勤',
                    '退勤',
                    '休憩',
                    '合計',
                ]);

                foreach ($records as $record) {
                    fputcsv($handle, [
                        $record['date'],
                        $record['clock_in'],
                        $record['clock_out'],
                        $record['total_break_time'],
                        $record['total_time'],
                    ]);
                }

                fclose($handle);
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    private function findStaff(int $id): User
    {
        return User::query()
            ->where('role', 'user')
            ->findOrFail($id);
    }

    private function selectedMonth(Request $request): CarbonImmutable
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'required', 'date_format:Y-m'],
        ]);

        $month = $validated['date']
            ?? CarbonImmutable::now('Asia/Tokyo')->format('Y-m');

        return CarbonImmutable::createFromFormat(
            '!Y-m',
            $month,
            'Asia/Tokyo'
        );
    }

    /**
     * @return Collection<int, array<string, int|string>>
     */
    private function monthlyRecords(
        User $user,
        CarbonImmutable $date
    ): Collection {
        $records = Attendance::query()
            ->with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [
                $date->startOfMonth()->toDateString(),
                $date->endOfMonth()->toDateString(),
            ])
            ->orderBy('work_date')
            ->get();

        return $records->map(function (Attendance $attendance): array {
            $workDate = CarbonImmutable::parse(
                $attendance->getRawOriginal('work_date'),
                'Asia/Tokyo'
            );

            $clockIn = $attendance->getRawOriginal('clock_in_at');
            $clockOut = $attendance->getRawOriginal('clock_out_at');

            $breakSeconds = $attendance->breaks->sum(
                fn ($break): int => $this->secondsBetween(
                    $break->getRawOriginal('started_at'),
                    $break->getRawOriginal('ended_at')
                )
            );

            $workSeconds = $clockOut === null
                ? null
                : max(
                    0,
                    $this->secondsBetween($clockIn, $clockOut)
                        - $breakSeconds
                );

            return [
                'id' => $attendance->id,
                'date' => $workDate->locale('ja')
                    ->isoFormat('MM/DD(ddd)'),
                'clock_in' => $this->jstTime($clockIn),
                'clock_out' => $this->jstTime($clockOut),
                'total_break_time' => $this->duration($breakSeconds),
                'total_time' => $workSeconds === null
                    ? ''
                    : $this->duration($workSeconds),
            ];
        });
    }

    private function jstTime(?string $utc): string
    {
        if ($utc === null) {
            return '';
        }

        return CarbonImmutable::parse($utc, 'UTC')
            ->setTimezone('Asia/Tokyo')
            ->format('H:i');
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
        $minutes = intdiv($seconds, 60);

        return sprintf(
            '%d:%02d',
            intdiv($minutes, 60),
            $minutes % 60
        );
    }
}
