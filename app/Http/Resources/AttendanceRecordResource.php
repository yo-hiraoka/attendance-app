<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $toJst = static function ($value): ?CarbonImmutable {
            if ($value === null) {
                return null;
            }

            return CarbonImmutable::parse(
                $value->format('Y-m-d H:i:s'),
                'UTC'
            )->setTimezone('Asia/Tokyo');
        };

        $formatDuration = static function (int $seconds): string {
            $minutes = intdiv(max(0, $seconds), 60);

            return sprintf(
                '%02d:%02d',
                intdiv($minutes, 60),
                $minutes % 60
            );
        };

        $clockIn = $toJst($this->clock_in_at);
        $clockOut = $toJst($this->clock_out_at);

        $totalBreakSeconds = 0;

        if ($this->resource->relationLoaded('breaks')) {
            foreach ($this->breaks as $break) {
                if ($break->started_at && $break->ended_at) {
                    $totalBreakSeconds += max(
                        0,
                        (int) $break->started_at->diffInSeconds(
                            $break->ended_at
                        )
                    );
                }
            }
        }

        $totalWorkSeconds = null;

        if ($clockIn !== null && $clockOut !== null) {
            $totalWorkSeconds = max(
                0,
                (int) $clockIn->diffInSeconds($clockOut)
                - $totalBreakSeconds
            );
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded(
                'user',
                fn () => $this->user?->name
            ),
            'date' => $this->work_date->format('Y-m-d'),
            'clock_in' => $clockIn?->format('H:i:s'),
            'clock_out' => $clockOut?->format('H:i:s'),
            'total_time' => $totalWorkSeconds === null
                ? null
                : $formatDuration($totalWorkSeconds),
            'total_break_time' => $formatDuration($totalBreakSeconds),
            'comment' => $this->note,
            'breaks' => $this->whenLoaded(
                'breaks',
                fn () => $this->breaks->map(
                    fn ($break): array => [
                        'id' => $break->id,
                        'start' => $toJst(
                            $break->started_at
                        )?->toIso8601String(),
                        'end' => $toJst(
                            $break->ended_at
                        )?->toIso8601String(),
                    ]
                )->all()
            ),
            'user' => $this->whenLoaded(
                'user',
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ]
            ),
            'correction_requests' => $this->whenLoaded(
                'correctionRequests',
                fn () => $this->correctionRequests->map(
                    fn ($correction): array => [
                        'id' => $correction->id,
                        'status' => $correction->status,
                        'reason' => $correction->reason,
                        'requested_clock_in' => $toJst(
                            $correction->requested_clock_in_at
                        )?->toIso8601String(),
                        'requested_clock_out' => $toJst(
                            $correction->requested_clock_out_at
                        )?->toIso8601String(),
                    ]
                )->all()
            ),
        ];
    }
}
