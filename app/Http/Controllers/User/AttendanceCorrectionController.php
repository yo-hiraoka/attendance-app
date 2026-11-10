<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionController extends Controller
{
    public function store(Request $request, int $id): RedirectResponse
    {
        abort_if($request->user()->isAdmin(), 403);

        $attendance = Attendance::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i'],
            'new_break_in' => ['sometimes', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i'],
            'new_break_out' => ['sometimes', 'array'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],
            'comment' => ['required', 'string'],
        ]);

        // 日付は元の勤務日を使用する。利用者による日付変更は許可しない。
        $workDate = CarbonImmutable::parse(
            $attendance->getRawOriginal('work_date'),
            'Asia/Tokyo'
        )->toDateString();

        $clockIn = CarbonImmutable::parse(
            $workDate . ' ' . $validated['new_clock_in'],
            'Asia/Tokyo'
        );

        $clockOut = CarbonImmutable::parse(
            $workDate . ' ' . $validated['new_clock_out'],
            'Asia/Tokyo'
        );

        if ($clockOut->equalTo($clockIn)) {
            throw ValidationException::withMessages([
                'new_clock_out' => '出勤時刻と退勤時刻を確認してください。',
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
                    'new_break_in.' . $index =>
                        '休憩の開始時刻と終了時刻を両方入力してください。',
                ]);
            }

            $found = null;

            // 出勤日と翌日の両方を試し、勤務時間内の休憩を特定する。
            foreach ([0, 1] as $startOffset) {
                $breakIn = CarbonImmutable::parse(
                    $workDate . ' ' . $in,
                    'Asia/Tokyo'
                )->addDays($startOffset);

                foreach ([0, 1] as $endOffset) {
                    $breakOut = CarbonImmutable::parse(
                        $workDate . ' ' . $out,
                        'Asia/Tokyo'
                    )->addDays($endOffset);

                    if (
                        $breakIn->greaterThanOrEqualTo($clockIn)
                        && $breakOut->greaterThan($breakIn)
                        && $breakOut->lessThanOrEqualTo($clockOut)
                    ) {
                        $found = [
                            'start' => $breakIn,
                            'end' => $breakOut,
                        ];
                        break 2;
                    }
                }
            }

            if ($found === null) {
                throw ValidationException::withMessages([
                    'new_break_in.' . $index =>
                        '休憩時間が勤務時間内になるよう入力してください。',
                ]);
            }

            $breaks[] = $found;
        }

        usort(
            $breaks,
            fn ($a, $b) => $a['start']->getTimestamp()
                <=> $b['start']->getTimestamp()
        );

        for ($i = 1; $i < count($breaks); $i++) {
            if ($breaks[$i]['start']->lessThan($breaks[$i - 1]['end'])) {
                throw ValidationException::withMessages([
                    'new_break_in' => '休憩時間が重複しています。',
                ]);
            }
        }

        DB::transaction(function () use (
            $request,
            $attendance,
            $clockIn,
            $clockOut,
            $breaks,
            $validated
        ): void {
            // 同一利用者の申請を直列化して二重申請を防ぐ。
            DB::table('users')
                ->where('id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            $pendingExists = DB::table('stamp_correction_requests')
                ->where('attendance_id', $attendance->id)
                ->where('status', 'pending')
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'comment' => 'この勤怠には承認待ちの申請があります。',
                ]);
            }

            $now = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');

            $requestId = DB::table('stamp_correction_requests')
                ->insertGetId([
                    'attendance_id' => $attendance->id,
                    'user_id' => $request->user()->id,
                    'requested_clock_in_at' => $clockIn
                        ->utc()->format('Y-m-d H:i:s'),
                    'requested_clock_out_at' => $clockOut
                        ->utc()->format('Y-m-d H:i:s'),
                    'reason' => $validated['comment'],
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            foreach ($breaks as $break) {
                DB::table('correction_request_breaks')->insert([
                    'correction_request_id' => $requestId,
                    'started_at' => $break['start']
                        ->utc()->format('Y-m-d H:i:s'),
                    'ended_at' => $break['end']
                        ->utc()->format('Y-m-d H:i:s'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return redirect()->route('attendance.show', [
            'id' => $attendance->id,
        ]);
    }
}
