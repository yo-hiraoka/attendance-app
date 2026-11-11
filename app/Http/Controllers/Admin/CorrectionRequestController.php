<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StampCorrectionRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CorrectionRequestController extends Controller
{
    public function index(): View
    {
        $applications = StampCorrectionRequest::query()
            ->with(['user', 'attendance'])
            ->whereIn('status', ['pending', 'approved'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (StampCorrectionRequest $application): array {
                $workDate = CarbonImmutable::parse(
                    $application->attendance->getRawOriginal('work_date'),
                    'Asia/Tokyo'
                );

                return [
                    'id' => $application->id,
                    'status' => $application->status,
                    'status_label' => $application->status === 'approved'
                        ? '承認済み'
                        : '承認待ち',
                    'name' => $application->user->name,
                    'date' => $workDate->format('Y/m/d'),
                    'reason' => $application->reason,
                    'created_at' => CarbonImmutable::parse(
                        $application->getRawOriginal('created_at'),
                        'UTC'
                    )->setTimezone('Asia/Tokyo')->format('Y/m/d H:i'),
                ];
            });

        return view('admin.admin-application-list', [
            'applications' => $applications,
        ]);
    }

    public function show(int $id): View
    {
        $application = StampCorrectionRequest::query()
            ->with([
                'user',
                'attendance',
                'proposalBreaks' => fn ($query) => $query
                    ->orderBy('started_at'),
            ])
            ->findOrFail($id);

        $workDate = CarbonImmutable::parse(
            $application->attendance->getRawOriginal('work_date'),
            'Asia/Tokyo'
        );

        $breaks = $application->proposalBreaks
            ->map(fn ($break): array => [
                'break_in' => $this->jstTime(
                    $break->getRawOriginal('started_at')
                ),
                'break_out' => $this->jstTime(
                    $break->getRawOriginal('ended_at')
                ),
            ])
            ->all();

        return view('admin.admin-application-detail', [
            'user' => $application->user,
            'application' => [
                'id' => $application->id,
                'year' => $workDate->format('Y年'),
                'date' => $workDate->format('n月j日'),
                'clock_in' => $this->jstTime(
                    $application->getRawOriginal('requested_clock_in_at')
                ),
                'clock_out' => $this->jstTime(
                    $application->getRawOriginal('requested_clock_out_at')
                ),
                'breaks' => $breaks,
                'reason' => $application->reason,
                'status' => $application->status,
            ],
        ]);
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        DB::transaction(function () use ($request, $id): void {
            $application = StampCorrectionRequest::query()
                ->findOrFail($id);

            // 他の勤怠更新処理と同じ利用者行を最初にロックする。
            DB::table('users')
                ->where('id', $application->user_id)
                ->lockForUpdate()
                ->first();

            $application = StampCorrectionRequest::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($application->status !== 'pending') {
                throw ValidationException::withMessages([
                    'approval' => 'この申請はすでに承認されています。',
                ]);
            }

            $attendance = DB::table('attendances')
                ->where('id', $application->attendance_id)
                ->lockForUpdate()
                ->first();

            if (
                $attendance === null
                || (int) $attendance->user_id !== (int) $application->user_id
            ) {
                throw ValidationException::withMessages([
                    'approval' => '対象の勤怠情報を確認できません。',
                ]);
            }

            $clockIn = $application->getRawOriginal(
                'requested_clock_in_at'
            );
            $clockOut = $application->getRawOriginal(
                'requested_clock_out_at'
            );

            if (
                $clockIn === null
                || $clockOut === null
                || $clockOut <= $clockIn
            ) {
                throw ValidationException::withMessages([
                    'approval' => '申請された出退勤時刻が不正です。',
                ]);
            }

            $proposalBreaks = DB::table('correction_request_breaks')
                ->where('correction_request_id', $application->id)
                ->orderBy('started_at')
                ->get();

            $previousEnd = null;

            foreach ($proposalBreaks as $break) {
                if (
                    $break->started_at === null
                    || $break->ended_at === null
                    || $break->started_at < $clockIn
                    || $break->ended_at > $clockOut
                    || $break->ended_at <= $break->started_at
                    || (
                        $previousEnd !== null
                        && $break->started_at < $previousEnd
                    )
                ) {
                    throw ValidationException::withMessages([
                        'approval' => '申請された休憩時刻が不正です。',
                    ]);
                }

                $previousEnd = $break->ended_at;
            }

            $now = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');

            DB::table('attendances')
                ->where('id', $attendance->id)
                ->update([
                    'clock_in_at' => $clockIn,
                    'clock_out_at' => $clockOut,
                    'note' => $application->reason,
                    'updated_at' => $now,
                ]);

            DB::table('break_times')
                ->where('attendance_id', $attendance->id)
                ->delete();

            foreach ($proposalBreaks as $break) {
                DB::table('break_times')->insert([
                    'attendance_id' => $attendance->id,
                    'started_at' => $break->started_at,
                    'ended_at' => $break->ended_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('stamp_correction_requests')
                ->where('id', $application->id)
                ->update([
                    'status' => 'approved',
                    'approved_by' => $request->user()->id,
                    'approved_at' => $now,
                    'updated_at' => $now,
                ]);
        });

        return redirect()->route('admin.correction.show', [
            'id' => $id,
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
}
