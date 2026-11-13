<?php

namespace App\Http\Requests\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'update',
            $this->route('attendanceRecord')
        ) ?? false;
    }

    public function rules(): array
    {
        $attendance = $this->route('attendanceRecord');

        return [
            'date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendances', 'work_date')
                    ->where('user_id', $attendance->user_id)
                    ->ignore($attendance->id),
            ],
            'clock_in' => [
                'sometimes',
                'required',
                'date_format:H:i:s',
            ],
            'clock_out' => [
                'sometimes',
                'nullable',
                'date_format:H:i:s',
            ],
            'comment' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => '勤怠日は必須です。',
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'date.unique' => 'この日付の勤怠は既に登録されています。',
            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (
                ! $this->exists('clock_in')
                && ! $this->exists('clock_out')
            ) {
                return;
            }

            $attendance = $this->route('attendanceRecord');

            $oldDate = $attendance->work_date->format('Y-m-d');
            $newDate = $this->input('date', $oldDate);

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

            $clockIn = $this->exists('clock_in')
                ? CarbonImmutable::parse(
                    $newDate.' '.$this->input('clock_in'),
                    'Asia/Tokyo'
                )
                : $existingUtc($attendance->clock_in_at)
                    ?->addDays($dayShift);

            $clockOut = $this->exists('clock_out')
                ? (
                    $this->input('clock_out') === null
                        ? null
                        : CarbonImmutable::parse(
                            $newDate.' '.$this->input('clock_out'),
                            'Asia/Tokyo'
                        )
                )
                : $existingUtc($attendance->clock_out_at)
                    ?->addDays($dayShift);

            if (
                $clockOut !== null
                && (
                    $clockIn === null
                    || $clockOut->lessThanOrEqualTo($clockIn)
                )
            ) {
                $validator->errors()->add(
                    'clock_out',
                    '退勤時刻は出勤時刻より後の時刻を指定してください。'
                );
            }
        });
    }
}
