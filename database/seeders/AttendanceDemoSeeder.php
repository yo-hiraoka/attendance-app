<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\CorrectionRequestBreak;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $emails = [
            'demo-user1@example.com',
            'demo-user2@example.com',
            'demo-admin@example.com',
        ];

        if (User::whereIn('email', $emails)->exists()) {
            $this->command?->warn(
                'デモ用ユーザーが存在するため、投入を中止しました。'
            );

            return;
        }

        DB::transaction(function (): void {
            $user1 = User::factory()->create([
                'name' => 'デモユーザー1',
                'email' => 'demo-user1@example.com',
            ]);

            $user2 = User::factory()->create([
                'name' => 'デモユーザー2',
                'email' => 'demo-user2@example.com',
            ]);

            $admin = User::factory()->create([
                'name' => 'デモ管理者',
                'email' => 'demo-admin@example.com',
                'role' => 'admin',
            ]);

            $attendance1 = Attendance::factory()->create([
                'user_id' => $user1->id,
                'work_date' => '2026-11-02',
                'clock_in_at' => '2026-11-02 00:00:00',
                'clock_out_at' => '2026-11-02 09:00:00',
            ]);

            Attendance::factory()->create([
                'user_id' => $user1->id,
                'work_date' => '2026-11-03',
                'clock_in_at' => '2026-11-03 00:00:00',
                'clock_out_at' => '2026-11-03 09:00:00',
            ]);

            $attendance2 = Attendance::factory()->create([
                'user_id' => $user2->id,
                'work_date' => '2026-11-02',
                'clock_in_at' => '2026-11-02 00:00:00',
                'clock_out_at' => '2026-11-02 09:00:00',
            ]);

            BreakTime::factory()->create([
                'attendance_id' => $attendance1->id,
                'started_at' => '2026-11-02 03:00:00',
                'ended_at' => '2026-11-02 03:30:00',
            ]);

            BreakTime::factory()->create([
                'attendance_id' => $attendance1->id,
                'started_at' => '2026-11-02 06:00:00',
                'ended_at' => '2026-11-02 06:15:00',
            ]);

            BreakTime::factory()->create([
                'attendance_id' => $attendance2->id,
                'started_at' => '2026-11-02 03:00:00',
                'ended_at' => '2026-11-02 04:00:00',
            ]);

            $pending = StampCorrectionRequest::factory()->create([
                'attendance_id' => $attendance1->id,
                'user_id' => $user1->id,
                'requested_clock_in_at' => '2026-11-01 23:30:00',
                'requested_clock_out_at' => '2026-11-02 09:00:00',
                'reason' => '出勤打刻時刻の修正をお願いします。',
                'status' => 'pending',
            ]);

            CorrectionRequestBreak::factory()->create([
                'correction_request_id' => $pending->id,
                'started_at' => '2026-11-02 03:00:00',
                'ended_at' => '2026-11-02 03:30:00',
            ]);

            $approved = StampCorrectionRequest::factory()
                ->approved()
                ->create([
                    'attendance_id' => $attendance2->id,
                    'user_id' => $user2->id,
                    'requested_clock_in_at' => '2026-11-02 00:00:00',
                    'requested_clock_out_at' => '2026-11-02 09:30:00',
                    'reason' => '退勤時刻を修正してください。',
                ]);

            $approved->forceFill([
                'approved_by' => $admin->id,
                'approved_at' => '2026-11-05 01:00:00',
            ])->save();

            CorrectionRequestBreak::factory()->create([
                'correction_request_id' => $approved->id,
                'started_at' => '2026-11-02 03:00:00',
                'ended_at' => '2026-11-02 04:00:00',
            ]);
        });

        $this->command?->info('勤怠デモデータの投入が完了しました。');
    }
}
