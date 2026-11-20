<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubmissionSampleSeeder extends Seeder
{
    public function run(): void
    {
        $emails = [
            'user1@example.com',
            'user2@example.com',
            'user3@example.com',
        ];

        DB::transaction(function () use ($emails): void {
            if (User::whereIn('email', $emails)->exists()) {
                $this->command?->warn(
                    '採点用ユーザーが既に存在するため、データ投入を中止しました。'
                );

                return;
            }

            $users = [];

            foreach ($emails as $index => $email) {
                $users[] = User::factory()->create([
                    'name' => '採点用ユーザー'.($index + 1),
                    'email' => $email,
                    'email_verified_at' => now('UTC'),
                    'password' => 'password',
                    'role' => $index === 2 ? 'admin' : 'user',
                ]);
            }

            $today = CarbonImmutable::now('Asia/Tokyo');
            $monthStart = $today->startOfMonth();

            for ($offset = 5; $offset >= 1; $offset--) {
                $dates = $this->firstWeekdays(
                    $monthStart->subMonths($offset),
                    15
                );

                foreach ($dates as $date) {
                    $this->createShift($users[0], $date, '09:00', '18:00');
                }
            }

            $patterns = array_merge(
                array_fill(0, 10, ['09:00', '18:00']),
                array_fill(0, 3, ['09:00', '20:00']),
                array_fill(0, 2, ['09:30', '18:00']),
                [['09:00', '17:00'], ['08:00', '21:00']]
            );

            foreach ($this->firstWeekdays($monthStart, 17) as $index => $date) {
                [$clockIn, $clockOut] = $patterns[$index];
                $this->createShift($users[0], $date, $clockIn, $clockOut);
            }

            $exampleDays = $this->firstWeekdays(
                $monthStart->subMonth(),
                5
            );

            foreach ([$users[1], $users[2]] as $user) {
                foreach ($exampleDays as $date) {
                    $this->createShift($user, $date, '09:00', '18:00');
                }
            }

            $this->command?->info('採点用ダミーデータを作成しました。');
        });
    }

    /**
     * @return list<string>
     */
    private function firstWeekdays(CarbonImmutable $monthStart, int $count): array
    {
        $dates = [];
        $day = $monthStart;

        while (count($dates) < $count) {
            if ($day->isWeekday()) {
                $dates[] = $day->toDateString();
            }

            $day = $day->addDay();
        }

        return $dates;
    }

    private function createShift(
        User $user,
        string $date,
        string $clockIn,
        string $clockOut
    ): void {
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'clock_in_at' => $this->toUtc($date, $clockIn),
            'clock_out_at' => $this->toUtc($date, $clockOut),
        ]);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'started_at' => $this->toUtc($date, '12:00'),
            'ended_at' => $this->toUtc($date, '13:00'),
        ]);
    }

    private function toUtc(string $date, string $time): string
    {
        return CarbonImmutable::parse(
            $date.' '.$time.':00',
            'Asia/Tokyo'
        )->utc()->format('Y-m-d H:i:s');
    }
}
