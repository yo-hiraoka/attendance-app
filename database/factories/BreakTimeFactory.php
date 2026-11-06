<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\BreakTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BreakTime>
 */
class BreakTimeFactory extends Factory
{
    protected $model = BreakTime::class;

    public function definition(): array
    {
        return [
            'attendance_id' => Attendance::factory(),
            'started_at' => CarbonImmutable::parse(
                '2026-11-02 12:00:00',
                'Asia/Tokyo'
            )->utc()->format('Y-m-d H:i:s'),
            'ended_at' => CarbonImmutable::parse(
                '2026-11-02 13:00:00',
                'Asia/Tokyo'
            )->utc()->format('Y-m-d H:i:s'),
        ];
    }
}
