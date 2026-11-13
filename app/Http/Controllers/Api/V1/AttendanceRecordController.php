<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\Attendance;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AttendanceRecordController extends Controller
{
    public function index(
        IndexAttendanceRecordRequest $request
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        $query = Attendance::query()
            ->with(['user', 'breaks']);

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['date'])) {
            $query->whereDate('work_date', $filters['date']);
        }

        if (isset($filters['month'])) {
            $query->where(
                'work_date',
                'like',
                $filters['month'].'%'
            );
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        $attendances = $query
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return AttendanceRecordResource::collection($attendances);
    }

    public function show(
        Attendance $attendanceRecord
    ): AttendanceRecordResource {
        $attendanceRecord->load([
            'user',
            'breaks',
            'correctionRequests',
        ]);

        return new AttendanceRecordResource($attendanceRecord);
    }

    public function store(
        StoreAttendanceRecordRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $toUtc = static function (string $date, string $time): string {
            return CarbonImmutable::parse(
                $date.' '.$time,
                'Asia/Tokyo'
            )->utc()->format('Y-m-d H:i:s');
        };

        $attendance = $request->user()->attendances()->create([
            'work_date' => $data['date'],
            'clock_in_at' => $toUtc($data['date'], $data['clock_in']),
            'clock_out_at' => isset($data['clock_out'])
                ? $toUtc($data['date'], $data['clock_out'])
                : null,
            'note' => $data['comment'] ?? null,
        ]);

        $attendance->load(['user', 'breaks']);

        return (new AttendanceRecordResource($attendance))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateAttendanceRecordRequest $request,
        Attendance $attendanceRecord
    ): AttendanceRecordResource {
        $this->authorize('update', $attendanceRecord);

        $data = $request->validated();

        $oldDate = $attendanceRecord->work_date->format('Y-m-d');
        $newDate = $data['date'] ?? $oldDate;

        $dayShift = (int) CarbonImmutable::parse($oldDate, 'UTC')
            ->diffInDays(
                CarbonImmutable::parse($newDate, 'UTC'),
                false
            );

        $existingUtc = static function ($value): ?CarbonImmutable {
            if ($value === null) {
                return null;
            }

            return CarbonImmutable::parse(
                $value->format('Y-m-d H:i:s'),
                'UTC'
            );
        };

        $toUtc = static function (
            string $date,
            string $time
        ): CarbonImmutable {
            return CarbonImmutable::parse(
                $date.' '.$time,
                'Asia/Tokyo'
            )->utc();
        };

        $updates = [];

        if (array_key_exists('date', $data)) {
            $updates['work_date'] = $newDate;
        }

        if ($dayShift !== 0) {
            $updates['clock_in_at'] = $existingUtc(
                $attendanceRecord->clock_in_at
            )?->addDays($dayShift)->format('Y-m-d H:i:s');

            $updates['clock_out_at'] = $existingUtc(
                $attendanceRecord->clock_out_at
            )?->addDays($dayShift)->format('Y-m-d H:i:s');
        }

        if (array_key_exists('clock_in', $data)) {
            $updates['clock_in_at'] = $toUtc(
                $newDate,
                $data['clock_in']
            )->format('Y-m-d H:i:s');
        }

        if (array_key_exists('clock_out', $data)) {
            $updates['clock_out_at'] = $data['clock_out'] === null
                ? null
                : $toUtc(
                    $newDate,
                    $data['clock_out']
                )->format('Y-m-d H:i:s');
        }

        if (array_key_exists('comment', $data)) {
            $updates['note'] = $data['comment'];
        }

        DB::transaction(function () use (
            $attendanceRecord,
            $updates,
            $dayShift,
            $existingUtc
        ): void {
            $attendanceRecord->update($updates);

            if ($dayShift === 0) {
                return;
            }

            foreach ($attendanceRecord->breaks as $break) {
                $break->update([
                    'started_at' => $existingUtc(
                        $break->started_at
                    )?->addDays($dayShift)->format('Y-m-d H:i:s'),

                    'ended_at' => $existingUtc(
                        $break->ended_at
                    )?->addDays($dayShift)->format('Y-m-d H:i:s'),
                ]);
            }

            foreach (
                $attendanceRecord->correctionRequests()
                    ->with('proposalBreaks')
                    ->get() as $correction
            ) {
                $correction->update([
                    'requested_clock_in_at' => $existingUtc(
                        $correction->requested_clock_in_at
                    )?->addDays($dayShift)->format('Y-m-d H:i:s'),

                    'requested_clock_out_at' => $existingUtc(
                        $correction->requested_clock_out_at
                    )?->addDays($dayShift)->format('Y-m-d H:i:s'),
                ]);

                foreach ($correction->proposalBreaks as $proposalBreak) {
                    $proposalBreak->update([
                        'started_at' => $existingUtc(
                            $proposalBreak->started_at
                        )?->addDays($dayShift)->format('Y-m-d H:i:s'),

                        'ended_at' => $existingUtc(
                            $proposalBreak->ended_at
                        )?->addDays($dayShift)->format('Y-m-d H:i:s'),
                    ]);
                }
            }

        });

        $attendanceRecord->refresh()->load([
            'user',
            'breaks',
            'correctionRequests',
        ]);

        return new AttendanceRecordResource($attendanceRecord);
    }

    public function destroy(Attendance $attendanceRecord): Response
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}
