<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $workDate = fake()->dateTimeBetween('-30 days', '-1 day')
            ->format('Y-m-d');

        $clockIn = CarbonImmutable::parse(
            $workDate.' 09:00:00',
            'Asia/Tokyo'
        )->utc();

        return [
            'user_id' => User::factory(),
            'work_date' => $workDate,
            'clock_in_at' => $clockIn->format('Y-m-d H:i:s'),
            'clock_out_at' => $clockIn->addHours(9)
                ->format('Y-m-d H:i:s'),
        ];
    }
}

