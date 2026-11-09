<?php

namespace App\Http\Controllers;

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
        abort_if($request->user()->isAdmin(), 403);

        $user = $request->user();
        $now = CarbonImmutable::now('UTC');
        $jst = $now->setTimezone('Asia/Tokyo');
        $today = $jst->toDateString();

        $openAttendance = DB::table('attendances')
            ->where('user_id', $user->id)
            ->whereNull('clock_out_at')
            ->whereNotNull('clock_in_at')
            ->orderByDesc('clock_in_at')
            ->first();

        $todayAttendance = DB::table('attendances')
            ->where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        if ($openAttendance) {
            $onBreak = DB::table('break_times')
                ->where('attendance_id', $openAttendance->id)
                ->whereNull('ended_at')
                ->exists();

            $status = $onBreak ? '休憩中' : '出勤中';
        } elseif ($todayAttendance && $todayAttendance->clock_out_at) {
            $status = '退勤済';
        } else {
            $status = '勤務外';
        }

        $user->attendance_status = $status;

        return view('user.attendance-register', [
            'user' => $user,
            'formattedDate' => $jst->locale('ja')->translatedFormat('Y年n月j日(D)'),
            'formattedTime' => $jst->format('H:i'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'action' => ['required', 'in:clock_in,clock_out'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $userId = $request->user()->id;

            // 同じユーザーの同時打刻を直列化する。
            DB::table('users')
                ->where('id', $userId)
                ->lockForUpdate()
                ->first();

            $now = CarbonImmutable::now('UTC');
            $timestamp = $now->format('Y-m-d H:i:s');
            $today = $now->setTimezone('Asia/Tokyo')->toDateString();

            $openAttendance = DB::table('attendances')
                ->where('user_id', $userId)
                ->whereNotNull('clock_in_at')
                ->whereNull('clock_out_at')
                ->orderByDesc('clock_in_at')
                ->first();

            if ($validated['action'] === 'clock_in') {
                $alreadyExists = DB::table('attendances')
                    ->where('user_id', $userId)
                    ->where('work_date', $today)
                    ->exists();

                if ($openAttendance || $alreadyExists) {
                    throw ValidationException::withMessages([
                        'action' => '本日の出勤はすでに記録されています。',
                    ]);
                }

                DB::table('attendances')->insert([
                    'user_id' => $userId,
                    'work_date' => $today,
                    'clock_in_at' => $timestamp,
                    'clock_out_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                return;
            }

            if (! $openAttendance) {
                throw ValidationException::withMessages([
                    'action' => '出勤記録がないため退勤できません。',
                ]);
            }

            $onBreak = DB::table('break_times')
                ->where('attendance_id', $openAttendance->id)
                ->whereNull('ended_at')
                ->exists();

            if ($onBreak) {
                throw ValidationException::withMessages([
                    'action' => '休憩中は退勤できません。',
                ]);
            }

            DB::table('attendances')
                ->where('id', $openAttendance->id)
                ->update([
                    'clock_out_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
        });

        return redirect()->route('attendance.index');
    }
}
