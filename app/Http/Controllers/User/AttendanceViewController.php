<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceViewController extends Controller
{
    public function index(Request $request): View
    {
        abort_if($request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'date' => ['sometimes', 'required', 'date_format:Y-m'],
        ]);

        $month = $validated['date']
            ?? CarbonImmutable::now('Asia/Tokyo')->format('Y-m');

        $date = CarbonImmutable::createFromFormat(
            '!Y-m',
            $month,
            'Asia/Tokyo'
        );

        $attendances = Attendance::with('breaks')
            ->where('user_id', $request->user()->id)
            ->whereBetween('work_date', [
                $date->startOfMonth()->toDateString(),
                $date->endOfMonth()->toDateString(),
            ])
            ->orderBy('work_date')
            ->get();

        $formattedAttendanceRecords = $attendances
            ->map(function (Attendance $attendance): array {
                $workDate = CarbonImmutable::parse(
                    $attendance->getRawOriginal('work_date'),
                    'Asia/Tokyo'
                );

                $clockIn = $attendance->getRawOriginal('clock_in_at');
                $clockOut = $attendance->getRawOriginal('clock_out_at');

                $breakSeconds = $attendance->breaks->sum(
                    fn ($break) => $this->secondsBetween(
                        $break->getRawOriginal('started_at'),
                        $break->getRawOriginal('ended_at')
                    )
                );

                $workSeconds = $clockOut
                    ? max(
                        0,
                        $this->secondsBetween($clockIn, $clockOut)
                            - $breakSeconds
                    )
                    : null;

                return [
                    'id' => $attendance->id,
                    'date' => $workDate->locale('ja')->isoFormat('MM/DD(ddd)'),
                    'clock_in' => $this->jstTime($clockIn),
                    'clock_out' => $this->jstTime($clockOut),
                    'total_break_time' => $this->duration($breakSeconds),
                    'total_time' => $workSeconds === null
                        ? ''
                        : $this->duration($workSeconds),
                ];
            });

        return view('user.user-attendance-list', [
            'date' => $date,
            'previousMonth' => $date->subMonth()->format('Y-m'),
            'nextMonth' => $date->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $formattedAttendanceRecords,
        ]);
    }

public function show(Request $request, int $id): View
{
    abort_if($request->user()->isAdmin(), 403);

    $attendance = Attendance::with([
        'breaks' => fn ($query) => $query->orderBy('started_at'),
    ])
        ->where('user_id', $request->user()->id)
        ->findOrFail($id);

    $pendingApplication = $attendance->correctionRequests()
        ->where('status', 'pending')
        ->latest('id')
        ->first();

    $workDate = CarbonImmutable::parse(
        $attendance->getRawOriginal('work_date'),
        'Asia/Tokyo'
    );

    $breaks = $attendance->breaks
        ->map(fn ($break): array => [
            'break_in' => $this->jstTime(
                $break->getRawOriginal('started_at')
            ),
            'break_out' => $this->jstTime(
                $break->getRawOriginal('ended_at')
            ),
        ])
        ->all();

    $data = [
        'id' => $attendance->id,
        'year' => $workDate->format('Y年'),
        'date' => $workDate->format('n月j日'),
        'clock_in' => $this->jstTime(
            $attendance->getRawOriginal('clock_in_at')
        ),
        'clock_out' => $this->jstTime(
            $attendance->getRawOriginal('clock_out_at')
        ),
        'breaks' => $breaks,
        'application' => $pendingApplication,
        'comment' => $pendingApplication?->reason ?? '',
    ];

    return view('user.user-detail', [
        'user' => $request->user(),
        'data' => $data,
    ]);
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
