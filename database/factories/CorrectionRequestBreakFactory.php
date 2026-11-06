<?php

namespace Database\Factories;

use App\Models\CorrectionRequestBreak;
use App\Models\StampCorrectionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CorrectionRequestBreak>
 */
class CorrectionRequestBreakFactory extends Factory
{
    protected $model = CorrectionRequestBreak::class;

    public function definition(): array
    {
        return [
            'correction_request_id' => StampCorrectionRequest::factory(),
            'started_at' => '2026-11-02 03:00:00',
            'ended_at' => '2026-11-02 04:00:00',
        ];
    }
}
