<?php

namespace App\Http\Requests\User;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || $user->isAdmin()) {
            return false;
        }

        // Validate ownership before input rules, preserving the existing 404 response.
        Attendance::query()
            ->where('user_id', $user->id)
            ->findOrFail((int) $this->route('id'));

        return true;
    }

    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i'],
            'new_break_in' => ['sometimes', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i'],
            'new_break_out' => ['sometimes', 'array'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],
            'comment' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_clock_in.date_format' => '出勤時刻はHH:mm形式で入力してください',
            'new_clock_out.date_format' => '退勤時刻はHH:mm形式で入力してください',
            'new_break_in.*.date_format' => '休憩開始時刻はHH:mm形式で入力してください',
            'new_break_out.*.date_format' => '休憩終了時刻はHH:mm形式で入力してください',
            'comment.required' => '備考を記入してください',
        ];
    }
}
