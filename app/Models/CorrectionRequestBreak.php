<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CorrectionRequestBreak extends Model
{
    use HasFactory;
    protected $fillable = [
        'correction_request_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function correctionRequest(): BelongsTo
    {
        return $this->belongsTo(
            StampCorrectionRequest::class,
            'correction_request_id'
        );
    }
}
