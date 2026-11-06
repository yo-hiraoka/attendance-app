<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correction_request_breaks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('correction_request_id')
                ->constrained('stamp_correction_requests')
                ->cascadeOnDelete();

            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correction_request_breaks');
    }
};
