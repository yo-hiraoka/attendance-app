<?php

namespace App\Models;

use App\Models\Concerns\UsesUtcTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Date;

class StampCorrectionRequest extends Model
{
    use HasFactory, UsesUtcTimestamps;

    protected $fillable = [
        'attendance_id',
        'user_id',
        'requested_clock_in_at',
        'requested_clock_out_at',
        'reason',
        'status',
    ];

    protected $casts = [
        'requested_clock_in_at' => 'datetime',
        'requested_clock_out_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected function approvedAt(): Attribute
    {
        return Attribute::get(fn ($value) => $value === null ? null : Date::parse($value, 'UTC'))->withoutObjectCaching();
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function proposalBreaks(): HasMany
    {
        return $this->hasMany(
            CorrectionRequestBreak::class,
            'correction_request_id'
        );
    }
}
