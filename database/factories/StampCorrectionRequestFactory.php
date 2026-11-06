<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\StampCorrectionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StampCorrectionRequest>
 */
class StampCorrectionRequestFactory extends Factory
{
    protected $model = StampCorrectionRequest::class;

    public function definition(): array
    {
        return [
            'attendance_id' => Attendance::factory(),
            'user_id' => function (array $attributes) {
                return Attendance::findOrFail(
                    $attributes['attendance_id']
                )->user_id;
            },
            'requested_clock_in_at' => null,
            'requested_clock_out_at' => null,
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'status' => 'approved',
        ]);
    }
}
