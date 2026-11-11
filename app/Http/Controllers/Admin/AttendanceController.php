<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ]);

        $date = CarbonImmutable::parse(
            $validated['date']
                ?? CarbonImmutable::now('Asia/Tokyo')->toDateString(),
            'Asia/Tokyo'
        );

        $records = Attendance::with(['user', 'breaks'])
            ->whereDate('work_date', $date->toDateString())
            ->orderBy('user_id')
            ->get();

        $attendanceRecords = $records->map(function (Attendance $attendance) {
            $clockIn = $attendance->getRawOriginal('clock_in_at');
            $clockOut = $attendance->getRawOriginal('clock_out_at');

            $breakSeconds = $attendance->breaks->sum(
                fn ($break) => $this->secondsBetween(
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
                'name' => $attendance->user->name,
                'clock_in' => $this->jstTime($clockIn),
                'clock_out' => $this->jstTime($clockOut),
                'total_break_time' => $this->duration($breakSeconds),
                'total_time' => $workSeconds === null
                    ? ''
                    : $this->duration($workSeconds),
            ];
        });

        return view('admin.admin-attendance-list', [
            'date' => $date,
            'previousDay' => $date->subDay()->toDateString(),
            'nextDay' => $date->addDay()->toDateString(),
            'attendanceRecords' => $attendanceRecords,
        ]);
    }

    public function show(int $id): View
    {
        $attendance = Attendance::with([
            'user',
            'breaks' => fn ($query) => $query->orderBy('started_at'),
        ])->findOrFail($id);

        $workDate = CarbonImmutable::parse(
            $attendance->getRawOriginal('work_date'),
            'Asia/Tokyo'
        );

        return view('admin.admin-detail', [
            'user' => $attendance->user,
            'attendanceRecord' => [
                'id' => $attendance->id,
                'year' => $workDate->format('Y年'),
                'date' => $workDate->format('n月j日'),
                'clock_in' => $this->jstTime(
                    $attendance->getRawOriginal('clock_in_at')
                ),
                'clock_out' => $this->jstTime(
                    $attendance->getRawOriginal('clock_out_at')
                ),
                'breaks' => $attendance->breaks->map(
                    fn ($break) => [
                        'break_in' => $this->jstTime(
                            $break->getRawOriginal('started_at')
                        ),
                        'break_out' => $this->jstTime(
                            $break->getRawOriginal('ended_at')
                        ),
                    ]
                )->all(),
                'comment' => $attendance->note ?? '',
            ],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i'],
            'new_break_in' => ['sometimes', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i'],
            'new_break_out' => ['sometimes', 'array'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],
            'comment' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($id, $validated): void {
            $attendance = Attendance::query()->findOrFail($id);

            // ユーザーごとの打刻処理と同じロックを利用する。
            DB::table('users')
                ->where('id', $attendance->user_id)
                ->lockForUpdate()
                ->first();

            $attendance = Attendance::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($attendance->correctionRequests()
                ->where('status', 'pending')
                ->exists()) {
                throw ValidationException::withMessages([
                    'comment' => '承認待ちの修正申請があるため、直接修正できません。',
                ]);
            }

            if ($attendance->getRawOriginal('clock_out_at') === null) {
                throw ValidationException::withMessages([
                    'new_clock_out' => '退勤済みの勤怠を修正してください。',
                ]);
            }

            $workDate = CarbonImmutable::parse(
                $attendance->getRawOriginal('work_date'),
                'Asia/Tokyo'
            )->toDateString();

            $clockIn = CarbonImmutable::parse(
                $workDate.' '.$validated['new_clock_in'],
                'Asia/Tokyo'
            );

            $clockOut = CarbonImmutable::parse(
                $workDate.' '.$validated['new_clock_out'],
                'Asia/Tokyo'
            );

            if ($clockOut->equalTo($clockIn)) {
                throw ValidationException::withMessages([
                    'new_clock_out' => '出勤・退勤時刻を確認してください。',
                ]);
            }

            if ($clockOut->lessThan($clockIn)) {
                $clockOut = $clockOut->addDay();
            }

            $breakIns = $validated['new_break_in'] ?? [];
            $breakOuts = $validated['new_break_out'] ?? [];

            $indexes = array_unique(array_merge(
                array_keys($breakIns),
                array_keys($breakOuts)
            ));

            sort($indexes);
            $breaks = [];

            foreach ($indexes as $index) {
                $in = $breakIns[$index] ?? null;
                $out = $breakOuts[$index] ?? null;

                if ($in === null && $out === null) {
                    continue;
                }

                if ($in === null || $out === null) {
                    throw ValidationException::withMessages([
                        'new_break_in.'.$index =>
                            '休憩の開始と終了を両方入力してください。',
                    ]);
                }

                $found = null;

                foreach ([0, 1] as $startOffset) {
                    $start = CarbonImmutable::parse(
                        $workDate.' '.$in,
                        'Asia/Tokyo'
                    )->addDays($startOffset);

                    foreach ([0, 1] as $endOffset) {
                        $end = CarbonImmutable::parse(
                            $workDate.' '.$out,
                            'Asia/Tokyo'
                        )->addDays($endOffset);

                        if (
                            $start->greaterThanOrEqualTo($clockIn)
                            && $end->greaterThan($start)
                            && $end->lessThanOrEqualTo($clockOut)
                        ) {
                            $found = [$start, $end];
                            break 2;
                        }
                    }
                }

                if ($found === null) {
                    throw ValidationException::withMessages([
                        'new_break_in.'.$index =>
                            '休憩は勤務時間内に入力してください。',
                    ]);
                }

                $breaks[] = $found;
            }

            usort(
                $breaks,
                fn ($a, $b) =>
                    $a[0]->getTimestamp() <=> $b[0]->getTimestamp()
            );

            for ($i = 1; $i < count($breaks); $i++) {
                if ($breaks[$i][0]->lessThan($breaks[$i - 1][1])) {
                    throw ValidationException::withMessages([
                        'new_break_in' => '休憩時間が重複しています。',
                    ]);
                }
            }

            $now = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');

            DB::table('attendances')
                ->where('id', $attendance->id)
                ->update([
                    'clock_in_at' => $clockIn->utc()->format('Y-m-d H:i:s'),
                    'clock_out_at' => $clockOut->utc()->format('Y-m-d H:i:s'),
                    'note' => $validated['comment'],
                    'updated_at' => $now,
                ]);

            DB::table('break_times')
                ->where('attendance_id', $attendance->id)
                ->delete();

            foreach ($breaks as [$start, $end]) {
                DB::table('break_times')->insert([
                    'attendance_id' => $attendance->id,
                    'started_at' => $start->utc()->format('Y-m-d H:i:s'),
                    'ended_at' => $end->utc()->format('Y-m-d H:i:s'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return redirect()->route('admin.attendance.show', ['id' => $id]);
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

    private function secondsBetween(?string $start, ?string $end): int
    {
        if ($start === null || $end === null) {
            return 0;
        }

        $startAt = CarbonImmutable::parse($start, 'UTC');
        $endAt = CarbonImmutable::parse($end, 'UTC');

        return max(0, $endAt->getTimestamp() - $startAt->getTimestamp());
    }

    private function duration(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);

        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
